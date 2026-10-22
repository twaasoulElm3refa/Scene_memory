import { FacebookAuthService } from './AuthService/FacebookAuthService';
import { isMobileRuntime } from './runtimeUrls';

const codes = new Set([
    'unavailable', 'cancelled', 'invalid_state', 'expired', 'identity_invalid', 'callback_failed',
    'disabled', 'verification_required', 'linking_required', 'busy', 'email_unavailable',
    'otp_expired', 'otp_invalid', 'otp_attempts', 'otp_cooldown', 'native_unavailable',
]);
export const facebookErrorKey = code => `facebookAuth.${codes.has(code) ? code : 'callback_failed'}`;

export const safeFacebookRedirect = (requested, lang) => {
    const fallback = `/${lang}/home`;
    if (typeof requested !== 'string' || !requested.startsWith(`/${lang}/`)
        || /[\\\u0000-\u0020\u007f]/.test(requested)) return fallback;
    try {
        const url = new URL(requested, window.location.origin);
        return url.origin === window.location.origin && url.pathname.startsWith(`/${lang}/`)
            && !url.pathname.startsWith(`/${lang}/auth`)
            ? url.pathname + url.search + url.hash : fallback;
    } catch {
        return fallback;
    }
};

export const startFacebookAuth = async lang => {
    // This flow requires the Laravel browser session; never hand a native WebView
    // a browser session token through a URL. Native social login stays separate.
    if (isMobileRuntime()) throw new Error('native_unavailable');
    const response = await FacebookAuthService.start(lang);
    const url = new URL(response.data.url);
    if (url.origin !== 'https://www.facebook.com' || url.username || url.password) throw new Error('unavailable');
    window.location.assign(url.href);
};
