<template>
    <section class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <RouterLink
                :to="{ name: 'creator-withdrawals-show', params: { lang: currentLang, id: withdrawalId } }"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            >
                <span aria-hidden="true">&larr;</span>
                <span>Back to Details</span>
            </RouterLink>
        </header>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-black text-slate-950">Update Withdrawal</h1>
            <p class="mt-2 text-sm text-slate-500">Only pending withdrawals can be edited.</p>

            <div v-if="loading" class="mt-6 flex items-center justify-center gap-3 text-slate-600">
                <div class="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-slate-900"></div>
                <p class="text-sm font-semibold">Loading withdrawal...</p>
            </div>

            <div v-else-if="error" class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ error }}
            </div>

            <form v-else class="mt-6 space-y-5" @submit.prevent="submitUpdate">
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
                    ></textarea>
                    <p v-if="fieldErrors.payment_details" class="mt-1 text-xs text-rose-600">{{ fieldErrors.payment_details }}</p>
                </div>

                <p v-if="submitError" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ submitError }}</p>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:opacity-50"
                        :disabled="submitting || !canEdit"
                    >
                        {{ submitting ? 'Saving...' : 'Save Changes' }}
                    </button>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="resetForm"
                    >
                        Reset
                    </button>
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

const loading = ref(false);
const submitting = ref(false);
const error = ref("");
const submitError = ref("");
const original = ref(null);
const fieldErrors = reactive({});

const form = reactive({
    amount: "",
    fee: "",
    currency: "",
    method: "",
    payment_details: "",
    reference: "",
    transaction_id: "",
});

const currentLang = computed(() => String(route.params.lang || localStorage.getItem("lang") || "en").toLowerCase());
const withdrawalId = computed(() => Number(route.params.id || 0));
const canEdit = computed(() => ["pending"].includes(String(original.value?.status || "").toLowerCase()));

const resetFieldErrors = () => {
    Object.keys(fieldErrors).forEach((key) => {
        delete fieldErrors[key];
    });
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

const fillForm = (item) => {
    form.amount = item?.amount ?? "";
    form.fee = item?.fee ?? "";
    form.currency = item?.currency || "";
    form.method = item?.method || "";
    form.reference = item?.reference || "";
    form.transaction_id = item?.transaction_id || "";

    if (item?.payment_details && typeof item.payment_details === "object") {
        form.payment_details = JSON.stringify(item.payment_details, null, 2);
    } else {
        form.payment_details = "";
    }
};

const fetchDetails = async () => {
    if (!withdrawalId.value) {
        error.value = "Invalid withdrawal id.";
        return;
    }

    loading.value = true;
    error.value = "";

    try {
        const payload = await withdrawalServices.showWithdrawals(withdrawalId.value);
        original.value = payload || null;

        if (!original.value) {
            error.value = "Withdrawal not found.";
            return;
        }

        fillForm(original.value);

        if (!canEdit.value) {
            submitError.value = "Only pending withdrawals can be edited.";
        }
    } catch (err) {
        error.value = err?.message || "Failed to load withdrawal details.";
    } finally {
        loading.value = false;
    }
};

const buildPayload = () => {
    const payload = {
        amount: Number(form.amount),
        fee: Number(form.fee || 0),
        currency: form.currency || null,
        method: form.method || null,
        reference: form.reference || null,
        transaction_id: form.transaction_id || null,
    };

    if (form.payment_details && form.payment_details.trim()) {
        payload.payment_details = JSON.parse(form.payment_details);
    }

    return payload;
};

const submitUpdate = async () => {
    submitError.value = "";
    resetFieldErrors();

    if (!canEdit.value) {
        submitError.value = "Only pending withdrawals can be edited.";
        return;
    }

    submitting.value = true;

    try {
        const payload = buildPayload();
        await withdrawalServices.updateWithdrawals(withdrawalId.value, payload);
        toastr.success("Withdrawal updated successfully.");
        await router.push({ name: "creator-withdrawals-show", params: { lang: currentLang.value, id: withdrawalId.value } });
    } catch (err) {
        if (err instanceof SyntaxError) {
            submitError.value = "Payment details must be valid JSON.";
        } else {
            submitError.value = err?.message || "Failed to update withdrawal.";
            setValidationErrors(err?.validation);
        }
    } finally {
        submitting.value = false;
    }
};

const resetForm = () => {
    if (original.value) {
        fillForm(original.value);
        submitError.value = "";
        resetFieldErrors();
    }
};

onMounted(fetchDetails);
</script>
