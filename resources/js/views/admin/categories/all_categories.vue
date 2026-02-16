<template>
  <AdminLayout>
    <div class="p-8 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-3xl font-bold text-gray-900">Event Categories</h2>
          <p class="mt-1.5 text-sm text-gray-500">
            Manage and organize the event types across the platform
          </p>
        </div>
        <button
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-sm"
          @click="openCreateModal"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 4v16m8-8H4"
            />
          </svg>
          Add Category
        </button>
      </div>

      <!-- Controls -->
      <div class="flex items-center justify-between gap-4">
        <div class="relative flex-1 max-w-md">
          <svg
            class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
            />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search categories..."
            class="w-full pl-11 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
            @keyup.enter="search"
            :disabled="loading"
          />
        </div>
        <div class="flex gap-2">
          <button
            class="inline-flex items-center gap-2 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors disabled:opacity-50"
            :disabled="loading"
          >
            Filter
          </button>
          <button
            class="inline-flex items-center gap-2 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors disabled:opacity-50"
            :disabled="loading"
          >
            Export
          </button>
        </div>
      </div>

      <!-- Table Card -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="relative overflow-x-auto min-h-[300px]">
          <!-- Loading overlay -->
          <div
            v-if="loading"
            class="absolute inset-0 bg-white/60 flex items-center justify-center z-10 backdrop-blur-[2px]"
          >
            <div class="flex flex-col items-center gap-3">
              <svg
                class="animate-spin h-10 w-10 text-blue-600"
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
              <span class="text-sm font-medium text-gray-700">Loading categories...</span>
            </div>
          </div>

          <table
            class="min-w-full divide-y divide-gray-200"
            :class="{ 'opacity-60 pointer-events-none': loading }"
          >
            <thead class="bg-gray-50">
              <tr>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"
                >
                  Category
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"
                >
                  Events Linked
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"
                >
                  Created Date
                </th>
                <th
                  class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider"
                >
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              <tr
                v-for="category in categories"
                :key="category.id"
                class="hover:bg-gray-50 transition-colors"
              >
                <td class="px-6 py-4">
                  <div class="flex items-center gap-4">
                    <div
                      class="w-14 h-14 rounded-lg overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200 border border-gray-200 flex items-center justify-center flex-shrink-0"
                    >
                      <img
                        v-if="category.image"
                        :src="category.image"
                        :alt="category.name"
                        class="w-full h-full object-cover"
                      />
                      <svg
                        v-else
                        class="w-7 h-7 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                      </svg>
                    </div>
                    <div class="text-base font-semibold text-gray-900">
                      {{ category.name }}
                    </div>
                  </div>
                </td>

                <td class="px-6 py-4">
                  <div
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-700 text-sm font-semibold rounded-full"
                  >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path
                        d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                      />
                    </svg>
                    {{ category.events_count }} Events
                  </div>
                </td>

                <td class="px-6 py-4">
                  <div class="text-sm font-medium text-gray-700">
                    {{ formatDate(category.created_at) }}
                  </div>
                </td>

                <td class="px-6 py-4 text-center">
                  <div class="inline-flex items-center gap-2">
                    <button
                      @click="editCategory(category)"
                      class="p-2.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                      title="Edit"
                      :disabled="loading"
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
                      @click="deleteCategory(category)"
                      class="p-2.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                      title="Delete"
                      :disabled="loading"
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

              <tr v-if="!loading && !categories.length">
                <td colspan="4" class="px-6 py-16 text-center">
                  <svg
                    class="mx-auto w-14 h-14 text-gray-300 mb-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                    />
                  </svg>
                  <p class="text-base font-medium text-gray-900 mb-2">
                    No categories found
                  </p>
                  <p class="text-sm text-gray-500">
                    Try adjusting your search or filters
                  </p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div
          v-if="total > 0"
          class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50"
        >
          <div class="text-sm text-gray-600">
            Showing <span class="font-medium text-gray-900">{{ from }}</span> to
            <span class="font-medium text-gray-900">{{ to }}</span> of
            <span class="font-medium text-gray-900">{{ total }}</span> categories
          </div>

          <div class="flex items-center gap-1">
            <button
              :disabled="loading || currentPage === 1"
              @click="changePage(currentPage - 1)"
              class="p-2 border border-gray-300 rounded-lg hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              <svg
                class="w-5 h-5 text-gray-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
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
              :disabled="loading || page === currentPage"
              @click="changePage(page)"
              :class="{
                'min-w-[2.6rem] px-3 py-2 text-sm font-medium rounded-lg transition-colors': true,
                'bg-blue-600 text-white shadow-sm': currentPage === page,
                'text-gray-700 border border-gray-300 hover:bg-gray-50':
                  currentPage !== page,
                'opacity-50 cursor-not-allowed': loading && page !== currentPage,
              }"
            >
              {{ page }}
            </button>

            <button
              :disabled="loading || currentPage === lastPage"
              @click="changePage(currentPage + 1)"
              class="p-2 border border-gray-300 rounded-lg hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              <svg
                class="w-5 h-5 text-gray-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5l7 7-7 7"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import axios from "axios";

const categories = ref([]);
const currentPage = ref(1);
const perPage = ref(10);
const total = ref(0);
const lastPage = ref(1);
const from = ref(1);
const to = ref(0);
const searchQuery = ref("");
const loading = ref(false);

async function fetchCategories(page = 1) {
  loading.value = true;
  try {
    const params = { page };

    // Uncomment when backend supports search
    // if (searchQuery.value.trim()) {
    //   params.search = searchQuery.value.trim();
    // }

    const res = await axios.get("/v1/categories/all/paginated", { params });

    if (res.data.status === "success") {
      const pag = res.data.data;
      categories.value = pag.data.map((item) => ({
        id: item.id,
        name: item.name,
        image: item.image,
        events_count: item.events_count || 0,
        created_at: item.created_at || new Date().toISOString(),
      }));

      currentPage.value = pag.current_page;
      perPage.value = pag.per_page;
      total.value = pag.total;
      lastPage.value = pag.last_page;
      from.value = pag.from || 1;
      to.value = pag.to || pag.data.length;
    }
  } catch (err) {
    console.error("Failed to load categories", err);
  } finally {
    loading.value = false;
  }
}

function changePage(page) {
  if (page < 1 || page > lastPage.value || loading.value) return;
  currentPage.value = page;
  fetchCategories(page);
}

function search() {
  if (loading.value) return;
  currentPage.value = 1;
  fetchCategories(1);
}

const visiblePages = computed(() => {
  const pages = [];
  const maxVisible = 5;
  let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2));
  let end = Math.min(lastPage.value, start + maxVisible - 1);

  if (end - start + 1 < maxVisible) {
    start = Math.max(1, end - maxVisible + 1);
  }

  for (let i = start; i <= end; i++) {
    pages.push(i);
  }
  return pages;
});

function formatDate(dateStr) {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function openCreateModal() {
  console.log("Open create modal");
}
function editCategory(cat) {
  console.log("Edit:", cat);
}
function deleteCategory(cat) {
  console.log("Delete:", cat);
}

onMounted(() => {
  fetchCategories(1);
});
</script>
