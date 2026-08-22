// @vitest-environment jsdom

import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@/services/ApiClient", () => ({
    default: {
        get: vi.fn(),
    },
}));

import api from "@/services/ApiClient";
import { EventService } from "../../resources/js/services/EventService/EventService";
import {
    discoveryResultsToMapEvents,
    eventFiltersToQuery,
    normalizeDiscoveryResult,
    normalizePaginatedResponse,
    queryToEventFilters,
} from "../../resources/js/services/EventService/eventSearchHelpers";

describe("discovery search requests", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.get.mockResolvedValue({ data: { data: { data: [] } } });
    });

    it("sends type, seed and all filters to the discovery endpoint", async () => {
        await EventService.searchEvents({
            type: "video",
            seed: 431,
            countryId: 1,
            cityId: 5,
            categoryId: 3,
            subCategoryId: 8,
            tagsIds: [4, 9],
            fromDate: "2026-08-01",
            toDate: "2026-08-31",
            searchQuery: "football",
            page: 2,
            perPage: 12,
        });

        expect(api.get).toHaveBeenCalledWith("/events/discovery/search", {
            params: {
                type: "video",
                seed: 431,
                country_id: 1,
                city_id: 5,
                category_id: 3,
                sub_category_id: 8,
                tags_id: [4, 9],
                from: "2026-08-01",
                to: "2026-08-31",
                q: "football",
                page: 2,
                per_page: 12,
            },
            paramsSerializer: { indexes: false },
        });
    });
});

describe("discovery URL and response helpers", () => {
    it("round-trips the complete search state through the query string", () => {
        const query = eventFiltersToQuery({
            type: "image",
            seed: 99,
            countryId: 2,
            cityId: 6,
            categoryId: 4,
            subCategoryId: 10,
            tagsIds: [3, 7],
            fromDate: "2026-01-01",
            toDate: "2026-01-31",
            searchQuery: "museum",
            page: 3,
            perPage: 16,
        }, { includePagination: true });

        expect(queryToEventFilters(query)).toMatchObject({
            type: "image",
            seed: 99,
            countryId: 2,
            cityId: 6,
            categoryId: 4,
            subCategoryId: 10,
            tagsIds: [3, 7],
            fromDate: "2026-01-01",
            toDate: "2026-01-31",
            searchQuery: "museum",
            page: 3,
            perPage: 16,
        });
    });

    it("normalizes unified results and keeps pagination metadata", () => {
        const response = {
            data: {
                data: {
                    data: [{
                        result_type: "video",
                        id: 8,
                        event_id: 4,
                        event_slug: "city-final",
                        title: "City final",
                        media_url: "events/video.mp4",
                        thumbnail_url: "events/poster.jpg",
                        city: { name: "Cairo" },
                    }],
                    current_page: 2,
                    last_page: 4,
                    per_page: 1,
                    total: 4,
                    from: 2,
                    to: 2,
                    seed: 71,
                    type: "video",
                },
            },
        };

        const paginator = normalizePaginatedResponse(response);
        const result = normalizeDiscoveryResult(paginator.results[0]);

        expect(paginator).toMatchObject({ currentPage: 2, lastPage: 4, total: 4, seed: 71, type: "video" });
        expect(result).toMatchObject({
            result_type: "video",
            event_id: 4,
            event_slug: "city-final",
            city_name: "Cairo",
        });
        expect(result.media_url).toBe("/storage/events/video.mp4");
    });

    it("deduplicates parent events before rendering map markers", () => {
        const markers = discoveryResultsToMapEvents([
            { result_type: "image", id: 1, event_id: 10, event_slug: "one", lattitude: "30", langitude: "31" },
            { result_type: "video", id: 2, event_id: 10, event_slug: "one", lattitude: "30", langitude: "31" },
            { result_type: "event", id: 11, event_id: 11, event_slug: "two", lattitude: "32", langitude: "33" },
        ]);

        expect(markers).toHaveLength(2);
        expect(markers.map((event) => event.id)).toEqual([10, 11]);
    });
});
