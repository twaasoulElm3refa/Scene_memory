import { AuthService } from './AuthService/AuthService';
import { googleAuthOrigin, isMobileRuntime } from './runtimeUrls';
import { Capacitor } from '@capacitor/core';
import { Browser } from '@capacitor/browser';
import { clearGoogleOAuthVerifier, setGoogleOAuthVerifier } from './authTokenStorage';

export const safePostAuthRedirect = (requested, lang) => {
    const fallback = `/${lang}/home`;
    if (typeof requested !== 'string' || !requested.startsWith(`/${lang}/`)
        || /[\\\u0000-\u0020\u007f]/.test(requested)) return fallback;
    try {
        const parsed = new URL(requested, window.location.origin);
        if (parsed.origin !== window.location.origin || !parsed.pathname.startsWith(`/${lang}/`)
            || parsed.pathname.startsWith(`/${lang}/auth`)) return fallback;
        return parsed.pathname + parsed.search + parsed.hash;
    } catch {
        return fallback;
    }
};

const ERROR_CODES = new Set([
    'cancelled', 'expired_state', 'invalid_state', 'oauth_failed', 'callback_failed',
    'identity_invalid', 'disabled', 'identity_conflict', 'linking_required',
    'account_login_required', 'recovery_required', 'recovery_web_required',
    'invalid_recovery_code', 'recovery_expired', 'recovery_attempts_exceeded',
    'recovery_rate_limited', 'recovery_unavailable', 'unavailable', 'busy',
]);
export const googleErrorKey = (code) => `googleAuth.${ERROR_CODES.has(code) ? code : 'callback_failed'}`;

export const startGoogleAuth = async (lang) => {
    if (isMobileRuntime()) {
        if (!googleAuthOrigin()) throw new Error('unavailable');
        try {
            const encode = (bytes) => btoa(String.fromCharCode(...new Uint8Array(bytes)))
                .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
            const verifier = encode(crypto.getRandomValues(new Uint8Array(32)));
            const challenge = encode(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier)));
            await setGoogleOAuthVerifier(verifier);
            sessionStorage.setItem('mobile_google_oauth_pending', String(Date.now()));
            const url = `${googleAuthOrigin()}/api/v1/users/google-login?${new URLSearchParams({ mobile: '1', lang, code_challenge: challenge })}`;
            if (Capacitor.isNativePlatform()) await Browser.open({ url });
            else window.location.assign(url);
        } catch (error) {
            sessionStorage.removeItem('mobile_google_oauth_pending');
            await clearGoogleOAuthVerifier();
            throw error;
        }
    } else {
        const response = await AuthService.googleLogin({ lang });
        // The backend supplies this URL, but also restrict navigation at this boundary.
        const url = new URL(response.data.url);
        if (url.origin !== 'https://accounts.google.com' || url.username || url.password) throw new Error('unavailable');
        window.location.assign(url.href);
    }
};
