<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-800">Purchases Management</h1>
            <p class="text-sm text-gray-500 mt-1">View and manage all checkout transactions</p>
          </div>
          <div class="flex items-center gap-3">
            <!-- Type Filter Dropdown -->
            <div class="relative">
              <select
                v-model="selectedType"
                @change="changeType"
                class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-10 text-sm font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer"
              >
                <option value="checkout">Checkout</option>
                <option value="wallet">Wallet</option>
                <option value="subscription">Subscription</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
            <!-- Refresh Button -->
            <button
              @click="fetchPurchases"
              :disabled="loading"
              class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              Refresh
            </button>
          </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs text-indigo-600 uppercase tracking-wide">Total Purchases</p>
              <p class="text-2xl font-bold text-indigo-900">{{ pagination.total || 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-full bg-indigo-200 flex items-center justify-center">
              <svg class="w-5 h-5 text-indigo-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
              </svg>
            </div>
          </div>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs text-green-600 uppercase tracking-wide">Completed</p>
              <p class="text-2xl font-bold text-green-900">{{ completedCount }}</p>
            </div>
            <div class="w-10 h-10 rounded-full bg-green-200 flex items-center justify-center">
              <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </div>
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs text-blue-600 uppercase tracking-wide">Total Amount</p>
              <p class="text-2xl font-bold text-blue-900">${{ totalAmount }}</p>
            </div>
            <div class="w-10 h-10 rounded-full bg-blue-200 flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </div>
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs text-purple-600 uppercase tracking-wide">Current Page</p>
              <p class="text-2xl font-bold text-purple-900">{{ pagination.current_page || 1 }}</p>
            </div>
            <div class="w-10 h-10 rounded-full bg-purple-200 flex items-center justify-center">
              <svg class="w-5 h-5 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="bg-white rounded-xl shadow-sm border border-gray-100 p-12">
        <div class="flex flex-col items-center justify-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
          <p class="mt-4 text-gray-500">Loading purchases...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-8 text-center">
        <svg class="w-12 h-12 text-red-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="text-lg font-semibold text-red-800">Failed to load purchases</h3>
        <p class="text-red-600 mt-1">{{ error }}</p>
        <button
          @click="fetchPurchases"
          class="mt-4 px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors"
        >
          Try Again
        </button>
      </div>

      <!-- Purchases Table -->
      <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  ID
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  User
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Amount
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Type
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Paid At
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Mail
                </th>
                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              <tr v-for="purchase in purchases" :key="purchase.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="text-sm font-mono font-medium text-gray-900">#{{ purchase.id }}</span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex flex-col">
                    <span class="text-sm font-medium text-gray-900">{{ purchase.user?.name || '—' }}</span>
                    <span class="text-xs text-gray-500">{{ purchase.user?.email || '—' }}</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="text-sm font-semibold text-gray-900">${{ parseFloat(purchase.amount).toFixed(2) }}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    :class="{
                      'bg-green-100 text-green-800': purchase.status === 'completed',
                      'bg-yellow-100 text-yellow-800': purchase.status === 'pending',
                      'bg-red-100 text-red-800': purchase.status === 'failed',
                      'bg-gray-100 text-gray-800': purchase.status !== 'completed' && purchase.status !== 'pending' && purchase.status !== 'failed',
                    }"
                    class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full capitalize"
                  >
                    {{ purchase.status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="text-sm text-gray-600 capitalize">{{ purchase.type }}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="text-sm text-gray-600">{{ formatDate(purchase.paid_at) }}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    :class="purchase.mail_sent ? 'text-green-600' : 'text-orange-500'"
                    class="text-sm font-medium"
                  >
                    {{ purchase.mail_sent ? 'Sent' : 'Pending' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <router-link
                    :to="`/admin/purchases/${purchase.id}`"
                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-50 text-indigo-700 text-sm font-medium rounded-lg hover:bg-indigo-100 transition-all"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    Show
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="purchases.length === 0" class="text-center py-12">
          <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
          </svg>
          <p class="text-gray-500">No purchases found for this type.</p>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-gray-200 bg-gray-50">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="text-sm text-gray-600">
              Showing <span class="font-medium">{{ pagination.from || 0 }}</span> to
              <span class="font-medium">{{ pagination.to || 0 }}</span> of
              <span class="font-medium">{{ pagination.total || 0 }}</span> results
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="goToPage(pagination.current_page - 1)"
                :disabled="!pagination.prev_page_url"
                class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
              >
                Previous
              </button>
              <div class="flex gap-1">
                <button
                  v-for="page in visiblePages"
                  :key="page"
                  @click="goToPage(page)"
                  :class="{
                    'bg-indigo-600 text-white border-indigo-600': pagination.current_page === page,
                    'bg-white text-gray-700 border-gray-300 hover:bg-gray-50': pagination.current_page !== page,
                  }"
                  class="w-9 h-9 text-sm font-medium rounded-lg border transition-all"
                >
                  {{ page }}
                </button>
              </div>
              <button
                @click="goToPage(pagination.current_page + 1)"
                :disabled="!pagination.next_page_url"
                class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { PurchaseService } from "../../../services/PurchaseService/PurchaseService";

const route = useRoute();
const router = useRouter();
const purchases = ref([]);
const loading = ref(true);
const error = ref(null);
const selectedType = ref(route.params.type || "checkout");

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0,
  prev_page_url: null,
  next_page_url: null,
});

// Computed properties for stats
const completedCount = computed(() => {
  return purchases.value.filter(p => p.status === "completed").length;
});

const totalAmount = computed(() => {
  const sum = purchases.value.reduce((acc, p) => acc + parseFloat(p.amount || 0), 0);
  return sum.toFixed(2);
});

// Visible page numbers for pagination
const visiblePages = computed(() => {
  const current = pagination.value.current_page;
  const last = pagination.value.last_page;
  const delta = 2;
  const range = [];

  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    range.push(i);
  }

  const pages = [1];
  if (range[0] > 2) pages.push("...");
  pages.push(...range);
  if (range[range.length - 1] < last - 1) pages.push("...");
  if (last > 1) pages.push(last);

  return pages.filter(p => p !== "...");
});

const formatDate = (dateString) => {
  if (!dateString) return "—";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const fetchPurchases = async (page = 1) => {
  loading.value = true;
  error.value = null;

  try {
    const response = await PurchaseService.getByType(selectedType.value, page);

    if (response.data.data) {
      purchases.value = response.data.data.data || [];
      pagination.value = {
        current_page: response.data.data.current_page || 1,
        last_page: response.data.data.last_page || 1,
        per_page: response.data.data.per_page || 10,
        total: response.data.data.total || 0,
        from: response.data.data.from || 0,
        to: response.data.data.to || 0,
        prev_page_url: response.data.data.prev_page_url,
        next_page_url: response.data.data.next_page_url,
      };
    } else {
      throw new Error("Invalid response format");
    }
  } catch (err) {
    console.error("Error fetching purchases:", err);
    error.value = err.response?.data?.message || err.message || "Failed to load purchases";
    purchases.value = [];
  } finally {
    loading.value = false;
  }
};

const goToPage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page && page !== pagination.value.current_page) {
    fetchPurchases(page);
  }
};

const changeType = () => {
  router.push(`/admin/purchases/${selectedType.value}/type`);
  fetchPurchases(1);
};

// Watch for route param changes
watch(
  () => route.params.type,
  (newType) => {
    if (newType && newType !== selectedType.value) {
      selectedType.value = newType;
      fetchPurchases(1);
    }
  }
);

onMounted(() => {
  if (route.params.type) {
    selectedType.value = route.params.type;
  }
  fetchPurchases();
});
</script>

<style scoped>
/* Optional custom styles */
</style>
