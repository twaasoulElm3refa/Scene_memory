import api from "./ApiClient";

export const EventImageService = {
  create(eventId, formData, config = {}) {
    return api.post(`/event-images/create/${eventId}`, formData, config);
  },

  delete(mediaId) {
    return api.delete(`/event-images/${mediaId}/delete`);
  },
};
