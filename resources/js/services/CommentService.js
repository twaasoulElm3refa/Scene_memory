// src/services/CommentService.js
import api from './ApiClient';

const CommentService = {
    /**
     * جلب كل التعليقات لإيفنت معين (paginated)
     * @param {string} slug - الـ slug اللي في الـ URL (مثال: first-human-landing-on-the-moon-IFPBr-1772873327)
     * @param {number} page - رقم الصفحة (اختياري، افتراضي 1)
     * @returns {Promise} response من الـ API
     */
    getAllComments(slug, page = 1) {
        return api.get(`/comments/${slug}`, {
            params: {
                page: page
            }
        })
            .then(response => response.data)
            .catch(error => {
                console.error("Error fetching comments:", error);
                throw error;
            });
    },

    reactToComment(commentId, reactionType) {
        return api.post(`/comments/${commentId}/${reactionType}`);
    },

    reportComment(commentId, reason) {
        return axios.post(`/v1/comments/${commentId}/report`, { reason });
    }
};

export default CommentService;
