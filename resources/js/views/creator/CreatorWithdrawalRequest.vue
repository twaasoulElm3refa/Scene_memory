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
        </header>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-black text-slate-950">Request Withdrawal</h1>
            <p class="mt-2 text-sm text-slate-500">Submit a new withdrawal request from your current wallet balance.</p>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Wallet ID</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ wallet?.id || 'N/A' }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Available Balance</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ currency(wallet?.amount, wallet?.currency) }}</p>
                </div>
            </div>

            <form class="mt-6 space-y-5" @submit.prevent="submitRequest">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="amount" class="mb-2 block text-sm font-semibold text-slate-700">Amount *</label>
                        <input
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-500 focus:outline-none"
                            placeholder="0.00"
                        />
                        <p v-if="fieldErrors.amount" class="mt-1 text-xs text-rose-600">{{ fieldErrors.amount }}</p>
                    </div>

                    <div>
                        <label for="fee" class="mb-2 block text-sm font-semibold text-slate-700">Fee</label>
                        <input
                            id="fee"
                            v-model="form.fee"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-500 focus:outline-none"
                            placeholder="0.00"
                        />
                        <p v-if="fieldErrors.fee" class="mt-1 text-xs text-rose-600">{{ fieldErrors.fee }}</p>
                    </div>

                    <div>
                        <label for="currency" class="mb-2 block text-sm font-semibold text-slate-700">Currency</label>
                        <input
                            id="currency"
                            v-model="form.currency"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm uppercase focus:border-slate-500 focus:outline-none"
                            placeholder="EGP"
                        />
                        <p v-if="fieldErrors.currency" class="mt-1 text-xs text-rose-600">{{ fieldErrors.currency }}</p>
                    </div>

                    <div>
                        <label for="method" class="mb-2 block text-sm font-semibold text-slate-700">Method</label>
                        <input
                            id="method"
                            v-model="form.method"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-500 focus:outline-none"
                            placeholder="bank_transfer"
                        />
                        <p v-if="fieldErrors.method" class="mt-1 text-xs text-rose-600">{{ fieldErrors.method }}</p>
                    </div>

                    <div>
                        <label for="reference" class="mb-2 block text-sm font-semibold text-slate-700">Reference</label>
                        <input
                            id="reference"
                            v-model="form.reference"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-500 focus:outline-none"
                            placeholder="Optional reference"
                        />
                        <p v-if="fieldErrors.reference" class="mt-1 text-xs text-rose-600">{{ fieldErrors.reference }}</p>
                    </div>

                    <div>
                        <label for="transaction_id" class="mb-2 block text-sm font-semibold text-slate-700">Transaction ID</label>
                        <input
                            id="transaction_id"
                            v-model="form.transaction_id"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-500 focus:outline-none"
                            placeholder="Optional transaction id"
                        />
                        <p v-if="fieldErrors.transaction_id" class="mt-1 text-xs text-rose-600">{{ fieldErrors.transaction_id }}</p>
                    </div>
                </div>

                <div>
                    <label for="payment_details" class="mb-2 block text-sm font-semibold text-slate-700">Payment Details (JSON)</label>
                    <textarea
                        id="payment_details"
                        v-model="form.payment_details"
                        rows="6"
                        class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-500 focus:outline-none"
                        placeholder='{"bank_name":"Example Bank","account_number":"XXXX"}'
                    ></textarea>
                    <p v-if="fieldErrors.payment_details" class="mt-1 text-xs text-rose-600">{{ fieldErrors.payment_details }}</p>
                </div>

                <p v-if="error" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</p>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:opacity-50"
                        :disabled="submitting || !wallet?.id"
                    >
                        {{ submitting ? 'Submitting...' : 'Submit Request' }}
                    </button>

                    <RouterLink
                        :to="{ name: 'creator-withdrawals', params: { lang: currentLang } }"
                        class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Cancel
                    </RouterLink>
                </div>
            </form>
        </section>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import toastr from "toastr";
import withdrawalServices from "@/services/withdrawals/withdrawalServices";

const route = useRoute();
const router = useRouter();

const wallet = ref(null);
const submitting = ref(false);
const loadingWallet = ref(false);
const error = ref("");
const fieldErrors = reactive({});

const form = reactive({
    amount: "",
    fee: "0",
    currency: "",
    method: "",
    payment_details: "",
    reference: "",
    transaction_id: "",
});

const currentLang = computed(() => String(route.params.lang || localStorage.getItem("lang") || "en").toLowerCase());

const resetFieldErrors = () => {
    Object.keys(fieldErrors).forEach((key) => {
        delete fieldErrors[key];
    });
};

const currency = (value, currencyCode) => {
    const amount = Number(value || 0);
    const code = String(currencyCode || "EGP").toUpperCase();

    if (!Number.isFinite(amount)) return `0.00 ${code}`;

    return `${amount.toFixed(2)} ${code}`;
};

const setValidationErrors = (validation) => {
    resetFieldErrors();

    if (!validation || typeof validation !== "object") return;

    Object.entries(validation).forEach(([key, messages]) => {
        if (Array.isArray(messages) && messages.length) {
            fieldErrors[key] = String(messages[0]);
        } else if (messages) {
            fieldErrors[key] = String(messages);
        }
    });
};

const fetchWallet = async () => {
    loadingWallet.value = true;

    try {
        const payload = await withdrawalServices.getMyWallet();
        wallet.value = payload || null;

        if (!form.currency) {
            form.currency = wallet.value?.currency || "EGP";
        }
    } catch (err) {
        error.value = err?.message || "Failed to load wallet data.";
    } finally {
        loadingWallet.value = false;
    }
};

const buildPayload = () => {
    const payload = {
        amount: Number(form.amount),
        fee: Number(form.fee || 0),
        currency: form.currency || wallet.value?.currency || "EGP",
        method: form.method || null,
        reference: form.reference || null,
        transaction_id: form.transaction_id || null,
    };

    if (form.payment_details && form.payment_details.trim()) {
        payload.payment_details = JSON.parse(form.payment_details);
    }

    return payload;
};

const submitRequest = async () => {
    error.value = "";
    resetFieldErrors();

    if (!wallet.value?.id) {
        error.value = loadingWallet.value ? "Wallet data is still loading." : "Wallet not found.";
        return;
    }

    submitting.value = true;

    try {
        const payload = buildPayload();
        const created = await withdrawalServices.requestWithdrawals(wallet.value.id, payload);
        toastr.success("Withdrawal request submitted successfully.");

        const newId = created?.id;

        if (newId) {
            await router.push({ name: "creator-withdrawals-show", params: { lang: currentLang.value, id: newId } });
            return;
        }

        await router.push({ name: "creator-withdrawals", params: { lang: currentLang.value } });
    } catch (err) {
        if (err instanceof SyntaxError) {
            error.value = "Payment details must be valid JSON.";
        } else {
            error.value = err?.message || "Failed to submit withdrawal request.";
            setValidationErrors(err?.validation);
        }
    } finally {
        submitting.value = false;
    }
};

onMounted(fetchWallet);
</script>
