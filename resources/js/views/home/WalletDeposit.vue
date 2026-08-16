<template>
    <div class="scemory-page deposit-page">
        <!-- Current Balance -->
        <div class="balance-card">
            <p class="label">{{ $t('walletDeposit.currentBalance') }}</p>
            <p class="amount">${{ wallet?.amount ?? '0.00' }}</p>
            <p class="currency">USD</p>
        </div>

        <!-- Deposit Form -->
        <div class="deposit-card">
            <h2>{{ $t('walletDeposit.title') }}</h2>

            <!-- Preset Amounts -->
            <div class="presets">
                <button v-for="val in presets" :key="val" :class="['preset-btn', { active: selectedPreset === val }]"
                    @click="selectPreset(val)">
                    ${{ val }}
                </button>
            </div>

            <!-- Custom Amount -->
            <div class="input-group">
                <label>{{ $t('walletDeposit.customAmount') }}</label>
                <div class="amount-wrap">
                    <span class="currency-badge">$</span>
                    <input v-model.number="form.amount" type="number" min="1" step="0.01" placeholder="0.00"
                        @input="selectedPreset = null" />
                </div>
                <p v-if="amountError" class="error">{{ amountError }}</p>
            </div>

            <!-- Description -->
            <div class="input-group">
                <label>{{ $t('walletDeposit.descriptionOptional') }}</label>
                <input v-model="form.description" type="text" maxlength="255" :placeholder="$t('walletDeposit.descriptionPlaceholder')" />
            </div>

            <hr />

            <!-- Summary -->
            <div class="summary-row">
                <span>{{ $t('walletDeposit.amount') }}</span>
                <span>{{ form.amount ? '$' + Number(form.amount).toFixed(2) : '—' }}</span>
            </div>
            <div class="summary-row">
                <span>{{ $t('walletDeposit.currency') }}</span>
                <span>USD</span>
            </div>
            <div class="summary-row">
                <span>{{ $t('walletDeposit.paymentMethod') }}</span>
                <span class="paypal-text">PayPal</span>
            </div>

            <!-- Submit -->
            <button class="pay-btn" :disabled="!isValid || loading" @click="handlePay">
                <span v-if="loading">{{ $t('walletDeposit.redirecting') }}</span>
                <span v-else>{{ $t('walletDeposit.payWithPayPal') }}</span>
            </button>

            <p v-if="serverError" class="error" style="margin-top:12px;text-align:center">
                {{ serverError }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PaymentService } from '../../services/PaymentService/PaymentService'
import { getOrCreateIdempotencyKey } from '../../services/PaymentService/checkoutSession'

// Props — تمرر البيانات من الصفحة الأب
const { t } = useI18n()

const props = defineProps({
    wallet: { type: Object, default: null },
})

const presets = [10, 25, 50, 100]
const selectedPreset = ref(null)
const loading = ref(false)
const serverError = ref('')

const form = ref({
    amount: '',
    description: '',
})

const idempotencyKey = computed(() =>
    getOrCreateIdempotencyKey("wallet_deposit:paypal", String(form.value.amount))
)

function selectPreset(val) {
    selectedPreset.value = val
    form.value.amount = val
}

const amountError = computed(() => {
    if (!form.value.amount) return ''
    if (form.value.amount < 1) return t('walletDeposit.errors.invalidAmount')
    return ''
})

const isValid = computed(() => {
    return form.value.amount >= 1 && !amountError.value
})

async function handlePay() {
    if (!isValid.value) return
    loading.value = true
    serverError.value = ''

    try {
        const { data } = await PaymentService.depositPay({
            amount: Number(form.value.amount).toFixed(2),
            description: form.value.description || 'Wallet Deposit',
            idempotency_key: idempotencyKey.value,   // ← هنا في الـ body
        })


        if (data.approval_url) {
            window.location.href = data.approval_url
        }

    } catch (err) {
        serverError.value = err.response?.data?.message ?? t('common.unknownError')
    } finally {
        loading.value = false
    }
}
</script>

<style scoped>
.deposit-page {
    max-width: 520px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.balance-card {
    background: #f5f5f5;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    margin-bottom: 1.5rem;
}

.balance-card .label {
    font-size: 13px;
    color: #888;
    margin-bottom: 4px;
}

.balance-card .amount {
    font-size: 32px;
    font-weight: 500;
}

.balance-card .currency {
    font-size: 13px;
    color: #aaa;
}

.deposit-card {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 12px;
    padding: 1.5rem;
}

.deposit-card h2 {
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 1.25rem;
}

.presets {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-bottom: 1.25rem;
}

.preset-btn {
    padding: 10px 0;
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all .15s;
}

.preset-btn:hover {
    background: #f5f5f5;
}

.preset-btn.active {
    border: 2px solid #378ADD;
    color: #185FA5;
    background: #E6F1FB;
}

.input-group {
    margin-bottom: 1rem;
}

.input-group label {
    display: block;
    font-size: 13px;
    color: #888;
    margin-bottom: 6px;
}

.amount-wrap {
    position: relative;
}

.currency-badge {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 14px;
    color: #888;
}

.amount-wrap input {
    width: 100%;
    padding: 10px 12px 10px 44px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 18px;
    font-weight: 500;
    outline: none;
}

.amount-wrap input:focus {
    border-color: #378ADD;
}

.input-group input[type="text"] {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
}

hr {
    border: none;
    border-top: 1px solid #f0f0f0;
    margin: 1.25rem 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    margin-bottom: 8px;
}

.summary-row span:first-child {
    color: #888;
}

.paypal-text {
    color: #003087;
    font-weight: 500;
}

.pay-btn {
    width: 100%;
    padding: 12px;
    margin-top: 1.25rem;
    background: #185FA5;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: opacity .15s;
}

.pay-btn:hover {
    opacity: .9;
}

.pay-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.error {
    font-size: 13px;
    color: #e24b4a;
    margin-top: 6px;
}

.deposit-page {
    max-width: 620px;
    padding: 3rem 1rem;
}

.balance-card {
    border: 1px solid #DCE8F5;
    border-radius: 28px;
    background:
        radial-gradient(circle at top right, rgba(48, 168, 255, 0.18), transparent 16rem),
        linear-gradient(135deg, #FFFFFF, #F4F8FC);
    box-shadow: 0 18px 55px rgba(13, 77, 151, 0.12);
}

.balance-card .label,
.balance-card .currency,
.input-group label,
.summary-row span:first-child {
    color: #64748B;
}

.balance-card .amount,
.deposit-card h2 {
    color: #06142A;
    font-weight: 800;
}

.deposit-card {
    border: 1px solid #E5EDF6;
    border-radius: 28px;
    padding: 2rem;
    box-shadow: 0 10px 35px rgba(13, 77, 151, 0.06);
}

.preset-btn {
    border-color: #DCE8F5;
    border-radius: 14px;
    color: #0F172A;
}

.preset-btn:hover {
    border-color: #CFE2F6;
    background: #F4F8FC;
}

.preset-btn.active {
    border: 1px solid #1677FF;
    color: #0D4D97;
    background: #EAF4FF;
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.08);
}

.amount-wrap input,
.input-group input[type="text"] {
    min-height: 46px;
    border-color: #DCE8F5;
    border-radius: 14px;
}

.amount-wrap input:focus,
.input-group input[type="text"]:focus {
    border-color: #1677FF;
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.10);
}

hr {
    border-top-color: #DCE8F5;
}

.pay-btn {
    border-radius: 999px;
    background: linear-gradient(135deg, #0D4D97, #1677FF);
    font-weight: 800;
    box-shadow: 0 14px 30px rgba(22, 119, 255, 0.20);
}
</style>
