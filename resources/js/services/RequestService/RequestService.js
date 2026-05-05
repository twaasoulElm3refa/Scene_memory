import api from "../ApiClient";

export const RequestService = {
  getAllPaginated(page = 1, params = {}) {
    return api.get("/requests/all/paginated", { params: { page, ...params } });
  },

  getSingle(id) {
    return api.get(`/requests/${id}`);
  },

  approve(requestId) {
    return api.post(`/requests/approve/${requestId}`);
  },

  decline(requestId, payload = {}) {
    return api.post(`/requests/decline/${requestId}`, payload);
  },

  delete(id) {
    return api.delete(`/requests/${id}`);
  },
};
