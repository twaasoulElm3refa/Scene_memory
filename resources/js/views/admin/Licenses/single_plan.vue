<template>
    <AdminLayout>
        <div class="p-6 max-w-7xl mx-auto">

            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold">Plan Details</h1>
                    <p class="text-gray-500">Manage your plan</p>
                </div>

                <button @click="goBack"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded">
                    Back
                </button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="text-center py-10">
                Loading...
            </div>

            <!-- Content -->
            <div v-else-if="plan" class="bg-white shadow rounded-xl p-6">

                <!-- Info -->
                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <p class="text-gray-500">Name</p>
                        <h2 class="text-xl font-bold">{{ plan.name }}</h2>
                    </div>

                    <div>
                        <p class="text-gray-500">Price</p>
                        <h2 class="text-xl font-bold">${{ formatPrice(plan.price) }}</h2>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 mb-6">
                    <button @click="openEditModal"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Edit
                    </button>

                    <button @click="deletePlan"
                        class="px-4 py-2 bg-red-100 text-red-600 rounded hover:bg-red-200">
                        Delete
                    </button>
                </div>

                <!-- Advantages -->
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold">
                            Features ({{ plan.advantges?.length || 0 }})
                        </h3>

                        <button @click="showAddModal = true"
                            class="px-3 py-1 bg-green-600 text-white rounded">
                            + Add
                        </button>
                    </div>

                    <div v-if="plan.advantges?.length">
                        <div v-for="adv in plan.advantges" :key="adv.id"
                            class="flex justify-between items-center border p-3 rounded mb-2">

                            <p>{{ adv.feature }}</p>

                            <div class="flex gap-2">
                                <button @click="editAdvantage(adv)"
                                    class="text-blue-600">✏️</button>

                                <button @click="deleteAdvantage(adv.id)"
                                    class="text-red-600">🗑️</button>
                            </div>
                        </div>
                    </div>

                    <p v-else class="text-gray-400">No features yet</p>
                </div>

            </div>
        </div>

        <!-- ================= EDIT PLAN MODAL ================= -->
        <div v-if="showEditModal"
            class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">

            <div class="bg-white p-6 rounded-xl w-96">
                <h3 class="text-lg font-bold mb-4">Edit Plan</h3>

                <input v-model="editForm.name"
                    class="w-full border p-2 mb-3 rounded"
                    placeholder="Name" />

                <input v-model="editForm.price"
                    type="number"
                    class="w-full border p-2 mb-3 rounded"
                    placeholder="Price" />

                <div class="flex justify-end gap-2">
                    <button @click="showEditModal=false"
                        class="px-3 py-1 bg-gray-200 rounded">
                        Cancel
                    </button>

                    <button @click="updatePlan"
                        class="px-3 py-1 bg-blue-600 text-white rounded">
                        Save
                    </button>
                </div>
            </div>
        </div>

        <!-- ================= ADD ADVANTAGE ================= -->
        <div v-if="showAddModal"
            class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">

            <div class="bg-white p-6 rounded-xl w-96">
                <h3 class="text-lg font-bold mb-4">Add Feature</h3>

                <textarea v-model="newFeature"
                    class="w-full border p-2 mb-3 rounded"
                    placeholder="Feature"></textarea>

                <div class="flex justify-end gap-2">
                    <button @click="showAddModal=false"
                        class="px-3 py-1 bg-gray-200 rounded">
                        Cancel
                    </button>

                    <button @click="addAdvantage"
                        class="px-3 py-1 bg-green-600 text-white rounded">
                        Add
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminLayout from "../../../layouts/AdminLayout.vue";
import PlanService from '@/services/admin/licenses/planService';

const route = useRoute();
const router = useRouter();

const plan = ref(null);
const loading = ref(false);

// modals
const showEditModal = ref(false);
const showAddModal = ref(false);

// forms
const editForm = ref({ name: '', price: '' });
const newFeature = ref('');

// ================= FETCH =================
const fetchPlan = async () => {
    loading.value = true;
    try {
        const res = await PlanService.getSingle(route.params.id);
        plan.value = res.data[0];
    } finally {
        loading.value = false;
    }
};

// ================= PLAN =================
const openEditModal = () => {
    editForm.value = {
        name: plan.value.name,
        price: plan.value.price
    };
    showEditModal.value = true;
};

const updatePlan = async () => {
    await PlanService.update(plan.value.id, editForm.value);
    await fetchPlan();
    showEditModal.value = false;
};

const deletePlan = async () => {
    if (!confirm('Delete this plan?')) return;
    await PlanService.delete(plan.value.id);
    router.push('/admin/plans');
};

// ================= ADVANTAGES =================
const addAdvantage = async () => {
    if (!newFeature.value.trim()) return;

    await PlanService.createBenefit(route.params.id, {
        feature: newFeature.value
    });

    newFeature.value = '';
    showAddModal.value = false;
    await fetchPlan();
};

const editAdvantage = async (adv) => {
    const text = prompt('Edit feature', adv.feature);
    if (!text) return;

    await PlanService.updateBenefit(adv.id, { feature: text });
    await fetchPlan();
};

const deleteAdvantage = async (id) => {
    if (!confirm('Delete feature?')) return;

    await PlanService.deleteBenefit(id);
    await fetchPlan();
};

// ================= HELPERS =================
const formatPrice = (p) => Number(p).toFixed(2);

const goBack = () => router.back();

onMounted(fetchPlan);
</script>
