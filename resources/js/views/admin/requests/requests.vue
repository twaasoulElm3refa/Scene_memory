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
            <!-- Total Requests -->
            <div
                class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4"
            >
                <div
                    class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500"
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
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                        />
                    </svg>
                </div>

                <div>
                    <p
                        class="text-xs text-gray-400 font-medium uppercase tracking-wide"
                    >
                        Total Requests
                    </p>

                    <p class="text-2xl font-bold text-gray-900">
                        {{ counts.total ?? "—" }}
                    </p>
                </div>
            </div>

            <!-- Pending -->
            <div
                class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4"
            >
                <div
                    class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500"
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
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </div>

                <div>
                    <p
                        class="text-xs text-gray-400 font-medium uppercase tracking-wide"
                    >
                        Pending
                    </p>

                    <p class="text-2xl font-bold text-gray-900">
                        {{ counts.pending ?? "—" }}
                    </p>
                </div>
            </div>

            <!-- Approved -->
            <div
                class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4"
            >
                <div
                    class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center text-green-500"
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
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </div>

                <div>
                    <p
                        class="text-xs text-gray-400 font-medium uppercase tracking-wide"
                    >
                        Approved
                    </p>

                    <p class="text-2xl font-bold text-gray-900">
                        {{ counts.approved ?? "—" }}
                    </p>
                </div>
            </div>

            <!-- Rejected -->
            <div
                class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4"
            >
                <div
                    class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center text-red-400"
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
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </div>

                <div>
                    <p
                        class="text-xs text-gray-400 font-medium uppercase tracking-wide"
                    >
                        Rejected
                    </p>

                    <p class="text-2xl font-bold text-gray-900">
                        {{ counts.rejected ?? "—" }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Content Safety Filter -->
        <div
            class="mb-5 bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">
                        Content safety filter
                    </h2>

                    <p class="mt-1 text-xs text-gray-500">
                        Filter requests by AI-detected sensitive content.
                    </p>
                </div>

                <div
                    class="inline-flex w-full sm:w-auto items-center rounded-xl border border-gray-200 bg-gray-50 p-1"
                >
                    <button
                        v-for="filter in contentFilters"
                        :key="filter.value"
                        type="button"
                        :disabled="loading"
                        class="flex flex-1 sm:flex-none items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all disabled:cursor-not-allowed disabled:opacity-60"
                        :class="
                            contentFilter === filter.value
                                ? filter.activeClass
                                : 'text-gray-500 hover:bg-white hover:text-gray-900'
                        "
                        @click="setContentFilter(filter.value)"
                    >
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="filter.dotClass"
                        ></span>

                        {{ filter.label }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div
            class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"
        >
            <!-- Loading -->
            <div
                v-if="loading"
                class="flex items-center justify-center py-20"
            >
                <div
                    class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"
                ></div>
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-full">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th
                                class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4 w-16"
                            >
                                ID
                            </th>

                            <th
                                class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4"
                            >
                                Event Title
                            </th>

                            <th
                                class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4"
                            >
                                Status
                            </th>

                            <th
                                class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4"
                            >
                                Sensitive Content
                            </th>

                            <th
                                class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4"
                            >
                                Created At
                            </th>

                            <th
                                class="text-center text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-4"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="request in requests"
                            :key="request.id"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <!-- ID -->
                            <td
                                class="px-6 py-4 text-sm font-medium text-gray-500"
                            >
                                #{{ request.id }}
                            </td>

                            <!-- Event -->
                            <td class="px-6 py-4">
                                <div class="font-semibold text-gray-900">
                                    {{ request.events?.title ?? "—" }}
                                </div>

                                <div class="text-xs text-gray-400 mt-1">
                                    Event ID: {{ request.event_id }}
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                                    :class="{
                                        'bg-amber-100 text-amber-700':
                                            request.status === 'pending',
                                        'bg-green-100 text-green-700':
                                            request.status === 'approved',
                                        'bg-red-100 text-red-700':
                                            request.status === 'rejected',
                                    }"
                                >
                                    <span
                                        class="w-2 h-2 rounded-full"
                                        :class="{
                                            'bg-amber-500':
                                                request.status === 'pending',
                                            'bg-green-500':
                                                request.status === 'approved',
                                            'bg-red-500':
                                                request.status === 'rejected',
                                        }"
                                    ></span>

                                    {{ capitalize(request.status) }}
                                </span>
                            </td>

                            <!-- Sensitive Content -->
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                                    :class="
                                        isSensitive(request.ai_flagged)
                                            ? 'bg-red-100 text-red-700'
                                            : 'bg-green-100 text-green-700'
                                    "
                                >
                                    <span
                                        class="w-2 h-2 rounded-full"
                                        :class="
                                            isSensitive(request.ai_flagged)
                                                ? 'bg-red-500'
                                                : 'bg-green-500'
                                        "
                                    ></span>

                                    {{
                                        isSensitive(request.ai_flagged)
                                            ? "Sensitive"
                                            : "Safe"
                                    }}
                                </span>
                            </td>

                            <!-- Created At -->
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ formatDate(request.created_at) }}
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-3">
                                    <router-link
                                        :to="`/admin/requests/${request.id}`"
                                        class="text-gray-400 hover:text-blue-600 transition-colors"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="w-5 h-5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
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
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5 16.477 5 20.268 7.943 21.542 12 20.268 16.057 16.477 19 12 19 7.523 19 3.732 16.057 2.458 12z"
                                            />
                                        </svg>
                                    </router-link>

                                    <button
                                        type="button"
                                        class="text-red-500 hover:text-red-600 transition-colors"
                                        @click="deleteRequest(request)"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="w-5 h-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <!-- Trash lid -->
                                            <path d="M3 6h18" />

                                            <!-- Trash body -->
                                            <path d="M8 6V4h8v2" />

                                            <!-- Container -->
                                            <path
                                                d="M6 6l1 14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-14"
                                            />

                                            <!-- Inner lines -->
                                            <path d="M10 11v6M14 11v6" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="requests.length === 0 && !loading">
                            <td
                                colspan="6"
                                class="py-16 text-center text-gray-400"
                            >
                                <div
                                    class="flex flex-col items-center justify-center"
                                >
                                    <svg
                                        class="mb-3 h-10 w-10 text-gray-300"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M9 12h6m-3-3v6m9-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>

                                    <p class="font-medium text-gray-500">
                                        No requests found
                                    </p>

                                    <p class="mt-1 text-xs text-gray-400">
                                        No
                                        {{
                                            contentFilter === "all"
                                                ? ""
                                                : contentFilter
                                        }}
                                        requests match this filter.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div
                v-if="pagination && !loading"
                class="flex flex-col gap-4 border-t border-gray-100 bg-gray-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-gray-500">
                    Showing
                    <span class="font-medium">
                        {{ pagination.from ?? 0 }}
                    </span>
                    to
                    <span class="font-medium">
                        {{ pagination.to ?? 0 }}
                    </span>
                    of
                    <span class="font-medium">
                        {{ pagination.total ?? 0 }}
                    </span>
                    entries
                </p>

                <div class="flex flex-wrap items-center gap-1">
                    <!-- Previous -->
                    <button
                        type="button"
                        :disabled="currentPage <= 1"
                        class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition-all flex items-center gap-1"
                        @click="fetchRequests(currentPage - 1)"
                    >
                        <span>←</span>
                        Previous
                    </button>

                    <!-- Page Numbers -->
                    <div
                        v-for="(link, index) in pageLinks"
                        :key="index"
                        class="flex"
                    >
                        <button
                            v-if="link.page"
                            type="button"
                            class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all"
                            :class="
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white hover:bg-gray-100 border border-gray-200'
                            "
                            @click="fetchRequests(link.page)"
                        >
                            {{ link.page }}
                        </button>

                        <span
                            v-else
                            class="px-3 py-2 text-gray-400"
                        >
                            …
                        </span>
                    </div>

                    <!-- Next -->
                    <button
                        type="button"
                        :disabled="
                            currentPage >= (pagination.last_page || 1)
                        "
                        class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition-all flex items-center gap-1"
                        @click="fetchRequests(currentPage + 1)"
                    >
                        Next
                        <span>→</span>
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { requestsService } from "../../../services/admin/requests/requestsService.js";

const requests = ref([]);
const pagination = ref(null);

const counts = ref({
    total: 0,
    pending: 0,
    approved: 0,
    rejected: 0,
});

const loading = ref(false);
const currentPage = ref(1);

/*
|--------------------------------------------------------------------------
| Content Safety Filter
|--------------------------------------------------------------------------
|
| all       => لا يتم إرسال ai_flagged
| safe      => يتم إرسال ai_flagged = 0
| sensitive => يتم إرسال ai_flagged = 1
|
*/
const contentFilter = ref("all");

const contentFilters = [
    {
        label: "All",
        value: "all",
        dotClass: "bg-blue-500",
        activeClass:
            "bg-blue-600 text-white shadow-sm",
    },
    {
        label: "Safe",
        value: "safe",
        dotClass: "bg-green-500",
        activeClass:
            "bg-green-600 text-white shadow-sm",
    },
    {
        label: "Sensitive",
        value: "sensitive",
        dotClass: "bg-red-500",
        activeClass:
            "bg-red-600 text-white shadow-sm",
    },
];

/*
|--------------------------------------------------------------------------
| Pagination Links
|--------------------------------------------------------------------------
*/
const pageLinks = computed(() => {
    if (!pagination.value) {
        return [];
    }

    const total = pagination.value.last_page || 1;
    const current = currentPage.value;
    const links = [];

    links.push({
        page: 1,
        active: current === 1,
    });

    if (total > 1) {
        if (current > 3) {
            links.push({
                page: null,
                label: "...",
            });
        }

        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);

        for (let page = start; page <= end; page++) {
            links.push({
                page,
                active: page === current,
            });
        }

        if (current < total - 2) {
            links.push({
                page: null,
                label: "...",
            });
        }

        links.push({
            page: total,
            active: current === total,
        });
    }

    return links;
});

/*
|--------------------------------------------------------------------------
| Request Parameters
|--------------------------------------------------------------------------
*/
function getRequestParams() {
    if (contentFilter.value === "safe") {
        return {
            ai_flagged: 0,
        };
    }

    if (contentFilter.value === "sensitive") {
        return {
            ai_flagged: 1,
        };
    }

    return {};
}

/*
|--------------------------------------------------------------------------
| Fetch Requests
|--------------------------------------------------------------------------
*/
async function fetchRequests(page = 1) {
    if (page < 1) {
        return;
    }

    loading.value = true;
    currentPage.value = page;

    try {
        const params = getRequestParams();

        const response = await requestsService.getAllPaginated(
            page,
            params
        );

        const paginatedData = response.data.data;
        const fullResponse = response.data;

        requests.value = paginatedData?.data || [];
        pagination.value = paginatedData || null;

        counts.value = {
            total: paginatedData?.total || 0,
            pending: fullResponse.counts?.pending ?? 0,
            approved: fullResponse.counts?.approved ?? 0,
            rejected: fullResponse.counts?.rejected ?? 0,
        };

        if (
            pagination.value &&
            currentPage.value > pagination.value.last_page &&
            pagination.value.last_page > 0
        ) {
            await fetchRequests(pagination.value.last_page);
        }
    } catch (error) {
        console.error(
            "Failed to fetch requests:",
            error.response?.data || error
        );

        requests.value = [];
        pagination.value = null;
    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Change Content Filter
|--------------------------------------------------------------------------
*/
async function setContentFilter(filter) {
    if (
        !["all", "safe", "sensitive"].includes(filter) ||
        contentFilter.value === filter
    ) {
        return;
    }

    contentFilter.value = filter;
    currentPage.value = 1;

    await fetchRequests(1);
}

/*
|--------------------------------------------------------------------------
| Delete Request
|--------------------------------------------------------------------------
*/
const deleteRequest = async (request) => {
    if (!confirm(`Delete request #${request.id}?`)) {
        return;
    }

    try {
        await requestsService.deleteRequest(request.id);

        const targetPage =
            requests.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        await fetchRequests(targetPage);
    } catch (error) {
        alert(
            error.response?.data?.message ||
                "Delete failed"
        );
    }
};

/*
|--------------------------------------------------------------------------
| Sensitive Value Normalizer
|--------------------------------------------------------------------------
|
| يدعم القيم:
| true / false
| 1 / 0
| "1" / "0"
| "true" / "false"
|
*/
function isSensitive(value) {
    return (
        value === true ||
        value === 1 ||
        value === "1" ||
        value === "true"
    );
}

/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/
function formatDate(dateString) {
    if (!dateString) {
        return "—";
    }

    return new Date(dateString).toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
    });
}

/*
|--------------------------------------------------------------------------
| Capitalize Text
|--------------------------------------------------------------------------
*/
function capitalize(value) {
    return value
        ? value.charAt(0).toUpperCase() + value.slice(1)
        : "";
}

/*
|--------------------------------------------------------------------------
| Initial Load
|--------------------------------------------------------------------------
*/
onMounted(() => {
    fetchRequests(1);
});
</script>
