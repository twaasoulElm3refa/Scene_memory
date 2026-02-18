<template>
  <AdminLayout>
    <div class="show-event-container" v-if="event">
      <!-- Header -->
      <div class="header">
        <div class="breadcrumb">
          <router-link to="/admin/events" class="breadcrumb-link"
            >جميع الاحداث</router-link
          >
          <span class="breadcrumb-separator">›</span>
          <span class="breadcrumb-current">{{ event.title }}</span>
        </div>

        <div class="header-actions">
          <button @click="goBack" class="btn btn-secondary">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M19 12H5M12 19l-7-7 7-7" />
            </svg>
            Back to List
          </button>

          <button @click="addMedia" class="btn btn-secondary">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <line x1="12" y1="5" x2="12" y2="19" />
              <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Add media
          </button>

          <button @click="editEvent" class="btn btn-secondary">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
            </svg>
            Edit
          </button>

          <button @click="deleteEvent" class="btn btn-danger">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="3 6 5 6 21 6" />
              <path
                d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
              />
            </svg>
            Delete
          </button>
        </div>
      </div>

      <!-- Title and Status -->
      <div class="title-section">
        <h1 class="event-title">{{ event.title }}</h1>
        <span class="status-badge status-upcoming">UPCOMING</span>
      </div>

      <!-- Main Content -->
      <div class="content-grid">
        <!-- Left Column -->
        <div class="left-column">
          <!-- Event Image -->
          <div class="event-image-card">
            <img
              :src="event.image?.trim() || fallbackImage"
              :alt="event.title"
              class="event-image"
              loading="lazy"
            />
            <div class="image-overlay">
              <span class="badge badge-primary">{{
                event.sub_categorey?.name || "TECHNOLOGY"
              }}</span>
              <span class="badge badge-secondary">ANNUAL</span>
            </div>
            <div class="location-overlay">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
              {{ event.city?.name || "Modern Convention Center" }} •
              {{ getCityDisplay() }}
            </div>
          </div>

          <!-- Event Overview -->
          <div class="overview-card">
            <h2 class="section-title">Event Overview</h2>
            <p class="event-description">{{ event.description }}</p>

            <div class="category-section">
              <div class="category-item">
                <div class="category-icon category-icon-primary">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                    <line x1="9" y1="9" x2="15" y2="15" />
                    <line x1="15" y1="9" x2="9" y2="15" />
                  </svg>
                </div>
                <div>
                  <div class="category-label">CATEGORY</div>
                  <div class="category-value">
                    {{ event.sub_categorey?.name || "Technology" }}
                  </div>
                </div>
              </div>

              <div class="category-item">
                <div class="category-icon category-icon-secondary">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <polygon
                      points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"
                    />
                  </svg>
                </div>
                <div>
                  <div class="category-label">SUB-CATEGORY</div>
                  <div class="category-value">
                    {{ event.sub_categorey?.name || "AI & Digital Design" }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="right-column">
          <!-- Schedule Card -->
          <div class="info-card">
            <div class="card-header">
              <h3 class="card-title">Schedule</h3>
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#2196F3"
                stroke-width="2"
              >
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
              </svg>
            </div>

            <div class="schedule-item">
              <div class="date-badge">
                <div class="date-month">{{ getMonth(event.start_date) }}</div>
                <div class="date-day">{{ getDay(event.start_date) }}</div>
              </div>
              <div class="schedule-details">
                <div class="schedule-label">Start Date & Time</div>
                <div class="schedule-value">{{ formatDate(event.start_date) }}</div>
                <div class="schedule-time">{{ event.time || "unknown" }}</div>
              </div>
            </div>

            <div class="schedule-item">
              <div class="date-badge">
                <div class="date-month">{{ getMonth(event.end_date) }}</div>
                <div class="date-day">{{ getDay(event.end_date) }}</div>
              </div>
              <div class="schedule-details">
                <div class="schedule-label">End Date</div>
                <div class="schedule-value">{{ formatDate(event.end_date) }}</div>
              </div>
            </div>

            <p class="registration-note">
              Registration closes on {{ formatDate(event.start_date) }}
            </p>
          </div>

          <!-- Location Card
          <div class="info-card">
            <div class="card-header">
              <h3 class="card-title">Location</h3>
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#2196F3"
                stroke-width="2"
              >
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
            </div>

            <div class="location-details">
              <h4 class="location-name">
                {{ event.city?.name || "Moscone Center West" }}
              </h4>
              <p class="location-address">
                Lat: {{ event.lattitude }}, Long: {{ event.langitude }}
              </p>
              <a href="#" class="location-link">{{ getCityDisplay() }}</a>
            </div>

            <div class="map-placeholder">
              <div class="map-marker">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="32"
                  height="32"
                  viewBox="0 0 24 24"
                  fill="#2196F3"
                  stroke="white"
                  stroke-width="2"
                >
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                  <circle cx="12" cy="10" r="3" />
                </svg>
              </div>
              <div class="map-city">{{ event.city?.name?.toUpperCase() }}</div>
            </div>
          </div> -->
        </div>
      </div>

      <!-- Event Images Gallery -->
      <div class="images-gallery" v-if="event.images?.length > 1">
        <h3 class="gallery-title">Event Images</h3>
        <div
          class="gallery-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
        >
          <div
            v-for="(media, index) in event.images"
            :key="media.id"
            class="gallery-item relative rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 group cursor-pointer bg-gray-900"
            @click="openModal(media, index)"
          >
            <!-- الصورة / الـ thumbnail -->
            <img
              :src="media.url?.trim() || fallbackImage"
              :alt="event.title"
              class="w-full h-64 object-cover transition-transform duration-700 group-hover:scale-110"
              loading="lazy"
            />

            <!-- Overlay خفيف عند الـ hover -->
            <div
              class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"
            ></div>

            <!-- أيقونة Play لو فيديو (في المنتصف) -->
            <div
              v-if="isVideo(media.url)"
              class="absolute inset-0 flex items-center justify-center pointer-events-none z-10"
            >
              <div class="bg-black/50 backdrop-blur-md rounded-full p-6">
                <svg class="w-14 h-14 text-white" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7z" />
                </svg>
              </div>
            </div>

            <!-- زر الحذف - فوق كل شيء بدون استثناء -->
            <button
              @click.stop.prevent="confirmDeleteMedia(media.id)"
              class="absolute top-4 right-4 z-[9999] w-11 h-11 flex items-center justify-center rounded-full bg-red-600/95 text-white shadow-2xl backdrop-blur-lg border-2 border-red-400/40 transform transition-all duration-200 opacity-0 group-hover:opacity-100 hover:bg-red-700 hover:scale-110 active:scale-90 focus:outline-none focus:ring-4 focus:ring-red-500/50"
              title="حذف هذه الوسائط"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="w-6 h-6"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2.5"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Fullscreen Modal for view media -->
      <div
        v-if="isModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-sm"
        @click="closeModal"
      >
        <button
          class="absolute top-6 right-6 z-10 text-white text-5xl hover:text-gray-300 transition-colors"
          @click.stop="closeModal"
        >
          ×
        </button>

        <img
          v-if="selectedMedia && !isVideo(selectedMedia.url)"
          :src="getSafeUrl(selectedMedia.url)"
          :alt="event.title"
          class="max-w-[95vw] max-h-[90vh] object-contain shadow-2xl"
          @click.stop
        />

        <video
          v-else-if="selectedMedia && isVideo(selectedMedia.url)"
          :src="getSafeUrl(selectedMedia.url)"
          controls
          autoplay
          loop
          muted
          class="max-w-[95vw] max-h-[90vh] object-contain shadow-2xl"
          @click.stop
        >
          المتصفح لا يدعم عرض الفيديو
        </video>

        <img
          v-else
          :src="fallbackImage"
          class="max-w-[95vw] max-h-[90vh] object-contain"
        />

        <button
          v-if="selectedIndex > 0"
          class="absolute left-6 top-1/2 -translate-y-1/2 text-white text-6xl hover:text-gray-300"
          @click.stop="prevMedia"
        >
          ‹
        </button>
        <button
          v-if="selectedIndex < event?.images?.length - 1"
          class="absolute right-6 top-1/2 -translate-y-1/2 text-white text-6xl hover:text-gray-300"
          @click.stop="nextMedia"
        >
          ›
        </button>
      </div>

      <!-- Add Media Modal -->
      <div
        v-if="isAddMediaModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4"
        @click="closeAddMediaModal"
      >
        <div
          class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-lg w-full overflow-hidden"
          @click.stop
        >
          <div class="p-6 border-b dark:border-gray-700">
            <h3 class="text-xl font-bold">إضافة وسائط جديدة</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
              يمكنك رفع صورة أو فيديو للحدث
            </p>
          </div>

          <div class="p-6 space-y-6">
            <div>
              <label class="block text-sm font-medium mb-2"
                >اختر ملف (صورة أو فيديو)</label
              >
              <div
                class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center cursor-pointer hover:border-blue-500 transition-colors"
                @click="$refs.fileInput.click()"
                @dragover.prevent
                @drop.prevent="handleDrop"
              >
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/*,video/*"
                  class="hidden"
                  @change="handleFileChange"
                />

                <div v-if="!selectedFile" class="space-y-3">
                  <svg
                    class="mx-auto h-12 w-12 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                    />
                  </svg>
                  <p class="text-sm text-gray-600 dark:text-gray-300">
                    اسحب الملف هنا أو
                    <span class="text-blue-600 font-medium">اضغط للتصفح</span>
                  </p>
                  <p class="text-xs text-gray-500">
                    PNG, JPG, MP4, MOV (الحد الأقصى 100 ميجا)
                  </p>
                </div>

                <div v-else class="space-y-3">
                  <div class="text-green-600 font-medium">
                    {{ selectedFile.name }}
                  </div>
                  <div class="text-sm text-gray-500">
                    {{ (selectedFile.size / 1024 / 1024).toFixed(2) }} MB
                  </div>
                  <button
                    type="button"
                    class="text-red-600 hover:text-red-800 text-sm underline"
                    @click="clearSelectedFile"
                  >
                    إلغاء الاختيار
                  </button>
                </div>
              </div>
            </div>

            <div v-if="uploading" class="space-y-2">
              <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                <div
                  class="bg-blue-600 h-2.5 rounded-full transition-all duration-300"
                  :style="{ width: `${uploadProgress}%` }"
                ></div>
              </div>
              <div class="text-center text-sm text-gray-600 dark:text-gray-400">
                جاري الرفع... {{ uploadProgress }}%
              </div>
            </div>

            <div v-if="uploadError" class="text-red-600 text-sm text-center">
              {{ uploadError }}
            </div>
          </div>

          <div class="p-6 border-t dark:border-gray-700 flex justify-end gap-3">
            <button
              @click="closeAddMediaModal"
              class="px-5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
              :disabled="uploading"
            >
              إلغاء
            </button>
            <button
              @click="uploadMedia"
              class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
              :disabled="uploading || !selectedFile"
            >
              <svg
                v-if="uploading"
                class="animate-spin h-5 w-5"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle
                  class="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="4"
                ></circle>
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
              </svg>
              {{ uploading ? "جاري الرفع..." : "رفع الملف" }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-else-if="loading" class="loading-container">
      <div class="spinner"></div>
      <p>Loading event details...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-container">
      <p class="error-message">{{ error }}</p>
      <button @click="goBack" class="btn btn-primary">Back to Events</button>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import axios from "axios";

// ────────────────────────────────────────────────
// Interfaces
// ────────────────────────────────────────────────
interface City {
  id: number;
  name: string;
}

interface SubCategory {
  id: number;
  name: string;
}

interface User {
  id: number;
  name: string;
}

interface EventImage {
  id: number;
  event_id: number;
  url: string;
  created_at: string;
  updated_at: string;
}

interface Event {
  id: number;
  user_id: number;
  city_id: number;
  sub_categorey_id: number;
  title: string;
  description: string;
  start_date: string;
  end_date: string;
  time: string | null;
  image: string | null;
  langitude: string;
  lattitude: string;
  slug: string;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  city: City;
  sub_categorey: SubCategory;
  user: User;
  images: EventImage[];
}

// ────────────────────────────────────────────────
// State
// ────────────────────────────────────────────────
const route = useRoute();
const router = useRouter();

const event = ref<Event | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const isModalOpen = ref(false);
const selectedMedia = ref<EventImage | null>(null);
const selectedIndex = ref(-1);

const fallbackImage = "https://spotme.com/wp-content/uploads/2020/07/Hero-1.jpg";

// Add Media Modal state
const isAddMediaModalOpen = ref(false);
const selectedFile = ref<File | null>(null);
const uploading = ref(false);
const uploadProgress = ref(0);
const uploadError = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

// ────────────────────────────────────────────────
// Helper: Check if URL is video
// ────────────────────────────────────────────────
const isVideo = (url?: string) => {
  if (!url) return false;
  const lower = url.toLowerCase();
  return (
    lower.endsWith(".mp4") ||
    lower.endsWith(".webm") ||
    lower.endsWith(".mov") ||
    lower.endsWith(".ogg") ||
    lower.endsWith(".m4v")
  );
};

// ────────────────────────────────────────────────
// View Modal functions
// ────────────────────────────────────────────────
const openModal = (media: EventImage, index: number) => {
  selectedMedia.value = media;
  selectedIndex.value = index;
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  selectedMedia.value = null;
  selectedIndex.value = -1;
};

const prevMedia = () => {
  if (selectedIndex.value > 0) {
    selectedIndex.value--;
    selectedMedia.value = event.value?.images?.[selectedIndex.value] || null;
  }
};

const nextMedia = () => {
  if (event.value?.images && selectedIndex.value < event.value.images.length - 1) {
    selectedIndex.value++;
    selectedMedia.value = event.value.images[selectedIndex.value];
  }
};

const getSafeUrl = (url?: string | null) => {
  if (!url || typeof url !== "string" || url.trim() === "") return fallbackImage;
  return url.trim();
};

// ────────────────────────────────────────────────
// Delete Media
// ────────────────────────────────────────────────
const confirmDeleteMedia = async (mediaId: number) => {
  if (!confirm("هل أنت متأكد من حذف هذه الوسائط؟ لا يمكن التراجع عن هذا الإجراء")) {
    return;
  }

  try {
    const response = await axios.delete(`/v1/event-images/${mediaId}/delete`);

    if (response.data.status === "success") {
      alert("تم حذف الوسائط بنجاح");
      // تحديث البيانات أو رفرش الصفحة
      await fetchEvent();
      // أو: window.location.reload();  ← لو عايز رفرش كامل
    } else {
      alert(response.data.message || "حدث خطأ أثناء الحذف");
    }
  } catch (err: any) {
    alert(err.response?.data?.message || "فشل حذف الوسائط");
    console.error("Delete error:", err);
  }
};

// ────────────────────────────────────────────────
// Add Media Modal functions
// ────────────────────────────────────────────────
const addMedia = () => {
  isAddMediaModalOpen.value = true;
  selectedFile.value = null;
  uploadError.value = null;
  uploadProgress.value = 0;
};

const closeAddMediaModal = () => {
  if (uploading.value) return;
  isAddMediaModalOpen.value = false;
  clearSelectedFile();
};

const clearSelectedFile = () => {
  selectedFile.value = null;
  if (fileInput.value) fileInput.value.value = "";
};

const handleFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files[0]) {
    selectedFile.value = target.files[0];
  }
};

const handleDrop = (e: DragEvent) => {
  e.preventDefault();
  if (e.dataTransfer?.files && e.dataTransfer.files[0]) {
    selectedFile.value = e.dataTransfer.files[0];
  }
};

const uploadMedia = async () => {
  if (!selectedFile.value || !event.value) return;

  uploading.value = true;
  uploadError.value = null;
  uploadProgress.value = 0;

  const formData = new FormData();
  formData.append("url", selectedFile.value);

  try {
    const response = await axios.post(
      `/v1/event-images/create/${event.value.id}`,
      formData,
      {
        headers: { "Content-Type": "multipart/form-data" },
        onUploadProgress: (progressEvent) => {
          if (progressEvent.total) {
            uploadProgress.value = Math.round(
              (progressEvent.loaded * 100) / progressEvent.total
            );
          }
        },
      }
    );

    if (response.data.status === "success") {
      await fetchEvent();
      alert("تم رفع الوسائط بنجاح!");
      closeAddMediaModal();
      // window.location.reload();  ← اختياري لو عايز رفرش كامل
    } else {
      uploadError.value = response.data.message || "حدث خطأ أثناء الرفع";
    }
  } catch (err: any) {
    uploadError.value = err.response?.data?.message || "فشل رفع الملف";
    console.error("Upload error:", err);
  } finally {
    uploading.value = false;
    uploadProgress.value = 0;
  }
};

// ────────────────────────────────────────────────
// Date Helpers
// ────────────────────────────────────────────────
const formatDate = (dateStr: string) => {
  const date = new Date(dateStr);
  return date.toLocaleDateString("en-US", {
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
  });
};

const getMonth = (dateStr: string) => {
  const date = new Date(dateStr);
  return date.toLocaleDateString("en-US", { month: "short" }).toUpperCase();
};

const getDay = (dateStr: string) => {
  const date = new Date(dateStr);
  return date.getDate().toString().padStart(2, "0");
};

const getCityDisplay = () => event.value?.city?.name || "San Francisco";

// ────────────────────────────────────────────────
// Navigation & Actions
// ────────────────────────────────────────────────
const goBack = () => router.push("/admin/events");

const editEvent = () => {
  if (event.value?.slug) router.push(`/admin/events/${event.value.slug}/edit`);
};

const deleteEvent = async () => {
  if (!confirm("متأكد من حذف الحدث؟")) return;
  try {
    await axios.delete(`v1/events/${event.value?.slug}/delete`);
    alert("تم حذف الحدث بنجاح");
    goBack();
  } catch (err) {
    alert("فشل حذف الحدث");
    console.error(err);
  }
};

const fetchEvent = async () => {
  try {
    loading.value = true;
    error.value = null;
    const slug = route.params.id as string;
    const res = await axios.get(`v1/events/${slug}/single/get`);
    if (res.data.status === "success") {
      event.value = res.data.data;
    } else {
      error.value = "فشل تحميل بيانات الحدث";
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || "حدث خطأ أثناء التحميل";
    console.error(err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchEvent();
});
</script>

<style scoped>
/* ──────────────────────────────────────────────── */
/*          General Container                        */
/* ──────────────────────────────────────────────── */
.show-event-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
}

/* ──────────────────────────────────────────────── */
/*          Header & Breadcrumb                      */
/* ──────────────────────────────────────────────── */
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  flex-wrap: wrap;
  gap: 16px;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #666;
}

.breadcrumb-link {
  color: #2196f3;
  text-decoration: none;
  transition: color 0.2s;
}

.breadcrumb-link:hover {
  color: #1976d2;
  text-decoration: underline;
}

.breadcrumb-separator {
  color: #999;
}

.breadcrumb-current {
  color: #333;
  font-weight: 500;
}

.header-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

/* ──────────────────────────────────────────────── */
/*          Buttons                                  */
/* ──────────────────────────────────────────────── */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-secondary {
  background: white;
  color: #333;
  border: 1px solid #ddd;
}

.btn-secondary:hover {
  background: #f8f9fa;
  border-color: #ccc;
}

.btn-danger {
  background: white;
  color: #f44336;
  border: 1px solid #f44336;
}

.btn-danger:hover {
  background: #f44336;
  color: white;
}

.btn-primary {
  background: #2196f3;
  color: white;
}

.btn-primary:hover {
  background: #1976d2;
}

/* ──────────────────────────────────────────────── */
/*          Title & Status                           */
/* ──────────────────────────────────────────────── */
.title-section {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 32px;
  flex-wrap: wrap;
}

.event-title {
  font-size: clamp(28px, 5vw, 42px);
  font-weight: 700;
  margin: 0;
  color: #1a1a1a;
}

.status-badge {
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.5px;
}

.status-upcoming {
  background: #e3f2fd;
  color: #2196f3;
}

/* ──────────────────────────────────────────────── */
/*          Content Grid                             */
/* ──────────────────────────────────────────────── */
.content-grid {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 32px;
  margin-bottom: 48px;
}

@media (max-width: 1024px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
}

/* ──────────────────────────────────────────────── */
/*          Left Column - Event Image & Overview     */
/* ──────────────────────────────────────────────── */
.left-column {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.event-image-card {
  position: relative;
  border-radius: 16px;
  overflow: hidden;
  background: #f5f5f5;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.event-image {
  width: 100%;
  height: 420px;
  object-fit: cover;
  display: block;
}

.image-overlay {
  position: absolute;
  top: 20px;
  left: 20px;
  display: flex;
  gap: 10px;
  z-index: 10;
}

.badge {
  padding: 8px 16px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.badge-primary {
  background: #2196f3;
  color: white;
}

.badge-secondary {
  background: rgba(255, 255, 255, 0.95);
  color: #333;
  backdrop-filter: blur(4px);
}

.location-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 24px 20px;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.75), transparent);
  color: white;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
  z-index: 5;
}

/* ──────────────────────────────────────────────── */
/*          Overview Card                            */
/* ──────────────────────────────────────────────── */
.overview-card {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.section-title {
  font-size: 24px;
  font-weight: 700;
  margin: 0 0 24px;
  color: #111827;
}

.event-description {
  font-size: 15.5px;
  line-height: 1.8;
  color: #4b5563;
  margin-bottom: 32px;
}

.category-section {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 24px;
}

.category-item {
  display: flex;
  align-items: flex-start;
  gap: 16px;
}

.category-icon {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.category-icon-primary {
  background: #e3f2fd;
  color: #2196f3;
}

.category-icon-secondary {
  background: #f3e5f5;
  color: #9c27b0;
}

.category-label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  margin-bottom: 6px;
  letter-spacing: 0.5px;
}

.category-value {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

/* ──────────────────────────────────────────────── */
/*          Right Column - Cards                     */
/* ──────────────────────────────────────────────── */
.right-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.info-card {
  background: white;
  border-radius: 16px;
  padding: 28px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.card-title {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
}

.schedule-item {
  display: flex;
  gap: 16px;
  padding: 16px 0;
}

.schedule-item:not(:last-child) {
  border-bottom: 1px solid #f0f0f0;
}

.date-badge {
  width: 60px;
  text-align: center;
  flex-shrink: 0;
}

.date-month {
  font-size: 13px;
  font-weight: 700;
  color: #2196f3;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.date-day {
  font-size: 32px;
  font-weight: 800;
  color: #2196f3;
  line-height: 1;
}

.schedule-details {
  flex: 1;
}

.schedule-label {
  font-size: 13.5px;
  color: #6b7280;
  margin-bottom: 6px;
}

.schedule-value {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.schedule-time {
  font-size: 14.5px;
  color: #4b5563;
}

.registration-note {
  font-size: 14px;
  color: #6b7280;
  font-style: italic;
  margin-top: 16px;
}

/* ──────────────────────────────────────────────── */
/*          Gallery Section                          */
/* ──────────────────────────────────────────────── */
.images-gallery {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
  margin-top: 40px;
}

.gallery-title {
  font-size: 26px;
  font-weight: 700;
  margin: 0 0 28px;
  color: #111827;
}

.gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}

.gallery-item {
  position: relative;
  border-radius: 12px;
  overflow: hidden;
  background: #111827;
  aspect-ratio: 4 / 3;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
  transition: all 0.3s ease;
}

.gallery-item:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.gallery-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s ease;
}

.gallery-item:hover img {
  transform: scale(1.08);
}

/* Overlay gradient on hover */
.gallery-item::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.5) 0%, transparent 60%);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
}

.gallery-item:hover::after {
  opacity: 1;
}

/* Delete Button - فوق كل شيء */
.delete-btn {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 9999 !important;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: rgba(239, 68, 68, 0.95);
  color: white;
  border: 2px solid rgba(239, 68, 68, 0.4);
  backdrop-filter: blur(6px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
  opacity: 0;
  transform: scale(0.9);
  transition: all 0.25s ease;
}

.gallery-item:hover .delete-btn {
  opacity: 1;
  transform: scale(1);
}

.delete-btn:hover {
  background: #ef4444;
  transform: scale(1.1);
}

.delete-btn:active {
  transform: scale(0.95);
}

.delete-btn svg {
  width: 22px;
  height: 22px;
  stroke-width: 2.5;
}

/* ──────────────────────────────────────────────── */
/*          Loading & Error                          */
/* ──────────────────────────────────────────────── */
.loading-container,
.error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 50vh;
  gap: 24px;
  text-align: center;
}

.spinner {
  width: 60px;
  height: 60px;
  border: 5px solid #f3f3f3;
  border-top: 5px solid #2196f3;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

.error-message {
  color: #f44336;
  font-size: 18px;
  font-weight: 500;
}

/* ──────────────────────────────────────────────── */
/*          Responsive                               */
/* ──────────────────────────────────────────────── */
@media (max-width: 1024px) {
  .content-grid {
    grid-template-columns: 1fr;
  }

  .right-column {
    order: -1;
  }
}

@media (max-width: 768px) {
  .show-event-container {
    padding: 16px;
  }

  .header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .header-actions {
    width: 100%;
    flex-wrap: wrap;
    justify-content: center;
  }

  .btn {
    flex: 1 1 45%;
    justify-content: center;
  }

  .event-title {
    font-size: 32px;
  }

  .gallery-grid {
    grid-template-columns: 1fr;
  }

  .event-image {
    height: 320px;
  }
}

@media (max-width: 480px) {
  .delete-btn {
    top: 10px;
    right: 10px;
    width: 40px;
    height: 40px;
  }

  .delete-btn svg {
    width: 20px;
    height: 20px;
  }
}
</style>
