import api from "../../ApiClient";

// Service object
const PlanService = {
    // جلب كل الباقات
    getAll: async () => {
        try {
            const response = await api.get('/plans/all/admin');
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    // جلب باقة واحدة بالـ id
    getSingle: async (id) => {
        try {
            const response = await api.get(`/plans/single/admin/${id}`);
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    // إنشاء باقة جديدة
    create: async (data) => {
        try {
            const response = await api.post('/plans/create', data);
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    // تحديث باقة
    update: async (id, data) => {
        try {
            const response = await api.put(`/plans/update/${id}`, data);
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    // حذف باقة
    delete: async (id) => {
        try {
            const response = await api.delete(`/plans/delete/${id}`);
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    // Media Tiers
    getMediaTiers: async () => {
        try {
            const response = await api.get('/admin/media-tiers');
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    saveMediaTier: async (data) => {
        try {
            const response = await api.post('/admin/media-tiers', data);
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    deleteMediaTier: async (id) => {
        try {
            const response = await api.delete(`/admin/media-tiers/${id}`);
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    validatePricing: async (params) => {
        try {
            const response = await api.get('/admin/plans/validate', { params });
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    getCreditReports: async () => {
        try {
            const response = await api.get('/admin/plan-credit-reports');
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    // planService.js
    createBenefit(planId, data) {
        return api.post(`/benefits/create/${planId}`, data);
    },

    updateBenefit(benefitId, data) {
        return api.post(`/benefits/update/${benefitId}/plan`, data);
    },

    deleteBenefit(benefitId) {
        return api.delete(`/benefits/delete/${benefitId}/plan`);
    }
};

export default PlanService;
