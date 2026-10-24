import api from '../ApiClient';
import { googleAuthOrigin } from '../runtimeUrls';

const options = { suppressGlobalErrorToast: true };

export const GoogleAuthService = {
    start(lang) {
        return api.get('/users/google-login', { ...options, params: { lang } });
    },

    async exchangeWebCode(code) {
        await api.get('/sanctum/csrf-cookie', { ...options, baseURL: '/' });
        return api.post('/users/google-exchange', { code }, options);
    },

    exchangeMobileGoogleCode(code, codeVerifier) {
        return api.post(`${googleAuthOrigin()}/api/v1/users/google-mobile-exchange`, {
            code,
            code_verifier: codeVerifier,
        }, options);
    },

    confirmLink(password) {
        return api.post('/users/google-link-confirm', { password }, options);
    },

    async startRecovery() {
        await api.get('/sanctum/csrf-cookie', { ...options, baseURL: '/' });
        return api.post('/users/google-recovery-start', {}, options);
    },

    confirmRecovery(code) {
        return api.post('/users/google-recovery-confirm', { code }, options);
    },

    startLink() {
        return api.get('/users/google-link-start');
    },

    unlink() {
        return api.post('/users/google-unlink');
    },
};
