import api from './ApiClient';
import adminApi from './AdminApiClient';
import { backendOrigin, isMobileRuntime } from './runtimeUrls';
import { v4 as uuidv4 } from 'uuid';

const root = () => `${isMobileRuntime() ? backendOrigin() : ''}/api`;
const options = () => ({ baseURL: root(), suppressGlobalErrorToast: true });
export const giftRequest = (method, path, data, params) => api.request({ ...options(), method, url: path, data, params }).then((response) => response.data.data);
export const giftAdminRequest = (method, path, data, params) => adminApi.request({ ...options(), method, url: `/v1/admin/gifts${path}`, data, params }).then((response) => response.data.data);
export function giftName(gift, locale, t) {
    return gift.translations?.[locale]?.name || gift.name;
}
export const giftMoney = (minor, currency, locale, precision = 2) => new Intl.NumberFormat(locale, { style: 'currency', currency }).format(Number(minor) / (10 ** precision));
export const giftKey = () => uuidv4();
