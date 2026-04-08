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
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total Requests</p>
                    <p class="text-2xl font-bold text-gray-900">{{ counts.total ?? "—" }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Pending</p>
                    <p class="text-2xl font-bold text-gray-900">{{ counts.pending ?? "—" }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center text-green-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Approved</p>
                    <p class="text-2xl font-bold text-gray-900">{{ counts.approved ?? "—" }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center text-red-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Rejected</p>
                    <p class="text-2xl font-bold text-gray-900">{{ counts.rejected ?? "—" }}</p>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-20">
                <div class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-full">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th
                                class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4 w-16">
                                ID</th>
                            <th
                                class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4">
                                Event Title</th>
                            <th
                                class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4">
                                Status</th>
                            <th
                                class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4">
                                Created At</th>
                            <th
                                class="text-center text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="request in requests" :key="request.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-gray-500">#{{ request.id }}</td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-gray-900">{{ request.events?.title ?? "—" }}</div>
                                <div class="text-xs text-gray-400 mt-1">Event ID: {{ request.event_id }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                                    :class="{
                                        'bg-amber-100 text-amber-700': request.status === 'pending',
                                        'bg-green-100 text-green-700': request.status === 'approved',
                                        'bg-red-100 text-red-700': request.status === 'rejected',
                                    }">
                                    <span class="w-2 h-2 rounded-full" :class="{
                                        'bg-amber-500': request.status === 'pending',
                                        'bg-green-500': request.status === 'approved',
                                        'bg-red-500': request.status === 'rejected',
                                    }"></span>
                                    {{ capitalize(request.status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ formatDate(request.created_at) }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-3">
                                    <router-link :to="`/admin/requests/${request.id}`"
                                        class="text-gray-400 hover:text-blue-600 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5 16.477 5 20.268 7.943 21.542 12 20.268 16.057 16.477 19 12 19 7.523 19 3.732 16.057 2.458 12z" />
                                        </svg>
                                    </router-link>
                                    <button @click="deleteRequest(request)"
                                        class="text-red-500 hover:text-red-600 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <!-- trash lid -->
                                            <path d="M3 6h18" />

                                            <!-- trash body -->
                                            <path d="M8 6V4h8v2" />

                                            <!-- container -->
                                            <path d="M6 6l1 14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-14" />

                                            <!-- inner lines -->
                                            <path d="M10 11v6M14 11v6" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="requests.length === 0 && !loading">
                            <td colspan="5" class="py-16 text-center text-gray-400">
                                No requests found
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer - تم إصلاحه -->
            <div v-if="pagination"
                class="flex items-center justify-between border-t border-gray-100 px-6 py-4 bg-gray-50">
                <p class="text-sm text-gray-500">
                    Showing <span class="font-medium">{{ pagination.from ?? 0 }}</span> to
                    <span class="font-medium">{{ pagination.to ?? 0 }}</span> of
                    <span class="font-medium">{{ pagination.total ?? 0 }}</span> entries
                </p>

                <div class="flex items-center gap-1">
                    <!-- Previous -->
                    <button @click="fetchRequests(currentPage - 1)" :disabled="currentPage <= 1"
                        class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition-all flex items-center gap-1">
                        <span>←</span> Previous
                    </button>

                    <!-- Page Numbers -->
                    <div v-for="(link, i) in pageLinks" :key="i" class="flex">
                        <button v-if="link.page" @click="fetchRequests(link.page)"
                            :class="[link.active ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-100 border border-gray-200']"
                            class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all">
                            {{ link.page }}
                        </button>
                        <span v-else class="px-3 py-2 text-gray-400">…</span>
                    </div>

                    <!-- Next -->
                    <button @click="fetchRequests(currentPage + 1)" :disabled="currentPage >= pagination.last_page"
                        class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition-all flex items-center gap-1">
                        Next <span>→</span>
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
const counts = ref({ total: 0, pending: 0, approved: 0, rejected: 0 });

const loading = ref(false);
const currentPage = ref(1);

const pageLinks = computed(() => {
    if (!pagination.value) return [];
    const total = pagination.value.last_page || 1;
    const current = currentPage.value;
    const links = [];

    // دائمًا نعرض الصفحة 1
    links.push({ page: 1, active: current === 1 });

    if (total > 1) {
        if (current > 3) links.push({ page: null, label: "..." });

        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);

        for (let i = start; i <= end; i++) {
            links.push({ page: i, active: i === current });
        }

        if (current < total - 2) links.push({ page: null, label: "..." });

        if (total > 1) {
            links.push({ page: total, active: current === total });
        }
    }

    return links;
});

async function fetchRequests(page = 1) {
    if (page < 1) return;
    loading.value = true;
    currentPage.value = page;

    try {
        const res = await requestsService.getAllPaginated(page);

        const paginatedData = res.data.data;        // الـ pagination object
        const fullResponse = res.data;              // عشان الـ counts

        requests.value = paginatedData.data || [];

        pagination.value = paginatedData;

        counts.value = {
            total: paginatedData.total || 0,
            pending: fullResponse.counts?.pending ?? 0,
            approved: fullResponse.counts?.approved ?? 0,
            rejected: fullResponse.counts?.rejected ?? 0,
        };
    } catch (err) {
        console.error("Failed to fetch requests:", err);
    } finally {
        loading.value = false;
    }
}

const deleteRequest = async (request) => {
    if (!confirm(`Delete request #${request.id}?`)) return;

    try {
        await axios.delete(`/api/v1/requests/${request.id}`, {
            headers: { Authorization: `Bearer ${localStorage.getItem("auth_token")}` }
        });
        await fetchRequests(currentPage.value);   // refresh بدون reload
    } catch (err) {
        alert(err.response?.data?.message || "Delete failed");
    }
};

function formatDate(dateStr) {
    if (!dateStr) return "—";
    return new Date(dateStr).toLocaleDateString("en-US", {
        month: "short", day: "numeric", year: "numeric"
    });
}

function capitalize(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : "";
}

onMounted(() => fetchRequests(1));
</script>
