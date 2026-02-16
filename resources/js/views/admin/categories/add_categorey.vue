<template>
  <AdminLayout>
    <div class="w-full h-screen py-10 px-5 md:px-8 overflow-auto">
      <!-- Header -->
      <div class="mb-10">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Add New Category</h1>
        <p class="mt-3 text-lg text-gray-600">
          Define a new classification for events and gatherings on the platform.
        </p>
      </div>

      <!-- Main Form Card -->
      <div
        class="bg-white shadow-xl rounded-xl p-7 md:p-9 border border-gray-200 w-full h-full"
      >
        <!-- Category Name -->
        <div class="mb-8">
          <label class="block text-xl font-semibold text-gray-900 mb-3">
            Category Name
          </label>
          <input
            v-model="form.name"
            type="text"
            placeholder="e.g., Electronic Music Festivals"
            class="w-full px-5 py-4 text-lg border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
          />
          <p class="mt-2 text-base text-gray-500">
            This will be visible to all users browsing event categories.
          </p>
        </div>

        <!-- Cover Image – Full Width -->
        <div class="mb-10 flex-1">
          <label class="block text-xl font-semibold text-gray-900 mb-3">
            Category Cover Image
          </label>

          <div
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
            @click="triggerFileInput"
            class="w-full h-full border-3 border-dashed rounded-2xl flex flex-col items-center justify-center cursor-pointer transition-all duration-200"
            :class="{
              'border-blue-500 bg-blue-50': isDragging,
              'border-gray-300 hover:border-blue-400 bg-gray-50 hover:bg-blue-50/30': !isDragging,
            }"
          >
            <input
              ref="fileInput"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              hidden
              @change="handleFileChange"
            />

            <div v-if="!previewUrl" class="text-center px-6">
              <div
                class="mx-auto w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6"
              >
                <svg
                  class="w-12 h-12 text-blue-600"
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
              <p class="text-xl md:text-2xl font-semibold text-gray-800 mb-2">
                Click to upload or drag and drop
              </p>
              <p class="text-base text-gray-500 mt-2">
                PNG, JPG or WEBP (recommended 1200×600px or larger)
              </p>
            </div>

            <img
              v-else
              :src="previewUrl"
              alt="Category preview"
              class="max-h-full max-w-full object-contain rounded-xl shadow-lg"
            />
          </div>

          <p class="mt-3 text-sm text-gray-500 text-center">
            Best results with vibrant, high-resolution, action-oriented images
          </p>
        </div>

        <!-- Switches + Buttons -->
        <div
          class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 border-t pt-8"
        >
          <div class="flex flex-col sm:flex-row gap-4 sm:gap-5">
            <button
              @click="cancel"
              class="px-8 py-4 bg-gray-200 text-gray-800 text-lg font-medium rounded-xl hover:bg-gray-300 transition"
            >
              Cancel
            </button>
            <button
              @click="createCategory"
              :disabled="loading || !form.name.trim()"
              class="px-8 py-4 bg-blue-600 text-white text-lg font-semibold rounded-xl hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed shadow-md"
            >
              {{ loading ? "Creating..." : "Create Category" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from "vue";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import axios from "axios";

const form = ref({
  name: "",
  active: true,
  featured: false,
  image: null,
});

const previewUrl = ref(null);
const fileInput = ref(null);
const isDragging = ref(false);
const loading = ref(false);

function triggerFileInput() {
  fileInput.value?.click();
}

function handleFileChange(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  processFile(file);
}

function handleDrop(e) {
  isDragging.value = false;
  const file = e.dataTransfer.files?.[0];
  if (!file) return;
  processFile(file);
}

function processFile(file) {
  if (!file.type.startsWith("image/")) {
    alert("Please upload an image file (PNG, JPG, WEBP)");
    return;
  }

  form.value.image = file;

  const reader = new FileReader();
  reader.onload = (e) => {
    previewUrl.value = e.target.result;
  };
  reader.readAsDataURL(file);
}

const createCategory = async () => {
  if (!form.value.name.trim()) {
    alert("Category name is required");
    return;
  }

  loading.value = true;

  const fd = new FormData();
  fd.append("name", form.value.name.trim());
  fd.append("active", form.value.active ? 1 : 0);
  fd.append("featured", form.value.featured ? 1 : 0);
  if (form.value.image) {
    fd.append("cover_image", form.value.image);
  }

  try {
    await axios.post("/v1/categories/create", fd, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    alert("Category created successfully!");
    // You can reset form or redirect here
    cancel();
  } catch (err) {
    console.error(err);
    alert(err.response?.data?.message || "Failed to create category");
  } finally {
    loading.value = false;
  }
};

function cancel() {
  form.value = {
    name: "",
    active: true,
    featured: false,
    image: null,
  };
  previewUrl.value = null;
}
</script>
