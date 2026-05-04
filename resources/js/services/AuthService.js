import api from "./ApiClient";

export const AuthService = {
  login(payload) {
    return api.post("/users/login", payload);
  },

  register(formData) {
    return api.post("/users/register", formData);
  },

  forgotPassword(payload) {
    return api.post("/users/forgot-password", payload);
  },

  resetPassword(payload) {
    return api.post("/users/reset-password", payload);
  },

  googleLogin() {
    return api.get("/users/google-login");
  },

  googleCallback(code) {
    return api.get("/users/google-callback", { params: { code } });
  },

  getProfile() {
    return api.get("/users/profile");
  },
};
