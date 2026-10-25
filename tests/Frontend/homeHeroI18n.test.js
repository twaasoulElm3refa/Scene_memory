import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const localeFiles = import.meta.glob('../../resources/js/i18n/*.json', { eager: true, import: 'default' });
const messages = Object.fromEntries(
    Object.entries(localeFiles).map(([file, value]) => [file.split('/').pop().replace('.json', ''), value])
);
const locales = ['ar', 'en', 'fr', 'de', 'ru', 'es', 'it', 'hi', 'ja', 'fa', 'zh', 'ur', 'tr'];
const heroKeys = [
    'badge', 'title', 'titleHighlight', 'description',
    'startSelling', 'exploreAndBuy', 'bottomText', 'searchPlaceholder',
];

describe('homepage map hero copy', () => {
    it('has localized, nonempty copy in all 13 supported languages', () => {
        expect(Object.keys(messages).sort()).toEqual([...locales].sort());

        for (const locale of locales) {
            const hero = messages[locale].homeAudit.home;
            for (const key of heroKeys) {
                expect(hero[key], `${locale}: ${key}`).toEqual(expect.any(String));
                expect(hero[key].trim().length, `${locale}: ${key}`).toBeGreaterThan(0);
                if (locale !== 'en') {
                    expect(hero[key], `${locale}: ${key}`).not.toBe(messages.en.homeAudit.home[key]);
                }
            }
        }
    });

    it('sends selling and buying CTAs to their existing routes', () => {
        const source = readFileSync(resolve(process.cwd(), 'resources/js/views/home/home.vue'), 'utf8');

        expect(source).toMatch(/<RouterLink :to="'\/' \+ lang \+ '\/add_event'" class="search-events-btn">[\s\S]*?homeAudit\.home\.startSelling[\s\S]*?<\/RouterLink>/);
        expect(source).toMatch(/<RouterLink :to="'\/' \+ lang \+ '\/events'" class="home-map-upload">[\s\S]*?homeAudit\.home\.exploreAndBuy[\s\S]*?<\/RouterLink>/);
    });
});
