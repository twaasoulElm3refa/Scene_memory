<template>
  <div class="waiting-container">
    <div class="card">
      <div class="spinner"></div>
      <h2>Processing Your Payment</h2>
      <p>Please wait while we confirm your payment with PayPal...</p>
      <p class="hint">Do not close this page</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import axios from "axios";

const router = useRouter();
const route = useRoute();
const orderId = route.query.order_id;
let interval = null;
const attempts = ref(0);
const MAX_ATTEMPTS = 30;

const checkStatus = async () => {
  try {
    attempts.value++;
    const url = `${import.meta.env.VITE_API_URL}/api/v1/order/status/${orderId}`;
    const { data } = await axios.get(url);
    if (data.status === "completed") {
      clearInterval(interval);
      router.push(`/en/success`);
    } else if (data.status === "failed") {
      clearInterval(interval);
      router.push(`/en/failed`);
    } else if (attempts.value >= MAX_ATTEMPTS) {
      clearInterval(interval);
      router.push(`/en/success`);
    }
  } catch (e) {
    console.error("Polling error:", e.response?.status, e.response?.data, e.message);
  }
};

onMounted(() => {
  if (!orderId) {
    router.push("/en/failed");
    return;
  }
  checkStatus();
  interval = setInterval(checkStatus, 5000);
});

onUnmounted(() => clearInterval(interval));
</script>

<style scoped>
.waiting-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f5f5;
}

.card {
  background: white;
  border-radius: 16px;
  padding: 48px 40px;
  text-align: center;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
  max-width: 420px;
  width: 90%;
}

.spinner {
  width: 56px;
  height: 56px;
  border: 5px solid #e0e0e0;
  border-top-color: #0070ba;
  border-radius: 50%;
  animation: spin 0.9s linear infinite;
  margin: 0 auto 24px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

h2 {
  font-size: 1.4rem;
  color: #1a1a1a;
  margin-bottom: 10px;
}

p {
  color: #666;
  font-size: 0.95rem;
}

.hint {
  margin-top: 8px;
  font-size: 0.82rem;
  color: #aaa;
}
</style>
