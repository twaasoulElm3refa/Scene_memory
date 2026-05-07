import api from "@/services/ApiClient";

const unwrap = (response) => response?.data?.data ?? response?.data ?? null;

const normalizeError = (error) => {
    const message =
        error?.response?.data?.message ||
        error?.message ||
        "Request failed.";

    const validation = error?.response?.data?.errors || null;

    const wrapped = new Error(message);
    wrapped.validation = validation;
    wrapped.status = error?.response?.status || null;
    wrapped.original = error;

    return wrapped;
};

export const withdrawalServices = {
    async getMyWallet() {
        try {
            const response = await api.get("/users/wallet");
            return unwrap(response);
        } catch (error) {
            throw normalizeError(error);
        }
    },

    async myWithdrawals(params = {}) {
        try {
            const response = await api.get("/withdraw/myWithdrawals", { params });
            return unwrap(response);
        } catch (error) {
            throw normalizeError(error);
        }
    },

    async showWithdrawals(id) {
        try {
            const response = await api.post("/withdraw/showWithdrawals", { id });
            return unwrap(response);
        } catch (error) {
            throw normalizeError(error);
        }
    },

    async requestWithdrawals(walletId, payload = {}) {
        try {
            const response = await api.post(`/withdraw/requestWithdrawals/${walletId}`, payload);
            return unwrap(response);
        } catch (error) {
            throw normalizeError(error);
        }
    },

    async updateWithdrawals(id, payload = {}) {
        try {
            const response = await api.post(`/withdraw/updateWithdrawals/${id}`, payload);
            return unwrap(response);
        } catch (error) {
            throw normalizeError(error);
        }
    },

    async deleteWithdrawals(id) {
        try {
            const response = await api.delete("/withdraw/deleteWithdrawals", {
                data: { id },
            });

            return unwrap(response);
        } catch (error) {
            throw normalizeError(error);
        }
    },
};

export default withdrawalServices;
