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

                    <!-- Form Actions -->
                    <div class="flex gap-4 pt-4 border-t border-gray-200">
                        <button
                            type="submit"
                            :disabled="loading"
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
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import AdminLayout from "../../../layouts/AdminLayout.vue";
import PlanService from '@/services/admin/licenses/planService';

const router = useRouter();

const form = ref({
    name: '',
    price: '',
    description: ''
});

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

    return isValid;
};

// Clear specific error
const clearError = (field) => {
    if (errors.value[field]) {
        errors.value[field] = '';
    }
    if (successMessage.value) successMessage.value = '';
    if (errorMessage.value) errorMessage.value = '';
};

// Reset form
const resetForm = () => {
    form.value = {
        name: '',
        price: '',
        description: ''
    };
    errors.value = { name: '', price: '' };
    successMessage.value = '';
    errorMessage.value = '';
};

// Handle form submission
const handleSubmit = async () => {
    // Clear previous messages
    successMessage.value = '';
    errorMessage.value = '';

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
