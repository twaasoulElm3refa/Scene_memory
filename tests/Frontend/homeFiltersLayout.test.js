import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const source = readFileSync(resolve(process.cwd(), "resources/js/views/home/home.vue"), "utf8");

function mediaBlock(maxWidth) {
    const marker = `@media (max-width: ${maxWidth}px)`;
    const start = source.indexOf(marker);

    expect(start).toBeGreaterThanOrEqual(0);

    const openingBrace = source.indexOf("{", start);
    let depth = 0;

    for (let index = openingBrace; index < source.length; index += 1) {
        if (source[index] === "{") {
            depth += 1;
        }

        if (source[index] === "}") {
            depth -= 1;

            if (depth === 0) {
                return source.slice(openingBrace + 1, index);
            }
        }
    }

    throw new Error(`Could not parse ${marker}`);
}

describe("home map overlay layout", () => {
    it("keeps the real map, search, filters, and trending events in one section", () => {
        const experience = source.slice(source.indexOf('<section id="explore-events"'), source.indexOf('<!-- EVENTS RESULTS -->'));

        expect(experience.match(/<MapSection\b/g)).toHaveLength(1);
        expect(experience).toContain('<UnifiedSearchBar');
        expect(experience).toContain('<FiltersSection');
        expect(experience).toContain('<TrendingEventsSection');
        expect(experience).toContain('@search="handleSearchClick"');
        expect(experience).toContain('@select-country="selectCountry"');
        expect(experience).toContain('@category-changed="handleMainCategoryChange"');
        expect(experience).toContain('@click="mobileFiltersOpen = !mobileFiltersOpen"');
        expect(experience).not.toContain('heroVideoUrl');
    });

    it("turns the floating filters into a full-width phone sheet", () => {
        const phoneBlock = mediaBlock(640);

        expect(phoneBlock).toMatch(/\.home-discovery-filters-column\s*\{[^}]*bottom:\s*0/i);
        expect(phoneBlock).toMatch(/\.home-discovery-filters-column\s*\{[^}]*width:\s*100%/i);
        expect(phoneBlock).toMatch(/\.home-discovery-filters\s+:deep\(\.filter-controls-grid\)\s*\{[^}]*minmax\(0, 1fr\)/i);
    });
});
