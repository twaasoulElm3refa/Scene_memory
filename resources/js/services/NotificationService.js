import api from "./ApiClient";

export const NotificationService = {
  create(payload) {
    return api.post("/notify/create", payload);
  },
};
