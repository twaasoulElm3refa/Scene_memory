// @vitest-environment jsdom

import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
    native: true,
    values: new Map(),
}));

vi.mock("@capacitor/core", () => ({
    Capacitor: {
        isNativePlatform: () => mocks.native,
    },
}));

vi.mock("capacitor-secure-storage-plugin", () => ({
    SecureStoragePlugin: {
        get: vi.fn(async ({ key }) => {
            if (!mocks.values.has(key)) throw new Error("Missing key");
            return { value: mocks.values.get(key) };
        }),
        set: vi.fn(async ({ key, value }) => {
            mocks.values.set(key, value);
            return { value: true };
        }),
        remove: vi.fn(async ({ key }) => {
            mocks.values.delete(key);
            return { value: true };
        }),
    },
}));

describe("mobile authentication token storage", () => {
    beforeEach(() => {
        mocks.native = true;
        mocks.values.clear();
        localStorage.clear();
        vi.resetModules();
    });

    it("restores the bearer token from secure storage after an app restart", async () => {
        const firstRuntime = await import("../../resources/js/services/authTokenStorage");

        await firstRuntime.setAuthToken("mobile-bearer-token");

        expect(localStorage.getItem("auth_token")).toBeNull();
        expect(mocks.values.get("scemory_auth_token")).toBe("mobile-bearer-token");

        vi.resetModules();
        const restartedRuntime = await import("../../resources/js/services/authTokenStorage");

        expect(restartedRuntime.getAuthToken()).toBe("");
        await restartedRuntime.restoreAuthToken();
        expect(restartedRuntime.getAuthToken()).toBe("mobile-bearer-token");
    });

    it("migrates and removes a legacy WebView localStorage token", async () => {
        localStorage.setItem("auth_token", "legacy-token");
        const storage = await import("../../resources/js/services/authTokenStorage");

        await storage.restoreAuthToken();

        expect(storage.getAuthToken()).toBe("legacy-token");
        expect(mocks.values.get("scemory_auth_token")).toBe("legacy-token");
        expect(localStorage.getItem("auth_token")).toBeNull();
    });

    it("keeps the existing localStorage behavior on the web", async () => {
        mocks.native = false;
        const storage = await import("../../resources/js/services/authTokenStorage");

        await storage.setAuthToken("web-token");

        expect(storage.getAuthToken()).toBe("web-token");
        expect(localStorage.getItem("auth_token")).toBe("web-token");
    });
});
