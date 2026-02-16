<template>
  <AdminLayout>
    <div :class="['wrapper', theme]" :data-theme="theme">
      <!-- Header -->
      <div class="header flex justify-between items-center mb-6">
        <div>
          <h1 class="title text-2xl font-bold">All Countries</h1>
          <p class="subtitle text-gray-600">
            Manage the global list of active countries and ISO standards.
          </p>
        </div>
        <button class="btn-primary flex items-center" @click="goToCreate">
          <span class="icon mr-1">➕</span> Add New Country
        </button>
      </div>

      <!-- Search -->
      <div class="filters-section flex justify-between items-center mb-4">
        <div class="search-box relative">
          <span class="search-icon absolute left-2 top-1/2 -translate-y-1/2">🔍</span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by name or ISO code..."
            class="search-input pl-8"
            @input="handleSearch"
          />
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="loading-container text-center py-10">
        <div class="spinner mb-2"></div>
        <p>Loading countries...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="error-container text-center py-10">
        <p class="text-red-600 mb-2">{{ error }}</p>
        <button class="btn-retry" @click="fetchCountries(currentPage, searchQuery)">
          Try Again
        </button>
      </div>

      <!-- Table -->
      <div v-else class="table-container overflow-x-auto">
        <table class="table w-full border-collapse">
          <thead>
            <tr class="bg-gray-100">
              <th class="p-2 text-left">FLAG</th>
              <th class="p-2 text-left">COUNTRY NAME</th>
              <th class="p-2 text-left">STATUS</th>
              <th class="p-2 text-left">ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="country in countriesList" :key="country.id" class="border-b">
              <td class="p-2">
                <div class="w-10 h-6 flex items-center justify-center">
                  <img
                    v-if="country.image"
                    :src="getImageUrl(country.image)"
                    :alt="country.name"
                    class="w-full h-full object-cover rounded"
                  />

                  <span v-else>{{ getCountryEmoji(country.name) }}</span>
                </div>
              </td>
              <td class="p-2">{{ country.name }}</td>
              <td class="p-2">
                <span :class="['status-badge', getStatusClass(country)]">
                  {{ getStatusText(country) }}
                </span>
              </td>
              <td class="p-2 flex gap-2">
                <!-- Show / Eye -->
                <a
                  :href="`/admin/countries/${country.id}`"
                  class="text-blue-500 hover:text-blue-700"
                  title="Show"
                >
                  <i class="fas fa-eye"></i>
                </a>

                <!-- Edit / Pencil -->
                <button
                  @click="openEditModal(country)"
                  class="text-yellow-500 hover:text-yellow-700"
                  title="Edit"
                >
                  <i class="fas fa-edit"></i>
                </button>

                <!-- Delete / Trash -->
                <button
                  @click="deleteCountry(country.id)"
                  class="text-red-500 hover:text-red-700"
                  title="Delete"
                >
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="countriesList.length === 0">
              <td colspan="5" class="text-center py-6 text-gray-500">
                <p v-if="searchQuery">No results for "{{ searchQuery }}"</p>
                <p v-else>No countries found. Add your first country.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="!loading && totalPages > 1"
        class="pagination flex justify-between items-center mt-4"
      >
        <button
          class="btn-pagination"
          :disabled="currentPage === 1"
          @click="goToPage(currentPage - 1)"
        >
          ‹
        </button>

        <div class="flex gap-1">
          <button
            v-for="page in totalPages"
            :key="page"
            :class="[
              'btn-pagination',
              { 'bg-blue-500 text-white': page === currentPage },
            ]"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
        </div>

        <button
          class="btn-pagination"
          :disabled="currentPage === totalPages"
          @click="goToPage(currentPage + 1)"
        >
          ›
        </button>
      </div>

      <!-- Edit Modal -->
      <div
        v-if="showEditModal"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      >
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
          <h2 class="text-xl font-bold mb-4">Edit Country</h2>

          <div v-if="modalError" class="text-red-600 mb-4">{{ modalError }}</div>

          <form @submit.prevent="updateCountry">
            <div class="mb-4">
              <label class="block text-gray-700 mb-1">Country Name</label>
              <input
                v-model="form.name"
                type="text"
                class="w-full border rounded px-3 py-2"
                required
              />
            </div>

            <div class="mb-4">
              <label class="block text-gray-700 mb-1">Country Image</label>
              <!-- معاينة الصورة الحالية -->
              <div v-if="form.image" class="mb-2">
                <img
                  :src="getImageUrl(form.image)"
                  alt="Country Image"
                  class="w-24 h-16 object-cover rounded"
                />
              </div>

              <!-- إدخال رفع صورة جديدة -->
              <input
                type="file"
                @change="handleImageUpload"
                accept="image/*"
                class="w-full border rounded px-3 py-2"
              />
            </div>

            <div class="flex justify-end gap-3 mt-6">
              <button
                type="button"
                @click="closeEditModal"
                class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                :disabled="modalLoading"
              >
                {{ modalLoading ? "Saving..." : "Save Changes" }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import AdminLayout from "../../../layouts/AdminLayout.vue";

const router = useRouter();
const theme = localStorage.getItem("theme") || "light";

// State
const countriesList = ref([]);
const paginationData = ref({});
const loading = ref(false);
const error = ref("");
const searchQuery = ref("");
const currentPage = ref(1);

// Edit Modal State
const showEditModal = ref(false);
const modalLoading = ref(false);
const modalError = ref("");
const editingCountryId = ref(null);
const form = ref({
  name: "",
  code: "",
  image: "",
});

const totalPages = computed(() => paginationData.value.last_page || 1);

const getImageUrl = (path) => {
  if (!path) return null; // لو مفيش صورة
  return `${
    import.meta.env.VITE_APP_BASE_URL || "http://127.0.0.1:8000"
  }/storage/${path}`;
};

// Methods
const fetchCountries = async (page = 1, search = "") => {
  loading.value = true;
  error.value = "";

  try {
    let url = `/v1/countries/paginated/get?page=${page}`;
    if (search.trim()) {
      url += `&search=${encodeURIComponent(search.trim())}`;
    }

    const response = await axios.get(url);

    if (response.data.status === "success") {
      countriesList.value = response.data.data.data;
      paginationData.value = response.data.data;
      currentPage.value = response.data.data.current_page;
    } else {
      error.value = "Failed to load countries";
    }
  } catch (err) {
    console.error("Error fetching countries:", err);
    error.value = err.response?.data?.message || "Failed to load countries";
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  currentPage.value = 1;
  fetchCountries(currentPage.value, searchQuery.value);
};

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    fetchCountries(page, searchQuery.value);
  }
};

const goToCreate = () => {
  router.push("/admin/countries/create");
};

const openEditModal = async (country) => {
  editingCountryId.value = country.id;
  modalError.value = "";
  form.value = {
    name: country.name || "",
    code: country.code || "",
  };

  showEditModal.value = true;
  try {
    const res = await axios.get(`/v1/countries/${country.id}`);
    if (res.data.status === "success") {
      form.value = { ...res.data.data };
    }
  } catch (e) {
    console.error("Failed to fetch full country data", e);
  }
};

const closeEditModal = () => {
  showEditModal.value = false;
  editingCountryId.value = null;
  modalError.value = "";
  modalLoading.value = false;
  form.value = { name: "", code: "", image: "" };
};
const handleImageUpload = (event) => {
  const file = event.target.files[0];
  if (!file) return;
  form.value.image = file;
};
const updateCountry = async () => {
  if (!editingCountryId.value) return;

  modalLoading.value = true;
  modalError.value = "";

  try {
    const formData = new FormData();
    formData.append("name", form.value.name);
    formData.append("code", form.value.code || "");

    // لو المستخدم رفع صورة جديدة
    if (form.value.image instanceof File) {
      formData.append("image", form.value.image);
    }

    const response = await axios.post(
      `/v1/countries/${editingCountryId.value}/update`,
      formData,
      {
        headers: { "Content-Type": "multipart/form-data" },
      }
    );

    if (response.data.status === "success") {
      // تحديث السطر في الجدول
      const index = countriesList.value.findIndex((c) => c.id === editingCountryId.value);
      if (index !== -1) {
        countriesList.value[index] = {
          ...countriesList.value[index],
          ...response.data.data, // استبدل بالقيم الجديدة من السيرفر
        };
      }
      closeEditModal();
    } else {
      modalError.value = response.data.message || "Failed to update country";
    }
  } catch (err) {
    console.error("Update error:", err);
    modalError.value = err.response?.data?.message || "Failed to update country";
  } finally {
    modalLoading.value = false;
  }
};

const deleteCountry = async (id) => {
  if (!confirm("Are you sure you want to delete this country?")) return;

  try {
    await axios.delete(`/v1/countries/${id}/delete`);
    countriesList.value = countriesList.value.filter((c) => c.id !== id);

    if (countriesList.value.length === 0 && currentPage.value > 1) {
      fetchCountries(currentPage.value - 1, searchQuery.value);
    }
  } catch (err) {
    console.error("Error deleting country:", err);
    alert("Failed to delete country");
  }
};

const getCountryEmoji = (name) => {
  const map = {
    مصر: "🇪🇬",
    السعودية: "🇸🇦",
    السعوديه: "🇸🇦",
    "United States": "🇺🇸",
    Japan: "🇯🇵",
    Germany: "🇩🇪",
    Brazil: "🇧🇷",
    Australia: "🇦🇺",
  };
  return map[name] || "🏴";
};

const getStatusClass = () => (Math.random() > 0.2 ? "status-active" : "status-review");
const getStatusText = (country) =>
  getStatusClass() === "status-active" ? "Active" : "Review Needed";

// Lifecycle
onMounted(() => {
  fetchCountries(currentPage.value);
});
</script>

<style scoped>
/* ===== WRAPPER ===== */
.wrapper {
  min-height: 100vh;
  padding: 40px;
  transition: background 0.3s, color 0.3s;
}

[data-theme="light"] .wrapper {
  background: #f3f4f6;
  color: #000000;
}

[data-theme="dark"] .wrapper {
  background: #0f172a;
  color: #ffffff;
}

/* ===== HEADER ===== */
.header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 30px;
}

.title {
  font-size: 32px;
  font-weight: 700;
  margin: 0 0 8px 0;
}

.subtitle {
  font-size: 15px;
  opacity: 0.7;
  margin: 0;
}

.btn-primary {
  padding: 12px 24px;
  border-radius: 10px;
  border: none;
  background: #3b82f6;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}

.btn-primary:hover {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.icon {
  font-size: 16px;
}

/* ===== FILTERS ===== */
.filters-section {
  display: flex;
  gap: 16px;
  margin-bottom: 24px;
}

.search-box {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
  padding: 12px 16px;
  border-radius: 12px;
  transition: all 0.2s;
}

[data-theme="light"] .search-box {
  background: #ffffff;
  border: 1px solid #e5e7eb;
}

[data-theme="light"] .search-box:focus-within {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

[data-theme="dark"] .search-box {
  background: #1e293b;
  border: 1px solid #334155;
}

[data-theme="dark"] .search-box:focus-within {
  border-color: #60a5fa;
  box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.1);
}

.search-icon {
  font-size: 18px;
  margin-right: 12px;
  opacity: 0.5;
}

.search-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: 14px;
}

[data-theme="light"] .search-input {
  color: #000000;
}

[data-theme="dark"] .search-input {
  color: #ffffff;
}

.search-input::placeholder {
  opacity: 0.5;
}

.filter-buttons {
  display: flex;
  gap: 12px;
}

.filter-btn {
  padding: 12px 20px;
  border-radius: 10px;
  border: 1px solid;
  background: transparent;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}

[data-theme="light"] .filter-btn {
  color: #374151;
  border-color: #e5e7eb;
}

[data-theme="light"] .filter-btn:hover {
  background: #f3f4f6;
  border-color: #3b82f6;
}

[data-theme="dark"] .filter-btn {
  color: #f3f4f6;
  border-color: #334155;
}

[data-theme="dark"] .filter-btn:hover {
  background: #1e293b;
  border-color: #60a5fa;
}

.filter-icon {
  font-size: 14px;
}

/* ===== LOADING & ERROR ===== */
.loading-container,
.error-container {
  text-align: center;
  padding: 60px 20px;
  border-radius: 12px;
}

[data-theme="light"] .loading-container,
[data-theme="light"] .error-container {
  background: #ffffff;
}

[data-theme="dark"] .loading-container,
[data-theme="dark"] .error-container {
  background: #1e293b;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid;
  border-color: #3b82f6 transparent #3b82f6 transparent;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 16px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.error-message {
  color: #ef4444;
  margin-bottom: 16px;
}

.btn-retry {
  padding: 10px 20px;
  border-radius: 8px;
  border: none;
  background: #3b82f6;
  color: #ffffff;
  font-size: 14px;
  cursor: pointer;
}

/* ===== TABLE ===== */
.table-container {
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 24px;
}

[data-theme="light"] .table-container {
  background: #ffffff;
  border: 1px solid #e5e7eb;
}

[data-theme="dark"] .table-container {
  background: #1e293b;
  border: 1px solid #334155;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  border-bottom: 1px solid;
}

[data-theme="light"] thead {
  background: #f9fafb;
  border-bottom-color: #e5e7eb;
}

[data-theme="dark"] thead {
  background: #0f172a;
  border-bottom-color: #334155;
}

th {
  text-align: left;
  padding: 16px 20px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  opacity: 0.7;
}

td {
  padding: 20px;
  border-bottom: 1px solid;
}

[data-theme="light"] td {
  border-bottom-color: #f3f4f6;
}

[data-theme="dark"] td {
  border-bottom-color: #1e293b;
}

tbody tr:last-child td {
  border-bottom: none;
}

tbody tr {
  transition: background 0.2s;
}

[data-theme="light"] tbody tr:hover {
  background: #f9fafb;
}

[data-theme="dark"] tbody tr:hover {
  background: #0f172a;
}

/* Table Columns */
.col-flag {
  width: 80px;
}

.col-name {
  width: 30%;
}

.col-iso {
  width: 15%;
}

.col-status {
  width: 20%;
}

.col-actions {
  width: 15%;
}

.flag-container {
  width: 48px;
  height: 32px;
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

[data-theme="light"] .flag-container {
  background: #f3f4f6;
}

[data-theme="dark"] .flag-container {
  background: #0f172a;
}

.flag-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.flag-placeholder {
  font-size: 24px;
}

.country-name {
  font-size: 15px;
  font-weight: 500;
}

.iso-code {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  font-family: monospace;
}

[data-theme="light"] .iso-code {
  background: #eff6ff;
  color: #1e40af;
}

[data-theme="dark"] .iso-code {
  background: #1e3a5f;
  color: #93c5fd;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.status-active {
  background: #dcfce7;
  color: #166534;
}

.status-active .status-dot {
  background: #22c55e;
}

.status-review {
  background: #fef3c7;
  color: #92400e;
}

.status-review .status-dot {
  background: #f59e0b;
}

[data-theme="dark"] .status-active {
  background: #14532d;
  color: #bbf7d0;
}

[data-theme="dark"] .status-review {
  background: #78350f;
  color: #fde68a;
}

.action-buttons {
  display: flex;
  gap: 8px;
}

.action-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

[data-theme="light"] .action-btn {
  background: #f3f4f6;
}

[data-theme="light"] .action-btn:hover {
  background: #e5e7eb;
}

[data-theme="dark"] .action-btn {
  background: #0f172a;
}

[data-theme="dark"] .action-btn:hover {
  background: #334155;
}

/* ===== EMPTY STATE ===== */
.empty-state {
  padding: 60px 20px !important;
}

.empty-content {
  text-align: center;
}

.empty-icon {
  font-size: 48px;
  display: block;
  margin-bottom: 16px;
}

.empty-title {
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  font-size: 14px;
  opacity: 0.6;
  margin: 0;
}

/* ===== PAGINATION ===== */
.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 40px;
}

.pagination-info {
  font-size: 14px;
  opacity: 0.7;
}

.pagination-controls {
  display: flex;
  gap: 8px;
  align-items: center;
}

.pagination-btn {
  min-width: 36px;
  height: 36px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid;
  background: transparent;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

[data-theme="light"] .pagination-btn {
  color: #374151;
  border-color: #e5e7eb;
}

[data-theme="light"] .pagination-btn:hover:not(:disabled) {
  background: #f3f4f6;
  border-color: #3b82f6;
}

[data-theme="light"] .pagination-btn.active {
  background: #3b82f6;
  color: #ffffff;
  border-color: #3b82f6;
}

[data-theme="dark"] .pagination-btn {
  color: #f3f4f6;
  border-color: #334155;
}

[data-theme="dark"] .pagination-btn:hover:not(:disabled) {
  background: #1e293b;
  border-color: #60a5fa;
}

[data-theme="dark"] .pagination-btn.active {
  background: #3b82f6;
  color: #ffffff;
  border-color: #3b82f6;
}

.pagination-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.pagination-ellipsis {
  padding: 0 8px;
  opacity: 0.5;
}

/* ===== FOOTER ===== */
.footer {
  text-align: center;
  padding-top: 40px;
  border-top: 1px solid;
}

[data-theme="light"] .footer {
  border-top-color: #e5e7eb;
}

[data-theme="dark"] .footer {
  border-top-color: #334155;
}

.footer-text {
  font-size: 13px;
  opacity: 0.6;
  margin: 0 0 12px 0;
}

.footer-links {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 12px;
  font-size: 13px;
}

.footer-link {
  text-decoration: none;
  transition: color 0.2s;
}

[data-theme="light"] .footer-link {
  color: #3b82f6;
}

[data-theme="light"] .footer-link:hover {
  color: #2563eb;
}

[data-theme="dark"] .footer-link {
  color: #60a5fa;
}

[data-theme="dark"] .footer-link:hover {
  color: #93c5fd;
}

.footer-separator {
  opacity: 0.3;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  .wrapper {
    padding: 20px;
  }

  .header {
    flex-direction: column;
    gap: 16px;
  }

  .btn-primary {
    width: 100%;
    justify-content: center;
  }

  .filters-section {
    flex-direction: column;
  }

  .table-container {
    overflow-x: auto;
  }

  .table {
    min-width: 600px;
  }

  .pagination {
    flex-direction: column;
    gap: 16px;
  }

  .pagination-controls {
    width: 100%;
    justify-content: center;
  }
}
</style>
