import api from "../ApiClient";

export const PlanService = {
    getAll() {
        return api.get("/plans/all");
    },

    getSingle(slug) {
        return api.get(`/plans/single/${slug}`);
    },

    subscribe(planId) {
        return api.post(`/subscribe/${planId}`);
    },

    subscribeWallet(planId) {
        return api.post(`/plans/subscribe/${planId}/wallet`);
    },

    subscribePayPal(planId) {
        // The plan page shows its own translated error, so skip the raw global toast.
        return api.post(`/plans/subscribe/${planId}/paypal`, null, { suppressGlobalErrorToast: true });
    },

    getSavingsEstimate(planId) {
        return api.get(`/plans/${planId}/savings-estimate`, { suppressGlobalErrorToast: true });
    },

    getUserCredits() {
        return api.get("/media-credits/balance", { suppressGlobalErrorToast: true });
    },

    getCreditHistory(params = {}) {
        return api.get("/media-credits/history", { params });
    },

    checkMediaEligibility(mediaId) {
        return api.get(`/media-credits/check/${mediaId}`);
    },

    redeemMedia(mediaId) {
        return api.post(`/media-credits/redeem/${mediaId}`);
    },

    // Admin endpoints
    getAdminTiers() {
        return api.get("/admin/media-tiers");
    },

    saveAdminTier(data) {
        return api.post("/admin/media-tiers", data);
    },

    deleteAdminTier(id) {
        return api.delete(`/admin/media-tiers/${id}`);
    },

    validatePricing(params) {
        return api.get("/admin/plans/validate", { params });
    },

    getCreditReports() {
        return api.get("/admin/plan-credit-reports");
    },

    /**
     * جلب كل الـ Plans
     */
    async getAllPlans() {
        try {
            const response = await api.get("/plans/all");
            return response.data.data || [];
        } catch (error) {
            console.error("❌ Error fetching plans:", error);
            throw error;
        }
    }
};
