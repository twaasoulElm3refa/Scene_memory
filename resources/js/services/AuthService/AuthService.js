import api from "../ApiClient";
import { googleAuthOrigin } from "../runtimeUrls";

export const AuthService = {
  login(payload) {
    return api.post("/users/login", payload);
  },

  register(formData) {
    return api.post("/users/register", formData);
  },

  verifyRegisterOtp(payload) {
    return api.post("/users/register/verify-otp", payload);
  },

  resendRegisterOtp(payload) {
    return api.post("/users/register/resend-otp", payload);
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

  exchangeMobileGoogleCode(code, codeVerifier) {
    return api.post(`${googleAuthOrigin()}/api/v1/users/google-mobile-exchange`, {
      code,
      code_verifier: codeVerifier,
    });
  },

  getProfile() {
    return api.get("/users/profile");
  },
};
