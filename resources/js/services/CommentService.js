// src/services/CommentService.js
import api from './ApiClient';

/**
 * استخراج رسالة الخطأ الحقيقية من الباك إند
 * @param {any} error
 * @returns {string}
 */
export const extractErrorMessage = (error) => {
    if (!error) return 'حدث خطأ غير معروف';

    const data = error?.response?.data;
    if (!data) {
        if (error.message === 'Network Error') return 'لا يوجد اتصال بالخادم';
        return error.message || 'حدث خطأ في الاتصال';
    }

    // رسالة مباشرة
    if (typeof data === 'string') return data;

    // { message: "..." }
    if (data.message && typeof data.message === 'string') return data.message;

    // { error: "..." }
    if (data.error && typeof data.error === 'string') return data.error;

    // { errors: { field: ["msg"] } }
    if (data.errors && typeof data.errors === 'object') {
        const firstKey = Object.keys(data.errors)[0];
        const firstVal = data.errors[firstKey];
        if (Array.isArray(firstVal) && firstVal.length > 0) return firstVal[0];
        if (typeof firstVal === 'string') return firstVal;
    }

    // { data: { message: "..." } }
    if (data.data?.message) return data.data.message;

    return 'حدث خطأ من الخادم';
};

const CommentService = {
    /**
     * جلب كل التعليقات لإيفنت معين (paginated)
     */
    getAllComments(slug, page = 1) {
        return api
            .get(`/comments/${slug}`, { params: { page } })
            .then((res) => res.data)
            .catch((err) => {
                throw err;
            });
    },

    /**
     * إضافة reaction على تعليق
     * reactionType: 'support' | 'Exhibitions' | 'neutral'
     */
    reactToComment(commentId, reactionType) {
        return api
            .post(`/comments/${commentId}/${reactionType}`)
            .catch((err) => {
                throw err;
            });
    },

    /**
     * الإبلاغ عن تعليق
     */
    reportComment(commentId, reason) {
        return api
            .post(`/comments/${commentId}/report`, { reason })
            .catch((err) => {
                throw err;
            });
    },
};

export default CommentService;
