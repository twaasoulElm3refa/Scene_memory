// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createApp, nextTick } from "vue";
import { createI18n } from "vue-i18n";
import { createMemoryHistory, createRouter } from "vue-router";
import { existsSync, readFileSync } from "node:fs";
import { resolve } from "node:path";
import weeklyCompetition from "../../resources/js/i18n/weeklyCompetition";
import WeeklyLeaderboardPreview from "../../resources/js/views/home/components/WeeklyLeaderboardPreview.vue";
import ScemoryExperienceTabs from "../../resources/js/views/home/components/ScemoryExperienceTabs.vue";

vi.mock("../../resources/js/services/leaderboardService", () => ({
    getWeeklyLeaderboardPreview: vi.fn(() => Promise.resolve({ data: { users: [] } })),
    getWeeklyLeaderboard: vi.fn(() => Promise.resolve({ data: { users: [] } })),
}));

const locales = ["ar", "en", "fr", "de", "ru", "es", "it", "hi", "ja", "fa", "zh", "ur", "tr"];
const json = import.meta.glob("../../resources/js/i18n/*.json", { eager: true, import: "default" });
const messages = Object.fromEntries(Object.entries(json).map(([file, value]) => {
    const lang = file.split("/").pop().replace(".json", "");
    return [lang, { ...value, weeklyCompetition: weeklyCompetition[lang] }];
}));

let app;
let observers;

async function mount(component, locale = "en") {
    const host = document.createElement("div");
    document.body.appendChild(host);
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: "/:pathMatch(.*)*", component: { template: "<div />" } }] });
    app = createApp(component);
    app.use(createI18n({ legacy: false, locale, fallbackLocale: "en", messages, missingWarn: false, fallbackWarn: false }));
    app.use(router);
    app.mount(host);
    for (let i = 0; i < 10; i++) await Promise.resolve();
    await nextTick();
    return host;
}

beforeEach(() => {
    observers = [];
    window.IntersectionObserver = class {
        constructor(callback, options) { this.callback = callback; this.options = options; observers.push(this); }
        observe(element) { this.element = element; }
        disconnect() { this.disconnected = true; }
    };
});

afterEach(() => {
    app?.unmount();
    document.body.innerHTML = "";
    delete window.IntersectionObserver;
});

describe("homepage leaderboard video", () => {
    it("keeps autoplay, muted, loop and playsinline and shows the poster before loading", async () => {
        const host = await mount(WeeklyLeaderboardPreview);
        const video = host.querySelector("video.weekly-leaderboard__video");

        expect(video).not.toBeNull();
        expect(video.autoplay).toBe(true);
        expect(video.muted).toBe(true);
        expect(video.loop).toBe(true);
        expect(video.hasAttribute("playsinline")).toBe(true);
        expect(video.getAttribute("aria-label")).toBe("Scemory preview");
        expect(video.getAttribute("poster")).toBe("/video/Final_Scemory-poster.jpg");
        // Nothing is downloaded until the section approaches the viewport.
        expect(video.hasAttribute("src")).toBe(false);
        expect(observers[0].options.rootMargin).toBe("300px 0px");
    });

    it("loads when near the viewport and pauses when it leaves or the tab is hidden", async () => {
        const host = await mount(WeeklyLeaderboardPreview);
        const video = host.querySelector("video");
        observers[0].callback([{ isIntersecting: true }]);
        await nextTick();
        expect(video.getAttribute("src")).toBe("/video/Final_Scemory-web.mp4");

        const pause = vi.fn();
        Object.defineProperty(video, "paused", { configurable: true, get: () => false });
        video.pause = pause;
        observers[0].callback([{ isIntersecting: false }]);
        expect(pause).toHaveBeenCalledTimes(1);

        observers[0].callback([{ isIntersecting: true }]);
        Object.defineProperty(document, "hidden", { configurable: true, get: () => true });
        document.dispatchEvent(new Event("visibilitychange"));
        expect(pause).toHaveBeenCalledTimes(2);
        delete document.hidden;
    });

    it("plays immediately where IntersectionObserver is unavailable", async () => {
        delete window.IntersectionObserver;
        const host = await mount(WeeklyLeaderboardPreview);
        expect(host.querySelector("video").getAttribute("src")).toBe("/video/Final_Scemory-web.mp4");
    });

    it("ships the web encode and poster and keeps the original for rollback", () => {
        for (const file of ["Final_Scemory.mp4", "Final_Scemory-web.mp4", "Final_Scemory-poster.jpg"]) {
            expect(existsSync(resolve(process.cwd(), "public/video", file)), file).toBe(true);
        }
    });
});

describe("Scemory experience tab photos", () => {
    it("offers AVIF and WebP at three widths with the PNG as fallback in every locale", async () => {
        for (const locale of locales) {
            const host = await mount(ScemoryExperienceTabs, locale);
            const media = host.querySelector(".experience-media");
            const picture = media.querySelector("picture");
            const img = media.querySelector("picture > img");

            expect(img.getAttribute("src")).toBe("/images/photo1.png");
            expect(img.getAttribute("alt").length).toBeGreaterThan(0);
            expect(img.getAttribute("loading")).toBe("lazy");
            expect([...picture.querySelectorAll("source")].map((source) => source.type)).toEqual(["image/avif", "image/webp"]);
            for (const source of picture.querySelectorAll("source")) {
                const extension = source.type.split("/")[1];
                expect(source.getAttribute("srcset")).toBe([960, 1600, 2624].map((w) => `/images/optimized/photo1-${w}.${extension} ${w}w`).join(", "));
                expect(source.getAttribute("sizes")).toBe("(max-width: 900px) 100vw, 70vw");
            }
            // The existing image rules still target the <img>.
            expect(media.querySelector(".experience-media img")).toBe(img);
            app.unmount();
            app = null;
            document.body.innerHTML = "";
        }
    });

    it("has every referenced optimized file on disk", () => {
        for (const photo of ["photo1", "photo2", "photo3"]) {
            for (const width of [960, 1600, 2624]) {
                for (const format of ["avif", "webp"]) {
                    expect(existsSync(resolve(process.cwd(), `public/images/optimized/${photo}-${width}.${format}`))).toBe(true);
                }
            }
        }
        const source = readFileSync(resolve(process.cwd(), "resources/js/views/home/components/ScemoryExperienceTabs.vue"), "utf8");
        expect(source).toMatch(/\.experience-media img \{[^}]*position: absolute;[^}]*object-fit: cover;/s);
    });
});
