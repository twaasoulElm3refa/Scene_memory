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
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-sm"
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
                  Sub Categories Linked
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
                    {{ category.events_count }} Sub Categories
                  </div>
                </td>

                <td class="px-6 py-4">
                  <div class="text-sm font-medium text-gray-700">
                    {{ formatDate(category.created_at) }}
                  </div>
                </td>

                <td class="px-6 py-4 text-center">
                  <div class="inline-flex items-center gap-2">
                    <!-- زر View / Show Category -->
                    <button
                      @click="viewCategory(category.id)"
                      class="p-2.5 text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                      title="View Category Details"
                      :disabled="loading || form.processing"
                    >
                      <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                        />
                      </svg>
                    </button>

                    <!-- زر Edit (الموجود) -->
                    <button
                      @click="openEditModal(category)"
                      class="p-2.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                      title="Edit"
                      :disabled="loading || form.processing"
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

                    <!-- زر Delete (الموجود) -->
                    <button
                      @click="confirmDelete(category)"
                      class="p-2.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                      title="Delete"
                      :disabled="loading || form.processing"
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

      <!-- Category Modal (Create & Edit) -->
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden">
          <div class="px-6 py-5 border-b border-gray-200">
            <h3 class="text-xl font-bold text-gray-900">
              {{ isEditMode ? "Edit Category" : "Add New Category" }}
            </h3>
          </div>

          <form @submit.prevent="submitForm" class="p-6 space-y-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5"
                >Category Name</label
              >
              <input
                v-model="form.name"
                type="text"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                placeholder="e.g. Conferences, Workshops..."
                :class="{ 'border-red-500': form.errors.name }"
                required
              />
              <p v-if="form.errors.name" class="mt-1.5 text-sm text-red-600">
                {{ form.errors.name }}
              </p>
            </div>

            <!-- <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5"
                >Image (optional)</label
              >
              <input
                type="url"
                v-model="form.image"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                placeholder="https://example.com/image.jpg"
              />
            </div> -->

            <div
              class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200"
            >
              <button
                type="button"
                @click="closeModal"
                class="px-5 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                :disabled="form.processing"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center gap-2"
                :disabled="form.processing"
              >
                <span v-if="form.processing">Saving...</span>
                <span v-else>{{ isEditMode ? "Update" : "Create" }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div
        v-if="showDeleteConfirm"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      >
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
          <h3 class="text-xl font-bold text-gray-900 mb-3">Delete Category?</h3>
          <p class="text-gray-600 mb-6">
            Are you sure you want to delete
            <span class="font-semibold text-gray-900">{{ categoryToDelete?.name }}</span
            >? This action cannot be undone.
          </p>

          <div class="flex items-center justify-end gap-3">
            <button
              @click="showDeleteConfirm = false"
              class="px-5 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
              :disabled="form.processing"
            >
              Cancel
            </button>
            <button
              @click="performDelete"
              class="px-5 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 flex items-center gap-2"
              :disabled="form.processing"
            >
              <span v-if="form.processing">Deleting...</span>
              <span v-else>Delete</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { categoryService } from "@/services/admin/categories/categoryService";
import { useRouter } from "vue-router";

const categories = ref([]);
const currentPage = ref(1);
const perPage = ref(10);
const total = ref(0);
const lastPage = ref(1);
const from = ref(1);
const to = ref(0);
const loading = ref(false);
const router = useRouter();
const showModal = ref(false);
const isEditMode = ref(false);
const showDeleteConfirm = ref(false);
const categoryToDelete = ref(null);

const form = ref({
  id: null,
  name: "",
  processing: false,
  errors: {},
});

/* ========= FETCH ========= */
async function fetchCategories(page = 1) {
  loading.value = true;
  const result = await categoryService.getCategories(page);

  if (result.success) {
    categories.value = result.data.data;
    const p = result.data.pagination;
    currentPage.value = p.current_page;
    perPage.value = p.per_page;
    total.value = p.total;
    lastPage.value = p.last_page;
    from.value = p.from;
    to.value = p.to;
  } else {
    console.error(result.error);
    // يمكنك عرض toast أو alert هنا
  }

  loading.value = false;
}
// في <script setup>
function viewCategory(categoryId) {
  if (!categoryId) {
    console.warn("No category ID provided");
    return;
  }
  router.push(`/admin/categories/${categoryId}`);
}
/* ========= CREATE / UPDATE ========= */
async function submitForm() {
  form.value.processing = true;
  form.value.errors = {};

  let result;

  if (isEditMode.value) {
    result = await categoryService.updateCategory(form.value.id, form.value.name);
  } else {
    result = await categoryService.createCategory(form.value.name);
  }

  if (result.success) {
    closeModal();
    await fetchCategories(currentPage.value);
  } else {
    if (result.error?.type === "validation") {
      form.value.errors = result.error.messages;
    } else {
      alert(result.error || "حدث خطأ أثناء الحفظ");
    }
  }

  form.value.processing = false;
}

/* ========= DELETE ========= */
async function performDelete() {
  if (!categoryToDelete.value?.id) return;

  form.value.processing = true;

  const result = await categoryService.deleteCategory(categoryToDelete.value.id);

  if (result.success) {
    showDeleteConfirm.value = false;
    await fetchCategories(currentPage.value);
  } else {
    alert(result.error || "فشل الحذف");
  }

  form.value.processing = false;
}

/* ========= Modal & Navigation helpers ========= */
function openCreateModal() {
  form.value = { id: null, name: "", processing: false, errors: {} };
  isEditMode.value = false;
  showModal.value = true;
}

function openEditModal(cat) {
  form.value = {
    id: cat.id,
    name: cat.name,
    processing: false,
    errors: {},
  };
  isEditMode.value = true;
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  // reset form after animation
  setTimeout(() => {
    form.value = { id: null, name: "", processing: false, errors: {} };
  }, 300);
}

function confirmDelete(cat) {
  categoryToDelete.value = cat;
  showDeleteConfirm.value = true;
}

function changePage(page) {
  if (page < 1 || page > lastPage.value || loading.value) return;
  currentPage.value = page;
  fetchCategories(page);
}

const visiblePages = computed(() => {
  const pages = [];
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

function formatDate(date) {
  if (!date) return "—";
  return new Date(date).toLocaleDateString("ar-EG", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

onMounted(() => {
  fetchCategories();
});
</script>
