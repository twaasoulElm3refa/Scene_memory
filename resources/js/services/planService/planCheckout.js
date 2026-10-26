// PayPal plan checkout helpers. POST /plans/subscribe/{plan}/paypal answers
// { order_id, approval_url } at the top level of the response body, like the
// cart, Buy Now and wallet deposit checkouts.

const ERROR_KEYS = {
    plan_financially_unavailable: 'planCheckout.financiallyUnavailable',
    accumulation_limit: 'planCheckout.accumulationLimit',
    plan_inactive: 'planCheckout.planInactive',
    paypal_unavailable: 'planCheckout.paypalUnavailable',
    paypal_order_failed: 'planCheckout.paypalOrderFailed',
    unauthorized: 'planCheckout.signIn',
};

/**
 * The PayPal approval link from the API response, or null. Only https links on
 * a paypal.com host are accepted, so a malformed response can never redirect elsewhere.
 */
export function paypalApprovalUrl(response) {
    const value = response?.data?.approval_url;
    if (typeof value !== 'string') return null;
    try {
        const url = new URL(value);
        const host = url.hostname.toLowerCase();
        return url.protocol === 'https:' && (host === 'paypal.com' || host.endsWith('.paypal.com')) ? url.href : null;
    } catch {
        return null;
    }
}

/** Order still being confirmed after an earlier PayPal approval, if the API says so. */
export function inProgressOrderId(error) {
    const data = error?.response?.data;
    return error?.response?.status === 409 && data?.code === 'checkout_in_progress' && data?.order_id ? data.order_id : null;
}

/**
 * i18n key for a failed checkout request; never the raw server text. In
 * production the API replaces every 5xx body with a generic message, so 5xx
 * errors are told apart by status code alone.
 */
export function planCheckoutErrorKey(error) {
    const response = error?.response;
    if (!response) return 'planCheckout.networkError';
    const key = ERROR_KEYS[response.data?.code];
    if (key) return key;
    if (response.status === 401 || response.status === 403) return 'planCheckout.signIn';
    if (response.status === 503) return 'planCheckout.paypalUnavailable';

    return 'planCheckout.paypalOrderFailed';
}

export function redirectToPayPal(url) {
    window.location.assign(url);
}
