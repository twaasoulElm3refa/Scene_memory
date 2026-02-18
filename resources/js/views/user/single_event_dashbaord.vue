<template>
  <UserLayout>
    <div v-if="loading" class="loading">جاري التحميل...</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <div v-else-if="event" class="scene-memory-single">
      <!-- Hero / Main Image Section -->
      <div class="hero" :style="{ backgroundImage: `url(${getFullUrl(event.image)})` }">
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

          <!-- Additional Images -->
          <section class="section images-section">
            <div class="section-header">
              <h3>الصور المرفقة</h3>
              <button class="btn small">إضافة صور</button>
            </div>

            <div class="image-grid">
              <div v-for="img in event.images" :key="img.id" class="image-item">
                <img :src="getFullUrl(img.url)" :alt="`صورة ${img.id}`" />
                <button
                  class="delete-overlay"
                  @click="deleteImage(img.id)"
                  :disabled="deleting === img.id"
                >
                  {{ deleting === img.id ? "جاري الحذف..." : "حذف" }}
                </button>
              </div>

              <div v-if="!event.images?.length" class="no-images">لا توجد صور إضافية</div>
            </div>
          </section>
        </div>

        <!-- Right Sidebar -->
        <aside class="sidebar">
          <div class="sidebar-card owner-card">
            <img src="https://via.placeholder.com/80" alt="avatar" class="avatar" />
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

          <div class="sidebar-card actions">
            <button class="btn full danger">حذف الذكرى</button>
          </div>
        </aside>
      </div>
    </div>
  </UserLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import axios from "axios";
import UserLayout from "../../layouts/user/UserLayout.vue";

const route = useRoute();
const slug = route.params.slug;

const event = ref(null);
const loading = ref(true);
const error = ref(null);
const deleting = ref(null);

const API_BASE = "/v1";

function getFullUrl(path) {
  if (!path) return "";
  if (path.startsWith("http")) return path;
  return `${import.meta.env.VITE_API_URL || ""}${path}`;
}

function formatDate(dateStr) {
  if (!dateStr) return "غير محدد";
  return new Date(dateStr).toLocaleDateString("ar-EG", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

async function fetchEvent() {
  try {
    const res = await axios.get(`${API_BASE}/events/${slug}/single/get`);
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
  if (!confirm("متأكد من حذف الصورة؟")) return;
  try {
    deleting.value = id;
    await axios.delete(`${API_BASE}/event-images/${id}/delete`);
    event.value.images = event.value.images.filter((i) => i.id !== id);
  } catch (err) {
    alert("فشل الحذف");
  } finally {
    deleting.value = null;
  }
}

onMounted(fetchEvent);
</script>

<style scoped>
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
