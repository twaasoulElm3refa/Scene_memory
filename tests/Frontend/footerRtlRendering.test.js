// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';
import siteRouter from '../../resources/js/router';
import Footer from '../../resources/js/components/layouts/footer.vue';
import { FooterService } from '../../resources/js/services/FooterService/FooterService';
import translations from '../../resources/js/i18n/policies';
import fs from 'node:fs';
import path from 'node:path';

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

const allSupportedLocales = ['ar', 'en', 'fr', 'de', 'ru', 'es', 'it', 'hi', 'ja', 'fa', 'zh', 'ur', 'tr'];
const rtlLocales = ['ar', 'fa', 'ur'];
const ltrLocales = allSupportedLocales.filter(l => !rtlLocales.includes(l));

let app;
const settle = async () => {
  for (let i = 0; i < 12; i++) await Promise.resolve();
  await nextTick();
};

async function mountFooter(lang = 'ar', url = `/${lang}/home`) {
  const isRtl = rtlLocales.includes(lang);
  document.documentElement.lang = lang;
  document.documentElement.dir = isRtl ? 'rtl' : 'ltr';
  document.body.setAttribute('dir', isRtl ? 'rtl' : 'ltr');

  const target = document.createElement('div');
  document.body.append(target);

  const router = createRouter({ history: createMemoryHistory(), routes: siteRouter.options.routes });
  const i18n = createI18n({ legacy: false, locale: lang, fallbackLocale: false, messages });

  await router.push(url);
  app = createApp(Footer);
  app.use(router);
  app.use(i18n);
  app.mount(target);
  await settle();

  return { target, router, i18n };
}

describe('Scemory Footer RTL Rendering & Anti-Mirroring Verification', () => {
  beforeEach(() => {
    localStorage.clear();
    FooterService.links.mockResolvedValue({
      data: {
        data: {
          email: 'support@scemory.com',
          facebook: 'https://facebook.com/scemory',
          twitter: 'https://x.com/scemory',
          instagram: 'https://instagram.com/scemory',
          google_play: 'https://play.google.com/store/apps/details?id=com.scemory.app',
          app_store: 'https://apps.apple.com/app/scemory/id123456789',
        },
      },
    });
  });

  afterEach(() => {
    if (app) {
      app.unmount();
      app = null;
    }
    document.body.innerHTML = '';
    document.documentElement.removeAttribute('dir');
    document.documentElement.removeAttribute('lang');
    document.body.removeAttribute('dir');
    vi.restoreAllMocks();
  });

  it('renders correctly in Arabic (RTL) without mirrored transforms or corrupted layout', async () => {
    const { target } = await mountFooter('ar', '/ar/home');
    const footerEl = target.querySelector('.scemory-footer');
    expect(footerEl).not.toBeNull();
    expect(footerEl.getAttribute('dir')).toBe('rtl');

    // Check document dir
    expect(document.documentElement.dir).toBe('rtl');
    expect(document.body.getAttribute('dir')).toBe('rtl');

    // Ensure no element has scaleX(-1) or rotateY(180deg)
    const elementsWithTransform = target.querySelectorAll('[style*="transform"], [style*="scale"]');
    elementsWithTransform.forEach(el => {
      const style = el.getAttribute('style') || '';
      expect(style).not.toContain('scaleX(-1)');
      expect(style).not.toContain('rotateY');
      expect(style).not.toContain('matrix(-1');
    });

    // Check headings text in Arabic
    const comparisonsTitle = target.querySelector('#footer-comparisons-title');
    expect(comparisonsTitle?.textContent.trim()).toBe('المقارنات');

    const supportTitle = target.querySelector('#footer-support-title');
    expect(supportTitle?.textContent.trim()).toBe('الدعم والتواصل');

    // Check brand title bidirectional isolation
    const brandTitle = target.querySelector('#footer-brand-title');
    expect(brandTitle).not.toBeNull();
    const brandBdi = brandTitle?.querySelector('bdi[dir="ltr"]');
    expect(brandBdi?.textContent.trim()).toBe('Scemory');
    const brandSmallBdi = brandTitle?.querySelector('small[dir="ltr"] bdi');
    expect(brandSmallBdi?.textContent.trim()).toBe('(Scene Memory)');

    // Check store badges bidirectional isolation
    const stores = target.querySelectorAll('.footer-store strong');
    expect(stores.length).toBe(2);
    expect(stores[0].getAttribute('dir')).toBe('ltr');
    expect(stores[0].querySelector('bdi')?.textContent.trim()).toBe('Google Play');
    expect(stores[1].getAttribute('dir')).toBe('ltr');
    expect(stores[1].querySelector('bdi')?.textContent.trim()).toBe('App Store');

    // Check comparisons links bidirectional isolation
    const compLinks = target.querySelectorAll('nav[aria-labelledby="footer-comparisons-title"] a bdi');
    expect(compLinks.length).toBe(3);
    expect(compLinks[0].textContent).toContain('Historypin');
    expect(compLinks[1].textContent).toContain('PastVu');
    expect(compLinks[2].textContent).toContain('The Clio');

    // Check copyright text in Arabic
    const bottom = target.querySelector('.footer-bottom');
    expect(bottom?.textContent).toContain('Scemory (Scene Memory). جميع الحقوق محفوظة.');
  });

  it('renders correctly in Persian (fa) and Urdu (ur) with proper RTL layout', async () => {
    for (const lang of ['fa', 'ur']) {
      const { target } = await mountFooter(lang, `/${lang}/home`);
      expect(document.documentElement.dir).toBe('rtl');
      expect(document.body.getAttribute('dir')).toBe('rtl');

      const footerEl = target.querySelector('.scemory-footer');
      expect(footerEl).not.toBeNull();
      expect(footerEl.getAttribute('dir')).toBe('rtl');

      // Heading exists and is translated
      const title = target.querySelector('#footer-comparisons-title');
      expect(title?.textContent.trim().length).toBeGreaterThan(0);

      // Scemory brand is isolated
      const brandBdi = target.querySelector('#footer-brand-title bdi[dir="ltr"]');
      expect(brandBdi?.textContent.trim()).toBe('Scemory');

      app.unmount();
      app = null;
      document.body.innerHTML = '';
    }
  });

  it('renders all LTR languages correctly without regressions', async () => {
    for (const lang of ltrLocales) {
      const { target } = await mountFooter(lang, `/${lang}/home`);
      expect(document.documentElement.dir).toBe('ltr');
      expect(document.body.getAttribute('dir')).toBe('ltr');

      const footerEl = target.querySelector('.scemory-footer');
      expect(footerEl).not.toBeNull();
      expect(footerEl.getAttribute('dir')).toBe('ltr');

      const title = target.querySelector('#footer-comparisons-title');
      expect(title?.textContent.trim().length).toBeGreaterThan(0);

      app.unmount();
      app = null;
      document.body.innerHTML = '';
    }
  });

  it('immediately updates direction and texts when dynamically switching languages', async () => {
    const { target, router, i18n } = await mountFooter('en', '/en/home');
    expect(document.documentElement.dir).toBe('ltr');
    expect(target.querySelector('.scemory-footer')?.getAttribute('dir')).toBe('ltr');
    expect(target.querySelector('#footer-comparisons-title')?.textContent.trim()).toBe('Comparisons');

    // Switch to Arabic
    i18n.global.locale.value = 'ar';
    document.documentElement.dir = 'rtl';
    document.body.setAttribute('dir', 'rtl');
    await router.push('/ar/home');
    await settle();

    expect(document.documentElement.dir).toBe('rtl');
    expect(target.querySelector('.scemory-footer')?.getAttribute('dir')).toBe('rtl');
    expect(target.querySelector('#footer-comparisons-title')?.textContent.trim()).toBe('المقارنات');

    // Switch back to English
    i18n.global.locale.value = 'en';
    document.documentElement.dir = 'ltr';
    document.body.setAttribute('dir', 'ltr');
    await router.push('/en/home');
    await settle();

    expect(document.documentElement.dir).toBe('ltr');
    expect(target.querySelector('.scemory-footer')?.getAttribute('dir')).toBe('ltr');
    expect(target.querySelector('#footer-comparisons-title')?.textContent.trim()).toBe('Comparisons');
  });

  it('keeps direction rules local to the footer without overriding page transforms', () => {
    const appCss = fs.readFileSync(path.resolve(__dirname, '../../resources/css/app.css'), 'utf-8');
    const footerVue = fs.readFileSync(path.resolve(__dirname, '../../resources/js/components/layouts/footer.vue'), 'utf-8');

    expect(appCss).not.toContain('transform: none !important;');
    expect(footerVue).toContain('.scemory-footer[dir="rtl"] { direction: rtl; text-align: start; }');
    expect(footerVue).toContain('.scemory-footer[dir="ltr"] { direction: ltr; text-align: start; }');
    expect(footerVue).not.toContain('transform: none;');
    expect(footerVue).toContain('margin-inline-start: 0;');
    expect(footerVue).toContain('margin-inline-end: auto;');
  });
});
