<template>
    <div class="fixed inset-0 bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 overflow-y-auto">

        <!-- Animated Background Elements -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div
                class="absolute -top-40 -right-40 w-80 h-80 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob">
            </div>
            <div
                class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000">
            </div>
            <div
                class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-80 h-80 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000">
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="relative min-h-screen flex items-center justify-center">
            <div class="text-center">
                <div class="relative">
                    <div class="animate-spin rounded-full h-24 w-24 border-t-4 border-b-4 border-purple-500 mx-auto">
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="h-12 w-12 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full animate-pulse">
                        </div>
                    </div>
                </div>
                <p class="mt-8 text-white text-xl font-medium">Loading your premium plan...</p>
                <p class="mt-2 text-purple-200 text-sm">Please wait while we prepare your experience</p>
            </div>
        </div>

        <!-- Plan Content -->
        <div v-else-if="plan" class="relative min-h-screen flex items-center justify-center p-4 md:p-8">
            <div class="w-full max-w-7xl mx-auto">

                <!-- Back Button - Floating -->
                <button @click="$router.back()"
                    class="fixed top-6 left-6 z-20 flex items-center gap-2 bg-white/10 backdrop-blur-lg hover:bg-white/20 text-white px-5 py-3 rounded-full transition-all duration-300 group border border-white/20">
                    <svg class="w-5 h-5 transform group-hover:-translate-x-1 transition-transform" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span>Back to Plans</span>
                </button>

                <!-- Main Content Grid -->
                <div class="grid lg:grid-cols-2 gap-8 items-start">

                    <!-- Left Column - Plan Details -->
                    <div class="space-y-6">
                        <!-- Plan Card -->
                        <div
                            class="bg-gradient-to-br from-white/10 to-white/5 backdrop-blur-xl rounded-2xl border border-white/20 overflow-hidden transform hover:scale-[1.02] transition-all duration-500">
                            <div class="relative overflow-hidden">
                                <div
                                    class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-purple-500/20 to-blue-500/20 rounded-full blur-3xl">
                                </div>
                                <div class="relative p-8 md:p-10">
                                    <div
                                        class="inline-flex items-center gap-2 bg-gradient-to-r from-purple-500 to-blue-500 px-4 py-2 rounded-full mb-6">
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <span class="text-white text-sm font-semibold">PREMIUM PLAN</span>
                                    </div>

                                    <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">
                                        {{ plan.translation?.name || plan.name }}
                                    </h1>

                                    <div class="flex items-baseline gap-2 mb-6">
                                        <span
                                            class="text-5xl md:text-6xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-blue-400">
                                            ${{ plan.price }}
                                        </span>
                                        <span class="text-white/60 text-lg">/ month</span>
                                    </div>

                                    <div
                                        class="h-px bg-gradient-to-r from-transparent via-white/20 to-transparent my-6">
                                    </div>

                                    <!-- Description -->
                                    <p class="text-white/70 text-lg leading-relaxed">
                                        Get access to all premium features and take your experience to the next level.
                                        Join thousands of satisfied customers who already upgraded.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Features Summary -->
                        <div class="bg-white/5 backdrop-blur-lg rounded-2xl border border-white/10 p-6 md:p-8">
                            <h3 class="text-white text-xl font-semibold mb-6 flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full bg-gradient-to-r from-purple-500 to-blue-500 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                What's Included
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="feature in plan.advantges" :key="feature.id"
                                    class="flex items-center gap-3 p-3 rounded-xl bg-white/5 hover:bg-white/10 transition-all duration-300 group">
                                    <div
                                        class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-r from-green-400 to-emerald-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                    <span
                                        class="text-white/80 group-hover:text-white transition-colors text-sm md:text-base">{{
                                        feature.feature }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Subscription Card -->
                    <div class="lg:sticky lg:top-8 space-y-6">
                        <!-- Pricing Card -->
                        <div
                            class="bg-gradient-to-br from-white/10 to-white/5 backdrop-blur-xl rounded-2xl border border-white/20 p-8">
                            <div class="text-center mb-8">
                                <div
                                    class="inline-flex items-center gap-2 bg-gradient-to-r from-yellow-500/20 to-orange-500/20 px-4 py-2 rounded-full mb-4">
                                    <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-yellow-400 text-sm font-semibold">BEST VALUE</span>
                                </div>
                                <h2 class="text-3xl font-bold text-white mb-2">Ready to upgrade?</h2>
                                <p class="text-white/60">Get instant access to all premium features</p>
                            </div>

                            <button @click="subscribe" :disabled="subscribing"
                                class="w-full relative group overflow-hidden bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white py-5 rounded-xl font-bold text-lg transition-all duration-300 transform hover:scale-[1.02] active:scale-95 shadow-2xl hover:shadow-purple-500/25">
                                <span v-if="!subscribing" class="relative z-10 flex items-center justify-center gap-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                        </path>
                                    </svg>
                                    Subscribe Now
                                </span>
                                <span v-else class="relative z-10 flex items-center justify-center gap-3">
                                    <svg class="animate-spin h-6 w-6" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    Processing...
                                </span>
                                <div
                                    class="absolute inset-0 bg-gradient-to-r from-purple-500 to-blue-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                </div>
                            </button>

                            <div class="mt-6 space-y-3">
                                <div class="flex items-center justify-center gap-2 text-white/60 text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 15v2m-6-4h12a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6a2 2 0 012-2zm10-10V4a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z">
                                        </path>
                                    </svg>
                                    Secure 256-bit SSL encryption
                                </div>
                                <div class="flex items-center justify-center gap-2 text-white/60 text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                        </path>
                                    </svg>
                                    30-day money-back guarantee
                                </div>
                            </div>
                        </div>

                        <!-- Trust Badges -->
                        <div class="bg-white/5 backdrop-blur-lg rounded-2xl border border-white/10 p-6">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div class="text-2xl font-bold text-white">10K+</div>
                                    <div class="text-xs text-white/50">Active Users</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-white">4.9</div>
                                    <div class="text-xs text-white/50">Rating</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-white">24/7</div>
                                    <div class="text-xs text-white/50">Support</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ Section -->
                <div class="mt-12 bg-white/5 backdrop-blur-lg rounded-2xl border border-white/10 p-8">
                    <h3 class="text-2xl font-bold text-white mb-6 text-center">Frequently Asked Questions</h3>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div v-for="(faq, index) in faqs" :key="index" class="space-y-2">
                            <button @click="toggleFaq(index)"
                                class="w-full text-left flex justify-between items-center p-4 rounded-xl bg-white/5 hover:bg-white/10 transition-all">
                                <span class="font-semibold text-white">{{ faq.question }}</span>
                                <svg class="w-5 h-5 text-white transition-transform"
                                    :class="{ 'rotate-180': openFaqs[index] }" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div v-if="openFaqs[index]" class="p-4 text-white/70 leading-relaxed">
                                {{ faq.answer }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error State -->
        <div v-else class="relative min-h-screen flex items-center justify-center">
            <div class="text-center max-w-md mx-auto p-8">
                <div
                    class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-red-500/20 backdrop-blur-lg mb-6">
                    <svg class="w-12 h-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-white mb-3">Plan Not Found</h3>
                <p class="text-white/60 mb-8">The plan you're looking for doesn't exist or has been removed.</p>
                <button @click="$router.push('/plans')"
                    class="inline-flex items-center gap-3 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white px-8 py-4 rounded-xl font-semibold transition-all transform hover:scale-105">
                    Browse All Plans
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Toast Notification -->
        <transition enter-active-class="transition duration-300 transform" enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100" leave-active-class="transition duration-300 transform"
            leave-from-class="translate-x-0 opacity-100" leave-to-class="translate-x-full opacity-0">
            <div v-if="toast.show" :class="[
                'fixed bottom-8 right-8 z-50 flex items-center gap-4 px-6 py-4 rounded-xl shadow-2xl backdrop-blur-lg',
                toast.type === 'success' ? 'bg-green-500/90 text-white' : 'bg-red-500/90 text-white'
            ]">
                <svg v-if="toast.type === 'success'" class="w-6 h-6" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
                <span class="font-medium">{{ toast.message }}</span>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/services/ApiClient";

const route = useRoute();
const router = useRouter();

const plan = ref(null);
const loading = ref(true);
const subscribing = ref(false);
const toast = ref({ show: false, message: '', type: '' });

// FAQ Data
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
        const res = await api.get(`/plans/single/${slug}`);
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
        await api.post(`/subscribe/${plan.value.id}`);

        showToast('Successfully subscribed! 🎉', 'success');

        setTimeout(() => {
            router.push('/');
        }, 2000);

    } catch (error) {
        console.error(error);
        showToast('Subscription failed. Please try again.', 'error');
    } finally {
        subscribing.value = false;
    }
};

onMounted(fetchPlan);
</script>

<style scoped>
@keyframes blob {
    0% {
        transform: translate(0px, 0px) scale(1);
    }

    33% {
        transform: translate(30px, -50px) scale(1.1);
    }

    66% {
        transform: translate(-20px, 20px) scale(0.9);
    }

    100% {
        transform: translate(0px, 0px) scale(1);
    }
}

.animate-blob {
    animation: blob 7s infinite;
}

.animation-delay-2000 {
    animation-delay: 2s;
}

.animation-delay-4000 {
    animation-delay: 4s;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 10px;
}

::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}
</style>
