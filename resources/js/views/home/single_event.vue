<template>
  <div class="min-h-screen bg-gray-50">
    <div v-if="loading" class="text-center py-40">
      <div
        class="animate-spin rounded-full h-20 w-20 border-t-4 border-blue-600 mx-auto mb-8"
      ></div>
      <p class="text-gray-700 text-2xl font-medium">جاري تحميل الحدث...</p>
    </div>

    <div v-else-if="!event" class="text-center py-32 text-gray-600 text-2xl">
      الحدث غير موجود أو تم حذفه.
    </div>

    <div v-else>
      <div class="relative">
        <!-- Hero Media -->
        <component
          :is="heroMediaComponent"
          v-if="heroMedia"
          :src="heroMedia.url"
          class="w-full h-[300px] md:h-[400px] lg:h-[500px] object-cover"
          controls
          autoplay
          muted
          loop
          playsinline
        />

        <img
          v-else
          src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&q=80&w=2400"
          :alt="event.title"
          class="w-full h-[300px] md:h-[400px] lg:h-[500px] object-cover"
        />

        <!-- Overlay + Play Icon فقط لو الفيديو -->
        <div
          v-if="heroMedia && (heroMedia.video || isVideoUrl(heroMedia.url))"
          class="absolute inset-0 bg-black/30 flex items-center justify-center z-10 pointer-events-none"
        >
          <div
            class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-2xl transform hover:scale-110 transition-transform duration-300"
          >
            <span class="text-white text-5xl md:text-7xl drop-shadow-lg">▶</span>
          </div>
        </div>

        <!-- Tags -->
        <div class="absolute top-6 left-6 md:left-10 flex flex-wrap gap-3 z-20">
          <span
            class="bg-blue-100/90 backdrop-blur-sm text-blue-900 px-5 py-2 rounded-full text-base font-bold shadow-lg uppercase tracking-wider"
          >
            {{ event.city?.name || "غير محدد" }}
          </span>
          <span
            v-if="event.sub_categorey?.name"
            class="bg-green-100/90 backdrop-blur-sm text-green-900 px-5 py-2 rounded-full text-base font-bold shadow-lg uppercase tracking-wider"
          >
            {{ event.sub_categorey.name }}
          </span>
        </div>
      </div>

      <!-- Content -->
      <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 -mt-16 relative z-10">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-0">
            <!-- Main Content -->
            <div class="lg:col-span-2 p-8 md:p-12 lg:p-16">
              <!-- العنوان + زر اللايك -->
              <div class="flex items-center justify-between mb-6">
                <h1
                  class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 leading-tight"
                >
                  {{ event.title }}
                </h1>

                <button
                  @click="toggleLike"
                  :disabled="likeLoading || isLiked"
                  class="flex items-center gap-2 px-5 py-2.5 rounded-full transition-all duration-200"
                  :class="{
                    'bg-pink-50 text-pink-600 border border-pink-200 hover:bg-pink-100': isLiked,
                    'bg-gray-100 text-gray-600 hover:bg-pink-50 hover:text-pink-600 border border-gray-300': !isLiked,
                  }"
                >
                  <svg
                    class="w-7 h-7 transition-transform"
                    :class="{ 'scale-110': isLiked }"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill-rule="evenodd"
                      clip-rule="evenodd"
                      d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
                    />
                  </svg>
                  <span class="text-lg font-semibold">{{ likesCount }}</span>
                  <span v-if="likeLoading" class="text-sm animate-pulse">...</span>
                </button>
              </div>

              <p class="text-lg md:text-xl text-gray-700 mb-10 leading-relaxed">
                {{ event.description }}
              </p>

              <!-- Media Gallery -->
              <div v-if="hasMedia" class="mb-12">
                <h2
                  class="text-2xl md:text-3xl font-bold text-gray-900 mb-6 border-b border-gray-200 pb-4"
                >
                  وسائط الحدث
                </h2>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                  <div
                    v-for="(media, index) in event.images"
                    :key="media.id || index"
                    class="aspect-[4/3] overflow-hidden rounded-xl shadow-sm hover:shadow-md transition-shadow cursor-pointer relative"
                    @click="openLightbox(index)"
                  >
                    <img
                      v-if="!media.video && media.url && !isVideoUrl(media.url)"
                      :src="media.url"
                      class="w-full h-full object-cover"
                      loading="lazy"
                    />

                    <div
                      v-else-if="media.video || (media.url && isVideoUrl(media.url))"
                      class="relative w-full h-full bg-black"
                    >
                      <video
                        :src="media.video || media.url"
                        class="w-full h-full object-cover"
                        muted
                        loop
                        playsinline
                        preload="metadata"
                      ></video>
                      <div
                        class="absolute inset-0 flex items-center justify-center bg-black/20"
                      >
                        <span class="text-white text-6xl opacity-80 drop-shadow-2xl"
                          >▶</span
                        >
                      </div>
                    </div>

                    <div
                      v-else
                      class="w-full h-full bg-gray-200 flex items-center justify-center text-gray-500"
                    >
                      لا يوجد وسائط
                    </div>
                  </div>
                </div>
              </div>

              <!-- نبذة عن الحدث -->
              <div class="mb-12">
                <h2
                  class="text-2xl md:text-3xl font-bold text-gray-900 mb-6 border-b border-gray-200 pb-4"
                >
                  نبذة عن الحدث
                </h2>
                <p class="text-gray-700 mb-8 text-lg leading-relaxed">
                  {{ event.description }}
                </p>
              </div>

              <!-- قسم التعليقات -->
              <div class="comments-section">
                <h2 class="comments-title">
                  التعليقات ({{ event.comments_count || event.comments?.length || 0 }})
                </h2>

                <div
                  v-for="comment in event.comments"
                  :key="comment.id"
                  class="comment-box"
                >
                  <div class="comment-content">
                    <div class="comment-text">
                      <div class="comment-header">
                        <p class="comment-user">
                          {{ comment.user?.name || "زائر" }}
                        </p>
                        <span class="comment-date">
                          {{ formatCommentDate(comment.created_at) }}
                        </span>
                      </div>

                      <p class="comment-body">
                        {{ comment.comment }}
                      </p>
                    </div>

                    <button
                      v-if="comment.user_id === currentUserId"
                      @click="deleteComment(comment.id)"
                      class="delete-btn"
                      title="حذف التعليق"
                    >
                      ✕
                    </button>
                  </div>
                </div>

                <div class="comment-form">
                  <h3 class="form-title">أضف تعليقك</h3>

                  <form @submit.prevent="addComment">
                    <textarea
                      v-model="newComment"
                      rows="3"
                      class="comment-textarea"
                      placeholder="اكتب تعليقك هنا..."
                      :disabled="commentLoading"
                      required
                    ></textarea>

                    <div class="form-actions">
                      <button
                        type="submit"
                        :disabled="commentLoading || !newComment.trim()"
                        class="submit-btn"
                      >
                        <span v-if="commentLoading">جاري الإرسال...</span>
                        <span v-else>إرسال</span>
                      </button>
                    </div>

                    <p v-if="commentError" class="error-msg">'يرجي تسجيل الدخول اولا'</p>
                    <p v-if="commentSuccess" class="success-msg">
                      تم إضافة تعليقك بنجاح!
                    </p>
                  </form>
                </div>
              </div>
            </div>

            <!-- Sidebar -->
            <div
              class="bg-gray-50/70 p-8 md:p-12 lg:p-16 border-t lg:border-t-0 lg:border-l border-gray-200 lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto"
            >
              <div class="space-y-10">
                <div>
                  <h3 class="text-2xl font-bold text-gray-900 mb-6">معلومات الحدث</h3>

                  <div class="space-y-3 text-gray-800">
                    <div class="flex items-center gap-2">
                      <span class="text-3xl">📅</span>
                      <div>
                        <p class="font-semibold text-base">من</p>
                        <p class="text-lg">{{ formatDate(event.start_date) }}</p>
                      </div>
                    </div>

                    <div class="flex items-center gap-2">
                      <span class="text-3xl">📅</span>
                      <div>
                        <p class="font-semibold text-base">إلى</p>
                        <p class="text-lg">{{ formatDate(event.end_date) }}</p>
                      </div>
                    </div>

                    <div class="flex items-center gap-2">
                      <span class="text-3xl">🕒</span>
                      <div>
                        <p class="font-semibold text-base">الوقت</p>
                        <p class="text-lg">{{ event.time || "غير محدد" }}</p>
                      </div>
                    </div>

                    <div class="flex items-center gap-2">
                      <span class="text-3xl">📍</span>
                      <div>
                        <p class="font-semibold text-base">المكان</p>
                        <p class="text-lg">{{ event.city?.name || "غير محدد" }}</p>
                      </div>
                    </div>
                    <div v-if="event.user?.name" class="flex items-center gap-2">
                      <span class="text-3xl">👤</span>
                      <div>
                        <p class="font-semibold text-base">منظم الحدث</p>
                        <p class="text-lg">{{ event.user.name }}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="pt-8 border-t border-gray-200 space-y-4">
                  <button
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold text-lg py-4 px-8 rounded-xl transition shadow-lg"
                  >
                    ♥ أضف إلى المفضلة
                  </button>
                  <button
                    class="w-full border-2 border-gray-300 hover:bg-gray-100 hover:border-gray-400 font-semibold text-lg py-4 px-8 rounded-xl transition"
                  >
                    + أضف إلى التقويم
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lightbox -->
      <div
        v-if="lightboxOpen"
        class="fixed inset-0 bg-black/95 z-50 flex items-center justify-center"
        @click="lightboxOpen = false"
      >
        <div class="relative max-w-[95vw] max-h-[95vh]">
          <img
            v-if="currentMedia && !isVideoUrl(currentMedia.url) && !currentMedia.video"
            :src="currentMedia.url"
            class="max-w-full max-h-[90vh] object-contain"
            @click.stop
          />

          <video
            v-else-if="
              currentMedia && (currentMedia.video || isVideoUrl(currentMedia.url))
            "
            :src="currentMedia.video || currentMedia.url"
            class="max-w-full max-h-[90vh]"
            controls
            autoplay
            @click.stop
          ></video>

          <button
            class="absolute top-4 right-4 text-white text-5xl font-bold drop-shadow-lg"
            @click="lightboxOpen = false"
          >
            ×
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import axios from "axios";
import { EventService } from "@/services/singleEventService";

const route = useRoute();
const slug = route.params.slug;

const event = ref(null);
const loading = ref(true);
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);

const newComment = ref("");
const commentLoading = ref(false);
const commentError = ref("");
const commentSuccess = ref(false);
const currentUserId = ref(null);

// ── لايكات ────────────────────────────────────────
const likesCount = ref(0);
const isLiked = ref(false);
const likeLoading = ref(false);

const fetchCurrentUser = async () => {
  try {
    const token = localStorage.getItem("auth_token");
    if (!token) return;

    const res = await axios.get("/v1/users/profile", {
      headers: { Authorization: `Bearer ${token}` },
    });

    currentUserId.value = res.data?.data?.user?.id || res.data?.id;
  } catch (err) {
    console.log("فشل جلب بيانات المستخدم", err);
  }
};

const fetchLikesInfo = async () => {
  if (!event.value?.id) return;

  try {
    const token = localStorage.getItem("auth_token") || "";
    const res = await axios.get(`/v1/likes/${event.value.id}`, {
      headers: { Authorization: `Bearer ${token}` },
    });

    if (res.data.status === "success" && res.data.data) {
      likesCount.value = res.data.data.count ?? 0;
      isLiked.value = !!res.data.data.liked;
    }
  } catch (err) {
    console.error("خطأ في جلب بيانات اللايكات", err);
    likesCount.value = 0;
    isLiked.value = false;
  }
};

const toggleLike = async () => {
  if (likeLoading.value || isLiked.value) return;
  if (!event.value?.id) return;

  const token = localStorage.getItem("auth_token");
  if (!token) {
    alert("يرجى تسجيل الدخول أولاً");
    return;
  }

  likeLoading.value = true;

  try {
    const res = await axios.post(
      `/v1/likes/${event.value.id}/create`,
      {},
      {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
        },
      }
    );

    if (res.data.status === "success") {
      likesCount.value += 1;
      isLiked.value = true;
    }
  } catch (err) {
    console.error("خطأ أثناء إضافة اللايك", err);
    alert(err.response?.data?.message || "حدث خطأ أثناء الإعجاب");
  } finally {
    likeLoading.value = false;
  }
};

const hasMedia = computed(() => event.value?.images?.length > 0);

const heroMedia = computed(() => event.value?.images?.[0] || null);

const heroMediaComponent = computed(() => {
  if (heroMedia.value?.video || isVideoUrl(heroMedia.value?.url)) {
    return "video";
  }
  return "img";
});

const currentMedia = computed(() => event.value?.images?.[lightboxIndex.value] || null);

const isVideoUrl = (url) => {
  if (!url) return false;
  const exts = [".mp4", ".webm", ".ogg", ".mov"];
  return exts.some((ext) => url.toLowerCase().endsWith(ext));
};

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  try {
    return new Date(dateStr).toLocaleDateString("ar-EG", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  } catch {
    return "—";
  }
};

const formatCommentDate = (dateStr) => {
  if (!dateStr) return "—";
  try {
    return new Date(dateStr).toLocaleString("ar-EG", {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "numeric",
      minute: "2-digit",
    });
  } catch {
    return "—";
  }
};

const openLightbox = (index) => {
  lightboxIndex.value = index;
  lightboxOpen.value = true;
};

const addComment = async () => {
  if (!newComment.value.trim()) return;
  commentLoading.value = true;
  commentError.value = "";
  commentSuccess.value = false;

  try {
    const response = await axios.post(
      `/v1/comments/${event.value.id}/create`,
      { comment: newComment.value.trim() },
      {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("auth_token") || ""}`,
          Accept: "application/json",
        },
      }
    );

    if (response.data.status === "success") {
      const newCommentData = response.data.data || {
        id: Date.now(),
        event_id: event.value.id,
        user_id: currentUserId.value,
        comment: newComment.value.trim(),
        created_at: new Date().toISOString(),
        user: { name: "أنت" },
      };

      event.value.comments.unshift(newCommentData);
      newComment.value = "";
      commentSuccess.value = true;
      setTimeout(() => (commentSuccess.value = false), 4000);
    }
    window.location.reload();
  } catch (err) {
    console.error("خطأ في إضافة التعليق:", err);
    commentError.value = err.response?.data?.message || "حدث خطأ أثناء إرسال التعليق";
  } finally {
    commentLoading.value = false;
  }
};

const deleteComment = async (commentId) => {
  if (!confirm("هل أنت متأكد من حذف التعليق؟")) return;

  try {
    const token = localStorage.getItem("auth_token");
    await axios.delete(`/v1/comments/${commentId}/delete`, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    });
    event.value.comments = event.value.comments.filter((c) => c.id !== commentId);
    if (event.value.comments_count !== undefined) {
      event.value.comments_count--;
    }
  } catch (err) {
    console.error("خطأ أثناء حذف التعليق:", err);
    alert(err.response?.data?.message || "حدث خطأ أثناء الحذف");
  }
};

onMounted(async () => {
  await fetchCurrentUser();

  if (!slug) {
    loading.value = false;
    return;
  }

  loading.value = true;
  try {
    const response = await EventService.getSingleEvent(slug);
    event.value = response.data?.data || response;

    // جلب بيانات اللايكات (عدد + حالة المستخدم)
    await fetchLikesInfo();
  } catch (err) {
    console.error("خطأ في جلب الحدث:", err);
    event.value = null;
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.comments-section {
  margin-bottom: 40px;
}

.comments-title {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 15px;
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 8px;
}

.comment-box {
  background: #f9fafb;
  padding: 8px 10px; /* صغير جدًا */
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  margin-top: 6px;
  position: relative;
  transition: 0.2s ease;
}

.comment-box:hover {
  background: #f3f4f6;
}

.comment-content {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.comment-header {
  display: flex;
  gap: 10px;
  align-items: center;
}

.comment-user {
  font-size: 13px;
  font-weight: 600;
  color: #1f2937;
}

.comment-date {
  font-size: 11px;
  color: #6b7280;
}

.comment-body {
  margin-top: 4px;
  font-size: 13px;
  color: #374151;
  line-height: 1.4;
}

.delete-btn {
  background: #fee2e2;
  color: #dc2626;
  border: none;
  width: 20px; /* أصغر */
  height: 20px;
  border-radius: 50%;
  cursor: pointer;
  font-size: 12px;
  font-weight: bold;
  transition: all 0.2s ease;
  margin-left: 6px;
}

.delete-btn:hover {
  background: #dc2626;
  color: #fff;
}

.comment-form {
  margin-top: 30px;
}

.form-title {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 10px;
}

.comment-textarea {
  width: 100%;
  padding: 8px;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  font-size: 13px;
  resize: none;
  outline: none;
  transition: 0.2s;
}

.comment-textarea:focus {
  border-color: #3b82f6;
}

.form-actions {
  margin-top: 10px;
  display: flex;
  justify-content: flex-end;
}

.submit-btn {
  background: #2563eb;
  color: white;
  border: none;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 13px;
  cursor: pointer;
  transition: 0.2s;
}

.submit-btn:hover {
  background: #1d4ed8;
}

.submit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.error-msg {
  color: #dc2626;
  font-size: 12px;
  margin-top: 6px;
}

.success-msg {
  color: #16a34a;
  font-size: 12px;
  margin-top: 6px;
}
</style>
