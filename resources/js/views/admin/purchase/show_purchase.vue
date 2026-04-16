<template>
  <AdminLayout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <router-link
            to="/admin/purchases"
            class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all shadow-sm"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
          </router-link>

          <div>
            <h1 class="text-2xl font-bold text-gray-800">Purchase Details</h1>
            <p class="text-sm text-gray-500 mt-0.5">ID: #{{ purchase?.id || "Loading..." }}</p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <span
            :class="{
              'bg-green-100 text-green-800': purchase?.status === 'completed',
              'bg-yellow-100 text-yellow-800': purchase?.status === 'pending',
              'bg-red-100 text-red-800': purchase?.status === 'failed',
              'bg-gray-100 text-gray-800': !purchase?.status,
            }"
            class="px-3 py-1 rounded-full text-xs font-semibold capitalize"
          >
            {{ purchase?.status || "Unknown" }}
          </span>

          <button
            @click="goToEditPage"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded hover:bg-indigo-800 transition-all"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit Purchase
          </button>
          <button
            @click="deletePurchase"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-red-500 rounded hover:bg-red-700 transition-all"
          >
            <!-- <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg> -->
            Delete Purchase
          </button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <div class="flex flex-col items-center justify-center py-12">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
          <p class="mt-4 text-gray-500">Loading purchase details...</p>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
        <svg class="w-12 h-12 text-red-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="text-lg font-semibold text-red-800">Failed to load purchase</h3>
        <p class="text-red-600 mt-1">{{ error }}</p>
        <button
          @click="fetchPurchase"
          class="mt-4 px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors"
        >
          Try Again
        </button>
      </div>

      <!-- Main Content -->
      <div v-else-if="purchase" class="space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Amount</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">
                  {{ purchase.currency }} {{ parseFloat(purchase.amount || 0).toFixed(2) }}
                </p>
              </div>
              <div class="w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Type</p>
                <p class="text-xl font-semibold text-gray-800 mt-1 capitalize">{{ purchase.type || "—" }}</p>
              </div>
              <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Wallet Credited</p>
                <p class="text-xl font-semibold" :class="purchase.wallet_credited ? 'text-green-600' : 'text-red-500'">
                  {{ purchase.wallet_credited ? "Yes" : "No" }}
                </p>
              </div>
              <div class="w-10 h-10 rounded-full" :class="purchase.wallet_credited ? 'bg-green-50' : 'bg-red-50'">
                <svg class="w-5 h-5 m-2.5" :class="purchase.wallet_credited ? 'text-green-600' : 'text-red-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M6 14h6m-6 4h12M5 4h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z" />
                </svg>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Paid At</p>
                <p class="text-sm font-medium text-gray-800 mt-1">{{ formatDate(purchase.paid_at) }}</p>
              </div>
              <div class="w-10 h-10 rounded-full bg-purple-50 flex items-center justify-center">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Left Column -->
          <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/40">
                <h2 class="text-base font-semibold text-gray-800">Transaction Details</h2>
              </div>
              <div class="p-5 space-y-3">
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Transaction ID</span>
                  <span class="text-sm font-mono text-gray-800">{{ purchase.transaction_id || "—" }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">PayPal Order ID</span>
                  <span class="text-sm font-mono text-gray-800">{{ purchase.paypal_order_id || "—" }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Idempotency Key</span>
                  <span class="text-sm font-mono text-gray-800">{{ purchase.idempotency_key || "—" }}</span>
                </div>
                <div class="flex justify-between py-2">
                  <span class="text-sm text-gray-500">Description</span>
                  <span class="text-sm text-gray-800">{{ purchase.description || "—" }}</span>
                </div>
              </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/40">
                <h2 class="text-base font-semibold text-gray-800">Payment Information</h2>
              </div>
              <div class="p-5 space-y-3">
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Payment Method</span>
                  <span class="text-sm text-gray-800 capitalize">{{ purchase.payment_method || "—" }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Payment Status</span>
                  <span class="text-sm text-gray-800 capitalize">{{ purchase.payment_status || "—" }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Payer Email</span>
                  <span class="text-sm text-gray-800">{{ purchase.payer_email || "—" }}</span>
                </div>
                <div class="flex justify-between py-2">
                  <span class="text-sm text-gray-500">Gateway Response</span>
                  <span class="text-sm text-gray-800">{{ purchase.gateway_response || "—" }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column -->
          <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/40">
                <h2 class="text-base font-semibold text-gray-800">User Information</h2>
              </div>
              <div class="p-5 space-y-3">
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Name</span>
                  <span class="text-sm font-medium text-gray-800">{{ purchase.user?.name || "—" }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Email</span>
                  <span class="text-sm text-gray-800">{{ purchase.user?.email || "—" }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Phone</span>
                  <span class="text-sm text-gray-800">{{ purchase.user?.phone || "—" }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Country</span>
                  <span class="text-sm text-gray-800">{{ purchase.user?.country || "—" }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Position</span>
                  <span class="text-sm text-gray-800">{{ purchase.user?.position || "—" }}</span>
                </div>
                <div class="flex justify-between py-2">
                  <span class="text-sm text-gray-500">Points Balance</span>
                  <span class="text-sm font-semibold text-indigo-600">{{ purchase.user?.points?.toLocaleString() || "0" }}</span>
                </div>
              </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/40">
                <h2 class="text-base font-semibold text-gray-800">Items ({{ purchase.items?.length || 0 }})</h2>
              </div>
              <div class="p-0">
                <div v-if="!purchase.items?.length" class="p-8 text-center text-gray-500">
                  No items associated with this purchase.
                </div>
                <div v-else class="divide-y divide-gray-100">
                  <div v-for="(item, idx) in purchase.items" :key="idx" class="p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex justify-between items-start">
                      <div>
                        <p class="font-medium text-gray-800">{{ item.name || `Item ${idx + 1}` }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">ID: {{ item.id || "—" }}</p>
                      </div>
                      <div class="text-right">
                        <p class="font-semibold text-gray-800">{{ purchase.currency }} {{ item.price || "0.00" }}</p>
                        <p class="text-xs text-gray-400">Qty: {{ item.quantity || 1 }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/40">
                <h2 class="text-base font-semibold text-gray-800">Metadata</h2>
              </div>
              <div class="p-5 space-y-3">
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Created At</span>
                  <span class="text-sm text-gray-800">{{ formatDateTime(purchase.created_at) }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Updated At</span>
                  <span class="text-sm text-gray-800">{{ formatDateTime(purchase.updated_at) }}</span>
                </div>
                <div class="flex justify-between py-2">
                  <span class="text-sm text-gray-500">Mail Sent</span>
                  <span class="text-sm" :class="purchase.mail_sent ? 'text-green-600' : 'text-orange-500'">
                    {{ purchase.mail_sent ? "Yes" : "No" }}
                  </span>
                </div>
              </div>
            </div>
          </div>
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

const purchase = ref(null);
const loading = ref(true);
const error = ref(null);

const fetchPurchase = async () => {
  loading.value = true;
  error.value = null;

  try {
    const id = route.params.id;
    const response = await axios.get(`/v1/purchases/show/${id}`);
    purchase.value = response.data.data || response.data;
  } catch (err) {
    console.error("Error fetching purchase:", err);
    error.value = err.response?.data?.message || err.message || "Failed to load purchase details";
  } finally {
    loading.value = false;
  }
};

const goToEditPage = () => {
  const id = route.params.id;
  router.push(`/admin/purchases/edit/${id}`);
};

const deletePurchase = () => {
  const id = route.params.id;
  axios.delete(`/v1/purchases/delete/${id}`).then(() => {
    router.push({ name: "admin-purchases" });
  });
};

const formatDate = (dateString) => {
  if (!dateString) return "—";
  return new Date(dateString).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const formatDateTime = (dateString) => {
  if (!dateString) return "—";
  return new Date(dateString).toLocaleString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

onMounted(() => {
  fetchPurchase();
});
</script>
