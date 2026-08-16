<template>
  <div class="scemory-page failed-page">
    <div class="particles"><div v-for="i in 8" :key="i" class="particle" :style="{ left: (i * 12) + '%', animationDelay: (i * 0.3) + 's' }"></div></div>
    <div class="card">
      <div class="paypal-badge"><div class="paypal-dot"></div>{{ $t('payment.failed.paypalBadge') }}</div>
      <div class="icon-wrap"><div class="icon-ring"></div><div class="icon-circle"><svg width="38" height="38" viewBox="0 0 38 38" fill="none"><line class="x-line" x1="12" y1="12" x2="26" y2="26" stroke="#fff" stroke-width="3.5" stroke-linecap="round" /><line class="x-line2" x1="26" y1="12" x2="12" y2="26" stroke="#fff" stroke-width="3.5" stroke-linecap="round" /></svg></div></div>
      <h1>{{ $t('payment.failed.title') }}</h1>
      <p class="subtitle">{{ $t('payment.depositFailed.subtitleLine1') }}<br />{{ $t('payment.failed.subtitleLine2') }}</p>
      <div class="info-row"><div class="info-icon">ERR</div><div><div class="info-label">{{ $t('payment.status') }}</div><div class="info-val">{{ $t('payment.failed.statusShort') }}</div></div></div>
      <div class="info-row"><div class="info-icon">INFO</div><div><div class="info-label">{{ $t('payment.failed.reasonLabel') }}</div><div class="info-val">{{ errorReason }}</div></div></div>
      <div class="info-row"><div class="info-icon">SAFE</div><div><div class="info-label">{{ $t('payment.info') }}</div><div class="info-val">{{ $t('payment.failed.noChargeShort') }}</div></div></div>
      <div class="divider"></div>
      <button @click="retryPayment" class="btn-retry"><span class="btn-icon">?</span>{{ $t('payment.failed.retry') }}</button>
      <button @click="contactSupport" class="btn-support">{{ $t('payment.failed.contactSupport') }}</button>
      <button @click="goHome" class="sec-link">{{ $t('payment.backHome') }}</button>
    </div>
  </div>
</template>

<script>
import { clearIdempotencyKey } from "../../services/PaymentService/checkoutSession";

export default {
  name: "PaymentFailed",

  mounted() {
    clearIdempotencyKey("wallet_deposit:paypal");
  },

  computed: {
    errorReason() {
      const reasons = {
                INSTRUMENT_DECLINED: this.$t("payment.failed.reasons.instrumentDeclined"),
                PAYER_CANNOT_PAY: this.$t("payment.failed.reasons.payerCannotPay"),
                TRANSACTION_REFUSED: this.$t("payment.failed.reasons.transactionRefused"),
            };

      const code = this.$route.query.error;
      return reasons[code] || this.$t("payment.failed.reasons.default");
    },
  },

  methods: {
    retryPayment() {
      this.$router.push(`/${this.$route.params.lang}/cart`);
    },

    contactSupport() {
      window.location.href = "mailto:scemorygmail@gmail.com";
    },

    goHome() {
      this.$router.push("/");
    },
  },
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap');

*,
*::before,
*::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

.failed-page {
    font-family: 'Cairo', sans-serif;
    direction: rtl;
    background: #ffffff;
    color: #111111;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.failed-page::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse 600px 400px at 15% 15%, rgba(0, 0, 0, 0.04) 0%, transparent 70%),
        radial-gradient(ellipse 500px 500px at 85% 85%, rgba(0, 0, 0, 0.03) 0%, transparent 70%);
    pointer-events: none;
}

.particles {
    position: fixed;
    inset: 0;
    pointer-events: none;
}

.particle {
    position: absolute;
    top: -10px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #111;
    opacity: 0;
    animation: fall-down 4s ease-in infinite;
}

.particle:nth-child(even) {
    background: #555;
    width: 3px;
    height: 3px;
}

@keyframes fall-down {
    0% {
        top: -10px;
        opacity: 0;
    }

    20% {
        opacity: 0.35;
    }

    100% {
        top: 100%;
        opacity: 0;
    }
}

.card {
    position: relative;
    background: #ffffff;
    border: 1.5px solid #e0e0e0;
    border-radius: 24px;
    padding: 52px 48px 44px;
    max-width: 520px;
    width: 90%;
    text-align: center;
    animation: card-in 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.10), 0 2px 8px rgba(0, 0, 0, 0.06);
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 10%;
    right: 10%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #111, #555, transparent);
    border-radius: 0 0 4px 4px;
}

@keyframes card-in {
    from {
        opacity: 0;
        transform: translateY(40px) scale(0.96);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.paypal-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #f5f5f5;
    border: 1px solid #d0d0d0;
    border-radius: 100px;
    padding: 6px 16px 6px 10px;
    font-size: 12px;
    color: #444;
    margin-bottom: 20px;
    animation: fade-in 0.5s ease 0.5s both;
}

.paypal-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #111;
    box-shadow: 0 0 6px rgba(0, 0, 0, 0.3);
    animation: blink 1s ease-in-out infinite;
}

@keyframes blink {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.2;
    }
}

.icon-wrap {
    position: relative;
    width: 96px;
    height: 96px;
    margin: 0 auto 28px;
    animation: icon-in 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.3s both;
}

@keyframes icon-in {
    from {
        opacity: 0;
        transform: scale(0.4) rotate(10deg);
    }

    to {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
}

.icon-ring {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    border: 2px solid rgba(0, 0, 0, 0.15);
    animation: pulse-ring 2s ease-in-out 1s infinite;
}

@keyframes pulse-ring {

    0%,
    100% {
        transform: scale(1);
        opacity: 1;
    }

    50% {
        transform: scale(1.12);
        opacity: 0.3;
    }
}

.icon-circle {
    position: absolute;
    inset: 10px;
    border-radius: 50%;
    background: linear-gradient(135deg, #222, #444);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.x-line {
    stroke-dasharray: 40;
    stroke-dashoffset: 40;
    animation: draw-x 0.4s ease-out 0.5s forwards;
}

.x-line2 {
    stroke-dasharray: 40;
    stroke-dashoffset: 40;
    animation: draw-x 0.4s ease-out 0.8s forwards;
}

@keyframes draw-x {
    to {
        stroke-dashoffset: 0;
    }
}

h1 {
    font-size: 26px;
    font-weight: 900;
    margin-bottom: 10px;
    color: #111;
    animation: fade-up 0.5s ease 0.5s both;
}

.subtitle {
    color: #888;
    font-size: 15px;
    line-height: 1.7;
    margin-bottom: 32px;
    animation: fade-up 0.5s ease 0.6s both;
}

@keyframes fade-up {
    from {
        opacity: 0;
        transform: translateY(12px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fade-in {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

.info-row {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f9f9f9;
    border: 1px solid #ececec;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 12px;
    text-align: right;
    animation: fade-up 0.5s ease 0.7s both;
}

.info-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 16px;
    background: #efefef;
}

.info-label {
    font-size: 12px;
    color: #999;
    margin-bottom: 2px;
}

.info-val {
    font-size: 14px;
    font-weight: 700;
    color: #111;
}

.info-val.red {
    color: #111;
}

.divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, #ddd, transparent);
    margin: 24px 0;
}

.btn-retry {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
    padding: 16px 24px;
    background: #111;
    border-radius: 14px;
    color: #fff;
    font-family: 'Cairo', sans-serif;
    font-size: 16px;
    font-weight: 700;
    text-decoration: none;
    transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.18);
    animation: fade-up 0.5s ease 1s both;
}

.btn-retry:hover {
    background: #333;
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.25);
}

.btn-icon {
    font-size: 18px;
    animation: spin-once 0.6s ease 1.5s both;
}

@keyframes spin-once {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.btn-support {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 14px 24px;
    margin-top: 12px;
    background: transparent;
    border: 1.5px solid #ccc;
    border-radius: 14px;
    color: #444;
    font-family: 'Cairo', sans-serif;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s, color 0.2s;
    animation: fade-up 0.5s ease 1.1s both;
}

.btn-support:hover {
    background: #f2f2f2;
    border-color: #aaa;
    color: #111;
}

.sec-link {
    display: block;
    margin-top: 14px;
    color: #aaa;
    font-size: 13px;
    text-decoration: none;
    animation: fade-in 0.5s ease 1.2s both;
    transition: color 0.2s;
}

.sec-link:hover {
    color: #333;
}

.failed-page {
    background:
        radial-gradient(circle at 20% 14%, rgba(239, 68, 68, 0.10), transparent 24rem),
        linear-gradient(180deg, #FFFFFF, #F8FAFC);
    color: #0F172A;
    padding: 32px 16px;
}

.particle {
    background: #30A8FF;
}

.card {
    border: 1px solid #E5EDF6;
    box-shadow: 0 18px 55px rgba(13, 77, 151, 0.12);
}

.card::before {
    background: linear-gradient(90deg, transparent, #EF4444, #30A8FF, transparent);
}

.paypal-badge {
    background: #F4F8FC;
    border-color: #DCE8F5;
    color: #0D4D97;
}

.paypal-dot,
.icon-circle {
    background: linear-gradient(135deg, #DC2626, #EF4444);
    box-shadow: 0 12px 30px rgba(239, 68, 68, 0.20);
}

h1 {
    color: #06142A;
}

.subtitle,
.info-label,
.sec-link {
    color: #64748B;
}

.info-row {
    background: #F8FAFC;
    border-color: #DCE8F5;
}

.info-val {
    color: #0F172A;
}

.btn-retry {
    background: linear-gradient(135deg, #0D4D97, #1677FF);
    color: #FFFFFF;
    box-shadow: 0 14px 30px rgba(22, 119, 255, 0.20);
}

.btn-retry:hover {
    background: linear-gradient(135deg, #0D4D97, #30A8FF);
    box-shadow: 0 18px 38px rgba(22, 119, 255, 0.26);
}

.btn-support {
    border-color: #CFE2F6;
    color: #0D4D97;
    background: #FFFFFF;
}
</style>
