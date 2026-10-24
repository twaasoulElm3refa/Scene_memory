// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';
import siteRouter from '../../resources/js/router';
import Footer from '../../resources/js/components/layouts/footer.vue';
import DataDeletion from '../../resources/js/views/home/DataDeletion.vue';
import { FooterService } from '../../resources/js/services/FooterService/FooterService';
import * as deletionApi from '../../resources/js/services/userDataDeletionService';
import translations from '../../resources/js/i18n/policies';

vi.mock('../../resources/js/services/FooterService/FooterService', () => ({
    FooterService: { links: vi.fn() }
}));

const existing = import.meta.glob('../../resources/js/i18n/*.json', { eager: true, import: 'default' });
const messages = Object.fromEntries(Object.entries(existing).map(([file, value]) => {
    const lang = file.split('/').pop().replace('.json', '');
    return [lang, { ...value, policies: translations[lang] }];
}));

let app;
const settle = async () => {
    for (let i = 0; i < 12; i++) await Promise.resolve();
    await nextTick();
};

async function mountComponent(component, props = {}, lang = 'en', url = `/${lang}/data-deletion`) {
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

describe('Data Deletion Feature', () => {
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

    it('renders the public Data Deletion page in English with required sections and compliance notice', async () => {
        const { target } = await mountComponent(DataDeletion, {}, 'en', '/en/data-deletion');

        expect(target.querySelector('h1').textContent).toContain('User Account & Data Deletion');
        expect(target.textContent).toContain('Complies with Meta / Facebook Platform Data Deletion Instructions Policy');
        expect(target.querySelector('#section-a')).not.toBeNull();
        expect(target.querySelector('#section-b')).not.toBeNull();
        expect(target.querySelector('#section-c')).not.toBeNull();
        expect(target.querySelector('#section-d')).not.toBeNull();
        expect(target.querySelector('#section-e')).not.toBeNull();
        expect(target.querySelector('#section-f')).not.toBeNull();
        expect(target.querySelector('#section-g')).not.toBeNull();
        expect(target.textContent).toContain('scemorygmail@gmail.com');
    });

    it('renders the public Data Deletion page in Arabic with RTL direction', async () => {
        const { target } = await mountComponent(DataDeletion, {}, 'ar', '/ar/data-deletion');

        expect(target.querySelector('.policy-page').getAttribute('dir')).toBe('rtl');
        expect(target.querySelector('h1').textContent).toContain('تعليمات حذف حساب المستخدم والبيانات');
        expect(target.querySelector('#section-a')).not.toBeNull();
        expect(target.textContent).toContain('scemorygmail@gmail.com');
    });

    it('renders Data Deletion link in global footer under Support & Contact', async () => {
        const { target } = await mountComponent(Footer, {}, 'en', '/en/home');

        const deletionLink = target.querySelector('footer a[href="/en/data-deletion"]');
        expect(deletionLink).not.toBeNull();
        expect(deletionLink.textContent.trim()).toBe('Data Deletion Instructions');
    });

    it('renders Arabic Data Deletion link in footer', async () => {
        const { target } = await mountComponent(Footer, {}, 'ar', '/ar/home');

        const deletionLink = target.querySelector('footer a[href="/ar/data-deletion"]');
        expect(deletionLink).not.toBeNull();
        expect(deletionLink.textContent.trim()).toBe('تعليمات حذف البيانات');
    });

    it('interactive status checker loads status from query parameter', async () => {
        const spy = vi.spyOn(deletionApi, 'getPublicDataDeletionStatus').mockResolvedValue({
            data: {
                reference: 'DEL-FB-12345678',
                status: 'completed',
                masked_email: 'j***e@example.com',
                financial_obligations_detected: false,
                completed_at: '2026-10-24T12:00:00Z',
            }
        });

        const { target } = await mountComponent(DataDeletion, {}, 'en', '/en/data-deletion?reference=DEL-FB-12345678');
        await settle();

        expect(spy).toHaveBeenCalledWith('DEL-FB-12345678');
        expect(target.textContent).toContain('DEL-FB-12345678');
        expect(target.textContent).toContain('j***e@example.com');
    });
});
