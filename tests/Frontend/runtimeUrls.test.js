import { afterEach, describe, expect, it, vi } from "vitest";
import { backendOrigin, googleAuthOrigin, mobileAppLinkOrigin } from "../../resources/js/services/runtimeUrls";

describe("mobile runtime URLs", () => {
    afterEach(() => {
        vi.unstubAllEnvs();
    });

    it("keeps development API traffic separate from production Google auth", () => {
        vi.stubEnv("VITE_API_URL", "https://dev.scemory.com/");
        vi.stubEnv("VITE_GOOGLE_AUTH_ORIGIN", "https://scemory.com/");
        vi.stubEnv("VITE_APP_LINK_ORIGIN", "https://scemory.com/");

        expect(backendOrigin()).toBe("https://dev.scemory.com");
        expect(googleAuthOrigin()).toBe("https://scemory.com");
        expect(mobileAppLinkOrigin()).toBe("https://scemory.com");
    });

    it("uses the API origin when a dedicated Google origin is not configured", () => {
        vi.stubEnv("VITE_API_URL", "https://api.example.test/");
        vi.stubEnv("VITE_GOOGLE_AUTH_ORIGIN", "");

        expect(googleAuthOrigin()).toBe("https://api.example.test");
    });
});
