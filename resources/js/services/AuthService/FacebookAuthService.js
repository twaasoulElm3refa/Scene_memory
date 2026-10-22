import api from '../ApiClient';

const options = { suppressGlobalErrorToast: true };

export const FacebookAuthService = {
    start(lang) {
        return api.get('/users/facebook-login', { ...options, params: { lang } });
    },
    async exchange(code, payload = {}) {
        await api.get('/sanctum/csrf-cookie', { ...options, baseURL: '/' });
        return api.post('/users/facebook-exchange', { ...payload, code }, options);
    },
};
