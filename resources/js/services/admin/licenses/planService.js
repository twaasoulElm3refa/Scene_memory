import axios from 'axios';

// إعداد base URL
const api = axios.create({
    baseURL: 'http://localhost:8000/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Accept-Language': 'ar',
    },
});

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
