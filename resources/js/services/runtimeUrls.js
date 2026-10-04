import { Capacitor } from "@capacitor/core";

export const isMobileRuntime = () =>
    Capacitor.isNativePlatform() || import.meta.env.VITE_FORCE_MOBILE_API === "true";

export const backendOrigin = () =>
    String(import.meta.env.VITE_API_URL || "").replace(/\/+$/, "");

export const mobileAppLinkOrigin = () =>
    String(import.meta.env.VITE_APP_LINK_ORIGIN || "https://scemory.com").replace(/\/+$/, "");

export const googleAuthOrigin = () =>
    String(import.meta.env.VITE_GOOGLE_AUTH_ORIGIN || backendOrigin()).replace(/\/+$/, "");

export const toAssetUrl = (path) => {
    if (!path || !isMobileRuntime() || !backendOrigin() || !path.startsWith("/")) {
        return path;
    }
    return `${backendOrigin()}${path}`;
};
