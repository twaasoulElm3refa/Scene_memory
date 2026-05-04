import api from "../../ApiClient";

export const ContactService = {
  /**
   * Fetch paginated contacts
   * @param {number} page - Current page number
   * @returns {Promise<Object>} Paginated contacts response
   */
  async getAll(page = 1) {
    const response = await api.get(`/contacts`, {
      params: { page },
    });
    return response.data;
  },
    async delete(id) {
    return await api.delete(`/contacts/delete/${id}`);
  },
};
