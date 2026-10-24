import api from "../ApiClient";
import { GoogleAuthService } from "./GoogleAuthService";

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
    return GoogleAuthService.start(params?.lang);
  },

  exchangeGoogleCode(code) {
    return GoogleAuthService.exchangeWebCode(code);
  },

  exchangeMobileGoogleCode(code, codeVerifier) {
    return GoogleAuthService.exchangeMobileGoogleCode(code, codeVerifier);
  },

  getProfile() {
    return api.get("/users/profile");
  },

  confirmGoogleLink(password) {
    return GoogleAuthService.confirmLink(password);
  },

  startGoogleRecovery() {
    return GoogleAuthService.startRecovery();
  },

  confirmGoogleRecovery(code) {
    return GoogleAuthService.confirmRecovery(code);
  },

  startGoogleLink() {
    return GoogleAuthService.startLink();
  },

  unlinkGoogle() {
    return GoogleAuthService.unlink();
  },
};

