import axios from 'axios';

// إعداد base URL
const api = axios.create({
    baseURL: 'http://localhost:8000/api/v1/plans',
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
            const response = await api.get('/all/admin');
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    // جلب باقة واحدة بالـ id
    getSingle: async (id) => {
        try {
            const response = await api.get(`/single/admin/${id}`);
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    // إنشاء باقة جديدة
    create: async (data) => {
        try {
            const response = await api.post('/create', data);
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    // تحديث باقة
    update: async (id, data) => {
        try {
            const response = await api.put(`/update/${id}`, data);
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },

    // حذف باقة
    delete: async (id) => {
        try {
            const response = await api.delete(`/delete/${id}`);
            return response.data;
        } catch (error) {
            throw error.response || error;
        }
    },
};

export default PlanService;
