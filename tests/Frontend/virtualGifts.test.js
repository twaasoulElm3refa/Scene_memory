// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';
import { readFileSync } from 'node:fs';
import Modal from '../../resources/js/components/gifts/VirtualGiftModal.vue';
import Admin from '../../resources/js/views/admin/gifts/VirtualGiftsAdmin.vue';
import messages from '../../resources/js/i18n/virtualGifts';
import { giftRequest, giftAdminRequest } from '../../resources/js/services/virtualGiftService';

vi.mock('../../resources/js/services/virtualGiftService', () => ({
    giftRequest: vi.fn(), giftAdminRequest: vi.fn(), giftKey: () => 'test-uuid-idempotency-1234',
    giftName: (gift, locale) => gift.translations?.[locale]?.name || gift.name,
    giftMoney: (minor, currency) => `${currency} ${(Number(minor) / 100).toFixed(2)}`,
}));
vi.mock('../../resources/js/layouts/AdminLayout.vue', () => ({ default: { template: '<div><slot /></div>' } }));
let app;
const settle = async () => { for (let index = 0; index < 15; index++) await Promise.resolve(); await nextTick(); };
async function mount(component, props, locale = 'en') {
    const target = document.createElement('div'); document.body.append(target);
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:lang/gifts', component: { template: '<div />' } }] }); await router.push(`/${locale}/gifts`);
    app = createApp(component, props); app.use(createI18n({ legacy: false, locale, messages: { [locale]: { gifts: messages[locale] } } })); app.use(router); app.mount(target); await settle(); return target;
}
const catalog = [{ id: 1, name: 'Coffee', slug: 'coffee', price_minor: 100, currency: 'USD', translations: { ar: { name: 'قهوة' } } }];
const recipient = { id: 2, name: 'Recipient Name' };
beforeEach(() => {
    sessionStorage.clear();
    HTMLDialogElement.prototype.showModal = vi.fn(function () { this.setAttribute('open', ''); });
    HTMLDialogElement.prototype.close = vi.fn();
    giftRequest.mockImplementation(async (method, path) => path === '/virtual-gifts' ? catalog : path === '/gifts/earnings' ? { wallet: { balance_minor: 1000, currency: 'USD' } } : {});
});
afterEach(() => { app?.unmount(); document.body.innerHTML = ''; vi.resetAllMocks(); });

describe('virtual gifts', () => {
    it.each(Object.keys(messages))('has complete nonempty gift translations for %s', (locale) => {
        expect(Object.keys(messages[locale]).sort()).toEqual(Object.keys(messages.en).sort());
        expect(Object.values(messages[locale]).every((value) => typeof value === 'string' && value.length > 0)).toBe(true);
        for (const path of ['resources/js/components/gifts/VirtualGiftModal.vue','resources/js/views/home/VirtualGifts.vue','resources/js/views/admin/gifts/VirtualGiftsAdmin.vue']) {
            const source = readFileSync(path, 'utf8');
            for (const match of source.matchAll(/gifts\.([A-Za-z_-]+)['"]/g)) expect(messages[locale][match[1]], match[1]).toBeTruthy();
        }
    });

    it('shows the actual recipient, gift image selection, server price, and Arabic RTL', async () => {
        await mount(Modal, { eventId: 7, recipient }, 'ar');
        const dialog = document.querySelector('dialog');
        expect(dialog.dir).toBe('rtl'); expect(dialog.textContent).toContain('Recipient Name'); expect(dialog.textContent).toContain('قهوة'); expect(dialog.textContent).toContain('USD 1.00');
        expect(dialog.textContent).not.toContain('gifts.');
    });

    it('uses one idempotency key for retries and never sends recipient or payment amounts', async () => {
        await mount(Modal, { eventId: 7, commentId: 8, recipient });
        giftRequest.mockRejectedValueOnce(new Error('timeout'));
        const confirm = document.querySelector('.gift-confirm'); confirm.click(); await settle();
        expect(document.querySelector('[role=alert]')).not.toBeNull();
        giftRequest.mockResolvedValueOnce({ reference: 'gift-reference', delivery_status: 'delivered', payment_status: 'paid', gift_snapshot: catalog[0], sender: { name: 'Sender Name' }, recipient, quantity: 1 });
        confirm.click(); await settle();
        const sends = giftRequest.mock.calls.filter(([, path]) => path === '/gifts/send/wallet');
        expect(sends).toHaveLength(2); expect(sends[0][2]).toEqual(sends[1][2]);
        expect(sends[0][2]).toEqual({ event_id: 7, comment_id: 8, gift_id: 1, quantity: 1, idempotency_key: 'test-uuid-idempotency-1234', expected_unit_price_minor: 100, expected_currency: 'USD' });
        expect(document.querySelector('.gift-success').textContent).toContain('Sender Name');
    });

    it('keeps a PayPal gift pending until verified delivery', async () => {
        await mount(Modal, { eventId: 7, recipient });
        const paypal = document.querySelector('input[value=paypal]'); paypal.checked = true; paypal.dispatchEvent(new Event('change', { bubbles: true })); await settle();
        giftRequest.mockResolvedValueOnce({ transaction: { reference: 'gift-reference', payment_status: 'pending', delivery_status: 'pending' }, approval_url: null });
        document.querySelector('.gift-confirm').click(); await settle();
        expect(giftRequest).toHaveBeenLastCalledWith('post', '/gifts/paypal/create-order', expect.objectContaining({ event_id: 7 }));
        expect(document.querySelector('.gift-success')).toBeNull(); expect(document.querySelector('dialog').textContent).toContain(messages.en.verify);
    });

    it('disables wallet payment when the currency differs or balance is too low', async () => {
        giftRequest.mockImplementation(async (_, path) => path === '/virtual-gifts' ? catalog : { wallet: { balance_minor: 99, currency: 'EUR' } });
        await mount(Modal, { eventId: 7, recipient });
        expect(document.querySelector('.gift-confirm').disabled).toBe(true);
    });

    it('submits only the three gift fields and refreshes the catalog', async () => {
        giftAdminRequest.mockResolvedValue(catalog);
        await mount(Admin);
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input');
        expect(inputs).toHaveLength(3);
        for (const [index, value] of [[0, 'Edited Coffee'], [1, '3.25']]) { inputs[index].value = value; inputs[index].dispatchEvent(new Event('input', { bubbles: true })); }
        await settle(); form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); await settle();
        const call = giftAdminRequest.mock.calls.find(([method]) => method === 'post');
        expect([...call[2].keys()]).toEqual(['name', 'price']);
        expect(call[2].get('price')).toBe('3.25'); expect(call[2].get('name')).toBe('Edited Coffee');
        expect(giftAdminRequest).toHaveBeenCalledWith('get', '/catalog');
    });

    it('shows validation details instead of a fake success', async () => {
        giftAdminRequest.mockResolvedValueOnce(catalog).mockRejectedValueOnce({ response: { data: { errors: { price: ['Enter a valid price.'] } } } });
        await mount(Admin);
        const form = document.querySelector('form');
        form.querySelector('input').value = 'Rose';
        form.querySelector('input').dispatchEvent(new Event('input', { bubbles: true }));
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); await settle();
        expect(document.querySelector('[role=alert]').textContent).toContain('Enter a valid price.');
        expect(document.querySelector('main [role=status]')).toBeNull();
    });

    it('distinguishes a catalog error from an empty catalog and allows retry', async () => {
        giftRequest.mockRejectedValueOnce({ response: { data: { message: 'Catalog unavailable' } } });
        await mount(Modal, { eventId: 7, recipient });
        expect(document.querySelector('dialog').textContent).toContain('Catalog unavailable');
        expect(document.querySelector('dialog').textContent).not.toContain(messages.en.empty);
        document.querySelector('dialog button:not([aria-label])').click(); await settle();
        expect(document.querySelectorAll('.gift-catalog button')).toHaveLength(1);
    });

    it('preserves reduced-motion and responsive gift layouts', () => {
        const source = readFileSync('resources/js/components/gifts/VirtualGiftModal.vue', 'utf8');
        expect(source).toContain('prefers-reduced-motion: reduce'); expect(source).toContain('@media(max-width: 450px)'); expect(source).toContain('animation: none');
        expect(source).toContain('position: fixed; inset: 0; margin: auto;');
        expect(source).toContain('<Teleport to="body">');
        const event = readFileSync('resources/js/views/home/single_event.vue', 'utf8');
        expect(event).toContain(':recipient="event.user"'); expect(event).toContain(':recipient="comment.user"');
    });

    it('restores an uncertain wallet attempt after reopening instead of creating a new debit key', async () => {
        await mount(Modal, { eventId: 7, recipient });
        giftRequest.mockRejectedValueOnce(new Error('network timeout'));
        document.querySelector('.gift-confirm').click(); await settle();
        const key = 'scemory_gift_intent:7:0';
        const saved = JSON.parse(sessionStorage.getItem(key));
        saved.payload.idempotency_key = 'retained-payment-retry-key'; sessionStorage.setItem(key, JSON.stringify(saved));
        app.unmount(); document.body.innerHTML = '';
        await mount(Modal, { eventId: 7, recipient });
        giftRequest.mockResolvedValueOnce({ delivery_status: 'delivered', payment_status: 'paid', gift_snapshot: catalog[0], sender: { name: 'Sender' }, recipient, quantity: 1 });
        document.querySelector('.gift-confirm').click(); await settle();
        expect(giftRequest).toHaveBeenLastCalledWith('post', '/gifts/send/wallet', expect.objectContaining({ idempotency_key: 'retained-payment-retry-key' }));
        expect(sessionStorage.getItem(key)).toBeNull();
    });
});
