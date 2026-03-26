import api from "./ApiClient";

export const PlanService = {
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
