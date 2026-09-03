import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import {
    cityNameExists,
    filterCityOptions,
    filterLocationOptions,
    normalizeCitySearch,
} from "../../resources/js/views/home/components/specialCoverageCityOptions";

const locales = ["ar", "de", "en", "es", "fa", "fr", "hi", "it", "ja", "ru", "tr", "ur", "zh"];
const requiredKeys = [
    "country",
    "city",
    "searchCity",
    "addNewCity",
    "createCity",
    "startDate",
    "eventType",
    "personalEvent",
    "publicEvent",
    "selectCountry",
    "selectCity",
    "noCitiesFound",
    "noCountriesFound",
];

describe("special coverage form", () => {
    it("searches translated city names and detects existing names", () => {
        const cities = [
            { id: 1, name: "Cairo", translation: { name: "القاهرة" } },
            { id: 2, name: "Alexandria", translation: { name: "الإسكندرية" } },
            { id: 3, name: "Giza", translation: { name: "الجيزة" } },
        ];

        expect(filterCityOptions(cities, "إسك", "ar")).toEqual([cities[1]]);
        expect(filterCityOptions(cities, "cai", "en")).toEqual([cities[0]]);
        expect(cityNameExists(cities, "alexandria", "en")).toBe(true);
        expect(cityNameExists(cities, "Luxor", "en")).toBe(false);
        expect(normalizeCitySearch("  New   Cairo  ")).toBe("New Cairo");
    });

    it("filters existing country names on the frontend", () => {
        const countries = [
            { id: 1, name: "Egypt", translation: { name: "مصر" } },
            { id: 2, name: "United Arab Emirates", translation: { name: "الإمارات العربية المتحدة" } },
            { id: 3, name: "France", translation: { name: "France" } },
        ];

        expect(filterLocationOptions(countries, "egy", "en")).toEqual([countries[0]]);
        expect(filterLocationOptions(countries, "الإمارات", "ar")).toEqual([countries[1]]);
        expect(filterLocationOptions(countries, "", "en")).toEqual(countries);
    });

    it("defines every new label in all 13 locales", () => {
        for (const locale of locales) {
            const messages = JSON.parse(readFileSync(
                resolve(process.cwd(), `resources/js/i18n/${locale}.json`),
                "utf8"
            ));
            const modal = messages.homeAudit.specialCoverage.modal;

            for (const key of requiredKeys) {
                expect(modal[key], `${locale}.${key}`).toBeTypeOf("string");
                expect(modal[key].trim(), `${locale}.${key}`).not.toBe("");
            }
        }
    });

    it("submits persisted location, date, and event type IDs", () => {
        const source = readFileSync(
            resolve(process.cwd(), "resources/js/views/home/components/SpecialCoverageSection.vue"),
            "utf8"
        );

        expect(source).toContain("LocationService.getCitiesByCountry(requestedCountryId)");
        expect(source).toContain("filterLocationOptions(countries.value, countrySearch.value, locale.value)");
        expect(source).toContain("SpecialCoverageRequestService.createCity");
        expect(source).toContain("city_id: Number(cityId.value)");
        expect(source).toContain("start_date: startDate.value");
        expect(source).toContain("event_type: eventType.value");
    });
});
