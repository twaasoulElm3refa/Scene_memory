<template>
    <AdminLayout>
        <div class="py-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-900 mb-1">All Purchases</h2>
                    <p class="text-sm text-gray-500">{{ purchases?.total ?? 0 }} total records</p>
                </div>

                <!-- Dropdowns -->
                <div class="flex gap-3">
                    <!-- Type Dropdown -->
                    <div class="relative" ref="typeDropdownRef">
                        <button
                            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-indigo-400 hover:shadow-sm transition-all"
                            @click="toggleTypeDropdown"
                        >
                            <span>{{ selectedType ? getTypeLabel(selectedType) : 'Filter by Type' }}</span>
                            <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <div v-if="showTypeDropdown" class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg z-50 overflow-hidden">
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-500 font-medium border-b border-gray-100 hover:bg-gray-50 cursor-pointer"
                                @click="selectType(null)"
                            >
                                All Types
                            </div>
                            <div
                                v-for="type in typeOptions" :key="type.value"
                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer"
                                @click="selectType(type.value)"
                            >
                                {{ type.icon }} {{ type.label }}
                            </div>
                        </div>
                    </div>

                    <!-- Status Dropdown -->
                    <div class="relative" ref="statusDropdownRef">
                        <button
                            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-indigo-400 hover:shadow-sm transition-all"
                            @click="toggleStatusDropdown"
                        >
                            <span>{{ selectedStatus ? capitalizeFirst(selectedStatus) : 'Filter by Status' }}</span>
                            <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <div v-if="showStatusDropdown" class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg z-50 overflow-hidden">
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-500 font-medium border-b border-gray-100 hover:bg-gray-50 cursor-pointer"
                                @click="selectStatus(null)"
                            >
                                All Statuses
                            </div>
                            <div
                                v-for="status in statusOptions" :key="status.value"
                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer"
                                @click="selectStatus(status.value)"
                            >
                                <span class="w-2 h-2 rounded-full" :style="{ background: getStatusColor(status.value) }"></span>
                                {{ status.label }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex flex-col items-center justify-center py-16 bg-white border border-gray-100 rounded-xl gap-3">
                <div class="w-8 h-8 border-2 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
                <p class="text-sm text-gray-500">Loading purchases...</p>
            </div>

            <!-- Error -->
            <div v-else-if="error" class="flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                {{ error }}
            </div>

            <!-- Table -->
            <template v-else>
                <div class="bg-white border border-gray-100 rounded-xl overflow-x-auto shadow-sm">
                    <table class="w-full text-sm min-w-[900px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-16">#</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-28">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-28">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-36">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-24">Mail</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-36">Paid at</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-36">Created at</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-20">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="purchase in purchases?.data" :key="purchase.id"
                                class="border-b border-gray-50 hover:bg-gray-50 transition-colors"
                            >
                                <td class="px-4 py-3 text-gray-400 font-medium">#{{ purchase.id }}</td>

                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-sm font-semibold shrink-0">
                                            {{ getUserInitial(purchase.user?.name) }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900 truncate max-w-[150px]">{{ purchase.user?.name || 'N/A' }}</div>
                                            <div class="text-xs text-gray-500 truncate max-w-[150px]">{{ purchase.user?.email || 'No email' }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3 font-semibold text-gray-900">
                                    ${{ Number(purchase.amount).toFixed(2) }}
                                </td>

                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full"
                                        :class="getStatusClass(purchase.status)"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :style="{ background: getStatusColor(purchase.status) }"></span>
                                        {{ purchase.status }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full capitalize"
                                        :class="getTypeClass(purchase.type)"
                                    >
                                        {{ getTypeIcon(purchase.type) }} {{ purchase.type }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center text-xs font-medium px-2.5 py-1 rounded-full"
                                        :class="purchase.mail_sent ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                    >
                                        {{ purchase.mail_sent ? 'Sent' : 'Pending' }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    {{ purchase.paid_at ? formatDate(purchase.paid_at) : '—' }}
                                </td>

                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    {{ formatDate(purchase.created_at) }}
                                </td>

                                <td class="px-4 py-3">
                                    <button
                                        @click="viewPurchase(purchase.id)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-white border border-gray-200 rounded-lg hover:bg-blue-50 hover:border-blue-200 transition-all"
                                    >
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                        View
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!purchases?.data?.length">
                                <td colspan="9" class="py-16 text-center">
                                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="3" y="4" width="18" height="16" rx="2" />
                                        <line x1="8" y1="10" x2="16" y2="10" />
                                        <line x1="8" y1="14" x2="12" y2="14" />
                                    </svg>
                                    <p class="text-sm font-medium text-gray-700">No purchases found</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="purchases?.last_page > 1" class="flex items-center justify-between mt-6 flex-wrap gap-4">
                    <span class="text-sm text-gray-500">
                        Showing {{ purchases.from }}–{{ purchases.to }} of {{ purchases.total }} results
                    </span>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            :disabled="currentPage === 1"
                            @click="goToPage(currentPage - 1)"
                            class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all"
                        >
                            ← Prev
                        </button>

                        <template v-for="page in visiblePages" :key="page">
                            <span v-if="page === '...'" class="px-1 text-gray-400">...</span>
                            <button
                                v-else
                                @click="goToPage(page)"
                                class="min-w-[34px] h-[34px] px-2 text-sm font-medium border rounded-lg transition-all"
                                :class="page === currentPage
                                    ? 'bg-blue-50 text-blue-600 border-blue-200'
                                    : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'"
                            >
                                {{ page }}
                            </button>
                        </template>

                        <button
                            :disabled="currentPage === purchases.last_page"
                            @click="goToPage(currentPage + 1)"
                            class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all"
                        >
                            Next →
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[1000]" @click="closeModal">
            <div class="bg-white rounded-xl w-[90%] max-w-lg max-h-[80vh] overflow-hidden shadow-2xl" @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">Purchase Details</h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-red-500 text-2xl leading-none transition-colors">&times;</button>
                </div>
                <div v-if="modalLoading" class="flex flex-col items-center justify-center py-10 gap-3">
                    <div class="w-6 h-6 border-2 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
                    <p class="text-sm text-gray-500">Loading details...</p>
                </div>
                <div v-else-if="selectedPurchase" class="p-6 flex flex-col gap-3">
                    <div class="flex gap-3 text-sm"><span class="w-24 font-semibold text-gray-500 shrink-0">ID:</span><span class="text-gray-900">#{{ selectedPurchase.id }}</span></div>
                    <div class="flex gap-3 text-sm"><span class="w-24 font-semibold text-gray-500 shrink-0">User:</span><span class="text-gray-900">{{ selectedPurchase.user?.name }} ({{ selectedPurchase.user?.email }})</span></div>
                    <div class="flex gap-3 text-sm"><span class="w-24 font-semibold text-gray-500 shrink-0">Amount:</span><span class="text-blue-600 font-semibold">${{ Number(selectedPurchase.amount).toFixed(2) }}</span></div>
                    <div class="flex gap-3 text-sm"><span class="w-24 font-semibold text-gray-500 shrink-0">Status:</span><span class="text-gray-900">{{ selectedPurchase.status }}</span></div>
                    <div class="flex gap-3 text-sm"><span class="w-24 font-semibold text-gray-500 shrink-0">Type:</span><span class="text-gray-900">{{ selectedPurchase.type }}</span></div>
                    <div class="flex gap-3 text-sm"><span class="w-24 font-semibold text-gray-500 shrink-0">Mail Sent:</span><span class="text-gray-900">{{ selectedPurchase.mail_sent ? 'Yes' : 'No' }}</span></div>
                    <div class="flex gap-3 text-sm"><span class="w-24 font-semibold text-gray-500 shrink-0">Paid At:</span><span class="text-gray-900">{{ selectedPurchase.paid_at ? formatDate(selectedPurchase.paid_at) : '—' }}</span></div>
                    <div class="flex gap-3 text-sm"><span class="w-24 font-semibold text-gray-500 shrink-0">Created At:</span><span class="text-gray-900">{{ formatDate(selectedPurchase.created_at) }}</span></div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import axios from 'axios'
import { useRouter, useRoute } from "vue-router";
import AdminLayout from '../../../layouts/AdminLayout.vue'

const router = useRouter();
const route = useRoute();

const purchases = ref(null)
const loading = ref(false)
const error = ref(null)
const currentPage = ref(1)

const showModal = ref(false)
const modalLoading = ref(false)
const selectedPurchase = ref(null)

const showTypeDropdown = ref(false)
const showStatusDropdown = ref(false)
const selectedType = ref(null)
const selectedStatus = ref(null)
const typeDropdownRef = ref(null)
const statusDropdownRef = ref(null)

const typeOptions = [
    { value: 'checkout', label: 'Checkout', icon: '🛒' },
    { value: 'wallet_deposit', label: 'Wallet Deposit', icon: '💰' },
    { value: 'wallet', label: 'Wallet', icon: '👛' },
]

const statusOptions = [
    { value: 'completed', label: 'Completed' },
    { value: 'failed', label: 'Failed' },
    { value: 'pending', label: 'Pending' },
]

const toggleTypeDropdown = () => {
    showTypeDropdown.value = !showTypeDropdown.value
    showStatusDropdown.value = false
}

const toggleStatusDropdown = () => {
    showStatusDropdown.value = !showStatusDropdown.value
    showTypeDropdown.value = false
}

const selectType = (value) => {
    showTypeDropdown.value = false
    if (!value) { selectedType.value = null; return }
    selectedType.value = value
    router.push(`/admin/purchases/${value}/type`)
}

const selectStatus = (value) => {
    showStatusDropdown.value = false
    if (!value) { selectedStatus.value = null; return }
    selectedStatus.value = value
    router.push(`/admin/purchases/${value}/status`)
}

const getTypeLabel = (value) => typeOptions.find(t => t.value === value)?.label || value
const capitalizeFirst = (str) => str ? str.charAt(0).toUpperCase() + str.slice(1) : ''

const handleClickOutside = (e) => {
    if (typeDropdownRef.value && !typeDropdownRef.value.contains(e.target)) showTypeDropdown.value = false
    if (statusDropdownRef.value && !statusDropdownRef.value.contains(e.target)) showStatusDropdown.value = false
}

onMounted(() => { document.addEventListener('click', handleClickOutside); fetchPurchases() })
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside))

const getUserInitial = (name) => name ? name.charAt(0).toUpperCase() : '?'
const getTypeIcon = (type) => ({ checkout: '🛒', wallet_deposit: '💰', wallet: '👛' })[type] || '📦'

const getTypeClass = (type) => ({
    checkout: 'bg-blue-50 text-blue-700',
    wallet_deposit: 'bg-purple-50 text-purple-700',
    wallet: 'bg-pink-50 text-pink-700',
})[type] || 'bg-gray-100 text-gray-600'

const getStatusClass = (status) => ({
    completed: 'bg-emerald-50 text-emerald-700',
    pending: 'bg-amber-50 text-amber-700',
    failed: 'bg-red-50 text-red-700',
})[status?.toLowerCase()] || 'bg-gray-100 text-gray-600'

const getStatusColor = (status) => ({
    completed: '#10B981', pending: '#F59E0B', failed: '#EF4444'
})[status?.toLowerCase()] || '#6B7280'

const formatDate = (str) => {
    if (!str) return '—'
    return new Date(str).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const fetchPurchases = async (page = 1) => {
    loading.value = true; error.value = null
    try {
        const { data } = await axios.get(`/v1/purchases?page=${page}`)
        purchases.value = data.data
        currentPage.value = page
    } catch (e) {
        error.value = e?.response?.data?.message || 'Something went wrong.'
    } finally {
        loading.value = false
    }
}

const viewPurchase = (id) => router.push(`/admin/purchases/${id}`)
const closeModal = () => { showModal.value = false; selectedPurchase.value = null }

const goToPage = (page) => {
    if (page < 1 || page > (purchases.value?.last_page || 1)) return
    fetchPurchases(page)
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

const visiblePages = computed(() => {
    const total = purchases.value?.last_page ?? 1
    const cur = currentPage.value
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
    const pages = [1]
    if (cur > 3) pages.push('...')
    for (let i = Math.max(2, cur - 1); i <= Math.min(total - 1, cur + 1); i++) pages.push(i)
    if (cur < total - 2) pages.push('...')
    pages.push(total)
    return pages
})

watch(() => route.fullPath, () => { currentPage.value = 1; fetchPurchases(1) })
</script>
