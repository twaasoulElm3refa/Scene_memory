<template>
  <AdminLayout>
    <div class="p-8 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-3xl font-bold text-gray-900">Tags</h2>
          <p class="mt-1.5 text-sm text-gray-500">
            Manage and organize tags across the platform
          </p>
        </div>
        <button
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="loading || form.processing"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 4v16m8-8H4"
            />
          </svg>
          Add Tag
        </button>
      </div>

      <!-- Table Card -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div
          class="px-6 py-4 border-b border-gray-200 flex flex-col gap-3 bg-gray-50 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="text-sm text-gray-600">
            Showing <span class="font-medium text-gray-900">{{ from }}</span> to
            <span class="font-medium text-gray-900">{{ to }}</span> of
            <span class="font-medium text-gray-900">{{ total }}</span> tags
          </div>

          <label class="flex items-center gap-2 text-sm text-gray-600">
            <span>Rows per page</span>
            <select
              v-model.number="perPage"
              @change="changePerPage"
              class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="loading || form.processing"
            >
              <option
                v-for="option in perPageOptions"
                :key="option"
                :value="option"
              >
                {{ option }}
              </option>
            </select>
          </label>
        </div>

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
              <span class="text-sm font-medium text-gray-700">Loading tags...</span>
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
                  Tag
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"
                >
                  Mode
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"
                >
                  Translation
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
                v-for="tag in tags"
                :key="tag.id"
                class="hover:bg-gray-50 transition-colors"
              >
                <td class="px-6 py-4">
                  <div>
                    <div class="font-semibold text-gray-900">
                      {{ tag.name }}
                    </div>

                    <div
                      v-if="
                        tag.translation?.name &&
                        tag.translation?.name !== tag.name
                      "
                      class="mt-1 text-xs text-gray-500"
                    >
                      {{ tag.name }}
                    </div>
                  </div>
                </td>

                <td class="px-6 py-4">
                  <span
                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold uppercase"
                    :class="modeClasses(tag.mode)"
                  >
                    {{ formatMode(tag.mode) }}
                  </span>
                </td>

                <td class="px-6 py-4">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-700">
                      {{ tag.translation?.name || "No translation" }}
                    </span>

                    <span
                      v-if="tag.translation?.locale"
                      class="inline-flex rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[10px] font-bold uppercase text-blue-700"
                    >
                      {{ tag.translation?.locale?.toUpperCase() }}
                    </span>
                  </div>
                </td>

                <td class="px-6 py-4 text-center">
                  <div class="inline-flex items-center gap-2">
                    <button
                      @click="openEditModal(tag)"
                      class="p-2.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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

                    <button
                      @click="confirmDelete(tag)"
                      class="p-2.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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

              <tr v-if="!loading && !tags.length">
                <td colspan="5" class="px-6 py-16 text-center">
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
                    No tags found
                  </p>
                  <p class="text-sm text-gray-500">
                    Create a tag to get started
                  </p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div
          v-if="total > 0"
          class="px-6 py-4 border-t border-gray-200 flex flex-col gap-3 bg-gray-50 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="text-sm text-gray-600">
            Showing <span class="font-medium text-gray-900">{{ from }}</span> to
            <span class="font-medium text-gray-900">{{ to }}</span> of
            <span class="font-medium text-gray-900">{{ total }}</span> tags
          </div>

          <div class="flex items-center gap-1">
            <button
              :disabled="loading || form.processing || currentPage === 1"
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
              :disabled="loading || form.processing || page === currentPage"
              @click="changePage(page)"
              :class="{
                'min-w-[2.6rem] px-3 py-2 text-sm font-medium rounded-lg transition-colors': true,
                'bg-blue-600 text-white shadow-sm': currentPage === page,
                'text-gray-700 border border-gray-300 hover:bg-gray-50':
                  currentPage !== page,
                'opacity-50 cursor-not-allowed':
                  loading || form.processing,
              }"
            >
              {{ page }}
            </button>

            <button
              :disabled="loading || form.processing || currentPage === lastPage"
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

      <!-- Tag Modal (Create & Edit) -->
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden">
          <div class="px-6 py-5 border-b border-gray-200">
            <h3 class="text-xl font-bold text-gray-900">
              {{ isEditMode ? "Edit Tag" : "Add New Tag" }}
            </h3>
          </div>

          <form @submit.prevent="submitForm" class="p-6 space-y-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Name
              </label>
              <input
                v-model="form.name"
                type="text"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                placeholder="e.g. greece"
                :class="{ 'border-red-500': form.errors.name }"
                required
              />
              <p v-if="form.errors.name" class="mt-1.5 text-sm text-red-600">
                {{ firstError(form.errors.name) }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Mode
              </label>
              <select
                v-model="form.mode"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                :class="{ 'border-red-500': form.errors.mode }"
                required
              >
                <option value="ai">ai</option>
                <option value="user">user</option>
              </select>
              <p v-if="form.errors.mode" class="mt-1.5 text-sm text-red-600">
                {{ firstError(form.errors.mode) }}
              </p>
            </div>

            <div
              class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200"
            >
              <button
                type="button"
                @click="closeModal"
                class="px-5 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
          <h3 class="text-xl font-bold text-gray-900 mb-3">Delete Tag?</h3>
          <p class="text-gray-600 mb-6">
            Are you sure you want to delete
            <span class="font-semibold text-gray-900">
              {{ selectedTag?.translation?.name || selectedTag?.name }}
            </span>
            ? This action cannot be undone.
          </p>

          <div class="flex items-center justify-end gap-3">
            <button
              @click="showDeleteConfirm = false"
              class="px-5 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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
import { tagService } from "@/services/admin/tags/tagService";
import { useRouter } from "vue-router";

const tags = ref([]);
const currentPage = ref(1);
const perPage = ref(30);
const total = ref(0);
const lastPage = ref(1);
const from = ref(0);
const to = ref(0);
const loading = ref(false);
const router = useRouter();
const showModal = ref(false);
const isEditMode = ref(false);
const showDeleteConfirm = ref(false);
const selectedTag = ref(null);
const perPageOptions = [10, 20, 30, 50, 100];

const form = ref({
  name: "",
  mode: "ai",
  processing: false,
  errors: {},
});

async function fetchTags(page = 1) {
  loading.value = true;

  try {
    const result = await tagService.getTags(
      page,
      perPage.value
    );

    if (result.success) {
      tags.value = result.data.tags;

      const pagination = result.data.pagination;

      currentPage.value = pagination.current_page;
      perPage.value = pagination.per_page;
      total.value = pagination.total;
      lastPage.value = pagination.last_page;
      from.value = pagination.from;
      to.value = pagination.to;
    } else {
      console.error(result.error);
    }
  } finally {
    loading.value = false;
  }
}

function modeClasses(mode) {
  const normalized = String(mode || "")
    .trim()
    .toLowerCase();

  if (normalized === "user") {
    return "bg-emerald-50 text-emerald-700 border-emerald-100";
  }

  if (normalized === "ai") {
    return "bg-violet-50 text-violet-700 border-violet-100";
  }

  return "bg-slate-50 text-slate-700 border-slate-200";
}

function formatMode(mode) {
  const normalized = String(mode || "")
    .trim()
    .toUpperCase();

  return normalized || "-";
}

function firstError(error) {
  return Array.isArray(error) ? error[0] : error;
}

function handleActionError(error, fallback) {
  alert(
    typeof error === "string"
      ? error
      : error?.message ||
          fallback ||
          "An unexpected error occurred"
  );
}

function viewTag(tag) {
  if (!tag?.slug) {
    console.warn("No tag slug provided");
    return;
  }

  router.push(
    `/admin/tags/${encodeURIComponent(tag.slug)}`
  );
}

function openCreateModal() {
  selectedTag.value = null;
  form.value = {
    name: "",
    mode: "ai",
    processing: false,
    errors: {},
  };
  isEditMode.value = false;
  showModal.value = true;
}

function openEditModal(tag) {
  selectedTag.value = tag;

  form.value = {
    name: tag.translation?.name || tag.name || "",
    mode: tag.mode || "ai",
    processing: false,
    errors: {},
  };

  isEditMode.value = true;
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  selectedTag.value = null;

  setTimeout(() => {
    form.value = {
      name: "",
      mode: "ai",
      processing: false,
      errors: {},
    };
  }, 300);
}

async function submitForm() {
  form.value.processing = true;
  form.value.errors = {};

  try {
    const payload = {
      name: form.value.name.trim(),
      mode: form.value.mode,
    };

    const result = isEditMode.value
      ? await tagService.updateTag(
          selectedTag.value.slug,
          payload
        )
      : await tagService.createTag(payload);

    if (result.success) {
      if (isEditMode.value) {
        closeModal();
        await fetchTags(currentPage.value);
      } else {
        closeModal();
        await fetchTags(1);
      }
    } else if (result.error?.type === "validation") {
      form.value.errors =
        result.error.messages || {};
    } else {
      handleActionError(
        result.error,
        "An unexpected error occurred"
      );
    }
  } finally {
    form.value.processing = false;
  }
}

function confirmDelete(tag) {
  selectedTag.value = tag;
  showDeleteConfirm.value = true;
}

async function performDelete() {
  if (!selectedTag.value?.slug) return;

  form.value.processing = true;

  try {
    const result = await tagService.deleteTag(
      selectedTag.value.slug
    );

    if (result.success) {
      showDeleteConfirm.value = false;

      const targetPage =
        tags.value.length === 1 &&
        currentPage.value > 1
          ? currentPage.value - 1
          : currentPage.value;

      selectedTag.value = null;
      await fetchTags(targetPage);
    } else {
      handleActionError(result.error, "Failed to delete tag");
    }
  } finally {
    form.value.processing = false;
  }
}

function changePage(page) {
  if (
    page < 1 ||
    page > lastPage.value ||
    loading.value ||
    form.value.processing
  ) {
    return;
  }

  currentPage.value = page;
  fetchTags(page);
}

async function changePerPage() {
  if (loading.value || form.value.processing) {
    return;
  }

  currentPage.value = 1;
  await fetchTags(1);
}

const visiblePages = computed(() => {
  const pages = [];
  const maxVisible = 5;
  let start = Math.max(1, currentPage.value - 2);
  let end = Math.min(
    lastPage.value,
    start + maxVisible - 1
  );

  if (end - start < maxVisible - 1) {
    start = Math.max(1, end - maxVisible + 1);
  }

  for (let page = start; page <= end; page += 1) {
    pages.push(page);
  }

  return pages;
});

onMounted(() => {
  fetchTags();
});
</script>
