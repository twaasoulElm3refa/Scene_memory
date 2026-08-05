<template>
    <div class="min-h-screen bg-white py-16 px-4">
        <!-- Header -->
        <div class="text-center mb-14">
            <h1 class="text-4xl font-bold text-black mb-3 tracking-tight">
                Choose Your Plan
            </h1>

            <p class="text-gray-600 text-lg">
                Simple, transparent pricing for everyone
            </p>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center items-center py-20">
            <div
                class="w-12 h-12 border-4 border-black border-t-transparent rounded-full animate-spin"
            ></div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="max-w-md mx-auto text-center py-16">
            <div class="text-red-600 text-5xl mb-4">
                ⚠️
            </div>

            <p class="text-red-600 text-lg">
                {{ error }}
            </p>

            <button
                type="button"
                @click="fetchPlans"
                class="mt-6 px-6 py-2 bg-black hover:bg-gray-800 text-white rounded-lg transition"
            >
                Try Again
            </button>
        </div>

        <!-- Plans Grid -->
        <div
            v-else
            class="max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 items-stretch"
        >
            <div
                v-for="(plan, index) in plans"
                :key="plan.id"
                :class="[
                    'relative rounded-3xl p-6 xl:p-8 flex flex-col transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl cursor-pointer border min-w-0',
                    isPopularPlan(plan, index)
                        ? 'bg-black border-black shadow-xl text-white lg:scale-[1.03] z-10'
                        : 'bg-white border-gray-200 hover:border-gray-400'
                ]"
            >
                <!-- Popular Badge -->
                <div
                    v-if="isPopularPlan(plan, index)"
                    class="absolute -top-4 left-1/2 -translate-x-1/2 bg-black text-white text-xs font-bold px-5 py-1.5 rounded-full uppercase tracking-widest whitespace-nowrap border border-white/20"
                >
                    Most Popular
                </div>

                <!-- Plan Icon -->
                <div
                    :class="[
                        'w-14 h-14 rounded-2xl flex items-center justify-center text-3xl mb-6 flex-shrink-0',
                        isPopularPlan(plan, index)
                            ? 'bg-white/20 text-white'
                            : getPlanIconBackground(plan)
                    ]"
                >
                    {{ getPlanIcon(plan, index) }}
                </div>

                <!-- Plan Name -->
                <h2
                    :class="[
                        'text-2xl font-bold mb-2 break-words',
                        isPopularPlan(plan, index)
                            ? 'text-white'
                            : 'text-black'
                    ]"
                >
                    {{ plan.translation?.name || plan.name }}
                </h2>

                <!-- Price -->
                <div class="mt-2 mb-8 min-h-[66px]">
                    <template v-if="isCustomPlan(plan)">
                        <span
                            :class="[
                                'text-3xl xl:text-4xl font-extrabold',
                                isPopularPlan(plan, index)
                                    ? 'text-white'
                                    : 'text-black'
                            ]"
                        >
                            Custom
                        </span>

                        <p
                            :class="[
                                'text-sm mt-2',
                                isPopularPlan(plan, index)
                                    ? 'text-white/70'
                                    : 'text-gray-500'
                            ]"
                        >
                            Contact us for pricing
                        </p>
                    </template>

                    <template v-else>
                        <span
                            :class="[
                                'text-4xl xl:text-5xl font-extrabold',
                                isPopularPlan(plan, index)
                                    ? 'text-white'
                                    : 'text-black'
                            ]"
                        >
                            {{
                                isFreePlan(plan)
                                    ? 'Free'
                                    : '$' + formatPrice(plan.price)
                            }}
                        </span>

                        <span
                            v-if="!isFreePlan(plan)"
                            :class="[
                                'text-sm ml-1',
                                isPopularPlan(plan, index)
                                    ? 'text-white/70'
                                    : 'text-gray-500'
                            ]"
                        >
                            / mo
                        </span>
                    </template>
                </div>

                <!-- Divider -->
                <div
                    :class="[
                        'border-t mb-6',
                        isPopularPlan(plan, index)
                            ? 'border-white/30'
                            : 'border-gray-200'
                    ]"
                ></div>

                <!-- Advantages -->
                <ul class="flex-1 space-y-3 mb-8">
                    <li
                        v-if="!plan.advantges || plan.advantges.length === 0"
                        :class="[
                            'text-sm italic',
                            isPopularPlan(plan, index)
                                ? 'text-white/70'
                                : 'text-gray-500'
                        ]"
                    >
                        {{
                            isCustomPlan(plan)
                                ? 'Custom features based on your needs'
                                : 'Basic access included'
                        }}
                    </li>

                    <li
                        v-for="adv in plan.advantges || []"
                        :key="adv.id"
                        :class="[
                            'flex items-start gap-3 text-sm xl:text-base leading-6',
                            isPopularPlan(plan, index)
                                ? 'text-white'
                                : 'text-gray-700'
                        ]"
                    >
                        <span
                            class="w-5 h-5 mt-0.5 bg-green-500 text-white rounded-full flex items-center justify-center flex-shrink-0"
                            aria-hidden="true"
                        >
                            <svg
                                class="w-3 h-3"
                                viewBox="0 0 20 20"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M4.5 10.5 8 14l7.5-8"></path>
                            </svg>
                        </span>

                        <span class="break-words min-w-0">
                            {{
                                adv.translation?.name ||
                                adv.name ||
                                adv.feature
                            }}
                        </span>
                    </li>
                </ul>

                <!-- Action Button -->
                <button
                    type="button"
                    @click="goToPlan(plan)"
                    :class="[
                        'w-full py-4 px-4 rounded-2xl font-semibold text-base transition-all duration-200 mt-auto',
                        isPopularPlan(plan, index)
                            ? 'bg-white text-black hover:bg-gray-100'
                            : isCustomPlan(plan)
                                ? 'bg-gray-900 hover:bg-gray-800 text-white border border-gray-900'
                                : 'bg-black hover:bg-gray-900 text-white border border-black'
                    ]"
                >
                    {{
                        isCustomPlan(plan)
                            ? 'Contact Us'
                            : isFreePlan(plan)
                                ? 'Get Started Free'
                                : 'Get Started'
                    }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { PlanService } from '@/services/planService/planService';

const lang = localStorage.getItem('language') || 'en';

const plans = ref([]);
const loading = ref(false);
const error = ref(null);

const router = useRouter();

const normalizePlanName = (plan) => {
    return String(
        plan?.translation?.name ||
        plan?.name ||
        ''
    )
        .trim()
        .toLowerCase()
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ');
};

const isCustomPlan = (plan) => {
    const name = normalizePlanName(plan);

    return (
        name === 'custom' ||
        name === 'custom plan' ||
        name === 'custom plans'
    );
};

const isFreePlan = (plan) => {
    return (
        normalizePlanName(plan) === 'free' ||
        (Number(plan?.price) === 0 && !isCustomPlan(plan))
    );
};

const isPopularPlan = (plan, index) => {
    const name = normalizePlanName(plan);

    return (
        name === 'professional' ||
        name === 'pro' ||
        index === 1
    );
};

const formatPrice = (price) => {
    const numericPrice = Number.parseFloat(price);

    if (Number.isNaN(numericPrice)) {
        return '0';
    }

    return numericPrice.toFixed(0);
};

const getPlanIcon = (plan, index) => {
    const name = normalizePlanName(plan);

    if (name === 'free') {
        return '🌱';
    }

    if (name === 'basic') {
        return '📚';
    }

    if (name === 'professional' || name === 'pro') {
        return '💎';
    }

    if (name === 'premium') {
        return '🚀';
    }

    if (isCustomPlan(plan)) {
        return '⚙️';
    }

    const fallbackIcons = ['🌱', '⚡', '🚀', '⚙️'];

    return fallbackIcons[index % fallbackIcons.length];
};

const getPlanIconBackground = (plan) => {
    const name = normalizePlanName(plan);

    if (name === 'premium') {
        return 'bg-purple-100 text-purple-700';
    }

    if (isCustomPlan(plan)) {
        return 'bg-amber-100 text-amber-700';
    }

    if (name === 'professional' || name === 'pro') {
        return 'bg-blue-100 text-blue-700';
    }

    return 'bg-gray-100 text-black';
};

const goToPlan = (plan) => {
    if (isCustomPlan(plan)) {
        router.push(`/${lang}/contact`);
        return;
    }

    if (!plan?.slug) {
        console.warn('The selected plan does not have a slug.', plan);
        return;
    }

    router.push(`/${lang}/plan/${plan.slug}`);
};

const fetchPlans = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await PlanService.getAll();

        plans.value = Array.isArray(response?.data?.data)
            ? response.data.data
            : [];
    } catch (err) {
        error.value =
            err.response?.data?.message ||
            'Failed to load plans. Please try again.';
    } finally {
        loading.value = false;
    }
};

onMounted(fetchPlans);
</script>
