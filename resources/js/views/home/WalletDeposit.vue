<template>
    <div class="deposit-page">
        <!-- Current Balance -->
        <div class="balance-card">
            <p class="label">رصيدك الحالي</p>
            <p class="amount">${{ wallet?.amount ?? '0.00' }}</p>
            <p class="currency">USD</p>
        </div>

        <!-- Deposit Form -->
        <div class="deposit-card">
            <h2>شحن المحفظة</h2>

            <!-- Preset Amounts -->
            <div class="presets">
                <button v-for="val in presets" :key="val" :class="['preset-btn', { active: selectedPreset === val }]"
                    @click="selectPreset(val)">
                    ${{ val }}
                </button>
            </div>

            <!-- Custom Amount -->
            <div class="input-group">
                <label>المبلغ المخصص</label>
                <div class="amount-wrap">
                    <span class="currency-badge">$</span>
                    <input v-model.number="form.amount" type="number" min="1" step="0.01" placeholder="0.00"
                        @input="selectedPreset = null" />
                </div>
                <p v-if="amountError" class="error">{{ amountError }}</p>
            </div>

            <!-- Description -->
            <div class="input-group">
                <label>وصف (اختياري)</label>
                <input v-model="form.description" type="text" maxlength="255" placeholder="مثلاً: شحن رصيد لشراء صور" />
            </div>

            <hr />

            <!-- Summary -->
            <div class="summary-row">
                <span>المبلغ</span>
                <span>{{ form.amount ? '$' + Number(form.amount).toFixed(2) : '—' }}</span>
            </div>
            <div class="summary-row">
                <span>العملة</span>
                <span>USD</span>
            </div>
            <div class="summary-row">
                <span>وسيلة الدفع</span>
                <span class="paypal-text">PayPal</span>
            </div>

            <!-- Submit -->
            <button class="pay-btn" :disabled="!isValid || loading" @click="handlePay">
                <span v-if="loading">جاري التحويل...</span>
                <span v-else>ادفع عبر PayPal</span>
            </button>

            <p v-if="serverError" class="error" style="margin-top:12px;text-align:center">
                {{ serverError }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import { v4 as uuidv4 } from 'uuid'

const idempotencyKey = ref(uuidv4())
// Props — تمرر البيانات من الصفحة الأب
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

function selectPreset(val) {
    selectedPreset.value = val
    form.value.amount = val
}

const amountError = computed(() => {
    if (!form.value.amount) return ''
    if (form.value.amount < 1) return 'أدخل مبلغ صحيح (أكثر من $1)'
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
        const { data } = await axios.post('/v1/deposit/pay', {
            amount: Number(form.value.amount).toFixed(2),
            description: form.value.description || 'Wallet Deposit',
            idempotency_key: idempotencyKey.value,   // ← هنا في الـ body
        })


        if (data.approval_url) {
            window.location.href = data.approval_url
        }

    } catch (err) {
        serverError.value = err.response?.data?.message ?? 'حدث خطأ، حاول مرة أخرى.'
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
</style>
