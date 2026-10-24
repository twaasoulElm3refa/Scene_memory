<template>
  <footer class="scemory-footer" :dir="direction">
    <div class="scemory-container">
      <div class="footer-grid">
        <nav aria-labelledby="footer-quick-title">
          <h2 id="footer-quick-title">{{ t('footer.quickLinks') }}</h2>
          <ul class="footer-list quick-links">
            <li v-for="link in quickLinks" :key="link.path + (link.action || '')">
              <RouterLink :to="destination(link)">{{ t(link.labelKey) }}</RouterLink>
            </li>
          </ul>
        </nav>
        <section aria-labelledby="footer-audience-title">
          <h2 id="footer-audience-title">{{ t('policies.ui.audiencesTitle') }}</h2>
          <p class="footer-description">{{ t('policies.ui.audiencesDescription') }}</p>
          <ul class="footer-list audience-list">
            <li v-for="(audience, index) in tm('policies.audiences')" :key="index">{{ rt(audience) }}</li>
          </ul>
        </section>
        <nav aria-labelledby="footer-comparisons-title">
          <h2 id="footer-comparisons-title">{{ t('comparisons.footerTitle') }}</h2>
          <ul class="footer-list">
            <li><RouterLink :to="path('/compare/historypin')">{{ t('comparisons.links.historypin') }}</RouterLink></li>
            <li><RouterLink :to="path('/compare/pastvu')">{{ t('comparisons.links.pastvu') }}</RouterLink></li>
            <li><RouterLink :to="path('/compare/the-clio')">{{ t('comparisons.links.theClio') }}</RouterLink></li>
          </ul>
        </nav>
        <nav aria-labelledby="footer-support-title">
          <h2 id="footer-support-title">{{ t('footer.supportContact') }}</h2>
          <ul class="footer-list">
            <li><RouterLink :to="path('/who')">{{ t('nav.about') }}</RouterLink></li>
            <li><RouterLink :to="path('/contact')">{{ t('nav.contact') }}</RouterLink></li>
            <li><RouterLink :to="path('/policies')">{{ t('policies.ui.legal') }}</RouterLink></li>
            <li><RouterLink :to="path('/data-deletion')">{{ t('footer.dataDeletion') }}</RouterLink></li>
            <li v-for="policy in policies" :key="policy.id"><RouterLink :to="path(policy.path)">{{ t('policies.pages.' + policy.id + '.title') }}</RouterLink></li>
          </ul>
        </nav>
        <section class="footer-brand" aria-labelledby="footer-brand-title">
          <RouterLink :to="path('/home')" :aria-label="t('nav.scemoryHome')">
            <img :src="toAssetUrl('/images/logo.png')" :alt="t('footer.logoAlt')" width="112" height="112" class="footer-logo" loading="lazy" />
          </RouterLink>
          <h2 id="footer-brand-title">Scemory <small>(Scene Memory)</small></h2>
          <p class="footer-description">{{ t('policies.ui.brandDescription') }}</p>
          <div class="footer-socials">
            <component :is="social.href ? 'a' : 'span'" v-for="social in socials" :key="social.key"
              :role="social.href ? undefined : 'img'"
              :href="social.href || undefined" :target="social.href ? '_blank' : undefined"
              :rel="social.href ? 'noopener noreferrer' : undefined" :aria-disabled="!social.href || undefined"
              :aria-label="social.label + ' — ' + t(social.href ? 'policies.ui.external' : 'policies.ui.unavailable')"
              :title="t(social.href ? 'policies.ui.external' : 'policies.ui.unavailable')" class="footer-social">
              <i :class="'bi bi-' + social.icon" aria-hidden="true"></i>
            </component>
          </div>
          <div class="footer-stores">
            <component :is="store.href ? 'a' : 'span'" v-for="store in stores" :key="store.key"
              :role="store.href ? undefined : 'group'"
              :href="store.href || undefined" :target="store.href ? '_blank' : undefined"
              :rel="store.href ? 'noopener noreferrer' : undefined" :aria-disabled="!store.href || undefined"
              :aria-label="store.label + ' — ' + t(store.href ? 'policies.ui.external' : 'policies.ui.unavailable')"
              class="footer-store">
              <i :class="'bi bi-' + store.icon" aria-hidden="true"></i>
              <span><small>{{ t(store.href ? 'policies.ui.download' : 'policies.ui.unavailable') }}</small><strong>{{ store.label }}</strong></span>
            </component>
          </div>
        </section>
      </div>
      <div class="footer-bottom"><p>{{ t('policies.ui.copyright', { year: currentYear }) }}</p><RouterLink :to="path('/copyright-policy')">{{ t('policies.ui.contentOwnership') }}</RouterLink></div>
    </div>
  </footer>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { toAssetUrl } from '../../services/runtimeUrls';
import { getAuthToken } from '../../services/authTokenStorage';
import { FooterService } from '../../services/FooterService/FooterService';
import { footerNavigation, navigationSession, specialCoverageDestination } from '../../navigation';
import { policies, verifiedFooterUrl } from '../../policies';
const { t, tm, rt, locale } = useI18n();
const route = useRoute();
const currentYear = new Date().getFullYear();
const direction = computed(() => ['ar', 'fa', 'ur'].includes(locale.value) ? 'rtl' : 'ltr');
const path = suffix => '/' + locale.value + suffix;
const comparisonPaths = ['/compare/historypin', '/compare/pastvu', '/compare/the-clio'];
const supportPaths = new Set(['/who', '/contact', '/data-deletion', ...policies.map(policy => policy.path), ...comparisonPaths]);
const quickLinks = computed(() => footerNavigation(navigationSession).filter(link => !supportPaths.has(link.path)));
const destination = link => link.action === 'specialCoverage'
  ? specialCoverageDestination(locale.value, Boolean(getAuthToken()), route.path === path('/home') ? route.query : {})
  : path(link.path);
const settings = ref({});
const socials = computed(() => [
  { key: 'facebook', label: 'Facebook', icon: 'facebook' },
  { key: 'twitter', label: 'X', icon: 'twitter-x' },
  { key: 'instagram', label: 'Instagram', icon: 'instagram' },
].map(item => ({ ...item, href: verifiedFooterUrl(settings.value[item.key], item.key) })));
const stores = computed(() => [
  { key: 'google_play', label: 'Google Play', icon: 'google-play' },
  { key: 'app_store', label: 'App Store', icon: 'apple' },
].map(item => ({ ...item, href: verifiedFooterUrl(settings.value[item.key], item.key) })));
onMounted(async () => {
  try { const response = await FooterService.links(); settings.value = response.data?.data || {}; }
  catch { /* Optional destinations stay visibly unavailable. */ }
});
</script>

<style scoped>
.scemory-footer { border-block-start: 1px solid var(--scemory-border, #C9DDEF); background: radial-gradient(circle at 90% 15%, rgba(48,168,255,.08), transparent 28rem), linear-gradient(145deg, var(--scemory-surface-soft, #EDF4FA), var(--scemory-surface, #F7FAFD)); color: var(--scemory-text, #334A62); padding-block: 48px 20px; }
.footer-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)) minmax(0, 1.15fr); gap: clamp(20px, 2.5vw, 36px); align-items: start; }
.footer-grid > * { min-width: 0; }
h2 { color: var(--scemory-heading, #06142A); font-size: 1rem; font-weight: 750; line-height: 1.6; margin-block: 0 22px; }
nav h2::after, section:not(.footer-brand) h2::after { content: ''; display: block; width: 32px; height: 3px; border-radius: 10px; background: var(--scemory-blue, #1677FF); margin-block-start: 10px; }
.scemory-footer .footer-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 9px; }
.footer-list li { font-size: .9rem; line-height: 1.65; overflow-wrap: anywhere; }
.footer-list a { display: inline-block; padding-block: 3px; }
a { color: var(--scemory-text, #334A62); text-decoration: none; }
a:hover { color: var(--scemory-primary, #0D4D97); text-decoration: underline; text-underline-offset: 4px; }
a:focus-visible { outline: 3px solid var(--scemory-primary, #0D4D97); outline-offset: 4px; border-radius: 4px; }
.footer-description { color: var(--scemory-muted, #64748B); font-size: .88rem; line-height: 1.8; margin-block: 0 16px; }
.audience-list li { position: relative; padding-inline-start: 12px; }
.audience-list li::before { content: ''; position: absolute; inset-inline-start: 0; top: .7em; width: 4px; height: 4px; background: var(--scemory-primary, #0D4D97); border-radius: 50%; }
.footer-brand { text-align: start; }
.footer-logo { display: block; border-radius: 50%; border: 1px solid var(--scemory-border); box-shadow: var(--scemory-shadow-sm); margin-block-end: 18px; }
.footer-brand h2 { margin-block-end: 10px; }
.footer-brand h2 small { display: block; font-size: .8rem; font-weight: 500; color: var(--scemory-muted); }
.footer-socials { display: flex; gap: 10px; margin-block: 18px; }
.footer-social { width: 40px; height: 40px; display: inline-grid; place-items: center; border: 1px solid var(--scemory-border); border-radius: 50%; color: var(--scemory-primary); background: var(--scemory-control); }
[aria-disabled="true"] { opacity: .7; }
.footer-stores { display: grid; gap: 10px; }
.footer-store { border: 1px solid var(--scemory-border); border-radius: 12px; padding: 10px 14px; display: flex; gap: 12px; align-items: center; background: var(--scemory-control); width: fit-content; max-width: 100%; }
.footer-store > i { font-size: 1.6rem; }
.footer-store small, .footer-store strong { display: block; line-height: 1.5; }
.footer-store small { font-size: .7rem; }
.footer-store strong { font-size: .95rem; }
.footer-bottom { margin-block-start: 36px; padding-block-start: 20px; border-block-start: 1px solid var(--scemory-border); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: .8rem; line-height: 1.8; }
.footer-bottom p { margin: 0; }
@media (max-width: 1200px) { .footer-grid { grid-template-columns: repeat(3, minmax(0,1fr)); gap: 32px; } }
@media (max-width: 800px) { .footer-grid { grid-template-columns: repeat(2, minmax(0,1fr)); gap: 28px; } }
@media (max-width: 575px) { .scemory-footer { padding-block-start: 32px; } .footer-grid { grid-template-columns: minmax(0,1fr); gap: 32px; } .quick-links { grid-template-columns: repeat(2, minmax(0,1fr)); column-gap: 16px; } .footer-stores { display: flex; flex-wrap: wrap; } .footer-logo { width: 96px; height: 96px; } .footer-bottom { margin-block-start: 28px; } }
</style>
