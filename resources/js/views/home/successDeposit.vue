<template>
  <div class="scemory-page success-page">
    <div class="particles"><div v-for="i in 8" :key="i" class="particle" :style="{ left: (i * 12) + '%', animationDelay: (i * 0.3) + 's' }"></div></div>
    <div class="card">
      <div class="paypal-badge"><div class="paypal-dot"></div>{{ $t('payment.depositSuccess.paypalBadge') }}</div>
      <div class="icon-wrap"><div class="icon-ring"></div><div class="icon-circle"><svg width="38" height="38" viewBox="0 0 38 38" fill="none"><polyline class="checkmark" points="8,20 16,28 30,12" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" /></svg></div></div>
      <h1>{{ $t('payment.depositSuccess.title') }}</h1>
      <p class="subtitle">{{ $t('payment.depositSuccess.subtitleLine1') }}<br />{{ $t('payment.depositSuccess.subtitleLine2') }}</p>
      <div class="info-row"><div class="info-icon">WAL</div><div><div class="info-label">{{ $t('payment.operationStatus') }}</div><div class="info-val">{{ $t('payment.successful') }}</div></div></div>
      <div class="info-row"><div class="info-icon">MAIL</div><div><div class="info-label">{{ $t('payment.notification') }}</div><div class="info-val">{{ $t('payment.depositSuccess.emailSent') }}</div></div></div>
      <div class="info-row"><div class="info-icon">PAY</div><div><div class="info-label">{{ $t('payment.transactionId') }}</div><div class="info-val">{{ transactionId }}</div></div></div>
      <div class="divider"></div>
      <button @click="goToDownloads" class="btn-downloads"><span class="btn-arrow">?</span>{{ $t('payment.depositSuccess.goProfile') }}</button>
      <button @click="goHome" class="sec-link">{{ $t('payment.backHome') }}</button>
    </div>
  </div>
</template>

<script>
export default {
  name: "PaymentSuccess",

  computed: {
    transactionId() {
      return this.$route.query.token || "PP-" + Date.now();
    },
  },

  methods: {
    goToDownloads() {
      this.$router.push(`/${this.$route.params.lang}/profile`);
    },
    goHome() {
      this.$router.push("/");
    },
  },
};
</script>

<style scoped>
.success-page {
  font-family: 'Cairo', sans-serif;
  direction: rtl;
  background: #fff;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}

/* particles */
.particle {
  position: absolute;
  width: 4px;
  height: 4px;
  background: #111;
  border-radius: 50%;
  animation: float 4s infinite;
}
@keyframes float {
  from { bottom: -10px; opacity: 0; }
  to { bottom: 100%; opacity: 1; }
}

/* card */
.card {
  background: #fff;
  border-radius: 20px;
  padding: 40px;
  width: 420px;
  text-align: center;
  box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

/* badge */
.paypal-badge {
  background: #f3f3f3;
  padding: 6px 14px;
  border-radius: 20px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  margin-bottom: 20px;
}
.paypal-dot {
  width: 8px;
  height: 8px;
  background: black;
  border-radius: 50%;
}

/* icon */
.icon-circle {
  background: #111;
  width: 80px;
  height: 80px;
  border-radius: 50%;
  margin: auto;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* info */
.info-row {
  display: flex;
  gap: 10px;
  background: #f7f7f7;
  padding: 12px;
  border-radius: 10px;
  margin-bottom: 10px;
  text-align: right;
}
.info-label {
  font-size: 12px;
  color: #888;
}
.info-val {
  font-weight: bold;
}

/* buttons */
.btn-downloads {
  width: 100%;
  padding: 14px;
  background: black;
  color: white;
  border-radius: 12px;
  margin-top: 20px;
  cursor: pointer;
  border: none;
  font-weight: bold;
}
.btn-downloads:hover {
  background: #333;
}

.sec-link {
  margin-top: 10px;
  background: none;
  border: none;
  color: #777;
  cursor: pointer;
}

.success-page {
  background:
    radial-gradient(circle at 22% 14%, rgba(34, 197, 94, 0.10), transparent 24rem),
    linear-gradient(180deg, #FFFFFF, #F8FAFC);
  color: #0F172A;
  padding: 32px 16px;
}

.particle { background: #30A8FF; }

.card {
  border: 1px solid #E5EDF6;
  box-shadow: 0 18px 55px rgba(13, 77, 151, 0.12);
}

.card::before {
  background: linear-gradient(90deg, transparent, #22C55E, #30A8FF, transparent);
}

.paypal-badge {
  background: #F4F8FC;
  border-color: #DCE8F5;
  color: #0D4D97;
}

.paypal-dot,
.icon-circle {
  background: linear-gradient(135deg, #16A34A, #22C55E);
  box-shadow: 0 12px 30px rgba(34, 197, 94, 0.20);
}

h1 { color: #06142A; }
.subtitle,
.info-label,
.sec-link { color: #64748B; }

.info-row {
  background: #F8FAFC;
  border: 1px solid #DCE8F5;
}

.info-val { color: #0F172A; }

.btn-downloads {
  background: linear-gradient(135deg, #0D4D97, #1677FF);
  box-shadow: 0 14px 30px rgba(22, 119, 255, 0.20);
}

.btn-downloads:hover {
  background: linear-gradient(135deg, #0D4D97, #30A8FF);
  box-shadow: 0 18px 38px rgba(22, 119, 255, 0.26);
}
</style>
