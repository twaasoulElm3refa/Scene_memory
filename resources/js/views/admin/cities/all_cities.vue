<template>
  <AdminLayout>
    <!-- Header Section -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900 mb-2">Cities Management</h1>
      <p class="text-gray-600">
        Manage and organize geographical data for events globally.
      </p>
    </div>

    <!-- Search and Filter Section -->
    <div class="flex gap-4 mb-6 justify-end">
      <router-link
        to="/admin/cities/create"
        class="inline-flex text-decoration-none items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 4v16m8-8H4"
          />
        </svg>
        Create City
      </router-link>
    </div>

    <!-- Cities Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th
              class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              City Preview
            </th>
            <th
              class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              City Name
            </th>
            <th
              class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              Country Name
            </th>
            <th
              class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              Total Events
            </th>
            <th
              class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-if="loading">
            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
              Loading cities...
            </td>
          </tr>
          <tr v-else-if="cities.length === 0">
            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
              No cities found
            </td>
          </tr>
          <tr v-else v-for="city in cities" :key="city.id" class="hover:bg-gray-50">
            <td class="px-6 py-4">
              <img
                :src="getCityImage(city.name)"
                :alt="city.name"
                class="w-12 h-12 rounded-lg object-cover"
              />
            </td>
            <td class="px-6 py-4">
              <span class="text-sm font-medium text-gray-900">{{ city.name }}</span>
            </td>
            <td class="px-6 py-4">
              <span class="text-sm text-gray-600">{{ city.countries?.name }}</span>
            </td>
            <td class="px-6 py-4">
              <span class="text-sm font-medium text-blue-600">
                {{ city.events_count }} Events
              </span>
            </td>
            <td class="px-6 py-4">
              <div class="flex gap-2">
                <button
                  @click="editCity(city)"
                  class="p-2 text-gray-400 hover:text-blue-600 transition-colors"
                  title="Edit"
                >
                  <svg
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                    />
                  </svg>
                </button>
                <button
                  @click="deleteCity(city)"
                  class="p-2 text-gray-400 hover:text-red-600 transition-colors"
                  title="Delete"
                >
                  <svg
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                    />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div
      class="flex items-center justify-between bg-white px-6 py-4 rounded-lg shadow-sm"
    >
      <div class="flex items-center gap-2">
        <span class="text-sm text-gray-700">Show</span>
        <select
          v-model="perPage"
          @change="handlePerPageChange"
          class="px-3 py-1 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option :value="5">5</option>
          <option :value="10">10</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
        </select>
        <span class="text-sm text-gray-700">per page</span>
      </div>

      <div class="flex items-center gap-1">
        <button
          @click="goToPage(1)"
          :disabled="currentPage === 1"
          class="px-3 py-1 text-gray-600 hover:bg-gray-100 rounded disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M11 19l-7-7 7-7m8 14l-7-7 7-7"
            />
          </svg>
        </button>
        <button
          @click="goToPage(currentPage - 1)"
          :disabled="currentPage === 1"
          class="px-3 py-1 text-gray-600 hover:bg-gray-100 rounded disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15 19l-7-7 7-7"
            />
          </svg>
        </button>

        <button
          v-for="page in visiblePages"
          :key="page"
          @click="goToPage(page)"
          :class="[
            'px-3 py-1 rounded',
            currentPage === page
              ? 'bg-blue-500 text-white'
              : 'text-gray-600 hover:bg-gray-100',
          ]"
        >
          {{ page }}
        </button>

        <button
          @click="goToPage(currentPage + 1)"
          :disabled="currentPage === lastPage"
          class="px-3 py-1 text-gray-600 hover:bg-gray-100 rounded disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 5l7 7-7 7"
            />
          </svg>
        </button>
        <button
          @click="goToPage(lastPage)"
          :disabled="currentPage === lastPage"
          class="px-3 py-1 text-gray-600 hover:bg-gray-100 rounded disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M13 5l7 7-7 7M5 5l7 7-7 7"
            />
          </svg>
        </button>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="text-sm text-gray-500 uppercase tracking-wide mb-2">
          Total Active Cities
        </div>
        <div class="flex items-end justify-between">
          <div class="text-4xl font-bold text-gray-900">{{ statistics.totalCities }}</div>
          <div class="text-sm text-green-600 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
              />
            </svg>
            +5 this month
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="text-sm text-gray-500 uppercase tracking-wide mb-2">
          Countries Represented
        </div>
        <div class="flex items-end justify-between">
          <div class="text-4xl font-bold text-gray-900">
            {{ statistics.totalCountries }}
          </div>
          <div class="text-sm text-gray-500">Global Coverage</div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="text-sm text-gray-500 uppercase tracking-wide mb-2">
          Avg. Events Per City
        </div>
        <div class="flex items-end justify-between">
          <div class="text-4xl font-bold text-gray-900">{{ statistics.avgEvents }}</div>
          <div class="text-sm text-blue-600">Activity Index</div>
        </div>
      </div>
    </div>

    <!-- Edit City Modal -->
    <div
      v-if="showEditModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="closeEditModal"
    >
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-2xl font-bold text-gray-900">Edit City</h2>
          <button
            @click="closeEditModal"
            class="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>

        <form @submit.prevent="updateCity">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              City Name
            </label>
            <input
              v-model="editForm.name"
              type="text"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter city name"
            />
          </div>

          <div class="flex gap-3">
            <button
              type="button"
              @click="closeEditModal"
              class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="updating"
              class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ updating ? "Updating..." : "Update City" }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="closeDeleteModal"
    >
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <div
          class="flex items-center justify-center w-12 h-12 rounded-full bg-red-100 mx-auto mb-4"
        >
          <svg
            class="w-6 h-6 text-red-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
          </svg>
        </div>

        <h2 class="text-xl font-bold text-gray-900 text-center mb-2">Delete City</h2>
        <p class="text-gray-600 text-center mb-6">
          Are you sure you want to delete
          <span class="font-semibold">{{ cityToDelete?.name }}</span
          >? This action cannot be undone.
        </p>

        <div class="flex gap-3">
          <button
            type="button"
            @click="closeDeleteModal"
            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="confirmDelete"
            :disabled="deleting"
            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ deleting ? "Deleting..." : "Delete" }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { cityService } from "@/services/admin/cities/cityService";

interface Country {
  id: number;
  name: string;
}

interface City {
  id: number;
  name: string;
  country_id: number;
  events_count: number;
  countries?: Country;
}

interface PaginationData {
  current_page: number;
  data: City[];
  first_page_url: string;
  from: number;
  last_page: number;
  last_page_url: string;
  links: any[];
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number;
  total: number;
}

const cities = ref<City[]>([]);
const countries = ref<Country[]>([]);
const loading = ref(false);
const searchQuery = ref("");
const selectedCountry = ref("");
const currentPage = ref(1);
const lastPage = ref(1);
const perPage = ref(10);
const total = ref(0);

const statistics = ref({
  totalCities: 0,
  totalCountries: 0,
  avgEvents: 0,
});

// Edit Modal
const showEditModal = ref(false);
const updating = ref(false);
const editForm = ref({
  id: 0,
  name: "",
  country_id: "",
});

// Delete Modal
const showDeleteModal = ref(false);
const deleting = ref(false);
const cityToDelete = ref<City | null>(null);

const visiblePages = computed(() => {
  const pages: number[] = [];
  const maxVisible = 5;

  let start = Math.max(1, currentPage.value - 2);
  let end = Math.min(lastPage.value, start + maxVisible - 1);

  if (end - start < maxVisible - 1) {
    start = Math.max(1, end - maxVisible + 1);
  }

  for (let i = start; i <= end; i++) {
    pages.push(i);
  }

  return pages;
});

const fetchCities = async (page = 1) => {
  loading.value = true;

  try {
    const params: Record<string, any> = {
      page,
      per_page: perPage.value,
    };

    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }
    if (selectedCountry.value) {
      params.country_id = selectedCountry.value;
    }

    const response = await cityService.getPaginatedCities(params);

    if (response.data?.status === "success") {
      const pagData: PaginationData = response.data.data.cities;

      cities.value = pagData.data;
      currentPage.value = pagData.current_page;
      lastPage.value = pagData.last_page;
      total.value = pagData.total;

      // إحصائيات (إما جاية من الـ backend أو نحسبها محليًا)
      const countCities = response.data.data.count_cities ?? cities.value.length;
      const countCountries = response.data.data.count_countries ?? 0;

      const avgEvents =
        cities.value.length > 0
          ? Number(
              (
                cities.value.reduce((sum, city) => sum + (city.events_count || 0), 0) /
                cities.value.length
              ).toFixed(1)
            )
          : 0;

      statistics.value = {
        totalCities: countCities,
        totalCountries: countCountries,
        avgEvents,
      };
    }
  } catch (error: any) {
    console.error("Failed to load cities:", error);
  } finally {
    loading.value = false;
  }
};

const fetchCountries = async () => {
  try {
    const response = await cityService.getAllCountries();

    if (response.data?.status === "success") {
      countries.value = response.data.data.countries || [];
    }
  } catch (error) {
    console.error("Failed to load countries:", error);
  }
};

const fetchStatistics = async () => {
  try {
    const response = await cityService.getCitiesStatistics();
    if (response.data?.status === "success") {
      statistics.value = response.data.data;
    }
  } catch (error) {
    console.error("Failed to load statistics:", error);
  }
};

const goToPage = (page: number) => {
  if (page < 1 || page > lastPage.value) return;
  currentPage.value = page;
  fetchCities(page);
};

const handlePerPageChange = () => {
  currentPage.value = 1;
  fetchCities(1);
};

const handleCountryFilter = () => {
  currentPage.value = 1;
  fetchCities(1);
};

const editCity = (city: City) => {
  editForm.value = {
    id: city.id,
    name: city.name,
    country_id: String(city.country_id || ""),
  };
  showEditModal.value = true;
};

const closeEditModal = () => {
  showEditModal.value = false;
  editForm.value = { id: 0, name: "", country_id: "" };
};

const updateCity = async () => {
  if (!editForm.value.name.trim()) return;

  updating.value = true;

  try {
    const response = await cityService.updateCity(editForm.value.id, {
      name: editForm.value.name.trim(),
      country_id: editForm.value.country_id,
    });

    if (response.data?.status === "success") {
      closeEditModal();
      fetchCities(currentPage.value);
      alert("تم تحديث المدينة بنجاح");
    }
  } catch (error: any) {
    console.error("Update city failed:", error);
    alert(error.response?.data?.message || "حدث خطأ أثناء التحديث");
  } finally {
    updating.value = false;
  }
};

const deleteCity = (city: City) => {
  cityToDelete.value = city;
  showDeleteModal.value = true;
};

const closeDeleteModal = () => {
  showDeleteModal.value = false;
  cityToDelete.value = null;
};

const confirmDelete = async () => {
  if (!cityToDelete.value?.id) return;

  deleting.value = true;

  try {
    const response = await cityService.deleteCity(cityToDelete.value.id);

    if (response.data?.status === "success") {
      closeDeleteModal();
      fetchCities(currentPage.value);
      alert("تم حذف المدينة بنجاح");
    }
  } catch (error: any) {
    console.error("Delete city failed:", error);
    alert(error.response?.data?.message || "حدث خطأ أثناء الحذف");
  } finally {
    deleting.value = false;
  }
};

onMounted(() => {
  fetchCities(1);
  fetchCountries();
});

const getCityImage = (cityName: string): string => {
  const placeholders: Record<string, string> = {
    Paris:
      "https://images.unsplash.com/photo-1502602898657-3e91760cbb34?w=100&h=100&fit=crop",
    Tokyo:
      "https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?w=100&h=100&fit=crop",
    "New York":
      "https://images.unsplash.com/photo-1496442226666-8d4d0e62e6e9?w=100&h=100&fit=crop",
    London:
      "https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?w=100&h=100&fit=crop",
    Rome:
      "https://images.unsplash.com/photo-1552832230-c0197dd311b5?w=100&h=100&fit=crop",
    Cairo:
      "https://images.unsplash.com/photo-1572252009286-268acec5ca0a?w=100&h=100&fit=crop",
    Dubai:
      "https://images.unsplash.com/photo-1546412412-4c5c3d8d2c8e?w=100&h=100&fit=crop",
  };

  return (
    placeholders[cityName] ||
    `https://ui-avatars.com/api/?name=${encodeURIComponent(
      cityName
    )}&size=100&background=random`
  );
};
</script>
