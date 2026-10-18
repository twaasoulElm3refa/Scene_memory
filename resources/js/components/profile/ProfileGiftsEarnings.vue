<template>
    <section class="profile-gifts" :dir="['ar','fa','ur'].includes(locale) ? 'rtl' : 'ltr'">
        <div class="gift-tabs" role="tablist" :aria-label="t('gifts.history')">
            <button type="button" role="tab" :aria-selected="tab === 'sent'" @click="tab = 'sent'; loadSent(1)">{{ t('gifts.sent') }}</button>
            <button type="button" role="tab" :aria-selected="tab === 'earnings'" @click="tab = 'earnings'; loadAccount()">{{ t('gifts.earningsWithdrawals') }}</button>
        </div>
        <p v-if="error" class="gift-error" role="alert">{{ error }}</p>
        <p v-if="notice" class="gift-notice" role="status">{{ notice }}</p>
        <section v-if="tab === 'sent'" role="tabpanel">
            <p v-if="loading" role="status">{{ t('gifts.loading') }}</p>
            <p v-else-if="!sent.length && !error">{{ t('gifts.noSentGifts') }}</p>
            <article v-for="gift in sent" :key="gift.reference" class="gift-card">
                <img v-if="gift.gift_snapshot?.image_url" :src="toAssetUrl(gift.gift_snapshot.image_url)" alt="" />
                <div class="gift-card-content">
                    <strong>{{ giftName(gift.gift_snapshot, locale, t) }} × {{ gift.quantity }}</strong>
                    <p>{{ t('gifts.recipient') }}: {{ gift.recipient?.name || '—' }} <span v-if="gift.comment_id">· {{ t('gifts.comment') }} #{{ gift.comment_id }}</span></p>
                    <p>{{ t('gifts.event') }}: {{ gift.event_title || `#${gift.event_id}` }}</p>
                    <p>{{ t('gifts.paymentMethod') }}: {{ gift.payment_method === 'wallet' ? t('gifts.wallet') : t('gifts.paypal') }}</p>
                    <p>{{ t('gifts.paymentStatus') }}: {{ gift.disputed ? t('gifts.disputed') : t(`gifts.${gift.payment_status}`) }} · {{ t('gifts.deliveryStatus') }}: {{ t(`gifts.${gift.delivery_status}`) }}</p>
                    <small>{{ formatDate(gift.created_at) }}</small>
                    <div class="gift-links"><RouterLink v-if="gift.event_slug" :to="`/${locale}/single_event/${gift.event_slug}`">{{ t('gifts.event') }}</RouterLink><RouterLink :to="`/${locale}/gifts?reference=${gift.reference}`">{{ t('gifts.paymentStatus') }}</RouterLink></div>
                </div>
                <strong>{{ money(gift.gross_amount_minor, gift.currency) }}</strong>
            </article>
            <div v-if="sentPage.last_page > 1" class="gift-pagination"><button :disabled="loading || sentPage.current_page <= 1" @click="loadSent(sentPage.current_page - 1)">{{ t('gifts.previous') }}</button><span>{{ sentPage.current_page }} / {{ sentPage.last_page }}</span><button :disabled="loading || sentPage.current_page >= sentPage.last_page" @click="loadSent(sentPage.current_page + 1)">{{ t('gifts.next') }}</button></div>
        </section>
        <section v-else role="tabpanel">
            <p v-if="loading" role="status">{{ t('gifts.loading') }}</p>
            <template v-else>
                <p class="gift-hold">{{ t('gifts.availableAfter24Hours') }}</p>
                <div v-if="!balances.length" class="gift-card">{{ t('gifts.withdrawalNotAvailable') }}</div>
                <div v-for="balance in balances" :key="balance.currency" class="gift-card gift-balance">
                    <h3>{{ balance.currency }}</h3>
                    <div><span>{{ t('gifts.pendingEarnings') }}</span><strong>{{ money(balance.pending_minor, balance.currency) }}</strong></div>
                    <div><span>{{ t('gifts.availableEarnings') }}</span><strong>{{ money(balance.withdrawable_minor, balance.currency) }}</strong></div>
                    <div><span>{{ t('gifts.totalReceived') }}</span><strong>{{ money(balance.total_received_minor, balance.currency) }}</strong></div>
                    <div><span>{{ t('gifts.totalWithdrawn') }}</span><strong>{{ money(balance.total_withdrawn_minor, balance.currency) }}</strong></div>
                    <p v-if="balance.recovery_minor > 0">{{ t('gifts.withdrawalNotAvailable') }}</p>
                </div>
                <form class="gift-card gift-withdraw-form" @submit.prevent="withdraw">
                    <h3>{{ t('gifts.requestWithdrawal') }}</h3>
                    <p v-if="!eligible">{{ t('gifts.withdrawalNotAvailable') }}</p>
                    <label>{{ t('gifts.amount') }} <input v-model="amount" required inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" /></label>
                    <label>{{ t('gifts.currency') }} <select v-model="currency" required><option v-for="balance in balances" :key="balance.currency" :value="balance.currency">{{ balance.currency }}</option></select></label>
                    <label>{{ t('gifts.paymentMethod') }} <input v-model.trim="method" required maxlength="100" /></label>
                    <label>{{ t('gifts.destination') }} <input v-model.trim="destination" required maxlength="200" /></label>
                    <button :disabled="busy || !eligible || !validAmount">{{ t('gifts.requestWithdrawal') }}</button>
                </form>
                <section class="gift-card gift-history">
                    <h3>{{ t('gifts.withdrawalHistory') }}</h3>
                    <p v-if="!withdrawals.length">{{ t('gifts.noWithdrawals') }}</p>
                    <article v-for="request in withdrawals" :key="request.reference">
                        <strong>{{ money(request.amount_minor, request.currency) }}</strong>
                        <span>{{ t(`gifts.${request.status}`) }} · {{ formatDate(request.created_at) }}</span>
                        <span>{{ request.payment_details?.method }}</span>
                        <small v-if="request.rejection_reason">{{ t('gifts.rejectionReason') }}: {{ request.rejection_reason }}</small>
                        <small v-if="request.failure_reason">{{ t('gifts.failureReason') }}: {{ request.failure_reason }}</small>
                        <button v-if="request.status === 'pending'" :disabled="busy" @click="cancel(request)">{{ t('gifts.cancel') }}</button>
                    </article>
                    <div v-if="withdrawalPage.last_page > 1" class="gift-pagination"><button :disabled="busy || withdrawalPage.current_page <= 1" @click="loadWithdrawals(withdrawalPage.current_page - 1)">{{ t('gifts.previous') }}</button><span>{{ withdrawalPage.current_page }} / {{ withdrawalPage.last_page }}</span><button :disabled="busy || withdrawalPage.current_page >= withdrawalPage.last_page" @click="loadWithdrawals(withdrawalPage.current_page + 1)">{{ t('gifts.next') }}</button></div>
                </section>
            </template>
        </section>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { giftKey, giftMoney, giftName, giftRequest } from '@/services/virtualGiftService';
import { toAssetUrl } from '@/services/runtimeUrls';

const { t, locale } = useI18n();
const tab = ref('sent'), sent = ref([]), sentPage = ref({ current_page: 1, last_page: 1 });
const balances = ref([]), withdrawals = ref([]), withdrawalPage = ref({ current_page: 1, last_page: 1 });
const loading = ref(false), busy = ref(false), error = ref(''), notice = ref('');
const amount = ref(''), currency = ref('USD'), method = ref(''), destination = ref('');
let withdrawalKey = giftKey();
const selectedBalance = computed(() => balances.value.find((item) => item.currency === currency.value));
const eligible = computed(() => Number(selectedBalance.value?.withdrawable_minor || 0) > 0);
const minorAmount = computed(() => { const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(amount.value); if (!match) return 0; return Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0')); });
const validAmount = computed(() => Number.isSafeInteger(minorAmount.value) && minorAmount.value > 0 && minorAmount.value <= Number(selectedBalance.value?.withdrawable_minor || 0));
const money = (minor, unit) => giftMoney(minor, unit, locale.value);
const formatDate = (value) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
const failureText = (failure) => Object.values(failure?.response?.data?.errors || {}).flat().join(' ') || failure?.response?.data?.message || t('gifts.loadError');
async function loadSent(page = 1) {
    loading.value = true; error.value = '';
    try { const result = await giftRequest('get', '/gifts/sent', null, { page }); sent.value = result.data; sentPage.value = result; }
    catch (failure) { error.value = failureText(failure); }
    finally { loading.value = false; }
}
async function loadWithdrawals(page = 1) {
    const result = await giftRequest('get', '/gifts/withdrawals', null, { page });
    withdrawals.value = result.data; withdrawalPage.value = result;
}
async function loadAccount() {
    loading.value = true; error.value = '';
    try {
        const account = await giftRequest('get', '/gifts/earnings');
        balances.value = account.balances;
        if (!balances.value.some((balance) => balance.currency === currency.value)) currency.value = balances.value[0]?.currency || 'USD';
        await loadWithdrawals();
    } catch (failure) { error.value = failureText(failure); }
    finally { loading.value = false; }
}
async function withdraw() {
    if (busy.value || !validAmount.value) return;
    busy.value = true; error.value = ''; notice.value = '';
    try {
        await giftRequest('post', '/gifts/withdrawals', { amount_minor: minorAmount.value, currency: currency.value, idempotency_key: withdrawalKey, payment_details: { method: method.value, destination: destination.value } });
        withdrawalKey = giftKey(); amount.value = ''; notice.value = t('gifts.withdrawSuccess');
        await loadAccount();
    } catch (failure) { error.value = failureText(failure); }
    finally { busy.value = false; }
}
async function cancel(request) {
    if (busy.value) return;
    busy.value = true; error.value = '';
    try { await giftRequest('post', `/gifts/withdrawals/${request.reference}/cancel`); await loadAccount(); }
    catch (failure) { error.value = failureText(failure); }
    finally { busy.value = false; }
}
onMounted(() => loadSent());
</script>

<style scoped>
.profile-gifts { color: #172036; }
.gift-tabs, .gift-pagination, .gift-links { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 18px; }
button { border: 1px solid #b8d9cc; border-radius: 10px; padding: 9px 14px; background: #ecfdf5; color: #047857; }
button[aria-selected=true], .gift-withdraw-form button { background: #047857; color: white; }
button:disabled { opacity: .5; cursor: not-allowed; }
.gift-card { background: white; border: 1px solid #d9e8e3; border-radius: 16px; padding: 16px; margin: 12px 0; display: flex; flex-wrap: wrap; gap: 14px; align-items: start; }
.gift-card > img { width: 64px; height: 64px; object-fit: contain; }
.gift-card-content { flex: 1 1 220px; }
.gift-card p { margin: 4px 0; }
.gift-balance { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.gift-balance h3 { grid-column: 1 / -1; margin: 0; }
.gift-balance div { display: flex; justify-content: space-between; gap: 12px; }
.gift-withdraw-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.gift-withdraw-form h3, .gift-withdraw-form p, .gift-withdraw-form button { grid-column: 1 / -1; }
label { display: grid; gap: 5px; }
input, select { width: 100%; border: 1px solid #cbded6; border-radius: 8px; padding: 9px; }
.gift-history { display: block; }
.gift-history article { display: flex; flex-wrap: wrap; gap: 10px; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
.gift-error { color: #b91c1c; }.gift-notice { color: #047857; }
@media(max-width: 600px) { .gift-balance, .gift-withdraw-form { grid-template-columns: 1fr; } }
</style>
