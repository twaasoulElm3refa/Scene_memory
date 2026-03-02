import axios from "axios";

const API_BASE = "/v1";

export const EventService = {
  /**
   * Get single event by slug
   * @param {string} slug
   */
  async getSingleEvent(slug) {
    const response = await axios.get(`${API_BASE}/events/${slug}/single/get`);
    return response.data;
  },

  /**
   * Update event by ID
   * @param {number|string} id
   * @param {FormData} formData
   */
  async updateEvent(id, formData) {
    const response = await axios.post(`${API_BASE}/user-dshboard/${id}/update/Event`, formData, {
      headers: {
        "Content-Type": "multipart/form-data",
        Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
      },
    });
    return response.data;
  },
};