// @vitest-environment jsdom

import { afterEach, describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { createApp, h, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';

import planOptimizer from '../../resources/js/i18n/planOptimizer';
import fixture from '../fixtures/plan-recommendation-200.json';
import {
    budgetRows,
    createFormatters,
    formStateFromOption,
    optionMetrics,
    primaryOption,
    proofLines,
} from '../../resources/js/views/admin/Licenses/planRecommendation';

const planService = vi.hoisted(() => ({
    getMediaTiers: vi.fn(),
    validatePricing: vi.fn(),
    recommendQuotas: vi.fn(),
    create: vi.fn(),
}));
vi.mock('@/services/admin/licenses/planService', () => ({ default: planService }));
vi.mock('../../resources/js/layouts/AdminLayout.vue', () => ({
    default: { setup: (_, { slots }) => () => h('div', slots.default?.()) },
}));

import CreatePlan from '../../resources/js/views/admin/Licenses/create_plan.vue';

const locales = ['ar', 'en', 'fr', 'de', 'ru', 'es', 'it', 'hi', 'ja', 'fa', 'zh', 'ur', 'tr'];
// Brand names and loanwords that are legitimately spelled as in English.
const mayMatchEnglish = new Set(['methodPaypal', 'photos', 'videos', 'mediaPhoto', 'mediaVideo', 'alternativesTitle', 'margin']);
const placeholders = (message) => [...message.matchAll(/\{(\w+)\}/g)].map((match) => match[1]).sort();
const seeded = fixture.scenarios.seeded_tiers_200;
const targetMet = fixture.scenarios.value_target_met_200;
const en = (key, params = {}) => planOptimizer.en[key].replace(/\{(\w+)\}/g, (_, name) => params[name]);

const tiersOf = (scenario) => scenario.tiers.map((tier) => ({
    ...tier,
    is_active: true,
    is_plan_eligible: true,
    max_photo_price: tier.max_price_photo / 100,
    max_video_price: tier.max_price_video / 100,
}));
const analysisOf = (option) => ({
    is_valid: true,
    catalog_value_is_valid: true,
    settings_version: 1,
    max_potential_value: option.potential_cart_value_minor / 100,
    max_creator_liability: option.max_creator_liability_minor / 100,
    worst_case_profit: option.worst_case_profit_minor / 100,
    customer_value_target_bps: option.recommendation_value_target_bps,
    errors: [],
});
const responseOf = (scenario, extra = {}) => {
    const withAnalysis = (option) => (option ? { ...option, analysis: analysisOf(option) } : null);
    const response = scenario.response;

    return {
        settings_version: 1,
        missing_assumptions: [],
        price_minor: response.proof.price_minor,
        ...response,
        recommendation: withAnalysis(response.recommendation),
        closest_safe_configuration: withAnalysis(response.closest_safe_configuration),
        alternatives: response.alternatives.map(withAnalysis),
        ...extra,
    };
};

let app;
const settle = async (ms = 0) => {
    await new Promise((done) => setTimeout(done, ms));
    for (let i = 0; i < 4; i++) {
        await new Promise((done) => setTimeout(done, 0));
        await nextTick();
    }
};

async function mount(scenario, locale = 'en') {
    planService.getMediaTiers.mockResolvedValue({ data: tiersOf(scenario) });
    planService.validatePricing.mockImplementation(async () => ({ is_valid: true, catalog_value_is_valid: true, settings_version: 1, errors: [] }));
    planService.create.mockResolvedValue({ data: {} });
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:pathMatch(.*)*', component: { render: () => h('div') } }] });
    await router.push('/admin/plans/create');
    const i18n = createI18n({
        legacy: false,
        locale,
        fallbackLocale: 'en',
        messages: Object.fromEntries(locales.map((lang) => [lang, { planOptimizer: planOptimizer[lang] }])),
    });
    const target = document.createElement('div');
    document.body.append(target);
    app = createApp(CreatePlan).use(router).use(i18n);
    app.mount(target);
    await settle();

    return target;
}

const numberInputs = (target) => [...target.querySelectorAll('input[type="number"]')];
const typeInto = async (input, value) => {
    input.value = value;
    input.dispatchEvent(new Event('input'));
    input.dispatchEvent(new Event('change'));
    await nextTick();
};
const button = (target, text) => [...target.querySelectorAll('button')].find((item) => item.textContent.trim() === text);
const formValues = (target) => {
    const inputs = numberInputs(target);
    // price, then photo/video ceilings per tier, then photo and video quotas.
    return {
        price: inputs[0].value,
        ceilings: inputs.slice(1, -2).map((input) => Number(input.value)),
        photos: Number(inputs.at(-2).value),
        videos: Number(inputs.at(-1).value),
    };
};

afterEach(() => {
    app?.unmount();
    app = null;
    document.body.innerHTML = '';
    vi.clearAllMocks();
});

describe('plan optimizer translations', () => {
    it('defines every optimizer message in all 13 languages with matching placeholders', () => {
        expect(Object.keys(planOptimizer).sort()).toEqual([...locales].sort());
        for (const locale of locales) {
            for (const [key, english] of Object.entries(planOptimizer.en)) {
                const message = planOptimizer[locale][key];
                expect(message, `${locale}.${key}`).toEqual(expect.any(String));
                expect(message.trim().length, `${locale}.${key}`).toBeGreaterThan(0);
                expect(placeholders(message), `${locale}.${key}`).toEqual(placeholders(english));
                // vue-i18n 9 reads "%{" as Rails interpolation and would drop the percent sign.
                expect(message, `${locale}.${key}`).not.toContain('%{');
                if (locale !== 'en' && !mayMatchEnglish.has(key)) {
                    expect(message, `${locale}.${key}`).not.toBe(english);
                }
            }
            expect(Object.keys(planOptimizer[locale]).sort(), locale).toEqual(Object.keys(planOptimizer.en).sort());
        }
    });

    it('is merged into the application messages', () => {
        const appSource = readFileSync(resolve(process.cwd(), 'resources/js/app.js'), 'utf8');
        expect(appSource).toContain('import planOptimizerMessages from "./i18n/planOptimizer"');
        expect(appSource).toContain('messages[lang].planOptimizer = planOptimizer');
    });
});

describe('plan optimizer helpers use the API values unchanged', () => {
    const fmt = createFormatters('en', 'USD');
    const t = (key) => key;

    it('maps every API option onto the form with Basic ceilings equal to the totals', () => {
        for (const scenario of Object.values(fixture.scenarios)) {
            const tiers = tiersOf(scenario);
            const response = scenario.response;
            const options = [response.recommendation, response.closest_safe_configuration, ...response.alternatives].filter(Boolean);
            expect(options.length).toBeGreaterThan(0);
            for (const option of options) {
                const state = formStateFromOption(option, tiers);
                expect(state.photo_quota).toBe(option.photo_quota);
                expect(state.video_quota).toBe(option.video_quota);
                expect(state.media_tier_id).toBe(option.media_tier_id);
                expect(state.recommendation_value_target_bps).toBe(option.recommendation_value_target_bps);
                expect(state.tier_limits.map((limit) => limit.media_tier_id)).toEqual(tiers.map((tier) => tier.id));
                for (const limit of state.tier_limits) {
                    const api = option.tier_limits.find((item) => item.media_tier_id === limit.media_tier_id);
                    expect(limit.photo_maximum).toBe(api ? api.photo_maximum : 0);
                    expect(limit.video_maximum).toBe(api ? api.video_maximum : 0);
                }
                expect(state.tier_limits[0].photo_maximum).toBe(option.photo_quota);
                expect(state.tier_limits[0].video_maximum).toBe(option.video_quota);
            }
        }
    });

    it('explains the seeded $200 case with the exact API proof numbers', () => {
        const response = seeded.response;
        const rows = Object.fromEntries(budgetRows(response.proof, fmt, t).map((row) => [row.key, row.value]));
        expect(rows.planPrice).toBe('$200.00');
        expect(rows.liabilityBudget).toBe('$176.00');
        expect(rows.valueTarget).toBe('$220.00');
        expect(rows.maxSafeValue).toBe('$195.00');

        const lines = Object.fromEntries(proofLines(response, fmt, t).map((line) => [line.key, line.params]));
        expect(lines.proofBestCredit).toMatchObject({ cap: '$1.80', maxPrice: '$2.00', ratio: '90' });
        expect(lines.proofUpperBound).toEqual({ budget: '$176.00', value: '$195.55' });
        expect(lines.proofShortfall).toEqual({ amount: '$25.00' });
        expect(lines.proofImpossible).toEqual({ share: '88', costs: '0', margin: '12', ratio: '97.77', required: '80' });
        expect(lines.proofReachable).toBeUndefined();

        const metrics = Object.fromEntries(optionMetrics(primaryOption(response), fmt).map((metric) => [metric.key, metric.value]));
        expect(metrics.maxPayout).toBe('$175.50');
        expect(metrics.worstCaseProfit).toBe('$24.50');
        expect(metrics.margin).toBe('12.25%');
        expect(metrics.potentialValue).toBe('$195.00 (97.5%)');
    });

    it('does not report a shortfall when the target is met', () => {
        const keys = proofLines(targetMet.response, fmt, t).map((line) => line.key);
        expect(keys).not.toContain('proofShortfall');
        expect(keys).not.toContain('proofImpossible');
        expect(primaryOption(targetMet.response).key).toBe('recommended');
    });
});

describe('Create Plan optimizer panel', () => {
    it('shows the proof and closest safe configuration when 110% is impossible, without auto-applying it', async () => {
        planService.recommendQuotas.mockResolvedValue(responseOf(seeded));
        const target = await mount(seeded);

        await typeInto(numberInputs(target)[0], '200');
        await settle(600);

        expect(planService.recommendQuotas).toHaveBeenCalledWith(200);
        const text = target.textContent;
        expect(text).toContain(en('statusNotMet', { price: '$200.00' }));
        expect(text).toContain('$176.00');
        expect(text).toContain(en('proofImpossible', { share: '88', costs: '0', margin: '12', ratio: '97.77', required: '80' }));
        expect(text).toContain(en('optionClosestSafe'));
        expect(text).toContain(en('noAlternatives'));
        expect(text).toContain(en('disclaimer'));
        expect(formValues(target).photos).toBe(0);

        button(target, en('useConfiguration')).click();
        await settle();
        const closest = seeded.response.closest_safe_configuration;
        expect(formValues(target)).toEqual({
            price: '200',
            ceilings: [11, 7, 3, 3, 3, 1],
            photos: closest.photo_quota,
            videos: closest.video_quota,
        });
    });

    it('auto-applies a target-meeting recommendation and submits exactly the API configuration', async () => {
        planService.recommendQuotas.mockResolvedValue(responseOf(targetMet));
        const target = await mount(targetMet);

        await typeInto(target.querySelector('input[type="text"]'), 'Value Plan');
        await typeInto(numberInputs(target)[0], '200');
        await settle(600);

        const recommended = targetMet.response.recommendation;
        expect(target.textContent).toContain(en('autoApplied'));
        expect(formValues(target)).toEqual({ price: '200', ceilings: [9, 9, 4, 4], photos: recommended.photo_quota, videos: recommended.video_quota });

        target.querySelector('form').dispatchEvent(new Event('submit'));
        await settle();
        expect(planService.create).toHaveBeenCalledTimes(1);
        expect(planService.create.mock.calls[0][0]).toMatchObject({
            price: 200,
            photo_quota: recommended.photo_quota,
            video_quota: recommended.video_quota,
            recommendation_value_target_bps: 11000,
            tier_limits: [
                { media_tier_id: 1, photo_maximum: 9, video_maximum: 9 },
                { media_tier_id: 2, photo_maximum: 4, video_maximum: 4 },
            ],
        });
    });

    it('changes the selling price only after explicit confirmation of a price alternative', async () => {
        const closest = seeded.response.closest_safe_configuration;
        const alternative = { ...closest, key: 'price_alternative', price_minor: 27700, price: '277.00', requires_price_change: true, meets_value_target: true, recommendation_value_target_bps: 11000 };
        planService.recommendQuotas.mockResolvedValue(responseOf(seeded, { alternatives: [{ ...alternative, analysis: analysisOf(alternative) }] }));
        const target = await mount(seeded);

        await typeInto(numberInputs(target)[0], '200');
        await settle(600);
        button(target, en('useAtPrice', { price: '$277.00' })).click();
        await settle();

        expect(formValues(target).price).toBe('200');
        expect(target.textContent).toContain(en('confirmPriceChange', { price: '$277.00', current: '$200.00' }));
        button(target, en('cancel')).click();
        await settle();
        expect(formValues(target).price).toBe('200');

        button(target, en('useAtPrice', { price: '$277.00' })).click();
        await settle();
        button(target, en('changePrice', { price: '$277.00' })).click();
        await settle();
        expect(formValues(target).price).toBe('277.00');
        expect(target.textContent).toContain(en('priceChanged', { from: '$200.00', to: '$277.00' }));
    });

    it('keeps manually edited quotas and their value target when the price changes, and revalidates them', async () => {
        planService.recommendQuotas.mockResolvedValue(responseOf(targetMet));
        const target = await mount(targetMet);
        await typeInto(numberInputs(target)[0], '200');
        await settle(600);

        await typeInto(numberInputs(target).at(-2), '5');
        await settle();
        planService.validatePricing.mockClear();
        await typeInto(numberInputs(target)[0], '210');
        await settle(600);

        expect(planService.recommendQuotas).toHaveBeenLastCalledWith(210);
        expect(formValues(target).photos).toBe(5);
        expect(planService.validatePricing).toHaveBeenCalled();
        expect(planService.validatePricing.mock.calls.at(-1)[0]).toMatchObject({ price: 210, photo_quota: 5, recommendation_value_target_bps: 11000 });
    });

    it('renders the panel in Arabic with logical (RTL-safe) spacing', async () => {
        planService.recommendQuotas.mockResolvedValue(responseOf(seeded));
        const target = await mount(seeded, 'ar');
        await typeInto(numberInputs(target)[0], '200');
        await settle(600);

        expect(target.textContent).toContain(planOptimizer.ar.title);
        expect(target.textContent).toContain(planOptimizer.ar.optionClosestSafe);

        const source = readFileSync(resolve(process.cwd(), 'resources/js/views/admin/Licenses/create_plan.vue'), 'utf8');
        const panel = source.slice(source.indexOf('<!-- Automatic quota optimizer'), source.indexOf('<!-- Media Tier Selection -->'));
        expect(panel).toContain('text-start');
        expect(panel).not.toMatch(/\b(text-left|text-right|pl-\d|pr-\d|ml-\d|mr-\d|left-\d|right-\d)\b/);
    });
});
