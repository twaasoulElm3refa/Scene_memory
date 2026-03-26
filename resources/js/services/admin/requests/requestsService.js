import axios from "axios";

const API_BASE = "/v1";

export const requestsService = {
  getAllPaginated(page = 1, params = {}) {
    return axios.get(`${API_BASE}/requests/all/paginated`, {
      params: { page, ...params },
    });
  },

  approveRequest(id) {
    return axios.post(`${API_BASE}/requests/${id}/approve`);
  },

  rejectRequest(id) {
    return axios.post(`${API_BASE}/requests/${id}/reject`);
  },
};