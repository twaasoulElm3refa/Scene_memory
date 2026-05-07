<template>
    <section class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <RouterLink
                :to="{ name: 'creator-withdrawals', params: { lang: currentLang } }"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            >
                <span aria-hidden="true">&larr;</span>
                <span>Back to Withdrawals</span>
            </RouterLink>

            <div class="flex flex-wrap items-center gap-2">
                <RouterLink
                    v-if="withdrawal && canEdit"
                    :to="{ name: 'creator-withdrawals-edit', params: { lang: currentLang, id: withdrawal.id } }"
                    class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100"
                >
                    Edit
                </RouterLink>
                <button
                    v-if="withdrawal && canDelete"
                    type="button"
                    class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 disabled:opacity-50"
                    :disabled="deleting"
                    @click="deleteWithdrawal"
                >
                    {{ deleting ? 'Deleting...' : 'Delete' }}
                </button>
            </div>
        </header>

        <section v-if="loading" class="rounded-3xl border border-slate-200 bg-white p-10 shadow-sm">
            <div class="flex items-center justify-center gap-3 text-slate-600">
                <div class="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-slate-900"></div>
                <p class="text-sm font-semibold">Loading withdrawal details...</p>
            </div>
        </section>

        <section v-else-if="error" class="rounded-3xl border border-rose-200 bg-rose-50 p-6 text-rose-700" role="alert">
            <p class="text-base font-bold">Failed to load withdrawal details.</p>
            <p class="mt-1 text-sm">{{ error }}</p>
            <button
                type="button"
                class="mt-4 rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700"
                @click="fetchDetails"
            >
                Retry
            </button>
        </section>

        <section v-else-if="!withdrawal" class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
            <p class="text-lg font-bold text-slate-800">Withdrawal not found</p>
            <p class="mt-2 text-sm text-slate-500">No data available for this withdrawal id.</p>
        </section>

        <article v-else class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Withdrawal ID</p>
                        <h1 class="mt-2 text-3xl font-black text-slate-950">#{{ withdrawal.id }}</h1>
                    </div>

                    <span class="rounded-full px-4 py-2 text-xs font-bold capitalize" :class="statusClass(withdrawal.status)">
                        {{ withdrawal.status || 'unknown' }}
                    </span>
                </div>

                <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</dt>
                        <dd class="mt-1 text-lg font-black text-slate-900">{{ currency(withdrawal.amount, withdrawal.currency) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fee</dt>
                        <dd class="mt-1 text-lg font-black text-slate-900">{{ currency(withdrawal.fee, withdrawal.currency) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Net Amount</dt>
                        <dd class="mt-1 text-lg font-black text-slate-900">{{ currency(withdrawal.net_amount, withdrawal.currency) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Method</dt>
                        <dd class="mt-1 text-slate-800">{{ withdrawal.method || 'N/A' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reference</dt>
                        <dd class="mt-1 break-all text-slate-800">{{ withdrawal.reference || 'N/A' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Transaction ID</dt>
                        <dd class="mt-1 break-all text-slate-800">{{ withdrawal.transaction_id || 'N/A' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Created At</dt>
                        <dd class="mt-1 text-slate-800">{{ formatDate(withdrawal.created_at) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Updated At</dt>
                        <dd class="mt-1 text-slate-800">{{ formatDate(withdrawal.updated_at) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Processed At</dt>
                        <dd class="mt-1 text-slate-800">{{ formatDate(withdrawal.processed_at) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Payment Details</h2>

                <div v-if="paymentDetailsEntries.length" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div v-for="item in paymentDetailsEntries" :key="item.key" class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ item.key }}</p>
                        <p class="mt-1 break-words text-sm text-slate-800">{{ item.value }}</p>
                    </div>
                </div>

                <p v-else class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                    No payment details provided.
                </p>
            </section>
        </article>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import toastr from "toastr";
import withdrawalServices from "@/services/withdrawals/withdrawalServices";

const route = useRoute();
const router = useRouter();
const { locale } = useI18n();

const loading = ref(false);
const deleting = ref(false);
const error = ref("");
const withdrawal = ref(null);

const currentLang = computed(() => String(route.params.lang || localStorage.getItem("lang") || "en").toLowerCase());
const withdrawalId = computed(() => Number(route.params.id || 0));

const canEdit = computed(() => ["pending"].includes(String(withdrawal.value?.status || "").toLowerCase()));

const canDelete = computed(() => {
    const key = String(withdrawal.value?.status || "").toLowerCase();
    return key !== "processing" && key !== "completed";
});

const paymentDetailsEntries = computed(() => {
    const details = withdrawal.value?.payment_details;

    if (!details || typeof details !== "object" || Array.isArray(details)) {
        return [];
    }

    return Object.entries(details).map(([key, value]) => ({
        key,
        value: value === null || value === undefined || value === "" ? "N/A" : String(value),
    }));
});

const statusClass = (status) => {
    const key = String(status || "").toLowerCase();

    if (key === "completed") return "bg-emerald-100 text-emerald-700";
    if (key === "pending" || key === "processing") return "bg-amber-100 text-amber-700";
    if (key === "rejected") return "bg-rose-100 text-rose-700";
    if (key === "cancelled") return "bg-slate-200 text-slate-700";

    return "bg-slate-100 text-slate-700";
};

const currency = (value, currencyCode) => {
    const amount = Number(value || 0);
    const code = String(currencyCode || "EGP").toUpperCase();

    if (!Number.isFinite(amount)) return `0.00 ${code}`;

    return `${amount.toFixed(2)} ${code}`;
};

const formatDate = (value) => {
    if (!value) return "N/A";

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return String(value);

    return date.toLocaleString(locale.value || currentLang.value || "en");
};

const fetchDetails = async () => {
    if (!withdrawalId.value) {
        error.value = "Invalid withdrawal id.";
        withdrawal.value = null;
        return;
    }

    loading.value = true;
    error.value = "";

    try {
        const payload = await withdrawalServices.showWithdrawals(withdrawalId.value);
        withdrawal.value = payload || null;

        if (!withdrawal.value) {
            error.value = "No withdrawal data returned from API.";
        }
    } catch (err) {
        withdrawal.value = null;
        error.value = err?.message || "Failed to fetch withdrawal details.";
    } finally {
        loading.value = false;
    }
};

const deleteWithdrawal = async () => {
    if (!withdrawal.value?.id) return;

    const confirmed = window.confirm("Delete this withdrawal request?");
    if (!confirmed) return;

    deleting.value = true;

    try {
        await withdrawalServices.deleteWithdrawals(withdrawal.value.id);
        toastr.success("Withdrawal deleted successfully.");
        await router.push({ name: "creator-withdrawals", params: { lang: currentLang.value } });
    } catch (err) {
        toastr.error(err?.message || "Failed to delete withdrawal.");
    } finally {
        deleting.value = false;
    }
};

onMounted(fetchDetails);
</script>
