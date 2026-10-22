// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick, h } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';
import { readFileSync } from 'node:fs';
import FacebookButton from '../../resources/js/components/auth/FacebookAuthButton.vue';
import Callback from '../../resources/js/views/auth/FacebookCallback.vue';
import Register from '../../resources/js/views/auth/register.vue';
import { FacebookAuthService } from '../../resources/js/services/AuthService/FacebookAuthService';
import { facebookErrorKey, safeFacebookRedirect, startFacebookAuth } from '../../resources/js/services/facebookAuth';
import { setAuthToken } from '../../resources/js/services/authTokenStorage';
import { isMobileRuntime } from '../../resources/js/services/runtimeUrls';

vi.mock('../../resources/js/services/AuthService/FacebookAuthService', () => ({ FacebookAuthService: { start: vi.fn(), exchange: vi.fn() } }));
vi.mock('../../resources/js/services/AuthService/AuthService', () => ({ AuthService: { googleLogin: vi.fn(), login: vi.fn(), register: vi.fn() } }));
vi.mock('../../resources/js/services/authTokenStorage', () => ({ setAuthToken: vi.fn(), clearGoogleOAuthVerifier: vi.fn(), setGoogleOAuthVerifier: vi.fn() }));
vi.mock('../../resources/js/services/runtimeUrls', () => ({ isMobileRuntime: vi.fn(() => false), googleAuthOrigin: () => '', toAssetUrl: value => value }));
vi.mock('@capacitor/core', () => ({ Capacitor: { isNativePlatform: () => false } }));
vi.mock('@capacitor/browser', () => ({ Browser: { open: vi.fn() } }));

const existing = import.meta.glob('../../resources/js/i18n/*.json', { eager: true, import: 'default' });
const messages = Object.fromEntries(Object.entries(existing).map(([file, value]) => [file.split('/').pop().replace('.json', ''), value]));
const code = 'c'.repeat(64);
const success = (role = 'user') => ({ data: { status: 'success', token: 'facebook-body-token', role, user: { id: 12, role, name: 'Existing User', email: 'existing@example.test' }, is_profile_complete: true } });
let app;
const settle = async () => { for (let i = 0; i < 4; i++) { await new Promise(resolve => setTimeout(resolve, 0)); await nextTick(); } };

async function mount(component, props = {}, lang = 'en', url = `/${lang}/auth`) {
    const target = document.createElement('div'); document.body.append(target);
    const router = createRouter({ history: createMemoryHistory(), routes: [
        { path: '/:lang/auth', name: 'auth', component: Register },
        { path: '/:lang/auth/facebook-callback', name: 'facebook-callback', component: Callback },
        { path: '/:pathMatch(.*)*', component: { render: () => h('div') } },
    ] });
    await router.push(url);
    app = createApp(component, props);
    app.use(router).use(createI18n({ legacy: false, locale: lang, fallbackLocale: false, messages }));
    app.mount(target); await settle();
    return { target, router };
}

beforeEach(() => {
    vi.clearAllMocks(); localStorage.clear(); sessionStorage.clear();
    isMobileRuntime.mockReturnValue(false);
    setAuthToken.mockResolvedValue();
    FacebookAuthService.exchange.mockResolvedValue(success());
});
afterEach(() => { app?.unmount(); document.body.innerHTML = ''; });

describe('Facebook authentication UI', () => {
    it.each(Object.keys(messages))('renders localized Facebook branding and direction in %s', async lang => {
        const { target } = await mount(FacebookButton, {}, lang);
        expect(target.textContent).toContain(messages[lang].facebookAuth.continue);
        expect(target.querySelector('.facebook-auth').dir).toBe(['ar', 'fa', 'ur'].includes(lang) ? 'rtl' : 'ltr');
        expect(target.querySelector('button').type).toBe('button');
        expect(target.querySelector('svg').getAttribute('aria-hidden')).toBe('true');
        expect(target.textContent).not.toContain('facebookAuth.');
    });

    it('announces loading and disables keyboard/button activation', async () => {
        const proceed = vi.fn();
        const { target } = await mount(FacebookButton, { loading: true, onContinue: proceed });
        const button = target.querySelector('button'); button.click();
        expect(button.disabled).toBe(true); expect(button.getAttribute('aria-busy')).toBe('true');
        expect(target.textContent).toContain(messages.en.facebookAuth.signingIn);
        expect(proceed).not.toHaveBeenCalled();
    });

    it('adds one Facebook button outside both forms and retains the existing Google button', async () => {
        const { target } = await mount(Register);
        expect(target.querySelectorAll('.facebook-button')).toHaveLength(1);
        expect(target.querySelector('form .facebook-button')).toBeNull();
        expect(target.querySelectorAll('.google-button')).toHaveLength(1);
        target.querySelectorAll('.nav-link')[1].click(); await settle();
        expect(target.querySelectorAll('.facebook-button')).toHaveLength(1);
        expect(target.querySelectorAll('.google-button')).toHaveLength(1);
        expect(target.querySelector('input[type=date]').required).toBe(true);
    });

    it('prevents repeated starts and presents safe localized errors', async () => {
        let reject;
        FacebookAuthService.start.mockImplementation(() => new Promise((_, r) => { reject = r; }));
        const { target } = await mount(Register, {}, 'ar');
        target.querySelector('.facebook-button').click(); target.querySelector('.facebook-button').click(); await settle();
        expect(FacebookAuthService.start).toHaveBeenCalledTimes(1);
        expect(target.querySelector('.facebook-button').disabled).toBe(true);
        reject({ response: { data: { error_code: 'unavailable', message: 'sensitive raw details' } } }); await settle();
        expect(target.querySelector('[role=alert]').textContent).toBe(messages.ar.facebookAuth.unavailable);
        expect(target.textContent).not.toContain('sensitive raw details');
        expect(target.querySelector('.facebook-button').disabled).toBe(false);
    });

    it('exchanges only a delivery code, ignores query tokens/roles and updates the existing auth state', async () => {
        const login = vi.fn(); window.addEventListener('login', login);
        sessionStorage.setItem('post_auth_redirect', '/en/events?country=EG');
        const { router } = await mount(Callback, {}, 'en', `/en/auth/facebook-callback?code=${code}&token=hostile&role=admin`);
        expect(FacebookAuthService.exchange).toHaveBeenCalledWith(code);
        expect(setAuthToken).toHaveBeenCalledWith('facebook-body-token');
        expect(localStorage.getItem('user_role')).toBe('user');
        expect(JSON.parse(localStorage.getItem('user_data')).id).toBe(12);
        expect(localStorage.getItem('language')).toBe('en');
        expect(router.currentRoute.value.fullPath).toBe('/en/events?country=EG');
        expect(login).toHaveBeenCalledTimes(1);
        window.removeEventListener('login', login);
    });

    it('routes admins using only the role returned by the backend', async () => {
        FacebookAuthService.exchange.mockResolvedValue(success('admin'));
        const { router } = await mount(Callback, {}, 'en', `/en/auth/facebook-callback?code=${code}`);
        expect(router.currentRoute.value.path).toBe('/admin');
    });

    it('rejects legacy query-string authentication tokens', async () => {
        const { target, router } = await mount(Callback, {}, 'en', '/en/auth/facebook-callback?token=hostile&role=admin');
        expect(target.querySelector('[role=alert]').textContent).toBe(messages.en.facebookAuth.invalid_state);
        expect(setAuthToken).not.toHaveBeenCalled();
        expect(router.currentRoute.value.query).toEqual({});
    });

    it('localizes cancellation and does not display untrusted provider errors', async () => {
        const { target } = await mount(Callback, {}, 'fa', '/fa/auth/facebook-callback?error=cancelled');
        expect(target.querySelector('[role=alert]').textContent).toBe(messages.fa.facebookAuth.cancelled);
        expect(target.firstChild.dir).toBe('rtl');
        expect(FacebookAuthService.exchange).not.toHaveBeenCalled();
        expect(facebookErrorKey('<script>raw details</script>')).toBe('facebookAuth.callback_failed');
    });

    it('signs in immediately without any account-completion form when profile is incomplete', async () => {
        const response = success(); response.data.is_profile_complete = false;
        FacebookAuthService.exchange.mockResolvedValue(response);
        const { target, router } = await mount(Callback, {}, 'en', `/en/auth/facebook-callback?code=${code}`);
        expect(FacebookAuthService.exchange).toHaveBeenCalledTimes(1);
        expect(FacebookAuthService.exchange).toHaveBeenCalledWith(code);
        expect(target.querySelector('form')).toBeNull();
        expect(target.querySelector('input')).toBeNull();
        expect(target.textContent).not.toContain(messages.en.facebookAuth.completeProfile);
        expect(localStorage.getItem('is_profile_complete')).toBe('false');
        expect(router.currentRoute.value.path).toBe('/en/home');
        expect(setAuthToken).toHaveBeenCalledWith('facebook-body-token');
    });

    it('accepts a Facebook-only account without email and never asks for email or a code', async () => {
        const response = success(); response.data.user.email = null;
        FacebookAuthService.exchange.mockResolvedValue(response);
        const { target, router } = await mount(Callback, {}, 'ur', `/ur/auth/facebook-callback?code=${code}`);
        expect(JSON.parse(localStorage.getItem('user_data')).email).toBeNull();
        expect(target.querySelector('form')).toBeNull();
        expect(router.currentRoute.value.path).toBe('/ur/home');
        expect(localStorage.getItem('language')).toBe('ur');
    });

    it('permits a network retry but stops on account-linking or suspension errors', async () => {
        FacebookAuthService.exchange.mockRejectedValueOnce(new Error('network'))
            .mockRejectedValueOnce({ response: { status: 409, data: { error_code: 'linking_required' } } });
        const { target } = await mount(Callback, {}, 'en', `/en/auth/facebook-callback?code=${code}`);
        target.querySelector('button').click(); await settle();
        expect(target.querySelector('[role=alert]').textContent).toBe(messages.en.facebookAuth.linking_required);
        expect(target.querySelector('button')).toBeNull();
        expect(setAuthToken).not.toHaveBeenCalled();
    });
});

describe('Facebook boundaries and localization', () => {
    it('rejects non-Facebook authorization URLs and unsupported native session transfers', async () => {
        FacebookAuthService.start.mockResolvedValue({ data: { url: 'https://attacker.test/oauth' } });
        await expect(startFacebookAuth('en')).rejects.toThrow('unavailable');
        isMobileRuntime.mockReturnValue(true);
        await expect(startFacebookAuth('en')).rejects.toThrow('native_unavailable');
        expect(FacebookAuthService.start).toHaveBeenCalledTimes(1);
    });
    it('allows only local post-login destinations and rejects auth loops', () => {
        for (const value of ['https://attacker.test', '//attacker.test', '/en/../../other', '/en/\\other', '/en/auth/facebook-callback', '/en/\nother']) {
            expect(safeFacebookRedirect(value, 'en')).toBe('/en/home');
        }
        expect(safeFacebookRedirect('/en/events?q=one#latest', 'en')).toBe('/en/events?q=one#latest');
    });
    it('has complete nonempty coverage in the 13 existing locales', () => {
        expect(Object.keys(messages).sort()).toEqual(['ar','en','fr','de','ru','es','it','hi','ja','fa','zh','ur','tr'].sort());
        for (const dictionary of Object.values(messages)) {
            expect(Object.keys(dictionary.facebookAuth).sort()).toEqual(Object.keys(messages.en.facebookAuth).sort());
            for (const value of Object.values(dictionary.facebookAuth)) expect(typeof value === 'string' && value.trim().length > 0).toBe(true);
        }
    });
    it('uses responsive width and visible keyboard focus', () => {
        const css = readFileSync('resources/js/components/auth/FacebookAuthButton.vue', 'utf8');
        expect(css).toContain('width: 100%'); expect(css).toContain('min-width: 0'); expect(css).toContain(':focus-visible');
        expect(css).toContain('color: #FFFFFF !important;');
        expect(css).toContain('color: #FFFFFF; fill: #FFFFFF;');
        expect(css).toContain(':active:not(:disabled)');
    });
});
