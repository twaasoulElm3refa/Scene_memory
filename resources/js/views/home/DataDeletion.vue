<template>
  <div class="scemory-page legal-page policy-page" :dir="direction">
    <div class="scemory-container policy-shell">
      <nav class="policy-breadcrumb" :aria-label="t('policies.ui.breadcrumb')">
        <RouterLink :to="path('/home')">{{ t('nav.home') }}</RouterLink>
        <span aria-hidden="true">/</span>
        <RouterLink :to="path('/policies')">{{ t('policies.ui.legal') }}</RouterLink>
        <span aria-hidden="true">/</span>
        <span>{{ t('dataDeletion.title') }}</span>
      </nav>

      <header class="scemory-page-header policy-header">
        <img :src="toAssetUrl('/images/logo.png')" :alt="t('footer.logoAlt')" width="72" height="72" />
        <div>
          <h1>{{ t('dataDeletion.title') }}</h1>
          <p>{{ t('dataDeletion.subtitle') }}</p>
          <p class="policy-date">
            {{ t('dataDeletion.effectiveDate') }} • {{ t('dataDeletion.metaInstructionsCompliance') }}
          </p>
        </div>
      </header>

      <!-- Status Check Tool Card -->
      <section class="status-check-card" aria-labelledby="status-checker-title">
        <div class="status-check-header">
          <div class="status-icon-wrapper">
            <i class="bi bi-shield-check" aria-hidden="true"></i>
          </div>
          <div>
            <h2 id="status-checker-title">{{ t('dataDeletion.checkStatusTitle') }}</h2>
            <p>{{ t('dataDeletion.checkStatusDesc') }}</p>
          </div>
        </div>

        <form class="status-check-form" @submit.prevent="checkStatus">
          <div class="input-group">
            <input
              type="text"
              v-model="referenceInput"
              :placeholder="t('dataDeletion.referencePlaceholder')"
              aria-label="Deletion Reference Code"
              class="reference-input"
              required
            />
            <button type="submit" class="check-btn" :disabled="isLoading">
              <span v-if="isLoading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
              <span v-else>{{ t('dataDeletion.checkButton') }}</span>
            </button>
          </div>
        </form>

        <div v-if="searchError" class="status-error-alert" role="alert">
          <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
          <span>{{ searchError }}</span>
        </div>

        <div v-if="statusResult" class="status-result-card" role="region" aria-live="polite">
          <div class="result-grid">
            <div class="result-item">
              <span class="result-label">{{ t('dataDeletion.statusResult.reference') }}</span>
              <strong class="result-value font-mono">{{ statusResult.reference }}</strong>
            </div>

            <div class="result-item">
              <span class="result-label">{{ t('dataDeletion.statusResult.status') }}</span>
              <span :class="['status-badge', statusBadgeClass(statusResult.status)]">
                {{ formatStatus(statusResult.status) }}
              </span>
            </div>

            <div class="result-item" v-if="statusResult.masked_email">
              <span class="result-label">{{ t('dataDeletion.statusResult.maskedEmail') }}</span>
              <span class="result-value">{{ statusResult.masked_email }}</span>
            </div>

            <div class="result-item" v-if="statusResult.completed_at">
              <span class="result-label">{{ t('policies.ui.effectiveDate') }}</span>
              <span class="result-value">{{ formatDate(statusResult.completed_at) }}</span>
            </div>
          </div>

          <div v-if="statusResult.financial_obligations_detected" class="financial-warning-note">
            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
            <span>{{ t('dataDeletion.statusResult.financialNotice') }}</span>
          </div>
        </div>
      </section>

      <!-- Main Policy Instructions Layout -->
      <div class="policy-layout">
        <nav class="policy-toc" :aria-label="t('policies.ui.contents')">
          <h2>{{ t('policies.ui.contents') }}</h2>
          <a href="#section-a">{{ t('dataDeletion.sections.sectionA.title') }}</a>
          <a href="#section-b">{{ t('dataDeletion.sections.sectionB.title') }}</a>
          <a href="#section-c">{{ t('dataDeletion.sections.sectionC.title') }}</a>
          <a href="#section-d">{{ t('dataDeletion.sections.sectionD.title') }}</a>
          <a href="#section-e">{{ t('dataDeletion.sections.sectionE.title') }}</a>
          <a href="#section-f">{{ t('dataDeletion.sections.sectionF.title') }}</a>
          <a href="#section-g">{{ t('dataDeletion.sections.sectionG.title') }}</a>
        </nav>

        <article class="policy-article" :aria-label="t('dataDeletion.title')">
          <!-- Section A -->
          <section id="section-a" tabindex="-1">
            <h2>{{ t('dataDeletion.sections.sectionA.title') }}</h2>
            <p>{{ t('dataDeletion.sections.sectionA.p1') }}</p>
            <ol class="steps-list">
              <li v-for="(step, idx) in tm('dataDeletion.sections.sectionA.steps')" :key="idx">
                {{ rt(step) }}
              </li>
            </ol>
            <div class="action-box">
              <RouterLink :to="path('/profile?tab=privacy')" class="primary-action-btn">
                <i class="bi bi-person-gear" aria-hidden="true"></i>
                <span>{{ t('profilePage.tabs.privacy') }}</span>
              </RouterLink>
            </div>
          </section>

          <!-- Section B -->
          <section id="section-b" tabindex="-1">
            <h2>{{ t('dataDeletion.sections.sectionB.title') }}</h2>
            <p>{{ t('dataDeletion.sections.sectionB.p1') }}</p>
            <div class="email-highlight-box">
              <i class="bi bi-envelope-at-fill" aria-hidden="true"></i>
              <a href="mailto:scemorygmail@gmail.com" class="contact-email">
                scemorygmail@gmail.com
              </a>
            </div>
            <p>{{ t('dataDeletion.sections.sectionB.p2') }}</p>
          </section>

          <!-- Section C -->
          <section id="section-c" tabindex="-1">
            <h2>{{ t('dataDeletion.sections.sectionC.title') }}</h2>
            <p>{{ t('dataDeletion.sections.sectionC.p1') }}</p>
            <p>{{ t('dataDeletion.sections.sectionC.p2') }}</p>
            <p>{{ t('dataDeletion.sections.sectionC.p3') }}</p>
          </section>

          <!-- Section D -->
          <section id="section-d" tabindex="-1">
            <h2>{{ t('dataDeletion.sections.sectionD.title') }}</h2>
            <p>{{ t('dataDeletion.sections.sectionD.p1') }}</p>
            <ul class="bullet-list">
              <li v-for="(item, idx) in tm('dataDeletion.sections.sectionD.items')" :key="idx">
                {{ rt(item) }}
              </li>
            </ul>
          </section>

          <!-- Section E -->
          <section id="section-e" tabindex="-1">
            <h2>{{ t('dataDeletion.sections.sectionE.title') }}</h2>
            <p>{{ t('dataDeletion.sections.sectionE.p1') }}</p>
            <ul class="bullet-list">
              <li v-for="(item, idx) in tm('dataDeletion.sections.sectionE.items')" :key="idx">
                {{ rt(item) }}
              </li>
            </ul>
            <p>{{ t('dataDeletion.sections.sectionE.p2') }}</p>
          </section>

          <!-- Section F -->
          <section id="section-f" tabindex="-1">
            <h2>{{ t('dataDeletion.sections.sectionF.title') }}</h2>
            <p>{{ t('dataDeletion.sections.sectionF.p1') }}</p>
            <p>{{ t('dataDeletion.sections.sectionF.p2') }}</p>
            <p>{{ t('dataDeletion.sections.sectionF.p3') }}</p>
          </section>

          <!-- Section G -->
          <section id="section-g" tabindex="-1">
            <h2>{{ t('dataDeletion.sections.sectionG.title') }}</h2>
            <p>{{ t('dataDeletion.sections.sectionG.p1') }}</p>
          </section>

          <RouterLink class="policy-contact" :to="{ path: path('/contact'), query: { topic: 'privacy' } }">
            {{ t('policies.ui.contact') }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
          </RouterLink>
        </article>
      </div>

      <!-- Related Legal Pages -->
      <nav class="policy-related" :aria-label="t('policies.ui.related')">
        <h2>{{ t('policies.ui.related') }}</h2>
        <div class="policy-cards">
          <RouterLink :to="path('/privacy-policy')">
            <h3>{{ t('policies.pages.privacy.title') }}</h3>
            <p>{{ t('policies.pages.privacy.description') }}</p>
          </RouterLink>
          <RouterLink :to="path('/terms')">
            <h3>{{ t('policies.pages.terms.title') }}</h3>
            <p>{{ t('policies.pages.terms.description') }}</p>
          </RouterLink>
          <RouterLink :to="path('/contact')">
            <h3>{{ t('nav.contact') }}</h3>
            <p>{{ t('policies.ui.contact') }}</p>
          </RouterLink>
        </div>
      </nav>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { toAssetUrl } from '../../services/runtimeUrls';
import { getPublicDataDeletionStatus } from '../../services/userDataDeletionService';

const route = useRoute();
const { t, tm, rt, locale } = useI18n();

const path = suffix => `/${locale.value}${suffix}`;
const direction = computed(() => ['ar', 'fa', 'ur'].includes(locale.value) ? 'rtl' : 'ltr');

const referenceInput = ref('');
const isLoading = ref(false);
const searchError = ref(null);
const statusResult = ref(null);

const checkStatus = async () => {
  const refCode = referenceInput.value?.trim();
  if (!refCode) return;

  isLoading.value = true;
  searchError.value = null;
  statusResult.value = null;

  try {
    const res = await getPublicDataDeletionStatus(refCode);
    if (res?.data) {
      statusResult.value = res.data;
    } else {
      searchError.value = t('dataDeletion.statusNotFound');
    }
  } catch (err) {
    searchError.value = err?.response?.data?.message || t('dataDeletion.statusNotFound');
  } finally {
    isLoading.value = false;
  }
};

const formatStatus = statusKey => {
  if (!statusKey) return '';
  return t(`dataDeletion.statusResult.statuses.${statusKey}`, statusKey);
};

const statusBadgeClass = status => {
  switch (status) {
    case 'completed':
      return 'badge-success';
    case 'under_review':
      return 'badge-warning';
    case 'processing':
    case 'pending':
      return 'badge-info';
    case 'cancelled':
    case 'failed':
      return 'badge-danger';
    default:
      return 'badge-secondary';
  }
};

const formatDate = dateStr => {
  if (!dateStr) return '';
  try {
    return new Date(dateStr).toLocaleString(locale.value, {
      dateStyle: 'medium',
      timeStyle: 'short',
    });
  } catch {
    return dateStr;
  }
};

onMounted(() => {
  // Check if reference is in query params (e.g. from Facebook redirect or direct URL)
  const queryRef = route.query.reference || route.query.code;
  if (queryRef && typeof queryRef === 'string') {
    referenceInput.value = queryRef.trim();
    checkStatus();
  }
});
</script>

<style scoped>
.policy-page { padding-block: 28px 64px; }
.policy-shell { max-width: 1200px; }
.policy-breadcrumb { display: flex; gap: 12px; margin-block-end: 22px; flex-wrap: wrap; }
.policy-header { display: flex; gap: 24px; padding: clamp(24px, 4vw, 44px); align-items: flex-start; }
.policy-header img { border-radius: 50%; flex-shrink: 0; }
.policy-header h1 { font-size: clamp(1.6rem, 3vw, 2.5rem); margin-block: 0 14px; overflow-wrap: anywhere; }
.policy-header p { max-width: 70ch; line-height: 1.8; margin-block-end: 8px; }
.policy-date { font-size: .9rem; opacity: .85; }

/* Status Checker Box */
.status-check-card {
  margin-block: 24px 36px;
  background: var(--scemory-surface, #ffffff);
  border: 1px solid var(--scemory-border, #e2e8f0);
  border-radius: 16px;
  padding: clamp(20px, 3vw, 32px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}
.status-check-header {
  display: flex;
  gap: 16px;
  align-items: flex-start;
  margin-block-end: 20px;
}
.status-icon-wrapper {
  font-size: 2rem;
  color: var(--scemory-primary, #1677ff);
  line-height: 1;
}
.status-check-header h2 {
  font-size: 1.3rem;
  font-weight: 700;
  margin: 0 0 6px 0;
}
.status-check-header p {
  margin: 0;
  color: var(--scemory-muted, #64748b);
  font-size: .95rem;
  line-height: 1.6;
}
.status-check-form { margin-block-end: 16px; }
.input-group {
  display: flex;
  gap: 12px;
  max-width: 580px;
}
.reference-input {
  flex: 1;
  padding: 12px 16px;
  font-size: 1rem;
  border: 1px solid var(--scemory-border, #cbd5e1);
  border-radius: 8px;
  background: var(--scemory-surface-soft, #f8fafc);
  color: inherit;
}
.reference-input:focus {
  outline: 2px solid var(--scemory-primary, #1677ff);
  background: #fff;
}
.check-btn {
  padding: 12px 24px;
  font-weight: 600;
  border-radius: 8px;
  background: var(--scemory-primary, #1677ff);
  color: #fff;
  border: none;
  cursor: pointer;
  transition: opacity .2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 120px;
}
.check-btn:hover:not(:disabled) { opacity: .9; }
.check-btn:disabled { opacity: .6; cursor: not-allowed; }

.status-error-alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 8px;
  background: #fef2f2;
  color: #dc2626;
  font-size: .95rem;
  margin-block-start: 12px;
}

.status-result-card {
  margin-block-start: 20px;
  padding: 20px;
  border-radius: 12px;
  background: var(--scemory-surface-soft, #f8fafc);
  border: 1px solid var(--scemory-border, #e2e8f0);
}
.result-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
}
.result-item { display: flex; flex-direction: column; gap: 4px; }
.result-label { font-size: .8rem; text-transform: uppercase; color: var(--scemory-muted, #64748b); font-weight: 600; }
.result-value { font-size: 1rem; color: inherit; }

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: .85rem;
  font-weight: 600;
  width: fit-content;
}
.badge-success { background: #dcfce7; color: #166534; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-info { background: #e0f2fe; color: #075985; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-secondary { background: #f1f5f9; color: #475569; }

.financial-warning-note {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-block-start: 16px;
  padding: 12px 16px;
  border-radius: 8px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
  font-size: .9rem;
  line-height: 1.6;
}

/* Policy Content */
.policy-layout { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(0, 3fr); gap: 32px; align-items: start; }
.policy-toc { position: sticky; top: 24px; display: grid; gap: 8px; }
.policy-toc h2, .policy-related > h2 { font-size: 1.15rem; margin-block-end: 12px; }
.policy-toc a { padding: 8px 12px; border-inline-start: 2px solid var(--scemory-border); text-decoration: none; overflow-wrap: anywhere; }
.policy-article { padding: clamp(20px, 4vw, 40px); background: var(--scemory-surface); border: 1px solid var(--scemory-border-soft); border-radius: var(--scemory-radius); }
.policy-article section { scroll-margin-top: 32px; padding-block: 24px; border-block-end: 1px solid var(--scemory-border-soft); }
.policy-article section:first-child { padding-block-start: 0; }
.policy-article h2 { font-size: 1.25rem; line-height: 1.5; margin-block-end: 12px; }
.policy-article p { line-height: 1.95; max-width: 75ch; margin: 0 0 12px 0; overflow-wrap: anywhere; }

.steps-list, .bullet-list {
  padding-inline-start: 24px;
  margin: 12px 0 18px 0;
  display: grid;
  gap: 10px;
  line-height: 1.8;
}
.action-box { margin-block-start: 16px; }
.primary-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  background: var(--scemory-primary, #1677ff);
  color: #fff;
  border-radius: 8px;
  font-weight: 600;
  text-decoration: none;
}
.primary-action-btn:hover { text-decoration: underline; }

.email-highlight-box {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  background: var(--scemory-surface-soft, #f8fafc);
  border: 1px solid var(--scemory-border, #e2e8f0);
  border-radius: 8px;
  margin-block: 8px 16px;
  font-size: 1.05rem;
}
.contact-email { font-weight: 700; color: var(--scemory-primary, #1677ff); text-decoration: underline; }

.policy-contact { display: inline-flex; align-items: center; gap: 12px; margin-block-start: 24px; font-weight: 700; }
.policy-related { margin-block-start: 40px; }
.policy-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.policy-cards > a { padding: 22px; border: 1px solid var(--scemory-border); border-radius: 16px; text-decoration: none; background: var(--scemory-surface); }
.policy-cards h3 { font-size: 1rem; line-height: 1.6; margin-block-end: 8px; }
.policy-cards p { font-size: .9rem; line-height: 1.8; margin: 0; }

@media (max-width: 767px) {
  .policy-layout { grid-template-columns: minmax(0, 1fr); }
  .policy-toc { position: static; }
  .policy-cards { grid-template-columns: minmax(0, 1fr); }
  .policy-header { flex-direction: column; gap: 16px; }
  .input-group { flex-direction: column; }
}
</style>
