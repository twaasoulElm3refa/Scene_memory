// @vitest-environment jsdom

import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';

import planCheckout from '../../resources/js/i18n/planCheckout';
import {
    inProgressOrderId,
    paypalApprovalUrl,
    planCheckoutErrorKey,
} from '../../resources/js/services/planService/planCheckout';

const planService = vi.hoisted(() => ({
    getSingle: vi.fn(),
    getSavingsEstimate: vi.fn(),
    subscribePayPal: vi.fn(),
    subscribeWallet: vi.fn(),
}));
const redirect = vi.hoisted(() => vi.fn());
const apiPost = vi.hoisted(() => vi.fn());
vi.mock('@/services/planService/planService', () => ({ PlanService: planService }));
vi.mock('@/services/accountState', () => ({ refreshAccount: vi.fn() }));
vi.mock('@/services/planService/planCheckout', async (importOriginal) => ({
    ...(await importOriginal()),
    redirectToPayPal: redirect,
}));
vi.mock('../../resources/js/services/ApiClient', () => ({ default: { post: apiPost, get: vi.fn() } }));

import ShowPlan from '../../resources/js/views/home/show_plan.vue';

const locales = ['ar', 'en', 'fr', 'de', 'ru', 'es', 'it', 'hi', 'ja', 'fa', 'zh', 'ur', 'tr'];
const files = import.meta.glob('../../resources/js/i18n/*.json', { eager: true, import: 'default' });
const json = Object.fromEntries(Object.entries(files).map(([file, value]) => [file.split('/').pop().replace('.json', ''), value]));
const approval = 'https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T';
const proPlan = { id: 7, name: 'Pro', slug: 'pro', price: '200.00', photo_quota: 31, video_quota: 6, advantges: [] };
const httpError = (status, data = {}) => Object.assign(new Error(`HTTP ${status}`), { response: { status, data } });

let app;
let router;
const settle = async () => {
    for (let i = 0; i < 5; i++) {
        await new Promise((done) => setTimeout(done, 0));
        await nextTick();
    }
};

async function mountPlan(locale = 'en') {
    planService.getSingle.mockResolvedValue({ data: { data: [proPlan] } });
    planService.getSavingsEstimate.mockRejectedValue(new Error('no cart'));
    router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/:lang/plans/:slug', component: { render: () => h('div') } },
            { path: '/:pathMatch(.*)*', component: { render: () => h('div') } },
        ],
    });
    await router.push(`/${locale}/plans/pro`);
    const messages = Object.fromEntries(locales.map((lang) => [lang, { ...json[lang], planCheckout: planCheckout[lang] }]));
    app = createApp(ShowPlan).use(router).use(createI18n({ legacy: false, locale, fallbackLocale: 'en', messages }));
    const target = document.createElement('div');
    document.body.append(target);
    app.mount(target);
    await settle();

    return target;
}

const clickPayPal = async (target, locale = 'en') => {
    [...target.querySelectorAll('button')].find((button) => button.textContent.includes(json[locale].mediaCredits.payWithPaypal)).click();
    await settle();
};

afterEach(() => {
    app?.unmount();
    app = null;
    document.body.innerHTML = '';
    vi.clearAllMocks();
});

describe('PayPal plan checkout helpers', () => {
    it('reads approval_url from the top level of the API response body', () => {
        expect(paypalApprovalUrl({ data: { order_id: 55, approval_url: approval } })).toBe(approval);
        // The old show_plan.vue lookup path, which the API has never returned.
        expect(paypalApprovalUrl({ data: { data: { approval_url: approval } } })).toBeNull();
    });

    it('only follows https links on a paypal.com host', () => {
        expect(paypalApprovalUrl({ data: { approval_url: 'https://www.paypal.com/checkoutnow?token=A' } })).toBe('https://www.paypal.com/checkoutnow?token=A');
        for (const unsafe of ['http://www.paypal.com/x', 'https://paypal.com.evil.test/x', 'javascript:alert(1)', 'https://evilpaypal.com/x', '', null]) {
            expect(paypalApprovalUrl({ data: { approval_url: unsafe } }), String(unsafe)).toBeNull();
        }
    });

    it('maps API failures to translated messages, including sanitized 5xx bodies', () => {
        expect(planCheckoutErrorKey(httpError(422, { code: 'plan_financially_unavailable' }))).toBe('planCheckout.financiallyUnavailable');
        expect(planCheckoutErrorKey(httpError(422, { code: 'accumulation_limit' }))).toBe('planCheckout.accumulationLimit');
        expect(planCheckoutErrorKey(httpError(422, { code: 'plan_inactive' }))).toBe('planCheckout.planInactive');
        expect(planCheckoutErrorKey(httpError(403, { code: 'unauthorized' }))).toBe('planCheckout.signIn');
        expect(planCheckoutErrorKey(httpError(401, {}))).toBe('planCheckout.signIn');
        expect(planCheckoutErrorKey(httpError(503, { status: 'error', message: 'Request failed.' }))).toBe('planCheckout.paypalUnavailable');
        expect(planCheckoutErrorKey(httpError(502, { status: 'error', message: 'Request failed.' }))).toBe('planCheckout.paypalOrderFailed');
        expect(planCheckoutErrorKey(new Error('Network Error'))).toBe('planCheckout.networkError');
        expect(inProgressOrderId(httpError(409, { code: 'checkout_in_progress', order_id: 55 }))).toBe(55);
        expect(inProgressOrderId(httpError(409, { code: 'other', order_id: 55 }))).toBeNull();
    });

    it('asks the API client not to stack its raw global toast on the plan page message', async () => {
        const { PlanService } = await vi.importActual('../../resources/js/services/planService/planService');
        apiPost.mockResolvedValue({ data: {} });
        await PlanService.subscribePayPal(7);
        expect(apiPost).toHaveBeenCalledWith('/plans/subscribe/7/paypal', null, { suppressGlobalErrorToast: true });
    });

    it('defines every checkout message in all 13 languages', () => {
        expect(Object.keys(planCheckout).sort()).toEqual([...locales].sort());
        for (const locale of locales) {
            expect(Object.keys(planCheckout[locale]).sort(), locale).toEqual(Object.keys(planCheckout.en).sort());
            for (const [key, english] of Object.entries(planCheckout.en)) {
                const message = planCheckout[locale][key];
                expect(message.trim().length, `${locale}.${key}`).toBeGreaterThan(0);
                expect(message, `${locale}.${key}`).not.toContain('%{');
                if (locale !== 'en') expect(message, `${locale}.${key}`).not.toBe(english);
            }
        }
    });
});

describe('Pay with PayPal on the plan page', () => {
    it('redirects to PayPal for a successful order instead of showing "Subscription failed"', async () => {
        planService.subscribePayPal.mockResolvedValue({ status: 200, data: { order_id: 55, approval_url: approval, status: 'created' } });
        const target = await mountPlan();

        await clickPayPal(target);

        expect(planService.subscribePayPal).toHaveBeenCalledWith(7);
        expect(redirect).toHaveBeenCalledWith(approval);
        expect(target.textContent).not.toContain(json.en.plans.errors.subscribeFailed);
    });

    it('sends an already approved checkout to the confirmation page', async () => {
        planService.subscribePayPal.mockRejectedValue(httpError(409, { code: 'checkout_in_progress', order_id: 55 }));
        const target = await mountPlan();

        await clickPayPal(target);

        expect(redirect).not.toHaveBeenCalled();
        expect(router.currentRoute.value.fullPath).toBe('/en/waiting?order_id=55');
    });

    it('explains a financial-policy rejection in the shopper language', async () => {
        planService.subscribePayPal.mockRejectedValue(httpError(422, { code: 'plan_financially_unavailable', message: 'This plan is temporarily unavailable pending financial validation.' }));
        const target = await mountPlan('ar');

        await clickPayPal(target, 'ar');

        expect(redirect).not.toHaveBeenCalled();
        expect(target.textContent).toContain(planCheckout.ar.financiallyUnavailable);
        expect(target.textContent).not.toContain(json.ar.plans.errors.subscribeFailed);
        // The button becomes usable again for another attempt.
        expect([...target.querySelectorAll('button')].some((button) => button.textContent.includes(json.ar.mediaCredits.payWithPaypal) && !button.disabled)).toBe(true);
    });

    it('shows the PayPal-unavailable message for a sanitized 503', async () => {
        planService.subscribePayPal.mockRejectedValue(httpError(503, { status: 'error', message: 'Request failed.' }));
        const target = await mountPlan();

        await clickPayPal(target);

        expect(target.textContent).toContain(planCheckout.en.paypalUnavailable);
    });

    it('never redirects when a 200 response lacks a PayPal approval link', async () => {
        planService.subscribePayPal.mockResolvedValue({ status: 200, data: { order_id: 55, approval_url: 'https://example.test/pay' } });
        const target = await mountPlan();

        await clickPayPal(target);

        expect(redirect).not.toHaveBeenCalled();
        expect(target.textContent).toContain(planCheckout.en.paypalOrderFailed);
    });
});
