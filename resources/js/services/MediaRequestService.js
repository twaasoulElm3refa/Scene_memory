import api from "./ApiClient";

export const MediaRequestService = {
  upload(id, formData) {
    return api.post(`/media-request/upload/${id}`, formData);
  },
};
