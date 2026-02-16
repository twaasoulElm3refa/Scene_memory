<template>
  <AdminLayout>
    <div class="w-full min-h-screen py-10 px-5 md:px-8 lg:px-12 bg-gray-50 overflow-auto">
      <!-- Header + Breadcrumb -->
      <div class="mb-10">
        <nav class="text-sm text-gray-500 mb-3">
          <ol class="flex items-center space-x-2">
            <li>
              <a href="/admin/categories" class="hover:text-blue-600">Categories</a>
            </li>
            <li><span class="text-gray-400">/</span></li>
            <li class="font-medium text-gray-900">Add New</li>
          </ol>
        </nav>
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">
          Add New Category
        </h1>
        <p class="mt-2 text-base md:text-lg text-gray-600">
          Create a new event category to help users discover and organize gatherings.
        </p>
      </div>

      <!-- Form Card -->
      <div
        class="bg-white shadow-xl rounded-2xl p-6 md:p-8 lg:p-10 border border-gray-100 max-w-4xl mx-auto"
      >
        <!-- Category Name -->
        <div class="mb-8">
          <label class="block text-lg font-semibold text-gray-900 mb-2.5">
            Category Name <span class="text-red-500 text-sm">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            placeholder="e.g., Electronic Music Festivals, Art Exhibitions..."
            class="w-full px-5 py-3.5 text-base border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all shadow-sm hover:shadow"
            :class="{ 'border-red-400 ring-1 ring-red-400': form.errors.name }"
          />
          <p v-if="form.errors.name" class="mt-2 text-sm text-red-600 font-medium">
            {{ form.errors.name[0] || form.errors.name }}
          </p>
          <p class="mt-2.5 text-sm text-gray-500">
            Choose a clear, descriptive name — this appears in category listings and
            filters.
          </p>
        </div>

        <!-- Cover Image – Fixed aspect ratio after upload -->
        <div class="mb-10">
          <label class="block text-lg font-semibold text-gray-900 mb-3">
            Cover Image
            <span class="text-gray-500 font-normal text-sm">(recommended)</span>
          </label>

          <div
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
            @click="triggerFileInput"
            class="group relative w-full h-40 border-2 border-dashed rounded-2xl overflow-hidden transition-all duration-300 cursor-pointer bg-gray-50"
            :class="{
              'border-blue-500 bg-blue-50/60 shadow-lg scale-[1.01]': isDragging,
              'border-gray-300 hover:border-blue-400 hover:bg-blue-50/40 hover:shadow-md':
                !isDragging && !previewUrl,
              'border-green-400 bg-green-50/30 shadow-md': previewUrl,
            }"
          >
            <input
              ref="fileInput"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              hidden
              @change="handleFileChange"
            />

            <!-- Default / Drag state -->
            <div
              v-if="!previewUrl"
              class="absolute inset-0 flex flex-col items-center justify-center text-center px-6 py-12"
            >
              <div
                class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-5 group-hover:scale-110 transition-transform"
              >
                <svg
                  class="w-10 h-10 text-blue-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                  />
                </svg>
              </div>
              <p
                class="text-xl font-semibold text-gray-800 mb-2 group-hover:text-blue-700 transition-colors"
              >
                Drag & drop or click to upload
              </p>
              <p class="text-sm text-gray-500">
                PNG, JPG, WEBP — ideal 1600×900px or larger
              </p>
            </div>

            <!-- Preview with FIXED aspect ratio -->
            <div v-else class="relative w-full" style="aspect-ratio: 16 / 7">
              <img
                :src="previewUrl"
                alt="Category cover preview"
                class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
              />
              <!-- Overlay on hover -->
              <div
                class="absolute inset-0 bg-black/45 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center"
              >
                <p class="text-white text-base font-medium px-6 text-center">
                  Click to change or drag new image
                </p>
              </div>
            </div>
          </div>

          <p class="mt-3 text-xs md:text-sm text-gray-500 text-center italic">
            Use vibrant, high-quality images that reflect the category atmosphere
          </p>
        </div>

        <!-- Actions -->
        <div
          class="flex flex-col sm:flex-row sm:items-center justify-end gap-4 pt-8 border-t border-gray-100"
        >
          <button
            @click="cancel"
            class="px-8 py-3.5 bg-white border-2 border-gray-300 text-gray-700 text-base font-medium rounded-xl hover:bg-gray-50 hover:border-gray-400 transition-all shadow-sm min-w-[120px]"
          >
            Cancel
          </button>
          <button
            @click="createCategory"
            :disabled="loading || !form.name.trim()"
            class="px-8 py-3.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-base font-semibold rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all disabled:opacity-60 disabled:cursor-not-allowed shadow-md hover:shadow-lg min-w-[180px]"
          >
            {{ loading ? "Creating..." : "Create Category" }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from "vue";
import AdminLayout from "@/layouts/AdminLayout.vue"; // تأكد من المسار الصحيح
import { categoryService } from "@/services/admin/categories/categoryService";

const form = ref({
  name: "",
  active: true,
  featured: false,
  coverImage: null,
  errors: {},
});

const previewUrl = ref(null);
const fileInput = ref(null);
const isDragging = ref(false);
const loading = ref(false);

function triggerFileInput() {
  fileInput.value?.click();
}

async function handleFileChange(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  await processFile(file);
}

async function handleDrop(e) {
  isDragging.value = false;
  const file = e.dataTransfer.files?.[0];
  if (!file) return;
  await processFile(file);
}

async function processFile(file) {
  if (!file.type.startsWith("image/")) {
    alert("يرجى رفع ملف صورة (PNG, JPG, WEBP)");
    return;
  }

  try {
    form.value.coverImage = file;
    previewUrl.value = await categoryService.generatePreviewUrl(file);
  } catch (err) {
    alert(err.message || "فشل معاينة الصورة");
  }
}

async function createCategory() {
  if (!form.value.name.trim()) {
    form.value.errors.name = ["اسم التصنيف مطلوب"];
    return;
  }

  form.value.errors = {};
  loading.value = true;

  const result = await categoryService.createCategoryWithImage({
    name: form.value.name,
    active: form.value.active,
    featured: form.value.featured,
    coverImage: form.value.coverImage,
  });

  if (result.success) {
    alert("تم إنشاء التصنيف بنجاح!");
    cancel();
    // أو يمكنك استخدام router.push('/admin/categories') إذا كنت تستخدم vue-router
  } else {
    if (result.error?.type === "validation") {
      form.value.errors = result.error.messages;
    } else {
      alert(result.error?.message || "فشل إنشاء التصنيف");
    }
  }

  loading.value = false;
}

function cancel() {
  form.value = {
    name: "",
    active: true,
    featured: false,
    coverImage: null,
    errors: {},
  };
  previewUrl.value = null;
}
</script>
