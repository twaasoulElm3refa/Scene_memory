<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import CommentService from '../../services/CommentService/CommentService';

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

const getCommentImageUrl = (image) => {
  const rawPath = image?.url || image?.path || '';

  if (!rawPath || typeof rawPath !== 'string') return '';

  const path = rawPath.replace(/\\/g, '/').trim();

  if (/^https?:\/\//i.test(path) || path.startsWith('/')) return path;
  if (path.startsWith('storage/')) return `/${path}`;
  if (path.startsWith('public/')) return `/storage/${path.replace(/^public\//, '')}`;

  return `/storage/${path}`;
};

// ======= Report Modal State =======
const reportModal = ref(false);
const reportCommentId = ref(null);
const reportReason = ref('');
const reportLoading = ref(false);
const reportSuccess = ref(false);
const reportError = ref('');

const reportReasons = [
  { value: 'spam',             label: 'بريد مزعج',          icon: 'SP' },
  { value: 'offensive',        label: 'محتوى مسيء',         icon: 'OF' },
  { value: 'inappropriate',    label: 'غير لائق',            icon: 'IN' },
  { value: 'illegal',          label: 'محتوى غير قانوني',    icon: 'LG' },
  { value: 'untrue',           label: 'محتوى كاذب',          icon: 'UN' },
  { value: 'False information',label: 'معلومات مضللة',       icon: 'FI' },
  { value: 'other',            label: 'سبب آخر',             icon: 'OT' },
];

const openReportModal = (commentId) => {
  reportCommentId.value = commentId;
  reportReason.value = '';
  reportSuccess.value = false;
  reportError.value = '';
  reportModal.value = true;
};

const closeReportModal = () => {
  reportModal.value = false;
  reportCommentId.value = null;
  reportReason.value = '';
  reportSuccess.value = false;
  reportError.value = '';
};

const submitReport = async () => {
  if (!reportReason.value) {
    reportError.value = 'يرجى اختيار سبب البلاغ';
    return;
  }
  reportLoading.value = true;
  reportError.value = '';
  try {
    await CommentService.reportComment(reportCommentId.value, reportReason.value);
    reportSuccess.value = true;
    setTimeout(() => closeReportModal(), 2000);
  } catch (err) {
    reportError.value = 'حدث خطأ أثناء إرسال البلاغ. حاول مرة أخرى.';
    console.error(err);
  } finally {
    reportLoading.value = false;
  }
};
// ==================================

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

  const oldReaction = previousReaction;
  const oldSupport = comment.support_count || 0;
  const oldExhibitions = comment.exhibitions_count || 0;
  const oldNeutral = comment.neutral_count || 0;

  reactions.value[commentId] = isToggle ? null : type;

  if (isToggle) {
    if (previousReaction === 'support') comment.support_count = Math.max(0, oldSupport - 1);
    if (previousReaction === 'exhibitions') comment.exhibitions_count = Math.max(0, oldExhibitions - 1);
    if (previousReaction === 'neutral') comment.neutral_count = Math.max(0, oldNeutral - 1);
  } else {
    if (previousReaction === 'support') comment.support_count = Math.max(0, oldSupport - 1);
    if (previousReaction === 'exhibitions') comment.exhibitions_count = Math.max(0, oldExhibitions - 1);
    if (previousReaction === 'neutral') comment.neutral_count = Math.max(0, oldNeutral - 1);
    if (type === 'support') comment.support_count = (oldSupport + 1);
    if (type === 'exhibitions') comment.exhibitions_count = (oldExhibitions + 1);
    if (type === 'neutral') comment.neutral_count = (oldNeutral + 1);
  }

  reactionLoading.value[commentId] = true;
  try {
    const endpoint = reactionEndpointMap[type];
    await CommentService.reactToComment(commentId, endpoint);
  } catch (error) {
    console.error('فشل في إرسال الـ reaction:', error);
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
<div class="scemory-page comments-page comments-section max-w-2xl mx-auto px-4 py-8" dir="rtl">

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
      <span class="text-xs font-bold">ERR</span>
      {{ errorMsg }}
    </div>

    <!-- No comments -->
    <div v-else-if="comments.length === 0" class="text-center py-16 text-gray-400">
      <div class="text-sm font-bold mb-3 text-[#0D4D97]">COMMENTS</div>
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

        <div
          v-if="comment.images?.length"
          :class="['grid gap-2 mb-4', comment.images.length === 1 ? 'grid-cols-1 max-w-sm' : 'grid-cols-2']"
        >
          <a
            v-for="image in comment.images.slice(0, 2)"
            :key="image.id"
            :href="getCommentImageUrl(image)"
            target="_blank"
            rel="noopener noreferrer"
            class="block overflow-hidden rounded-lg bg-gray-100"
          >
            <img
              :src="getCommentImageUrl(image)"
              alt="Comment attachment"
              class="h-32 w-full object-cover"
              loading="lazy"
            />
          </a>
        </div>

        <div class="flex items-center justify-between mb-4 text-xs text-gray-500">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
              {{ comment.user?.name?.charAt(0)?.toUpperCase() || '?' }}
            </div>
            <span class="font-medium">{{ comment.user?.name || 'مستخدم' }}</span>
          </div>
          <div class="flex items-center gap-3">
            <span>
              {{ new Date(comment.created_at || comment.translation?.created_at).toLocaleString('ar-EG') }}
            </span>
            <!-- زر الإبلاغ -->
            <button
              @click="openReportModal(comment.id)"
              class="flex items-center gap-1 text-xs text-gray-400 hover:text-red-500 transition-colors duration-150 px-2 py-1 rounded-lg hover:bg-red-50"
              title="الإبلاغ عن هذا التعليق"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h18l-2 9H5L3 3z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12v7h14v-7" />
              </svg>
              إبلاغ
            </button>
          </div>
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
            <span class="text-[11px] font-bold">YES</span>
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
            <span class="text-[11px] font-bold">MID</span>
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
            <span class="text-[11px] font-bold">NO</span>
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

  <!-- ============ Report Modal ============ -->
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="reportModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        dir="rtl"
      >
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-black/50 backdrop-blur-sm"
          @click="closeReportModal"
        ></div>

        <!-- Modal Box -->
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 z-10">

          <!-- Success State -->
          <div v-if="reportSuccess" class="flex flex-col items-center py-6 gap-3 text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center text-sm font-bold">OK</div>
            <h4 class="text-lg font-bold text-gray-800">تم إرسال البلاغ</h4>
            <p class="text-sm text-gray-500">شكراً لك، سنراجع هذا التعليق قريباً.</p>
          </div>

          <!-- Form State -->
          <template v-else>
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-5">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-red-500">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                  </svg>
                </div>
                <h3 class="text-base font-bold text-gray-800">الإبلاغ عن تعليق</h3>
              </div>
              <button
                @click="closeReportModal"
                class="w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <p class="text-sm text-gray-500 mb-4">اختر سبب الإبلاغ عن هذا التعليق:</p>

            <!-- Reasons Grid -->
            <div class="grid grid-cols-2 gap-2 mb-5">
              <button
                v-for="reason in reportReasons"
                :key="reason.value"
                @click="reportReason = reason.value"
                :class="[
                  'flex items-center gap-2 px-3 py-2.5 rounded-xl border text-sm text-right transition-all duration-150',
                  reportReason === reason.value
                    ? 'bg-red-50 border-red-400 text-red-700 font-semibold shadow-sm'
                    : 'bg-gray-50 border-gray-200 text-gray-700 hover:border-red-300 hover:bg-red-50/50'
                ]"
              >
                <span class="text-base leading-none">{{ reason.icon }}</span>
                <span class="leading-tight">{{ reason.label }}</span>
              </button>
            </div>

            <!-- Error Message -->
            <p v-if="reportError" class="text-xs text-red-500 mb-3 flex items-center gap-1">
              <span class="text-xs font-bold">ERR</span> {{ reportError }}
            </p>

            <!-- Actions -->
            <div class="flex gap-2 justify-end">
              <button
                @click="closeReportModal"
                class="px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition"
              >
                إلغاء
              </button>
              <button
                @click="submitReport"
                :disabled="reportLoading || !reportReason"
                :class="[
                  'px-5 py-2 rounded-xl text-sm font-semibold transition-all duration-150',
                  reportReason && !reportLoading
                    ? 'bg-red-500 hover:bg-red-600 text-white shadow-sm'
                    : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                ]"
              >
                <span v-if="reportLoading" class="flex items-center gap-1.5">
                  <svg class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                  </svg>
                  جاري الإرسال...
                </span>
                <span v-else>إرسال البلاغ</span>
              </button>
            </div>
          </template>

        </div>
      </div>
    </Transition>
  </Teleport>

</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: all 0.25s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
  transform: scale(0.95);
}

.comments-page {
  background:
    radial-gradient(circle at top left, rgba(48, 168, 255, 0.10), transparent 28rem),
    linear-gradient(180deg, #FFFFFF, #F8FAFC);
  border-radius: 28px;
}

.comments-page h2 {
  color: #06142A;
}

.comments-page .bg-white,
.comments-page .rounded-2xl {
  border: 1px solid #E5EDF6;
  box-shadow: 0 10px 35px rgba(13, 77, 151, 0.06);
}

.comments-page .bg-indigo-500,
.comments-page .bg-indigo-600 {
  background: #1677FF !important;
}

.comments-page .text-indigo-600,
.comments-page .text-indigo-700 {
  color: #0D4D97 !important;
}
</style>
