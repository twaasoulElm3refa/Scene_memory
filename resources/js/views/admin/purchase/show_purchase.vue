<template>
  <AdminLayout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header with back button -->
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
            <p class="text-sm text-gray-500 mt-0.5">ID: #{{ purchase?.id || 'Loading...' }}</p>
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
            {{ purchase?.status || 'Unknown' }}
          </span>

          <button
            @click="copyToClipboard"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-all"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            Copy ID
          </button>

          <button
            @click="openEditModal"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700 transition-all"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit
          </button>

          <button
            @click="openDeleteModal"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-red-600 rounded hover:bg-red-700 transition-all"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Delete
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
                <p class="text-xl font-semibold text-gray-800 mt-1 capitalize">{{ purchase.type || '—' }}</p>
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
                  {{ purchase.wallet_credited ? 'Yes' : 'No' }}
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
            <!-- Transaction Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/40">
                <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                  <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Transaction Details
                </h2>
              </div>
              <div class="p-5 space-y-3">
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Transaction ID</span>
                  <span class="text-sm font-mono text-gray-800">{{ purchase.transaction_id || '—' }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">PayPal Order ID</span>
                  <span class="text-sm font-mono text-gray-800">{{ purchase.paypal_order_id || '—' }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Idempotency Key</span>
                  <span class="text-sm font-mono text-gray-800">{{ purchase.idempotency_key || '—' }}</span>
                </div>
                <div class="flex justify-between py-2">
                  <span class="text-sm text-gray-500">Description</span>
                  <span class="text-sm text-gray-800">{{ purchase.description || '—' }}</span>
                </div>
              </div>
            </div>

            <!-- Payment Information -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/40">
                <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                  <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                  </svg>
                  Payment Information
                </h2>
              </div>
              <div class="p-5 space-y-3">
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Payment Method</span>
                  <span class="text-sm text-gray-800 capitalize">{{ purchase.payment_method || '—' }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Payment Status</span>
                  <span class="text-sm text-gray-800 capitalize">{{ purchase.payment_status || '—' }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Payer Email</span>
                  <span class="text-sm text-gray-800">{{ purchase.payer_email || '—' }}</span>
                </div>
                <div class="flex justify-between py-2">
                  <span class="text-sm text-gray-500">Gateway Response</span>
                  <span class="text-sm text-gray-800">{{ purchase.gateway_response || '—' }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column -->
          <div class="space-y-6">
            <!-- User Information -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/40">
                <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                  <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  User Information
                </h2>
              </div>
              <div class="p-5 space-y-3">
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Name</span>
                  <span class="text-sm font-medium text-gray-800">{{ purchase.user?.name || '—' }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Email</span>
                  <span class="text-sm text-gray-800">{{ purchase.user?.email || '—' }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Phone</span>
                  <span class="text-sm text-gray-800">{{ purchase.user?.phone || '—' }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Country</span>
                  <span class="text-sm text-gray-800">{{ purchase.user?.country || '—' }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-50">
                  <span class="text-sm text-gray-500">Position</span>
                  <span class="text-sm text-gray-800">{{ purchase.user?.position || '—' }}</span>
                </div>
                <div class="flex justify-between py-2">
                  <span class="text-sm text-gray-500">Points Balance</span>
                  <span class="text-sm font-semibold text-indigo-600">{{ purchase.user?.points?.toLocaleString() || '0' }}</span>
                </div>
              </div>
            </div>

            <!-- Items -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/40">
                <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                  <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                  </svg>
                  Items ({{ purchase.items?.length || 0 }})
                </h2>
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
                        <p class="text-xs text-gray-400 mt-0.5">ID: {{ item.id || '—' }}</p>
                      </div>
                      <div class="text-right">
                        <p class="font-semibold text-gray-800">{{ purchase.currency }} {{ item.price || '0.00' }}</p>
                        <p class="text-xs text-gray-400">Qty: {{ item.quantity || 1 }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Metadata -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/40">
                <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                  <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Metadata
                </h2>
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
                    {{ purchase.mail_sent ? 'Yes' : 'No' }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Raw JSON -->
        <div class="mt-6 border border-gray-200 rounded-xl overflow-hidden bg-gray-50">
          <button
            @click="showRaw = !showRaw"
            class="w-full px-5 py-3 flex justify-between items-center text-left text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors"
          >
            <span class="flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
              </svg>
              Raw Response Data
            </span>
            <svg class="w-5 h-5 transition-transform" :class="{ 'rotate-180': showRaw }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="showRaw" class="border-t border-gray-200 p-4 bg-gray-900 text-gray-100 text-xs font-mono overflow-auto max-h-96">
            <pre>{{ JSON.stringify(purchase, null, 2) }}</pre>
          </div>
        </div>
      </div>

      <!-- ==================== Edit Modal ==================== -->
      <div v-if="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
          <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeEditModal"></div>
          <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

          <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-6 pt-5 pb-4">
              <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Edit Purchase</h3>
                <button @click="closeEditModal" class="text-gray-400 hover:text-gray-500">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <form @submit.prevent="updatePurchase" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                  <select v-model="editForm.status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                    <option value="refunded">Refunded</option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                  <input
                    type="number"
                    step="0.01"
                    v-model="editForm.amount"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                    required
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                  <input
                    type="text"
                    v-model="editForm.currency"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                  <input
                    type="text"
                    v-model="editForm.payment_method"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                  <input
                    type="text"
                    v-model="editForm.payment_status"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Transaction ID</label>
                  <input
                    type="text"
                    v-model="editForm.transaction_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                  <textarea
                    v-model="editForm.description"
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                  ></textarea>
                </div>

                <div class="flex items-center gap-3">
                  <input
                    type="checkbox"
                    v-model="editForm.wallet_credited"
                    class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                  />
                  <label class="text-sm text-gray-700">Wallet Credited</label>
                </div>

                <div class="flex items-center gap-3">
                  <input
                    type="checkbox"
                    v-model="editForm.mail_sent"
                    class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                  />
                  <label class="text-sm text-gray-700">Mail Sent</label>
                </div>

                <div v-if="editError" class="text-red-600 text-sm">{{ editError }}</div>

                <div class="flex gap-3 pt-4">
                  <button
                    type="button"
                    @click="closeEditModal"
                    class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    :disabled="editLoading"
                    class="flex-1 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                  >
                    {{ editLoading ? 'Updating...' : 'Update Purchase' }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- ==================== Delete Modal ==================== -->
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
          <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeDeleteModal"></div>
          <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

          <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-6 pt-5 pb-4">
              <div class="flex items-center justify-center mb-4">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                  <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-center text-gray-900 mb-2">Delete Purchase</h3>
              <p class="text-sm text-center text-gray-500 mb-4">
                Are you sure you want to delete purchase #{{ purchase?.id }}?<br>
                This action cannot be undone.
              </p>

              <div v-if="deleteError" class="text-red-600 text-sm text-center mb-4">{{ deleteError }}</div>

              <div class="flex gap-3">
                <button
                  @click="closeDeleteModal"
                  class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                >
                  Cancel
                </button>
                <button
                  @click="deletePurchase"
                  :disabled="deleteLoading"
                  class="flex-1 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50"
                >
                  {{ deleteLoading ? 'Deleting...' : 'Delete' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Toast -->
      <div v-if="toast.show" class="fixed bottom-4 right-4 z-50 animate-slide-up">
        <div
          :class="{
            'bg-green-500': toast.type === 'success',
            'bg-red-500': toast.type === 'error',
            'bg-blue-500': toast.type === 'info'
          }"
          class="text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3"
        >
          <svg v-if="toast.type === 'success'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <svg v-else-if="toast.type === 'error'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span>{{ toast.message }}</span>
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
const showRaw = ref(false);

// Edit Modal
const showEditModal = ref(false);
const editLoading = ref(false);
const editError = ref(null);
const editForm = ref({
  status: "",
  amount: "",
  currency: "",
  payment_method: "",
  payment_status: "",
  transaction_id: "",
  description: "",
  wallet_credited: false,
  mail_sent: false,
});

// Delete Modal
const showDeleteModal = ref(false);
const deleteLoading = ref(false);
const deleteError = ref(null);

// Toast
const toast = ref({
  show: false,
  message: "",
  type: "success",
});

const showToast = (message, type = "success") => {
  toast.value = { show: true, message, type };
  setTimeout(() => {
    toast.value.show = false;
  }, 3000);
};

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

const openEditModal = () => {
  if (!purchase.value) return;

  editForm.value = {
    status: purchase.value.status || "",
    amount: purchase.value.amount || "",
    currency: purchase.value.currency || "USD",
    payment_method: purchase.value.payment_method || "",
    payment_status: purchase.value.payment_status || "",
    transaction_id: purchase.value.transaction_id || "",
    description: purchase.value.description || "",
    wallet_credited: !!purchase.value.wallet_credited,
    mail_sent: !!purchase.value.mail_sent,
  };

  showEditModal.value = true;
  editError.value = null;
};

const closeEditModal = () => {
  showEditModal.value = false;
  editError.value = null;
  // لا نعيد تعيين editForm كاملاً لتجنب مشاكل الـ reactivity
};

const updatePurchase = async () => {
  editLoading.value = true;
  editError.value = null;

  try {
    const id = route.params.id;
    const response = await axios.post(`/v1/purchases/update/${id}`, editForm.value);

    if (response.data.status === "success") {
      showToast("Purchase updated successfully!", "success");
      closeEditModal();
      await fetchPurchase(); // إعادة تحميل البيانات
    } else {
      throw new Error(response.data.message || "Update failed");
    }
  } catch (err) {
    console.error("Update error:", err);
    editError.value = err.response?.data?.message || err.message || "Failed to update purchase";
    showToast(editError.value, "error");
  } finally {
    editLoading.value = false;
  }
};

const openDeleteModal = () => {
  showDeleteModal.value = true;
  deleteError.value = null;
};

const closeDeleteModal = () => {
  showDeleteModal.value = false;
  deleteError.value = null;
};

const deletePurchase = async () => {
  deleteLoading.value = true;
  deleteError.value = null;

  try {
    const id = route.params.id;
    const response = await axios.delete(`/v1/purchases/delete/${id}`);

    if (response.data.status === "success") {
      showToast("Purchase deleted successfully!", "success");
      setTimeout(() => {
        router.push("/admin/purchases");
      }, 1200);
    } else {
      throw new Error(response.data.message || "Delete failed");
    }
  } catch (err) {
    console.error("Delete error:", err);
    deleteError.value = err.response?.data?.message || err.message || "Failed to delete purchase";
    showToast(deleteError.value, "error");
  } finally {
    deleteLoading.value = false;
  }
};

// Helpers
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

const copyToClipboard = async () => {
  if (!purchase.value?.id) return;
  try {
    await navigator.clipboard.writeText(purchase.value.id.toString());
    showToast("Purchase ID copied to clipboard!", "success");
  } catch (err) {
    showToast("Failed to copy ID", "error");
  }
};

onMounted(() => {
  fetchPurchase();
});
</script>

<style scoped>
@keyframes slide-up {
  from { transform: translateY(100%); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}
.animate-slide-up {
  animation: slide-up 0.3s ease-out;
}
</style>
