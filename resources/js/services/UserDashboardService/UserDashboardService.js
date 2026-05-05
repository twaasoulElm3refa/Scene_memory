import api from "../ApiClient";

export const UserDashboardService = {
  getMyEvents() {
    return api.get("/user-dshboard/my-events");
  },

  getSingleEvent(slug) {
    return api.get(`/events/${slug}/single/get`);
  },

  createEvent(formData) {
    return api.post("/user-dshboard/create/Event", formData);
  },

  updateEvent(slug, formData) {
    return api.post(`/user-dshboard/${slug}/update/Event`, formData);
  },

  deleteEvent(id) {
    return api.delete(`/user-dshboard/${id}/destroy`);
  },

  deleteMedia(id) {
    return api.delete(`/user-dshboard/${id}/delete`);
  },

  uploadMedia(slug, formData) {
    return api.post(`/user-dshboard/${slug}`, formData);
  },
};
