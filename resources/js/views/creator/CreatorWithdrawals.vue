<template>
    <section class="space-y-6">
        <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Creator Dashboard</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">My Withdrawals</h1>
                    <p class="mt-2 text-sm text-slate-500">Track, request, edit, and manage your withdrawal requests.</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <RouterLink
                        :to="{ name: 'creator-withdrawals-request', params: { lang: currentLang } }"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800"
                    >
                        Request Withdrawal
                    </RouterLink>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-2xl bg-slate-950 p-4 text-white">
                    <p class="text-xs text-slate-300">Total</p>
                    <p class="mt-1 text-2xl font-black">{{ totals.total }}</p>
                </div>
                <div class="rounded-2xl bg-amber-50 p-4">
                    <p class="text-xs text-amber-700">Pending</p>
                    <p class="mt-1 text-2xl font-black text-amber-800">{{ totals.pending }}</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-4">
                    <p class="text-xs text-emerald-700">Completed</p>
                    <p class="mt-1 text-2xl font-black text-emerald-800">{{ totals.completed }}</p>
                </div>
                <div class="rounded-2xl bg-rose-50 p-4">
                    <p class="text-xs text-rose-700">Rejected</p>
                    <p class="mt-1 text-2xl font-black text-rose-800">{{ totals.rejected }}</p>
                </div>
            </div>
        </header>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="status in statusOptions"
                    :key="status.value"
                    type="button"
                    class="rounded-xl border px-3 py-2 text-sm font-semibold transition"
                    :class="activeStatus === status.value
                        ? 'border-slate-900 bg-slate-900 text-white'
                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                    @click="applyStatus(status.value)"
                >
                    {{ status.label }}
                </button>
            </div>
        </section>

        <section v-if="loading" class="rounded-3xl border border-slate-200 bg-white p-10 shadow-sm">
            <div class="flex items-center justify-center gap-3 text-slate-600">
                <div class="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-slate-900"></div>
                <p class="text-sm font-semibold">Loading withdrawals...</p>
            </div>
        </section>

        <section v-else-if="error" class="rounded-3xl border border-rose-200 bg-rose-50 p-6 text-rose-700" role="alert">
            <p class="text-base font-bold">Could not load withdrawals.</p>
            <p class="mt-1 text-sm">{{ error }}</p>
            <button
                type="button"
                class="mt-4 rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700"
                @click="fetchWithdrawals(pagination.current_page)"
            >
                Retry
            </button>
        </section>

        <section v-else-if="rows.length === 0" class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center shadow-sm">
            <p class="text-lg font-bold text-slate-800">No withdrawals found</p>
            <p class="mt-2 text-sm text-slate-500">Create your first withdrawal request to start tracking payouts.</p>
        </section>

        <section v-else class="space-y-4">
            <div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-[980px] w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">ID</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Fee</th>
                            <th class="px-4 py-3">Net</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3">Reference</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in rows"
                            :key="item.id"
                            class="border-b border-slate-100 align-top text-slate-700 hover:bg-slate-50"
                        >
                            <td class="px-4 py-3 font-bold text-slate-900">#{{ item.id }}</td>
                            <td class="px-4 py-3">{{ currency(item.amount, item.currency) }}</td>
                            <td class="px-4 py-3">{{ currency(item.fee, item.currency) }}</td>
                            <td class="px-4 py-3 font-semibold">{{ currency(item.net_amount, item.currency) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold capitalize" :class="statusClass(item.status)">
                                    {{ item.status || 'unknown' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ item.method || 'N/A' }}</td>
                            <td class="px-4 py-3">{{ item.reference || 'N/A' }}</td>
                            <td class="px-4 py-3 text-xs">{{ formatDate(item.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <RouterLink
                                        :to="{ name: 'creator-withdrawals-show', params: { lang: currentLang, id: item.id } }"
                                        class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                    >
                                        View
                                    </RouterLink>
                                    <RouterLink
                                        v-if="canEdit(item.status)"
                                        :to="{ name: 'creator-withdrawals-edit', params: { lang: currentLang, id: item.id } }"
                                        class="rounded-lg border border-indigo-200 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50"
                                    >
                                        Edit
                                    </RouterLink>
                                    <button
                                        v-if="canDelete(item.status)"
                                        type="button"
                                        class="rounded-lg border border-rose-200 px-2.5 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 disabled:opacity-50"
                                        :disabled="deletingIds.has(item.id)"
                                        @click="deleteItem(item.id)"
                                    >
                                        {{ deletingIds.has(item.id) ? 'Deleting...' : 'Delete' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="pagination.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-sm text-slate-600">
                    Showing {{ pagination.from }} - {{ pagination.to }} of {{ pagination.total }}
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                        :disabled="pagination.current_page <= 1"
                        @click="fetchWithdrawals(pagination.current_page - 1)"
                    >
                        Prev
                    </button>
                    <span class="text-sm font-semibold text-slate-700">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                        :disabled="pagination.current_page >= pagination.last_page"
                        @click="fetchWithdrawals(pagination.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import toastr from "toastr";
import withdrawalServices from "@/services/withdrawals/withdrawalServices";

const route = useRoute();
const { locale } = useI18n();

const loading = ref(false);
const error = ref("");
const rows = ref([]);
const activeStatus = ref("all");
const deletingIds = ref(new Set());

const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
});

const statusOptions = [
    { value: "all", label: "All" },
    { value: "pending", label: "Pending" },
    { value: "processing", label: "Processing" },
    { value: "completed", label: "Completed" },
    { value: "rejected", label: "Rejected" },
    { value: "cancelled", label: "Cancelled" },
];

const currentLang = computed(() => String(route.params.lang || localStorage.getItem("lang") || "en").toLowerCase());

const statusClass = (status) => {
    const key = String(status || "").toLowerCase();

    if (key === "completed") return "bg-emerald-100 text-emerald-700";
    if (key === "pending" || key === "processing") return "bg-amber-100 text-amber-700";
    if (key === "rejected") return "bg-rose-100 text-rose-700";
    if (key === "cancelled") return "bg-slate-200 text-slate-700";

    return "bg-slate-100 text-slate-700";
};

const normalizePaginator = (payload) => {
    if (Array.isArray(payload)) {
        return {
            data: payload,
            current_page: 1,
            last_page: 1,
            total: payload.length,
            from: payload.length ? 1 : 0,
            to: payload.length,
        };
    }

    const raw = payload?.data?.data
        ? payload.data
        : payload;

    if (Array.isArray(raw?.data)) {
        return {
            data: raw.data,
            current_page: Number(raw.current_page || 1),
            last_page: Number(raw.last_page || 1),
            total: Number(raw.total || raw.data.length),
            from: Number(raw.from || (raw.data.length ? 1 : 0)),
            to: Number(raw.to || raw.data.length),
        };
    }

    const fallback = Array.isArray(raw) ? raw : [];

    return {
        data: fallback,
        current_page: 1,
        last_page: 1,
        total: fallback.length,
        from: fallback.length ? 1 : 0,
        to: fallback.length,
    };
};

const totals = computed(() => {
    const items = rows.value;

    return {
        total: pagination.value.total || items.length,
        pending: items.filter((item) => String(item?.status).toLowerCase() === "pending").length,
        completed: items.filter((item) => String(item?.status).toLowerCase() === "completed").length,
        rejected: items.filter((item) => String(item?.status).toLowerCase() === "rejected").length,
    };
});

const canEdit = (status) => ["pending"].includes(String(status || "").toLowerCase());

const canDelete = (status) => {
    const key = String(status || "").toLowerCase();
    return key !== "processing" && key !== "completed";
};

const currency = (value, currencyCode) => {
    const amount = Number(value || 0);
    const code = String(currencyCode || "EGP").toUpperCase();

    if (!Number.isFinite(amount)) return "0.00";

    return `${amount.toFixed(2)} ${code}`;
};

const formatDate = (value) => {
    if (!value) return "N/A";

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString(locale.value || currentLang.value || "en");
};

const fetchWithdrawals = async (page = 1) => {
    loading.value = true;
    error.value = "";

    try {
        const params = {
            page,
            per_page: 10,
            paginate: true,
        };

        if (activeStatus.value !== "all") {
            params.status = activeStatus.value;
        }

        const payload = await withdrawalServices.myWithdrawals(params);
        const pager = normalizePaginator(payload);

        rows.value = pager.data;
        pagination.value = {
            current_page: pager.current_page,
            last_page: pager.last_page,
            total: pager.total,
            from: pager.from,
            to: pager.to,
        };
    } catch (err) {
        rows.value = [];
        error.value = err?.message || "Failed to fetch withdrawals.";
    } finally {
        loading.value = false;
    }
};

const applyStatus = async (status) => {
    if (activeStatus.value === status) return;

    activeStatus.value = status;
    await fetchWithdrawals(1);
};

const deleteItem = async (id) => {
    const confirmDelete = window.confirm("Delete this withdrawal request?");
    if (!confirmDelete) return;

    deletingIds.value.add(id);

    try {
        await withdrawalServices.deleteWithdrawals(id);
        rows.value = rows.value.filter((item) => item.id !== id);
        pagination.value.total = Math.max(0, Number(pagination.value.total || 0) - 1);
        toastr.success("Withdrawal deleted successfully.");

        if (rows.value.length === 0 && pagination.value.current_page > 1) {
            await fetchWithdrawals(pagination.value.current_page - 1);
            return;
        }

        await fetchWithdrawals(pagination.value.current_page);
    } catch (err) {
        toastr.error(err?.message || "Failed to delete withdrawal.");
    } finally {
        deletingIds.value.delete(id);
    }
};

onMounted(() => {
    fetchWithdrawals(1);
});
</script>
