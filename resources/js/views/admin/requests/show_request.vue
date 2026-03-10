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
      <div v-else class="max-w-5xl mx-auto space-y-8">
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
              statusClasses[apiData.request.status] ||
              'bg-gray-100 text-gray-700 border-gray-200'
            "
            class="px-5 py-1.5 rounded-full text-sm font-semibold uppercase tracking-wide border shadow-sm"
          >
            {{ apiData.request.status || "pending" }}
          </span>
        </div>
        <!-- Hero Image -->
        <div
          class="rounded-3xl overflow-hidden shadow-xl border border-gray-200 bg-white"
        >
          <img
            :src="
              apiData.event.first_image
                ? `/storage/${apiData.event.first_image.url}`
                : 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1200&auto=format&fit=crop&q=80'
            "
            :alt="apiData.event.title || 'Event image'"
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
                  {{ apiData.event.admin_translation?.title || "—" }}
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-500 mb-1.5">Description</p>
                <p class="text-gray-700 leading-relaxed whitespace-pre-line">
                  {{ apiData.event.admin_translation?.description || "No description provided." }}
                </p>
              </div>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-6">
                <div>
                  <p class="text-sm text-gray-500 mb-1.5">Start Date</p>
                  <p class="text-gray-900 font-medium">
                    {{ apiData.event.start_date || "—" }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 mb-1.5">End Date</p>
                  <p class="text-gray-900 font-medium">
                    {{ apiData.event.end_date || "—" }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 mb-1.5">Time</p>
                  <p class="text-gray-900 font-medium">
                    {{ apiData.event.time || "—" }}
                  </p>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-6">
                <div>
                  <p class="text-sm text-gray-500 mb-1.5">City</p>
                  <p class="text-gray-900 font-medium">
                    {{ apiData.event.city?.name || "—" }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 mb-1.5">Category</p>
                  <p class="text-gray-900 font-medium">
                    {{ apiData.event.sub_categorey?.name || "—" }}
                  </p>
                </div>
              </div>
              <div>
                <p class="text-sm text-gray-500 mb-1.5">Active</p>
                <span
                  :class="
                    apiData.event.is_active === '1' || apiData.event.is_active === 1
                      ? 'bg-green-50 text-green-700 border-green-200'
                      : 'bg-red-50 text-red-700 border-red-200'
                  "
                  class="inline-flex px-3.5 py-1 rounded-full text-sm font-medium border"
                >
                  {{
                    apiData.event.is_active === "1" || apiData.event.is_active === 1
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
                  {{ apiData.event.user?.name || "—" }}
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-500 mb-1.5">Created At</p>
                <p class="text-gray-900 font-medium">
                  {{ formatDate(apiData.event.created_at) }}
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-500 mb-1.5">Request Status</p>
                <p class="text-gray-900 font-medium capitalize">
                  {{ apiData.request.status || "—" }}
                </p>
              </div>
            </div>
          </div>
        </div>
        <!-- Rejection Reason (shown only when rejecting) -->
        <div
          v-if="showRejectReason"
          class="bg-white rounded-2xl shadow border border-red-100 p-7 mt-6"
        >
          <h3 class="text-xl font-bold text-red-700 mb-4">Reason for Rejection</h3>
          <textarea
            v-model="rejectReason"
            rows="4"
            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent resize-y"
            placeholder="Please enter the reason why this request is being rejected..."
          ></textarea>
          <p v-if="rejectReasonError" class="mt-2 text-sm text-red-600">
            {{ rejectReasonError }}
          </p>
        </div>
        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-5 pt-8 justify-center">
          <button
            @click="approveRequest"
            :disabled="actionLoading"
            class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 px-10 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 rounded-full min-w-[220px] disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {{ actionLoading ? "Processing..." : "Approve Request" }}
          </button>
          <button
            v-if="!showRejectReason"
            @click="showRejectReason = true"
            :disabled="actionLoading"
            class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-10 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded-full min-w-[220px] disabled:opacity-60 disabled:cursor-not-allowed"
          >
            Reject Request
          </button>
          <button
            v-else
            @click="declineRequest"
            :disabled="actionLoading || !rejectReason.trim()"
            class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-10 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded-full min-w-[220px] disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {{ actionLoading ? "Processing..." : "Confirm Rejection" }}
          </button>
          <button
            v-if="showRejectReason"
            @click="
              showRejectReason = false;
              rejectReason = '';
              rejectReasonError = '';
            "
            class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2.5 px-10 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 rounded-full min-w-[220px]"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";
import AdminLayout from "../../../layouts/AdminLayout.vue";

const route = useRoute();
const router = useRouter();
const apiData = ref(null);
const loading = ref(true);
const error = ref(null);
const actionLoading = ref(false);
const showRejectReason = ref(false);
const rejectReason = ref("");
const rejectReasonError = ref("");
const baseUrl = "/v1";

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
    apiData.value = {
      request: data.data.request,
      event: data.data.event,
    };
    console.log(apiData.value);
  } catch (err) {
    error.value = err.response?.data?.message || "Failed to load request details.";
  } finally {
    loading.value = false;
  }
};

const approveRequest = async () => {
  if (!confirm("Are you sure you want to APPROVE this event request?")) return;
  try {
    actionLoading.value = true;
    await axios.post(
      `${baseUrl}/requests/approve/${route.params.id}`,
      {},
      { headers: { Authorization: `Bearer ${localStorage.getItem("auth_token")}` } }
    );
    apiData.value.request.status = "approved";
    alert("Request approved successfully!");
    window.location.href = "/admin/requests";
  } catch (err) {
    alert(err.response?.data?.message || "Failed to approve request.");
  } finally {
    actionLoading.value = false;
  }
};

const declineRequest = async () => {
  const reason = (rejectReason.value || "").trim();
  if (!reason) {
    rejectReasonError.value = "Please provide a reason for rejection";
    return;
  }
  if (!confirm("Are you sure you want to REJECT this event request?")) return;
  try {
    actionLoading.value = true;
    await axios.post(
      `${baseUrl}/requests/decline/${route.params.id}`,
      { reason },
      { headers: { Authorization: `Bearer ${localStorage.getItem("auth_token")}` } }
    );
    apiData.value.request.status = "rejected";
    alert("Request rejected successfully!");
    window.location.href = "/admin/requests";
  } catch (err) {
    alert(err.response?.data?.message || "Failed to reject request.");
  } finally {
    actionLoading.value = false;
  }
};

onMounted(fetchRequest);
</script>
