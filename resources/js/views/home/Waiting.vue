<template>
  <div class="waiting-container">
    <div class="card">
      <div v-if="!timedOut" class="spinner"></div>
      <h2>Processing Your Payment</h2>
      <p>Please wait while we confirm your payment with PayPal.</p>
      <p class="hint">
        {{ timedOut
          ? "Confirmation is taking longer than expected. Your payment may still complete; you can safely revisit this page."
          : "You may close this page; confirmation continues securely in the background." }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import { PaymentService } from "../../services/PaymentService/PaymentService";
import { pollOrderStatus } from "../../services/PaymentService/pollOrderStatus";
import { clearIdempotencyKey } from "../../services/PaymentService/checkoutSession";

const router = useRouter();
const route = useRoute();
const orderId = route.query.order_id;
const lang = localStorage.getItem("lang") || "en";
const timedOut = ref(false);
const controller = new AbortController();

onMounted(async () => {
  if (!orderId) {
    router.replace(`/${lang}/failed`);
    return;
  }

  const result = await pollOrderStatus({
    signal: controller.signal,
    fetchStatus: async (signal) => (await PaymentService.orderStatus(orderId, signal)).data.status,
  });
  if (result.outcome === "completed") {
    clearIdempotencyKey("cart:paypal");
    router.replace(`/${lang}/success`);
  } else if (["failed", "terminal_error"].includes(result.outcome)) {
    router.replace(`/${lang}/failed`);
  } else if (result.outcome === "timeout") {
    timedOut.value = true;
  }
});

onUnmounted(() => controller.abort());
</script>

<style scoped>
.waiting-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f5f5f5; }
.card { background: white; border-radius: 16px; padding: 48px 40px; text-align: center; box-shadow: 0 4px 24px rgba(0,0,0,.1); max-width: 480px; width: 90%; }
.spinner { width: 56px; height: 56px; border: 5px solid #e5e7eb; border-top-color: #2563eb; border-radius: 50%; margin: 0 auto 24px; animation: spin .9s linear infinite; }
.hint { color: #6b7280; font-size: .9rem; margin-top: 16px; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
