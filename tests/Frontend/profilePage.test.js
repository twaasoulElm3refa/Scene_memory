// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';
import siteRouter from '../../resources/js/router';
import Profile from '../../resources/js/views/home/profile.vue';
import * as userService from '../../resources/js/services/userService/userService';
import { PlanService } from '../../resources/js/services/planService/planService';
import translations from '../../resources/js/i18n/policies';

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

async function mountProfile(lang = 'en', url = `/${lang}/profile`) {
    const target = document.createElement('div');
    document.body.append(target);
    const router = createRouter({ history: createMemoryHistory(), routes: siteRouter.options.routes });
    const i18n = createI18n({ legacy: false, locale: lang, fallbackLocale: false, messages });
    await router.push(url);
    app = createApp(Profile);
    app.use(router);
    app.use(i18n);
    app.mount(target);
    await settle();
    return { target, router, i18n };
}

describe('Profile Page Component', () => {
    beforeEach(() => {
        localStorage.clear();
        localStorage.setItem('auth_token', 'fake-token');
    });

    it('mounts profile.vue with currentTab === 1', async () => {
        vi.spyOn(userService, 'getProfile').mockResolvedValue({
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            role: 'user',
            is_active: 1,
            licenceType: { name: 'Free', price: '0.00' },
            wallet: { amount: '10.00' }
        });

        const { target } = await mountProfile('en', '/en/profile');
        expect(target.querySelector('h1').textContent).toContain('My Profile');
    });

    it('mounts profile.vue when getProfile returns empty or null', async () => {
        vi.spyOn(userService, 'getProfile').mockResolvedValue(null);

        const { target } = await mountProfile('en', '/en/profile');
        expect(target.querySelector('h1')).not.toBeNull();
    });

    it('mounts profile.vue when getProfile throws an error', async () => {
        vi.spyOn(userService, 'getProfile').mockRejectedValue(new Error('Network error'));

        const { target } = await mountProfile('en', '/en/profile');
        expect(target.querySelector('h1')).not.toBeNull();
    });

    it('mounts profile.vue when accessing privacy tab and displays delete options', async () => {
        vi.spyOn(userService, 'getProfile').mockResolvedValue({
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            role: 'user',
            is_active: 1,
            licenceType: { name: 'Free', price: '0.00' },
            wallet: { amount: '10.00' }
        });

        const { target } = await mountProfile('en', '/en/profile?tab=privacy');
        expect(target.querySelector('h1').textContent).toContain('My Profile');

        // Check Account & Privacy card heading
        const cardTitle = target.querySelector('.border-red-200');
        expect(cardTitle).not.toBeNull();
        expect(target.textContent).toContain('Account & Data Deletion');

        // Check Delete Account & Data button
        const deleteBtn = Array.from(target.querySelectorAll('button')).find(b => b.textContent.includes('Delete Account & Data'));
        expect(deleteBtn).toBeDefined();

        // Check warning points are rendered
        const warningList = target.querySelector('ul.list-disc');
        expect(warningList).not.toBeNull();
        const listItems = warningList.querySelectorAll('li');
        expect(listItems.length).toBeGreaterThan(0);

        // Open modal
        deleteBtn.click();
        await settle();
        const modal = target.querySelector('.fixed.inset-0');
        expect(modal).not.toBeNull();
        expect(modal.textContent).toContain('Confirm Account Deletion');

        // Cancel / close modal
        const cancelBtn = Array.from(modal.querySelectorAll('button')).find(b => b.textContent.includes('Cancel'));
        expect(cancelBtn).toBeDefined();
        cancelBtn.click();
        await settle();
        expect(target.querySelector('.fixed.inset-0')).toBeNull();
    });

    it('mounts profile.vue in Arabic with RTL orientation on privacy tab', async () => {
        vi.spyOn(userService, 'getProfile').mockResolvedValue({
            id: 1,
            name: 'مستخدم تجريبي',
            email: 'arabic@example.com',
            role: 'user',
            is_active: 1,
            licenceType: { name: 'Free', price: '0.00' },
            wallet: { amount: '0.00' }
        });

        const { target } = await mountProfile('ar', '/ar/profile?tab=privacy');
        expect(target.querySelector('h1')).not.toBeNull();
        expect(target.textContent).toContain('حذف الحساب والبيانات');
        const deleteBtn = Array.from(target.querySelectorAll('button')).find(b => b.textContent.includes('حذف الحساب والبيانات'));
        expect(deleteBtn).toBeDefined();
    });
});
