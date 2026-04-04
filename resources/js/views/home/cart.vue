<template>
    <!-- Toast -->
    <div v-if="showToast" class="fixed top-5 right-5 bg-green-600 text-white px-4 py-2 rounded-lg shadow z-50">
        Item removed ✅
    </div>

    <div class="min-h-screen bg-gray-50 py-8">
        <div class="container mx-auto px-4 max-w-6xl">

            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                    <span class="text-4xl">🛒</span>
                    Shopping Cart
                </h1>

                <button v-if="items.length" @click="clearCart"
                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded font-semibold transition">
                    Clear Cart 🗑️
                </button>
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

                                <!-- IMAGE -->
                                <img v-if="!isVideo(item.full_url)" :src="getMediaUrl(item.full_url)"
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />

                                <div v-else class="relative w-full h-56 bg-black overflow-hidden group">

                                    <!-- VIDEO -->
                                    <video ref="videoRef" class="w-full h-full object-cover" playsinline
                                        preload="metadata" @click="togglePlay">
                                        <source :src="getMediaUrl(item.full_url)" />
                                    </video>

                                    <!-- CUSTOM OVERLAY CONTROLS -->
                                    <div class="absolute inset-0 flex items-center justify-center">

                                        <!-- Play Button -->
                                        <button v-if="!playing[item.id]" @click="playVideo(item.id)"
                                            class="bg-black/80 hover:bg-black text-black p-3 rounded-full shadow-lg">
                                            ▶
                                        </button>

                                    </div>

                                    <!-- Bottom Controls -->
                                    <div
                                        class="absolute bottom-0 left-0 right-0 bg-black/50 text-white px-3 py-2 flex items-center gap-3">

                                        <!-- Progress -->
                                        <input type="range" min="0" max="100" v-model="progress[item.id]"
                                            @input="seek(item.id)" class="flex-1" />

                                        <!-- Time -->
                                        <span class="text-xs">
                                            {{ progress[item.id] || 0 }}%
                                        </span>

                                    </div>

                                </div>

                                <!-- badge -->
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
                                        class="p-2 text-red-500 hover:bg-red-50 rounded-full">
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
                                <span>Subtotal</span>
                                <span>{{ total.toFixed(2) }} $</span>
                            </div>

                            <div class="flex justify-between">
                                <span>Shipping</span>
                                <span class="text-green-600">Free</span>
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

                        <!-- checkout -->
                        <button @click="handleCheckout"
                            class="mt-8 w-full py-4 bg-gradient-to-r from-green-600 to-blue-600 text-white font-bold rounded-xl">
                            Proceed to Checkout →
                        </button>

                    </div>

                    <!-- continue -->
                    <div class="mt-6 text-center">
                        <router-link to="/en/all_events" class="text-green-600 font-medium">
                            ← Continue Shopping
                        </router-link>
                    </div>

                </div>

            </div>

        </div>
    </div>
</template>

<script setup>
import { onMounted, computed } from "vue";
import { CartService } from "@/services/CartService";

import { ref, reactive } from "vue";

const videoRef = ref(null);

const playing = reactive({});
const progress = reactive({});

/* PLAY / PAUSE */
const togglePlay = (id) => {
    const video = document.querySelector(`video[data-id='${id}']`);

    if (!video) return;

    if (video.paused) {
        video.play();
        playing[id] = true;
    } else {
        video.pause();
        playing[id] = false;
    }
};

/* PLAY DIRECT */
const playVideo = (id) => {
    const video = document.querySelectorAll("video")[0]; // simple fallback
    if (!video) return;

    video.play();
    playing[id] = true;
};

/* SEEK */
const seek = (id) => {
    const video = document.querySelectorAll("video")[0];
    if (!video) return;

    const time = (progress[id] / 100) * video.duration;
    video.currentTime = time;
};

/* AUTO PROGRESS UPDATE */
const attachProgress = () => {
    setInterval(() => {
        document.querySelectorAll("video").forEach((video, index) => {
            if (!video.duration) return;

            const id = Object.keys(playing)[index];

            progress[id] =
                (video.currentTime / video.duration) * 100 || 0;
        });
    }, 500);
};
const items = ref([]);
const loading = ref(true);
const showToast = ref(false);

/* ---------------- TOTAL ---------------- */
const total = computed(() => {
    return items.value.reduce((sum, item) => {
        return sum + (parseFloat(item.price) || 0);
    }, 0);
});

/* ---------------- MEDIA ---------------- */
const getMediaUrl = (path) => {
    if (!path) return "";
    return `http://localhost:8000/storage/${path}`;
};

const isVideo = (path) => {
    if (!path) return false;

    return (
        path.includes(".mp4") ||
        path.includes(".webm") ||
        path.includes(".ogg")
    );
};

/* ---------------- FETCH CART ---------------- */
const fetchCart = async () => {
    try {
        const res = await CartService.getCart();
        items.value = res.data || [];
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

/* ---------------- REMOVE ITEM ---------------- */
const removeItem = async (id) => {
    try {
        await CartService.deleteFromCart(id);
        items.value = items.value.filter(i => i.id !== id);

        showToast.value = true;
        setTimeout(() => (showToast.value = false), 2000);

    } catch (e) {
        console.error(e);
    }
};

/* ---------------- CLEAR CART ---------------- */
const clearCart = async () => {
    try {
        await CartService.clearCart();
        items.value = [];
    } catch (e) {
        console.error(e);
    }
};

/* ---------------- CHECKOUT ---------------- */
const handleCheckout = async () => {
    try {
        loading.value = true;

        const res = await fetch("http://localhost:8000/api/v1/purchase", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "Authorization": `Bearer ${localStorage.getItem("auth_token") || ""}`
            },
            body: JSON.stringify({
                total: total.value
            })
        });

        const data = await res.json();

        if (!res.ok) throw new Error(data.message);

        items.value = [];

        const lang = localStorage.getItem("language") || "en";
        window.location.href = `/${lang}/downloads`;

    } catch (e) {
        console.error(e);
        alert(e.message || "Checkout failed");
    } finally {
        loading.value = false;
    }
};

/* ---------------- INIT ---------------- */
onMounted(() => {
    fetchCart();
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
