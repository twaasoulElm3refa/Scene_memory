<template>
    <AdminLayout>
        <main class="gift-withdrawals-admin" :dir="['ar','fa','ur'].includes(locale) ? 'rtl' : 'ltr'">
            <h1>{{ t('gifts.giftWithdrawalRequests') }}</h1>
            <p v-if="error" role="alert">{{ error }}</p>
            <p v-if="notice" role="status">{{ notice }}</p>
            <label>{{ t('gifts.paymentStatus') }}
                <select v-model="filter" @change="load(1)">
                    <option value="">{{ t('gifts.all') }}</option>
                    <option v-for="state in states" :key="state" :value="state">{{ t(`gifts.${state}`) }}</option>
                </select>
            </label>
            <p v-if="loading" role="status">{{ t('gifts.loading') }}</p>
            <p v-else-if="!requests.length && !error">{{ t('gifts.noWithdrawals') }}</p>
            <div class="request-grid">
                <article v-for="request in requests" :key="request.reference" class="request-card">
                    <strong>#{{ request.id }} · {{ request.user?.name || `#${request.user_id}` }}</strong>
                    <p>ID: {{ request.user_id }} · {{ money(request.amount_minor, request.currency) }}</p>
                    <p>{{ t('gifts.paymentMethod') }}: {{ request.payment_method || '—' }} · {{ t('gifts.destination') }}: {{ request.destination_masked }}</p>
                    <p>{{ t('gifts.balanceAtRequest') }}: {{ request.available_balance_at_request_minor == null ? '—' : money(request.available_balance_at_request_minor, request.currency) }}</p>
                    <p>{{ t('gifts.requestDate') }}: {{ formatDate(request.created_at) }} · {{ t(`gifts.${request.status}`) }}</p>
                    <p v-if="request.approved_at">{{ t('gifts.approvalDate') }}: {{ formatDate(request.approved_at) }}</p>
                    <p v-if="request.paid_at">{{ t('gifts.completionDate') }}: {{ formatDate(request.paid_at) }}</p>
                    <button :disabled="busy" @click="show(request)">{{ t('gifts.audit') }}</button>
                </article>
            </div>
            <div v-if="page.last_page > 1" class="pagination"><button :disabled="loading || page.current_page <= 1" @click="load(page.current_page - 1)">{{ t('gifts.previous') }}</button><span>{{ page.current_page }} / {{ page.last_page }}</span><button :disabled="loading || page.current_page >= page.last_page" @click="load(page.current_page + 1)">{{ t('gifts.next') }}</button></div>
            <section v-if="detail" class="request-card detail">
                <h2>#{{ detail.withdrawal.id }} · {{ detail.withdrawal.user?.name }}</h2>
                <p>{{ t('gifts.paymentMethod') }}: {{ detail.withdrawal.payment_details?.method }}</p>
                <p>{{ t('gifts.destination') }}: {{ detail.withdrawal.payment_details?.destination }}</p>
                <p>{{ t('gifts.eligibleBalance') }}: {{ money(detail.balance?.available_minor || 0, detail.withdrawal.currency) }} · {{ t('gifts.reserved') }}: {{ money(detail.balance?.reserved_minor || 0, detail.withdrawal.currency) }}</p>
                <p v-if="detail.withdrawal.rejection_reason">{{ t('gifts.rejectionReason') }}: {{ detail.withdrawal.rejection_reason }}</p>
                <p v-if="detail.withdrawal.failure_reason">{{ t('gifts.failureReason') }}: {{ detail.withdrawal.failure_reason }}</p>
                <p v-if="detail.withdrawal.payout_reference">{{ t('gifts.payoutReference') }}: {{ detail.withdrawal.payout_reference }}</p>
                <div v-for="entry in detail.allocations" :key="entry.earning_entry_id" class="allocation">{{ entry.gift_reference }} · {{ money(entry.amount_minor, detail.withdrawal.currency) }} · {{ t(`gifts.${entry.payment_status}`) }}<span v-if="entry.disputed || entry.review_required"> · {{ t('gifts.review') }}</span></div>
                <div class="actions">
                    <button v-if="detail.withdrawal.status === 'pending'" :disabled="busy" @click="act('approved')">{{ t('gifts.approve') }}</button>
                    <button v-if="detail.withdrawal.status === 'approved'" :disabled="busy" @click="act('processing')">{{ t('gifts.markProcessing') }}</button>
                    <button v-if="['pending','approved','failed'].includes(detail.withdrawal.status)" :disabled="busy" @click="action = 'rejected'">{{ t('gifts.reject') }}</button>
                    <button v-if="detail.withdrawal.status === 'processing'" :disabled="busy" @click="action = 'failed'">{{ t('gifts.markFailed') }}</button>
                    <button v-if="['approved','processing','failed'].includes(detail.withdrawal.status)" :disabled="busy" @click="action = 'paid'">{{ t('gifts.recordPayout') }}</button>
                </div>
                <form v-if="['rejected','failed','paid'].includes(action)" @submit.prevent="act(action)">
                    <label v-if="action !== 'paid'">{{ action === 'rejected' ? t('gifts.rejectionReason') : t('gifts.failureReason') }} <textarea v-model.trim="reason" required minlength="3" maxlength="2000" /></label>
                    <label v-else>{{ t('gifts.payoutReference') }} <input v-model.trim="reference" required maxlength="160" /></label>
                    <label v-if="action === 'paid'" class="confirm"><input v-model="confirmed" type="checkbox" required /> {{ t('gifts.recordPayout') }}</label>
                    <button :disabled="busy || (action === 'paid' && !confirmed)">{{ t('gifts.save') }}</button>
                    <button type="button" @click="action = ''">{{ t('gifts.cancel') }}</button>
                </form>
            </section>
        </main>
    </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { giftAdminRequest, giftMoney } from '@/services/virtualGiftService';

const { t, locale } = useI18n();
const states = ['pending','approved','processing','failed','rejected','cancelled','paid'];
const filter = ref(''), requests = ref([]), page = ref({ current_page: 1, last_page: 1 }), detail = ref(null);
const loading = ref(false), busy = ref(false), error = ref(''), notice = ref(''), action = ref(''), reason = ref(''), reference = ref(''), confirmed = ref(false);
const money = (amount, currency) => giftMoney(amount, currency, locale.value);
const formatDate = (value) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
const failureText = (failure) => Object.values(failure?.response?.data?.errors || {}).flat().join(' ') || failure?.response?.data?.message || t('gifts.adminError');
async function load(number = 1) {
    loading.value = true; error.value = '';
    try { page.value = await giftAdminRequest('get', '/withdrawals', null, { page: number, ...(filter.value ? { status: filter.value } : {}) }); requests.value = page.value.data; }
    catch (failure) { error.value = failureText(failure); }
    finally { loading.value = false; }
}
async function show(request) {
    error.value = ''; action.value = '';
    try { detail.value = await giftAdminRequest('get', `/withdrawals/${request.reference}`); }
    catch (failure) { error.value = failureText(failure); }
}
async function act(status) {
    if (busy.value || !detail.value || (status === 'paid' && !confirmed.value)) return;
    busy.value = true; error.value = ''; notice.value = '';
    try {
        const target = detail.value.withdrawal.reference;
        await giftAdminRequest('post', `/withdrawals/${target}`, { status, ...(status === 'paid' ? { payout_reference: reference.value } : {}), ...(['rejected','failed'].includes(status) ? { reason: reason.value } : {}) });
        detail.value = await giftAdminRequest('get', `/withdrawals/${target}`);
        await load(page.value.current_page);
        notice.value = t(`gifts.${status}`);
        action.value = ''; reason.value = ''; reference.value = ''; confirmed.value = false;
    } catch (failure) { error.value = failureText(failure); }
    finally { busy.value = false; }
}
onMounted(load);
</script>

<style scoped>
.gift-withdrawals-admin { max-width: 1200px; margin: auto; padding: 18px; color: #172036; }
h1 { font-size: 1.8rem; margin-bottom: 18px; }
.request-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
.request-card { background: white; border: 1px solid #d9e8e3; border-radius: 16px; padding: 18px; }
.request-card p { margin: 6px 0; overflow-wrap: anywhere; }
.detail { margin-top: 20px; }.allocation { padding: 8px 0; border-bottom: 1px solid #e2e8f0; overflow-wrap: anywhere; }
.actions, .pagination { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; align-items: center; }
button { border: 1px solid #b8d9cc; border-radius: 10px; padding: 9px 14px; background: #ecfdf5; color: #047857; } button:disabled { opacity: .5; }
label, form { display: grid; gap: 8px; margin: 10px 0; } .confirm { display: flex; align-items: center; }
input:not([type=checkbox]), select, textarea { border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px; max-width: 100%; }
[role=alert] { color: #b91c1c; } [role=status] { color: #047857; }
</style>
