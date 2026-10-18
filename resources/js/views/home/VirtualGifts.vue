<template>
    <main class="gift-return scemory-page" :dir="['ar','fa','ur'].includes(locale) ? 'rtl' : 'ltr'">
        <h1>{{ t('gifts.paymentStatus') }}</h1>
        <p v-if="error" role="alert">{{ error }}</p>
        <p v-if="loading" role="status">{{ t('gifts.loading') }}</p>
        <section v-if="checkout" class="gift-panel" aria-live="polite">
            <img v-if="checkout.gift_snapshot?.image_url" :src="toAssetUrl(checkout.gift_snapshot.image_url)" alt="" />
            <h2>{{ giftName(checkout.gift_snapshot, locale, t) }} × {{ checkout.quantity }}</h2>
            <p>{{ t('gifts.recipient') }}: {{ checkout.recipient?.name }}</p>
            <p>{{ t('gifts.paymentStatus') }}: {{ t(`gifts.${checkout.payment_status}`) }}</p>
            <p>{{ t('gifts.deliveryStatus') }}: {{ t(`gifts.${checkout.delivery_status}`) }}</p>
            <p>{{ giftMoney(checkout.gross_amount_minor, checkout.currency, locale) }}</p>
            <button v-if="checkout.can_capture && ['pending','failed'].includes(checkout.payment_status)" :disabled="busy" @click="verify">{{ t('gifts.verify') }}</button>
        </section>
        <RouterLink :to="`/${locale}/profile?tab=gifts`">{{ t('gifts.history') }}</RouterLink>
    </main>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { giftMoney, giftName, giftRequest } from '@/services/virtualGiftService';
import { toAssetUrl } from '@/services/runtimeUrls';

const route = useRoute(), { locale, t } = useI18n();
const checkout = ref(null), loading = ref(false), busy = ref(false), error = ref('');
let pollTimer, pollAttempts = 0;
function clearCompletedIntent() {
    if (!checkout.value || !['paid','refunded','reversed'].includes(checkout.value.payment_status)) return;
    sessionStorage.removeItem('scemory_pending_gift');
    for (const key of Object.keys(sessionStorage)) {
        if (!key.startsWith('scemory_gift_intent:')) continue;
        try { if (JSON.parse(sessionStorage.getItem(key)).reference === checkout.value.reference) sessionStorage.removeItem(key); } catch { /* Ignore unrelated state. */ }
    }
}
function schedulePoll() {
    clearTimeout(pollTimer);
    if (++pollAttempts > 30) return;
    pollTimer = setTimeout(async () => {
        try {
            checkout.value = await giftRequest('get', `/gifts/${checkout.value.reference}/status`);
            clearCompletedIntent();
            if (checkout.value.payment_status === 'pending') schedulePoll();
        } catch (failure) { error.value = failure.response?.data?.message || t('gifts.loadError'); }
    }, 3000);
}
async function verify() {
    if (busy.value || !checkout.value) return;
    busy.value = true; error.value = '';
    try {
        checkout.value = await giftRequest('post', '/gifts/paypal/capture', { reference: checkout.value.reference });
        clearCompletedIntent();
        if (checkout.value.payment_status === 'pending') schedulePoll();
    } catch (failure) { error.value = failure.response?.data?.message || t('gifts.paymentFailed'); }
    finally { busy.value = false; }
}
async function loadCheckout() {
    const reference = route.query.reference || sessionStorage.getItem('scemory_pending_gift');
    if (!reference) return;
    loading.value = true; error.value = '';
    try {
        checkout.value = await giftRequest('get', `/gifts/${reference}/status`);
        if (route.query.cancelled === '1') checkout.value = await giftRequest('post', `/gifts/${reference}/cancel`);
        else if (checkout.value.can_capture && checkout.value.payment_status === 'pending') await verify();
        if (checkout.value.payment_status === 'pending') schedulePoll();
        clearCompletedIntent();
    } catch (failure) { error.value = failure.response?.data?.message || t('gifts.loadError'); }
    finally { loading.value = false; }
}
watch(() => route.query.reference, loadCheckout);
onMounted(loadCheckout);
onUnmounted(() => clearTimeout(pollTimer));
</script>

<style scoped>
.gift-return { max-width: 720px; margin: auto; padding: 32px 18px; color: #172036; }
.gift-panel { border: 1px solid #d9e8e3; border-radius: 18px; padding: 24px; background: white; margin: 18px 0; }
.gift-panel img { width: 72px; height: 72px; object-fit: contain; }
button { border: 1px solid #cbded6; background: #047857; color: white; padding: 9px 14px; border-radius: 10px; }
button:disabled { opacity: .5; }
[role=alert] { color: #b91c1c; }
</style>
