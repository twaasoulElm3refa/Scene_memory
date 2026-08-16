<template>
  <div class="scemory-page success-page">
    <div class="particles"><div v-for="i in 8" :key="i" class="particle" :style="{ left: (i * 12) + '%', animationDelay: (i * 0.3) + 's' }"></div></div>
    <div class="card">
      <div class="paypal-badge"><div class="paypal-dot"></div>{{ $t('payment.success.paypalBadge') }}</div>
      <div class="icon-wrap"><div class="icon-ring"></div><div class="icon-circle"><svg width="38" height="38" viewBox="0 0 38 38" fill="none"><polyline class="checkmark" points="8,20 16,28 30,12" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div></div>
      <h1>{{ $t('payment.success.title') }}</h1>
      <p class="subtitle">{{ $t('payment.success.subtitleLine1') }}<br />{{ $t('payment.success.subtitleLine2') }}</p>
      <div class="info-row"><div class="info-icon green">OK</div><div><div class="info-label">{{ $t('payment.success.orderStatus') }}</div><div class="info-val green">{{ $t('payment.success.statusValue') }}</div></div></div>
      <div class="info-row"><div class="info-icon blue">MAIL</div><div><div class="info-label">{{ $t('payment.success.confirmationLabel') }}</div><div class="info-val">{{ $t('payment.success.emailSent') }}</div></div></div>
      <div class="info-row"><div class="info-icon green">PAY</div><div><div class="info-label">{{ $t('payment.transactionId') }}</div><div class="info-val">{{ transactionId }}</div></div></div>
      <div class="divider"></div>
      <router-link :to="`/${$route.params.lang}/downloads`" class="btn-downloads"><span class="btn-arrow">?</span>{{ $t('payment.success.goDownloads') }}</router-link>
      <router-link to="/" class="sec-link">{{ $t('payment.backHome') }}</router-link>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PaymentSuccess',
  computed: {
    transactionId() {
      return this.$route.query.token || '#PP-' + Date.now()
    }
  }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.success-page {
  font-family: 'Cairo', sans-serif;
  direction: rtl;
  background: #ffffff;
  color: #111111;
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  position: relative; overflow: hidden;
}
.success-page::before {
  content: ''; position: fixed; inset: 0;
  background:
    radial-gradient(ellipse 600px 400px at 20% 20%, rgba(0,0,0,0.03) 0%, transparent 70%),
    radial-gradient(ellipse 500px 500px at 80% 80%, rgba(0,0,0,0.03) 0%, transparent 70%);
  pointer-events: none;
}

.particles { position: fixed; inset: 0; pointer-events: none; }
.particle {
  position: absolute; width: 4px; height: 4px;
  border-radius: 50%; background: #111; opacity: 0;
  animation: float-up 4s ease-in infinite;
}
.particle:nth-child(even) { background: #777; width:3px; height:3px; }
@keyframes float-up {
  0%   { bottom:-10px; opacity:0; }
  20%  { opacity:0.4; }
  100% { bottom:100%; opacity:0; }
}

.card {
  position: relative;
  background: #ffffff;
  border: 1.5px solid #e0e0e0;
  border-radius: 24px; padding: 52px 48px 44px;
  max-width: 520px; width: 90%; text-align: center;
  animation: card-in 0.7s cubic-bezier(0.16,1,0.3,1) both;
  box-shadow: 0 8px 40px rgba(0,0,0,0.10), 0 2px 8px rgba(0,0,0,0.06);
}
.card::before {
  content: ''; position: absolute;
  top:0; left:10%; right:10%; height:2px;
  background: linear-gradient(90deg, transparent, #111, #666, transparent);
  border-radius: 0 0 4px 4px;
}
@keyframes card-in {
  from { opacity:0; transform: translateY(40px) scale(0.96); }
  to   { opacity:1; transform: translateY(0) scale(1); }
}

.paypal-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: #f5f5f5; border: 1px solid #d0d0d0;
  border-radius: 100px; padding: 6px 16px 6px 10px;
  font-size: 12px; color: #444; margin-bottom: 20px;
  animation: fade-in 0.5s ease 0.5s both;
}
.paypal-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: #111; box-shadow: 0 0 6px rgba(0,0,0,0.3);
}

.icon-wrap {
  position: relative; width: 96px; height: 96px;
  margin: 0 auto 28px;
  animation: icon-in 0.6s cubic-bezier(0.16,1,0.3,1) 0.3s both;
}
@keyframes icon-in {
  from { opacity:0; transform: scale(0.4); }
  to   { opacity:1; transform: scale(1); }
}
.icon-ring {
  position: absolute; inset: 0; border-radius: 50%;
  border: 2px solid rgba(0,0,0,0.15);
  animation: pulse-ring 2s ease-in-out 1s infinite;
}
@keyframes pulse-ring {
  0%,100% { transform:scale(1); opacity:1; }
  50% { transform:scale(1.12); opacity:0.3; }
}
.icon-circle {
  position: absolute; inset: 10px; border-radius: 50%;
  background: linear-gradient(135deg, #222, #444);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}
.checkmark {
  stroke-dasharray: 50; stroke-dashoffset: 50;
  animation: draw-check 0.6s ease-out 0.6s forwards;
}
@keyframes draw-check { to { stroke-dashoffset:0; } }

h1 {
  font-size: 28px; font-weight: 900; margin-bottom: 10px; color: #111;
  animation: fade-up 0.5s ease 0.5s both;
}
.subtitle {
  color: #888; font-size: 15px; line-height: 1.7;
  margin-bottom: 32px; animation: fade-up 0.5s ease 0.6s both;
}
@keyframes fade-up {
  from { opacity:0; transform: translateY(12px); }
  to   { opacity:1; transform: translateY(0); }
}
@keyframes fade-in { from{opacity:0;} to{opacity:1;} }

.info-row {
  display: flex; align-items: center; gap: 12px;
  background: #f9f9f9; border: 1px solid #ececec;
  border-radius: 12px; padding: 14px 18px; margin-bottom: 12px;
  text-align: right; animation: fade-up 0.5s ease 0.7s both;
}
.info-icon {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: 16px; background: #efefef;
}
.info-icon.green { background: #efefef; }
.info-icon.blue  { background: #efefef; }
.info-label { font-size: 12px; color: #999; margin-bottom: 2px; }
.info-val   { font-size: 14px; font-weight: 700; color: #111; }
.info-val.green { color: #222; }

.divider {
  height: 1px;
  background: linear-gradient(90deg, transparent, #ddd, transparent);
  margin: 24px 0;
}

.btn-downloads {
  display: flex; align-items: center; justify-content: center; gap: 12px;
  width: 100%; padding: 16px 24px;
  background: #111;
  border-radius: 14px; color: #fff;
  font-family: 'Cairo', sans-serif; font-size: 16px; font-weight: 700;
  text-decoration: none;
  transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
  box-shadow: 0 4px 20px rgba(0,0,0,0.18);
  animation: fade-up 0.5s ease 1s both;
  overflow: hidden; position: relative;
}
.btn-downloads::before {
  content: ''; position: absolute; top:0; left:-100%; width:60%; height:100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
  transition: left 0.6s;
}
.btn-downloads:hover::before { left:160%; }
.btn-downloads:hover { background: #333; transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,0.25); }
.btn-arrow { font-size: 20px; transition: transform 0.2s; }
.btn-downloads:hover .btn-arrow { transform: translateX(-4px); }

.sec-link {
  display: block; margin-top: 16px; color: #aaa;
  font-size: 13px; text-decoration: none;
  animation: fade-in 0.5s ease 1.1s both; transition: color 0.2s;
}
.sec-link:hover { color: #333; }

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
  border-color: #DCE8F5;
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
