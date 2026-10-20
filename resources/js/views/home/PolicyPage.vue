<template>
  <div class="scemory-page legal-page policy-page" :dir="direction">
    <div class="scemory-container policy-shell">
      <nav class="policy-breadcrumb" :aria-label="t('policies.ui.breadcrumb')">
        <RouterLink :to="path('/home')">{{ t('nav.home') }}</RouterLink>
        <span aria-hidden="true">/</span>
        <RouterLink :to="path('/policies')">{{ t('policies.ui.legal') }}</RouterLink>
      </nav>
      <header class="scemory-page-header policy-header">
        <img :src="toAssetUrl('/images/logo.png')" :alt="t('footer.logoAlt')" width="72" height="72" />
        <div>
          <h1>{{ policy ? content.title : t('policies.ui.legal') }}</h1>
          <p>{{ policy ? content.description : t('policies.ui.introduction') }}</p>
          <p v-if="policy" class="policy-date">{{ t('policies.ui.effectiveDate') }}:
            <time v-if="approvedPolicyDate" :datetime="approvedPolicyDate">{{ d(new Date(`${approvedPolicyDate}T00:00:00Z`), { timeZone: 'UTC' }) }}</time>
            <span v-else>{{ t('policies.ui.pending') }}</span>
          </p>
        </div>
      </header>
      <p v-if="!approvedPolicyDate" class="policy-review" role="note">{{ t('policies.ui.draft') }}</p>
      <div v-if="policy" class="policy-layout">
        <nav class="policy-toc" :aria-label="t('policies.ui.contents')">
          <h2>{{ t('policies.ui.contents') }}</h2>
          <a v-for="(section, index) in content.sections" :key="index" :href="`#policy-section-${index}`">{{ section.title }}</a>
        </nav>
        <article class="policy-article" :aria-label="content.title" :key="policy">
          <section v-for="(section, index) in content.sections" :key="index" :id="`policy-section-${index}`" tabindex="-1">
            <h2>{{ section.title }}</h2>
            <p>{{ section.body }}</p>
          </section>
          <RouterLink class="policy-contact" :to="{ path: path('/contact'), query: { topic: policy === 'reporting' ? 'copyright' : 'privacy' === policy || 'dataProtection' === policy ? 'privacy' : 'legal' } }">
            {{ t('policies.ui.contact') }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
          </RouterLink>
        </article>
      </div>
      <nav class="policy-related" :aria-label="t('policies.ui.related')">
        <h2>{{ t('policies.ui.related') }}</h2>
        <div class="policy-cards">
          <RouterLink v-for="item in related" :key="item.id" :to="path(item.path)">
            <h3>{{ t(`policies.pages.${item.id}.title`) }}</h3>
            <p>{{ t(`policies.pages.${item.id}.description`) }}</p>
          </RouterLink>
        </div>
      </nav>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, watchEffect } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { policies, approvedPolicyDate } from '../../policies';
import { toAssetUrl, isMobileRuntime, backendOrigin } from '../../services/runtimeUrls';

const props = defineProps({ policy: { type: String, default: null } });
const route = useRoute();
const { t, tm, rt, locale, availableLocales, d } = useI18n();
const path = suffix => `/${locale.value}${suffix}`;
const direction = computed(() => ['ar', 'fa', 'ur'].includes(locale.value) ? 'rtl' : 'ltr');
const related = computed(() => policies.filter(item => item.id !== props.policy));
const content = computed(() => {
  if (!props.policy) return null;
  const page = tm(`policies.pages.${props.policy}`);
  return { title: rt(page.title), description: rt(page.description), sections: page.sections.map(section => ({ title: rt(section.title), body: rt(section.body) })) };
});

// Restore previous metadata on leaving this view, including existing canonical tags.
let previousTitle;
const added = [];
const restored = [];
function metadata(selector, tag, attributes) {
  let element = document.head.querySelector(selector);
  if (element) restored.push([element, [...element.attributes].map(a => [a.name, a.value])]);
  else { element = document.createElement(tag); document.head.append(element); added.push(element); }
  Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, value));
  return element;
}
onMounted(() => {
  // Wait until the previous route has released its head elements before claiming them.
  previousTitle = document.title;
  const description = metadata('meta[name="description"]', 'meta', { name: 'description' });
  const robots = metadata('meta[name="robots"]', 'meta', { name: 'robots' });
  const canonical = metadata('link[rel="canonical"]', 'link', { rel: 'canonical' });
  const alternates = availableLocales.map(lang => [lang, metadata(`link[rel="alternate"][hreflang="${lang}"]`, 'link', { rel: 'alternate', hreflang: lang })]);
  watchEffect(() => {
    document.title = `${content.value?.title || t('policies.ui.legal')} | Scemory`;
    description.content = content.value?.description || t('policies.ui.introduction');
    robots.content = approvedPolicyDate ? 'index, follow' : 'noindex, follow';
    const origin = isMobileRuntime() ? backendOrigin() : window.location.origin;
    canonical.href = `${origin}${route.path}`;
    alternates.forEach(([lang, element]) => { element.href = `${origin}${route.path.replace(/^\/[^/]+/, `/${lang}`)}`; });
  });
});
onUnmounted(() => {
  document.title = previousTitle;
  added.forEach(element => element.remove());
  restored.forEach(([element, attributes]) => {
    [...element.attributes].forEach(attribute => element.removeAttribute(attribute.name));
    attributes.forEach(([key, value]) => element.setAttribute(key, value));
  });
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
.policy-date { font-size: .9rem; }
.policy-review { margin-block: 24px; border-inline-start: 3px solid var(--scemory-primary); padding: 16px 20px; background: var(--scemory-active); border-radius: 8px; line-height: 1.8; }
.policy-layout { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(0, 3fr); gap: 32px; align-items: start; }
.policy-toc { position: sticky; top: 24px; display: grid; gap: 8px; }
.policy-toc h2, .policy-related > h2 { font-size: 1.15rem; margin-block-end: 12px; }
.policy-toc a { padding: 8px 12px; border-inline-start: 2px solid var(--scemory-border); text-decoration: none; overflow-wrap: anywhere; }
.policy-article { padding: clamp(20px, 4vw, 40px); background: var(--scemory-surface); border: 1px solid var(--scemory-border-soft); border-radius: var(--scemory-radius); }
.policy-article section { scroll-margin-top: 32px; padding-block: 24px; border-block-end: 1px solid var(--scemory-border-soft); }
.policy-article section:first-child { padding-block-start: 0; }
.policy-article h2 { font-size: 1.25rem; line-height: 1.5; margin-block-end: 12px; }
.policy-article p { line-height: 1.95; max-width: 75ch; margin: 0; overflow-wrap: anywhere; }
.policy-contact { display: inline-flex; align-items: center; gap: 12px; margin-block-start: 24px; font-weight: 700; }
.policy-related { margin-block-start: 40px; }
.policy-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.policy-cards > a { padding: 22px; border: 1px solid var(--scemory-border); border-radius: 16px; text-decoration: none; background: var(--scemory-surface); }
.policy-cards h3 { font-size: 1rem; line-height: 1.6; margin-block-end: 8px; }
.policy-cards p { font-size: .9rem; line-height: 1.8; margin: 0; }
a:focus-visible, section:focus { outline: 3px solid var(--scemory-primary); outline-offset: 4px; }
@media (max-width: 767px) { .policy-layout { grid-template-columns: minmax(0, 1fr); } .policy-toc { position: static; } .policy-cards { grid-template-columns: minmax(0, 1fr); } .policy-header { flex-direction: column; gap: 16px; } }
@media (prefers-reduced-motion: reduce) { a { transition: none !important; } }
</style>
