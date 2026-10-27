import api from "../ApiClient";

export const ContactService = {
  create(payload) {
    return api.post("/contacts/create", payload, { suppressGlobalErrorToast: true });
  },
  list(page = 1) {
    return api.get('/support/tickets', { params: { page }, suppressGlobalErrorToast: true });
  },
  show(id, accessToken, page = 1, peek = false) {
    return api.get(`/support/tickets/${id}`, { params: { page, peek: peek ? 1 : undefined }, headers: accessToken ? { 'X-Support-Access': accessToken } : {}, suppressGlobalErrorToast: true });
  },
  reply(id, message, key, accessToken) {
    return api.post(`/support/tickets/${id}/messages`, { message, idempotency_key: key }, { headers: accessToken ? { 'X-Support-Access': accessToken } : {}, suppressGlobalErrorToast: true });
  },
};
