import api from "../../ApiClient";

export const ContactService = {
  /**
   * Fetch paginated contacts
   * @param {number} page - Current page number
   * @returns {Promise<Object>} Paginated contacts response
   */
  async getAll(page = 1, status = 'all', search = '') {
    const response = await api.get(`/contacts`, {
      params: { page, status, search },
      suppressGlobalErrorToast: true,
    });
    return response.data;
  },
};
