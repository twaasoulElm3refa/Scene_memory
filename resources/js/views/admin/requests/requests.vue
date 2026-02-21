<template>
  <AdminLayout>
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">
        Event Creation Requests
      </h1>
      <p class="mt-1 text-sm text-gray-500">
        Review and manage user-submitted event memories. Every approval helps preserve
        history and build our global community collection.
      </p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
      <div
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4"
      >
        <div
          class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
            />
          </svg>
        </div>
        <div>
          <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">
            Total Requests
          </p>
          <p class="text-2xl font-bold text-gray-900 leading-tight">
            {{ counts.total ?? pagination?.total ?? "—" }}
          </p>
        </div>
        <span class="ml-auto text-xs font-semibold text-green-500">+12%</span>
      </div>

      <div
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4"
      >
        <div
          class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>
        <div>
          <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">
            Pending Review
          </p>
          <p class="text-2xl font-bold text-gray-900 leading-tight">
            {{ counts.pending ?? "—" }}
          </p>
        </div>
        <span class="ml-auto text-xs font-semibold text-green-500">+5%</span>
      </div>

      <div
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4"
      >
        <div
          class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center text-green-500"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>
        <div>
          <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">
            Approved
          </p>
          <p class="text-2xl font-bold text-gray-900 leading-tight">
            {{ counts.approved ?? "—" }}
          </p>
        </div>
        <span class="ml-auto text-xs font-semibold text-red-400">-2%</span>
      </div>

      <div
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4"
      >
        <div
          class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center text-red-400"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>
        <div>
          <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">
            Rejected
          </p>
          <p class="text-2xl font-bold text-gray-900 leading-tight">
            {{ counts.rejected ?? "—" }}
          </p>
        </div>
        <span class="ml-auto text-xs font-semibold text-gray-400">0%</span>
      </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <!-- Toolbar -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <div class="flex items-center gap-3">
          <!-- Status Filter -->
          <div class="relative">
            <select
              v-model="statusFilter"
              @change="fetchRequests(1)"
              class="appearance-none pl-9 pr-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
            >
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
            <svg
              class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"
              />
            </svg>
          </div>

          <!-- Date Filter -->
          <div class="relative">
            <select
              v-model="dateFilter"
              @change="fetchRequests(1)"
              class="appearance-none pl-9 pr-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
            >
              <option value="30">Last 30 Days</option>
              <option value="7">Last 7 Days</option>
              <option value="90">Last 90 Days</option>
              <option value="all">All Time</option>
            </select>
            <svg
              class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
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
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-20">
        <div
          class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"
        ></div>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50/60">
              <th
                class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-3 w-16"
              >
                ID
              </th>
              <th
                class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 py-3"
              >
                Event Title
              </th>
              <th
                class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 py-3"
              >
                Status
              </th>
              <th
                class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 py-3"
              >
                Created At
              </th>
              <th
                class="text-center text-xs font-semibold text-gray-400 uppercase tracking-wider px-6 py-3"
              >
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr
              v-for="request in requests"
              :key="request.id"
              class="hover:bg-gray-50/50 transition-colors group"
            >
              <td class="px-6 py-4 text-sm font-semibold text-gray-400">
                #{{ request.id }}
              </td>
              <td class="px-4 py-4">
                <div class="font-semibold text-gray-900 text-sm">
                  {{ request.events?.title ?? "—" }}
                </div>
                <div class="text-xs text-gray-400 mt-0.5">
                  Event ID: {{ request.event_id }}
                </div>
              </td>
              <td class="px-4 py-4">
                <span
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                  :class="{
                    'bg-amber-50 text-amber-600': request.status === 'pending',
                    'bg-green-50 text-green-600': request.status === 'approved',
                    'bg-red-50 text-red-500': request.status === 'rejected',
                  }"
                >
                  <span
                    class="w-1.5 h-1.5 rounded-full"
                    :class="{
                      'bg-amber-400': request.status === 'pending',
                      'bg-green-500': request.status === 'approved',
                      'bg-red-400': request.status === 'rejected',
                    }"
                  ></span>
                  {{ capitalize(request.status) }}
                </span>
              </td>
              <td class="px-4 py-4 text-sm text-gray-500">
                {{ formatDate(request.created_at) }}
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-row justify-center items-center gap-2">
                  <!-- View -->
                  <router-link
                    :to="`/admin/requests/${request.id}`"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all"
                  >
                    <svg
                      class="w-6 h-6"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
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
                  </router-link>

                  <!-- Delete -->
                  <button
                    @click="deleteRequest(request)"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-red-500 hover:text-white hover:bg-red-600 transition-all"
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
                        d="M6 18L18 6M6 6l12 12"
                      />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-if="requests.length === 0 && !loading">
              <td colspan="5" class="text-center py-16 text-gray-400 text-sm">
                <svg
                  class="w-6 h-6 mx-auto mb-3 text-gray-200"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                  />
                </svg>
                No requests found
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100">
        <p class="text-sm text-gray-400">
          Showing {{ pagination?.from ?? 0 }} to {{ pagination?.to ?? 0 }} of
          {{ pagination?.total ?? 0 }} entries
        </p>

        <div class="flex items-center gap-1">
          <!-- Prev -->
          <button
            @click="fetchRequests(currentPage - 1)"
            :disabled="!pagination?.prev_page_url"
            class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 19l-7-7 7-7"
              />
            </svg>
          </button>

          <!-- Page Numbers -->
          <template v-for="link in pageLinks" :key="link.label">
            <button
              v-if="link.page"
              @click="fetchRequests(link.page)"
              class="w-8 h-8 rounded-lg text-sm font-medium transition-all"
              :class="
                link.active
                  ? 'bg-blue-600 text-white shadow-sm'
                  : 'text-gray-500 hover:bg-gray-100'
              "
            >
              {{ link.page }}
            </button>
            <span v-else-if="link.label === '...'" class="px-1 text-gray-400 text-sm"
              >...</span
            >
          </template>

          <!-- Next -->
          <button
            @click="fetchRequests(currentPage + 1)"
            :disabled="!pagination?.next_page_url"
            class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { requestsService } from "../../../services/admin/requests/requestsService.js";

const requests = ref([]);
const pagination = ref(null);
const counts = ref({
  total: null,
  pending: null,
  approved: null,
  rejected: null,
});
const loading = ref(false);
const currentPage = ref(1);
const statusFilter = ref("");
const dateFilter = ref("30");

const pageLinks = computed(() => {
  if (!pagination.value) return [];
  const total = pagination.value.last_page;
  const current = currentPage.value;
  const pages = [];
  pages.push({ page: 1, label: "1", active: 1 === current });

  if (current > 3) {
    pages.push({ page: null, label: "..." });
  }

  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    pages.push({ page: i, label: String(i), active: i === current });
  }

  if (current < total - 2) {
    pages.push({ page: null, label: "..." });
  }

  if (total > 1) {
    pages.push({
      page: total,
      label: String(total),
      active: total === current,
    });
  }

  return pages;
});

async function fetchRequests(page = 1) {
  if (page < 1) return;
  if (pagination.value && page > pagination.value.last_page) return;

  loading.value = true;
  currentPage.value = page;

  try {
    const params = {};
    if (statusFilter.value) params.status = statusFilter.value;
    if (dateFilter.value && dateFilter.value !== "all") {
      params.days = dateFilter.value;
    }

    const res = await requestsService.getAllPaginated(page, params);
    const responseData = res.data;

    const paginatedData = responseData.data?.requests || responseData.data;
    requests.value = (paginatedData.data || []).map((r) => ({
      ...r,
      _loading: false,
    }));
    pagination.value = paginatedData;
    if (responseData.data?.PendingCounts !== undefined) {
      counts.value = {
        total: responseData.data.total ?? paginatedData.total,
        pending: responseData.data.PendingCounts ?? 0,
        approved: responseData.data.approvedCounts ?? 0,
        rejected: responseData.data.rejectedCounts ?? 0,
      };
    }
  } catch (err) {
    console.error("Failed to fetch requests:", err);
  } finally {
    loading.value = false;
  }
}
const deleteRequest = async (request) => {
  if (!confirm(`Are you sure you want to delete request #${request.id}?`)) return;

  try {
    request._loading = true;
    await axios.delete(`/v1/requests/${request.id}`, {
      headers: { Authorization: `Bearer ${localStorage.getItem("auth_token")}` },
    });
    requests.value = requests.value.filter((r) => r.id !== request.id);
    alert("Request deleted successfully!");
    window.location.reload();
  } catch (err) {
    alert(err.response?.data?.message || "Failed to delete request.");
  } finally {
    request._loading = false;
  }
};
function formatDate(dateStr) {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function capitalize(str) {
  if (!str) return "";
  return str.charAt(0).toUpperCase() + str.slice(1);
}

onMounted(() => {
  fetchRequests(1);
});
</script>
