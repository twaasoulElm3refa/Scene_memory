<template>
  <UserLayout>
    <div v-if="loading" class="loading">جاري التحميل...</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <div v-else-if="event" class="scene-memory-single">
      <!-- Hero / Main Image Section -->
      <div
        class="hero"
        :style="{
          backgroundImage: `url(${getMainImageUrl()})`,
        }"
      >
        <div class="hero-overlay">
          <div class="hero-content">
            <div class="badges">
              <span class="badge private" v-if="event.private">خاص</span>
              <span class="badge shared" v-if="event.shared">مشترك</span>
            </div>
            <h1 class="title">{{ event.title }}</h1>
            <div class="owner">
              بواسطة: <strong>{{ event.user?.name || "غير معروف" }}</strong>
            </div>
            <div class="actions">
              <button class="btn secondary">رجوع للقائمة</button>
              <button class="btn primary">تعديل الذكرى</button>
            </div>
          </div>
        </div>
      </div>
      <!-- Info Cards Row -->
      <div class="info-cards">
        <div class="card">
          <div class="icon">🚤</div>
          <div class="label">التصنيف</div>
          <div class="value">{{ event.sub_categorey?.name || "غير محدد" }}</div>
        </div>
        <div class="card">
          <div class="icon">📍</div>
          <div class="label">المدينة</div>
          <div class="value">{{ event.city?.name || "غير محدد" }}</div>
        </div>
        <div class="card">
          <div class="icon">⏰</div>
          <div class="label">الوقت</div>
          <div class="value">{{ event.time || "غير محدد" }}</div>
        </div>
        <div class="card">
          <div class="icon">📅</div>
          <div class="label">تاريخ الحدث</div>
          <div class="value">{{ formatDate(event.start_date) }}</div>
        </div>
      </div>
      <!-- Main Content + Sidebar -->
      <div class="main-with-sidebar">
        <div class="main-content">
          <!-- Description -->
          <section class="section">
            <h3>تفاصيل الذكرى</h3>
            <p>{{ event.description || "لا يوجد وصف متاح" }}</p>
          </section>
          <!-- Attached Media (Images + Videos) -->
          <section class="section images-section">
            <div class="section-header">
              <h3>الصور والفيديوهات المرفقة</h3>
              <button class="btn small" @click="openAddImagesModal">إضافة ميديا</button>
            </div>
            <div class="image-grid">
              <div v-for="media in event.images" :key="media.id" class="image-item">
                <div v-if="isImage(media.url)" class="media-wrapper">
                  <img :src="getFullUrl(media.url)" :alt="`صورة ${media.id}`" />
                </div>
                <div v-else-if="isVideo(media.url)" class="media-wrapper">
                  <video controls :src="getFullUrl(media.url)" />
                </div>

                <!-- الأزرار فوق الميديا -->
                <div class="media-actions">
                  <button
                    class="action-btn fullscreen-btn"
                    @click="openFullscreen(media)"
                    title="عرض كامل الشاشة"
                  >
                    ⛶
                  </button>
                  <button
                    class="action-btn delete-btn"
                    @click="deleteImage(media.id)"
                    :disabled="deleting === media.id"
                    title="حذف"
                  >
                    {{ deleting === media.id ? "جاري الحذف..." : "🗑" }}
                  </button>
                </div>
              </div>
              <div v-if="!event.images?.length" class="no-images">
                لا توجد صور أو فيديوهات
              </div>
            </div>
          </section>
        </div>
        <!-- Right Sidebar -->
        <aside class="sidebar">
          <div class="sidebar-card owner-card">
            <img
              src="https://media.istockphoto.com/id/2151669184/vector/vector-flat-illustration-in-grayscale-avatar-user-profile-person-icon-gender-neutral.jpg?s=612x612&w=0&k=20&c=UEa7oHoOL30ynvmJzSCIPrwwopJdfqzBs0q69ezQoM8="
              alt="avatar"
              class="avatar"
            />
            <h4>{{ event.user?.name || "Mohamed Maher" }}</h4>
            <p>صاحب الذكرى</p>
          </div>
          <div class="sidebar-card stats">
            <h4>إحصائيات الذكرى</h4>
            <ul>
              <li>
                تاريخ الإنشاء: <strong>{{ formatDate(event.created_at) }}</strong>
              </li>
              <li>عدد المشاهدات: <strong>12</strong></li>
              <li>الحالة: <strong class="status-active">نشط</strong></li>
            </ul>
          </div>
          <div
            class="sidebar-card actions"
            style="display: flex; flex-direction: column; gap: 10px"
          >
            <router-link :to="`/owner/${event.slug}/update`" class="btn full green">
              تعديل الذكرى
            </router-link>
            <button
              class="btn full danger"
              @click="deleteEvent"
              :disabled="deletingEvent"
            >
              {{ deletingEvent ? "جاري الحذف..." : "حذف الذكرى" }}
            </button>
          </div>
        </aside>
      </div>
    </div>
    <!-- Add Media Modal (Images + Videos) -->
    <div v-if="showAddModal" class="modal-overlay" @click.self="closeAddImagesModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>إضافة صور أو فيديوهات جديدة</h3>
          <button class="close-btn" @click="closeAddImagesModal">×</button>
        </div>
        <div class="modal-body">
          <div class="dropzone" @dragover.prevent @drop.prevent="handleDrop">
            <input
              type="file"
              multiple
              accept="image/*,video/*"
              @change="handleFileChange"
              ref="fileInput"
              hidden
            />
            <p>اسحب الصور أو الفيديوهات هنا أو</p>
            <button
              type="button"
              class="btn primary small"
              @click="$refs.fileInput.click()"
            >
              اختر من الجهاز
            </button>
            <p class="hint">
              يمكنك اختيار صور (jpg, png, ...) أو فيديوهات (mp4, mov, ...)
            </p>
          </div>
          <div v-if="selectedFiles.length" class="preview-grid">
            <div v-for="(file, index) in selectedFiles" :key="index" class="preview-item">
              <div v-if="file.type.startsWith('image/')" class="preview-media">
                <img :src="file.preview" alt="معاينة" />
              </div>
              <div v-else-if="file.type.startsWith('video/')" class="preview-media">
                <video controls :src="file.preview" />
              </div>
              <button class="remove-preview" @click="removeFile(index)">×</button>
              <p class="file-name">{{ file.name }}</p>
            </div>
          </div>
          <p v-if="uploadError" class="error-text">{{ uploadError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn secondary" @click="closeAddImagesModal">إلغاء</button>
          <button
            class="btn primary"
            :disabled="uploading || !selectedFiles.length"
            @click="uploadImages"
          >
            {{ uploading ? "جاري الرفع..." : "رفع الميديا" }}
          </button>
        </div>
      </div>
    </div>

    <!-- Fullscreen Modal -->
    <div v-if="fullscreenMedia" class="fullscreen-modal" @click="closeFullscreen">
      <button class="close-fullscreen" @click="closeFullscreen">×</button>
      <div class="fullscreen-content" @click.stop>
        <img
          v-if="isImage(fullscreenMedia.url)"
          :src="getFullUrl(fullscreenMedia.url)"
          alt="عرض كامل"
        />
        <video
          v-else-if="isVideo(fullscreenMedia.url)"
          controls
          autoplay
          :src="getFullUrl(fullscreenMedia.url)"
        />
      </div>
    </div>
  </UserLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { useRoute } from "vue-router";
import UserLayout from "../../layouts/user/UserLayout.vue";
import { UserDashboardService } from "../../services/UserDashboardService/UserDashboardService";

const route = useRoute();
const slug = route.params.slug;

const event = ref(null);
const loading = ref(true);
const error = ref(null);
const deleting = ref(null);

const showAddModal = ref(false);
const selectedFiles = ref([]);
const fileInput = ref(null);
const uploading = ref(false);
const uploadError = ref(null);

const fullscreenMedia = ref(null); // ← جديد: لتخزين الميديا المعروضة بكامل الشاشة
const deletingEvent = ref(false);

async function deleteEvent() {
  if (
    !confirm("هل أنت متأكد من حذف هذه الذكرى نهائيًا؟\nهذا الإجراء لا يمكن التراجع عنه.")
  ) {
    return;
  }
  if (!event.value?.id) {
    alert("لا يمكن العثور على معرف الذكرى");
    return;
  }
  try {
    deletingEvent.value = true;
    const response = await UserDashboardService.deleteEvent(event.value.id);
    if (response.data.status === "success") {
      alert("تم حذف الذكرى بنجاح");
      window.location.href = "/owner";
    } else {
      alert(response.data.message || "حدث خطأ أثناء الحذف");
    }
  } catch (err) {
    console.error(err);
    alert("فشل حذف الذكرى، حاول مرة أخرى");
  } finally {
    deletingEvent.value = false;
  }
}

function getFullUrl(path) {
  if (!path) return "";
  if (path.startsWith("http")) return path;
  return `${import.meta.env.VITE_API_URL || ""}${path}`;
}

function getMainImageUrl() {
  if (event.value?.images?.length > 0) {
    const first = event.value.images[0];
    if (isImage(first.url)) {
      return getFullUrl(first.url);
    }
  }
  // fallback
  return "https://via.placeholder.com/1200x600?text=صورة+الذكرى+الرئيسية";
}

function formatDate(dateStr) {
  if (!dateStr) return "غير محدد";
  return new Date(dateStr).toLocaleDateString("ar-EG", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

function isImage(url) {
  if (!url) return false;
  const lower = url.toLowerCase();
  return (
    lower.endsWith(".jpg") ||
    lower.endsWith(".jpeg") ||
    lower.endsWith(".png") ||
    lower.endsWith(".gif") ||
    lower.endsWith(".webp") ||
    lower.endsWith(".bmp")
  );
}

function isVideo(url) {
  if (!url) return false;
  const lower = url.toLowerCase();
  return (
    lower.endsWith(".mp4") ||
    lower.endsWith(".mov") ||
    lower.endsWith(".avi") ||
    lower.endsWith(".mkv") ||
    lower.endsWith(".webm")
  );
}

async function fetchEvent() {
  try {
    const res = await UserDashboardService.getSingleEvent(slug);
    if (res.data.status === "success") {
      event.value = res.data.data;
    } else {
      error.value = res.data.message || "حدث خطأ";
    }
  } catch (err) {
    error.value = "فشل جلب البيانات";
    console.error(err);
  } finally {
    loading.value = false;
  }
}

async function deleteImage(id) {
  if (!confirm("متأكد من حذف الميديا؟")) return;
  try {
    deleting.value = id;
    await UserDashboardService.deleteMedia(id);
    event.value.images = event.value.images.filter((i) => i.id !== id);
  } catch (err) {
    alert("فشل الحذف");
  } finally {
    deleting.value = null;
  }
}

// ── Fullscreen Logic ────────────────────────────────────────────────
function openFullscreen(media) {
  fullscreenMedia.value = media;
  // يمكن إضافة document.body.style.overflow = 'hidden' إذا أردت
}

function closeFullscreen() {
  fullscreenMedia.value = null;
}

// ── Modal Logic ────────────────────────────────────────────────
function openAddImagesModal() {
  showAddModal.value = true;
  selectedFiles.value = [];
  uploadError.value = null;
}

function closeAddImagesModal() {
  if (uploading.value) return;
  showAddModal.value = false;
  selectedFiles.value = [];
  if (fileInput.value) fileInput.value.value = "";
}

function handleFileChange(e) {
  const files = Array.from(e.target.files);
  addFiles(files);
}

function handleDrop(e) {
  const files = Array.from(e.dataTransfer.files);
  addFiles(files);
}

function addFiles(newFiles) {
  newFiles.forEach((file) => {
    if (!file.type.startsWith("image/") && !file.type.startsWith("video/")) return;
    const preview = URL.createObjectURL(file);
    selectedFiles.value.push({
      file,
      preview,
      name: file.name,
      type: file.type,
    });
  });
}

function removeFile(index) {
  const removed = selectedFiles.value.splice(index, 1)[0];
  URL.revokeObjectURL(removed.preview);
}

async function uploadImages() {
  if (!selectedFiles.value.length) return;
  uploading.value = true;
  uploadError.value = null;

  const formData = new FormData();
  selectedFiles.value.forEach((item) => {
    formData.append("url[]", item.file);
  });

  try {
    const response = await UserDashboardService.uploadMedia(slug, formData);

    if (response.data.status === "success") {
      if (response.data.data?.images) {
        event.value.images = [...event.value.images, ...response.data.data.images];
      } else {
        await fetchEvent();
      }
      closeAddImagesModal();
      alert("تم رفع الميديا بنجاح");
      window.location.reload();
    } else {
      uploadError.value = response.data.message || "حدث خطأ أثناء الرفع";
    }
  } catch (err) {
    console.error(err);
    uploadError.value = "فشل رفع الميديا، حاول مرة أخرى";
  } finally {
    uploading.value = false;
  }
}

onUnmounted(() => {
  selectedFiles.value.forEach((item) => {
    URL.revokeObjectURL(item.preview);
  });
});

onMounted(fetchEvent);
</script>

<style scoped>
/* ------------------ تعديلات الأنماط الجديدة ------------------ */

.image-item {
  position: relative;
  overflow: hidden;
  border-radius: 8px;
  background: #f0f0f0;
}

.media-wrapper {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.media-wrapper img,
.media-wrapper video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* الأزرار فوق الميديا */
.media-actions {
  position: absolute;
  top: 8px;
  right: 8px;
  display: flex;
  gap: 8px;
  opacity: 0;
  transition: opacity 0.25s ease;
  pointer-events: none;
}

.image-item:hover .media-actions {
  opacity: 1;
  pointer-events: auto;
}

.action-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
  transition: all 0.2s;
}

.action-btn:hover {
  transform: scale(1.1);
}

.fullscreen-btn {
  background: rgba(0, 0, 0, 0.6);
  color: white;
}

.delete-btn {
  background: rgba(220, 38, 38, 0.85);
  color: white;
}

.delete-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Fullscreen Modal */
.fullscreen-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.95);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.fullscreen-content {
  max-width: 95vw;
  max-height: 95vh;
  position: relative;
}

.fullscreen-content img,
.fullscreen-content video {
  max-width: 100%;
  max-height: 90vh;
  object-fit: contain;
  border-radius: 8px;
}

.close-fullscreen {
  position: absolute;
  top: 20px;
  right: 30px;
  background: rgba(0, 0, 0, 0.5);
  color: white;
  border: none;
  font-size: 40px;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
}

.close-fullscreen:hover {
  background: rgba(220, 38, 38, 0.8);
}

/* باقي الأنماط الخاصة بك موجودة مسبقًا */
</style>
<style scoped>
/* أضف هذه الـ styles حسب تصميمك - مثال بسيط */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-bottom: 1px solid #eee;
}

.close-btn {
  background: none;
  border: none;
  font-size: 28px;
  cursor: pointer;
}

.modal-body {
  padding: 24px;
}

.dropzone {
  border: 2px dashed #aaa;
  border-radius: 12px;
  padding: 40px 20px;
  text-align: center;
  background: #f9f9f9;
  transition: all 0.2s;
}

.dropzone:hover {
  border-color: #666;
  background: #f0f0f0;
}

.preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 16px;
  margin-top: 24px;
}

.preview-item {
  position: relative;
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
  background: #fafafa;
}

.preview-item img {
  width: 100%;
  height: 140px;
  object-fit: cover;
}

.remove-preview {
  position: absolute;
  top: 6px;
  right: 6px;
  background: rgba(220, 53, 69, 0.9);
  color: white;
  border: none;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  cursor: pointer;
  font-weight: bold;
}

.file-name {
  font-size: 0.85rem;
  text-align: center;
  padding: 6px;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #eee;
}

.error-text {
  color: #dc3545;
  margin-top: 12px;
  text-align: center;
}

.hint {
  color: #777;
  font-size: 0.9rem;
  margin-top: 12px;
}

.scene-memory-single {
  font-family: "Tajawal", system-ui, sans-serif;
  direction: rtl;
  color: #333;
}

.hero {
  position: relative;
  height: 55vh;
  min-height: 420px;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
}

.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to top,
    rgba(0, 0, 0, 0.75) 0%,
    rgba(0, 0, 0, 0.35) 50%,
    transparent 100%
  );
  display: flex;
  align-items: flex-end;
  padding: 2.5rem 3rem;
}

.hero-content {
  max-width: 1100px;
  width: 100%;
  margin: 0 auto;
  color: white;
}

.badges {
  margin-bottom: 1rem;
}

.badge {
  padding: 0.35rem 1rem;
  border-radius: 30px;
  font-size: 0.9rem;
  margin-left: 0.6rem;
}

.badge.private {
  background: #d00000;
}
.badge.shared {
  background: #006d77;
}

.title {
  font-size: 2.6rem;
  margin: 0.4rem 0 1rem;
  font-weight: 700;
  line-height: 1.2;
}

.owner {
  font-size: 1.15rem;
  opacity: 0.9;
  margin-bottom: 1.8rem;
}

.actions {
  display: flex;
  gap: 1rem;
}

.btn {
  padding: 0.7rem 1.6rem;
  border-radius: 50px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  background-color: #006d77;
  color: rgba(255, 255, 255, 0.74);
  transition: all 0.3s ease;
}

.btn:hover {
  background-color: #05666e;
  color: white;
  transform: scale(1.1);
}

.btn.primary {
  background: #028090;
  color: white;
}
.btn.secondary {
  background: rgba(255, 255, 255, 0.25);
  color: white;
  backdrop-filter: blur(4px);
}
.btn.small {
  padding: 0.5rem 1.2rem;
  font-size: 0.9rem;
}
.btn.full {
  width: 100%;
}
.btn.danger {
  background: #d00000;
  color: white;
}
.green {
  background-color: rgba(0, 128, 0, 0.877);
  color: wheat;
}
.green:hover {
  background-color: green;
  color: white;
  transition: scale(1.1);
}
.info-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 1.2rem;
  max-width: 1100px;
  margin: -3.5rem auto 2.5rem;
  padding: 0 1.5rem;
  position: relative;
  z-index: 2;
}

.card {
  background: white;
  border-radius: 12px;
  padding: 1.4rem;
  text-align: center;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
}

.card .icon {
  font-size: 2.1rem;
  margin-bottom: 0.6rem;
}
.card .label {
  color: #666;
  font-size: 0.9rem;
}
.card .value {
  font-weight: 700;
  font-size: 1.15rem;
  margin-top: 0.3rem;
}

.main-with-sidebar {
  display: flex;
  gap: 2.5rem;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem 4rem;
}

.main-content {
  flex: 3;
}

.sidebar {
  flex: 1;
  min-width: 280px;
}

.section {
  background: white;
  border-radius: 12px;
  padding: 1.8rem;
  margin-bottom: 2rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.2rem;
}

.image-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1.2rem;
}

.image-item {
  position: relative;
  border-radius: 10px;
  overflow: hidden;
  aspect-ratio: 4/3;
}

.image-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.delete-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  color: white;
  border: none;
  opacity: 0;
  transition: opacity 0.2s;
  cursor: pointer;
  font-size: 1.1rem;
}

.image-item:hover .delete-overlay {
  opacity: 1;
}

.sidebar-card {
  background: white;
  border-radius: 12px;
  padding: 1.6rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
}

.avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  margin: 0 auto 1rem;
  display: block;
}

.owner-card {
  text-align: center;
}

.stats ul {
  list-style: none;
  padding: 0;
  margin: 1rem 0 0;
}

.stats li {
  margin: 0.7rem 0;
}

.status-active {
  color: #2a9d8f;
  font-weight: 600;
}

.loading,
.error {
  text-align: center;
  padding: 6rem 1rem;
  font-size: 1.3rem;
}

.error {
  color: #d00000;
}

@media (max-width: 900px) {
  .main-with-sidebar {
    flex-direction: column;
  }
  .sidebar {
    order: -1;
  }
  .info-cards {
    margin: -2rem auto 2rem;
  }
}
</style>
