import api from "../../ApiClient";

export const withdrawalServices = {
    getAll(page = 1, params = {}) {
        return api.get("/withdraw", {
            params: { page, ...params },
        });
    },

    getCount() {
        return api.get("/withdraw/all/count");
    },

    getByStatus(status, page = 1, params = {}) {
        return api.get(`/withdraw/status/${status}`, {
            params: { page, ...params },
        });
    },

    getById(id) {
        return api.get(`/withdraw/show/${id}`);
    },

    update(id, payload = {}) {
        return api.post(`/withdraw/update/${id}`, payload);
    },

    approve(id) {
        return api.post(`/withdraw/approve/${id}`);
    },

    reject(id) {
        return api.post(`/withdraw/reject/${id}`);
    },

    delete(id) {
        return api.delete(`/withdraw/delete/${id}`);
    },
};
