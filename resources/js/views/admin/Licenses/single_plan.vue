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
                    <button @click="goBack" class="flex items-center gap-2 px-4 py-2 text-gray-600 hover:text-gray-900 transition-colors">
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

                    <!-- Plan Details Grid + Description + Advantages -->
                    <div class="p-8">
                        <!-- ... نفس الـ Grid السابق (Price, Status, Created, Updated) ... -->
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

                            <!-- Status, Created At, Last Updated sections (نفس الكود السابق) -->
                            <!-- ... (مختصر هنا للتوفير) ... -->
                        </div>

                        <!-- Description -->
                        <div v-if="plan.description" class="mt-8">
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-3">Description</h3>
                                <p class="text-gray-600">{{ plan.description }}</p>
                            </div>
                        </div>

                        <!-- Advantages Section -->
                        <div class="mt-8">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    Plan Advantages
                                    <span class="text-sm font-normal text-gray-500">({{ plan.advantges?.length || 0 }})</span>
                                </h3>
                                <button
                                    @click="openAddModal"
                                    class="flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-all"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Advantage
                                </button>
                            </div>

                            <div v-if="plan.advantges && plan.advantges.length > 0" class="bg-gray-50 rounded-xl p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div
                                        v-for="advantage in plan.advantges"
                                        :key="advantage.id"
                                        class="flex items-start justify-between bg-white rounded-lg p-4 border border-gray-100 hover:border-blue-200 transition-all group"
                                    >
                                        <div class="flex items-start gap-3 flex-1">
                                            <div class="mt-0.5 flex-shrink-0">
                                                <div class="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <p class="text-gray-700 leading-relaxed">{{ advantage.feature }}</p>
                                        </div>

                                        <!-- Action Buttons for each advantage -->
                                        <div class="flex gap-2 opacity-70 group-hover:opacity-100 transition-opacity">
                                            <button
                                                @click="editAdvantage(advantage)"
                                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                title="Edit"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="deleteAdvantage(advantage.id)"
                                                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Delete"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="bg-gray-50 rounded-xl p-8 text-center text-gray-500">
                                No advantages added yet.
                            </div>
                        </div>

                        <!-- Action Buttons for the Plan -->
                        <div class="mt-10 flex gap-4">
                            <button @click="editPlan" class="flex-1 md:flex-none flex items-center justify-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-all">
                                Edit Plan
                            </button>
                            <button @click="deletePlan" class="flex-1 md:flex-none flex items-center justify-center gap-2 px-6 py-3 bg-red-50 hover:bg-red-100 text-red-600 font-semibold rounded-lg transition-all">
                                Delete Plan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Not Found -->
            <div v-else class="text-center py-12"> ... (نفس الكود السابق) </div>

            <!-- Add Advantage Modal -->
            <div v-if="showAddModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
                    <div class="px-6 py-5 border-b">
                        <h3 class="text-xl font-semibold">Add New Advantage</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Feature</label>
                                <textarea
                                    v-model="newFeature"
                                    rows="3"
                                    class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500 resize-y"
                                    placeholder="Enter advantage feature..."
                                ></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t flex gap-3">
                        <button
                            @click="closeAddModal"
                            class="flex-1 py-3 text-gray-600 font-medium rounded-xl hover:bg-gray-100 transition"
                        >
                            Cancel
                        </button>
                        <button
                            @click="addAdvantage"
                            :disabled="!newFeature.trim()"
                            class="flex-1 py-3 bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white font-semibold rounded-xl transition"
                        >
                            Add Advantage
                        </button>
                    </div>
                </div>
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

const showAddModal = ref(false);
const newFeature = ref('');

// ====================== Fetch Plan ======================
const fetchPlan = async () => {
    loading.value = true;
    try {
        const response = await PlanService.getSingle(route.params.id);
        plan.value = response.data[0] || null;
    } catch (err) {
        console.error('Error fetching plan:', err);
    } finally {
        loading.value = false;
    }
};

// ====================== Advantages Functions ======================
const openAddModal = () => {
    newFeature.value = '';
    showAddModal.value = true;
};

const closeAddModal = () => {
    showAddModal.value = false;
};

const addAdvantage = async () => {
    if (!newFeature.value.trim()) return;

    try {
        await PlanService.createBenefit(route.params.id, { feature: newFeature.value.trim() });
        await fetchPlan();           // Refresh the plan data
        closeAddModal();
        // يمكنك إضافة Toast Success هنا
    } catch (err) {
        console.error('Error adding advantage:', err);
        // Toast Error
    }
};

const editAdvantage = (advantage) => {
    // يمكنك تطويره لاحقاً بمودال تعديل
    const newText = prompt('Edit advantage:', advantage.feature);
    if (newText && newText.trim() !== advantage.feature) {
        PlanService.updateBenefit(advantage.id, { feature: newText.trim() })
            .then(() => fetchPlan())
            .catch(err => console.error(err));
    }
};

const deleteAdvantage = async (id) => {
    if (!confirm('Are you sure you want to delete this advantage?')) return;

    try {
        await PlanService.deleteBenefit(id);
        await fetchPlan();   // Refresh list
    } catch (err) {
        console.error('Error deleting advantage:', err);
    }
};

// ====================== Plan Functions ======================
const formatDate = (date) => dayjs(date).format('DD MMMM YYYY');
const formatTime = (date) => dayjs(date).format('HH:mm');
const formatPrice = (price) => Number(price).toFixed(2);

const goBack = () => router.back();

const editPlan = () => router.push(`/admin/plans/${plan.value.id}/edit`);

const deletePlan = async () => {
    if (confirm('Are you sure you want to delete this plan?')) {
        try {
            await PlanService.delete(plan.value.id);
            router.push('/admin/plans');
        } catch (err) {
            console.error(err);
        }
    }
};

onMounted(() => {
    fetchPlan();
});
</script>
