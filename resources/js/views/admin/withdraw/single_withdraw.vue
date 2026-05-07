<template>
    <AdminLayout>
        <div class="mx-auto max-w-5xl py-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 hover:bg-gray-50"
                        @click="goBack"
                    >
                        <-
                    </button>
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">Withdrawal Details</h1>
                        <p class="text-sm text-gray-500">ID: #{{ withdrawal?.id || route.params.id }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button class="rounded-lg border border-indigo-200 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50" @click="goEdit">
                        Edit
                    </button>
                    <button
                        class="rounded-lg border border-green-200 px-3 py-2 text-sm font-medium text-green-700 hover:bg-green-50 disabled:opacity-50"
                        :disabled="loadingAction"
                        @click="approve"
                    >
                        Approve
                    </button>
                    <button
                        class="rounded-lg border border-amber-200 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50 disabled:opacity-50"
                        :disabled="loadingAction"
                        @click="reject"
                    >
                        Reject
                    </button>
                    <button
                        class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
                        :disabled="loadingAction"
                        @click="removeItem"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <div v-if="loading" class="rounded-xl border border-gray-100 bg-white p-10 text-center">
                <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-gray-200 border-t-blue-500"></div>
                <p class="mt-3 text-sm text-gray-500">Loading withdrawal details...</p>
            </div>

            <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                {{ error }}
            </div>

            <div v-else-if="!withdrawal" class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Withdrawal not found.
            </div>

            <div v-else class="space-y-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-gray-100 bg-white p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Amount</p>
                        <p class="mt-2 text-xl font-bold text-gray-900">{{ money(withdrawal.amount) }} {{ withdrawal.currency || '' }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Fee</p>
                        <p class="mt-2 text-xl font-bold text-gray-900">{{ money(withdrawal.fee) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Status</p>
                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(withdrawal.status)">
                            {{ withdrawal.status || 'unknown' }}
                        </span>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Processed At</p>
                        <p class="mt-2 text-sm font-medium text-gray-900">{{ formatDate(withdrawal.processed_at) }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <section class="rounded-xl border border-gray-100 bg-white p-5">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Withdrawal Info</h2>
                        <dl class="space-y-3 text-sm text-gray-700">
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Reference</dt><dd>{{ withdrawal.reference || 'N/A' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Method</dt><dd>{{ withdrawal.method || 'N/A' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Mail Sent</dt><dd>{{ toYesNo(withdrawal.mail_sent) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Created At</dt><dd>{{ formatDate(withdrawal.created_at) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Updated At</dt><dd>{{ formatDate(withdrawal.updated_at) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Deleted At</dt><dd>{{ formatDate(withdrawal.deleted_at) }}</dd></div>
                        </dl>
                    </section>

                    <section class="rounded-xl border border-gray-100 bg-white p-5">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Users</h2>
                        <dl class="space-y-3 text-sm text-gray-700">
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">User ID</dt><dd>{{ withdrawal.user_id ?? 'N/A' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">User Name</dt><dd>{{ withdrawal.user?.name || 'N/A' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">User Email</dt><dd>{{ withdrawal.user?.email || 'N/A' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Approved By</dt><dd>{{ withdrawal.approved_by ?? 'N/A' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Approved By Name</dt><dd>{{ withdrawal.approvedBy?.name || 'N/A' }}</dd></div>
                        </dl>
                    </section>
                </div>

                <section class="rounded-xl border border-gray-100 bg-white p-5">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Admin Note</h2>
                    <p class="whitespace-pre-line text-sm text-gray-700">{{ withdrawal.admin_note || 'No admin note.' }}</p>
                </section>

                <section class="rounded-xl border border-gray-100 bg-white p-5">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Payment Details</h2>
                    <pre class="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">{{ prettyPaymentDetails }}</pre>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import toastr from "toastr";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { withdrawalServices } from "../../../services/admin/withdrawals/withdrawalServices";

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const loadingAction = ref(false);
const error = ref("");
const withdrawal = ref(null);

const withdrawalId = computed(() => route.params.id);

const prettyPaymentDetails = computed(() => {
    const raw = withdrawal.value?.payment_details;
    if (!raw) return "N/A";
    if (typeof raw === "object") return JSON.stringify(raw, null, 2);

    try {
        return JSON.stringify(JSON.parse(raw), null, 2);
    } catch {
        return String(raw);
    }
});

const normalizeItem = (payload) => {
    return payload?.data?.data || payload?.data || payload || null;
};

const fetchDetails = async () => {
    loading.value = true;
    error.value = "";

    try {
        const response = await withdrawalServices.getById(withdrawalId.value);
        withdrawal.value = normalizeItem(response?.data);
        if (!withdrawal.value) {
            error.value = "Withdrawal not found.";
        }
    } catch (err) {
        withdrawal.value = null;
        error.value = err?.response?.data?.message || err?.message || "Failed to load withdrawal details.";
    } finally {
        loading.value = false;
    }
};

const runAction = async (fn, successMessage) => {
    loadingAction.value = true;
    try {
        const response = await fn(withdrawalId.value);
        const data = normalizeItem(response?.data);
        if (data) {
            withdrawal.value = { ...withdrawal.value, ...data };
        }
        toastr.success(successMessage);
    } catch (err) {
        toastr.error(err?.response?.data?.message || err?.message || "Action failed");
    } finally {
        loadingAction.value = false;
    }
};

const approve = () => runAction(withdrawalServices.approve, "Withdrawal approved.");
const reject = () => runAction(withdrawalServices.reject, "Withdrawal rejected.");

const removeItem = async () => {
    const confirmed = window.confirm("Delete this withdrawal request?");
    if (!confirmed) return;

    loadingAction.value = true;
    try {
        await withdrawalServices.delete(withdrawalId.value);
        toastr.success("Withdrawal deleted.");
        router.push("/admin/purchases/withdrawls");
    } catch (err) {
        toastr.error(err?.response?.data?.message || err?.message || "Delete failed");
    } finally {
        loadingAction.value = false;
    }
};

const goBack = () => router.push("/admin/purchases/withdrawls");
const goEdit = () => router.push(`/admin/purchases/withdrawls/${withdrawalId.value}/edit`);

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

const toYesNo = (value) => {
    return value === true || value === 1 || value === "1" ? "Yes" : "No";
};

const statusClass = (status) => {
    const key = String(status || "").toLowerCase();
    if (key === "approved" || key === "paid") return "bg-green-100 text-green-700";
    if (key === "pending" || key === "processing") return "bg-amber-100 text-amber-700";
    if (key === "rejected") return "bg-red-100 text-red-700";
    return "bg-gray-100 text-gray-700";
};

onMounted(fetchDetails);
</script>
