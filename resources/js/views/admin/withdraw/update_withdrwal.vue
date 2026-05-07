<template>
    <AdminLayout>
        <div class="mx-auto max-w-4xl py-6">
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
                        <h1 class="text-2xl font-semibold text-gray-900">Update Withdrawal</h1>
                        <p class="text-sm text-gray-500">ID: #{{ route.params.id }}</p>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    @click="goToDetails"
                >
                    Open Details
                </button>
            </div>

            <div v-if="loading" class="rounded-xl border border-gray-100 bg-white p-10 text-center">
                <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-gray-200 border-t-blue-500"></div>
                <p class="mt-3 text-sm text-gray-500">Loading withdrawal...</p>
            </div>

            <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                {{ error }}
            </div>

            <form v-else class="space-y-6" @submit.prevent="submitUpdate">
                <section class="rounded-xl border border-gray-100 bg-white p-5">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Editable Fields</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Amount</span>
                            <input v-model="form.amount" type="number" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-400" />
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Fee</span>
                            <input v-model="form.fee" type="number" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-400" />
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Currency</span>
                            <input v-model="form.currency" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-400" />
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Status</span>
                            <select v-model="form.status" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-400">
                                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Method</span>
                            <input v-model="form.method" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-400" />
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Reference</span>
                            <input v-model="form.reference" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-400" />
                        </label>

                        <label class="block md:col-span-2">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Admin Note</span>
                            <textarea v-model="form.admin_note" rows="4" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-400"></textarea>
                        </label>

                        <label class="block md:col-span-2">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Payment Details (JSON)</span>
                            <textarea v-model="form.payment_details" rows="6" class="w-full rounded-lg border border-gray-200 px-3 py-2 font-mono text-xs outline-none focus:border-blue-400"></textarea>
                        </label>

                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 md:col-span-2">
                            <input v-model="form.mail_sent" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                            Mail sent
                        </label>
                    </div>
                </section>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                        :disabled="submitting"
                    >
                        {{ submitting ? 'Saving...' : 'Save Changes' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="resetForm"
                    >
                        Reset
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import toastr from "toastr";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { withdrawalServices } from "../../../services/admin/withdrawals/withdrawalServices";

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const submitting = ref(false);
const error = ref("");
const original = ref(null);

const statuses = ["pending", "processing", "approved", "rejected", "paid"];

const form = reactive({
    amount: "",
    fee: "",
    currency: "",
    status: "pending",
    method: "",
    reference: "",
    admin_note: "",
    payment_details: "",
    mail_sent: false,
});

const normalizeItem = (payload) => {
    return payload?.data?.data || payload?.data || payload || null;
};

const fillForm = (item) => {
    form.amount = item?.amount ?? "";
    form.fee = item?.fee ?? "";
    form.currency = item?.currency ?? "";
    form.status = item?.status || "pending";
    form.method = item?.method || "";
    form.reference = item?.reference || "";
    form.admin_note = item?.admin_note || "";
    form.mail_sent = item?.mail_sent === true || item?.mail_sent === 1 || item?.mail_sent === "1";

    const rawPayment = item?.payment_details;
    if (!rawPayment) {
        form.payment_details = "";
    } else if (typeof rawPayment === "object") {
        form.payment_details = JSON.stringify(rawPayment, null, 2);
    } else {
        form.payment_details = String(rawPayment);
    }
};

const fetchDetails = async () => {
    loading.value = true;
    error.value = "";

    try {
        const response = await withdrawalServices.getById(route.params.id);
        const item = normalizeItem(response?.data);
        original.value = item;

        if (!item) {
            error.value = "Withdrawal not found.";
            return;
        }

        fillForm(item);
    } catch (err) {
        error.value = err?.response?.data?.message || err?.message || "Failed to load withdrawal.";
    } finally {
        loading.value = false;
    }
};

const buildPayload = () => {
    let parsedPaymentDetails = null;

    if (form.payment_details && form.payment_details.trim()) {
        try {
            parsedPaymentDetails = JSON.parse(form.payment_details);
        } catch {
            throw new Error("Payment details must be valid JSON.");
        }
    }

    return {
        amount: form.amount === "" ? 0 : Number(form.amount),
        fee: form.fee === "" ? 0 : Number(form.fee),
        currency: form.currency,
        status: form.status,
        method: form.method || null,
        reference: form.reference || null,
        admin_note: form.admin_note || null,
        mail_sent: form.mail_sent,
        payment_details: parsedPaymentDetails,
    };
};

const submitUpdate = async () => {
    submitting.value = true;

    try {
        const payload = buildPayload();
        await withdrawalServices.update(route.params.id, payload);
        toastr.success("Withdrawal updated successfully.");
        await fetchDetails();
    } catch (err) {
        toastr.error(err?.response?.data?.message || err?.message || "Update failed");
    } finally {
        submitting.value = false;
    }
};

const resetForm = () => {
    if (original.value) {
        fillForm(original.value);
    }
};

const goBack = () => router.push("/admin/purchases/withdrawls");
const goToDetails = () => router.push(`/admin/purchases/withdrawls/${route.params.id}`);

onMounted(fetchDetails);
</script>
