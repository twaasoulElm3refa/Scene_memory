// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';
import siteRouter from '../../resources/js/router';
import Footer from '../../resources/js/components/layouts/footer.vue';
import ComparisonPage from '../../resources/js/views/home/ComparisonPage.vue';
import { FooterService } from '../../resources/js/services/FooterService/FooterService';
import translations from '../../resources/js/i18n/policies';
import { comparisonFeatures } from '../../resources/js/comparisons';

vi.mock('../../resources/js/services/FooterService/FooterService', () => ({
  FooterService: { links: vi.fn() },
}));

const existing = import.meta.glob('../../resources/js/i18n/*.json', { eager: true, import: 'default' });
const messages = Object.fromEntries(
  Object.entries(existing).map(([file, value]) => {
    const lang = file.split('/').pop().replace('.json', '');
    return [lang, { ...value, policies: translations[lang] }];
  })
);

const locales = ['en', 'ar', 'fr', 'de', 'ru', 'es', 'it', 'hi', 'ja', 'fa', 'zh', 'ur', 'tr'];

let app;
const settle = async () => {
  for (let i = 0; i < 12; i++) await Promise.resolve();
  await nextTick();
};

async function mountComponent(component, props = {}, lang = 'en', url = `/${lang}/compare/historypin`) {
  const target = document.createElement('div');
  document.body.append(target);
  const router = createRouter({ history: createMemoryHistory(), routes: siteRouter.options.routes });
  const i18n = createI18n({ legacy: false, locale: lang, fallbackLocale: false, messages });
  await router.push(url);
  app = createApp(component, props);
  app.use(router);
  app.use(i18n);
  app.mount(target);
  await settle();
  return { target, router, i18n };
}

describe('Competitor Comparison Pages & Footer Integration', () => {
  beforeEach(() => {
    localStorage.clear();
    FooterService.links.mockResolvedValue({ data: { data: {} } });
  });

  afterEach(() => {
    if (app) {
      app.unmount();
      app = null;
    }
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  describe('Footer Navigation Integration', () => {
    it('renders Comparisons section with exactly 3 links in English', async () => {
      const { target } = await mountComponent(Footer, {}, 'en');
      const comparisonsNav = target.querySelector('nav[aria-labelledby="footer-comparisons-title"]');
      expect(comparisonsNav).not.toBeNull();

      const heading = comparisonsNav.querySelector('h2');
      expect(heading.textContent).toBe('Comparisons');

      const links = comparisonsNav.querySelectorAll('ul li a');
      expect(links.length).toBe(3);

      expect(links[0].getAttribute('href')).toBe('/en/compare/historypin');
      expect(links[0].textContent).toBe('Scemory vs Historypin');

      expect(links[1].getAttribute('href')).toBe('/en/compare/pastvu');
      expect(links[1].textContent).toBe('Scemory vs PastVu');

      expect(links[2].getAttribute('href')).toBe('/en/compare/the-clio');
      expect(links[2].textContent).toBe('Scemory vs The Clio');
    });

    it('renders Comparisons section with Arabic localization and RTL', async () => {
      const { target } = await mountComponent(Footer, {}, 'ar');
      const comparisonsNav = target.querySelector('nav[aria-labelledby="footer-comparisons-title"]');
      expect(comparisonsNav).not.toBeNull();

      const heading = comparisonsNav.querySelector('h2');
      expect(heading.textContent).toBe('المقارنات');

      const links = comparisonsNav.querySelectorAll('ul li a');
      expect(links.length).toBe(3);
      expect(links[0].getAttribute('href')).toBe('/ar/compare/historypin');
      expect(links[1].getAttribute('href')).toBe('/ar/compare/pastvu');
      expect(links[2].getAttribute('href')).toBe('/ar/compare/the-clio');
    });

    it.each(locales)('renders valid comparisons links without missing keys in %s', async lang => {
      const { target } = await mountComponent(Footer, {}, lang);
      const comparisonsNav = target.querySelector('nav[aria-labelledby="footer-comparisons-title"]');
      expect(comparisonsNav).not.toBeNull();
      expect(comparisonsNav.textContent).not.toMatch(/comparisons\./);
    });
  });

  describe('ComparisonPage View Mounting & Structure', () => {
    it.each(['historypin', 'pastvu', 'the-clio'])(
      'mounts successfully for %s with all 8 sections and logos',
      async competitorSlug => {
        const { target } = await mountComponent(
          ComparisonPage,
          { competitor: competitorSlug },
          'en',
          `/en/compare/${competitorSlug}`
        );

        // Section A: Hero
        const hero = target.querySelector('.comparison-hero');
        expect(hero).not.toBeNull();
        expect(hero.querySelector('h1')).not.toBeNull();
        expect(hero.querySelector('.brand-card.scemory-brand-card img').getAttribute('src')).toContain('/images/logo.png');
        expect(hero.querySelector('.brand-card.competitor-brand-card img').getAttribute('src')).toBeTruthy();
        expect(hero.querySelector('.verification-pill').textContent).toContain('October 24, 2026');

        // Section B: Overviews
        const overviews = target.querySelector('.overviews-section');
        expect(overviews).not.toBeNull();
        expect(overviews.querySelectorAll('.overview-card').length).toBe(2);

        // Section C: 19-Feature Comparison Table
        const table = target.querySelector('.comparison-table');
        expect(table).not.toBeNull();
        const rows = table.querySelectorAll('tbody tr');
        expect(rows.length).toBe(19);
        expect(rows.length).toBe(comparisonFeatures.length);

        // Section D: What Scemory Offers Beyond Competitor
        const beyond = target.querySelector('.beyond-section');
        expect(beyond).not.toBeNull();
        expect(beyond.querySelectorAll('.advantage-card').length).toBe(4);

        // Section E: Who Should Use Each Platform?
        const audiences = target.querySelector('.audiences-section');
        expect(audiences).not.toBeNull();
        expect(audiences.querySelectorAll('.audience-column').length).toBe(2);

        // Section F: Why Choose Scemory?
        const whyChoose = target.querySelector('.why-choose-section');
        expect(whyChoose).not.toBeNull();
        expect(whyChoose.querySelectorAll('.pillar-card').length).toBe(4);

        // Section G: Final CTA
        const cta = target.querySelector('.cta-section');
        expect(cta).not.toBeNull();
        expect(cta.querySelector('a[href="/en/auth"]')).not.toBeNull();
        expect(cta.querySelector('a[href="/en/home"]')).not.toBeNull();

        // Section H: Sources & Neutrality Disclaimer
        const methodology = target.querySelector('.methodology-section');
        expect(methodology).not.toBeNull();
        expect(methodology.querySelector('.disclaimer-callout')).not.toBeNull();
      }
    );

    it('renders RTL layout orientation for Arabic, Persian, and Urdu', async () => {
      for (const rtlLang of ['ar', 'fa', 'ur']) {
        const { target } = await mountComponent(
          ComparisonPage,
          { competitor: 'historypin' },
          rtlLang,
          `/${rtlLang}/compare/historypin`
        );
        expect(target.querySelector('.scemory-comparison-page').getAttribute('dir')).toBe('rtl');
        app.unmount();
        document.body.innerHTML = '';
      }
    });

    it.each(locales)('renders ComparisonPage in %s without any translation key leakage', async lang => {
      const { target } = await mountComponent(
        ComparisonPage,
        { competitor: 'historypin' },
        lang,
        `/${lang}/compare/historypin`
      );

      // Verify no untranslated keys
      expect(target.textContent).not.toMatch(/comparisons\./);
      expect(target.textContent).not.toMatch(/policies\./);
      expect(target.textContent).not.toMatch(/nav\./);
      expect(target.textContent).not.toMatch(/footer\./);
    });

    it('updates dynamic SEO title, meta description, and hreflangs', async () => {
      await mountComponent(
        ComparisonPage,
        { competitor: 'historypin' },
        'en',
        '/en/compare/historypin'
      );

      expect(document.title).toContain('Scemory vs Historypin');
      expect(document.title).toContain('Scemory');

      const metaDesc = document.querySelector('meta[name="description"]');
      expect(metaDesc).not.toBeNull();
      expect(metaDesc.getAttribute('content')).toContain('Historypin');

      const canonical = document.querySelector('link[rel="canonical"]');
      expect(canonical).not.toBeNull();
      expect(canonical.getAttribute('href')).toContain('/en/compare/historypin');

      const hreflangs = document.querySelectorAll('link[rel="alternate"][hreflang]');
      expect(hreflangs.length).toBeGreaterThanOrEqual(13);
    });
  });
});
