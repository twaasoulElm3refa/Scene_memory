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
    discoveryImageSources,
    discoveryVideoSources,
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
    it("uses the backend image preview and falls through to the full image on mobile", () => {
        vi.stubEnv("VITE_FORCE_MOBILE_API", "true");
        vi.stubEnv("VITE_API_URL", "https://dev.scemory.com");
        try {
            const image = normalizeDiscoveryResult({
                result_type: "image",
                id: 42,
                event_id: 7,
                event_slug: "sample",
                media_url: "events/full/example.jpg",
                preview_url: "events/preview/example.jpg",
                thumbnail_url: "events/preview/example.jpg",
                first_image: { full_url: "events/full/other.jpg" },
            });

            expect(discoveryImageSources(image)).toEqual([
                "https://dev.scemory.com/storage/events/preview/example.jpg",
                "https://dev.scemory.com/storage/events/full/example.jpg",
            ]);
            expect(image.image_url).toBe("https://dev.scemory.com/storage/events/preview/example.jpg");
        } finally {
            vi.unstubAllEnvs();
        }
    });

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
                        type: "video",
                        price: "17.50",
                        media_url: "events/video.mp4",
                        preview_url: "events/video-preview.mp4",
                        video_url: "events/video.mp4",
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
            media_id: 8,
            media_type: "video",
            price: "17.50",
        });
        expect(result.media_url).toBe("/storage/events/video.mp4");
        expect(result.preview_url).toBe("/storage/events/video-preview.mp4");
        expect(result.video_url).toBe("/storage/events/video.mp4");
    });

    it("uses the watermarked video preview before the original and falls back when absent", () => {
        expect(discoveryVideoSources({
            preview_url: "events/watermarked.mp4",
            video_url: "events/original.mp4",
        })).toEqual([
            "/storage/events/watermarked.mp4",
            "/storage/events/original.mp4",
        ]);

        expect(discoveryVideoSources({
            preview_url: "",
            video_url: "events/original.mp4",
        })).toEqual(["/storage/events/original.mp4"]);
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
