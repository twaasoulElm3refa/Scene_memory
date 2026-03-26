<template>
    <AdminLayout>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">All Plans</h3>
            <a href="/admin/plans/create"
                class="bg-blue-500 hover:bg-blue-600 text-white px-4 text-decoration-none py-2 rounded">
                Add Plan
            </a>
        </div>

        <div v-if="loading">Loading plans...</div>

        <div v-else>
            <table class="min-w-full border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border px-4 py-2">ID</th>
                        <th class="border px-4 py-2">Name</th>
                        <th class="border px-4 py-2">Price</th>
                        <th class="border px-4 py-2">Created</th>
                        <th class="border px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="plan in plans" :key="plan.id">
                        <td class="border px-4 py-2">{{ plan.id }}</td>
                        <td class="border px-4 py-2">{{ plan.name }}</td>
                        <td class="border px-4 py-2">${{ plan.price }}</td>
                        <td class="border px-4 py-2">{{ formatDate(plan.created_at) }}</td>
                        <td class="border px-4 py-2 flex gap-2">
                            <!-- Show Plan -->
                            <router-link :to="`/admin/plans/${plan.id}`"
                                class="bg-green-500 hover:bg-green-600 text-decoration-none text-white px-2 py-1 rounded" title="View Plan">
                                👁️
                            </router-link>

                            <!-- تعديل -->
                            <button class="bg-yellow-400 hover:bg-yellow-500 text-white px-2 py-1 rounded"
                                @click="openEditModal(plan)">
                                ✏️
                            </button>

                            <!-- حذف -->
                            <button class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded"
                                @click="deletePlan(plan.id)">
                                🗑️
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal تعديل الباقة -->
        <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-96">
                <h3 class="text-lg font-bold mb-4">Edit Plan</h3>
                <div class="mb-4">
                    <label class="block mb-1">Name</label>
                    <input v-model="editPlan.translation.name" type="text" class="w-full border px-2 py-1 rounded" />
                </div>
                <div class="mb-4">
                    <label class="block mb-1">Price</label>
                    <input v-model="editPlan.price" type="number" class="w-full border px-2 py-1 rounded" />
                </div>
                <div class="flex justify-end gap-2">
                    <button class="bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded" @click="closeModal">
                        Cancel
                    </button>
                    <button class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded" @click="updatePlan()">
                        Save
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminLayout from "../../../layouts/AdminLayout.vue";
import PlanService from '@/services/admin/licenses/planService';
import dayjs from 'dayjs';

const plans = ref([]);
const loading = ref(false);

// modal
const showModal = ref(false);
const editPlan = ref({});

// جلب الباقات
const fetchPlans = async () => {
    loading.value = true;
    try {
        const response = await PlanService.getAll();
        plans.value = response.data;
    } catch (err) {
        console.error('Error fetching plans:', err);
    } finally {
        loading.value = false;
    }
};

// فورمات التاريخ
const formatDate = (date) => {
    return dayjs(date).format('DD-MM-YYYY');
};

// فتح modal تعديل
const openEditModal = (plan) => {
    editPlan.value = { ...plan, translation: { ...plan.translation } };
    showModal.value = true;
};

// إغلاق modal
const closeModal = () => {
    showModal.value = false;
    editPlan.value = {};
};

// تحديث باقة
const updatePlan = async () => {
    try {
        await PlanService.update(editPlan.value.id, {
            name: editPlan.value.translation.name,
            price: editPlan.value.price,
        });
        fetchPlans();
        closeModal();
        window.location.reload();
    } catch (err) {
        console.error('Error updating plan:', err);
    }
};

// حذف باقة
const deletePlan = async (id) => {
    if (!confirm('Are you sure you want to delete this plan?')) return;
    try {
        await PlanService.delete(id);
        fetchPlans();
        window.location.reload();

    } catch (err) {
        console.error('Error deleting plan:', err);
    }
};

onMounted(() => {
    fetchPlans();
});
</script>
