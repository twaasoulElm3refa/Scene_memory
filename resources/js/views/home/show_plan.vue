<template>
    <div class="min-h-screen bg-white overflow-y-auto font-sans">

        <!-- Top Bar -->
        <div class="border-b border-black">
            <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
                <button @click="$router.back()"
                    class="flex items-center gap-2 text-sm font-semibold text-black hover:opacity-50 transition-opacity duration-200 tracking-widest uppercase">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back
                </button>
                <span class="text-xs tracking-[0.3em] uppercase text-gray-400 font-medium">Subscription</span>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center min-h-[80vh]">
            <div class="text-center space-y-4">
                <div class="w-8 h-8 border-2 border-black border-t-transparent rounded-full animate-spin mx-auto"></div>
                <p class="text-sm tracking-widest uppercase text-gray-400">Loading plan...</p>
            </div>
        </div>

        <!-- Plan Content -->
        <div v-else-if="plan" class="max-w-6xl mx-auto px-6 py-12">

            <div class="grid lg:grid-cols-5 gap-0 border border-black">

                <!-- Left Column - Plan Details (3 cols) -->
                <div class="lg:col-span-3 border-r border-black">

                    <!-- Header Section -->
                    <div class="p-10 border-b border-black">
                        <div class="flex items-start justify-between mb-8">
                            <span
                                class="text-xs tracking-[0.4em] uppercase font-semibold border border-black px-3 py-1">
                                Premium Plan
                            </span>
                            <span class="text-xs text-gray-400 tracking-widest uppercase">
                                #{{ plan.id.toString().padStart(4, '0') }}
                            </span>
                        </div>

                        <h1 class="text-5xl md:text-6xl font-black text-black leading-none tracking-tight mb-6">
                            {{ plan.translation?.name || plan.name }}
                        </h1>

                        <div class="flex items-baseline gap-1">
                            <span class="text-7xl font-black text-black leading-none">
                                ${{ plan.price.split('.')[0] }}
                            </span>
                            <span class="text-3xl font-black text-black">.{{ plan.price.split('.')[1] }}</span>
                            <span class="text-sm text-gray-400 ml-2 tracking-widest uppercase self-end mb-1">/
                                month</span>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="p-10 border-b border-black">
                        <p class="text-gray-600 leading-relaxed text-lg">
                            Get instant access to all features included in this plan. No hidden fees, no surprises —
                            just
                            everything you need to get started right away.
                        </p>
                    </div>

                    <!-- Features -->
                    <div class="p-10">
                        <h3 class="text-xs tracking-[0.4em] uppercase font-semibold text-gray-400 mb-8">
                            What's Included
                        </h3>
                        <ul class="divide-y divide-gray-100">
                            <li v-for="feature in plan.advantges" :key="feature.id"
                                class="flex items-center gap-4 py-5 group">
                                <div
                                    class="flex-shrink-0 w-5 h-5 bg-black rounded-full flex items-center justify-center group-hover:bg-gray-600 transition-colors">
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-black font-medium text-base">
                                    {{ feature.translation?.name || feature.feature }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Right Column - Subscribe (2 cols) -->
                <div class="lg:col-span-2 flex flex-col">

                    <!-- Subscribe Block -->
                    <div class="p-10 border-b border-black">
                        <h2 class="text-2xl font-black text-black mb-2 leading-tight">
                            Ready to get started?
                        </h2>
                        <p class="text-sm text-gray-500 mb-8 tracking-wide">
                            Cancel anytime. No questions asked.
                        </p>

                        <!-- Price Summary -->
                        <div class="border border-black p-5 mb-6">
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-sm text-gray-500">Plan</span>
                                <span class="text-sm font-semibold text-black">
                                    {{ plan.translation?.name || plan.name }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-sm text-gray-500">Billing</span>
                                <span class="text-sm font-semibold text-black">Monthly</span>
                            </div>
                            <div class="border-t border-black pt-3 flex justify-between items-center">
                                <span class="text-sm font-bold text-black uppercase tracking-wider">Total</span>
                                <span class="text-xl font-black text-black">${{ plan.price }}</span>
                            </div>
                        </div>

                        <!-- Subscribe Button -->
                        <button @click="subscribe" :disabled="subscribing"
                            class="w-full bg-black text-white py-5 text-sm font-bold tracking-[0.2em] uppercase hover:bg-gray-800 transition-colors duration-200 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-3">
                            <svg v-if="subscribing" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4" />
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <span>{{ subscribing ? 'Processing...' : 'Subscribe Now' }}</span>
                        </button>

                        <!-- Trust -->
                        <div class="mt-6 space-y-2">
                            <div class="flex items-center gap-2 text-xs text-gray-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6-4h12a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6a2 2 0 012-2zm10-10V4a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z" />
                                </svg>
                                Secure 256-bit SSL encryption
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                30-day money-back guarantee
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 divide-x divide-black border-b border-black">
                        <div class="p-6 text-center">
                            <div class="text-2xl font-black text-black">10K+</div>
                            <div class="text-xs text-gray-400 tracking-wider uppercase mt-1">Users</div>
                        </div>
                        <div class="p-6 text-center">
                            <div class="text-2xl font-black text-black">4.9</div>
                            <div class="text-xs text-gray-400 tracking-wider uppercase mt-1">Rating</div>
                        </div>
                        <div class="p-6 text-center">
                            <div class="text-2xl font-black text-black">24/7</div>
                            <div class="text-xs text-gray-400 tracking-wider uppercase mt-1">Support</div>
                        </div>
                    </div>

                    <!-- Footer note -->
                    <div class="flex-1 p-10 flex items-end">
                        <p class="text-xs text-gray-300 leading-relaxed">
                            By subscribing, you agree to our Terms of Service and Privacy Policy. Subscription renews
                            automatically each month.
                        </p>
                    </div>
                </div>
            </div>

            <!-- FAQ Section -->
            <div class="border-x border-b border-black">
                <div class="border-b border-black p-10">
                    <h3 class="text-xs tracking-[0.4em] uppercase font-semibold text-gray-400">
                        Frequently Asked Questions
                    </h3>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="(faq, index) in faqs" :key="index">
                        <button @click="toggleFaq(index)"
                            class="w-full text-left flex justify-between items-center p-8 hover:bg-gray-50 transition-colors duration-150">
                            <span class="font-bold text-black text-base pr-8">{{ faq.question }}</span>
                            <svg class="w-4 h-4 text-black flex-shrink-0 transition-transform duration-200"
                                :class="{ 'rotate-180': openFaqs[index] }" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div v-if="openFaqs[index]"
                            class="px-8 pb-8 text-gray-600 leading-relaxed text-sm border-t border-gray-100">
                            <div class="pt-4">{{ faq.answer }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error State -->
        <div v-else class="flex items-center justify-center min-h-[80vh]">
            <div class="text-center max-w-sm mx-auto p-8">
                <div class="w-16 h-16 border-2 border-black flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-2xl font-black text-black mb-3">Plan Not Found</h3>
                <p class="text-gray-500 text-sm mb-8 leading-relaxed">
                    The plan you're looking for doesn't exist or has been removed.
                </p>
                <button @click="$router.push('/plans')"
                    class="inline-flex items-center gap-3 bg-black text-white px-8 py-4 text-sm font-bold tracking-widest uppercase hover:bg-gray-800 transition-colors">
                    Browse All Plans
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Toast Notification -->
        <transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-4 opacity-0">
            <div v-if="toast.show" :class="[
                'fixed bottom-8 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 px-6 py-4 text-sm font-semibold tracking-wide border',
                toast.type === 'success'
                    ? 'bg-black text-white border-black'
                    : 'bg-white text-black border-black'
            ]">
                <svg v-if="toast.type === 'success'" class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
                {{ toast.message }}
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { PlanService } from "@/services/planService";

const route = useRoute();
const router = useRouter();

const plan = ref(null);
const loading = ref(true);
const subscribing = ref(false);
const toast = ref({ show: false, message: '', type: '' });

const openFaqs = ref({});
const faqs = ref([
    {
        question: "Can I cancel my subscription anytime?",
        answer: "Yes, you can cancel your subscription at any time. No questions asked, and you'll still have access until the end of your billing period."
    },
    {
        question: "Is there a free trial?",
        answer: "We offer a 30-day money-back guarantee. If you're not satisfied, we'll refund your full payment."
    },
    {
        question: "What payment methods do you accept?",
        answer: "We accept all major credit cards, PayPal, and bank transfers for annual plans."
    },
    {
        question: "Can I switch plans later?",
        answer: "Absolutely! You can upgrade or downgrade your plan at any time from your account dashboard."
    }
]);

const toggleFaq = (index) => {
    openFaqs.value[index] = !openFaqs.value[index];
};

const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => {
        toast.value.show = false;
    }, 3000);
};

const fetchPlan = async () => {
    try {
        const slug = route.params.slug;
        const res = await PlanService.getSingle(slug);
        plan.value = res.data.data[0];
    } catch (error) {
        console.error(error);
        showToast('Failed to load plan. Please try again.', 'error');
    } finally {
        loading.value = false;
    }
};

const subscribe = async () => {
    subscribing.value = true;
    try {
        await PlanService.subscribe(plan.value.id);
        showToast('Successfully subscribed! Welcome aboard.', 'success');
        const lang = localStorage.getItem('language') || 'en';

        setTimeout(() => {
            router.push(`/${lang}/profile`);
        }, 1200);
    } catch (error) {
        console.error(error);
        showToast('Subscription failed. Please try again.', 'error');
    } finally {
        subscribing.value = false;
    }
};

onMounted(fetchPlan);
</script>
