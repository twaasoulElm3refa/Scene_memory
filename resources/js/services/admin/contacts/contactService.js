import axios from "axios";

const API_BASE = "/v1";

export const ContactService = {
  /**
   * Fetch paginated contacts
   * @param {number} page - Current page number
   * @returns {Promise<Object>} Paginated contacts response
   */
  async getAll(page = 1) {
    const response = await axios.get(`${API_BASE}/contacts`, {
      params: { page },
    });
    return response.data;
  },
    async delete(id) {
    return await axios.delete(`${API_BASE}/contacts/delete/${id}`);
  },
};