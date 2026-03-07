<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import CommentService from '../../services/CommentService';

const route = useRoute();
const comments = ref([]);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
});
const loading = ref(false);
const errorMsg = ref(null);
const slug = route.params.slug;

const reactions = ref({});
const reactionLoading = ref({});

const fetchComments = async (page = 1) => {
  loading.value = true;
  errorMsg.value = null;
  try {
    const response = await CommentService.getAllComments(slug, page);
    comments.value = response.data.data || [];

    pagination.value = {
      current_page: response.data.current_page || 1,
      last_page: response.data.last_page || 1,
      total: response.data.total || 0,
      per_page: response.data.per_page || 10,
    };

    // اختياري: لو الـ backend بيرجع حالة الـ reaction لكل تعليق
    // comments.value.forEach(c => {
    //   if (c.my_reaction) reactions.value[c.id] = c.my_reaction;
    // });
  } catch (error) {
    errorMsg.value = "حصل خطأ أثناء جلب التعليقات";
    console.error(error);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchComments(1);
});

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return;
  fetchComments(page);
};

const reactionEndpointMap = {
  support: 'support',
  exhibitions: 'Exhibitions',
  neutral: 'neutral',
};

const setReaction = async (commentId, type) => {
  if (reactionLoading.value[commentId]) return;

  const commentIndex = comments.value.findIndex(c => c.id === commentId);
  if (commentIndex === -1) return;

  const comment = comments.value[commentIndex];

  const previousReaction = reactions.value[commentId];
  const isToggle = previousReaction === type;

  // حفظ القيم القديمة للـ rollback
  const oldReaction = previousReaction;
  const oldSupport = comment.support_count || 0;
  const oldExhibitions = comment.exhibitions_count || 0;
  const oldNeutral = comment.neutral_count || 0;

  // Optimistic UI update
  reactions.value[commentId] = isToggle ? null : type;

  // تعديل العدادات بشكل آمن (باستخدام Vue.set أو مباشرة مع ref)
  if (isToggle) {
    // إلغاء التصويت
    if (previousReaction === 'support') comment.support_count = Math.max(0, oldSupport - 1);
    if (previousReaction === 'exhibitions') comment.exhibitions_count = Math.max(0, oldExhibitions - 1);
    if (previousReaction === 'neutral') comment.neutral_count = Math.max(0, oldNeutral - 1);
  } else {
    // إزالة التصويت القديم إن وجد
    if (previousReaction === 'support') comment.support_count = Math.max(0, oldSupport - 1);
    if (previousReaction === 'exhibitions') comment.exhibitions_count = Math.max(0, oldExhibitions - 1);
    if (previousReaction === 'neutral') comment.neutral_count = Math.max(0, oldNeutral - 1);

    // إضافة التصويت الجديد
    if (type === 'support') comment.support_count = (oldSupport + 1);
    if (type === 'exhibitions') comment.exhibitions_count = (oldExhibitions + 1);
    if (type === 'neutral') comment.neutral_count = (oldNeutral + 1);
  }

  reactionLoading.value[commentId] = true;

  try {
    const endpoint = reactionEndpointMap[type];
    await CommentService.reactToComment(commentId, endpoint);
    // نجح → نترك التغييرات كما هي
  } catch (error) {
    console.error('فشل في إرسال الـ reaction:', error);
    // rollback
    reactions.value[commentId] = oldReaction;
    comment.support_count = oldSupport;
    comment.exhibitions_count = oldExhibitions;
    comment.neutral_count = oldNeutral;
  } finally {
    reactionLoading.value[commentId] = false;
  }
};
</script>

<template>
  <div class="comments-section max-w-2xl mx-auto px-4 py-8" dir="rtl">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
      <div class="w-1 h-7 bg-indigo-500 rounded-full"></div>
      <h3 class="text-xl font-bold text-gray-800">التعليقات</h3>
      <span
        v-if="pagination.total > 0"
        class="text-xs bg-indigo-100 text-indigo-600 font-semibold px-2.5 py-0.5 rounded-full"
      >
        {{ pagination.total }}
      </span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex flex-col gap-3">
      <div
        v-for="n in 3" :key="n"
        class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 animate-pulse"
      >
        <div class="h-3 bg-gray-200 rounded w-3/4 mb-3"></div>
        <div class="h-3 bg-gray-200 rounded w-1/2 mb-4"></div>
        <div class="flex gap-2">
          <div class="h-7 w-20 bg-gray-200 rounded-full"></div>
          <div class="h-7 w-20 bg-gray-200 rounded-full"></div>
          <div class="h-7 w-20 bg-gray-200 rounded-full"></div>
        </div>
      </div>
    </div>

    <!-- Error -->
    <div
      v-else-if="errorMsg"
      class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-600 rounded-2xl px-5 py-4 text-sm"
    >
      <span class="text-lg">⚠️</span>
      {{ errorMsg }}
    </div>

    <!-- No comments -->
    <div v-else-if="comments.length === 0" class="text-center py-16 text-gray-400">
      <div class="text-4xl mb-3">💬</div>
      <p class="text-sm">لا توجد تعليقات بعد. كن أول من يعلّق!</p>
    </div>

    <!-- Comments list -->
    <ul v-else class="flex flex-col gap-4">
      <li
        v-for="comment in comments"
        :key="comment.id"
        class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 hover:shadow-md hover:border-indigo-100 transition-all duration-200"
      >
        <p class="text-gray-700 text-sm leading-relaxed mb-3">
          {{ comment.translation?.comment || comment.comment }}
        </p>

        <div class="flex items-center justify-between mb-4 text-xs text-gray-500">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
              {{ comment.user?.name?.charAt(0)?.toUpperCase() || '?' }}
            </div>
            <span class="font-medium">{{ comment.user?.name || 'مستخدم' }}</span>
          </div>
          <span>
            {{ new Date(comment.created_at || comment.translation?.created_at).toLocaleString('ar-EG') }}
          </span>
        </div>

        <!-- Reaction Buttons -->
        <div class="flex items-center gap-2.5 flex-wrap mt-1">
          <!-- موافق -->
          <button
            @click="setReaction(comment.id, 'support')"
            :disabled="reactionLoading[comment.id]"
            :class="[
              'flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium border transition-all duration-150 min-w-[90px] justify-center',
              reactions[comment.id] === 'support'
                ? 'bg-emerald-600 border-emerald-600 text-white shadow-sm'
                : 'bg-white border-gray-300 text-gray-700 hover:bg-emerald-50 hover:border-emerald-400',
              reactionLoading[comment.id] ? 'opacity-60 cursor-not-allowed' : ''
            ]"
          >
            <span class="text-base">👍</span>
            موافق
            <span class="text-[11px] font-semibold bg-white/30 px-1.5 py-0.5 rounded ml-1">
              {{ comment.support_count ?? 0 }}
            </span>
          </button>

          <!-- محايد -->
          <button
            @click="setReaction(comment.id, 'neutral')"
            :disabled="reactionLoading[comment.id]"
            :class="[
              'flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium border transition-all duration-150 min-w-[90px] justify-center',
              reactions[comment.id] === 'neutral'
                ? 'bg-amber-500 border-amber-500 text-white shadow-sm'
                : 'bg-white border-gray-300 text-gray-700 hover:bg-amber-50 hover:border-amber-400',
              reactionLoading[comment.id] ? 'opacity-60 cursor-not-allowed' : ''
            ]"
          >
            <span class="text-base">😐</span>
            محايد
            <span class="text-[11px] font-semibold bg-white/30 px-1.5 py-0.5 rounded ml-1">
              {{ comment.neutral_count ?? 0 }}
            </span>
          </button>

          <!-- غير موافق -->
          <button
            @click="setReaction(comment.id, 'exhibitions')"
            :disabled="reactionLoading[comment.id]"
            :class="[
              'flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium border transition-all duration-150 min-w-[110px] justify-center',
              reactions[comment.id] === 'exhibitions'
                ? 'bg-rose-600 border-rose-600 text-white shadow-sm'
                : 'bg-white border-gray-300 text-gray-700 hover:bg-rose-50 hover:border-rose-400',
              reactionLoading[comment.id] ? 'opacity-60 cursor-not-allowed' : ''
            ]"
          >
            <span class="text-base">👎</span>
            غير موافق
            <span class="text-[11px] font-semibold bg-white/30 px-1.5 py-0.5 rounded ml-1">
              {{ comment.exhibitions_count ?? 0 }}
            </span>
          </button>
        </div>
      </li>
    </ul>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="flex items-center justify-between mt-10 gap-4">
      <button
        :disabled="pagination.current_page === 1"
        @click="goToPage(pagination.current_page - 1)"
        class="px-5 py-2 rounded-xl border text-sm font-medium disabled:opacity-40 transition"
      >
        &#8594; السابق
      </button>

      <span class="text-sm">
        صفحة <strong class="text-indigo-600">{{ pagination.current_page }}</strong> من
        <strong>{{ pagination.last_page }}</strong>
      </span>

      <button
        :disabled="pagination.current_page === pagination.last_page"
        @click="goToPage(pagination.current_page + 1)"
        class="px-5 py-2 rounded-xl border text-sm font-medium disabled:opacity-40 transition"
      >
        التالي &#8592;
      </button>
    </div>
  </div>
</template>
