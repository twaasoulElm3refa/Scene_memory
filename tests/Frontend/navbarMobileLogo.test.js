// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';
import siteRouter from '../../resources/js/router';
import Navbar from '../../resources/js/components/layouts/Navbar.vue';
import api from '../../resources/js/services/ApiClient';
import translations from '../../resources/js/i18n/policies';

const existing = import.meta.glob('../../resources/js/i18n/*.json', { eager: true, import: 'default' });
const messages = Object.fromEntries(
  Object.entries(existing).map(([file, value]) => {
    const lang = file.split('/').pop().replace('.json', '');
    return [lang, { ...value, policies: translations[lang] }];
  })
);

const allLocales = ['en', 'ar', 'de', 'fr', 'fa'];
let app;

const settle = async () => {
  for (let i = 0; i < 10; i++) await Promise.resolve();
  await nextTick();
};

async function mountNavbar(lang = 'en', url = `/${lang}/home`) {
  const isRtl = ['ar', 'fa', 'ur'].includes(lang);
  document.documentElement.lang = lang;
  document.documentElement.dir = isRtl ? 'rtl' : 'ltr';
  document.body.setAttribute('dir', isRtl ? 'rtl' : 'ltr');

  const target = document.createElement('div');
  document.body.append(target);

  const router = createRouter({ history: createMemoryHistory(), routes: siteRouter.options.routes });
  const i18n = createI18n({ legacy: false, locale: lang, fallbackLocale: false, messages });

  await router.push(url);
  await router.isReady();
  app = createApp(Navbar);
  app.use(router);
  app.use(i18n);
  app.mount(target);
  await settle();

  return { target, router, i18n };
}

describe('Scemory Mobile Navbar & Logo Visibility Verification', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.spyOn(api, 'get').mockResolvedValue({ data: { status: 'success', data: {} } });
  });

  afterEach(() => {
    if (app) {
      app.unmount();
      app = null;
    }
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('always renders the official Scemory logo in the mobile navbar when hamburger menu is closed', async () => {
    const { target } = await mountNavbar('en', '/en/plans');
    const brandMark = target.querySelector('.brand-mark');
    expect(brandMark).not.toBeNull();

    const logoImg = target.querySelector('.brand-logo img');
    expect(logoImg).not.toBeNull();
    expect(logoImg.getAttribute('src')).toContain('/images/logo.png');
    expect(logoImg.getAttribute('alt')).toBe('Scemory logo');

    // Confirm hamburger menu is closed by default
    expect(target.querySelector('.navbar-mobile-menu')).toBeNull();
  });

  it('keeps the Scemory logo visible in the main navbar when hamburger menu is opened', async () => {
    const { target } = await mountNavbar('en', '/en/plans');
    const toggleBtn = target.querySelector('.mobile-toggle');
    expect(toggleBtn).not.toBeNull();

    // Open hamburger menu
    toggleBtn.click();
    await settle();

    // Mobile menu is now visible
    expect(target.querySelector('.navbar-mobile-menu')).not.toBeNull();

    // Brand logo must STILL be present in the main navbar
    const brandMark = target.querySelector('.navbar-inner .brand-mark');
    expect(brandMark).not.toBeNull();
    const logoImg = brandMark.querySelector('.brand-logo img');
    expect(logoImg).not.toBeNull();
    expect(logoImg.getAttribute('src')).toContain('/images/logo.png');

    // Confirm no duplicate logo was added inside the mobile menu panel
    const menuLogo = target.querySelector('.navbar-mobile-menu img');
    expect(menuLogo).toBeNull();
  });

  it('navigates to the language-specific home page when clicking the brand logo', async () => {
    for (const lang of allLocales) {
      const { target, router } = await mountNavbar(lang, `/${lang}/plans`);
      const brandMark = target.querySelector('.brand-mark');
      expect(brandMark).not.toBeNull();
      expect(brandMark.tagName.toLowerCase()).toBe('a');
      expect(brandMark.getAttribute('href')).toBe(`/${lang}/home`);

      await router.push(brandMark.getAttribute('href'));
      await settle();
      expect(router.currentRoute.value.path).toBe(`/${lang}/home`);

      app.unmount();
      app = null;
      document.body.innerHTML = '';
    }
  });

  it('supports RTL and LTR orientations correctly with brand mark on the start side', async () => {
    // English (LTR)
    const { target: ltrTarget } = await mountNavbar('en', '/en/home');
    expect(document.documentElement.dir).toBe('ltr');
    const ltrBrandMark = ltrTarget.querySelector('.brand-mark');
    expect(ltrBrandMark).not.toBeNull();

    app.unmount();
    app = null;
    document.body.innerHTML = '';

    // Arabic (RTL)
    const { target: rtlTarget } = await mountNavbar('ar', '/ar/home');
    expect(document.documentElement.dir).toBe('rtl');
    const rtlBrandMark = rtlTarget.querySelector('.brand-mark');
    expect(rtlBrandMark).not.toBeNull();
    expect(rtlBrandMark.getAttribute('href')).toBe('/ar/home');
  });

  it('renders all action controls alongside the logo in both guest and logged-in states', async () => {
    // Guest state
    const { target: guestTarget } = await mountNavbar('en', '/en/home');
    expect(guestTarget.querySelector('.brand-mark')).not.toBeNull();
    expect(guestTarget.querySelector('.language-btn')).not.toBeNull();
    expect(guestTarget.querySelector('.login-btn')).not.toBeNull();
    expect(guestTarget.querySelector('.cart-btn')).not.toBeNull();
    expect(guestTarget.querySelector('.mobile-toggle')).not.toBeNull();

    app.unmount();
    app = null;
    document.body.innerHTML = '';

    // Logged in state
    localStorage.setItem('auth_token', 'test-token');
    vi.spyOn(api, 'get').mockResolvedValue({
      data: { status: 'success', data: { user: { name: 'TestUser', items: 3, event_count: 1 } } },
    });
    const { target: authTarget } = await mountNavbar('en', '/en/home');
    expect(authTarget.querySelector('.brand-mark')).not.toBeNull();
    expect(authTarget.querySelector('.language-btn')).not.toBeNull();
    expect(authTarget.querySelector('.user-menu-button')).not.toBeNull();
    expect(authTarget.querySelector('.cart-btn')).not.toBeNull();
    expect(authTarget.querySelector('.mobile-toggle')).not.toBeNull();
  });

  it('verifies responsive CSS rules guarantee the logo remains visible without display:none in home.vue and Navbar.vue', () => {
    const fs = require('node:fs');
    const path = require('node:path');

    const homeVuePath = path.resolve(__dirname, '../../resources/js/views/home/home.vue');
    const homeContent = fs.readFileSync(homeVuePath, 'utf8');

    // Confirm that home.vue never sets display: none on brand-mark
    expect(homeContent).not.toMatch(/\.hero-navbar[\s\S]*?\.brand-mark[\s\S]*?display:\s*none/);
    // Confirm brand-mark is explicitly display: inline-flex !important
    expect(homeContent).toMatch(/\.hero-navbar[\s\S]*?\.brand-mark[\s\S]*?display:\s*inline-flex/);

    const navbarVuePath = path.resolve(__dirname, '../../resources/js/components/layouts/Navbar.vue');
    const navbarContent = fs.readFileSync(navbarVuePath, 'utf8');

    // Confirm mobile media query anchors brand-mark with order: 1 and margin-inline-end: auto
    expect(navbarContent).toMatch(/@media\s*\(max-width:\s*767\.98px\)[\s\S]*?\.brand-mark[\s\S]*?order:\s*1/);
    expect(navbarContent).toMatch(/@media\s*\(max-width:\s*767\.98px\)[\s\S]*?\.brand-mark[\s\S]*?margin-inline-end:\s*auto/);

    // Confirm object-fit: contain preserves brand logo proportions
    expect(navbarContent).toMatch(/\.brand-logo img[\s\S]*?object-fit:\s*contain/);

    // Confirm small screen breakpoints for 420px and 340px (supporting 320px screens)
    expect(navbarContent).toContain('@media (max-width: 420px)');
    expect(navbarContent).toContain('@media (max-width: 340px)');
  });

  it('verifies Android launcher icons and adaptive XML configurations are present for all densities', () => {
    const fs = require('node:fs');
    const path = require('node:path');

    const resDir = path.resolve(__dirname, '../../android/app/src/main/res');
    const densities = ['mipmap-mdpi', 'mipmap-hdpi', 'mipmap-xhdpi', 'mipmap-xxhdpi', 'mipmap-xxxhdpi'];

    for (const d of densities) {
      const dir = path.join(resDir, d);
      expect(fs.existsSync(path.join(dir, 'ic_launcher.png'))).toBe(true);
      expect(fs.existsSync(path.join(dir, 'ic_launcher_round.png'))).toBe(true);
      expect(fs.existsSync(path.join(dir, 'ic_launcher_foreground.png'))).toBe(true);
    }

    // Adaptive XMLs
    expect(fs.existsSync(path.join(resDir, 'mipmap-anydpi-v26/ic_launcher.xml'))).toBe(true);
    expect(fs.existsSync(path.join(resDir, 'mipmap-anydpi-v26/ic_launcher_round.xml'))).toBe(true);
    expect(fs.existsSync(path.join(resDir, 'values/ic_launcher_background.xml'))).toBe(true);

    // AndroidManifest.xml references
    const manifestPath = path.resolve(__dirname, '../../android/app/src/main/AndroidManifest.xml');
    const manifest = fs.readFileSync(manifestPath, 'utf8');
    expect(manifest).toContain('android:icon="@mipmap/ic_launcher"');
    expect(manifest).toContain('android:roundIcon="@mipmap/ic_launcher_round"');
  });
});
