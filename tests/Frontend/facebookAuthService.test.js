import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../../resources/js/services/ApiClient';
import { FacebookAuthService } from '../../resources/js/services/AuthService/FacebookAuthService';

vi.mock('../../resources/js/services/ApiClient', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
beforeEach(() => vi.resetAllMocks());

describe('Facebook API transport', () => {
    it('starts only the Facebook endpoint and keeps the locale', async () => {
        await FacebookAuthService.start('ar');
        expect(api.get).toHaveBeenCalledWith('/users/facebook-login', { suppressGlobalErrorToast: true, params: { lang: 'ar' } });
    });
    it('initializes CSRF then sends codes in a POST body', async () => {
        await FacebookAuthService.exchange('delivery');
        expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie', { suppressGlobalErrorToast: true, baseURL: '/' });
        expect(api.post).toHaveBeenCalledWith('/users/facebook-exchange', { code: 'delivery' }, { suppressGlobalErrorToast: true });
        expect(api.get.mock.invocationCallOrder[0]).toBeLessThan(api.post.mock.invocationCallOrder[0]);
    });
    it('does not exchange if CSRF initialization fails', async () => {
        api.get.mockRejectedValue(new Error('network'));
        await expect(FacebookAuthService.exchange('delivery')).rejects.toThrow('network');
        expect(api.post).not.toHaveBeenCalled();
    });
});
