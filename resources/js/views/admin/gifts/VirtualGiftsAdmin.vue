<template>
    <AdminLayout>
        <main class="gifts-admin" :dir="['ar','fa','ur'].includes(locale) ? 'rtl' : 'ltr'">
            <h1>{{ t('gifts.adminTitle') }}</h1>
            <p v-if="error" role="alert">{{ error }}</p>
            <p v-if="busy" role="status">{{ t('gifts.loading') }}</p>
            <nav><button v-for="panel in panels" :key="panel" :aria-pressed="tab === panel" @click="tab = panel; load()">{{ t(`gifts.${panel}`) }}</button></nav>
            <template v-if="tab === 'catalog'">
                <form class="gift-panel" @submit.prevent="save">
                    <h2>{{ form.id ? t('gifts.edit') : t('gifts.create') }}</h2>
                    <div class="gift-form-fields">
                        <label>{{ t('gifts.name') }} <input v-model.trim="form.name" required maxlength="120" /></label>
                        <label>{{ t('gifts.price') }} (USD) <input v-model="form.price" required type="number" min="0.01" max="999999.99" step="0.01" inputmode="decimal" /></label>
                        <label class="gift-image-field">{{ t('gifts.image') }} <input ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp" :required="!form.id" @change="chooseImage" /></label>
                        <img v-if="imagePreview || form.image_url" class="gift-preview" :src="imagePreview || toAssetUrl(form.image_url)" alt="" />
                    </div>
                    <div class="gift-actions"><button class="gift-primary" :disabled="busy">{{ form.id ? (locale === 'en' ? 'Save Changes' : t('gifts.save')) : t('gifts.create') }}</button><button v-if="form.id" type="button" :disabled="busy" @click="reset">{{ t('gifts.cancel') }}</button></div>
                </form>
                <p v-if="notice" role="status">{{ notice }}</p>
                <div class="gift-admin-grid">
                    <article v-for="gift in catalog" :key="gift.id" class="gift-panel gift-card">
                        <img v-if="gift.image_url" :src="toAssetUrl(gift.image_url)" alt="" />
                        <div><strong>{{ gift.name }}</strong><p>{{ money(gift.price_minor, gift.currency) }}</p><span class="gift-status" :class="{ inactive: !gift.is_active }">{{ gift.is_active ? t('gifts.active') : t('gifts.inactive') }}</span></div>
                        <div class="gift-actions"><button :disabled="busy" @click="edit(gift)">{{ t('gifts.edit') }}</button><button :disabled="busy" @click="toggle(gift)">{{ gift.is_active ? t('gifts.deactivate') : (locale === 'en' ? 'Activate' : t('gifts.active')) }}</button></div>
                    </article>
                </div>
            </template>
            <template v-if="tab === 'transactions'">
                <form class="gift-filters" @submit.prevent="transactionPage = 1; loadTransactions()">
                    <label>{{ t('gifts.paymentStatus') }} <select v-model="status"><option value="">{{ t('gifts.all') }}</option><option v-for="item in statuses" :key="item" :value="item">{{ t(`gifts.${item}`) }}</option></select></label>
                    <label>{{ t('gifts.paymentMethod') }} <select v-model="method"><option value="">{{ t('gifts.all') }}</option><option value="wallet">{{ t('gifts.wallet') }}</option><option value="paypal">{{ t('gifts.paypal') }}</option></select></label>
                    <label>{{ t('gifts.recipient') }} <input v-model="recipientFilter" type="number" min="1" /></label>
                    <label><input v-model="reviewOnly" type="checkbox" /> {{ t('gifts.review') }}</label>
                    <button>{{ t('gifts.filter') }}</button>
                </form>
                <article v-for="gift in transactions.data || []" :key="gift.reference" class="gift-panel">
                    <strong>{{ gift.sender?.name }} → {{ gift.recipient?.name }} · {{ gift.gift_snapshot.name }} × {{ gift.quantity }}</strong>
                    <p>{{ t(`gifts.${gift.payment_status}`) }} · {{ money(gift.gross_amount_minor, gift.currency) }} · {{ t('gifts.commission') }}: {{ money(gift.platform_commission_minor - gift.reversed_commission_minor, gift.currency) }}</p>
                    <p>{{ t('gifts.processingFees') }}: {{ gift.processing_fee_status === 'not_applicable' ? t('gifts.notApplicable') : gift.payment_processing_fee_minor == null ? t('gifts.unknown') : money(gift.payment_processing_fee_minor, gift.processing_fee_currency, gift.processing_fee_precision) }}</p>
                    <p v-if="gift.review_required || gift.disputed">{{ t('gifts.review') }}</p>
                    <div class="gift-actions">
                        <button v-if="gift.payment_method === 'paypal'" :disabled="busy" @click="action(gift, 'reconcile')">{{ t('gifts.verify') }}</button>
                        <button v-if="gift.paid_at && gift.refunded_gross_minor < gift.gross_amount_minor" :disabled="busy" @click="refundTarget = gift">{{ t('gifts.refund') }}</button>
                        <button :disabled="busy" @click="loadAudit(gift)">{{ t('gifts.audit') }}</button>
                        <button v-if="gift.review_required" :disabled="busy" @click="reviewTarget = gift">{{ t('gifts.review') }}</button>
                    </div>
                </article>
                <div class="gift-pagination"><button :disabled="transactionPage <= 1" @click="transactionPage--; loadTransactions()">{{ t('gifts.previous') }}</button><span>{{ transactionPage }} / {{ transactions.last_page || 1 }}</span><button :disabled="transactionPage >= transactions.last_page" @click="transactionPage++; loadTransactions()">{{ t('gifts.next') }}</button></div>
                <section v-if="refundTarget" class="gift-panel"><p>{{ t('gifts.confirmRefund') }} {{ money(refundTarget.gross_amount_minor - refundTarget.refunded_gross_minor, refundTarget.currency) }}</p><button :disabled="busy" @click="action(refundTarget, 'refund'); refundTarget = null">{{ t('gifts.refund') }}</button> <button @click="refundTarget = null">{{ t('gifts.cancel') }}</button></section>
                <form v-if="reviewTarget" class="gift-panel" @submit.prevent="action(reviewTarget, reviewAction, { proof_reference: proof, amount_minor: recoveryAmount }); reviewTarget = null">
                    <label>{{ t('gifts.review') }} <select v-model="reviewAction"><option value="clear_review">{{ t('gifts.clearReview') }}</option><option value="reconcile_refund">PayPal · {{ t('gifts.verify') }} · {{ t('gifts.refund') }}</option><option value="recover">{{ t('gifts.recovery') }}</option></select></label>
                    <label>{{ t('gifts.proof') }} <input v-model="proof" required maxlength="160" /></label>
                    <label v-if="reviewAction === 'recover'">{{ t('gifts.amount') }} <input v-model="recoveryPrice" required inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" /></label>
                    <button :disabled="busy">{{ t('gifts.save') }}</button><button type="button" @click="reviewTarget = null">{{ t('gifts.cancel') }}</button>
                </form>
                <section v-if="audit" class="gift-panel"><h2>{{ t('gifts.audit') }}</h2><p v-for="event in audit.events" :key="event.id">{{ event.created_at }} · {{ eventLabel(event.type) }}</p><p>{{ t('gifts.ledgerMovements', { count: audit.movements.length }) }}</p></section>
            </template>
            <template v-if="tab === 'report' && report">
                <section v-for="row in report.financials" :key="row.currency" class="gift-panel">
                    <h2>{{ row.currency }}</h2><p v-for="[key, label] in financialLabels" :key="key">{{ t(`gifts.${label}`) }}: <strong>{{ row[key] == null ? t('gifts.provisional') : money(row[key], row.currency) }}</strong></p>
                </section>
                <section v-for="balance in report.balances" :key="balance.currency" class="gift-panel"><h2>{{ t('gifts.earnings') }} · {{ balance.currency }}</h2><p v-for="bucket in buckets" :key="bucket">{{ t(`gifts.${bucket}`) }}: {{ money(balance[`${bucket}_minor`], balance.currency) }}</p></section>
                <section class="gift-panel"><h2>{{ t('gifts.processingFees') }}</h2><p v-for="cost in report.processing_costs" :key="cost.currency">{{ money(cost.amount_minor, cost.currency, cost.fee_decimals) }}</p></section>
                <section class="gift-panel"><h2>{{ t('gifts.paymentStatus') }}</h2><p v-for="row in report.payment_counts" :key="row.payment_method + row.payment_status">{{ t(`gifts.${row.payment_status}`) }} · {{ row.payment_method === 'wallet' ? t('gifts.wallet') : t('gifts.paypal') }}: {{ row.count }}</p></section>
                <section class="gift-panel"><h2>{{ t('gifts.withdrawals') }}</h2><p v-for="row in report.payouts" :key="row.currency + row.status">{{ t(`gifts.${row.status}`) }} · {{ money(row.amount_minor, row.currency) }}</p></section>
                <section class="gift-panel"><h2>{{ t('gifts.popularGifts') }}</h2><p v-for="row in report.popular_gifts" :key="row.gift_id + row.currency">{{ catalog.find(gift => gift.id === row.gift_id)?.name || row.gift_id }} · {{ row.currency }}: {{ row.quantity }}</p></section>
                <section class="gift-panel"><h2>{{ t('gifts.supportedEvents') }}</h2><p v-for="row in report.supported_events" :key="row.event_id + row.currency">#{{ row.event_id }} · {{ money(row.gross_minor, row.currency) }}</p></section>
                <section class="gift-panel"><h2>{{ t('gifts.recipients') }}</h2><article v-for="row in report.recipients.data" :key="row.recipient_user_id + row.currency"><strong>#{{ row.recipient_user_id }} · {{ money(row.entitlement_minor, row.currency) }}</strong><p v-for="bucket in ['pending','available','reserved','settled','recovery']" :key="bucket">{{ t(`gifts.${bucket}`) }}: {{ money(row[`${bucket}_minor`], row.currency) }}</p></article><button :disabled="recipientPage <= 1" @click="recipientPage--; loadReport()">{{ t('gifts.previous') }}</button><button :disabled="recipientPage >= report.recipients.last_page" @click="recipientPage++; loadReport()">{{ t('gifts.next') }}</button></section>
            </template>
            <template v-if="tab === 'withdrawals'">
                <article v-for="withdrawal in withdrawals.data || []" :key="withdrawal.reference" class="gift-panel">
                    <p>#{{ withdrawal.user_id }} · {{ money(withdrawal.amount_minor, withdrawal.currency) }} · {{ t(`gifts.${withdrawal.status}`) }}</p>
                    <p>{{ withdrawal.payment_details?.method }} · {{ withdrawal.payment_details?.destination }}</p>
                    <button v-if="withdrawal.status === 'pending'" :disabled="busy" @click="payout(withdrawal, 'approved')">{{ t('gifts.approve') }}</button>
                    <button v-if="['pending','approved'].includes(withdrawal.status)" :disabled="busy" @click="payout(withdrawal, 'rejected')">{{ t('gifts.reject') }}</button>
                    <form v-if="withdrawal.status === 'approved'" @submit.prevent="payout(withdrawal, 'paid', payoutProof)"><label>{{ t('gifts.proof') }} <input v-model="payoutProof" required maxlength="160" /></label><button :disabled="busy">{{ t('gifts.recordPayout') }}</button></form>
                </article>
                <button :disabled="withdrawalPage <= 1" @click="withdrawalPage--; loadWithdrawals()">{{ t('gifts.previous') }}</button><button :disabled="withdrawalPage >= withdrawals.last_page" @click="withdrawalPage++; loadWithdrawals()">{{ t('gifts.next') }}</button>
            </template>
        </main>
    </AdminLayout>
</template>
<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { giftAdminRequest, giftMoney } from '@/services/virtualGiftService';
import { toAssetUrl } from '@/services/runtimeUrls';
const { t, locale } = useI18n();
const panels = ['catalog','transactions','report','withdrawals'];
const statuses = ['pending','paid','failed','cancelled','partially_refunded','refunded','reversed'];
const buckets = ['pending','available','reserved','settled','reversed','recovery'];
const financialLabels = [['gross_payments_minor','gross'],['gross_minor','total'],['commission_minor','commission'],['recipient_minor','recipientEarnings'],['processing_fee_minor','processingFees'],['platform_net_minor','platformNet'],['wallet_volume_minor','walletVolume'],['paypal_volume_minor','paypalVolume'],['refunded_minor','refunds']];
const blank = () => ({ name: '', price: '' });
const form = ref(blank()), image = ref(null), imagePreview = ref(''), imageInput = ref(null), tab = ref('catalog'), catalog = ref([]), transactions = ref({}), report = ref(null), withdrawals = ref({});
const error = ref(''), notice = ref(''), busy = ref(false), status = ref(''), method = ref(''), recipientFilter = ref(''), reviewOnly = ref(false), transactionPage = ref(1), recipientPage = ref(1), withdrawalPage = ref(1), audit = ref(null), refundTarget = ref(null), reviewTarget = ref(null), reviewAction = ref('clear_review'), proof = ref(''), recoveryPrice = ref(''), payoutProof = ref('');
const toMinor = (value) => { const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(String(value)); if (!match) throw new Error(); const amount = Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0')); if (!Number.isSafeInteger(amount) || amount < 1 || amount > 99999999) throw new Error(); return amount; };
const recoveryAmount = computed(() => { try { return toMinor(recoveryPrice.value); } catch { return 0; } });
const money = (minor, currency, precision = 2) => giftMoney(minor, currency, locale.value, precision ?? 2);
const eventLabel = (type) => t(`gifts.${({ created: 'create', delivered: 'sentSuccess', processing_fee: 'processingFees', released: 'available', refunded: 'refunded', reversed: 'reversed', reversal_already_covered: 'refunded', refund_requested: 'refund', withdrawal_reserved: 'reserved', withdrawal_approved: 'approved', withdrawal_paid: 'settled', withdrawal_rejected: 'rejected', withdrawal_cancelled: 'cancelled', dispute: 'review', review_cleared: 'clearReview', recovered: 'recovery', failed: 'failed', cancelled: 'cancelled' })[type] || 'audit'}`);
const failureMessage = (failure) => Object.values(failure?.response?.data?.errors || {}).flat().join(' ') || failure?.response?.data?.message || failure?.message || t('gifts.adminError');
const run = async (fn) => { if (busy.value) return; busy.value = true; error.value = ''; try { await fn(); } catch (failure) { error.value = failureMessage(failure); } finally { busy.value = false; } };
function clearPreview() { if (imagePreview.value) URL.revokeObjectURL(imagePreview.value); imagePreview.value = ''; }
function chooseImage(event) { clearPreview(); image.value = event.target.files[0] || null; if (image.value) imagePreview.value = URL.createObjectURL(image.value); }
function reset() { form.value = blank(); image.value = null; clearPreview(); if (imageInput.value) imageInput.value.value = ''; }
function edit(gift) { reset(); form.value = { id: gift.id, name: gift.name, price: (gift.price_minor / 100).toFixed(2), image_url: gift.image_url }; window.scrollTo({ top: 0, behavior: 'auto' }); }
async function save() { await run(async () => { notice.value = ''; const data = new FormData(); data.append('name', form.value.name); data.append('price', form.value.price); if (image.value) data.append('image', image.value); const editing = Boolean(form.value.id); const saved = await giftAdminRequest('post', editing ? `/catalog/${form.value.id}` : '/catalog', data); catalog.value = editing ? catalog.value.map((gift) => gift.id === saved.id ? saved : gift) : [...catalog.value, saved]; reset(); notice.value = editing ? (locale.value === 'en' ? 'Gift updated.' : t('gifts.save')) : (locale.value === 'en' ? 'Gift created.' : t('gifts.create')); catalog.value = await giftAdminRequest('get', '/catalog'); }); }
async function toggle(gift) { await run(async () => { await giftAdminRequest(gift.is_active ? 'delete' : 'post', `/catalog/${gift.id}${gift.is_active ? '' : '/activate'}`); catalog.value = await giftAdminRequest('get', '/catalog'); }); }
async function loadTransactions() { await run(async () => { transactions.value = await giftAdminRequest('get', '/transactions', null, { page: transactionPage.value, ...(status.value ? {status: status.value} : {}), ...(method.value ? {payment_method: method.value} : {}), ...(recipientFilter.value ? {recipient_user_id: recipientFilter.value} : {}), ...(reviewOnly.value ? {review_required: 1} : {}) }); }); }
async function loadReport() { await run(async () => { report.value = await giftAdminRequest('get', '/report', null, {page: recipientPage.value}); }); }
async function loadWithdrawals() { await run(async () => { withdrawals.value = await giftAdminRequest('get', '/withdrawals', null, {page: withdrawalPage.value}); }); }
async function action(gift, operation, extra = {}) { await run(async () => { await giftAdminRequest('post', `/transactions/${gift.reference}/actions`, {action: operation, ...extra}); transactions.value = await giftAdminRequest('get', '/transactions', null, {page: transactionPage.value}); }); }
async function loadAudit(gift) { await run(async () => { audit.value = await giftAdminRequest('get', `/transactions/${gift.reference}/audit`); }); }
async function payout(withdrawal, newStatus, reference) { await run(async () => { await giftAdminRequest('post', `/withdrawals/${withdrawal.reference}`, {status: newStatus, ...(reference ? {payout_reference: reference} : {})}); withdrawals.value = await giftAdminRequest('get', '/withdrawals', null, {page: withdrawalPage.value}); }); }
async function load() { if (tab.value === 'catalog') await run(async () => { catalog.value = await giftAdminRequest('get', '/catalog'); }); else if (tab.value === 'transactions') await loadTransactions(); else if (tab.value === 'report') await loadReport(); else await loadWithdrawals(); }
onMounted(load);
onUnmounted(clearPreview);
</script>
<style scoped>
.gifts-admin { color: #172036; padding: 24px; max-width: 1200px; margin: auto; } h1 { font-size: 1.8rem; } h2 { font-size: 1.2rem; } nav, .gift-actions, .gift-filters, .gift-pagination { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-block: 16px; } .gift-panel { padding: 20px; border: 1px solid #d9e8e3; border-radius: 16px; background: white; margin-block: 16px; } form.gift-panel { display: grid; gap: 12px; max-width: 650px; } label { display: grid; gap: 4px; } input:not([type=checkbox]), select, textarea { padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; max-width: 100%; } button { padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 10px; background: #ecfdf5; color: #047857; } button:disabled { opacity: .5; } button[aria-pressed=true] { background: #047857; color: white; } .gift-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; } .gift-row img { width: 56px; height: 56px; object-fit: contain; } [role=alert] { color: #b91c1c; } @media(max-width: 600px) { .gifts-admin { padding: 12px; } }
.gifts-admin form.gift-panel { max-width: none; gap: 16px; }
.gift-form-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; align-items: end; }
.gift-image-field { grid-column: 1 / 2; }
.gift-preview { width: 88px; height: 88px; object-fit: contain; border: 1px solid #d9e8e3; border-radius: 12px; }
.gift-primary { background: #047857; color: white; border-color: #047857; }
.gift-admin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 16px; }
.gift-card { display: grid; justify-items: start; gap: 12px; margin: 0; }
.gift-card > img { width: 100%; height: 112px; object-fit: contain; }
.gift-card p { margin: 4px 0; }
.gift-status { display: inline-block; padding: 3px 10px; border-radius: 99px; color: #047857; background: #ecfdf5; }
.gift-status.inactive { color: #64748b; background: #f1f5f9; }
@media(max-width: 600px) { .gift-form-fields { grid-template-columns: 1fr; } }
</style>
