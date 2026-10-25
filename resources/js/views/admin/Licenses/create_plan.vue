<template>
    <AdminLayout>
        <div class="p-6 max-w-4xl mx-auto">
            <!-- Header Section -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent">
                            Create New Plan
                        </h1>
                        <p class="text-gray-500 mt-2">Add a new subscription plan to your system</p>
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

            <!-- Form Card -->
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 px-8 py-6">
                    <h2 class="text-xl font-bold text-white">Plan Information</h2>
                    <p class="text-blue-100 mt-1">Fill in the details to create a new subscription plan</p>
                </div>

                <form @submit.prevent="handleSubmit" class="p-8">
                    <!-- Name Field -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Plan Name <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M6 14h12M9 6h6M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                                </svg>
                            </div>
                            <input
                                type="text"
                                v-model="form.name"
                                :class="[
                                    'block w-full pl-10 pr-3 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-all duration-200',
                                    errors.name ? 'border-red-500 focus:ring-red-200' : 'border-gray-300 focus:ring-blue-200 focus:border-blue-500'
                                ]"
                                placeholder="Enter plan name (e.g., Basic, Pro, Enterprise)"
                                @input="clearError('name')"
                            />
                        </div>
                        <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
                        <p class="mt-1 text-xs text-gray-500">Example: Basic Plan, Premium Plan, Business Plan</p>
                    </div>

                    <!-- Price Field -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Price <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-400 font-semibold">$</span>
                            </div>
                            <input
                                type="number"
                                v-model="form.price"
                                step="0.01"
                                :class="[
                                    'block w-full pl-8 pr-3 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-all duration-200',
                                    errors.price ? 'border-red-500 focus:ring-red-200' : 'border-gray-300 focus:ring-blue-200 focus:border-blue-500'
                                ]"
                                placeholder="0.00"
                                @input="clearError('price')"
                            />
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-400">USD</span>
                            </div>
                        </div>
                        <p v-if="errors.price" class="mt-1 text-sm text-red-600">{{ errors.price }}</p>
                        <p class="mt-1 text-xs text-gray-500">Enter the monthly subscription price (e.g., 9.99, 19.99, 49.99)</p>
                    </div>

                    <div class="mb-6 flex flex-wrap gap-2">
                        <button type="button" @click="calculateRecommendation" :disabled="recommending || !Number(form.price)"
                            class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold disabled:opacity-50">
                            {{ recommending ? 'Calculating…' : 'Auto Calculate Safe Quotas' }}
                        </button>
                        <button v-if="recommended" type="button" @click="resetRecommended"
                            class="px-4 py-2 rounded-lg bg-indigo-50 text-indigo-700 text-sm font-semibold">Reset Recommended</button>
                    </div>

                    <!-- Media Tier Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Access Media Tier
                        </label>
                        <select
                            v-model="form.media_tier_id"
                            class="block w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500 transition-all duration-200"
                        >
                            <option :value="null">-- Select Media Tier --</option>
                            <option v-for="tier in tiers" :key="tier.id" :value="tier.id">
                                {{ tier.name }} (Rank {{ tier.rank }}) — Max Photo: ${{ tier.max_photo_price }}, Max Video: ${{ tier.max_video_price }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Tier determines the highest priced media eligible for this plan.</p>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg mt-4">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50"><tr>
                                    <th class="text-start px-3 py-2">Tier ceiling</th>
                                    <th class="text-start px-3 py-2">Photos</th>
                                    <th class="text-start px-3 py-2">Videos</th>
                                </tr></thead>
                                <tbody>
                                    <tr v-for="limit in form.tier_limits" :key="limit.media_tier_id" class="border-t border-gray-100">
                                        <td class="px-3 py-2 font-semibold">{{ tierName(limit.media_tier_id) }}</td>
                                        <td class="px-3 py-2"><input v-model.number="limit.photo_maximum" @change="validatePricing" type="number" min="0" class="w-28 border rounded px-2 py-1" /></td>
                                        <td class="px-3 py-2"><input v-model.number="limit.video_maximum" @change="validatePricing" type="number" min="0" class="w-28 border rounded px-2 py-1" /></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Tier ceilings are constraints inside the shared totals, not additive balances.</p>
                    </div>

                    <!-- Photo & Video Quotas -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Photo Quota (Units)
                            </label>
                            <input
                                type="number"
                                v-model.number="form.photo_quota"
                                @change="validatePricing"
                                min="0"
                                class="block w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500 transition-all duration-200"
                                placeholder="e.g. 50"
                            />
                            <p class="mt-1 text-xs text-gray-500">Number of photos included in this plan.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Video Quota (Units)
                            </label>
                            <input
                                type="number"
                                v-model.number="form.video_quota"
                                @change="validatePricing"
                                min="0"
                                class="block w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500 transition-all duration-200"
                                placeholder="e.g. 10"
                            />
                            <p class="mt-1 text-xs text-gray-500">Number of videos included in this plan.</p>
                        </div>
                    </div>

                    <!-- Real-time Financial Validation Card -->
                    <div v-if="selectedTier || hasQuota" class="mb-6 p-4 rounded-xl border" :class="isFinanciallyValid ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-300'">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider" :class="isFinanciallyValid ? 'text-emerald-800' : 'text-amber-800'">
                                <i :class="isFinanciallyValid ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'" class="me-1"></i>
                                Financial Validation & Solvency Model
                            </span>
                            <span class="px-2 py-0.5 rounded text-xs font-bold" :class="isFinanciallyValid ? 'bg-emerald-200 text-emerald-900' : 'bg-amber-200 text-amber-900'">
                                {{ isFinanciallyValid ? 'SOLVENT / VALID' : 'WARNING / UNHEDGED' }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs mt-3">
                            <div>
                                <span class="text-gray-500 block">Max Catalog Value:</span>
                                <strong class="text-gray-900 text-sm">${{ maxPotentialCatalogValue.toFixed(2) }}</strong>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Max Creator Liability:</span>
                                <strong class="text-gray-900 text-sm text-blue-700">${{ maxCreatorLiability.toFixed(2) }}</strong>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Catalog Headroom:</span>
                                <strong class="text-sm" :class="catalogPriceHeadroom >= 0 ? 'text-emerald-700' : 'text-red-700'">${{ catalogPriceHeadroom.toFixed(2) }}</strong>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Payout Headroom:</span>
                                <strong class="text-sm" :class="creatorLiabilityHeadroom >= 0 ? 'text-emerald-700' : 'text-red-700'">${{ creatorLiabilityHeadroom.toFixed(2) }}</strong>
                            </div>
                        </div>
                        <p class="text-xs text-gray-600 mt-3 mb-0">
                            {{ financialValidationMessage }}
                        </p>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex gap-4 pt-4 border-t border-gray-200">
                        <button
                            type="submit"
                            :disabled="loading || !isFinanciallyValid"
                            class="flex-1 flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold rounded-lg transition-all duration-200 shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg v-if="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            <div v-else class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                            {{ loading ? 'Creating Plan...' : 'Create Plan' }}
                        </button>

                        <button
                            type="button"
                            @click="resetForm"
                            class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-all duration-200"
                        >
                            Reset
                        </button>
                    </div>

                    <!-- Success/Error Messages -->
                    <div v-if="successMessage" class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-green-800">{{ successMessage }}</p>
                        </div>
                    </div>

                    <div v-if="errorMessage" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-red-800">{{ errorMessage }}</p>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import AdminLayout from "../../../layouts/AdminLayout.vue";
import PlanService from '@/services/admin/licenses/planService';

const router = useRouter();

const form = ref({
    name: '',
    price: '',
    description: '',
    media_tier_id: null,
    photo_quota: 0,
    video_quota: 0,
    tier_limits: []
});

const tiers = ref([]);
const financialAnalysis = ref(null);
const recommended = ref(null);
const recommending = ref(false);

const fetchTiers = async () => {
    try {
        const res = await PlanService.getMediaTiers();
        tiers.value = res.data || [];
        form.value.tier_limits = tiers.value
            .filter((tier) => tier.is_active && tier.is_plan_eligible)
            .map((tier) => ({ media_tier_id: tier.id, photo_maximum: 0, video_maximum: 0 }));
    } catch (e) {
        console.error('Error fetching tiers:', e);
    }
};

onMounted(fetchTiers);

const selectedTier = computed(() => {
    if (!form.value.media_tier_id) return null;
    return tiers.value.find(t => t.id === form.value.media_tier_id) || null;
});

const maxPotentialCatalogValue = computed(() => {
    return Number(financialAnalysis.value?.max_potential_value || 0);
});

const maxCreatorLiability = computed(() => {
    return Number(financialAnalysis.value?.max_creator_liability || 0);
});

const catalogPriceHeadroom = computed(() => {
    return Number(financialAnalysis.value?.catalog_price_headroom || 0);
});

const creatorLiabilityHeadroom = computed(() => {
    return Number(financialAnalysis.value?.creator_liability_headroom || 0);
});

const hasQuota = computed(() => (form.value.photo_quota || 0) > 0 || (form.value.video_quota || 0) > 0);
const isCatalogValueValid = computed(() => catalogPriceHeadroom.value >= 0);
const isCreatorLiabilityValid = computed(() => creatorLiabilityHeadroom.value >= 0);

const isFinanciallyValid = computed(() => financialAnalysis.value?.is_valid === true);

const financialValidationMessage = computed(() => {
    if (!financialAnalysis.value) return 'Use Auto Calculate or validate the edited quotas.';
    if (isFinanciallyValid.value) return `Safe under financial settings version ${financialAnalysis.value.settings_version}. Worst-case profit: $${Number(financialAnalysis.value.worst_case_profit || 0).toFixed(2)}.`;
    return financialAnalysis.value.errors?.join(' ') || 'Plan cannot be saved.';
});

const tierName = (id) => tiers.value.find((tier) => tier.id === id)?.name || `Tier ${id}`;

const validatePricing = async () => {
    if (!Number(form.value.price)) {
        financialAnalysis.value = null;
        return;
    }
    try {
        financialAnalysis.value = await PlanService.validatePricing({
            price: Number(form.value.price),
            media_tier_id: form.value.media_tier_id,
            photo_quota: Number(form.value.photo_quota || 0),
            video_quota: Number(form.value.video_quota || 0),
            tier_limits: form.value.tier_limits,
        });
    } catch (error) {
        financialAnalysis.value = null;
        errorMessage.value = error.data?.message || 'Unable to validate pricing.';
    }
};

const applyRecommendation = (result) => {
    form.value.photo_quota = result.photo_quota;
    form.value.video_quota = result.video_quota;
    form.value.media_tier_id = result.media_tier_id;
    form.value.tier_limits = tiers.value.filter((tier) => tier.is_active && tier.is_plan_eligible).map((tier) => {
        const limit = result.tier_limits.find((item) => item.media_tier_id === tier.id);
        return limit || { media_tier_id: tier.id, photo_maximum: 0, video_maximum: 0 };
    });
    financialAnalysis.value = result.analysis;
};

const calculateRecommendation = async () => {
    recommending.value = true;
    errorMessage.value = '';
    try {
        const result = await PlanService.recommendQuotas(Number(form.value.price));
        if (!result.is_available) throw { data: { message: result.reason + (result.missing_assumptions?.length ? ` Missing: ${result.missing_assumptions.join(', ')}` : '') } };
        recommended.value = JSON.parse(JSON.stringify(result));
        applyRecommendation(result);
    } catch (error) {
        errorMessage.value = error.data?.message || 'Unable to calculate a safe recommendation.';
    } finally {
        recommending.value = false;
    }
};

const resetRecommended = () => recommended.value && applyRecommendation(JSON.parse(JSON.stringify(recommended.value)));

const loading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errors = ref({
    name: '',
    price: ''
});

// Validation rules
const validateForm = () => {
    let isValid = true;
    errors.value = { name: '', price: '' };

    // Validate name
    if (!form.value.name.trim()) {
        errors.value.name = 'Plan name is required';
        isValid = false;
    } else if (form.value.name.length < 3) {
        errors.value.name = 'Plan name must be at least 3 characters';
        isValid = false;
    } else if (form.value.name.length > 100) {
        errors.value.name = 'Plan name must not exceed 100 characters';
        isValid = false;
    }

    // Validate price
    if (!form.value.price && form.value.price !== 0) {
        errors.value.price = 'Price is required';
        isValid = false;
    } else if (isNaN(form.value.price)) {
        errors.value.price = 'Price must be a valid number';
        isValid = false;
    } else if (Number(form.value.price) < 0) {
        errors.value.price = 'Price cannot be negative';
        isValid = false;
    } else if (Number(form.value.price) > 999999.99) {
        errors.value.price = 'Price is too high';
        isValid = false;
    }

    if (!isFinanciallyValid.value) {
        errorMessage.value = financialValidationMessage.value;
        isValid = false;
    }

    return isValid;
};

// Clear specific error
const clearError = (field) => {
    if (errors.value[field]) {
        errors.value[field] = '';
    }
    if (successMessage.value) successMessage.value = '';
    if (errorMessage.value) errorMessage.value = '';
    if (field === 'price') financialAnalysis.value = null;
};

// Reset form
const resetForm = () => {
    form.value = {
        name: '',
        price: '',
        description: '',
        media_tier_id: null,
        photo_quota: 0,
        video_quota: 0,
        tier_limits: tiers.value.filter((tier) => tier.is_active && tier.is_plan_eligible)
            .map((tier) => ({ media_tier_id: tier.id, photo_maximum: 0, video_maximum: 0 }))
    };
    financialAnalysis.value = null;
    recommended.value = null;
    errors.value = { name: '', price: '' };
    successMessage.value = '';
    errorMessage.value = '';
};

// Handle form submission
const handleSubmit = async () => {
    // Clear previous messages
    successMessage.value = '';
    errorMessage.value = '';

    await validatePricing();

    // Validate form
    if (!validateForm()) {
        return;
    }

    loading.value = true;

    try {
        // Prepare data for API
        const planData = {
            name: form.value.name.trim(),
            price: parseFloat(form.value.price),
            media_tier_id: form.value.media_tier_id,
            photo_quota: form.value.photo_quota || 0,
            video_quota: form.value.video_quota || 0,
            tier_limits: form.value.tier_limits,
            ...(form.value.description && { description: form.value.description.trim() })
        };

        // Call API to create plan
        const response = await PlanService.create(planData);

        // Show success message
        successMessage.value = 'Plan created successfully! Redirecting...';

        // Reset form
        resetForm();

        // Redirect to plans list after 2 seconds
        setTimeout(() => {
            router.push('/admin/plans');
        }, 2000);

    } catch (err) {
        console.error('Error creating plan:', err);

        // Handle different error scenarios
        if (err.status === 422) {
            // Validation errors from API
            const apiErrors = err.data?.errors;
            if (apiErrors) {
                if (apiErrors.name) {
                    errors.value.name = apiErrors.name[0];
                }
                if (apiErrors.price) {
                    errors.value.price = apiErrors.price[0];
                }
                errorMessage.value = 'Please check the form for errors.';
            } else {
                errorMessage.value = err.data?.message || 'Validation failed. Please check your input.';
            }
        } else if (err.status === 401) {
            errorMessage.value = 'Unauthorized. Please login again.';
            // Optionally redirect to login
        } else if (err.status === 500) {
            errorMessage.value = 'Server error. Please try again later.';
        } else {
            errorMessage.value = err.data?.message || 'Failed to create plan. Please try again.';
        }
    } finally {
        loading.value = false;
    }
};

// Go back function
const goBack = () => {
    router.back();
};
</script>

<style scoped>
/* Remove number input spinners */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
}

/* Smooth transitions */
* {
    transition: all 0.2s ease;
}
</style>
