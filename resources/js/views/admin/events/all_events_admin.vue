<template>
  <AdminLayout>
    <div class="events-container">
      <h3 class="page-title">جميع الفعاليات ({{ pagination.total }} فعاليه)</h3>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>جاري تحميل الفعاليات...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-state">
        <p>{{ error }}</p>
        <button @click="fetchEvents" class="retry-btn">إعادة المحاولة</button>
      </div>

      <!-- Events Grid -->
      <div v-else-if="events.length > 0" class="events-content">
        <div class="events-grid">
          <div v-for="event in events" :key="event.id" class="event-card">
            <div class="event-image">
              <img
                :src="getEventImage(event)"
                :alt="event.title"
                @error="handleImageError"
              />
            </div>

            <div class="event-details">
              <h4 class="event-title">{{ event.translation.title }}</h4>

              <div class="event-info">
                <div class="info-item">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                  </svg>
                  <span>{{ formatDate(event.start_date) }}</span>
                </div>

                <div class="info-item">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                  </svg>
                  <span>{{ event.city?.name || "غير محدد" }}</span>
                </div>

                <div class="info-item">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                  </svg>
                  <span>{{ event.sub_categorey?.name || "غير محدد" }}</span>
                </div>
              </div>

              <div class="event-actions">
                <router-link :to="`/admin/events/${event.slug}`" class="btn-view">
                  التفاصيل
                </router-link>
                <button @click="editEvent(event.slug)" class="btn-edit">تعديل</button>
                <button
                  @click="confirmDelete(event.slug, event.title)"
                  class="btn-delete"
                >
                  حذف
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div class="pagination" v-if="pagination.last_page > 1">
          <button
            @click="goToPage(pagination.current_page - 1)"
            :disabled="!pagination.prev_page_url"
            class="pagination-btn"
          >
            السابق
          </button>

          <div class="pagination-pages">
            <button
              v-for="page in paginationPages"
              :key="page"
              @click="goToPage(page)"
              :class="['pagination-page', { active: page === pagination.current_page }]"
            >
              {{ page }}
            </button>
          </div>

          <button
            @click="goToPage(pagination.current_page + 1)"
            :disabled="!pagination.next_page_url"
            class="pagination-btn"
          >
            التالي
          </button>
        </div>

        <!-- Stats -->
        <div class="stats-footer">
          <p>
            عرض {{ pagination.from }} إلى {{ pagination.to }} من أصل
            {{ pagination.total }} فعالية
          </p>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="empty-state">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="64"
          height="64"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        <h4>لا توجد فعاليات</h4>
        <p>لم يتم العثور على أي فعاليات حالياً</p>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import eventService from "@/services/admin/events/eventsService";

export default {
  components: {
    AdminLayout,
  },

  setup() {
    const router = useRouter();

    const events = ref([]);
    const loading = ref(true);
    const error = ref(null);

    const pagination = ref({
      current_page: 1,
      last_page: 1,
      from: 0,
      to: 0,
      total: 0,
      per_page: 8,
      next_page_url: null,
      prev_page_url: null,
    });

    // Computed
    const paginationPages = computed(() => {
      const pages = [];
      const maxPages = 5;
      let start = Math.max(1, pagination.value.current_page - Math.floor(maxPages / 2));
      let end = Math.min(pagination.value.last_page, start + maxPages - 1);

      if (end - start < maxPages - 1) {
        start = Math.max(1, end - maxPages + 1);
      }

      for (let i = start; i <= end; i++) {
        pages.push(i);
      }

      return pages;
    });

    // Methods
    const fetchEvents = async (page = 1) => {
      loading.value = true;
      error.value = null;

      try {
        const response = await eventService.getAllEvents(page);

        if (response.status === "success") {
          events.value = response.data.data;
          pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            from: response.data.from,
            to: response.data.to,
            total: response.data.total,
            per_page: response.data.per_page,
            next_page_url: response.data.next_page_url,
            prev_page_url: response.data.prev_page_url,
          };
        }
      } catch (err) {
        error.value = "حدث خطأ أثناء تحميل الفعاليات";
        console.error("Error:", err);
      } finally {
        loading.value = false;
      }
    };

    const goToPage = (page) => {
      if (page >= 1 && page <= pagination.value.last_page) {
        fetchEvents(page);
        window.scrollTo({ top: 0, behavior: "smooth" });
      }
    };

    const formatDate = (dateString) => {
      const date = new Date(dateString);
      return date.toLocaleDateString("ar-EG", {
        year: "numeric",
        month: "long",
        day: "numeric",
      });
    };

    const handleImageError = (e) => {
      e.target.src =
        "https://developers.elementor.com/docs/assets/img/elementor-placeholder-image.png";
    };

    const confirmDelete = (id, title) => {
      if (!confirm(`هل أنت متأكد من حذف الفعالية "${title}" ؟`)) {
        return;
      }

      deleteEvent(id);
    };

    const getEventImage = (event) => {
      if (event.first_image && event.first_image.url) {
        const imageUrl = event.first_image.url;
        if (imageUrl.startsWith("http://") || imageUrl.startsWith("https://")) {
          return imageUrl;
        }
        return `/storage/${imageUrl.replace(/^\/+/, "")}`;
      }
      return "https://developers.elementor.com/docs/assets/img/elementor-placeholder-image.png";
    };

    const deleteEvent = async (id) => {
      try {
        const response = await eventService.deleteEvent(id);

        if (response.status === "success") {
          alert("تم حذف الفعالية بنجاح");
          fetchEvents(pagination.value.current_page);
        }
      } catch (err) {
        alert("فشل في حذف الفعالية");
        console.error("Delete error:", err);
      }
    };

    const editEvent = (slug) => {
      router.push(`/admin/events/${slug}/edit`);
    };
    onMounted(() => {
      fetchEvents();
    });

    return {
      events,
      loading,
      error,
      pagination,
      paginationPages,
      fetchEvents,
      goToPage,
      formatDate,
      handleImageError,
      confirmDelete,
      editEvent,
      getEventImage,
    };
  },
};
</script>

<style scoped>
.events-container {
  padding: 2rem;
  direction: rtl;
}

.page-title {
  font-size: 2rem;
  font-weight: bold;
  margin-bottom: 2rem;
  color: #1a1a1a;
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem;
  gap: 1rem;
}

.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #f3f4f6;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Error State */
.error-state {
  text-align: center;
  padding: 3rem;
  background: #fee;
  border-radius: 8px;
  color: #c33;
}

.retry-btn {
  margin-top: 1rem;
  padding: 0.5rem 1.5rem;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 1rem;
}

.retry-btn:hover {
  background: #2563eb;
}

/* Events Grid */
.events-content {
  animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.events-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 2rem;
  margin-bottom: 2rem;
}

.event-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.event-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.event-image {
  width: 100%;
  height: 200px;
  overflow: hidden;
  background: #f3f4f6;
}

.event-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.event-card:hover .event-image img {
  transform: scale(1.05);
}

.event-details {
  padding: 1.5rem;
}

.event-title {
  font-size: 1.25rem;
  font-weight: bold;
  margin-bottom: 1rem;
  color: #1a1a1a;
}

.event-info {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.info-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #666;
  font-size: 0.9rem;
}

.info-item svg {
  flex-shrink: 0;
  color: #3b82f6;
}

.event-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.event-actions button {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.875rem;
  transition: all 0.3s ease;
  flex: 1;
  min-width: fit-content;
}

.btn-view {
  font-size: 16px;
  background: #3b82f6;
  color: white;
  text-decoration: none;
  padding: 2px 2px;
  border-radius: 10%;
  transition: all 0.3s ease;
}

.btn-view:link,
.btn-view:visited {
  text-decoration: none;
  color: white;
}

.btn-view:hover {
  background: #2563eb;
}
.event-actions {
  display: flex;
  gap: 8px;
}

.event-actions .btn-view,
.event-actions .btn-edit,
.event-actions .btn-delete {
  flex: 1;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-edit {
  background: #10b981;
  color: white;
}

.btn-edit:hover {
  background: #059669;
}

.btn-delete {
  background: #ef4444;
  color: white;
}

.btn-delete:hover {
  background: #dc2626;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin: 2rem 0;
}

.pagination-btn {
  padding: 0.5rem 1rem;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.3s ease;
}

.pagination-btn:hover:not(:disabled) {
  background: #2563eb;
}

.pagination-btn:disabled {
  background: #d1d5db;
  cursor: not-allowed;
}

.pagination-pages {
  display: flex;
  gap: 0.5rem;
}

.pagination-page {
  width: 40px;
  height: 40px;
  border: 2px solid #e5e7eb;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-weight: 500;
}

.pagination-page:hover {
  border-color: #3b82f6;
  color: #3b82f6;
}

.pagination-page.active {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

/* Stats Footer */
.stats-footer {
  text-align: center;
  color: #666;
  font-size: 0.9rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 4rem;
  color: #666;
}

.empty-state svg {
  margin-bottom: 1rem;
  color: #d1d5db;
}

.empty-state h4 {
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
  color: #1a1a1a;
}

/* Responsive */
@media (max-width: 768px) {
  .events-container {
    padding: 1rem;
  }

  .events-grid {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }

  .pagination {
    flex-wrap: wrap;
  }

  .pagination-pages {
    order: 3;
    width: 100%;
    justify-content: center;
  }
}
</style>
