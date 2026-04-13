<template>
    <!-- Toast -->
    <div v-if="toast.show" :class="['fixed top-5 right-5 px-4 py-3 rounded-xl shadow-2xl z-50 flex items-center gap-2 transition-all duration-300 font-medium',
        toast.type === 'success' ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white']">
        {{ toast.message }}
    </div>

    <div class="min-h-screen py-10" style="background: #f0f4f8;">
        <div class="container mx-auto px-4 max-w-6xl">

            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                    <span class="text-4xl">🛒</span>
                    Shopping Cart
                </h1>
                <button v-if="items.length" @click="clearCart"
                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-xl font-semibold transition shadow-sm">
                    Clear Cart 🗑️
                </button>
            </div>

            <!-- Wallet Banner -->
            <div class="mb-8 rounded-2xl overflow-hidden shadow-lg"
                style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);">
                <div class="px-7 py-5 flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <!-- Wallet Icon -->
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center shadow-inner"
                            style="background: rgba(255,255,255,0.1);">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white"
                                stroke-width="1.8">
                                <path d="M21 12V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2v-5z" />
                                <circle cx="17" cy="12" r="1.5" fill="white" stroke="none" />
                                <path d="M3 9h18" stroke-linecap="round" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-widest uppercase"
                                style="color: rgba(255,255,255,0.5);">My Wallet Balance</p>
                            <div v-if="walletLoading" class="flex items-center gap-2 mt-1">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white opacity-60"></div>
                                <span class="text-white opacity-60 text-sm">Loading...</span>
                            </div>
                            <p v-else class="text-3xl font-bold text-white mt-0.5">
                                {{ parseFloat(walletAmount).toFixed(2) }}
                                <span class="text-lg font-normal opacity-70">$</span>
                            </p>
                        </div>
                    </div>

                    <!-- Wallet Status Badge -->
                    <div v-if="!walletLoading">
                        <span v-if="parseFloat(walletAmount) >= total"
                            class="px-4 py-2 rounded-full text-sm font-semibold flex items-center gap-2"
                            style="background: rgba(52,211,153,0.2); color: #6ee7b7; border: 1px solid rgba(52,211,153,0.3);">
                            ✅ Sufficient Balance
                        </span>
                        <span v-else class="px-4 py-2 rounded-full text-sm font-semibold flex items-center gap-2"
                            style="background: rgba(251,191,36,0.15); color: #fcd34d; border: 1px solid rgba(251,191,36,0.3);">
                            ⚠️ Insufficient Balance
                        </span>
                    </div>
                </div>

                <!-- Progress bar: wallet vs total -->
                <div v-if="!walletLoading && items.length" class="px-7 pb-5">
                    <div class="flex justify-between text-xs mb-1.5" style="color: rgba(255,255,255,0.45);">
                        <span>Wallet covers</span>
                        <span>{{ Math.min(100, Math.round((parseFloat(walletAmount) / total) * 100)) }}% of order</span>
                    </div>
                    <div class="h-1.5 rounded-full w-full" style="background: rgba(255,255,255,0.1);">
                        <div class="h-1.5 rounded-full transition-all duration-700" :style="{
                            width: Math.min(100, Math.round((parseFloat(walletAmount) / total) * 100)) + '%',
                            background: parseFloat(walletAmount) >= total ? '#34d399' : '#fbbf24'
                        }">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex justify-center items-center py-20">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>
            </div>

            <div v-else>

                <!-- EMPTY -->
                <div v-if="!items.length" class="text-center py-20 bg-white rounded-2xl shadow-sm">
                    <div class="text-6xl mb-4">🛍️</div>
                    <h3 class="text-2xl font-semibold text-gray-700 mb-2">Your cart is empty</h3>
                    <p class="text-gray-500 mb-6">Looks like you haven't added anything yet</p>
                    <router-link to="/en/all_events"
                        class="inline-block px-6 py-3 bg-gradient-to-r from-green-600 to-blue-600 text-white font-semibold rounded-lg hover:shadow-lg transition-all">
                        Start Shopping →
                    </router-link>
                </div>

                <!-- ITEMS -->
                <div v-else>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="item in items" :key="item.id"
                            class="group bg-white rounded-2xl shadow-md hover:shadow-xl transition overflow-hidden">

                            <!-- MEDIA -->
                            <div class="relative h-56 bg-gray-100 overflow-hidden">

                                <img v-if="!isVideo(item.full_url)" :src="getMediaUrl(item.full_url)"
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />

                                <div v-else class="relative w-full h-56 bg-black overflow-hidden">
                                    <video :data-id="item.id" class="w-full h-full object-cover" playsinline
                                        preload="metadata" @timeupdate="onTimeUpdate(item.id, $event)">
                                        <source :src="getMediaUrl(item.full_url)" />
                                    </video>

                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <button v-if="!playing[item.id]" @click="playVideo(item.id)"
                                            class="bg-white/90 hover:bg-white text-gray-800 p-4 rounded-full shadow-lg text-xl transition">
                                            ▶
                                        </button>
                                        <button v-else @click="pauseVideo(item.id)"
                                            class="bg-white/90 hover:bg-white text-gray-800 p-4 rounded-full shadow-lg text-xl transition">
                                            ⏸
                                        </button>
                                    </div>

                                    <div
                                        class="absolute bottom-0 left-0 right-0 bg-black/50 text-white px-3 py-2 flex items-center gap-3">
                                        <input type="range" min="0" max="100" :value="progress[item.id] || 0"
                                            @input="seek(item.id, $event)" class="flex-1 accent-green-400" />
                                        <span class="text-xs w-8 text-right">
                                            {{ Math.round(progress[item.id] || 0) }}%
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="absolute top-3 right-3 bg-white/90 px-2 py-1 rounded-lg text-sm font-semibold">
                                    In Stock
                                </div>
                            </div>

                            <!-- DETAILS -->
                            <div class="p-5">
                                <h3 class="font-semibold text-gray-800 text-lg mb-2 line-clamp-1">
                                    {{ item.name || 'Product' }}
                                </h3>

                                <div class="flex justify-between items-center mt-4">
                                    <div>
                                        <span v-if="item.old_price" class="text-sm text-gray-500 line-through">
                                            {{ item.old_price }} $
                                        </span>
                                        <p class="text-2xl font-bold text-green-600">
                                            {{ item.price || 0 }} $
                                        </p>
                                    </div>

                                    <button @click="removeItem(item.id)"
                                        class="p-2 text-red-500 hover:bg-red-50 rounded-full transition">
                                        🗑️
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- SUMMARY -->
                    <div class="mt-10 bg-white rounded-2xl shadow-lg p-6 md:p-8">
                        <h2 class="text-2xl font-bold mb-6">Order Summary</h2>

                        <div class="space-y-3 border-b pb-4">
                            <div class="flex justify-between">
                                <span>Subtotal ({{ items.length }} items)</span>
                                <span>{{ total.toFixed(2) }} $</span>
                            </div>
                            <!-- Wallet row -->
                            <div class="flex justify-between items-center pt-1">
                                <span class="flex items-center gap-2 text-sm font-medium" style="color: #1a1a2e;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path
                                            d="M21 12V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2v-5z" />
                                        <circle cx="17" cy="12" r="1.2" fill="currentColor" stroke="none" />
                                    </svg>
                                    Wallet Balance
                                </span>
                                <span class="font-semibold text-sm" style="color: #0f3460;">
                                    {{ parseFloat(walletAmount).toFixed(2) }} $
                                </span>
                            </div>
                        </div>

                        <div class="flex justify-between mt-4">
                            <div>
                                <p class="text-xl font-bold">Total</p>
                                <p class="text-sm text-gray-500">Including VAT</p>
                            </div>
                            <p class="text-3xl font-bold text-green-600">
                                {{ total.toFixed(2) }} $
                            </p>
                        </div>

                        <!-- ─── Pay with Wallet Button ──────────────────────── -->
                        <button @click="handleWalletCheckout"
                            :disabled="walletCheckoutLoading || parseFloat(walletAmount) < total"
                            :title="parseFloat(walletAmount) < total ? 'Insufficient wallet balance' : ''"
                            class="mt-6 w-full py-4 rounded-xl font-bold flex items-center justify-center gap-3 transition-all shadow-md relative overflow-hidden group"
                            :class="parseFloat(walletAmount) >= total
                                ? 'text-white hover:shadow-xl'
                                : 'cursor-not-allowed opacity-50 text-white'" :style="parseFloat(walletAmount) >= total
                                    ? 'background: linear-gradient(135deg, #1a1a2e 0%, #0f3460 100%);'
                                    : 'background: #9ca3af;'">

                            <!-- shimmer on hover -->
                            <span v-if="parseFloat(walletAmount) >= total"
                                class="absolute inset-0 opacity-0 group-hover:opacity-10 transition-opacity duration-300"
                                style="background: linear-gradient(90deg, transparent, white, transparent); transform: skewX(-20deg);"></span>

                            <span v-if="walletCheckoutLoading" class="flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                                </svg>
                                Processing...
                            </span>
                            <span v-else class="flex items-center gap-2 relative z-10">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M21 12V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2v-5z" />
                                    <circle cx="17" cy="12" r="1.5" fill="currentColor" stroke="none" />
                                    <path d="M3 9h18" stroke-linecap="round" />
                                </svg>
                                Pay with Wallet
                                <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold"
                                    style="background: rgba(255,255,255,0.15);">
                                    {{ total.toFixed(2) }} $
                                </span>
                            </span>
                        </button>
                        <p v-if="parseFloat(walletAmount) < total && !walletLoading"
                            class="text-center text-xs mt-2 text-amber-500 flex items-center justify-center gap-1">
                            ⚠️ You need {{ (total - parseFloat(walletAmount)).toFixed(2) }}$ more in your wallet
                        </p>

                        <!-- Divider -->
                        <div class="flex items-center gap-3 my-5">
                            <div class="flex-1 h-px bg-gray-200"></div>
                            <span class="text-xs text-gray-400 font-medium">OR</span>
                            <div class="flex-1 h-px bg-gray-200"></div>
                        </div>

                        <!-- PayPal Checkout Button -->
                        <button @click="handleCheckout" :disabled="checkoutLoading"
                            class="w-full py-4 bg-gradient-to-r from-yellow-400 to-yellow-500 hover:from-yellow-500 hover:to-yellow-600 text-gray-900 font-bold rounded-xl flex items-center justify-center gap-3 transition-all disabled:opacity-60 disabled:cursor-not-allowed shadow-md hover:shadow-lg">

                            <span v-if="checkoutLoading" class="flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                                </svg>
                                Redirecting to PayPal...
                            </span>

                            <span v-else class="flex items-center gap-2">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M20.067 8.478c.492.315.844.825.983 1.39.487 1.988-.803 4.108-3.026 4.108h-1.5l-.4 2.558a.75.75 0 01-.74.633H13.4a.25.25 0 01-.247-.29l.812-5.29c.057-.371.374-.642.747-.642h1.6c1.46 0 2.51-.645 2.917-1.626.054-.13.102-.265.14-.404.148.05.29.112.422.19l.276.373z" />
                                    <path
                                        d="M9.108 6.5h4.5c.548 0 1.072.07 1.557.205a4.5 4.5 0 011.38.595c-.408.981-1.459 1.626-2.917 1.626H12.03a.75.75 0 00-.746.642l-.812 5.29a.25.25 0 00.246.29h1.984l.4-2.558h1.5c2.223 0 3.513-2.12 3.026-4.108A3.32 3.32 0 0016.3 6.092 4.946 4.946 0 0013.608 5.5H9c-.373 0-.69.27-.747.642L7 14.5h1.756L9.108 6.5z" />
                                </svg>
                                Pay with PayPal — {{ total.toFixed(2) }} $
                            </span>
                        </button>

                        <!-- Security note -->
                        <p class="text-center text-xs text-gray-400 mt-3 flex items-center justify-center gap-1">
                            🔒 Secure checkout powered by PayPal. You'll be redirected to complete payment.
                        </p>
                    </div>

                    <div class="mt-6 text-center">
                        <router-link to="/en/all_events"
                            class="text-green-600 font-medium hover:text-green-700 transition">
                            ← Continue Shopping
                        </router-link>
                    </div>

                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { CartService } from "@/services/CartService";

// ══════════════════════════════════════════════════════════
// STATE
// ══════════════════════════════════════════════════════════

const items = ref([]);
const loading = ref(true);
const checkoutLoading = ref(false);
const walletCheckoutLoading = ref(false);
const walletLoading = ref(true);
const walletAmount = ref("0.00");

const toast = reactive({ show: false, message: '', type: 'success' });

// Video state
const playing = reactive({});
const progress = reactive({});

// ══════════════════════════════════════════════════════════
// TOAST
// ══════════════════════════════════════════════════════════

const showToast = (message, type = 'success', duration = 3000) => {
    toast.message = message;
    toast.type = type;
    toast.show = true;
    setTimeout(() => (toast.show = false), duration);
};

// ══════════════════════════════════════════════════════════
// COMPUTED
// ══════════════════════════════════════════════════════════

const total = computed(() =>
    items.value.reduce((sum, item) => sum + (parseFloat(item.price) || 0), 0)
);

const idempotencyKey = computed(() => {
    const ids = [...items.value.map(i => i.id)].sort().join('-');
    return `cart-${ids}-${total.value.toFixed(2)}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
});

// ══════════════════════════════════════════════════════════
// MEDIA HELPERS
// ══════════════════════════════════════════════════════════

const getMediaUrl = (path) => {
    if (!path) return "";
    return `http://localhost:8000/storage/${path}`;
};

const isVideo = (path) => {
    if (!path) return false;
    return /\.(mp4|webm|ogg)$/i.test(path);
};

// ══════════════════════════════════════════════════════════
// VIDEO CONTROLS
// ══════════════════════════════════════════════════════════

const getVideo = (id) =>
    document.querySelector(`video[data-id="${id}"]`);

const playVideo = (id) => {
    const video = getVideo(id);
    if (!video) return;
    video.play();
    playing[id] = true;
};

const pauseVideo = (id) => {
    const video = getVideo(id);
    if (!video) return;
    video.pause();
    playing[id] = false;
};

const seek = (id, event) => {
    const video = getVideo(id);
    if (!video || !video.duration) return;
    video.currentTime = (event.target.value / 100) * video.duration;
};

const onTimeUpdate = (id, event) => {
    const video = event.target;
    if (!video.duration) return;
    progress[id] = (video.currentTime / video.duration) * 100;
};

// ══════════════════════════════════════════════════════════
// CART ACTIONS
// ══════════════════════════════════════════════════════════

const fetchCart = async () => {
    try {
        const res = await CartService.getCart();
        items.value = res.data || [];
    } catch (e) {
        console.error(e);
        showToast('Failed to load cart', 'error');
    } finally {
        loading.value = false;
    }
};

const fetchWallet = async () => {
    walletLoading.value = true;
    try {
        const res = await CartService.wallet();
        // Support both response shapes:
        // { data: { amount: "100.00" } }  OR  { data: [...] }
        const d = res.data;
        if (d && typeof d === 'object' && !Array.isArray(d) && d.amount !== undefined) {
            walletAmount.value = d.amount;
        }
    } catch (e) {
        console.error(e);
        showToast('Failed to load wallet', 'error');
    } finally {
        walletLoading.value = false;
    }
};

const removeItem = async (id) => {
    try {
        await CartService.deleteFromCart(id);
        items.value = items.value.filter(i => i.id !== id);
        showToast('Item removed ✅');
    } catch (e) {
        console.error(e);
        showToast('Failed to remove item', 'error');
    }
};

const clearCart = async () => {
    try {
        await CartService.clearCart();
        items.value = [];
        showToast('Cart cleared 🗑️');
    } catch (e) {
        console.error(e);
        showToast('Failed to clear cart', 'error');
    }
};

// ══════════════════════════════════════════════════════════
// PAYPAL CHECKOUT
// ══════════════════════════════════════════════════════════

const handleCheckout = async () => {
    if (checkoutLoading.value || !items.value.length) return;

    checkoutLoading.value = true;

    try {
        const res = await fetch("http://localhost:8000/api/v1/pay", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "Authorization": `Bearer ${localStorage.getItem("auth_token") || ""}`,
            },
            body: JSON.stringify({
                amount: total.value,
                description: `Cart (${items.value.length} items)`,
                idempotency_key: idempotencyKey.value,
            }),
        });

        const data = await res.json();

        if (!res.ok || !data.success) {
            throw new Error(data.message || "Payment initiation failed");
        }

        window.location.href = data.approval_url;

    } catch (e) {
        console.error(e);
        showToast(e.message || "Checkout failed. Please try again.", 'error', 4000);
        checkoutLoading.value = false;
    }
};

// ══════════════════════════════════════════════════════════
// WALLET CHECKOUT
// ══════════════════════════════════════════════════════════

const handleWalletCheckout = async () => {
    if (walletCheckoutLoading.value || !items.value.length) return;
    if (parseFloat(walletAmount.value) < total.value) {
        showToast('Insufficient wallet balance', 'error');
        return;
    }

    walletCheckoutLoading.value = true;

    try {
        const data = await CartService.payWithWallet({
            amount: total.value,
            description: `Cart (${items.value.length} items)`,
            idempotency_key: idempotencyKey.value,
        });

        if (!data.success) {
            throw new Error(data.message || "Wallet payment failed");
        }

        walletAmount.value = (parseFloat(walletAmount.value) - total.value).toFixed(2);
        items.value = [];
        showToast('Payment successful! 🎉 Paid from wallet.', 'success', 5000);
        window.location.reload();
        window.location.href = '/en/downloads';
    } catch (e) {
        console.error(e);
        showToast(e.message || "Wallet payment failed. Please try again.", 'error', 4000);
    } finally {
        walletCheckoutLoading.value = false;
    }
};

// ══════════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════════

onMounted(() => {
    fetchCart();
    fetchWallet();
});
</script>

<style scoped>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
