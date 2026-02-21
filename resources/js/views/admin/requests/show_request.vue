<template>
  <AdminLayout>
    <div class="min-h-screen bg-gray-50 p-6 md:p-8 font-sans">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center min-h-[60vh]">
        <div
          class="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"
        ></div>
      </div>

      <!-- Error -->
      <div
        v-else-if="error"
        class="max-w-4xl mx-auto bg-white rounded-2xl shadow border border-red-100 p-8 text-center text-red-700"
      >
        <p class="text-lg font-medium">{{ error }}</p>
      </div>

      <!-- Main Content -->
      <div v-else-if="requestData" class="max-w-5xl mx-auto space-y-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <button
            @click="$router.back()"
            class="flex items-center gap-2 text-gray-600 hover:text-indigo-600 transition-colors group font-medium"
          >
            <svg
              class="w-5 h-5 group-hover:-translate-x-1 transition-transform"
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
            Back
          </button>

          <span
            :class="
              statusClasses[requestData.status] ||
              'bg-gray-100 text-gray-700 border-gray-200'
            "
            class="px-5 py-1.5 rounded-full text-sm font-semibold uppercase tracking-wide border shadow-sm"
          >
            {{ requestData.status || "pending" }}
          </span>
        </div>

        <!-- Hero Image -->
        <div
          class="rounded-3xl overflow-hidden shadow-xl border border-gray-200 bg-white"
        >
          <img
            :src="
              requestData.image
                ? `/storage/${requestData.image}`
                : 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1200&auto=format&fit=crop&q=80'
            "
            :alt="requestData.title || 'Event image'"
            class="w-full h-80 md:h-[28rem] object-cover transition-transform duration-700 hover:scale-105"
            @error="handleImageError"
          />
        </div>

        <!-- Main Info Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">
          <!-- Event Details -->
          <div class="bg-white rounded-2xl shadow border border-gray-100 p-7 space-y-6">
            <h2 class="text-2xl font-bold text-gray-900 pb-3 border-b border-gray-100">
              Event Request
            </h2>

            <div class="space-y-6">
              <div>
                <p class="text-sm text-gray-500 mb-1.5">Title</p>
                <p class="text-xl font-semibold text-gray-900">
                  {{ requestData.title || "—" }}
                </p>
              </div>

              <div>
                <p class="text-sm text-gray-500 mb-1.5">Description</p>
                <p class="text-gray-700 leading-relaxed whitespace-pre-line">
                  {{ requestData.description || "No description provided." }}
                </p>
              </div>

              <div class="grid grid-cols-2 sm:grid-cols-3 gap-6">
                <div>
                  <p class="text-sm text-gray-500 mb-1.5">Start Date</p>
                  <p class="text-gray-900 font-medium">
                    {{ requestData.start_date || "—" }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 mb-1.5">End Date</p>
                  <p class="text-gray-900 font-medium">
                    {{ requestData.end_date || "—" }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 mb-1.5">Time</p>
                  <p class="text-gray-900 font-medium">{{ requestData.time || "—" }}</p>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-6">
                <div>
                  <p class="text-sm text-gray-500 mb-1.5">City</p>
                  <p class="text-gray-900 font-medium">
                    {{ requestData.city?.name || "—" }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 mb-1.5">Category</p>
                  <p class="text-gray-900 font-medium">
                    {{ requestData.sub_categorey?.name || "—" }}
                  </p>
                </div>
              </div>

              <div>
                <p class="text-sm text-gray-500 mb-1.5">Active</p>
                <span
                  :class="
                    requestData.is_active === '1' || requestData.is_active === 1
                      ? 'bg-green-50 text-green-700 border-green-200'
                      : 'bg-red-50 text-red-700 border-red-200'
                  "
                  class="inline-flex px-3.5 py-1 rounded-full text-sm font-medium border"
                >
                  {{
                    requestData.is_active === "1" || requestData.is_active === 1
                      ? "Active"
                      : "Inactive"
                  }}
                </span>
              </div>
            </div>
          </div>

          <!-- Request Metadata -->
          <div class="bg-white rounded-2xl shadow border border-gray-100 p-7 space-y-6">
            <h2 class="text-2xl font-bold text-gray-900 pb-3 border-b border-gray-100">
              Request Details
            </h2>

            <div class="space-y-6">
              <div>
                <p class="text-sm text-gray-500 mb-1.5">Requested by</p>
                <p class="text-gray-900 font-medium">
                  {{ requestData.user?.name || "—" }}
                </p>
              </div>

              <div>
                <p class="text-sm text-gray-500 mb-1.5">Created At</p>
                <p class="text-gray-900 font-medium">
                  {{ formatDate(requestData.created_at) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons flex flex-col sm:flex-row gap-4 pt-8">
          <button
            @click="updateStatus('approved')"
            :disabled="requestData.status === 'approved' || actionLoading"
            class="bg-green-600 hover:bg-green-700 disabled:bg-green-400 disabled:cursor-not-allowed text-white font-semibold py-3.5 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
          >
            Approve Request
          </button>

          <button
            @click="updateStatus('rejected')"
            :disabled="requestData.status === 'rejected' || actionLoading"
            class="bg-red-600 hover:bg-red-700 disabled:bg-red-400 disabled:cursor-not-allowed text-white font-semibold py-3.5 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
          >
            Reject Request
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import axios from "axios";
import AdminLayout from "../../../layouts/AdminLayout.vue";

const route = useRoute();
const requestData = ref(null);
const loading = ref(true);
const error = ref(null);
const actionLoading = ref(false);

const baseUrl = import.meta.env.VITE_API_BASE_URL || "/v1";

const statusClasses = {
  pending: "bg-amber-50 text-amber-800 border-amber-200",
  approved: "bg-green-50 text-green-800 border-green-200",
  rejected: "bg-red-50 text-red-800 border-red-200",
};

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleString("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const handleImageError = (e) => {
  e.target.src = "https://placehold.co/900x400/1f2937/9ca3af?text=No+Image+Available";
};

const fetchRequest = async () => {
  try {
    loading.value = true;
    const { data } = await axios.get(`${baseUrl}/requests/${route.params.id}`, {
      headers: { Authorization: `Bearer ${localStorage.getItem("auth_token")}` },
    });
    requestData.value = data.data;
  } catch (err) {
    error.value = err.response?.data?.message || "Failed to load request details.";
  } finally {
    loading.value = false;
  }
};

const updateStatus = async (status) => {
  if (!confirm(`Are you sure you want to ${status} this request?`)) return;

  try {
    actionLoading.value = true;
    await axios.patch(
      `${baseUrl}/requests/${route.params.id}`,
      { status },
      { headers: { Authorization: `Bearer ${localStorage.getItem("auth_token")}` } }
    );
    requestData.value.status = status;
  } catch (err) {
    alert(err.response?.data?.message || "Failed to update status.");
  } finally {
    actionLoading.value = false;
  }
};

onMounted(fetchRequest);
</script>

<style scoped>
.action-buttons {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
}

.action-buttons button {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 0.5rem;
  width: 25%;
  padding: 0.6rem 1.2rem;
  font-size: 0.9rem;
  border-radius: 9999px;
}

@media (max-width: 640px) {
  .action-buttons {
    flex-direction: column;
  }

  .action-buttons button {
    width: 100%;
  }
}
</style>
