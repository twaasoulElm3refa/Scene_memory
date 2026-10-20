import api from "../ApiClient";

export const FooterService = {
  links() {
    return api.get('/footer/links', { suppressGlobalErrorToast: true });
  },
  get() {
    return api.get("/footer");
  },

  update(payload) {
    return api.post("/footer/update", payload);
  },
};
