<template>
    <Teleport to="body">
        <dialog ref="dialog" class="gift-modal" :dir="rtl ? 'rtl' : 'ltr'" aria-labelledby="gift-title" @cancel.prevent="close">
            <header>
                <h2 id="gift-title">{{ t('gifts.choose') }}</h2>
                <button type="button" :disabled="busy" :aria-label="t('gifts.close')" @click="close">×</button>
            </header>
            <p>{{ t('gifts.recipient') }}: <strong>{{ recipient.name }}</strong></p>
            <p v-if="loading" role="status">{{ t('gifts.loading') }}</p>
            <p v-if="error" role="alert">{{ error }}</p>
            <div v-if="transaction?.delivery_status === 'delivered'" class="gift-success" role="status">
                <img v-if="transaction.gift_snapshot.image_url" :src="asset(transaction.gift_snapshot.image_url)" alt="" />
                <h3>{{ t('gifts.sentSuccess') }}</h3>
                <p>{{ transaction.sender?.name }} → {{ transaction.recipient?.name }} · {{ transaction.quantity }} × {{ name(transaction.gift_snapshot) }}</p>
                <button type="button" @click="close">{{ t('gifts.close') }}</button>
            </div>
            <template v-else-if="!loading">
                <p v-if="!catalog.length">{{ t('gifts.empty') }}</p>
                <div class="gift-catalog" role="group" :aria-label="t('gifts.choose')">
                    <button v-for="gift in catalog" :key="gift.id" type="button" :aria-pressed="selected?.id === gift.id" :disabled="busy || Boolean(transaction)" @click="select(gift)">
                        <img v-if="gift.image_url" :src="asset(gift.image_url)" alt="" />
                        <strong>{{ name(gift) }}</strong>
                        <span>{{ money(gift.price_minor, gift.currency) }}</span>
                    </button>
                </div>
                <template v-if="selected">
                    <p>{{ selected.translations?.[locale]?.description || selected.description }}</p>
                    <label>{{ t('gifts.quantity') }} <input v-model.number="quantity" type="number" min="1" max="100" step="1" :disabled="busy || Boolean(transaction)" @change="newAttempt" /></label>
                    <p><strong>{{ t('gifts.total') }}: {{ money(total, selected.currency) }}</strong></p>
                    <p>{{ t('gifts.split') }}</p>
                    <p>{{ t('gifts.walletBalance') }}: {{ wallet && wallet.is_consistent !== false ? money(wallet.balance_minor, wallet.currency) : t('gifts.unavailable') }}</p>
                    <fieldset :disabled="busy || Boolean(transaction)">
                        <legend>{{ t('gifts.paymentMethod') }}</legend>
                        <label><input v-model="method" type="radio" value="wallet" @change="newAttempt" /> {{ t('gifts.wallet') }}</label>
                        <label><input v-model="method" type="radio" value="paypal" :disabled="selected.payment_methods && !selected.payment_methods.includes('paypal')" @change="newAttempt" /> {{ t('gifts.paypal') }}</label>
                    </fieldset>
                    <p v-if="method === 'wallet' && !walletEligible">{{ t('gifts.insufficient') }}</p>
                    <p v-if="transaction" role="status">{{ t(`gifts.${transaction.payment_status}`) }}</p>
                    <button v-if="!transaction" class="gift-confirm" type="button" :disabled="busy || !valid || (method === 'wallet' && !walletEligible)" @click="submit">{{ busy ? t('gifts.loading') : t('gifts.confirm') }}</button>
                    <template v-else>
                        <button type="button" :disabled="busy" @click="verify">{{ t('gifts.verify') }}</button>
                        <RouterLink :to="`/${locale}/gifts`">{{ t('gifts.history') }}</RouterLink>
                    </template>
                </template>
            </template>
        </dialog>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { giftKey, giftMoney, giftName, giftRequest } from '@/services/virtualGiftService';
import { toAssetUrl } from '@/services/runtimeUrls';

const props = defineProps({ eventId: { type: Number, required: true }, commentId: { type: Number, default: null }, recipient: { type: Object, required: true } });
const emit = defineEmits(['close', 'delivered']);
const { t, locale } = useI18n();
const dialog = ref(null);
const catalog = ref([]), selected = ref(null), wallet = ref(null), quantity = ref(1), method = ref('wallet');
const loading = ref(true), busy = ref(false), error = ref(''), transaction = ref(null);
const rtl = computed(() => ['ar', 'fa', 'ur'].includes(locale.value));
let requestKey = giftKey();
const intentStorageKey = `scemory_gift_intent:${props.eventId}:${props.commentId || 0}`;
const name = (gift) => giftName(gift, locale.value, t);
const money = (minor, currency) => giftMoney(minor, currency, locale.value);
const asset = toAssetUrl;
const total = computed(() => (selected.value?.price_minor || 0) * quantity.value);
const valid = computed(() => Number.isInteger(quantity.value) && quantity.value >= 1 && quantity.value <= 100 && total.value <= 99999999);
const walletEligible = computed(() => wallet.value?.is_consistent !== false && wallet.value?.currency === selected.value?.currency && Number(wallet.value.balance_minor) >= total.value);
function newAttempt() { if (!busy.value && !transaction.value) { requestKey = giftKey(); sessionStorage.removeItem(intentStorageKey); } }
function select(gift) { selected.value = gift; newAttempt(); }
function close() { if (!busy.value) emit('close'); }
function delivered(value) {
    transaction.value = value;
    if (value.delivery_status === 'delivered' && value.payment_status === 'paid') { sessionStorage.removeItem(intentStorageKey); emit('delivered', value); }
}
async function submit() {
    busy.value = true; error.value = '';
    try {
        const payload = { gift_id: selected.value.id, event_id: props.eventId, comment_id: props.commentId, quantity: quantity.value, idempotency_key: requestKey, expected_unit_price_minor: selected.value.price_minor, expected_currency: selected.value.currency };
        sessionStorage.setItem(intentStorageKey, JSON.stringify({ payload, method: method.value, selection: selected.value }));
        if (method.value === 'wallet') delivered(await giftRequest('post', '/gifts/send/wallet', payload));
        else {
            const result = await giftRequest('post', '/gifts/paypal/create-order', payload);
            transaction.value = result.transaction;
            sessionStorage.setItem(intentStorageKey, JSON.stringify({ payload, method: method.value, selection: selected.value, reference: result.transaction.reference }));
            if (result.approval_url) {
                sessionStorage.setItem('scemory_pending_gift', result.transaction.reference);
                window.location.assign(result.approval_url);
            } else delivered(result.transaction);
        }
    } catch (failure) {
        const message = failure.response?.data?.message;
        error.value = message === 'Insufficient wallet balance.' ? t('gifts.insufficient') : message === 'Gift price changed. Choose the gift again before paying.' ? t('gifts.priceChanged') : t('gifts.paymentFailed');
    } finally { busy.value = false; }
}
async function verify() {
    busy.value = true; error.value = '';
    try { delivered(await giftRequest('post', '/gifts/paypal/capture', { reference: transaction.value.reference })); }
    catch { error.value = t('gifts.paymentFailed'); }
    finally { busy.value = false; }
}
onMounted(async () => {
    await nextTick();
    dialog.value?.showModal();
    try {
        const [gifts, account] = await Promise.all([giftRequest('get', '/virtual-gifts'), giftRequest('get', '/gifts/earnings')]);
        catalog.value = gifts; selected.value = gifts[0] || null; wallet.value = account.wallet;
        try {
            const intent = JSON.parse(sessionStorage.getItem(intentStorageKey) || 'null');
            if (intent?.payload?.idempotency_key && intent.payload.event_id === props.eventId && Number(intent.payload.comment_id || 0) === Number(props.commentId || 0)) {
                requestKey = intent.payload.idempotency_key; quantity.value = intent.payload.quantity; method.value = intent.method;
                selected.value = intent.selection || gifts.find((gift) => gift.id === intent.payload.gift_id);
            }
        } catch { sessionStorage.removeItem(intentStorageKey); }
    } catch { error.value = t('gifts.loadError'); }
    finally { loading.value = false; }
});
onUnmounted(() => dialog.value?.close());
</script>

<style scoped>
.gift-modal { border: 1px solid #d9e8e3; border-radius: 24px; padding: 24px; width: min(620px, 94vw); max-height: 90vh; color: #172036; background: white; }
.gift-modal::backdrop { background: #17203699; backdrop-filter: blur(3px); }
header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
header h2 { font-size: 1.35rem; } button { border: 1px solid #c9ddd7; padding: 9px 14px; border-radius: 12px; background: #f6faf8; } button:disabled { opacity: .5; cursor: wait; }
.gift-catalog { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-block: 16px; }
.gift-catalog button { display: grid; gap: 5px; text-align: center; justify-items: center; }
.gift-catalog button[aria-pressed=true] { border: 2px solid #059669; background: #ecfdf5; }
.gift-catalog img, .gift-success img { width: 64px; height: 64px; object-fit: contain; }
fieldset { display: flex; flex-wrap: wrap; gap: 16px; margin-block: 12px; } input[type=number] { width: 90px; border: 1px solid #c9ddd7; padding: 8px; border-radius: 8px; }
.gift-confirm { background: #047857; color: white; width: 100%; } [role=alert] { color: #b91c1c; } .gift-success { text-align: center; padding: 24px; } .gift-success img { margin: auto; animation: gift-arrive .6s ease-out; }
@keyframes gift-arrive { from { transform: translateY(20px) scale(.5); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
@media(prefers-reduced-motion: reduce) { .gift-success img { animation: none; } }
@media(max-width: 450px) { .gift-catalog { grid-template-columns: repeat(2, minmax(0, 1fr)); } .gift-modal { padding: 16px; } }
</style>
