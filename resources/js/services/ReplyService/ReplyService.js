import api from "../ApiClient";

export const ReplyService = {
  createReply(commentId, payload) {
    return api.post(`/replies/reply/${commentId}`, payload);
  },
};
