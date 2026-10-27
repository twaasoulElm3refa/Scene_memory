// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter, RouterView } from 'vue-router';
import translations from '../../resources/js/i18n/policies';
import supportConversationMessages from '../../resources/js/i18n/supportConversation';
import { policies, approvedPolicyDate, verifiedFooterUrl } from '../../resources/js/policies';
import { footerNavigation, navigationSession, specialCoverageDestination } from '../../resources/js/navigation';
import Footer from '../../resources/js/components/layouts/footer.vue';
import Navbar from '../../resources/js/components/layouts/Navbar.vue';
import PolicyPage from '../../resources/js/views/home/PolicyPage.vue';
import Contact from '../../resources/js/views/home/contact.vue';
import siteRouter from '../../resources/js/router';
import { FooterService } from '../../resources/js/services/FooterService/FooterService';
import { ContactService } from '../../resources/js/services/ContactService/ContactService';
import api from '../../resources/js/services/ApiClient';

vi.mock('../../resources/js/services/FooterService/FooterService', () => ({ FooterService: { links: vi.fn() } }));
vi.mock('../../resources/js/services/ContactService/ContactService', () => ({ ContactService: { create: vi.fn() } }));
const existing = import.meta.glob('../../resources/js/i18n/*.json', { eager: true, import: 'default' });
const messages = Object.fromEntries(Object.entries(existing).map(([file, value]) => {
    const lang = file.split('/').pop().replace('.json', '');
    return [lang, { ...value, policies: translations[lang], supportConversation: supportConversationMessages[lang] }];
}));
const locales = Object.keys(messages);
let app;
const settle = async () => { for (let i = 0; i < 12; i++) await Promise.resolve(); await nextTick(); };
async function mount(component, props = {}, lang = 'en', url = `/${lang}/policies`) {
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
beforeEach(() => {
    localStorage.clear();
    navigationSession.isLoggedIn = false;
    navigationSession.eventCount = 0;
    FooterService.links.mockResolvedValue({ data: { data: {} } });
});
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
    vi.clearAllMocks();
});
function leaves(value, prefix = '') {
    return Object.entries(value).flatMap(([key, child]) => typeof child === 'object'
        ? leaves(child, `${prefix}${key}.`) : [[`${prefix}${key}`, child]]);
}

describe('policy translation and routing contract', () => {
    it('keeps metadata when navigating between shared and existing policy route components', async () => {
        const { router } = await mount({ render: () => h(RouterView) }, {}, 'en', '/en/copyright-policy');
        for (const path of ['/privacy-policy', '/terms', '/data-protection', '/cookie-policy', '/policies', '/copyright-policy']) {
            await router.push(`/en${path}`);
            await settle();
            expect(document.querySelectorAll('link[rel="canonical"]')).toHaveLength(1);
            expect(document.querySelector('link[rel="canonical"]').href).toContain(`/en${path}`);
            expect(document.querySelectorAll('link[hreflang]')).toHaveLength(13);
            expect(document.querySelector('meta[name="robots"]').content).toBe('noindex, follow');
        }
        await router.push('/en/contact');
        await settle();
        expect(document.querySelector('link[rel="canonical"]')).toBeNull();
        expect(document.querySelector('meta[name="robots"]')).toBeNull();
    });
    it('covers exactly the existing 13 locales, every key and every section', () => {
        expect(locales).toHaveLength(13);
        expect(Object.keys(translations).sort()).toEqual(locales.sort());
        const expected = leaves(translations.en).map(([key]) => key).sort();
        for (const lang of locales) {
            expect(leaves(translations[lang]).map(([key]) => key).sort()).toEqual(expected);
            for (const [, value] of leaves(translations[lang])) expect(typeof value === 'string' && value.trim().length > 0).toBe(true);
            if (lang !== 'en') {
                for (const policy of policies) {
                    translations[lang].pages[policy.id].sections.forEach((section, index) => {
                        expect(section.body).not.toBe(translations.en.pages[policy.id].sections[index].body);
                    });
                }
            }
        }
        expect(approvedPolicyDate).toBeNull();
    });

    it.each(locales)('renders all policies and footer in %s without fallbacks or broken destinations', async lang => {
        const { target, router } = await mount(Footer, {}, lang);
        expect(target.textContent).toContain(translations[lang].ui.audiencesTitle);
        expect(target.textContent).toContain(String(new Date().getFullYear()));
        expect(target.querySelector('footer').dir).toBe(['ar', 'fa', 'ur'].includes(lang) ? 'rtl' : 'ltr');
        for (const link of target.querySelectorAll('a[href]')) {
            const href = link.getAttribute('href');
            expect(href).toMatch(new RegExp(`^/${lang}/`));
            expect(router.resolve(href).matched.length).toBeGreaterThan(0);
        }
        const hrefs = [...target.querySelectorAll('.footer-list a')].map(a => a.getAttribute('href'));
        expect(new Set(hrefs).size).toBe(hrefs.length);
        expect(target.textContent).not.toMatch(/policies\.|nav\.|footer\./);
        expect(target.querySelector('a[href="#"]')).toBeNull();
        app.unmount();
        for (const policy of policies) {
            const page = await mount(PolicyPage, { policy: policy.id }, lang, `/${lang}${policy.path}`);
            expect(page.router.resolve(`/${lang}${policy.path}`).meta.legalPolicy).toBe(true);
            expect(page.target.querySelector('h1').textContent).toBe(translations[lang].pages[policy.id].title);
            expect(page.target.querySelectorAll('article section')).toHaveLength(translations[lang].pages[policy.id].sections.length);
            expect(document.title).toContain(translations[lang].pages[policy.id].title);
            expect(document.querySelector('meta[name="description"]').content).toBe(translations[lang].pages[policy.id].description);
            expect(document.querySelector('meta[name="robots"]').content).toBe('noindex, follow');
            expect(document.querySelector('link[rel="canonical"]').href).toContain(`/${lang}${policy.path}`);
            expect(document.querySelectorAll('link[hreflang]')).toHaveLength(locales.length);
            expect(page.target.textContent).not.toMatch(/policies\.(ui|pages|audiences)|nav\.[a-zA-Z]/);
            for (const anchor of page.target.querySelectorAll('.policy-toc a')) expect(page.target.querySelector(anchor.getAttribute('href'))).not.toBeNull();
            app.unmount();
        }
    });
});

describe('navigation and external links', () => {
    it('opens legal pages at the top while preserving back-navigation positions and anchors', () => {
        const scroll = siteRouter.options.scrollBehavior;
        expect(scroll({ meta: { legalPolicy: true } }, {}, null)).toEqual({ top: 0, behavior: 'instant' });
        expect(scroll({ meta: { legalPolicy: true }, hash: '#policy-section-1' }, {}, null)).toEqual({ el: '#policy-section-1' });
        expect(scroll({ meta: { legalPolicy: true } }, {}, { top: 420 })).toEqual({ top: 420 });
    });
    it('restricts private destinations and creator dashboard to verified sessions', () => {
        const guest = footerNavigation({ isLoggedIn: false, eventCount: 0 });
        expect(guest.some(link => link.authenticated)).toBe(false);
        expect(guest.some(link => link.path === '/auth')).toBe(true);
        const user = footerNavigation({ isLoggedIn: true, eventCount: 0 });
        expect(user.some(link => link.path === '/auth')).toBe(false);
        expect(user.some(link => link.path === '/profile')).toBe(true);
        expect(user.some(link => link.path.startsWith('/creator'))).toBe(false);
        expect(footerNavigation({ isLoggedIn: true, eventCount: 1 }).some(link => link.path === '/creator/events')).toBe(true);
    });
    it('preserves the special coverage login redirect and existing query', () => {
        const guest = specialCoverageDestination('ar', false, { q: 'Cairo' });
        expect(guest.name).toBe('auth');
        expect(guest.params.lang).toBe('ar');
        expect(guest.query.redirect).toBe('/ar/home?q=Cairo&openSpecialCoverage=1');
        expect(specialCoverageDestination('fr', true).query.openSpecialCoverage).toBe('1');
    });
    it('shares the verified Navbar session with the footer and preserves language on navigation', async () => {
        localStorage.setItem('auth_token', 'test-token');
        vi.spyOn(api, 'get').mockResolvedValue({ data: { status: 'success', data: { user: { name: 'Author', event_count: 2 } } } });
        const { target, router } = await mount({ render: () => h('div', [h(Navbar), h(Footer)]) }, {}, 'en');
        expect(navigationSession.isLoggedIn).toBe(true);
        expect(target.querySelector('footer a[href="/en/creator/events"]')).not.toBeNull();
        await router.push('/fa/cookie-policy');
        await settle();
        expect(localStorage.getItem('language')).toBe('fa');
        expect(target.querySelector('footer').dir).toBe('rtl');
        expect(target.querySelector('footer a[href="/fa/cookie-policy"]')).not.toBeNull();
    });
    it('rejects unsafe, placeholder and wrong-store URLs', () => {
        for (const value of ['#', '', 'x.com', 'https://x.com', 'javascript:alert(1)', 'https://x.com.evil.test/profile', 'https://user:pass@x.com/profile', 'https://x.com:444/profile']) expect(verifiedFooterUrl(value, 'twitter')).toBeNull();
        expect(verifiedFooterUrl('https://x.com/scemory', 'twitter')).toBe('https://x.com/scemory');
        expect(verifiedFooterUrl('https://play.google.com/store/apps/details?id=com.scemory.app', 'google_play')).not.toBeNull();
        expect(verifiedFooterUrl('https://apps.apple.com/app/id123456', 'app_store')).not.toBeNull();
        expect(verifiedFooterUrl('https://play.google.com/store', 'google_play')).toBeNull();
    });
    it('keeps unavailable destinations unlinked and uses configured links with accessible names', async () => {
        FooterService.links.mockResolvedValue({ data: { data: { facebook: 'https://www.facebook.com/scemory', google_play: 'googlePlay.com' } } });
        const { target } = await mount(Footer);
        expect(target.querySelector('a[href="https://www.facebook.com/scemory"]').getAttribute('rel')).toContain('noopener');
        expect(target.querySelector('a[href="https://www.facebook.com/scemory"]').getAttribute('aria-label')).toContain('Facebook');
        expect(target.querySelectorAll('.footer-store[aria-disabled="true"]')).toHaveLength(2);
    });
});

describe('working legal contact channel', () => {
    it.each(['copyright', 'privacy', 'legal'])('preselects and sends %s through the existing API', async topic => {
        ContactService.create.mockResolvedValue({ status: 200, data: { status: 'success', data: { ticket: { id: 42, subject: topic, status: 'open' }, access_token: 'test-guest-access' } } });
        const { target } = await mount(Contact, {}, 'ar', `/ar/contact?topic=${topic}`);
        expect(target.querySelector('#subject').value).toBe(topic);
        for (const [selector, value] of [['#full-name', 'Rights Owner'], ['#email', 'owner@example.test'], ['#message', 'Please review https://scemory.com/ar/single_event/example']]) {
            const input = target.querySelector(selector); input.value = value; input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        await nextTick();
        target.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await settle();
        expect(ContactService.create).toHaveBeenCalledWith(expect.objectContaining({ subject: topic, email: 'owner@example.test' }));
        expect(target.querySelector('[role="status"]')).not.toBeNull();
    });
});
