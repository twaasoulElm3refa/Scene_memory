import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../../resources/js/services/ApiClient';
import { AuthService } from '../../resources/js/services/AuthService/AuthService';

vi.mock('../../resources/js/services/ApiClient', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('../../resources/js/services/runtimeUrls', () => ({ googleAuthOrigin: () => 'https://scemory.com' }));

beforeEach(() => { vi.resetAllMocks(); });

describe('Google authentication API transport', () => {
    it('loads the Laravel CSRF cookie before exchanging the web delivery code in a POST body', async () => {
        api.get.mockResolvedValue({}); api.post.mockResolvedValue({ data: { token: 'body-only' } });
        await AuthService.exchangeGoogleCode('delivery-code');
        expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie', { baseURL: '/', suppressGlobalErrorToast: true });
        expect(api.post).toHaveBeenCalledWith('/users/google-exchange', { code: 'delivery-code' }, { suppressGlobalErrorToast: true });
        expect(api.get.mock.invocationCallOrder[0]).toBeLessThan(api.post.mock.invocationCallOrder[0]);
    });
    it('does not exchange credentials if CSRF initialization fails', async () => {
        api.get.mockRejectedValue(new Error('CSRF cookie unavailable'));
        await expect(AuthService.exchangeGoogleCode('delivery-code')).rejects.toThrow();
        expect(api.post).not.toHaveBeenCalled();
    });
    it('binds the existing native exchange to the verifier and sends neither in a URL query', async () => {
        await AuthService.exchangeMobileGoogleCode('delivery-code', 'verifier');
        expect(api.get).not.toHaveBeenCalled();
        expect(api.post).toHaveBeenCalledWith('https://scemory.com/api/v1/users/google-mobile-exchange', { code: 'delivery-code', code_verifier: 'verifier' }, { suppressGlobalErrorToast: true });
    });
    it('sends explicit linking confirmation and link start requests properly', async () => {
        api.post.mockResolvedValue({ data: { status: 'success' } });
        api.get.mockResolvedValue({ data: { status: 'success', url: 'https://accounts.google.com' } });
        await AuthService.confirmGoogleLink('my-password');
        expect(api.post).toHaveBeenCalledWith('/users/google-link-confirm', { password: 'my-password' }, { suppressGlobalErrorToast: true });
        await AuthService.startGoogleLink();
        expect(api.get).toHaveBeenCalledWith('/users/google-link-start');
        await AuthService.unlinkGoogle();
        expect(api.post).toHaveBeenCalledWith('/users/google-unlink');
    });
});
