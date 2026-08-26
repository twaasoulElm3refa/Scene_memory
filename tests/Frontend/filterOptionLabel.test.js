import { describe, expect, it } from "vitest";

import { getFilterOptionLabel } from "../../resources/js/views/home/components/filterOptionLabel";

describe("home filter option labels", () => {
    it("uses the current locale translation when available", () => {
        expect(getFilterOptionLabel({
            id: 12,
            name: "Fallback name",
            translation: { name: "Translated name" },
        })).toBe("Translated name");
    });

    it("falls back safely when a locale translation is missing", () => {
        expect(getFilterOptionLabel({ id: 12, name: "Fallback name", translation: null }))
            .toBe("Fallback name");
        expect(getFilterOptionLabel({ id: 13, translation: null })).toBe("#13");
        expect(getFilterOptionLabel(null)).toBe("");
    });
});
