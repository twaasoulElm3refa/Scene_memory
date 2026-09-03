<template>
    <AdminLayout>
        <div class="min-h-screen bg-gray-50 p-4 md:p-6">
            <div v-if="loading" class="flex items-center justify-center min-h-[60vh]">
                <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
            </div>

            <div v-else-if="error" class="max-w-4xl mx-auto bg-white rounded-2xl shadow border border-red-100 p-8 text-center text-red-700">
                {{ error }}
            </div>

            <div v-else class="max-w-5xl mx-auto space-y-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-wrap items-center justify-between gap-3">
                    <button
                        type="button"
                        class="flex items-center gap-2 text-gray-600 hover:text-blue-600 transition-colors font-medium text-sm"
                        @click="$router.back()"
                    >
                        Back
                    </button>

                    <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide" :class="statusClass(item.status)">
                        {{ item.status }}
                    </span>
                </div>

                <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Request #{{ item.id }}</p>
                            <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ item.event_name }}</h1>
                        </div>
                        <div class="text-sm text-gray-500">
                            Submitted {{ formatDate(item.created_at) }}
                        </div>
                    </div>

                    <p class="mt-5 whitespace-pre-wrap text-gray-700 leading-relaxed">{{ item.event_description }}</p>

                    <dl class="mt-5 grid grid-cols-1 gap-4 border-t border-gray-100 pt-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-gray-500">{{ $t("homeAudit.specialCoverage.modal.country") }}</dt>
                            <dd class="font-semibold text-gray-900">{{ locationName(item.country) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ $t("homeAudit.specialCoverage.modal.city") }}</dt>
                            <dd class="font-semibold text-gray-900">{{ locationName(item.city) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ $t("homeAudit.specialCoverage.modal.startDate") }}</dt>
                            <dd class="font-semibold text-gray-900">{{ formatDateOnly(item.start_date) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ $t("homeAudit.specialCoverage.modal.eventType") }}</dt>
                            <dd class="font-semibold text-gray-900">{{ eventTypeLabel(item.event_type) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-bold text-gray-900 mb-4">User</h2>
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-gray-500">Name</dt>
                                <dd class="font-semibold text-gray-900">{{ item.user?.name || "-" }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Email</dt>
                                <dd class="font-semibold text-gray-900">{{ item.user?.email || "-" }}</dd>
                            </div>
                            <div v-if="item.user?.phone">
                                <dt class="text-gray-500">Phone</dt>
                                <dd class="font-semibold text-gray-900">{{ item.user.phone }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-bold text-gray-900 mb-4">Review</h2>
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-gray-500">Status</dt>
                                <dd class="font-semibold capitalize text-gray-900">{{ item.status }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Reviewed By</dt>
                                <dd class="font-semibold text-gray-900">{{ item.reviewer?.name || "-" }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Reviewed At</dt>
                                <dd class="font-semibold text-gray-900">{{ formatDate(item.reviewed_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section v-if="item.rejection_reason" class="bg-white rounded-xl shadow-sm border border-red-100 p-5">
                    <h2 class="text-sm font-bold text-red-700 mb-2">Rejection Reason</h2>
                    <p class="whitespace-pre-wrap text-gray-700">{{ item.rejection_reason }}</p>
                </section>

                <section v-if="isPending" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div v-if="showRejectReason" class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="special-coverage-rejection-reason">
                            Rejection Reason
                        </label>
                        <textarea
                            id="special-coverage-rejection-reason"
                            v-model="rejectReason"
                            rows="4"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent resize-y"
                            placeholder="Please enter the reason why this request is being rejected..."
                        ></textarea>
                        <p v-if="rejectReasonError" class="mt-2 text-sm text-red-600">{{ rejectReasonError }}</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <button
                            type="button"
                            class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 px-8 rounded-full min-w-[180px] disabled:opacity-60 disabled:cursor-not-allowed"
                            :disabled="actionLoading"
                            @click="approveRequest"
                        >
                            {{ actionLoading ? "Processing..." : "Approve" }}
                        </button>

                        <button
                            v-if="!showRejectReason"
                            type="button"
                            class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-8 rounded-full min-w-[180px] disabled:opacity-60 disabled:cursor-not-allowed"
                            :disabled="actionLoading"
                            @click="showRejectReason = true"
                        >
                            Reject
                        </button>

                        <button
                            v-else
                            type="button"
                            class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-8 rounded-full min-w-[180px] disabled:opacity-60 disabled:cursor-not-allowed"
                            :disabled="actionLoading || !rejectReason.trim()"
                            @click="rejectRequest"
                        >
                            {{ actionLoading ? "Processing..." : "Confirm Rejection" }}
                        </button>

                        <button
                            v-if="showRejectReason"
                            type="button"
                            class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2.5 px-8 rounded-full min-w-[180px]"
                            @click="cancelReject"
                        >
                            Cancel
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import toastr from "toastr";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { specialCoverageRequestsService } from "../../../services/admin/specialCoverageRequestsService";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const item = ref(null);
const loading = ref(true);
const error = ref("");
const actionLoading = ref(false);
const showRejectReason = ref(false);
const rejectReason = ref("");
const rejectReasonError = ref("");

const isPending = computed(() => item.value?.status === "pending");

async function fetchRequest() {
    loading.value = true;
    error.value = "";

    try {
        const response = await specialCoverageRequestsService.getSingle(route.params.id);
        item.value = response.data.data;
    } catch (err) {
        error.value = err.response?.data?.message || "Failed to load request details.";
    } finally {
        loading.value = false;
    }
}

async function approveRequest() {
    if (!window.confirm("Approve this special coverage request?")) return;

    actionLoading.value = true;

    try {
        const response = await specialCoverageRequestsService.approve(route.params.id);
        item.value = response.data.data;
        toastr.success("Special coverage request approved successfully.");
        await router.push("/admin/special-coverage");
    } catch (err) {
        toastr.error(err.response?.data?.message || "Failed to approve request.");
    } finally {
        actionLoading.value = false;
    }
}

async function rejectRequest() {
    const reason = rejectReason.value.trim();
    rejectReasonError.value = "";

    if (!reason) {
        rejectReasonError.value = "Please provide a rejection reason.";
        return;
    }

    if (!window.confirm("Reject this special coverage request?")) return;

    actionLoading.value = true;

    try {
        const response = await specialCoverageRequestsService.reject(route.params.id, { reason });
        item.value = response.data.data;
        toastr.success("Special coverage request rejected successfully.");
        await router.push("/admin/special-coverage");
    } catch (err) {
        rejectReasonError.value = err.response?.data?.errors?.reason?.[0] || "";
        toastr.error(err.response?.data?.message || "Failed to reject request.");
    } finally {
        actionLoading.value = false;
    }
}

function cancelReject() {
    showRejectReason.value = false;
    rejectReason.value = "";
    rejectReasonError.value = "";
}

function statusClass(status) {
    return {
        pending: "bg-amber-100 text-amber-700",
        approved: "bg-green-100 text-green-700",
        rejected: "bg-red-100 text-red-700",
    }[status] || "bg-gray-100 text-gray-700";
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

function formatDateOnly(dateString) {
    if (!dateString) return "-";

    return new Date(`${dateString}T00:00:00`).toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
}

function locationName(location) {
    return location?.translation?.name || location?.name || "-";
}

function eventTypeLabel(type) {
    if (type === "personal") return t("homeAudit.specialCoverage.modal.personalEvent");
    if (type === "public") return t("homeAudit.specialCoverage.modal.publicEvent");

    return "-";
}

onMounted(fetchRequest);
</script>
