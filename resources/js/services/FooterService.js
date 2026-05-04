import api from "./ApiClient";

export const FooterService = {
  get() {
    return api.get("/footer");
  },

  update(payload) {
    return api.post("/footer/update", payload);
  },
};
