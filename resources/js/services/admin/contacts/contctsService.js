import api from "../../ApiClient";

export const ContactService = {
  /**
   * Get single contact by ID
   * GET /v1/contacts/{id}
   */
  async getContact(id, page = 1) {
    const response = await api.get(`/contacts/${id}`, { params: { page }, suppressGlobalErrorToast: true });
    return response.data;
  },

  /**
   * Send a respond to a contact
   * POST /v1/contacts/respond/{id}
   * @param {number} id - contact id
   * @param {string} message - respond message
   */
  async respondToContact(id, message, key) {
    const response = await api.post(`/contacts/respond/${id}`, {
      message,
      idempotency_key: key,
    });
    return response.data;
  },
  async updateStatus(id, status, assignToMe = false) {
    const response = await api.patch(`/contacts/${id}/status`, { status, assign_to_me: assignToMe });
    return response.data;
  },
};
