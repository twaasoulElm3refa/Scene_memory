import api from "../ApiClient";

export const LikeService = {
  getLikes(id) {
    return api.get(`/likes/${id}`);
  },

  createLike(id) {
    return api.post(`/likes/${id}/create`);
  },
};
