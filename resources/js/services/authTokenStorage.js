import { Capacitor } from "@capacitor/core";
import { SecureStoragePlugin } from "capacitor-secure-storage-plugin";

const TOKEN_KEY = "scemory_auth_token";
const GOOGLE_VERIFIER_KEY = "scemory_google_oauth_verifier";
const LEGACY_TOKEN_KEY = "auth_token";

let nativeToken = "";

const isNative = () => Capacitor.isNativePlatform();

export const getAuthToken = () => {
    if (isNative()) {
        return nativeToken;
    }

    return localStorage.getItem(LEGACY_TOKEN_KEY) || "";
};

export const restoreAuthToken = async () => {
    if (!isNative()) {
        return getAuthToken();
    }

    const legacyToken = localStorage.getItem(LEGACY_TOKEN_KEY) || "";

    try {
        const result = await SecureStoragePlugin.get({ key: TOKEN_KEY });
        nativeToken = result.value || "";
    } catch {
        if (legacyToken) {
            await SecureStoragePlugin.set({ key: TOKEN_KEY, value: legacyToken });
            nativeToken = legacyToken;
        } else {
            nativeToken = "";
        }
    }

    localStorage.removeItem(LEGACY_TOKEN_KEY);

    return nativeToken;
};

export const setAuthToken = async (token) => {
    const normalizedToken = String(token || "");

    if (!normalizedToken) {
        throw new Error("Cannot store an empty authentication token.");
    }

    if (isNative()) {
        await SecureStoragePlugin.set({ key: TOKEN_KEY, value: normalizedToken });
        nativeToken = normalizedToken;
        localStorage.removeItem(LEGACY_TOKEN_KEY);
        return;
    }

    localStorage.setItem(LEGACY_TOKEN_KEY, normalizedToken);
};

export const clearAuthToken = async () => {
    nativeToken = "";
    localStorage.removeItem(LEGACY_TOKEN_KEY);

    if (!isNative()) {
        return;
    }

    try {
        await SecureStoragePlugin.remove({ key: TOKEN_KEY });
    } catch {
    }
};

export const setGoogleOAuthVerifier = async (verifier) => {
    await SecureStoragePlugin.set({
        key: GOOGLE_VERIFIER_KEY,
        value: String(verifier || ""),
    });
};

export const getGoogleOAuthVerifier = async () => {
    try {
        const result = await SecureStoragePlugin.get({ key: GOOGLE_VERIFIER_KEY });
        return result.value || "";
    } catch {
        return "";
    }
};

export const clearGoogleOAuthVerifier = async () => {
    try {
        await SecureStoragePlugin.remove({ key: GOOGLE_VERIFIER_KEY });
    } catch {
    }
};
