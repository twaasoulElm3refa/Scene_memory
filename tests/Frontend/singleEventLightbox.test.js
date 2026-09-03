import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const source = readFileSync(
    resolve(process.cwd(), "resources/js/views/home/single_event.vue"),
    "utf8"
);

describe("single event lightbox", () => {
    it("uses preview media URLs for the fullscreen preview", () => {
        expect(source).toContain("const getMediaPreviewUrl = (media) => {");
        expect(source).toContain("media?.preview_url || media?.previewUrl");
        expect(source).toContain("return getMediaUrl(media);");
        expect(source).toContain("const currentMediaUrl = computed(() => getMediaPreviewUrl(currentMedia.value));");
    });

    it("closes from the close button, backdrop, and Escape key", () => {
        expect(source).toContain('@click.self="closeLightbox"');
        expect(source).toContain('@click.stop.prevent="closeLightbox"');
        expect(source).toContain('event.key === "Escape" && lightboxOpen.value');
        expect(source).toContain('window.addEventListener("keydown", handleLightboxKeydown)');
        expect(source).toContain('window.removeEventListener("keydown", handleLightboxKeydown)');
    });
});
