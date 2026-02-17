import axios from "axios";

const API_BASE = "/v1";

export const ContactService = {
  /**
   * Get single contact by ID
   * GET /v1/contacts/{id}
   */
  async getContact(id) {
    const response = await axios.get(`${API_BASE}/contacts/${id}`);
    return response.data;
  },

  /**
   * Send a respond to a contact
   * POST /v1/contacts/respond/{id}
   * @param {number} id - contact id
   * @param {string} message - respond message
   */
  async respondToContact(id, message) {
    const response = await axios.post(`${API_BASE}/contacts/respond/${id}`, {
      message,
    });
    return response.data;
  },
};