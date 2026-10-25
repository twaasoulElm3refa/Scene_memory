import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const localeFiles = import.meta.glob('../../resources/js/i18n/*.json', { eager: true, import: 'default' });
const messages = Object.fromEntries(
    Object.entries(localeFiles).map(([file, value]) => [file.split('/').pop().replace('.json', ''), value])
);
const locales = ['ar', 'en', 'fr', 'de', 'ru', 'es', 'it', 'hi', 'ja', 'fa', 'zh', 'ur', 'tr'];
const savingsKeys = [
    'title', 'description', 'purchasedOn', 'planPaid', 'redeemedCartValue',
    'tierAllowance', 'sharedQuota', 'savedSoFar', 'breakEven', 'cartWouldSave',
    'incompleteHistory', 'unusedCreditsNote', 'noPlans', 'unavailable',
    'estimateTitle', 'planPrice', 'comparableCartValue', 'cartItemsMatched',
    'estimatedSavings', 'estimatedCartSaving', 'estimateDisclaimer',
];

describe('plan vs cart savings tracker', () => {
    it('has every savings message in all 13 supported languages', () => {
        expect(Object.keys(messages).sort()).toEqual([...locales].sort());

        for (const locale of locales) {
            const savings = messages[locale].mediaCredits.savings;
            for (const key of savingsKeys) {
                expect(savings[key], `${locale}: ${key}`).toEqual(expect.any(String));
                expect(savings[key].trim().length, `${locale}: ${key}`).toBeGreaterThan(0);
                if (locale !== 'en') {
                    expect(savings[key], `${locale}: ${key}`).not.toBe(messages.en.mediaCredits.savings[key]);
                }
            }
        }
    });

    it('renders authoritative dashboard values and requests the cart estimate endpoint', () => {
        const profile = readFileSync(resolve(process.cwd(), 'resources/js/views/home/profile.vue'), 'utf8');
        const plan = readFileSync(resolve(process.cwd(), 'resources/js/views/home/show_plan.vue'), 'utf8');
        const service = readFileSync(resolve(process.cwd(), 'resources/js/services/planService/planService.js'), 'utf8');

        expect(profile).toContain('grant.comparison.plan_price_paid_minor');
        expect(profile).toContain('grant.comparison.redeemed_cart_value_minor');
        expect(profile).toContain('grant.comparison.difference_minor');
        expect(profile).toContain('grant.comparison.has_unused_credits');
        expect(plan).toContain('savingsEstimate.comparable_cart_value_minor');
        expect(service).toContain('/savings-estimate');
    });
});
