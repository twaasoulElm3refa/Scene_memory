import api from "../ApiClient";

export const EventImageService = {
  all(eventId) {
    return api.get(`/event-images/${eventId}`, {
      suppressGlobalErrorToast: true,
    });
  },

  create(eventId, formData, config = {}) {
    return api.post(`/event-images/create/${eventId}`, formData, {
      ...config,
      suppressGlobalErrorToast: true,
    });
  },

  delete(mediaId) {
    return api.delete(`/event-images/${mediaId}/delete`, {
      suppressGlobalErrorToast: true,
    });
  },
};
