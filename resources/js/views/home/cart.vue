<template>
    <div v-if="showToast" class="fixed top-5 right-5 bg-green-600 text-white px-4 py-2 rounded-lg shadow">
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

            <!-- Loading State -->
            <div v-if="loading" class="flex justify-center items-center py-20">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>
            </div>

            <div v-else>
                <!-- Empty Cart -->
                <div v-if="!items.length" class="text-center py-20 bg-white rounded-2xl shadow-sm">
                    <div class="text-6xl mb-4">🛍️</div>
                    <h3 class="text-2xl font-semibold text-gray-700 mb-2">Your cart is empty</h3>
                    <p class="text-gray-500 mb-6">Looks like you haven't added anything yet</p>
                    <router-link to="/en/all_events"
                        class="inline-block px-6 py-3 bg-gradient-to-r from-green-600 to-blue-600 text-white font-semibold rounded-lg hover:shadow-lg transition-all">
                        Start Shopping →
                    </router-link>
                </div>

                <!-- Cart Items -->
                <div v-else>
                    <!-- Items Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="item in items" :key="item.id"
                            class="group bg-white rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden">
                            <!-- Image Container -->
                            <div class="relative overflow-hidden bg-gray-100 h-56">
                                <img :src="getImageUrl(item.full_url)" :alt="item.name || 'Product image'"
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                                <!-- Badge -->
                                <div
                                    class="absolute top-3 right-3 bg-white/90 backdrop-blur-sm px-2 py-1 rounded-lg text-sm font-semibold text-gray-700">
                                    In Stock
                                </div>
                            </div>

                            <!-- Item Details -->
                            <div class="p-5">
                                <h3 class="font-semibold text-gray-800 text-lg mb-2 line-clamp-1">
                                    {{ item.name || 'Product' }}
                                </h3>

                                <!-- Price and Actions -->
                                <div class="flex justify-between items-center mt-4">
                                    <div class="space-y-1">
                                        <span class="text-sm text-gray-500 line-through" v-if="item.old_price">
                                            {{ item.old_price }} $
                                        </span>
                                        <p class="text-2xl font-bold text-green-600">
                                            {{ item.price }} $
                                        </p>
                                    </div>

                                    <button @click="removeItem(item.id)"
                                        class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-full transition-colors"
                                        title="Remove item">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="mt-10 bg-white rounded-2xl shadow-lg p-6 md:p-8">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">Order Summary</h2>

                        <div class="space-y-3 border-b border-gray-200 pb-4">
                            <div class="flex justify-between text-gray-600">
                                <span>Subtotal</span>
                                <span class="font-medium">{{ total.toFixed(2) }} $</span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Shipping</span>
                                <span class="text-green-600 font-medium">Free</span>
                            </div>
                        </div>

                        <div class="flex justify-between items-center mt-4 pt-2">
                            <div>
                                <span class="text-xl font-bold text-gray-800">Total</span>
                                <p class="text-sm text-gray-500">Including VAT</p>
                            </div>
                            <span
                                class="text-3xl font-bold bg-gradient-to-r from-green-600 to-blue-600 bg-clip-text text-transparent">
                                {{ total.toFixed(2) }} $
                            </span>
                        </div>

                        <!-- Checkout Button -->
                        <div class="mt-8">
                            <button @click="handleCheckout"
                                class="w-full py-4 bg-gradient-to-r from-green-600 to-blue-600 text-white font-bold rounded-xl hover:shadow-xl transform hover:scale-[1.02] transition-all duration-300 flex items-center justify-center gap-2 group">
                                <span>Proceed to Checkout</span>
                                <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </button>

                            <p class="text-center text-sm text-gray-500 mt-4">
                                Secure payment • 30-day money back guarantee
                            </p>
                        </div>
                    </div>

                    <!-- Continue Shopping Button -->
                    <div class="mt-6 text-center">
                        <router-link to="/products"
                            class="inline-flex items-center gap-2 text-green-600 hover:text-green-700 font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Continue Shopping
                        </router-link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { CartService } from "@/services/CartService";

const showToast = ref(false);

const items = ref([]);
const loading = ref(true);

// ✅ total
const total = computed(() => {
    return items.value.reduce((sum, item) => {
        return sum + parseFloat(item.price);
    }, 0);
});

// ✅ base image
const getImageUrl = (path) => {
    return `http://localhost:8000/storage/${path}`;
};

// ✅ fetch cart
const fetchCart = async () => {
    try {
        const res = await CartService.getCart();
        items.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

// ✅ checkout handler - doesn't do anything
const handleCheckout = () => {
    console.log("Checkout button clicked - coming soon!");
};


const removeItem = async (itemId) => {
    try {
        await CartService.deleteFromCart(itemId);

        items.value = items.value.filter(item => item.id !== itemId);

        showToast.value = true;
        setTimeout(() => (showToast.value = false), 2000);
    } catch (e) {
        console.error(e);
    }
};

onMounted(() => {
    fetchCart();
});

const clearCart = async () => {
    try {
        await CartService.clearCart();
        items.value = [];

        alert("Cart cleared 🧹");
    } catch (e) {
        console.error(e);
        alert("Error clearing cart ❌");
    }
};
</script>

<style scoped>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
