import api from "../ApiClient";

export const ContactService = {
  create(payload) {
    return api.post("/contacts/create", payload);
  },
};
