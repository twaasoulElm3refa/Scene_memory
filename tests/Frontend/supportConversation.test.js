// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';
import Contact from '../../resources/js/views/home/contact.vue';
import en from '../../resources/js/i18n/en.json';
import ar from '../../resources/js/i18n/ar.json';
import support from '../../resources/js/i18n/supportConversation';
import policies from '../../resources/js/i18n/policies';
import { ContactService } from '../../resources/js/services/ContactService/ContactService';

vi.mock('../../resources/js/services/ContactService/ContactService', () => ({
  ContactService: { create: vi.fn(), list: vi.fn(), show: vi.fn(), reply: vi.fn() },
}));

let app;
const settle = async () => { for (let i = 0; i < 15; i++) await Promise.resolve(); await nextTick(); };
const ticket = { id: 19, subject: 'Payment issue', status: 'open', last_message_at: '2026-10-27T09:00:00Z' };
const initial = { id: 1, sender_type: 'guest', message: 'Payment failed', created_at: ticket.last_message_at };

async function mount(locale = 'en') {
  const root = document.createElement('div');
  document.body.append(root);
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:lang/contact', component: Contact }] });
  await router.push(`/${locale}/contact`);
  const i18n = createI18n({ legacy: false, locale, messages: {
    en: { ...en, policies: policies.en, supportConversation: support.en },
    ar: { ...ar, policies: policies.ar, supportConversation: support.ar },
  } });
  app = createApp(Contact);
  app.use(router).use(i18n).mount(root);
  await settle();
  return root;
}

afterEach(() => { app?.unmount(); app = null; document.body.innerHTML = ''; localStorage.clear(); vi.clearAllMocks(); });

describe('support conversation page', () => {
  it('opens the saved guest ticket and retains an unsent reply after network failure', async () => {
    ContactService.create.mockResolvedValue({ status: 200, data: { data: { ticket, access_token: 'private-token' } } });
    ContactService.show.mockResolvedValue({ data: { data: { ticket, messages: { data: [initial], current_page: 1, last_page: 1 } } } });
    ContactService.reply.mockRejectedValue(new Error('offline'));
    const root = await mount();
    for (const [selector, value] of [['#full-name', 'Guest User'], ['#email', 'guest@example.test'], ['#message', 'Payment failed']]) {
      const input = root.querySelector(selector); input.value = value; input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    await settle();
    root.querySelector('.contact-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await settle();
    expect(root.querySelector('[role="tab"][aria-selected="true"]').textContent).toContain('My Conversations');
    expect(root.querySelector('.conversation-card h2').textContent).toBe('Payment issue');
    expect(root.querySelector('.message-bubble p').textContent).toBe('Payment failed');
    expect(localStorage.getItem('scemory-support-guests')).toContain('private-token');
    const reply = root.querySelector('#support-reply');
    reply.value = 'Please help'; reply.dispatchEvent(new Event('input', { bubbles: true }));
    await settle();
    root.querySelector('.conversation-reply').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await settle();
    expect(ContactService.reply).toHaveBeenCalledWith(19, 'Please help', expect.any(String), 'private-token');
    expect(reply.value).toBe('Please help');
  });

  it('keeps Arabic message direction in the conversation', async () => {
    ContactService.create.mockResolvedValue({ status: 200, data: { data: { ticket, access_token: 'private-token' } } });
    ContactService.show.mockResolvedValue({ data: { data: { ticket, messages: { data: [{ ...initial, message: 'الدفع لم ينجح' }], current_page: 1, last_page: 1 } } } });
    const root = await mount('ar');
    const form = root.querySelector('.contact-form');
    for (const [selector, value] of [['#full-name', 'Guest User'], ['#email', 'guest@example.test'], ['#message', 'Payment failed']]) {
      const input = root.querySelector(selector); input.value = value; input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    await settle();
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await settle();
    expect(root.querySelector('.message-bubble').getAttribute('dir')).toBe('rtl');
    expect(root.querySelector('[role="tab"][aria-selected="true"]').textContent).toContain('محادثاتي');
  });
});
