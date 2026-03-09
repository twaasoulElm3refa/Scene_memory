<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto p-6">
      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 text-sm text-gray-600 mb-4">
        <span>Dashboard</span>
        <span>›</span>
        <span>Settings</span>
        <span>›</span>
        <span>Countries</span>
        <span>›</span>
        <span class="text-gray-900 font-medium">Add New</span>
      </div>

      <!-- Page Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Add New Country</h1>
        <p class="text-gray-600">
          Register a new country profile in the global database.
        </p>
      </div>

      <!-- Form -->
      <form
        @submit.prevent="handleSubmit"
        class="bg-white rounded-lg border border-gray-200 p-6"
      >
        <!-- Country Code -->
        <div class="mb-6">
          <label for="countryCode" class="block text-sm font-semibold text-gray-900 mb-2">
            Country Code
          </label>
          <input
            id="countryCode"
            v-model="formData.code"
            type="text"
            placeholder="e.g., US, GB, FR, EG, SA"
            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
            required
            maxlength="3"
            pattern="[A-Za-z]{2,3}"
            title="Use 2 or 3 letter ISO country code (e.g. EG, USA, GBR)"
          />
          <p class="text-sm text-gray-500 mt-1">
            ISO Alpha-2 or Alpha-3 code (usually 2 or 3 uppercase letters)
          </p>
        </div>

        <!-- Country Flag / Image -->
        <div class="mb-6">
          <label class="block text-sm font-semibold text-gray-900 mb-2">
            Country Flag / Image
          </label>

          <!-- Upload Area -->
          <div
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
            @click="triggerFileInput"
            :class="[
              'border-2 border-dashed rounded-lg p-12 text-center cursor-pointer transition',
              isDragging
                ? 'border-blue-500 bg-blue-50'
                : 'border-gray-300 hover:border-gray-400',
            ]"
          >
            <!-- Preview Image -->
            <div v-if="imagePreview" class="mb-4">
              <img
                :src="imagePreview"
                alt="Flag preview"
                class="max-h-48 mx-auto rounded"
              />
              <button
                type="button"
                @click.stop="clearImage"
                class="mt-3 text-sm text-red-600 hover:text-red-700 font-medium"
              >
                Remove Flag
              </button>
            </div>

            <!-- Upload Icon and Text -->
            <div v-else>
              <svg
                class="w-12 h-12 mx-auto mb-4 text-blue-500"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z"
                />
                <path d="M9 13h2v5a1 1 0 11-2 0v-5z" />
              </svg>
              <p class="text-gray-700 mb-1">Click to upload or drag and drop</p>
              <p class="text-sm text-gray-500">SVG, PNG, JPG or GIF (max. 2MB)</p>
            </div>
          </div>

          <!-- Hidden File Input -->
          <input
            ref="fileInput"
            type="file"
            accept="image/svg+xml,image/png,image/jpeg,image/jpg,image/gif"
            @change="handleFileSelect"
            class="hidden"
          />
        </div>

        <!-- Error Message -->
        <div
          v-if="errorMessage"
          class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg"
        >
          <p class="text-sm text-red-600">{{ errorMessage }}</p>
        </div>

        <!-- Success Message -->
        <div
          v-if="successMessage"
          class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg"
        >
          <p class="text-sm text-green-600">{{ successMessage }}</p>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-4">
          <button
            type="button"
            @click="handleCancel"
            class="px-6 py-2.5 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition font-medium"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="isSubmitting"
            class="px-6 py-2.5 text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ isSubmitting ? "Saving..." : "Save Country" }}
          </button>
        </div>
      </form>

      <!-- Info Note -->
      <div class="mt-4 flex items-start gap-2 text-sm text-gray-500">
        <svg class="w-4 h-4 mt-0.5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
          <path
            fill-rule="evenodd"
            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
            clip-rule="evenodd"
          />
        </svg>
        <p>
          New countries are automatically set to 'Inactive' until verified by a senior
          admin.
        </p>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { countryService } from "@/services/admin/countries/countryService";

const router = useRouter();

const formData = ref({
  code: "",
});

const imagePreview = ref(null);
const isDragging = ref(false);
const isSubmitting = ref(false);
const errorMessage = ref("");
const successMessage = ref("");
const fileInput = ref(null);

let selectedFile = ref(null);

const handleFileSelect = (event) => {
  const file = event.target.files?.[0];
  if (file) validateAndSetImage(file);
};

const handleDrop = (event) => {
  isDragging.value = false;
  const file = event.dataTransfer.files?.[0];
  if (file) validateAndSetImage(file);
};

const validateAndSetImage = (file) => {
  const validTypes = ["image/svg+xml", "image/png", "image/jpeg", "image/jpg", "image/gif"];
  if (!validTypes.includes(file.type)) {
    errorMessage.value = "Invalid file type. SVG, PNG, JPG, GIF only.";
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    errorMessage.value = "File too large (max 2MB)";
    return;
  }

  errorMessage.value = "";
  selectedFile.value = file;

  const reader = new FileReader();
  reader.onload = (e) => (imagePreview.value = e.target.result);
  reader.readAsDataURL(file);
};

const clearImage = () => {
  selectedFile.value = null;
  imagePreview.value = null;
  if (fileInput.value) fileInput.value.value = "";
};

const triggerFileInput = () => fileInput.value?.click();

const handleSubmit = async () => {
  errorMessage.value = "";
  successMessage.value = "";

  const code = formData.value.code.trim().toUpperCase();
  if (!code) {
    errorMessage.value = "Country code is required.";
    return;
  }

  if (!selectedFile.value) {
    errorMessage.value = "Country flag/image is required.";
    return;
  }

  isSubmitting.value = true;

  try {
    const response = await countryService.createCountry(
      { code },
      selectedFile.value
    );
    if (response.data?.status === "success" || response.status === 201) {
      successMessage.value = "Country created successfully!";
      setTimeout(() => router.push("/admin/countries"), 1400);
    } else {
      throw new Error(response.data?.message || "Creation failed");
    }
  } catch (err) {
    console.error(err);
    errorMessage.value =
      err.response?.data?.message ||
      err.message ||
      "An error occurred while creating the country.";
  } finally {
    isSubmitting.value = false;
  }
};

const handleCancel = () => router.push("/admin/countries");
</script>
