import api from "./ApiClient";

export const PurchaseService = {
  getAll(page = 1) {
    return api.get(`/purchases?page=${page}`);
  },

  getByStatus(status, page = 1) {
    return api.get(`/purchases/status/${status}`, { params: { page } });
  },

  getByType(type, page = 1) {
    return api.get(`/purchases/type/${type}`, { params: { page } });
  },

  getSingle(id) {
    return api.get(`/purchases/show/${id}`);
  },

  delete(id) {
    return api.delete(`/purchases/delete/${id}`);
  },
};
