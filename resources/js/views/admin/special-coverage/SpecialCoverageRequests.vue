<template>
    <AdminLayout>
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                Special Coverage Requests
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Review dedicated event coverage requests submitted from the Home page.
            </p>
        </div>

        <div class="mb-5 bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="inline-flex w-full sm:w-auto items-center rounded-xl border border-gray-200 bg-gray-50 p-1">
                    <button
                        v-for="filter in statusFilters"
                        :key="filter.value"
                        type="button"
                        :disabled="loading"
                        class="flex flex-1 sm:flex-none items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all disabled:cursor-not-allowed disabled:opacity-60"
                        :class="statusFilter === filter.value ? filter.activeClass : 'text-gray-500 hover:bg-white hover:text-gray-900'"
                        @click="setStatusFilter(filter.value)"
                    >
                        <span class="h-2 w-2 rounded-full" :class="filter.dotClass"></span>
                        {{ filter.label }}
                    </button>
                </div>

                <form class="flex w-full gap-2 lg:w-96" @submit.prevent="fetchRequests(1)">
                    <input
                        v-model="search"
                        type="search"
                        class="w-full rounded-xl border border-gray-200 px-4 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        placeholder="Search event, user, or email"
                    />
                    <button
                        type="submit"
                        class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                        :disabled="loading"
                    >
                        Search
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div v-if="loading" class="flex items-center justify-center py-20">
                <div class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
            </div>

            <div v-else-if="loadError" class="py-16 text-center text-red-600">
                {{ loadError }}
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-full">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4">ID</th>
                            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4">User</th>
                            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4">User Email</th>
                            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4">Event Name</th>
                            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4">Status</th>
                            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4">Submitted At</th>
                            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4">Reviewed At</th>
                            <th class="text-center text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="request in requests" :key="request.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-gray-500">#{{ request.id }}</td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ request.user?.name || "-" }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ request.user?.email || "-" }}</td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ request.event_name }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold" :class="statusClass(request.status)">
                                    <span class="w-2 h-2 rounded-full" :class="statusDotClass(request.status)"></span>
                                    {{ capitalize(request.status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ formatDate(request.created_at) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ formatDate(request.reviewed_at) }}</td>
                            <td class="px-6 py-4 text-center">
                                <router-link
                                    :to="`/admin/special-coverage/${request.id}`"
                                    class="inline-flex items-center justify-center rounded-full border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 text-decoration-none hover:border-blue-300 hover:text-blue-600"
                                >
                                    View
                                </router-link>
                            </td>
                        </tr>

                        <tr v-if="requests.length === 0">
                            <td colspan="8" class="py-16 text-center text-gray-400">
                                <p class="font-medium text-gray-500">No special coverage requests found</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="pagination && !loading"
                class="flex flex-col gap-4 border-t border-gray-100 bg-gray-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-gray-500">
                    Showing <span class="font-medium">{{ pagination.from ?? 0 }}</span>
                    to <span class="font-medium">{{ pagination.to ?? 0 }}</span>
                    of <span class="font-medium">{{ pagination.total ?? 0 }}</span> entries
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="currentPage <= 1"
                        class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed"
                        @click="fetchRequests(currentPage - 1)"
                    >
                        Previous
                    </button>
                    <span class="text-sm text-gray-500">Page {{ currentPage }} of {{ pagination.last_page || 1 }}</span>
                    <button
                        type="button"
                        :disabled="currentPage >= (pagination.last_page || 1)"
                        class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed"
                        @click="fetchRequests(currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from "vue";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { specialCoverageRequestsService } from "../../../services/admin/specialCoverageRequestsService";

const requests = ref([]);
const pagination = ref(null);
const loading = ref(false);
const loadError = ref("");
const currentPage = ref(1);
const statusFilter = ref("all");
const search = ref("");

const statusFilters = [
    { label: "All", value: "all", dotClass: "bg-blue-500", activeClass: "bg-blue-600 text-white shadow-sm" },
    { label: "Pending", value: "pending", dotClass: "bg-amber-500", activeClass: "bg-amber-600 text-white shadow-sm" },
    { label: "Approved", value: "approved", dotClass: "bg-green-500", activeClass: "bg-green-600 text-white shadow-sm" },
    { label: "Rejected", value: "rejected", dotClass: "bg-red-500", activeClass: "bg-red-600 text-white shadow-sm" },
];

async function fetchRequests(page = 1) {
    loading.value = true;
    loadError.value = "";
    currentPage.value = page;

    try {
        const params = {};
        if (statusFilter.value !== "all") params.status = statusFilter.value;
        if (search.value.trim()) params.search = search.value.trim();

        const response = await specialCoverageRequestsService.getAll(page, params);
        const paginated = response.data.data;

        requests.value = paginated?.data || [];
        pagination.value = paginated || null;
    } catch (error) {
        console.error("Failed to load special coverage requests:", error.response?.data || error);
        loadError.value = error.response?.data?.message || "Failed to load special coverage requests.";
        requests.value = [];
        pagination.value = null;
    } finally {
        loading.value = false;
    }
}

async function setStatusFilter(status) {
    if (statusFilter.value === status) return;

    statusFilter.value = status;
    await fetchRequests(1);
}

function statusClass(status) {
    return {
        pending: "bg-amber-100 text-amber-700",
        approved: "bg-green-100 text-green-700",
        rejected: "bg-red-100 text-red-700",
    }[status] || "bg-gray-100 text-gray-700";
}

function statusDotClass(status) {
    return {
        pending: "bg-amber-500",
        approved: "bg-green-500",
        rejected: "bg-red-500",
    }[status] || "bg-gray-500";
}

function formatDate(dateString) {
    if (!dateString) return "-";

    return new Date(dateString).toLocaleString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

function capitalize(value) {
    return value ? value.charAt(0).toUpperCase() + value.slice(1) : "";
}

onMounted(() => fetchRequests(1));
</script>
