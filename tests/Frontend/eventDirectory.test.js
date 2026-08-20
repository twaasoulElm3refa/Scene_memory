// @vitest-environment jsdom

import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@/services/ApiClient", () => ({
    default: {
        get: vi.fn(),
    },
}));

import api from "@/services/ApiClient";
import eventDirectoryMessages from "../../resources/js/i18n/eventDirectory";
import { EventService } from "../../resources/js/services/EventService/EventService";
import {
    EVENT_FALLBACK_IMAGE,
    formatEventDate,
    getEventImageCandidate,
    getEventImageUrl,
    getMediaRawPath,
    getStorageUrl,
    isMediaVideo,
} from "../../resources/js/services/EventService/eventMedia";

describe("event directory requests", () => {
    beforeEach(() => vi.clearAllMocks());

    it("keeps normal and historical APIs separate while forwarding server filters", () => {
        EventService.getAll(3, "real", { q: "museum", sort: "oldest", country_id: 7 });
        EventService.getHistorical(2, { q: "archive", sort: "title", category_id: 4 });

        expect(api.get).toHaveBeenNthCalledWith(1, "/events", {
            params: { q: "museum", sort: "oldest", country_id: 7, page: 3, is_real: 1 },
        });
        expect(api.get).toHaveBeenNthCalledWith(2, "/events/historical", {
            params: { q: "archive", sort: "title", category_id: 4, page: 2 },
        });
    });
});

describe("shared event directory presentation helpers", () => {
    it("provides the complete directory translation contract in all 13 locales", () => {
        const locales = ["ar", "de", "en", "es", "fa", "fr", "hi", "it", "ja", "ru", "tr", "ur", "zh"];
        const expectedKeys = Object.keys(eventDirectoryMessages.en).sort();

        expect(Object.keys(eventDirectoryMessages).sort()).toEqual(locales.sort());
        locales.forEach((locale) => {
            expect(Object.keys(eventDirectoryMessages[locale]).sort()).toEqual(expectedKeys);
            expect(Object.keys(eventDirectoryMessages[locale].types).sort()).toEqual(["all", "general", "real"]);
        });
    });

    it("resolves the same media shapes for both directories and uses one fallback", () => {
        const event = {
            first_image: { preview_url: "events/preview/photo.webp" },
            images: [{ url: "ignored.jpg" }],
        };

        expect(getEventImageCandidate(event)).toBe("events/preview/photo.webp");
        expect(getEventImageUrl(event)).toContain("/storage/events/preview/photo.webp");
        expect(getEventImageUrl({})).toBe(EVENT_FALLBACK_IMAGE);
    });

    it("prioritizes full_url so playable video files are not hidden behind previews", () => {
        const media = {
            type: "video",
            preview_url: "events/previews/poster.jpg",
            full_url: "events/videos/clip.mp4",
        };

        expect(getMediaRawPath(media)).toBe("events/videos/clip.mp4");
        expect(getStorageUrl(media)).toContain("/storage/events/videos/clip.mp4");
    });

    it("detects videos by normalized type, explicit flags, and legacy URL extensions", () => {
        expect(isMediaVideo({ type: " VIDEO ", full_url: "events/videos/clip.mov?token=123" })).toBe(true);
        expect(isMediaVideo({ is_video: true, full_url: "events/media/file.bin" })).toBe(true);
        expect(isMediaVideo({ full_url: "events/videos/archive.m4v#fragment" })).toBe(true);
        expect(isMediaVideo({ video: "events/videos/legacy.webm" })).toBe(true);
        expect(isMediaVideo({ type: "image", full_url: "events/images/photo.mp4.jpg" })).toBe(false);
        expect(isMediaVideo({ preview_url: "events/images/photo.webp" })).toBe(false);
    });

    it("formats valid dates through Intl for every supported locale", () => {
        const supportedLocales = ["ar", "de", "en", "es", "fa", "fr", "hi", "it", "ja", "ru", "tr", "ur", "zh"];

        supportedLocales.forEach((locale) => {
            const formatted = formatEventDate("2026-08-17T12:00:00Z", locale);
            expect(formatted).not.toBe("—");
            expect(formatted.length).toBeGreaterThan(3);
        });

        expect(formatEventDate("not-a-date", "en")).toBe("—");
    });
});
