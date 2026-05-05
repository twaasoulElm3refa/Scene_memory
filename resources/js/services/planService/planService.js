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
