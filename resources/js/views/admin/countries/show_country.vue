<template>
  <AdminLayout>
    <div class="country-detail-page">
      <!-- Breadcrumb Navigation -->
      <nav class="breadcrumb">
        <router-link to="/admin" class="breadcrumb-link">Dashboard</router-link>
        <span class="breadcrumb-separator">/</span>
        <router-link to="/admin/countries" class="breadcrumb-link">Countries</router-link>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">{{ country?.name }}</span>
      </nav>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <div class="spinner"></div>
        <p>Loading country data...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-container">
        <div class="error-icon">⚠️</div>
        <h3>Failed to Load Country</h3>
        <p>{{ error }}</p>
        <button @click="fetchCountry" class="retry-btn">Retry</button>
      </div>

      <!-- Main Content -->
      <div v-else-if="country" class="country-content">
        <!-- Header Section -->
        <header class="country-header">
          <div class="country-header-left">
            <img
              :src="getImageUrl(country.image)"
              :alt="country.name"
              class="country-flag"
            />
            <div class="country-title-section">
              <div class="title-row">
                <h1 class="country-name">{{ country.name }}</h1>
                <span class="status-badge" :class="statusClass">
                  {{ country.deleted_at ? "INACTIVE" : "ACTIVE" }}
                </span>
              </div>
              <p class="country-meta">
                {{ country.code || "No Code" }} • {{ formatDate(country.created_at) }}
              </p>
            </div>
          </div>
          <div class="country-header-actions">
            <button class="btn-secondary" @click="shareCountry">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                <polyline points="16 6 12 2 8 6"></polyline>
                <line x1="12" y1="2" x2="12" y2="15"></line>
              </svg>
              Share
            </button>
            <!-- <router-link :to="`/admin/countries`" class="btn-primary">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
                ></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
              Create City
            </router-link> -->
          </div>
        </header>

        <!-- Statistics Cards -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon cities-icon">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
              </svg>
            </div>
            <div class="stat-content">
              <div class="stat-header">
                <span class="stat-label">Total Cities</span>
                <span class="stat-change positive">+{{ stats.cityGrowth }}%</span>
              </div>
              <div class="stat-value">{{ stats.totalCities }}</div>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon events-icon">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
            </div>
            <div class="stat-content">
              <div class="stat-header">
                <span class="stat-label">Total Events</span>
                <span class="stat-change positive">+{{ stats.eventGrowth }}%</span>
              </div>
              <div class="stat-value">{{ stats.totalEvents.toLocaleString() }}</div>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon users-icon">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </div>
            <div class="stat-content">
              <div class="stat-header">
                <span class="stat-label">Active Users</span>
                <span class="stat-change negative">-{{ stats.userChange }}%</span>
              </div>
              <div class="stat-value">{{ stats.activeUsers }}</div>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon growth-icon">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                <polyline points="17 6 23 6 23 12"></polyline>
              </svg>
            </div>
            <div class="stat-content">
              <div class="stat-header">
                <span class="stat-label">Monthly Growth</span>
                <span class="stat-change positive">+{{ stats.monthlyGrowth }}%</span>
              </div>
              <div class="stat-value">+{{ stats.monthlyGrowth }}%</div>
            </div>
          </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
          <!-- Major Cities Section -->
          <section class="cities-section">
            <div class="section-header">
              <h2>Major Cities</h2>
              <!-- <button @click="viewAllCities" class="view-all-link">View All</button> -->
            </div>

            <div class="table-container">
              <table class="cities-table">
                <thead>
                  <tr>
                    <th>CITY NAME</th>
                    <th>CREATED AT</th>
                    <th class="text-right">STATUS</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="city in country.cities" :key="city.id" class="city-row">
                    <td class="city-name">{{ city.name }}</td>
                    <td class="city-region">{{ formatDate(city.created_at) }}</td>
                    <td class="event-count text-right">
                      <span
                        class="status-badge"
                        :class="city.deleted_at ? 'status-inactive' : 'status-active'"
                      >
                        {{ city.deleted_at ? "Inactive" : "Active" }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="!country.cities || country.cities.length === 0">
                    <td colspan="3" class="no-data">No cities available</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- Recent Events Section -->
        <section class="events-section">
          <div class="section-header">
            <h2>Recent Events</h2>
            <div class="event-navigation">
              <button
                class="nav-btn"
                @click="previousEvents"
                :disabled="!eventsPagination.prev_page_url"
              >
                <svg
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
              </button>
              <span class="pagination-info">
                Page {{ eventsPagination.current_page }} of
                {{ eventsPagination.last_page }}
              </span>
              <button
                class="nav-btn"
                @click="nextEvents"
                :disabled="!eventsPagination.next_page_url"
              >
                <svg
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
              </button>
            </div>
          </div>

          <div class="events-list">
            <div v-for="event in recentEvents" :key="event.id" class="event-item">
              <div class="event-icon" :style="`background: ${event.iconColor}`">
                <span>{{ event.icon }}</span>
              </div>
              <div class="event-details">
                <h4 class="event-title">{{ event.title }}</h4>
                <p class="event-meta">{{ event.location }} • {{ event.date }}</p>
              </div>
              <span class="event-badge" :class="event.status.toLowerCase()">
                {{ event.status }}
              </span>
            </div>

            <div v-if="recentEvents.length === 0" class="no-events">
              <p>No events available</p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { countryService } from "../../../services/admin/countries/countryService";

const route = useRoute();
const router = useRouter();

// State
const country = ref(null);
const loading = ref(true);
const error = ref(null);

// Stats من الـ API
const stats = ref({
  totalCities: 0,
  cityGrowth: 3,
  totalEvents: 0,
  eventGrowth: 12,
  activeUsers: 0,
  userChange: 1.2,
  monthlyGrowth: 14.2,
});

// Events من الـ API
const recentEvents = ref([]);

// Pagination للـ events
const eventsPagination = ref({
  current_page: 1,
  last_page: 1,
  prev_page_url: null,
  next_page_url: null,
});

// Computed
const statusClass = computed(() => {
  return country.value?.deleted_at ? "status-inactive" : "status-active";
});

// Helper functions لتحديد حالة الـ event
const getEventStatus = (startDate, endDate) => {
  const now = new Date();
  const start = new Date(startDate);
  const end = new Date(endDate);

  if (now >= start && now <= end) {
    return "LIVE";
  } else if (now < start) {
    return "UPCOMING";
  } else {
    return "PASSED";
  }
};

// Helper function لتحديد الأيقونة حسب الـ category
const getEventIcon = (categoryId) => {
  const icons = {
    1: "⚽", // Sports
    2: "🎭", // Entertainment
    3: "🏛️", // Tourism
    4: "📚", // Culture
  };
  return icons[categoryId] || "📅";
};

// Helper function لتحديد اللون حسب الـ category
const getEventColor = (categoryId) => {
  const colors = {
    1: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
    2: "linear-gradient(135deg, #f093fb 0%, #f5576c 100%)",
    3: "linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)",
    4: "linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)",
  };
  return colors[categoryId] || "linear-gradient(135deg, #667eea 0%, #764ba2 100%)";
};

// Methods
const fetchCountry = async (page = 1) => {
  loading.value = true;
  error.value = null;

  try {
    const response = await countryService.getCountryDetails(route.params.id, page);

    if (response.data.status === "success") {
      // تحديث بيانات الـ country
      country.value = response.data.data.countries;

      // تحديث الإحصائيات من الـ API
      stats.value = {
        totalCities: response.data.data.cities || 0,
        cityGrowth: 3, // يمكن حسابها لاحقاً من البيانات
        totalEvents: response.data.data.countevents || 0,
        eventGrowth: 12, // يمكن حسابها لاحقاً من البيانات
        activeUsers: response.data.data.users || 0,
        userChange: 1.2, // يمكن حسابها لاحقاً من البيانات
        monthlyGrowth: 14.2, // يمكن حسابها لاحقاً من البيانات
      };

      // تحديث الـ events من الـ API
      if (response.data.data.events && response.data.data.events.data) {
        recentEvents.value = response.data.data.events.data.map((event) => ({
          id: event.id,
          title: event.title,
          location: event.description || "No description",
          date: formatEventDate(event.start_date, event.end_date),
          status: getEventStatus(event.start_date, event.end_date),
          icon: getEventIcon(event.sub_categorey_id),
          iconColor: getEventColor(event.sub_categorey_id),
        }));

        // تحديث معلومات الـ pagination
        eventsPagination.value = {
          current_page: response.data.data.events.current_page,
          last_page: response.data.data.events.last_page,
          prev_page_url: response.data.data.events.prev_page_url,
          next_page_url: response.data.data.events.next_page_url,
        };
      }
    } else {
      throw new Error(response.data.message || "Failed to fetch country data");
    }
  } catch (err) {
    error.value = err.message || "An error occurred while fetching country data";
    console.error("Error fetching country:", err);
  } finally {
    loading.value = false;
  }
};

const getImageUrl = (imagePath) => {
  if (!imagePath) return "/placeholder-flag.png";
  return imagePath.startsWith("http") ? imagePath : `/storage/${imagePath}`;
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

const formatEventDate = (startDate, endDate) => {
  if (!startDate) return "N/A";

  const start = new Date(startDate);
  const end = endDate ? new Date(endDate) : null;

  const startFormatted = start.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });

  if (end && startDate !== endDate) {
    const endFormatted = end.toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
    return `${startFormatted} - ${endFormatted}`;
  }

  return startFormatted;
};

const shareCountry = () => {
  // Implement share functionality
  if (navigator.share) {
    navigator
      .share({
        title: country.value.name,
        text: `Check out ${country.value.name}`,
        url: window.location.href,
      })
      .catch((err) => console.log("Error sharing:", err));
  } else {
    // Fallback: copy to clipboard
    navigator.clipboard.writeText(window.location.href);
    alert("Link copied to clipboard!");
  }
};

const viewAllCities = () => {
  router.push(`/countries/${country.value.id}/cities`);
};

const previousEvents = () => {
  if (eventsPagination.value.prev_page_url) {
    const page = eventsPagination.value.current_page - 1;
    fetchCountry(page);
  }
};

const nextEvents = () => {
  if (eventsPagination.value.next_page_url) {
    const page = eventsPagination.value.current_page + 1;
    fetchCountry(page);
  }
};

// Lifecycle
onMounted(() => {
  fetchCountry();
});
</script>

<style scoped>
.country-detail-page {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

/* Breadcrumb */
.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 2rem;
  font-size: 0.875rem;
}

.breadcrumb-link {
  color: #6b7280;
  text-decoration: none;
  transition: color 0.2s;
}

.breadcrumb-link:hover {
  color: #111827;
}

.breadcrumb-separator {
  color: #d1d5db;
}

.breadcrumb-current {
  color: #111827;
  font-weight: 500;
}

/* Loading State */
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  gap: 1rem;
}

.spinner {
  width: 48px;
  height: 48px;
  border: 4px solid #e5e7eb;
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
.error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  gap: 1rem;
  text-align: center;
}

.error-icon {
  font-size: 3rem;
}

.retry-btn {
  padding: 0.75rem 1.5rem;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
  font-weight: 500;
  transition: background 0.2s;
}

.retry-btn:hover {
  background: #2563eb;
}

/* Header */
.country-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
  margin-bottom: 2rem;
  padding: 2rem;
  background: white;
  border-radius: 1rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.country-header-left {
  display: flex;
  gap: 1.5rem;
  align-items: flex-start;
}

.country-flag {
  width: 80px;
  height: 80px;
  border-radius: 0.75rem;
  object-fit: cover;
  border: 2px solid #e5e7eb;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.5rem;
}

.country-name {
  font-size: 1.875rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-active {
  background: #d1fae5;
  color: #065f46;
}

.status-inactive {
  background: #fee2e2;
  color: #991b1b;
}

.country-meta {
  color: #6b7280;
  margin: 0;
}

.country-header-actions {
  display: flex;
  gap: 0.75rem;
}

.btn-secondary,
.btn-primary {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  border-radius: 0.5rem;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-secondary {
  background: white;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn-secondary:hover {
  background: #f9fafb;
}

.btn-primary {
  background: #3b82f6;
  color: white;
  border: none;
}

.btn-primary:hover {
  background: #2563eb;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  display: flex;
  gap: 1rem;
  padding: 1.5rem;
  background: white;
  border-radius: 1rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cities-icon {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.events-icon {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  color: white;
}

.users-icon {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  color: white;
}

.growth-icon {
  background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
  color: white;
}

.stat-content {
  flex: 1;
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.stat-label {
  font-size: 0.875rem;
  color: #6b7280;
  font-weight: 500;
}

.stat-change {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
}

.stat-change.positive {
  background: #d1fae5;
  color: #065f46;
}

.stat-change.negative {
  background: #fee2e2;
  color: #991b1b;
}

.stat-value {
  font-size: 1.875rem;
  font-weight: 700;
  color: #111827;
}

/* Cities Section */
.cities-section {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.section-header h2 {
  font-size: 1.25rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.view-all-link {
  color: #3b82f6;
  font-weight: 500;
  background: none;
  border: none;
  cursor: pointer;
  transition: color 0.2s;
}

.view-all-link:hover {
  color: #2563eb;
}

.table-container {
  overflow-x: auto;
}

.cities-table {
  width: 100%;
  border-collapse: collapse;
}

.cities-table thead th {
  text-align: left;
  padding: 0.75rem 1rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #e5e7eb;
}

.cities-table tbody td {
  padding: 1rem;
  border-bottom: 1px solid #f3f4f6;
}

.city-row:hover {
  background: #f9fafb;
}

.city-name {
  font-weight: 500;
  color: #111827;
}

.city-region {
  color: #6b7280;
}

.text-right {
  text-align: right !important;
}

.no-data {
  text-align: center;
  color: #6b7280;
  padding: 2rem !important;
}

/* Events Section */
.events-section {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.event-navigation {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pagination-info {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0 0.5rem;
}

.nav-btn {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
}

.nav-btn:hover:not(:disabled) {
  background: #f9fafb;
  border-color: #9ca3af;
}

.nav-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.events-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.event-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  transition: all 0.2s;
}

.event-item:hover {
  border-color: #d1d5db;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.event-icon {
  width: 48px;
  height: 48px;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.event-details {
  flex: 1;
}

.event-title {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
  margin: 0 0 0.25rem 0;
}

.event-meta {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
}

.event-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.event-badge.live {
  background: #d1fae5;
  color: #065f46;
}

.event-badge.upcoming {
  background: #dbeafe;
  color: #1e40af;
}

.event-badge.passed {
  background: #f3f4f6;
  color: #6b7280;
}

.no-events {
  text-align: center;
  padding: 3rem;
  color: #6b7280;
}

/* Responsive */
@media (max-width: 768px) {
  .country-header {
    flex-direction: column;
  }

  .country-header-actions {
    width: 100%;
  }

  .btn-secondary,
  .btn-primary {
    flex: 1;
    justify-content: center;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
