<template>
    <AdminLayout>
        <div class="py-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Withdrawals by Status</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Current status: <span class="font-semibold capitalize">{{ currentStatus }}</span>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="goAll"
                    >
                        All
                    </button>
                    <button
                        v-for="status in statuses"
                        :key="status"
                        class="rounded-lg border px-3 py-1.5 text-sm font-medium capitalize transition"
                        :class="currentStatus === status ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                        @click="goStatus(status)"
                    >
                        {{ status }}
                    </button>
                </div>
            </div>

            <div v-if="loading" class="rounded-xl border border-gray-100 bg-white p-10 text-center">
                <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-gray-200 border-t-blue-500"></div>
                <p class="mt-3 text-sm text-gray-500">Loading withdrawals...</p>
            </div>

            <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                {{ error }}
            </div>

            <template v-else>
                <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table class="min-w-[1050px] w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">User</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fee</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Reference</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Processed</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in rows"
                                :key="item.id"
                                class="border-b border-gray-50 hover:bg-gray-50"
                            >
                                <td class="px-4 py-3 font-medium text-gray-700">#{{ item.id }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ item.user?.name || 'N/A' }}</p>
                                    <p class="text-xs text-gray-500">{{ item.user?.email || 'No email' }}</p>
                                </td>
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ money(item.amount) }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ money(item.fee) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(item.status)">
                                        {{ item.status || 'unknown' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ item.reference || 'N/A' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ formatDate(item.processed_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button class="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100" @click="viewItem(item.id)">
                                            View
                                        </button>
                                        <button class="rounded-md border border-indigo-200 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50" @click="editItem(item.id)">
                                            Edit
                                        </button>
                                        <button
                                            class="rounded-md border border-green-200 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-50 disabled:opacity-50"
                                            :disabled="isActionLoading(item.id)"
                                            @click="approveItem(item.id)"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            class="rounded-md border border-amber-200 px-2.5 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50 disabled:opacity-50"
                                            :disabled="isActionLoading(item.id)"
                                            @click="rejectItem(item.id)"
                                        >
                                            Reject
                                        </button>
                                        <button
                                            class="rounded-md border border-red-200 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
                                            :disabled="isActionLoading(item.id)"
                                            @click="deleteItem(item.id)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="8" class="px-4 py-12 text-center text-gray-500">No records for this status.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="pagination.last_page > 1" class="mt-5 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500">
                        Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            class="rounded-md border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="pagination.current_page <= 1"
                            @click="fetchByStatus(pagination.current_page - 1)"
                        >
                            Prev
                        </button>
                        <span class="text-sm text-gray-600">Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
                        <button
                            class="rounded-md border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="pagination.current_page >= pagination.last_page"
                            @click="fetchByStatus(pagination.current_page + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import toastr from "toastr";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { withdrawalServices } from "../../../services/admin/withdrawals/withdrawalServices";

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const error = ref("");
const rows = ref([]);
const actionLoadingIds = ref(new Set());
const pagination = ref({
    current_page: 1,
    last_page: 1,
    from: 0,
    to: 0,
    total: 0,
});

const statuses = ["pending", "processing", "approved", "rejected", "paid"];
const currentStatus = computed(() => String(route.params.status || "pending").toLowerCase());

const isActionLoading = (id) => actionLoadingIds.value.has(id);

const normalizePaginator = (payload) => {
    const raw = payload?.data?.data || payload?.data || payload;
    const data = Array.isArray(raw?.data) ? raw.data : [];

    return {
        data,
        current_page: Number(raw?.current_page || 1),
        last_page: Number(raw?.last_page || 1),
        from: Number(raw?.from || (data.length ? 1 : 0)),
        to: Number(raw?.to || data.length),
        total: Number(raw?.total || data.length),
    };
};

const formatDate = (value) => {
    if (!value) return "N/A";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString();
};

const money = (value) => {
    const amount = Number(value || 0);
    return Number.isFinite(amount) ? amount.toFixed(2) : "0.00";
};

const statusClass = (status) => {
    const key = String(status || "").toLowerCase();
    if (key === "approved" || key === "paid") return "bg-green-100 text-green-700";
    if (key === "pending" || key === "processing") return "bg-amber-100 text-amber-700";
    if (key === "rejected") return "bg-red-100 text-red-700";
    return "bg-gray-100 text-gray-700";
};

const fetchByStatus = async (page = 1) => {
    loading.value = true;
    error.value = "";

    try {
        const response = await withdrawalServices.getByStatus(currentStatus.value, page);
        const pager = normalizePaginator(response);
        rows.value = pager.data;
        pagination.value = {
            current_page: pager.current_page,
            last_page: pager.last_page,
            from: pager.from,
            to: pager.to,
            total: pager.total,
        };
    } catch (err) {
        error.value = err?.response?.data?.message || err?.message || "Failed to load withdrawals by status.";
        rows.value = [];
    } finally {
        loading.value = false;
    }
};

const runRowAction = async (id, actionFn, successMessage) => {
    actionLoadingIds.value.add(id);
    try {
        await actionFn(id);
        toastr.success(successMessage);
        await fetchByStatus(pagination.value.current_page);
    } catch (err) {
        toastr.error(err?.response?.data?.message || err?.message || "Action failed");
    } finally {
        actionLoadingIds.value.delete(id);
    }
};

const approveItem = (id) => runRowAction(id, withdrawalServices.approve, "Withdrawal approved.");
const rejectItem = (id) => runRowAction(id, withdrawalServices.reject, "Withdrawal rejected.");
const deleteItem = async (id) => {
    const confirmed = window.confirm("Delete this withdrawal request?");
    if (!confirmed) return;
    await runRowAction(id, withdrawalServices.delete, "Withdrawal deleted.");
};

const goStatus = (status) => router.push(`/admin/purchases/withdrawls/${status}/status`);
const goAll = () => router.push("/admin/purchases/withdrawls");
const viewItem = (id) => router.push(`/admin/purchases/withdrawls/${id}`);
const editItem = (id) => router.push(`/admin/purchases/withdrawls/${id}/edit`);

watch(() => route.params.status, () => fetchByStatus(1));
onMounted(() => fetchByStatus(1));
</script>
