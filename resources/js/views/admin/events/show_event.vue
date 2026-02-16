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
              :src="
                event.images && event.images.length > 0
                  ? event.images[0].url
                  : 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTqqfiehshBO116BpKAfS1V4BXLBL6AqkE7aw&s'
              "
              :alt="event.title"
              class="event-image"
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

            <!-- Category Info -->
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
                <div class="schedule-time">{{ event.time || "09:00 AM PST" }}</div>
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
                <div class="schedule-time">06:00 PM PST</div>
              </div>
            </div>

            <p class="registration-note">
              Registration closes on {{ formatDate(event.start_date) }}
            </p>
          </div>

          <!-- Location Card -->
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
          </div>
        </div>
      </div>

      <!-- Event Images Gallery -->
      <div class="images-gallery" v-if="event.images && event.images.length > 1">
        <h3 class="gallery-title">Event Images</h3>
        <div class="gallery-grid">
          <div v-for="image in event.images" :key="image.id" class="gallery-item">
            <img :src="image.url" :alt="event.title" />
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-else-if="loading" class="loading-container">
      <div class="spinner"></div>
      <p>Loading event details...</p>
    </div>

    <!-- Error State -->
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
  video: string | null;
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

const route = useRoute();
const router = useRouter();
const event = ref<Event | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const fetchEvent = async () => {
  try {
    loading.value = true;
    error.value = null;

    const slug = route.params.id as string;
    const response = await axios.get(`v1/events/${slug}/single/get`);

    if (response.data.status === "success") {
      event.value = response.data.data;
    } else {
      error.value = "Failed to load event";
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || "Failed to load event";
    console.error("Error fetching event:", err);
  } finally {
    loading.value = false;
  }
};

const formatDate = (dateStr: string) => {
  const date = new Date(dateStr);
  const options: Intl.DateTimeFormatOptions = {
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
  };
  return date.toLocaleDateString("en-US", options);
};

const getMonth = (dateStr: string) => {
  const date = new Date(dateStr);
  return date.toLocaleDateString("en-US", { month: "short" }).toUpperCase();
};

const getDay = (dateStr: string) => {
  const date = new Date(dateStr);
  return date.getDate().toString().padStart(2, "0");
};

const getCityDisplay = () => {
  return event.value?.city?.name || "San Francisco";
};

const goBack = () => {
  router.push("/admin/events");
};

const editEvent = () => {
  router.push(`/admin/events/${event.value?.slug}/edit`);
};

const deleteEvent = async () => {
  if (!confirm("Are you sure you want to delete this event?")) return;

  try {
    await axios.delete(`v1/events/${event.value?.slug}/delete`);
    alert("Event deleted successfully");
    goBack();
  } catch (err) {
    alert("Failed to delete event");
    console.error("Error deleting event:", err);
  }
};

onMounted(() => {
  fetchEvent();
});
</script>

<style scoped>
.show-event-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
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
}

.breadcrumb-link:hover {
  text-decoration: underline;
}

.breadcrumb-separator {
  color: #999;
}

.breadcrumb-current {
  color: #333;
}

.header-actions {
  display: flex;
  gap: 12px;
}

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
  transition: all 0.2s;
}

.btn-secondary {
  background: #fff;
  color: #333;
  border: 1px solid #ddd;
}

.btn-secondary:hover {
  background: #f5f5f5;
}

.btn-danger {
  background: #fff;
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

.title-section {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 32px;
}

.event-title {
  font-size: 42px;
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

.content-grid {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 32px;
  margin-bottom: 40px;
}

.left-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.event-image-card {
  position: relative;
  border-radius: 16px;
  overflow: hidden;
  background: #f5f5f5;
}

.event-image {
  width: 100%;
  height: 400px;
  object-fit: cover;
  display: block;
}

.image-overlay {
  position: absolute;
  top: 20px;
  left: 20px;
  display: flex;
  gap: 8px;
}

.badge {
  padding: 8px 16px;
  border-radius: 20px;
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
  background: rgba(255, 255, 255, 0.9);
  color: #333;
}

.location-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 20px;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.7), transparent);
  color: white;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.overview-card {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-title {
  font-size: 24px;
  font-weight: 600;
  margin: 0 0 20px 0;
  color: #1a1a1a;
}

.event-description {
  font-size: 15px;
  line-height: 1.7;
  color: #555;
  margin-bottom: 32px;
}

.category-section {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.category-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.category-icon {
  width: 48px;
  height: 48px;
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
  font-size: 11px;
  font-weight: 600;
  color: #999;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.category-value {
  font-size: 15px;
  font-weight: 600;
  color: #333;
}

.right-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.info-card {
  background: white;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.card-title {
  font-size: 20px;
  font-weight: 600;
  margin: 0;
  color: #1a1a1a;
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
  width: 56px;
  text-align: center;
  flex-shrink: 0;
}

.date-month {
  font-size: 12px;
  font-weight: 600;
  color: #2196f3;
  text-transform: uppercase;
  margin-bottom: 2px;
}

.date-day {
  font-size: 28px;
  font-weight: 700;
  color: #2196f3;
  line-height: 1;
}

.schedule-details {
  flex: 1;
}

.schedule-label {
  font-size: 13px;
  color: #999;
  margin-bottom: 4px;
}

.schedule-value {
  font-size: 15px;
  font-weight: 600;
  color: #333;
  margin-bottom: 2px;
}

.schedule-time {
  font-size: 14px;
  color: #666;
}

.registration-note {
  font-size: 13px;
  color: #999;
  font-style: italic;
  margin: 16px 0 0 0;
}

.location-details {
  margin-bottom: 20px;
}

.location-name {
  font-size: 16px;
  font-weight: 600;
  margin: 0 0 8px 0;
  color: #333;
}

.location-address {
  font-size: 14px;
  color: #666;
  margin: 0 0 8px 0;
}

.location-link {
  font-size: 14px;
  color: #2196f3;
  text-decoration: none;
  font-weight: 500;
}

.location-link:hover {
  text-decoration: underline;
}

.map-placeholder {
  height: 200px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: white;
}

.map-marker {
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

.map-city {
  font-size: 14px;
  font-weight: 600;
  letter-spacing: 1px;
}

.images-gallery {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.gallery-title {
  font-size: 24px;
  font-weight: 600;
  margin: 0 0 24px 0;
  color: #1a1a1a;
}

.gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 16px;
}

.gallery-item {
  border-radius: 12px;
  overflow: hidden;
  aspect-ratio: 16/9;
}

.gallery-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.gallery-item:hover img {
  transform: scale(1.05);
}

.loading-container,
.error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  gap: 20px;
}

.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #2196f3;
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
  font-size: 16px;
}

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
    gap: 16px;
  }

  .header-actions {
    width: 100%;
    flex-wrap: wrap;
  }

  .btn {
    flex: 1;
    justify-content: center;
  }

  .event-title {
    font-size: 28px;
  }

  .title-section {
    flex-direction: column;
    align-items: flex-start;
  }

  .category-section {
    grid-template-columns: 1fr;
  }

  .gallery-grid {
    grid-template-columns: 1fr;
  }
}
</style>
