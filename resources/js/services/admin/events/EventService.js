import api from "../../ApiClient";

export const EventService = {
  /**
   * Get single event by slug
   * @param {string} slug
   */
  async getSingleEvent(slug) {
    const response = await api.get(`/events/${slug}/single/get`);
    return response.data;
  },

  /**
   * Update event by ID
   * @param {number|string} id
   * @param {FormData} formData
   */
  async updateEvent(id, formData) {
    const response = await api.post(`/events/${id}/update`, formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
      suppressGlobalErrorToast: true,
    });
    return response.data;
  },

  async deleteEvent(id) {
    const response = await api.delete(`/events/${id}/delete`);
    return response.data;
  },
};
