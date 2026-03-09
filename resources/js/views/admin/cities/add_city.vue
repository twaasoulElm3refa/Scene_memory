<template>
  <AdminLayout>
    <div class="add-city-container">
      <!-- Breadcrumb -->
      <nav class="breadcrumb">
        <router-link to="/admin">Dashboard</router-link>
        <span class="separator">›</span>
        <router-link to="/admin/cities">Cities</router-link>
        <span class="separator">›</span>
        <span class="current">Add New City</span>
      </nav>

      <!-- Page Header -->
      <div class="page-header">
        <h1>Add New City</h1>
        <p class="subtitle">Create a new entry for your global travel directory.</p>
      </div>

      <!-- Form Card -->
      <div class="form-card">
        <form @submit.prevent="handleSubmit">
          <div class="form-row">
            <!-- City Name -->
            <div class="form-group">
              <label for="cityName">City Name</label>
              <input
                id="cityName"
                v-model="formData.name"
                type="text"
                placeholder="e.g. Barcelona"
                required
              />
              <span v-if="errors.name" class="error">{{ errors.name }}</span>
            </div>

            <!-- Country -->
            <div class="form-group">
              <label for="country">Country</label>
              <div class="select-wrapper">
                <input
                  id="country"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Select a country"
                  @input="handleSearch"
                  @focus="showDropdown = true"
                  autocomplete="off"
                  required
                />
                <svg
                  class="dropdown-icon"
                  width="20"
                  height="20"
                  viewBox="0 0 20 20"
                  fill="none"
                >
                  <path
                    d="M5 7.5L10 12.5L15 7.5"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>

                <!-- Dropdown -->
                <div v-if="showDropdown && filteredCountries.length > 0" class="dropdown">
                  <div
                    v-for="country in filteredCountries"
                    :key="country.id"
                    class="dropdown-item"
                    @click="selectCountry(country)"
                  >
                    {{ country.name }}
                  </div>
                </div>

                <div
                  v-if="showDropdown && searchQuery && filteredCountries.length === 0"
                  class="dropdown"
                >
                  <div class="dropdown-item no-results">No countries found</div>
                </div>
              </div>
              <span v-if="errors.country_id" class="error">{{ errors.country_id }}</span>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="form-actions">
            <button type="button" class="btn-cancel" @click="handleCancel">Cancel</button>
            <button type="submit" class="btn-submit" :disabled="loading">
              {{ loading ? "Creating..." : "Create City" }}
            </button>
          </div>
        </form>
      </div>

      <!-- Info Cards -->
      <div class="info-cards">
        <div class="info-card">
          <div class="info-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" stroke="#3B82F6" stroke-width="2" />
              <path
                d="M12 8V12L14.5 14.5"
                stroke="#3B82F6"
                stroke-width="2"
                stroke-linecap="round"
              />
            </svg>
          </div>
          <div class="info-content">
            <h4>Listing Status</h4>
            <p>
              New cities are added as 'Draft' by default until reviewed by an
              administrator.
            </p>
          </div>
        </div>

        <div class="info-card">
          <div class="info-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
              <path
                d="M13 2L3 14H12L11 22L21 10H12L13 2Z"
                stroke="#3B82F6"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </div>
          <div class="info-content">
            <h4>SEO Optimized</h4>
            <p>
              Descriptions are automatically indexed for better search visibility on Scene
              Memory.
            </p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { cityService } from "@/services/admin/cities/cityService";

const router = useRouter();
const formData = ref({
  name: "",
  country_id: null as number | null,
});

const searchQuery = ref("");
const showDropdown = ref(false);
const countries = ref<Array<{ id: number; name: string }>>([]);
const loading = ref(false);
const errors = ref<Record<string, string>>({});

const filteredCountries = computed(() => {
  if (!searchQuery.value) {
    return countries.value;
  }
  return countries.value.filter((country) =>
    country.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});

const fetchCountries = async () => {
  try {
    const response = await cityService.getAllCountries();
    if (response.data?.status === "success") {
      countries.value = response.data.data.map((country) => ({
        id: country.id || country.country_id,
        name: country.name || country.translation?.name,
      }));
      console.log("Countries loaded:", countries.value);
    }
  } catch (error) {
    console.error("Error fetching countries:", error);
  }
};

const handleSearch = () => {
  showDropdown.value = true;
  formData.value.country_id = null;
};

const selectCountry = (country: { id: number; name: string }) => {
  formData.value.country_id = country.id;
  searchQuery.value = country.name;
  showDropdown.value = false;
};

const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement;
  if (!target.closest(".select-wrapper")) {
    showDropdown.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};

  if (!formData.value.name?.trim()) {
    errors.value.name = "City name is required";
    return;
  }

  if (!formData.value.country_id) {
    errors.value.country_id = "Please select a country";
    return;
  }

  loading.value = true;

  try {
    const payload = {
      name: formData.value.name.trim(),
      country_id: formData.value.country_id,
    };

    const response = await cityService.createCity(payload);
    if (response.data?.status === "success") {
      router.push("/admin/cities");
    } else {
      if (response.data?.errors) {
        errors.value = response.data.errors;
      } else {
        alert(response.data?.message || "Failed to create city");
      }
    }
  } catch (error: any) {
    console.error("Error creating city:", error);
    alert(error.response?.data?.message || "An error occurred while creating the city");
  } finally {
    loading.value = false;
  }
};

const handleCancel = () => {
  router.push("/admin/cities");
};

onMounted(() => {
  fetchCountries();
  document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
});
</script>

<style scoped>
.add-city-container {
  max-width: 960px;
  margin: 0 auto;
  padding: 24px;
}

/* Breadcrumb */
.breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 24px;
}

.breadcrumb a {
  color: #6b7280;
  text-decoration: none;
  transition: color 0.2s;
}

.breadcrumb a:hover {
  color: #3b82f6;
}

.breadcrumb .separator {
  color: #d1d5db;
}

.breadcrumb .current {
  color: #111827;
}

/* Page Header */
.page-header {
  margin-bottom: 32px;
}

.page-header h1 {
  font-size: 32px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 8px 0;
}

.subtitle {
  font-size: 16px;
  color: #6b7280;
  margin: 0;
}

/* Form Card */
.form-card {
  background: white;
  border-radius: 12px;
  padding: 32px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 24px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  margin-bottom: 24px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 8px;
}

.form-group input {
  padding: 10px 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.2s;
  background: #f9fafb;
}

.form-group input:focus {
  outline: none;
  border-color: #3b82f6;
  background: white;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-group input::placeholder {
  color: #9ca3af;
}

.error {
  color: #ef4444;
  font-size: 12px;
  margin-top: 4px;
}

/* Select Wrapper */
.select-wrapper {
  position: relative;
}

.dropdown-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  pointer-events: none;
}

.dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  max-height: 240px;
  overflow-y: auto;
  z-index: 10;
}

.dropdown-item {
  padding: 10px 14px;
  cursor: pointer;
  transition: background 0.2s;
  font-size: 14px;
  color: #374151;
}

.dropdown-item:hover {
  background: #f3f4f6;
}

.dropdown-item.no-results {
  cursor: default;
  color: #9ca3af;
}

.dropdown-item.no-results:hover {
  background: white;
}

/* Form Actions */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
}

.btn-cancel,
.btn-submit {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-cancel {
  background: white;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn-cancel:hover {
  background: #f9fafb;
}

.btn-submit {
  background: #3b82f6;
  color: white;
}

.btn-submit:hover:not(:disabled) {
  background: #2563eb;
}

.btn-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Info Cards */
.info-cards {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.info-card {
  display: flex;
  gap: 16px;
  padding: 20px;
  background: #f0f9ff;
  border-radius: 12px;
}

.info-icon {
  flex-shrink: 0;
}

.info-content h4 {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.info-content p {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
  line-height: 1.5;
}

/* Responsive */
@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }

  .info-cards {
    grid-template-columns: 1fr;
  }

  .form-card {
    padding: 24px;
  }
}
</style>
