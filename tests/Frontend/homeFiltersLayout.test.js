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

describe("home filters layout CSS", () => {
    it("keeps the filters visible in the tablet one-column layout", () => {
        const tabletBlock = mediaBlock(991);

        expect(tabletBlock).toContain(".home-discovery-filters-column");
        expect(tabletBlock).not.toMatch(/\.home-mobile-filter-toggle\s*\{/);
        expect(tabletBlock).not.toMatch(/\.home-discovery-filters-column\s*\{[^}]*display:\s*none/i);
        expect(tabletBlock).not.toMatch(/\.home-discovery-filters-column\.is-open\s*\{/);
    });

    it("limits the collapsed filter drawer to phone widths", () => {
        const phoneBlock = mediaBlock(640);

        expect(phoneBlock).toMatch(/\.home-mobile-filter-toggle\s*\{[^}]*display:\s*flex/i);
        expect(phoneBlock).toMatch(/\.home-discovery-filters-column\s*\{[^}]*display:\s*none/i);
        expect(phoneBlock).toMatch(/\.home-discovery-filters-column\.is-open\s*\{[^}]*display:\s*block/i);
    });
});
