import api from "../ApiClient";

export const specialCoverageRequestsService = {
    getAll(page = 1, params = {}) {
        return api.get("/admin/special-coverage-requests", {
            params: { page, ...params },
        });
    },

    getSingle(id) {
        return api.get(`/admin/special-coverage-requests/${id}`);
    },

    approve(id) {
        return api.post(`/admin/special-coverage-requests/${id}/approve`);
    },

    reject(id, payload) {
        return api.post(`/admin/special-coverage-requests/${id}/reject`, payload);
    },
};
