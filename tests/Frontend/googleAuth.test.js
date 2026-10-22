// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick, h } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';
import { readFileSync } from 'node:fs';
import GoogleAuthButton from '../../resources/js/components/auth/GoogleAuthButton.vue';
import Register from '../../resources/js/views/auth/register.vue';
import Callback from '../../resources/js/views/auth/GoggleCallback.vue';
import { AuthService } from '../../resources/js/services/AuthService/AuthService';
import { safePostAuthRedirect, googleErrorKey } from '../../resources/js/services/googleAuth';
import { getGoogleOAuthVerifier, setAuthToken, clearGoogleOAuthVerifier } from '../../resources/js/services/authTokenStorage';
import { isMobileRuntime } from '../../resources/js/services/runtimeUrls';

vi.mock('../../resources/js/services/AuthService/AuthService', () => ({ AuthService: {
    googleLogin: vi.fn(), exchangeGoogleCode: vi.fn(), exchangeMobileGoogleCode: vi.fn(),
    login: vi.fn(), register: vi.fn(),
} }));
vi.mock('../../resources/js/services/authTokenStorage', () => ({
    setAuthToken: vi.fn(), getGoogleOAuthVerifier: vi.fn(),
    setGoogleOAuthVerifier: vi.fn(), clearGoogleOAuthVerifier: vi.fn(),
}));
vi.mock('../../resources/js/services/runtimeUrls', () => ({
    isMobileRuntime: vi.fn(() => false), googleAuthOrigin: () => 'https://scemory.com', toAssetUrl: value => value,
}));
vi.mock('@capacitor/core', () => ({ Capacitor: { isNativePlatform: () => false } }));
vi.mock('@capacitor/browser', () => ({ Browser: { open: vi.fn() } }));

const existing = import.meta.glob('../../resources/js/i18n/*.json', { eager: true, import: 'default' });
const messages = Object.fromEntries(Object.entries(existing).map(([file, value]) => [file.split('/').pop().replace('.json', ''), value]));
let app;
const settle = async () => { for (let i = 0; i < 4; i++) { await new Promise(resolve => setTimeout(resolve, 0)); await nextTick(); } };
const success = (role = 'user') => ({ data: { status: 'success', token: 'post-body-token', role, user: { id: 7, role, name: 'Scemory User', email: 'local@example.test' }, is_profile_complete: true } });
const code = 'a'.repeat(64);

async function mount(component, props = {}, lang = 'en', url = `/${lang}/auth`) {
    const target = document.createElement('div');
    document.body.append(target);
    const router = createRouter({ history: createMemoryHistory(), routes: [
        { path: '/:lang/auth', name: 'auth', component: Register },
        { path: '/:lang/auth/google-callback', name: 'google-callback', component: Callback },
        { path: '/:pathMatch(.*)*', component: { render: () => h('div') } },
    ] });
    await router.push(url);
    app = createApp(component, props);
    app.use(router).use(createI18n({ legacy: false, locale: lang, fallbackLocale: false, messages }));
    app.mount(target);
    await settle();
    return { target, router };
}

beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear(); sessionStorage.clear();
    isMobileRuntime.mockReturnValue(false);
    AuthService.exchangeGoogleCode.mockResolvedValue(success());
    AuthService.exchangeMobileGoogleCode.mockResolvedValue(success());
    getGoogleOAuthVerifier.mockResolvedValue('v'.repeat(43));
    setAuthToken.mockResolvedValue();
});
afterEach(() => { app?.unmount(); document.body.innerHTML = ''; });

describe('Google authentication UI', () => {
    it.each(Object.keys(messages))('renders localized Google branding, divider and direction in %s', async lang => {
        const { target } = await mount(GoogleAuthButton, {}, lang);
        expect(target.textContent).toContain(messages[lang].googleAuth.continue);
        expect(target.textContent).toContain(messages[lang].googleAuth.or);
        expect(target.querySelector('.google-auth').dir).toBe(['ar', 'fa', 'ur'].includes(lang) ? 'rtl' : 'ltr');
        const button = target.querySelector('button');
        expect(button.type).toBe('button');
        expect(button.querySelector('svg').getAttribute('aria-hidden')).toBe('true');
        expect(target.textContent).not.toContain('googleAuth.');
    });

    it('disables the button and announces loading without submitting a password form', async () => {
        const proceed = vi.fn();
        const { target } = await mount(GoogleAuthButton, { loading: true, onContinue: proceed });
        const button = target.querySelector('button');
        button.click();
        expect(button.disabled).toBe(true);
        expect(button.getAttribute('aria-busy')).toBe('true');
        expect(target.textContent).toContain(messages.en.googleAuth.signingIn);
        expect(proceed).not.toHaveBeenCalled();
    });

    it('keeps one shared Google button outside both login and registration forms', async () => {
        const { target } = await mount(Register);
        expect(target.querySelectorAll('.google-button')).toHaveLength(1);
        expect(target.querySelector('form .google-button')).toBeNull();
        target.querySelectorAll('.nav-link')[1].click(); await settle();
        expect(target.querySelectorAll('.google-button')).toHaveLength(1);
        expect(target.querySelector('input[type=date]').required).toBe(true);
    });

    it('shows a localized start error and allows retry without submitting email credentials', async () => {
        AuthService.googleLogin.mockRejectedValue({ response: { data: { error_code: 'unavailable' } } });
        const { target } = await mount(Register, {}, 'ar');
        target.querySelector('.google-button').click(); await settle();
        expect(target.querySelector('[role=alert]').textContent).toBe(messages.ar.googleAuth.unavailable);
        expect(target.querySelector('.google-button').disabled).toBe(false);
        expect(AuthService.login).not.toHaveBeenCalled();
    });

    it('prevents repeated clicks while starting authentication', async () => {
        let reject;
        AuthService.googleLogin.mockImplementation(() => new Promise((_, r) => { reject = r; }));
        const { target } = await mount(Register);
        target.querySelector('.google-button').click();
        target.querySelector('.google-button').click(); await settle();
        expect(AuthService.googleLogin).toHaveBeenCalledTimes(1);
        expect(target.querySelector('.google-button').disabled).toBe(true);
        reject(new Error()); await settle();
    });

    it('exchanges a code in POST, strips callback parameters, updates auth state and honors a safe return path', async () => {
        sessionStorage.setItem('post_auth_redirect', '/en/events?country=EG');
        const login = vi.fn(); window.addEventListener('login', login);
        const { router } = await mount(Callback, {}, 'en', `/en/auth/google-callback?code=${code}&role=admin&token=hostile`);
        expect(AuthService.exchangeGoogleCode).toHaveBeenCalledWith(code, { country: '', date_of_birth: '' });
        expect(setAuthToken).toHaveBeenCalledWith('post-body-token');
        expect(localStorage.getItem('user_role')).toBe('user');
        expect(JSON.parse(localStorage.getItem('user_data')).id).toBe(7);
        expect(router.currentRoute.value.fullPath).toBe('/en/events?country=EG');
        expect(login).toHaveBeenCalledTimes(1);
        expect(sessionStorage.getItem('post_auth_redirect')).toBeNull();
        window.removeEventListener('login', login);
    });

    it('uses the server role for admin navigation', async () => {
        AuthService.exchangeGoogleCode.mockResolvedValue(success('admin'));
        const { router } = await mount(Callback, {}, 'en', `/en/auth/google-callback?code=${code}`);
        expect(router.currentRoute.value.path).toBe('/admin');
    });

    it('does not accept legacy query-string authentication tokens', async () => {
        const { target } = await mount(Callback, {}, 'en', '/en/auth/google-callback?token=hostile&role=admin');
        expect(setAuthToken).not.toHaveBeenCalled();
        expect(target.querySelector('[role=alert]').textContent).toBe(messages.en.googleAuth.invalid_state);
    });

    it('renders cancellation and unknown callback errors without exposing untrusted text', async () => {
        const { target } = await mount(Callback, {}, 'fa', '/fa/auth/google-callback?error=cancelled');
        expect(target.querySelector('[role=alert]').textContent).toBe(messages.fa.googleAuth.cancelled);
        expect(target.firstChild.dir).toBe('rtl');
        expect(AuthService.exchangeGoogleCode).not.toHaveBeenCalled();
        expect(googleErrorKey('<script>details</script>')).toBe('googleAuth.callback_failed');
    });

    it('retains a native verifier until profile completion and submits required details without a password', async () => {
        isMobileRuntime.mockReturnValue(true);
        AuthService.exchangeMobileGoogleCode.mockResolvedValueOnce({ data: { status: 'profile_required' } }).mockResolvedValueOnce(success());
        const { target } = await mount(Callback, {}, 'ur', `/ur/auth/google-callback?code=${code}`);
        expect(clearGoogleOAuthVerifier).not.toHaveBeenCalled();
        const country = target.querySelector('#google-country'); country.value = 'Egypt'; country.dispatchEvent(new Event('input'));
        const birthday = target.querySelector('#google-birthday'); birthday.value = '2000-01-01'; birthday.dispatchEvent(new Event('input'));
        target.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true })); await settle();
        expect(AuthService.exchangeMobileGoogleCode).toHaveBeenLastCalledWith(code, 'v'.repeat(43), { country: 'Egypt', date_of_birth: '2000-01-01' });
        expect(clearGoogleOAuthVerifier).toHaveBeenCalledTimes(1);
        expect(setAuthToken).toHaveBeenCalledWith('post-body-token');
    });

    it('allows retry after a network interruption and localizes validation failures', async () => {
        AuthService.exchangeGoogleCode.mockRejectedValueOnce(new Error('network'));
        const { target } = await mount(Callback, {}, 'en', `/en/auth/google-callback?code=${code}`);
        expect(target.querySelector('button').textContent).toBe(messages.en.googleAuth.retry);
        target.querySelector('button').click(); await settle();
        expect(AuthService.exchangeGoogleCode).toHaveBeenCalledTimes(2);
        expect(setAuthToken).toHaveBeenCalledTimes(1);
    });

    it('preserves existing email/password login and registration requests', async () => {
        AuthService.login.mockResolvedValue({ data: { status: 'success', data: { token: 'password-token', user: { role: 'user' } } } });
        AuthService.register.mockResolvedValue({ data: { status: 'otp_required', data: { email: 'm***@example.test', resend_after: 1 } } });
        const { target } = await mount(Register);
        const email = target.querySelector('input[type=email]'); email.value = 'me@example.test'; email.dispatchEvent(new Event('input'));
        const password = target.querySelector('input[type=password]'); password.value = 'ExistingPass123!'; password.dispatchEvent(new Event('input'));
        target.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true })); await settle();
        expect(AuthService.login).toHaveBeenCalledWith({ email: 'me@example.test', password: 'ExistingPass123!' });
        expect(setAuthToken).toHaveBeenCalledWith('password-token');
        target.querySelectorAll('.nav-link')[1].click(); await settle();
        target.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true })); await settle();
        expect(AuthService.register).toHaveBeenCalledWith(expect.any(FormData));
        expect(target.textContent).toContain('Confirm your email');
        expect(target.querySelector('.google-button')).toBeNull();
    });
});

describe('Google security and locale contracts', () => {
    it('rejects external, malformed and auth-loop destinations while accepting local paths', () => {
        for (const path of ['https://attacker.test', '//attacker.test', '/en/../../evil', '/en/\\attacker', '/en/auth/google-callback', '/en/\ninvalid']) {
            expect(safePostAuthRedirect(path, 'en')).toBe('/en/home');
        }
        expect(safePostAuthRedirect('/en/events?q=one#recent', 'en')).toBe('/en/events?q=one#recent');
    });

    it('covers every new string in exactly 13 existing dictionaries', () => {
        expect(Object.keys(messages).sort()).toEqual(['ar', 'en', 'fr', 'de', 'ru', 'es', 'it', 'hi', 'ja', 'fa', 'zh', 'ur', 'tr'].sort());
        for (const dictionary of Object.values(messages)) {
            expect(Object.keys(dictionary.googleAuth).sort()).toEqual(Object.keys(messages.en.googleAuth).sort());
            for (const value of Object.values(dictionary.googleAuth)) expect(typeof value === 'string' && value.trim().length > 0).toBe(true);
        }
    });

    it('provides flexible width, keyboard focus and RTL-safe spacing for narrow screens', () => {
        const source = readFileSync('resources/js/components/auth/GoogleAuthButton.vue', 'utf8');
        expect(source).toContain('width: 100%'); expect(source).toContain('min-width: 0');
        expect(source).toContain(':focus-visible'); expect(source).toContain('margin-block');
    });
});
