<template>
    <AdminLayout>
        <div class="p-6 max-w-7xl mx-auto">
            <!-- Header Section -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent">
                            Plan Details
                        </h1>
                        <p class="text-gray-500 mt-2">View and manage plan information</p>
                    </div>
                    <button
                        @click="goBack"
                        class="flex items-center gap-2 px-4 py-2 text-gray-600 hover:text-gray-900 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back
                    </button>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-500 border-t-transparent"></div>
                    <p class="mt-4 text-gray-500">Loading plan details...</p>
                </div>
            </div>

            <!-- Plan Content -->
            <div v-else-if="plan">
                <!-- Main Card -->
                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                    <!-- Plan Header -->
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 px-8 py-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-2xl font-bold text-white">{{ plan.name }}</h2>
                                <p class="text-blue-100 mt-1">Plan ID: {{ plan.id }}</p>
                            </div>
                            <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                                <span class="text-white font-semibold">Active Plan</span>
                            </div>
                        </div>
                    </div>

                    <!-- Plan Details Grid -->
                    <div class="p-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Price Section -->
                            <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-6">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="p-2 bg-blue-100 rounded-lg">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 uppercase tracking-wide">Price</p>
                                        <p class="text-3xl font-bold text-gray-900">${{ formatPrice(plan.price) }}</p>
                                        <p class="text-sm text-gray-500">per month</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Section -->
                            <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-6">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="p-2 bg-green-100 rounded-lg">
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 uppercase tracking-wide">Status</p>
                                        <p class="text-xl font-semibold text-gray-900">Active</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Created At -->
                            <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-6">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="p-2 bg-purple-100 rounded-lg">
                                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 uppercase tracking-wide">Created At</p>
                                        <p class="text-lg font-semibold text-gray-900">{{ formatDate(plan.created_at) }}</p>
                                        <p class="text-sm text-gray-500">{{ formatTime(plan.created_at) }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Last Updated -->
                            <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-6">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="p-2 bg-orange-100 rounded-lg">
                                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 uppercase tracking-wide">Last Updated</p>
                                        <p class="text-lg font-semibold text-gray-900">{{ formatDate(plan.updated_at) }}</p>
                                        <p class="text-sm text-gray-500">{{ formatTime(plan.updated_at) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Info Section (if needed) -->
                        <div v-if="plan.description" class="mt-8">
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-3">Description</h3>
                                <p class="text-gray-600">{{ plan.description }}</p>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-8 flex gap-4">
                            <button
                                @click="editPlan"
                                class="flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-all duration-200 shadow-md hover:shadow-lg"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Edit Plan
                            </button>
                            <button
                                @click="deletePlan"
                                class="flex items-center gap-2 px-6 py-3 bg-red-50 hover:bg-red-100 text-red-600 font-semibold rounded-lg transition-all duration-200"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Delete Plan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Not Found State -->
            <div v-else class="text-center py-12">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-gray-100 rounded-full mb-6">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Plan Not Found</h3>
                <p class="text-gray-500">The plan you're looking for doesn't exist or has been removed.</p>
                <button
                    @click="goBack"
                    class="mt-6 inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Plans
                </button>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import AdminLayout from "../../../layouts/AdminLayout.vue";
import PlanService from '@/services/admin/licenses/planService';
import dayjs from 'dayjs';

const router = useRouter();
const route = useRoute();

const plan = ref(null);
const loading = ref(false);

const fetchPlan = async () => {
    loading.value = true;
    try {
        const response = await PlanService.getSingle(route.params.id);
        plan.value = response.data[0] || null;
    } catch (err) {
        console.error('Error fetching plan:', err);
        // Show error notification here if you have a toast system
    } finally {
        loading.value = false;
    }
};

const formatDate = (date) => dayjs(date).format('DD MMMM YYYY');
const formatTime = (date) => dayjs(date).format('HH:mm');
const formatPrice = (price) => Number(price).toFixed(2);

const goBack = () => {
    router.back();
};

const editPlan = () => {
    // Navigate to edit page
    router.push(`/admin/plans/${plan.value.id}/edit`);
};

const deletePlan = async () => {
    if (confirm('Are you sure you want to delete this plan? This action cannot be undone.')) {
        try {
            await PlanService.delete(plan.value.id);
            // Show success notification
            router.push('/admin/plans');
        } catch (err) {
            console.error('Error deleting plan:', err);
            // Show error notification
        }
    }
};

onMounted(() => {
    fetchPlan();
});
</script>

<style scoped>
/* Optional: Add custom animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.bg-white {
    animation: fadeIn 0.5s ease-out;
}
</style>
