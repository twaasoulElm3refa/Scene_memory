<template>
    <main class="gift-page scemory-page" :dir="['ar','fa','ur'].includes(locale) ? 'rtl' : 'ltr'">
        <h1>{{ t('gifts.history') }}</h1>
        <p v-if="error" role="alert">{{ error }}</p>
        <section v-if="checkout" class="gift-panel" aria-live="polite">
            <h2>{{ t('gifts.paymentStatus') }}</h2>
            <p>{{ checkout.recipient?.name }} · {{ name(checkout.gift_snapshot) }} × {{ checkout.quantity }}</p>
            <p>{{ t(`gifts.${checkout.payment_status}`) }}</p>
            <p v-if="checkout.delivery_status === 'delivered' && checkout.payment_status === 'paid'">✦ {{ t('gifts.sentSuccess') }}</p>
            <button v-if="checkout.can_capture && ['pending','failed'].includes(checkout.payment_status)" :disabled="busy" @click="verify">{{ t('gifts.verify') }}</button>
        </section>
        <nav>
            <button v-for="item in ['sent','received']" :key="item" :aria-pressed="tab === item" @click="loadHistory(item, 1)">{{ t(`gifts.${item}`) }}</button>
        </nav>
        <p v-if="loading" role="status">{{ t('gifts.loading') }}</p>
        <p v-else-if="!history.length">{{ t('gifts.empty') }}</p>
        <article v-for="gift in history" :key="gift.reference" class="gift-panel gift-row">
            <img v-if="gift.gift_snapshot.image_url" :src="toAssetUrl(gift.gift_snapshot.image_url)" alt="" />
            <div><strong>{{ name(gift.gift_snapshot) }} × {{ gift.quantity }}</strong><p>{{ gift.sender?.name }} → {{ gift.recipient?.name }}</p><small>{{ t(`gifts.${gift.payment_status}`) }}</small></div>
            <strong>{{ money(gift.gross_amount_minor, gift.currency) }}</strong>
            <RouterLink :to="`/${locale}/gifts?reference=${gift.reference}`">{{ t('gifts.paymentStatus') }}</RouterLink>
        </article>
        <div class="gift-pagination"><button :disabled="page <= 1 || loading" @click="loadHistory(tab, page - 1)">{{ t('gifts.previous') }}</button><span>{{ page }} / {{ lastPage }}</span><button :disabled="page >= lastPage || loading" @click="loadHistory(tab, page + 1)">{{ t('gifts.next') }}</button></div>
        <section class="gift-panel">
            <h2>{{ t('gifts.earnings') }}</h2>
            <p>{{ t('gifts.holding', { days: holdingDays }) }}</p>
            <div v-for="balance in balances" :key="balance.currency" class="gift-balance">
                <p v-for="bucket in buckets" :key="bucket">{{ t(`gifts.${bucket === 'pending' ? 'pendingEarnings' : bucket === 'available' ? 'availableEarnings' : bucket}`) }}: <strong>{{ money(bucket === 'available' ? balance.withdrawable_minor : balance[`${bucket}_minor`], balance.currency) }}</strong></p>
            </div>
            <form @submit.prevent="withdraw">
                <h3>{{ t('gifts.requestWithdrawal') }}</h3>
                <label>{{ t('gifts.amount') }} <input v-model="withdrawAmount" inputmode="decimal" required pattern="[0-9]+(\.[0-9]{1,2})?" /></label>
                <label>{{ t('gifts.currency') }} <select v-model="withdrawCurrency" required><option v-for="balance in balances" :key="balance.currency" :value="balance.currency">{{ balance.currency }}</option></select></label>
                <label>{{ t('gifts.paymentMethod') }} <input v-model="payoutMethod" maxlength="100" required /></label>
                <label>{{ t('gifts.destination') }} <input v-model="destination" maxlength="200" required /></label>
                <button :disabled="busy || !balances.length">{{ t('gifts.requestWithdrawal') }}</button>
            </form>
            <h3>{{ t('gifts.withdrawals') }}</h3>
            <article v-for="withdrawal in withdrawals" :key="withdrawal.reference">
                {{ money(withdrawal.amount_minor, withdrawal.currency) }} · {{ t(`gifts.${withdrawal.status}`) }}
                <button v-if="withdrawal.status === 'pending'" :disabled="busy" @click="cancelWithdrawal(withdrawal)">{{ t('gifts.cancel') }}</button>
            </article>
        </section>
    </main>
</template>
<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { giftKey, giftMoney, giftName, giftRequest } from '@/services/virtualGiftService';
import { toAssetUrl } from '@/services/runtimeUrls';
const { locale, t } = useI18n(); const route = useRoute();
const history = ref([]), balances = ref([]), withdrawals = ref([]), checkout = ref(null), tab = ref('sent');
const loading = ref(false), busy = ref(false), error = ref(''), page = ref(1), lastPage = ref(1), holdingDays = ref(7);
const withdrawAmount = ref(''), withdrawCurrency = ref('USD'), payoutMethod = ref(''), destination = ref('');
let withdrawalKey = giftKey();
let pollTimer;
let pollAttempts = 0;
const buckets = ['pending', 'available', 'reserved', 'settled', 'reversed', 'recovery'];
const name = (gift) => giftName(gift, locale.value, t), money = (minor, currency) => giftMoney(minor, currency, locale.value);
async function loadHistory(nextTab = tab.value, requestedPage = 1) {
    loading.value = true; error.value = '';
    try { const data = await giftRequest('get', `/gifts/${nextTab}`, null, { page: requestedPage }); history.value = data.data; page.value = data.current_page; lastPage.value = data.last_page; tab.value = nextTab; }
    catch { error.value = t('gifts.loadError'); }
    finally { loading.value = false; }
}
async function loadAccount() {
    const [account, payouts] = await Promise.all([giftRequest('get', '/gifts/earnings'), giftRequest('get', '/gifts/withdrawals')]);
    balances.value = account.balances; holdingDays.value = account.holding_days; withdrawals.value = payouts.data;
    if (balances.value.length && !balances.value.some((balance) => balance.currency === withdrawCurrency.value)) withdrawCurrency.value = balances.value[0].currency;
}
async function loadCheckout() {
    const reference = route.query.reference || sessionStorage.getItem('scemory_pending_gift');
    if (!reference) return;
    checkout.value = await giftRequest('get', `/gifts/${reference}/status`);
    if (route.query.cancelled === '1') checkout.value = await giftRequest('post', `/gifts/${reference}/cancel`);
    else if (checkout.value.can_capture && checkout.value.payment_status === 'pending') await verify();
}
async function verify() {
    busy.value = true; error.value = '';
    try { checkout.value = await giftRequest('post', '/gifts/paypal/capture', { reference: checkout.value.reference }); clearCompletedIntent(); if (checkout.value.payment_status === 'pending') schedulePoll(); await loadHistory(); await loadAccount(); }
    catch { error.value = t('gifts.paymentFailed'); }
    finally { busy.value = false; }
}
function clearCompletedIntent() {
    if (!checkout.value || !['paid','refunded','reversed'].includes(checkout.value.payment_status)) return;
    sessionStorage.removeItem('scemory_pending_gift');
    for (const key of Object.keys(sessionStorage)) {
        if (!key.startsWith('scemory_gift_intent:')) continue;
        try { if (JSON.parse(sessionStorage.getItem(key)).reference === checkout.value.reference) sessionStorage.removeItem(key); } catch { /* Ignore unrelated invalid local state. */ }
    }
}
function schedulePoll() {
    clearTimeout(pollTimer);
    if (++pollAttempts > 30) return;
    pollTimer = setTimeout(async () => {
        try {
            checkout.value = await giftRequest('get', `/gifts/${checkout.value.reference}/status`);
            clearCompletedIntent();
            if (checkout.value.payment_status === 'pending') schedulePoll(); else { await loadHistory(); await loadAccount(); }
        } catch { error.value = t('gifts.loadError'); }
    }, 3000);
}
async function withdraw() {
    busy.value = true; error.value = '';
    try {
        const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(withdrawAmount.value);
        if (!match) throw new Error();
        const amount = Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0'));
        if (!Number.isSafeInteger(amount) || amount < 1) throw new Error();
        await giftRequest('post', '/gifts/withdrawals', { amount_minor: amount, currency: withdrawCurrency.value, idempotency_key: withdrawalKey, payment_details: { method: payoutMethod.value, destination: destination.value } });
        withdrawalKey = giftKey(); withdrawAmount.value = ''; await loadAccount();
    } catch { error.value = t('gifts.withdrawalFailed'); }
    finally { busy.value = false; }
}
async function cancelWithdrawal(withdrawal) { busy.value = true; try { await giftRequest('post', `/gifts/withdrawals/${withdrawal.reference}/cancel`); await loadAccount(); } catch { error.value = t('gifts.withdrawalFailed'); } finally { busy.value = false; } }
watch(() => route.query.reference, () => loadCheckout().catch(() => { error.value = t('gifts.loadError'); }));
onMounted(async () => { await loadHistory(); try { await loadAccount(); await loadCheckout(); } catch { error.value = t('gifts.loadError'); } });
onUnmounted(() => clearTimeout(pollTimer));
</script>
<style scoped>
.gift-page { max-width: 960px; margin: auto; padding: 32px 18px; color: #172036; } h1 { font-size: 1.8rem; } h2 { font-size: 1.3rem; } h3 { font-size: 1.1rem; } .gift-panel { border: 1px solid #d9e8e3; border-radius: 18px; padding: 20px; background: white; margin-block: 16px; } .gift-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; } .gift-row img { width: 56px; height: 56px; object-fit: contain; } .gift-row div { flex: 1; } button { border: 1px solid #cbded6; background: #ecfdf5; color: #047857; padding: 8px 14px; border-radius: 10px; } button:disabled { opacity: .5; } nav, .gift-pagination { display: flex; gap: 12px; align-items: center; margin-block: 16px; } form { display: grid; gap: 12px; } label { display: grid; gap: 5px; } input, select { border: 1px solid #cbded6; border-radius: 8px; padding: 8px; width: 100%; } [role=alert] { color: #b91c1c; } .gift-balance { padding: 12px; background: #f0fdf8; margin-block: 12px; }
</style>
