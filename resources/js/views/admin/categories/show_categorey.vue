<template>
  <AdminLayout>
    <div
      dir="rtl"
      class="min-h-screen flex flex-col bg-gradient-to-br from-gray-50 to-gray-100"
    >
      <!-- Header -->
      <div
        class="top-0 z-10 px-4 py-5 sm:px-6 lg:px-8 bg-white/80 backdrop-blur-md border-b border-gray-200/80 shadow-sm"
      >
        <div
          class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 max-w-7xl mx-auto"
        >
          <div class="flex items-center gap-3">
            <div class="p-2 bg-blue-50 rounded-xl">
              <svg
                class="w-6 h-6 text-blue-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h7"
                ></path>
              </svg>
            </div>
            <div>
              <h1
                class="text-2xl sm:text-3xl font-bold bg-gradient-to-l from-gray-900 to-gray-700 bg-clip-text text-transparent"
              >
                {{ category?.name || "تحميل..." }}
              </h1>
              <p class="mt-1 text-sm sm:text-base text-gray-600 flex items-center gap-2">
                <svg
                  class="w-4 h-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                  ></path>
                </svg>
                {{ formatDate(category?.created_at) }}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-3 flex-wrap">
            <button
              @click="goToAddSubCategory"
              class="group px-5 py-2.5 bg-green-50 hover:bg-green-100 text-green-700 rounded-xl transition-all duration-200 flex items-center gap-2 text-sm sm:text-base whitespace-nowrap border border-green-200 shadow-sm hover:shadow-md"
            >
              <svg
                class="w-5 h-5 transition-transform group-hover:rotate-90"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4v16m8-8H4"
                />
              </svg>
              إضافة تصنيف فرعي
            </button>

            <button
              @click="$router.back()"
              class="group px-5 py-2.5 bg-white hover:bg-gray-50 text-gray-700 rounded-xl transition-all duration-200 flex items-center gap-2 text-sm sm:text-base whitespace-nowrap border border-gray-200 shadow-sm hover:shadow-md"
            >
              <svg
                class="w-5 h-5 rotate-180 transition-transform group-hover:-translate-x-1"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M10 19l-7-7m0 0l7-7m-7 7h18"
                />
              </svg>
              رجوع
            </button>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="flex-1 px-4 py-8 sm:px-6 lg:px-8">
        <!-- Loading -->
        <div
          v-if="loading"
          class="flex flex-col justify-center items-center min-h-[60vh]"
        >
          <div class="relative">
            <div
              class="animate-spin rounded-full h-16 w-16 border-4 border-gray-200 border-t-blue-600 border-r-blue-600"
            ></div>
            <div class="absolute inset-0 flex items-center justify-center">
              <div class="h-8 w-8 bg-blue-100 rounded-full animate-pulse"></div>
            </div>
          </div>
          <p class="mt-4 text-gray-600 font-medium">جاري تحميل التفاصيل...</p>
        </div>

        <!-- Error -->
        <div
          v-else-if="error"
          class="max-w-4xl mx-auto bg-red-50/90 backdrop-blur-sm border border-red-200 rounded-2xl p-8 text-center shadow-lg"
        >
          <div class="flex justify-center mb-4">
            <div class="p-3 bg-red-100 rounded-full">
              <svg
                class="w-8 h-8 text-red-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                ></path>
              </svg>
            </div>
          </div>
          <p class="text-red-700 text-lg">{{ error }}</p>
          <button
            @click="fetchCategory"
            class="mt-4 px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl transition-colors"
          >
            إعادة المحاولة
          </button>
        </div>

        <!-- Content -->
        <div v-else-if="category" class="space-y-8 max-w-7xl mx-auto">
          <!-- Basic Info Card -->
          <div
            class="bg-white/90 backdrop-blur-sm shadow-lg border border-gray-100 rounded-2xl overflow-hidden transition-all hover:shadow-xl"
          >
            <div
              class="px-8 py-6 border-b border-gray-100 bg-gradient-to-l from-gray-50 to-white"
            >
              <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-50 rounded-lg">
                  <svg
                    class="w-5 h-5 text-blue-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    ></path>
                  </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900">معلومات الفئة الأساسية</h2>
              </div>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              <div class="text-right bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                <label class="block text-sm font-medium text-gray-600 mb-1">الاسم</label>
                <p class="text-gray-900 font-bold text-lg">{{ category.name }}</p>
              </div>

              <div class="text-right bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                <label class="block text-sm font-medium text-gray-600 mb-1">Slug</label>
                <p
                  class="text-gray-900 font-medium break-all bg-white p-2 rounded-lg border border-gray-200"
                >
                  {{ category.slug || "—" }}
                </p>
              </div>

              <div class="text-right bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                <label class="block text-sm font-medium text-gray-600 mb-1"
                  >تاريخ الإنشاء</label
                >
                <p class="text-gray-900 font-medium flex items-center gap-2">
                  <svg
                    class="w-4 h-4 text-gray-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    ></path>
                  </svg>
                  {{ formatDate(category.created_at) }}
                </p>
              </div>

              <div class="text-right bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                <label class="block text-sm font-medium text-gray-600 mb-1"
                  >آخر تحديث</label
                >
                <p class="text-gray-900 font-medium flex items-center gap-2">
                  <svg
                    class="w-4 h-4 text-gray-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                    ></path>
                  </svg>
                  {{ formatDate(category.updated_at) }}
                </p>
              </div>

              <div
                class="md:col-span-2 lg:col-span-3 text-right bg-gradient-to-br from-gray-50 to-white p-6 rounded-2xl border border-gray-200"
              >
                <label
                  class="block text-lg font-medium text-gray-800 mb-4 flex items-center gap-2"
                >
                  <svg
                    class="w-5 h-5 text-blue-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                    ></path>
                  </svg>
                  صورة الفئة
                </label>
                <div class="flex justify-center md:justify-start">
                  <div
                    class="w-full max-w-2xl h-80 rounded-2xl overflow-hidden border-2 border-gray-200 bg-gradient-to-br from-gray-100 to-gray-50 flex items-center justify-center shadow-lg group hover:shadow-xl transition-shadow"
                  >
                    <div v-if="category.image" class="w-full h-full">
                      <img
                        :src="category.image"
                        :alt="category.name"
                        class="w-full h-full object-cover transition-transform group-hover:scale-105 duration-500"
                      />
                    </div>
                    <div v-else class="text-center p-8">
                      <svg
                        class="mx-auto h-24 w-24 text-gray-300"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="1.5"
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                        ></path>
                      </svg>
                      <p class="mt-4 text-lg text-gray-400">لا توجد صورة متاحة</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sub Categories Card – الجزء المعدل -->
          <div
            class="bg-white/90 backdrop-blur-sm shadow-lg border border-gray-100 rounded-2xl overflow-hidden transition-all hover:shadow-xl"
          >
            <div
              class="px-8 py-6 border-b border-gray-100 bg-gradient-to-l from-gray-50 to-white flex items-center justify-between"
            >
              <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-50 rounded-lg">
                  <svg
                    class="w-5 h-5 text-purple-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                    />
                  </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900">الفئات الفرعية</h2>
              </div>
              <span
                class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold bg-gradient-to-l from-purple-500 to-purple-600 text-white shadow-md"
              >
                {{ category.sub_categories?.length || 0 }} فئة فرعية
              </span>
            </div>

            <div class="p-8">
              <div
                v-if="category.sub_categories?.length"
                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
              >
                <div
                  v-for="sub in category.sub_categories"
                  :key="sub.id"
                  class="relative p-6 bg-gradient-to-br from-gray-50 to-white rounded-xl border-2 border-gray-200 hover:border-purple-300 transition-all duration-200 hover:shadow-lg hover:-translate-y-1 text-right flex items-start justify-between gap-4"
                >
                  <!-- المحتوى الرئيسي -->
                  <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-gray-900 text-lg mb-2">{{ sub.name }}</h3>
                    <div class="bg-gray-100 p-2 rounded-lg mb-3">
                      <p class="text-sm text-gray-700 font-mono break-all">
                        <span class="text-gray-500">Slug:</span> {{ sub.slug || "—" }}
                      </p>
                    </div>
                    <p class="text-sm text-gray-500 flex items-center gap-2">
                      <svg
                        class="w-3 h-3"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                      </svg>
                      {{ formatDate(sub.created_at) }}
                    </p>
                  </div>

                  <!-- أزرار التعديل والحذف – دائمًا ظاهرة -->
                  <div class="flex items-center gap-1.5 shrink-0">
                    <button
                      @click="openEditModal(sub)"
                      class="p-2.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-400"
                      title="تعديل التصنيف الفرعي"
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
                      @click="confirmDelete(sub)"
                      class="p-2.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-red-400"
                      title="حذف التصنيف الفرعي"
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
                </div>
              </div>

              <!-- حالة عدم وجود تصنيفات فرعية -->
              <div v-else class="py-20 text-center">
                <div class="flex justify-center mb-4">
                  <div class="p-4 bg-gray-100 rounded-full">
                    <svg
                      class="w-12 h-12 text-gray-400"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M20 12H4M12 4v16"
                      />
                    </svg>
                  </div>
                </div>
                <p class="text-gray-500 text-lg">
                  لا توجد فئات فرعية مرتبطة بهذه الفئة حاليًا
                </p>
                <p class="text-gray-400 text-sm mt-2">
                  يمكنك إضافة فئات فرعية من الزر أعلاه
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- تعديل تصنيف فرعي - Modal -->
      <div
        v-if="showEditModal"
        class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden">
          <div class="px-6 py-5 border-b border-gray-200 bg-gray-50">
            <h3 class="text-xl font-bold text-gray-900">تعديل التصنيف الفرعي</h3>
          </div>

          <form @submit.prevent="updateSubCategory" class="p-6 space-y-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">الاسم</label>
              <input
                v-model="editForm.name"
                type="text"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                :class="{ 'border-red-500': editForm.errors.name }"
                required
              />
              <p v-if="editForm.errors.name" class="mt-1.5 text-sm text-red-600">
                {{ editForm.errors.name[0] }}
              </p>
            </div>

            <div
              class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200"
            >
              <button
                type="button"
                @click="closeEditModal"
                class="px-5 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                :disabled="editForm.processing"
              >
                إلغاء
              </button>
              <button
                type="submit"
                class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-60"
                :disabled="editForm.processing"
              >
                <span v-if="editForm.processing">جاري الحفظ...</span>
                <span v-else>حفظ التعديلات</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- تأكيد الحذف - Modal -->
      <div
        v-if="showDeleteConfirm"
        class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
          <h3 class="text-xl font-bold text-gray-900 mb-3">تأكيد الحذف</h3>
          <p class="text-gray-600 mb-6">
            هل أنت متأكد من حذف التصنيف الفرعي
            <span class="font-semibold text-gray-900">{{ subToDelete?.name }}</span
            >؟ هذا الإجراء لا يمكن التراجع عنه.
          </p>

          <div class="flex items-center justify-end gap-3">
            <button
              @click="showDeleteConfirm = false"
              class="px-5 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
              :disabled="deleteProcessing"
            >
              إلغاء
            </button>
            <button
              @click="performDelete"
              class="px-5 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors disabled:opacity-60 flex items-center gap-2"
              :disabled="deleteProcessing"
            >
              <span v-if="deleteProcessing">جاري الحذف...</span>
              <span v-else>حذف</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { CategoryService } from "../../../services/CategoryService/CategoryService";
import { categoryService } from "../../../services/admin/categories/categoryService";

const route = useRoute();
const router = useRouter();

const category = ref(null);
const loading = ref(true);
const error = ref(null);
const showEditModal = ref(false);
const editForm = ref({
  id: null,
  name: "",
  processing: false,
  errors: {},
});
const showDeleteConfirm = ref(false);
const subToDelete = ref(null);
const deleteProcessing = ref(false);

async function fetchCategory() {
  loading.value = true;
  error.value = null;

  try {
    const response = await CategoryService.getCategoryById(route.params.id);

    if (response.data.status === "success") {
      category.value = response.data.data;
    } else {
      error.value = "حدث خطأ أثناء جلب البيانات";
    }
  } catch (err) {
    console.error("Fetch category error:", err);
    error.value = err.response?.data?.message || "فشل في تحميل تفاصيل الفئة";
  } finally {
    loading.value = false;
  }
}

function goToAddSubCategory() {
  const categoryId = route.params.id;
  if (categoryId) {
    router.push(`/admin/categories/${categoryId}/add`);
  }
}

function formatDate(dateString) {
  if (!dateString) return "—";
  const date = new Date(dateString);
  return date.toLocaleDateString("ar-EG", {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}
function openEditModal(sub) {
  editForm.value = {
    id: sub.id,
    name: sub.name,
    processing: false,
    errors: {},
  };
  showEditModal.value = true;
}
function closeEditModal() {
  showEditModal.value = false;
  editForm.value = { id: null, name: "", processing: false, errors: {} };
}
async function updateSubCategory() {
  editForm.value.processing = true;
  editForm.value.errors = {};

  try {
    const result = await categoryService.updateSubCategory(
      editForm.value.id,
      editForm.value.name.trim()
    );
    if (!result?.success) throw result?.error || new Error("Update failed");

    closeEditModal();
    await fetchCategory();
  } catch (err) {
    if (err.response?.status === 422) {
      editForm.value.errors = err.response.data.errors || {};
    } else {
      alert("حدث خطأ أثناء التعديل");
      console.error(err);
    }
  } finally {
    editForm.value.processing = false;
  }
}
function confirmDelete(sub) {
  subToDelete.value = sub;
  showDeleteConfirm.value = true;
}
async function performDelete() {
  if (!subToDelete.value) return;

  deleteProcessing.value = true;

  try {
    const result = await categoryService.deleteSubCategory(subToDelete.value.id);
    if (!result?.success) throw result?.error || new Error("Delete failed");

    showDeleteConfirm.value = false;
    await fetchCategory();
  } catch (err) {
    console.error("Delete error:", err);
    alert("حدث خطأ أثناء الحذف");
  } finally {
    deleteProcessing.value = false;
  }
}

onMounted(() => {
  fetchCategory();
});
</script>
