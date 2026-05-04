import api from "./ApiClient";

export const AdminDashboardService = {
  getLatestUsers() {
    return api.get("/users/latest/get");
  },

  getEventsCount() {
    return api.get("/events/count");
  },

  getUsersCount() {
    return api.get("/users/all/count");
  },

  getMemoriesCount() {
    return api.get("/events/memories");
  },

  getPurchasesCount() {
    return api.get("/purchases/all/count");
  },
};
