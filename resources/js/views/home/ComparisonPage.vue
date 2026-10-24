<template>
  <div class="scemory-comparison-page" :dir="direction">
    <!-- Breadcrumb -->
    <div class="scemory-container breadcrumb-container">
      <nav class="comparison-breadcrumb" aria-label="Breadcrumb">
        <RouterLink :to="path('/home')">{{ t('nav.home') }}</RouterLink>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <span>{{ t('comparisons.footerTitle') }}</span>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <span class="breadcrumb-current">{{ currentCompetitor.name }}</span>
      </nav>
    </div>

    <!-- Section A: Hero Banner -->
    <header class="comparison-hero">
      <div class="scemory-container hero-container">
        <!-- Logo Matchup -->
        <div class="hero-logos-wrapper">
          <div class="brand-card scemory-brand-card">
            <img
              :src="toAssetUrl('/images/logo.png')"
              :alt="t('footer.logoAlt') || 'Scemory'"
              width="96"
              height="96"
              class="brand-logo"
            />
            <span class="brand-name">Scemory</span>
          </div>

          <div class="vs-badge-container">
            <span class="vs-badge">{{ t('comparisons.common.vs') }}</span>
          </div>

          <div class="brand-card competitor-brand-card">
            <img
              :src="toAssetUrl(currentCompetitor.logo)"
              :alt="t(currentCompetitor.logoAltKey)"
              width="96"
              height="96"
              class="brand-logo competitor-logo"
            />
            <span class="brand-name">{{ currentCompetitor.name }}</span>
          </div>
        </div>

        <!-- Hero Headline & Subtitle -->
        <h1 class="hero-title">{{ t(`comparisons.${competitorKey}.heroTitle`) }}</h1>
        <p class="hero-subtitle">{{ t(`comparisons.${competitorKey}.heroSubtitle`) }}</p>

        <!-- Verification Date Badge -->
        <div class="verification-pill">
          <i class="bi bi-shield-check" aria-hidden="true"></i>
          <span>{{ t('comparisons.common.verifiedDate') }}</span>
        </div>

        <!-- Hero Action Buttons -->
        <div class="hero-actions">
          <RouterLink :to="path('/auth')" class="btn btn-primary hero-btn">
            <i class="bi bi-person-plus" aria-hidden="true"></i>
            {{ t('comparisons.common.createAccount') }}
          </RouterLink>
          <RouterLink :to="path('/home')" class="btn btn-outline hero-btn">
            <i class="bi bi-compass" aria-hidden="true"></i>
            {{ t('comparisons.common.exploreScemory') }}
          </RouterLink>
          <a :href="currentCompetitor.website" target="_blank" rel="noopener noreferrer" class="btn btn-subtle hero-btn">
            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
            {{ t('comparisons.common.visitOfficialWebsite') }}
          </a>
        </div>
      </div>
    </header>

    <!-- Main Content Container -->
    <div class="scemory-container comparison-main">
      <!-- Section B: Platform Overviews -->
      <section class="comparison-section overviews-section" aria-labelledby="section-overviews-title">
        <div class="section-header">
          <h2 id="section-overviews-title">{{ t('comparisons.common.overviewTitle') }}</h2>
          <div class="header-line"></div>
        </div>

        <div class="overviews-grid">
          <!-- Scemory Overview Card -->
          <article class="overview-card scemory-overview">
            <div class="overview-card-header">
              <img
                :src="toAssetUrl('/images/logo.png')"
                :alt="t('footer.logoAlt')"
                width="48"
                height="48"
                class="overview-logo"
              />
              <div>
                <h3>Scemory</h3>
                <span class="overview-tagline">Scene Memory</span>
              </div>
            </div>
            <p class="overview-description">
              {{ t(`comparisons.${competitorKey}.aboutScemory`) }}
            </p>
            <ul class="overview-metadata">
              <li>
                <strong>{{ t('comparisons.common.organizationType') }}:</strong>
                <span>Next-Gen Event & Memory Platform</span>
              </li>
              <li>
                <strong>{{ t('comparisons.common.founded') }}:</strong>
                <span>2026</span>
              </li>
              <li>
                <strong>{{ t('comparisons.common.headquarters') }}:</strong>
                <span>Global Distributed Cloud</span>
              </li>
              <li>
                <strong>{{ t('comparisons.common.coreMission') }}:</strong>
                <span>Preserve human moments, document contemporary events, and reward visual storytellers.</span>
              </li>
            </ul>
          </article>

          <!-- Competitor Overview Card -->
          <article class="overview-card competitor-overview">
            <div class="overview-card-header">
              <img
                :src="toAssetUrl(currentCompetitor.logo)"
                :alt="t(currentCompetitor.logoAltKey)"
                width="48"
                height="48"
                class="overview-logo"
              />
              <div>
                <h3>{{ currentCompetitor.name }}</h3>
                <a :href="currentCompetitor.website" target="_blank" rel="noopener noreferrer" class="competitor-external-link">
                  {{ currentCompetitor.website }}
                  <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
              </div>
            </div>
            <p class="overview-description">
              {{ t(`comparisons.${competitorKey}.aboutCompetitor`) }}
            </p>
            <ul class="overview-metadata">
              <li>
                <strong>{{ t('comparisons.common.organizationType') }}:</strong>
                <span>{{ currentCompetitor.organizationType }}</span>
              </li>
              <li>
                <strong>{{ t('comparisons.common.founded') }}:</strong>
                <span>{{ currentCompetitor.yearFounded }}</span>
              </li>
              <li>
                <strong>{{ t('comparisons.common.headquarters') }}:</strong>
                <span>{{ currentCompetitor.headquarters }}</span>
              </li>
              <li>
                <strong>{{ t('comparisons.common.coreMission') }}:</strong>
                <span>{{ t(`comparisons.${competitorKey}.primaryFocus`) }}</span>
              </li>
            </ul>
          </article>
        </div>
      </section>

      <!-- Section C: 19-Feature Comparison Table -->
      <section id="comparison-table" class="comparison-section table-section" aria-labelledby="section-table-title">
        <div class="section-header">
          <h2 id="section-table-title">{{ t('comparisons.common.tableTitle') }}</h2>
          <div class="header-line"></div>
          <p class="section-subtitle">{{ t('comparisons.common.independentNotice') }}</p>
        </div>

        <!-- Legend -->
        <div class="status-legend" aria-label="Status Legend">
          <div class="legend-item">
            <span class="status-pill status-supported">
              <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
              {{ t('comparisons.status.supported') }}
            </span>
          </div>
          <div class="legend-item">
            <span class="status-pill status-partial">
              <i class="bi bi-dash-circle-fill" aria-hidden="true"></i>
              {{ t('comparisons.status.partial') }}
            </span>
          </div>
          <div class="legend-item">
            <span class="status-pill status-not_offered">
              <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
              {{ t('comparisons.status.not_offered') }}
            </span>
          </div>
          <div class="legend-item">
            <span class="status-pill status-not_documented">
              <i class="bi bi-question-circle-fill" aria-hidden="true"></i>
              {{ t('comparisons.status.not_documented') }}
            </span>
          </div>
        </div>

        <!-- Desktop Table -->
        <div class="table-responsive-wrapper">
          <table class="comparison-table">
            <thead>
              <tr>
                <th scope="col" class="th-feature">{{ t('comparisons.common.feature') }}</th>
                <th scope="col" class="th-scemory">
                  <div class="th-brand">
                    <img :src="toAssetUrl('/images/logo.png')" alt="" width="24" height="24" aria-hidden="true" />
                    <span>Scemory</span>
                  </div>
                </th>
                <th scope="col" class="th-competitor">
                  <div class="th-brand">
                    <img :src="toAssetUrl(currentCompetitor.logo)" alt="" width="24" height="24" aria-hidden="true" />
                    <span>{{ currentCompetitor.name }}</span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="featKey in comparisonFeatures" :key="featKey">
                <td class="td-feature">
                  <span class="feature-name">{{ t(`comparisons.features.${featKey}.name`) }}</span>
                  <span class="feature-desc">{{ t(`comparisons.features.${featKey}.desc`) }}</span>
                </td>
                <td class="td-status td-scemory-status">
                  <span :class="['status-pill', `status-${scemoryFeatureStatuses[featKey]}`]">
                    <i :class="statusIcon(scemoryFeatureStatuses[featKey])" aria-hidden="true"></i>
                    {{ t(`comparisons.status.${scemoryFeatureStatuses[featKey]}`) }}
                  </span>
                </td>
                <td class="td-status td-competitor-status">
                  <span :class="['status-pill', `status-${currentCompetitor.featureStatuses[featKey]}`]">
                    <i :class="statusIcon(currentCompetitor.featureStatuses[featKey])" aria-hidden="true"></i>
                    {{ t(`comparisons.status.${currentCompetitor.featureStatuses[featKey]}`) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Stacked Feature Cards -->
        <div class="mobile-feature-cards">
          <div v-for="featKey in comparisonFeatures" :key="`mobile-${featKey}`" class="mobile-feature-card">
            <h4 class="mobile-feature-title">{{ t(`comparisons.features.${featKey}.name`) }}</h4>
            <p class="mobile-feature-desc">{{ t(`comparisons.features.${featKey}.desc`) }}</p>
            <div class="mobile-feature-rows">
              <div class="mobile-row scemory-row">
                <div class="mobile-brand-label">
                  <img :src="toAssetUrl('/images/logo.png')" alt="" width="18" height="18" aria-hidden="true" />
                  <strong>Scemory:</strong>
                </div>
                <span :class="['status-pill', `status-${scemoryFeatureStatuses[featKey]}`]">
                  <i :class="statusIcon(scemoryFeatureStatuses[featKey])" aria-hidden="true"></i>
                  {{ t(`comparisons.status.${scemoryFeatureStatuses[featKey]}`) }}
                </span>
              </div>
              <div class="mobile-row competitor-row">
                <div class="mobile-brand-label">
                  <img :src="toAssetUrl(currentCompetitor.logo)" alt="" width="18" height="18" aria-hidden="true" />
                  <strong>{{ currentCompetitor.name }}:</strong>
                </div>
                <span :class="['status-pill', `status-${currentCompetitor.featureStatuses[featKey]}`]">
                  <i :class="statusIcon(currentCompetitor.featureStatuses[featKey])" aria-hidden="true"></i>
                  {{ t(`comparisons.status.${currentCompetitor.featureStatuses[featKey]}`) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Section D: What Scemory Offers Beyond Competitor -->
      <section class="comparison-section beyond-section" aria-labelledby="section-beyond-title">
        <div class="section-header">
          <h2 id="section-beyond-title">
            {{ t('comparisons.common.beyondTitle', { competitor: currentCompetitor.name }) }}
          </h2>
          <div class="header-line"></div>
        </div>

        <div class="advantages-grid">
          <article
            v-for="(adv, index) in competitorAdvantages"
            :key="index"
            class="advantage-card"
          >
            <div class="advantage-badge">
              <i :class="advantageIcon(index)" aria-hidden="true"></i>
            </div>
            <h3>{{ adv.title }}</h3>
            <p>{{ adv.desc }}</p>
          </article>
        </div>
      </section>

      <!-- Section E: Who Should Use Each Platform? -->
      <section class="comparison-section audiences-section" aria-labelledby="section-audiences-title">
        <div class="section-header">
          <h2 id="section-audiences-title">{{ t('comparisons.common.whoShouldUseTitle') }}</h2>
          <div class="header-line"></div>
        </div>

        <div class="audiences-grid">
          <!-- Scemory Audience -->
          <div class="audience-column scemory-audience">
            <div class="audience-column-header">
              <div class="audience-icon-wrap scemory-icon">
                <i class="bi bi-people-fill" aria-hidden="true"></i>
              </div>
              <h3>{{ t('comparisons.common.audienceScemoryHeading') }}</h3>
            </div>
            <p class="audience-text">
              {{ t(`comparisons.${competitorKey}.audienceScemory`) }}
            </p>
          </div>

          <!-- Competitor Audience -->
          <div class="audience-column competitor-audience">
            <div class="audience-column-header">
              <div class="audience-icon-wrap competitor-icon">
                <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
              </div>
              <h3>{{ t('comparisons.common.audienceCompetitorHeading', { competitor: currentCompetitor.name }) }}</h3>
            </div>
            <p class="audience-text">
              {{ t(`comparisons.${competitorKey}.audienceCompetitor`) }}
            </p>
          </div>
        </div>
      </section>

      <!-- Section F: Why Choose Scemory? -->
      <section class="comparison-section why-choose-section" aria-labelledby="section-why-title">
        <div class="section-header">
          <h2 id="section-why-title">{{ t('comparisons.common.whyChooseTitle') }}</h2>
          <div class="header-line"></div>
          <p class="why-choose-summary">
            {{ t(`comparisons.${competitorKey}.whyChooseSummary`) }}
          </p>
        </div>

        <div class="pillars-grid">
          <div class="pillar-card">
            <div class="pillar-icon-box">
              <i class="bi bi-calendar-event" aria-hidden="true"></i>
            </div>
            <h4>Live & Historical Continuum</h4>
            <p>A unified timeline preserving ancient historical archives alongside real-time contemporary cultural and community events.</p>
          </div>

          <div class="pillar-card">
            <div class="pillar-icon-box">
              <i class="bi bi-play-btn" aria-hidden="true"></i>
            </div>
            <h4>Native HD Video & Images</h4>
            <p>End-to-end media processing, watermarked previews, secure originals, and smooth native streaming without third-party dependence.</p>
          </div>

          <div class="pillar-card">
            <div class="pillar-icon-box">
              <i class="bi bi-cash-stack" aria-hidden="true"></i>
            </div>
            <h4>Fair 85% Creator Marketplace</h4>
            <p>Direct media licensing with transparent rights, platform credit wallet, and automated creator payouts via PayPal.</p>
          </div>

          <div class="pillar-card">
            <div class="pillar-icon-box">
              <i class="bi bi-globe2" aria-hidden="true"></i>
            </div>
            <h4>Global 13-Language Inclusion</h4>
            <p>Complete multilingual architecture with full right-to-left layout for Arabic, Persian, and Urdu speaking communities.</p>
          </div>
        </div>
      </section>

      <!-- Section G: Final Call-To-Action (CTA) -->
      <section class="comparison-section cta-section">
        <div class="cta-card">
          <div class="cta-glow" aria-hidden="true"></div>
          <div class="cta-content">
            <h2>{{ t('comparisons.common.ctaTitle') }}</h2>
            <p class="cta-subtitle">{{ t('comparisons.common.ctaSubtitle') }}</p>
            <div class="cta-buttons">
              <RouterLink :to="path('/auth')" class="btn btn-primary cta-btn">
                <i class="bi bi-person-plus-fill" aria-hidden="true"></i>
                {{ t('comparisons.common.createAccount') }}
              </RouterLink>
              <RouterLink :to="path('/home')" class="btn btn-outline-white cta-btn">
                <i class="bi bi-compass-fill" aria-hidden="true"></i>
                {{ t('comparisons.common.exploreScemory') }}
              </RouterLink>
            </div>
          </div>
        </div>
      </section>

      <!-- Section H: Sources, Methodology & Neutrality Disclaimer -->
      <section class="comparison-section methodology-section" aria-labelledby="section-methodology-title">
        <div class="section-header">
          <h2 id="section-methodology-title">{{ t('comparisons.common.methodologyTitle') }}</h2>
          <div class="header-line"></div>
        </div>

        <div class="methodology-card">
          <p class="methodology-desc">{{ t('comparisons.common.methodologyDesc') }}</p>

          <div class="sources-list">
            <h4>Primary Reference Sources:</h4>
            <ul>
              <li>
                <a :href="currentCompetitor.website" target="_blank" rel="noopener noreferrer">
                  {{ currentCompetitor.sourceName }}
                  <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                </a>
                <span class="source-verification">— {{ t('comparisons.common.verifiedDate') }}</span>
              </li>
              <li>
                <span>Scemory Platform Architecture & Feature Specifications — Verified: October 24, 2026</span>
              </li>
            </ul>
          </div>

          <div class="disclaimer-callout" role="note">
            <i class="bi bi-info-circle-fill disclaimer-icon" aria-hidden="true"></i>
            <div class="disclaimer-text">
              <strong>Independent Neutrality Notice:</strong>
              <p>{{ t('comparisons.common.disclaimer') }}</p>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, watchEffect } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { toAssetUrl, backendOrigin, isMobileRuntime } from '../../services/runtimeUrls';
import {
  competitors,
  comparisonFeatures,
  scemoryFeatureStatuses,
  COMPARISON_VERIFICATION_DATE,
  getCompetitor
} from '../../comparisons';

const props = defineProps({
  competitor: {
    type: String,
    default: 'historypin',
  },
});

const route = useRoute();
const router = useRouter();
const { t, tm, rt, locale, availableLocales } = useI18n();

const competitorSlug = computed(() => {
  const raw = String(props.competitor || route.params.competitor || 'historypin').toLowerCase().trim();
  if (raw === 'theclio') return 'the-clio';
  return raw;
});

const currentCompetitor = computed(() => {
  const comp = getCompetitor(competitorSlug.value);
  return comp || competitors['historypin'];
});

const competitorKey = computed(() => {
  if (competitorSlug.value === 'the-clio' || competitorSlug.value === 'theclio') {
    return 'theClio';
  }
  return competitorSlug.value;
});

const direction = computed(() => ['ar', 'fa', 'ur'].includes(locale.value) ? 'rtl' : 'ltr');
const path = suffix => `/${locale.value}${suffix}`;

const competitorAdvantages = computed(() => {
  try {
    const items = tm(`comparisons.${competitorKey.value}.scemoryAdvantages`);
    if (Array.isArray(items)) {
      return items.map(item => ({
        title: rt(item.title),
        desc: rt(item.desc),
      }));
    }
  } catch {
    // fallback if tm fails
  }
  return [];
});

function statusIcon(status) {
  switch (status) {
    case 'supported':
      return 'bi bi-check-circle-fill';
    case 'partial':
      return 'bi bi-dash-circle-fill';
    case 'not_offered':
      return 'bi bi-x-circle-fill';
    default:
      return 'bi bi-question-circle-fill';
  }
}

function advantageIcon(index) {
  const icons = [
    'bi bi-layers-fill',
    'bi bi-camera-reels-fill',
    'bi bi-wallet2',
    'bi bi-translate',
  ];
  return icons[index % icons.length];
}

// SEO & Metadata Management
let previousTitle = '';
const added = [];
const restored = [];

function metadata(selector, tag, attributes) {
  let element = document.head.querySelector(selector);
  if (!element) {
    element = document.createElement(tag);
    document.head.append(element);
    added.push(element);
  } else {
    restored.push([element, Object.fromEntries(attributes ? Object.keys(attributes).map(key => [key, element.getAttribute(key)]) : [])]);
  }
  if (attributes) {
    Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, value));
  }
  return element;
}

onMounted(() => {
  previousTitle = document.title;
  const description = metadata('meta[name="description"]', 'meta', { name: 'description' });
  const canonical = metadata('link[rel="canonical"]', 'link', { rel: 'canonical' });
  const alternates = (availableLocales || ['en', 'ar', 'fr', 'de', 'es', 'it', 'ru', 'tr', 'fa', 'ur', 'hi', 'ja', 'zh']).map(lang => [
    lang,
    metadata(`link[rel="alternate"][hreflang="${lang}"]`, 'link', { rel: 'alternate', hreflang: lang }),
  ]);

  watchEffect(() => {
    const seoTitle = t(`comparisons.${competitorKey.value}.seoTitle`);
    const seoDesc = t(`comparisons.${competitorKey.value}.seoDescription`);

    document.title = `${seoTitle} | Scemory`;
    description.content = seoDesc;

    const origin = isMobileRuntime() ? backendOrigin() : window.location.origin;
    const currentPath = `/${locale.value}/compare/${currentCompetitor.value.slug}`;
    canonical.href = `${origin}${currentPath}`;

    alternates.forEach(([lang, element]) => {
      element.href = `${origin}/${lang}/compare/${currentCompetitor.value.slug}`;
    });
  });
});

onUnmounted(() => {
  if (previousTitle) {
    document.title = previousTitle;
  }
  added.forEach(element => element.remove());
  restored.forEach(([element, attributes]) => {
    [...element.attributes].forEach(attribute => element.removeAttribute(attribute.name));
    attributes.forEach(([key, value]) => element.setAttribute(key, value));
  });
});
</script>

<style scoped>
.scemory-comparison-page {
  background: var(--scemory-surface, #F8FAFC);
  color: var(--scemory-text, #334A62);
  min-height: 100vh;
  padding-block-end: 80px;
}

.scemory-container {
  width: 100%;
  max-width: 1200px;
  margin-inline: auto;
  padding-inline: 20px;
}

/* Breadcrumb */
.breadcrumb-container {
  padding-block: 20px 0;
}
.comparison-breadcrumb {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 0.88rem;
  color: var(--scemory-muted, #64748B);
}
.comparison-breadcrumb a {
  color: var(--scemory-blue, #1677FF);
  text-decoration: none;
}
.comparison-breadcrumb a:hover {
  text-decoration: underline;
}
.breadcrumb-separator {
  color: var(--scemory-border, #CBD5E1);
}
.breadcrumb-current {
  color: var(--scemory-heading, #06142A);
  font-weight: 600;
}

/* Section A: Hero Banner */
.comparison-hero {
  padding-block: 36px 48px;
  background: radial-gradient(circle at 50% 10%, rgba(22, 119, 255, 0.08), transparent 70%),
              linear-gradient(180deg, var(--scemory-surface-soft, #EDF4FA) 0%, var(--scemory-surface, #F8FAFC) 100%);
  border-block-end: 1px solid var(--scemory-border, #E2E8F0);
  text-align: center;
}
.hero-container {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.hero-logos-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 24px;
  margin-block-end: 28px;
}
.brand-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 16px 24px;
  background: #FFFFFF;
  border: 1px solid var(--scemory-border, #E2E8F0);
  border-radius: 16px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
  width: 130px;
  transition: transform 0.2s ease;
}
.brand-card:hover {
  transform: translateY(-2px);
}
.brand-logo {
  object-fit: contain;
  width: 68px;
  height: 68px;
}
.brand-name {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--scemory-heading, #06142A);
}
.vs-badge-container {
  display: grid;
  place-items: center;
}
.vs-badge {
  display: inline-block;
  padding: 6px 14px;
  font-size: 0.82rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 1px;
  background: var(--scemory-blue, #1677FF);
  color: #FFFFFF;
  border-radius: 20px;
  box-shadow: 0 2px 8px rgba(22, 119, 255, 0.3);
}

.hero-title {
  font-size: clamp(1.8rem, 3.2vw, 2.6rem);
  font-weight: 800;
  color: var(--scemory-heading, #06142A);
  line-height: 1.25;
  max-width: 900px;
  margin-block: 0 16px;
}
.hero-subtitle {
  font-size: clamp(1rem, 1.4vw, 1.2rem);
  color: var(--scemory-muted, #64748B);
  line-height: 1.6;
  max-width: 780px;
  margin-block: 0 20px;
}
.verification-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #059669;
  background: #ECFDF5;
  border: 1px solid #A7F3D0;
  padding: 6px 14px;
  border-radius: 20px;
  margin-block-end: 28px;
}
.hero-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 12px;
}
.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 22px;
  font-size: 0.95rem;
  font-weight: 600;
  border-radius: 10px;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-primary {
  background: var(--scemory-blue, #1677FF);
  color: #FFFFFF;
  border: 1px solid var(--scemory-blue, #1677FF);
}
.btn-primary:hover {
  background: #0d62d9;
  border-color: #0d62d9;
}
.btn-outline {
  background: #FFFFFF;
  color: var(--scemory-blue, #1677FF);
  border: 1px solid var(--scemory-border, #CBD5E1);
}
.btn-outline:hover {
  border-color: var(--scemory-blue, #1677FF);
  background: rgba(22, 119, 255, 0.04);
}
.btn-subtle {
  background: transparent;
  color: var(--scemory-muted, #64748B);
  border: 1px solid transparent;
}
.btn-subtle:hover {
  color: var(--scemory-heading, #06142A);
  background: rgba(0, 0, 0, 0.04);
}

/* Main Content & Section Structure */
.comparison-main {
  margin-block-start: 48px;
}
.comparison-section {
  margin-block-end: 64px;
}
.section-header {
  text-align: center;
  margin-block-end: 36px;
}
.section-header h2 {
  font-size: clamp(1.4rem, 2.2vw, 1.85rem);
  font-weight: 750;
  color: var(--scemory-heading, #06142A);
  margin-block: 0 10px;
}
.header-line {
  width: 44px;
  height: 3.5px;
  border-radius: 4px;
  background: var(--scemory-blue, #1677FF);
  margin-inline: auto;
  margin-block: 10px 14px;
}
.section-subtitle {
  font-size: 0.95rem;
  color: var(--scemory-muted, #64748B);
  max-width: 680px;
  margin-inline: auto;
  margin-block: 0;
}

/* Section B: Platform Overviews */
.overviews-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 28px;
}
.overview-card {
  background: #FFFFFF;
  border: 1px solid var(--scemory-border, #E2E8F0);
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
  display: flex;
  flex-direction: column;
}
.overview-card-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-block-end: 18px;
}
.overview-logo {
  object-fit: contain;
  border-radius: 8px;
}
.overview-card-header h3 {
  font-size: 1.3rem;
  font-weight: 750;
  color: var(--scemory-heading, #06142A);
  margin: 0;
}
.overview-tagline {
  font-size: 0.85rem;
  color: var(--scemory-muted, #64748B);
}
.competitor-external-link {
  font-size: 0.85rem;
  color: var(--scemory-blue, #1677FF);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.competitor-external-link:hover {
  text-decoration: underline;
}
.overview-description {
  font-size: 0.96rem;
  line-height: 1.7;
  color: var(--scemory-text, #334A62);
  margin-block: 0 24px;
}
.overview-metadata {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 12px;
  border-block-start: 1px solid var(--scemory-border, #F1F5F9);
  padding-block-start: 18px;
  font-size: 0.9rem;
}
.overview-metadata li {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.overview-metadata strong {
  color: var(--scemory-heading, #06142A);
  font-size: 0.82rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Section C: Comparison Table */
.status-legend {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 14px;
  margin-block-end: 24px;
}
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.82rem;
  font-weight: 600;
  line-height: 1.4;
  white-space: nowrap;
}
.status-supported {
  background: #ECFDF5;
  color: #065F46;
  border: 1px solid #A7F3D0;
}
.status-partial {
  background: #FFFBEB;
  color: #92400E;
  border: 1px solid #FDE68A;
}
.status-not_offered {
  background: #F8FAFC;
  color: #64748B;
  border: 1px solid #E2E8F0;
}
.status-not_documented {
  background: #F1F5F9;
  color: #475569;
  border: 1px solid #CBD5E1;
}

.table-responsive-wrapper {
  background: #FFFFFF;
  border: 1px solid var(--scemory-border, #E2E8F0);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}
.comparison-table {
  width: 100%;
  border-collapse: collapse;
  text-align: start;
}
.comparison-table th {
  padding: 18px 24px;
  background: var(--scemory-surface-soft, #F8FAFC);
  border-block-end: 2px solid var(--scemory-border, #E2E8F0);
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--scemory-heading, #06142A);
}
.th-brand {
  display: flex;
  align-items: center;
  gap: 10px;
}
.th-feature {
  width: 48%;
}
.th-scemory, .th-competitor {
  width: 26%;
}
.comparison-table td {
  padding: 18px 24px;
  border-block-end: 1px solid var(--scemory-border, #F1F5F9);
  vertical-align: middle;
}
.comparison-table tr:hover td {
  background: rgba(22, 119, 255, 0.015);
}
.feature-name {
  display: block;
  font-weight: 650;
  font-size: 0.96rem;
  color: var(--scemory-heading, #06142A);
  margin-block-end: 4px;
}
.feature-desc {
  display: block;
  font-size: 0.84rem;
  color: var(--scemory-muted, #64748B);
  line-height: 1.5;
}

/* Mobile Feature Cards (Hidden on desktop) */
.mobile-feature-cards {
  display: none;
  gap: 16px;
}
.mobile-feature-card {
  background: #FFFFFF;
  border: 1px solid var(--scemory-border, #E2E8F0);
  border-radius: 14px;
  padding: 18px;
}
.mobile-feature-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--scemory-heading, #06142A);
  margin-block: 0 4px;
}
.mobile-feature-desc {
  font-size: 0.84rem;
  color: var(--scemory-muted, #64748B);
  margin-block: 0 14px;
}
.mobile-feature-rows {
  display: grid;
  gap: 10px;
  border-block-start: 1px solid #F1F5F9;
  padding-block-start: 12px;
}
.mobile-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.mobile-brand-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.88rem;
}

/* Section D: Advantages */
.advantages-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 24px;
}
.advantage-card {
  background: #FFFFFF;
  border: 1px solid var(--scemory-border, #E2E8F0);
  border-radius: 16px;
  padding: 28px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
  position: relative;
  transition: transform 0.2s ease;
}
.advantage-card:hover {
  transform: translateY(-2px);
}
.advantage-badge {
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  background: rgba(22, 119, 255, 0.1);
  color: var(--scemory-blue, #1677FF);
  border-radius: 12px;
  font-size: 1.25rem;
  margin-block-end: 18px;
}
.advantage-card h3 {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--scemory-heading, #06142A);
  margin-block: 0 10px;
}
.advantage-card p {
  font-size: 0.94rem;
  line-height: 1.65;
  color: var(--scemory-text, #334A62);
  margin: 0;
}

/* Section E: Audiences */
.audiences-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 28px;
}
.audience-column {
  background: #FFFFFF;
  border: 1px solid var(--scemory-border, #E2E8F0);
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
}
.audience-column-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-block-end: 18px;
}
.audience-icon-wrap {
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  font-size: 1.3rem;
}
.scemory-icon {
  background: rgba(22, 119, 255, 0.1);
  color: var(--scemory-blue, #1677FF);
}
.competitor-icon {
  background: rgba(100, 116, 139, 0.1);
  color: #475569;
}
.audience-column-header h3 {
  font-size: 1.2rem;
  font-weight: 750;
  color: var(--scemory-heading, #06142A);
  margin: 0;
}
.audience-text {
  font-size: 0.96rem;
  line-height: 1.7;
  color: var(--scemory-text, #334A62);
  margin: 0;
}

/* Section F: Why Choose Scemory */
.why-choose-summary {
  font-size: 1.05rem;
  color: var(--scemory-text, #334A62);
  line-height: 1.7;
  max-width: 820px;
  margin-inline: auto;
  margin-block: 16px 0;
}
.pillars-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
}
.pillar-card {
  background: #FFFFFF;
  border: 1px solid var(--scemory-border, #E2E8F0);
  border-radius: 14px;
  padding: 24px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
  text-align: start;
}
.pillar-icon-box {
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  background: rgba(22, 119, 255, 0.08);
  color: var(--scemory-blue, #1677FF);
  border-radius: 10px;
  font-size: 1.2rem;
  margin-block-end: 16px;
}
.pillar-card h4 {
  font-size: 1rem;
  font-weight: 700;
  color: var(--scemory-heading, #06142A);
  margin-block: 0 8px;
}
.pillar-card p {
  font-size: 0.88rem;
  line-height: 1.6;
  color: var(--scemory-muted, #64748B);
  margin: 0;
}

/* Section G: CTA Banner */
.cta-card {
  position: relative;
  background: linear-gradient(135deg, #06142A 0%, #0D3568 100%);
  border-radius: 20px;
  padding: clamp(36px, 5vw, 64px) 24px;
  text-align: center;
  color: #FFFFFF;
  overflow: hidden;
  box-shadow: 0 12px 32px rgba(6, 20, 42, 0.15);
}
.cta-glow {
  position: absolute;
  top: -50%;
  left: 50%;
  transform: translateX(-50%);
  width: 80%;
  height: 100%;
  background: radial-gradient(circle, rgba(22, 119, 255, 0.35) 0%, transparent 70%);
  pointer-events: none;
}
.cta-content {
  position: relative;
  z-index: 1;
  max-width: 760px;
  margin-inline: auto;
}
.cta-card h2 {
  font-size: clamp(1.6rem, 2.6vw, 2.2rem);
  font-weight: 800;
  color: #FFFFFF;
  margin-block: 0 14px;
  line-height: 1.3;
}
.cta-subtitle {
  font-size: clamp(0.95rem, 1.2vw, 1.1rem);
  color: #CBD5E1;
  line-height: 1.65;
  margin-block: 0 28px;
}
.cta-buttons {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 16px;
}
.cta-btn {
  padding: 13px 26px;
  font-size: 1rem;
}
.btn-outline-white {
  background: transparent;
  color: #FFFFFF;
  border: 1px solid rgba(255, 255, 255, 0.4);
}
.btn-outline-white:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: #FFFFFF;
}

/* Section H: Sources & Methodology */
.methodology-card {
  background: #FFFFFF;
  border: 1px solid var(--scemory-border, #E2E8F0);
  border-radius: 16px;
  padding: 32px;
}
.methodology-desc {
  font-size: 0.95rem;
  line-height: 1.7;
  color: var(--scemory-text, #334A62);
  margin-block: 0 20px;
}
.sources-list {
  border-block-start: 1px solid #F1F5F9;
  padding-block: 16px 20px;
}
.sources-list h4 {
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--scemory-heading, #06142A);
  margin-block: 0 10px;
}
.sources-list ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 8px;
  font-size: 0.88rem;
}
.sources-list a {
  color: var(--scemory-blue, #1677FF);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.sources-list a:hover {
  text-decoration: underline;
}
.source-verification {
  color: var(--scemory-muted, #64748B);
  margin-inline-start: 6px;
}

.disclaimer-callout {
  display: flex;
  gap: 14px;
  align-items: flex-start;
  background: var(--scemory-surface-soft, #F8FAFC);
  border: 1px solid var(--scemory-border, #E2E8F0);
  border-inline-start: 4px solid var(--scemory-blue, #1677FF);
  border-radius: 10px;
  padding: 16px 20px;
  margin-block-start: 12px;
}
.disclaimer-icon {
  font-size: 1.25rem;
  color: var(--scemory-blue, #1677FF);
  margin-block-start: 2px;
}
.disclaimer-text strong {
  display: block;
  font-size: 0.9rem;
  color: var(--scemory-heading, #06142A);
  margin-block-end: 4px;
}
.disclaimer-text p {
  font-size: 0.85rem;
  line-height: 1.6;
  color: var(--scemory-muted, #64748B);
  margin: 0;
}

/* Responsive Breakpoints */
@media (max-width: 1024px) {
  .pillars-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .overviews-grid,
  .advantages-grid,
  .audiences-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .table-responsive-wrapper {
    display: none;
  }
  .mobile-feature-cards {
    display: grid;
  }
  .brand-card {
    padding: 12px 16px;
    width: 110px;
  }
  .brand-logo {
    width: 52px;
    height: 52px;
  }
}
</style>
