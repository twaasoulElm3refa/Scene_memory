import api from "../../ApiClient";

export const requestsService = {
  getAllPaginated(page = 1, params = {}) {
    return api.get(`/requests/all/paginated`, {
      params: { page, ...params },
    });
  },

  approveRequest(id) {
    return api.post(`/requests/approve/${id}`);
  },

  rejectRequest(id) {
    return api.post(`/requests/decline/${id}`);
  },

  deleteRequest(id) {
    return api.delete(`/requests/${id}`);
  },
};
