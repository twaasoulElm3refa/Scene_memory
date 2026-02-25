<template>
  <AdminLayout>
    <div :class="['wrapper', theme]" :data-theme="theme">
      <!-- Header -->
      <div class="header flex justify-between items-center mb-6">
        <div>
          <h1 class="title text-2xl font-bold">All Countries</h1>
        </div>
        <button
          class="btn-primary bg-blue-600 p-2 rounded text-white flex items-center"
          @click="goToCreate"
        >
          <span class="icon mr-1">➕</span> Add New Country
        </button>
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
              <th class="p-2 text-left">NAME</th>
              <th class="p-2 text-left">CODE</th>
              <th class="p-2 text-left">STATUS</th>
              <th class="p-2 text-left">ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="country in countriesList"
              :key="country.country_id"
              class="border-b"
            >
              <td class="p-2">
                <div class="w-10 h-6 flex items-center justify-center">
                  <img
                    v-if="country.image"
                    :src="getImageUrl(country.image)"
                    :alt="country.code"
                    class="w-full h-full object-cover rounded"
                  />
                  <span v-else>{{ getCountryEmoji(country.code) }}</span>
                </div>
              </td>
              <td class="p-2 font-medium">{{ country.name }}</td>
              <td class="p-2 font-medium">{{ country.code }}</td>
              <td class="p-2">
                <span :class="['status-badge', getStatusClass(country)]">
                  {{ getStatusText(country) }}
                </span>
              </td>
              <td class="p-2 flex gap-3">
                <a
                  :href="`/admin/countries/${country.id}`"
                  class="text-blue-500 hover:text-blue-700"
                  title="View Details"
                >
                  <i class="fas fa-eye"></i>
                </a>

                <button
                  @click="openEditModal(country)"
                  class="text-yellow-500 hover:text-yellow-700"
                  title="Edit"
                >
                  <i class="fas fa-edit"></i>
                </button>

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
              <td colspan="4" class="text-center py-8 text-gray-500">
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
        class="pagination mt-6 flex flex-col sm:flex-row justify-between items-center gap-4 border-t pt-5"
      >
        <!-- Previous -->
        <button
          class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
          :class="{
            'bg-gray-100 text-gray-400 cursor-not-allowed': currentPage === 1,
            'hover:bg-gray-50 text-gray-700 shadow-sm border-gray-300': currentPage > 1,
          }"
          :disabled="currentPage === 1"
          @click="goToPage(currentPage - 1)"
        >
          ← Previous
        </button>

        <!-- Pages -->
        <div class="flex flex-wrap justify-center gap-2">
          <!-- First page + ellipsis if needed -->
          <button
            v-if="totalPages > 1"
            class="min-w-[2.5rem] h-10 rounded-lg text-sm font-medium border transition-colors"
            :class="{
              'bg-blue-600 text-white border-blue-600': currentPage === 1,
              'hover:bg-gray-50 border-gray-300': currentPage !== 1,
            }"
            @click="goToPage(1)"
          >
            1
          </button>

          <!-- Left ellipsis -->
          <span
            v-if="currentPage > 4"
            class="min-w-[2.5rem] h-10 flex items-center justify-center text-sm text-gray-500"
          >
            ...
          </span>

          <!-- Main page range (limited to ~6 buttons) -->
          <template v-for="page in displayedPages" :key="page">
            <button
              class="min-w-[2.5rem] h-10 rounded-lg text-sm font-medium border transition-colors"
              :class="{
                'bg-blue-600 text-white border-blue-600': page === currentPage,
                'hover:bg-gray-50 border-gray-300': page !== currentPage,
              }"
              @click="goToPage(page)"
            >
              {{ page }}
            </button>
          </template>

          <!-- Right ellipsis -->
          <span
            v-if="currentPage < totalPages - 3"
            class="min-w-[2.5rem] h-10 flex items-center justify-center text-sm text-gray-500"
          >
            ...
          </span>

          <!-- Last page -->
          <button
            v-if="totalPages > 1 && totalPages !== currentPage"
            class="min-w-[2.5rem] h-10 rounded-lg text-sm font-medium border transition-colors"
            :class="{
              'bg-blue-600 text-white border-blue-600': currentPage === totalPages,
              'hover:bg-gray-50 border-gray-300': currentPage !== totalPages,
            }"
            @click="goToPage(totalPages)"
          >
            {{ totalPages }}
          </button>
        </div>

        <!-- Next -->
        <button
          class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
          :class="{
            'bg-gray-100 text-gray-400 cursor-not-allowed': currentPage === totalPages,
            'hover:bg-gray-50 text-gray-700 shadow-sm border-gray-300':
              currentPage < totalPages,
          }"
          :disabled="currentPage === totalPages"
          @click="goToPage(currentPage + 1)"
        >
          Next →
        </button>
      </div>

      <!-- Edit Modal -->
      <div
        v-if="showEditModal"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      >
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
          <h2 class="text-xl font-bold mb-5">Edit Country</h2>

          <div v-if="modalError" class="text-red-600 mb-4 p-3 bg-red-50 rounded">
            {{ modalError }}
          </div>

          <form @submit.prevent="updateCountry">
            <div class="mb-5">
              <label class="block text-gray-700 font-medium mb-1">Country Code</label>
              <input
                v-model="form.code"
                type="text"
                class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
                maxlength="3"
              />
            </div>

            <div class="mb-5">
              <label class="block text-gray-700 font-medium mb-1">Country Flag</label>
              <div v-if="form.imagePreview" class="mb-3">
                <img
                  :src="form.imagePreview"
                  alt="Current flag"
                  class="w-24 h-16 object-cover rounded border"
                />
              </div>
              <div v-else-if="form.image" class="mb-3">
                <img
                  :src="getImageUrl(form.image)"
                  alt="Current flag"
                  class="w-24 h-16 object-cover rounded border"
                />
              </div>

              <input
                type="file"
                @change="handleImageUpload"
                accept="image/*"
                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
              />
            </div>

            <div class="flex justify-end gap-3 mt-6">
              <button
                type="button"
                @click="closeEditModal"
                class="px-5 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 transition"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="px-5 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition disabled:opacity-50"
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
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { countryService } from "@/services/admin/countries/countryService";

const router = useRouter();
const theme = localStorage.getItem("theme") || "light";

const countriesList = ref([]);
const paginationData = ref({});
const loading = ref(false);
const error = ref("");
const searchQuery = ref("");
const currentPage = ref(1);

const showEditModal = ref(false);
const modalLoading = ref(false);
const modalError = ref("");
const editingCountryId = ref(null);

const form = ref({
  code: "",
  image: null,
  imagePreview: null,
});

const totalPages = computed(() => paginationData.value.last_page || 1);

const getImageUrl = (path) => {
  if (!path) return null;
  const base = import.meta.env.VITE_APP_BASE_URL || "http://127.0.0.1:8000";
  return `${base}/storage/${path}`;
};

const getCountryEmoji = (code) => {
  const map = { EG: "🇪🇬", SA: "🇸🇦", US: "🇺🇸", GB: "🇬🇧", FR: "🇫🇷" };
  return map[code?.toUpperCase()] || "🏳️";
};

const getStatusClass = () =>
  Math.random() > 0.2 ? "bg-green-100 text-green-800" : "bg-yellow-100 text-yellow-800";
const getStatusText = () => (Math.random() > 0.2 ? "Active" : "Under Review");

const fetchCountries = async (page = 1, search = "") => {
  loading.value = true;
  error.value = "";

  try {
    const response = await countryService.getPaginatedCountries(page, search);
    if (response.data?.status === "success") {
      countriesList.value = response.data.data.data || [];
      paginationData.value = response.data.data;
      currentPage.value = response.data.data.current_page;
    } else {
      error.value = "Failed to load countries";
    }
  } catch (err) {
    error.value = err.response?.data?.message || "Error loading countries";
  } finally {
    loading.value = false;
  }
};

const openEditModal = (country) => {
  editingCountryId.value = country.country_id;
  form.value = {
    code: country.code || "",
    image: country.image || null,
    imagePreview: null,
  };
  showEditModal.value = true;
};

const handleImageUpload = (event) => {
  const file = event.target.files?.[0];
  if (!file) return;

  form.value.image = file;
  const reader = new FileReader();
  reader.onload = (e) => {
    form.value.imagePreview = e.target.result;
  };
  reader.readAsDataURL(file);
};

const updateCountry = async () => {
  if (!editingCountryId.value) return;

  modalLoading.value = true;
  modalError.value = "";

  try {
    const payload = {
      code: form.value.code.trim().toUpperCase(),
    };

    const response = await countryService.updateCountry(
      editingCountryId.value,
      payload,
      form.value.image instanceof File ? form.value.image : null
    );

    if (response.data?.status === "success") {
      const updated = response.data.data;
      const index = countriesList.value.findIndex(
        (c) => c.country_id === editingCountryId.value
      );
      if (index !== -1) {
        countriesList.value[index] = { ...countriesList.value[index], ...updated };
      }
      closeEditModal();
    } else {
      modalError.value = response.data?.message || "Update failed";
    }
  } catch (err) {
    modalError.value = err.response?.data?.message || "Error updating country";
  } finally {
    modalLoading.value = false;
  }
};

const displayedPages = computed(() => {
  const pages = [];
  const maxVisible = 5;
  const side = Math.floor(maxVisible / 2);

  let start = Math.max(2, currentPage.value - side);
  let end = Math.min(totalPages.value - 1, currentPage.value + side);
  if (currentPage.value <= side + 2) {
    start = 2;
    end = Math.min(totalPages.value - 1, maxVisible + 1);
  }
  if (currentPage.value >= totalPages.value - side - 1) {
    end = totalPages.value - 1;
    start = Math.max(2, totalPages.value - maxVisible);
  }

  for (let i = start; i <= end; i++) {
    pages.push(i);
  }

  return pages;
});

const deleteCountry = async (country_id) => {
  if (!confirm("Are you sure you want to delete this country?")) return;

  try {
    await countryService.deleteCountry(country_id);

    countriesList.value = countriesList.value.filter((c) => c.country_id !== country_id);

    if (countriesList.value.length === 0 && currentPage.value > 1) {
      goToPage(currentPage.value - 1);
    }
    window.location.reload();
  } catch (err) {
    alert(err.response?.data?.message || "Failed to delete country");
  }
};

const goToPage = (page) => {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
  fetchCountries(page, searchQuery.value);
};

const goToCreate = () => {
  router.push("/admin/countries/create");
};

const closeEditModal = () => {
  showEditModal.value = false;
  editingCountryId.value = null;
  modalError.value = "";
  modalLoading.value = false;
  form.value = { code: "", image: null, imagePreview: null };
};

onMounted(() => {
  fetchCountries(1);
});
</script>
