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

  googleLogin(params = {}) {
    return api.get("/users/google-login", { params, suppressGlobalErrorToast: true });
  },

  async exchangeGoogleCode(code) {
    await api.get("/sanctum/csrf-cookie", { baseURL: "/", suppressGlobalErrorToast: true });
    return api.post("/users/google-exchange", { code }, { suppressGlobalErrorToast: true });
  },

  exchangeMobileGoogleCode(code, codeVerifier) {
    return api.post(`${googleAuthOrigin()}/api/v1/users/google-mobile-exchange`, {
      code,
      code_verifier: codeVerifier,
    }, { suppressGlobalErrorToast: true });
  },

  getProfile() {
    return api.get("/users/profile");
  },

  confirmGoogleLink(password) {
    return api.post("/users/google-link-confirm", { password }, { suppressGlobalErrorToast: true });
  },

  async startGoogleRecovery() {
    await api.get("/sanctum/csrf-cookie", { baseURL: "/", suppressGlobalErrorToast: true });
    return api.post("/users/google-recovery-start", {}, { suppressGlobalErrorToast: true });
  },

  confirmGoogleRecovery(code) {
    return api.post("/users/google-recovery-confirm", { code }, { suppressGlobalErrorToast: true });
  },

  startGoogleLink() {
    return api.get("/users/google-link-start");
  },

  unlinkGoogle() {
    return api.post("/users/google-unlink");
  },
};
