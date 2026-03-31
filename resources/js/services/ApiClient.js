import axios from "axios";

const api = axios.create({
  baseURL: "/api/v1",
  headers: {
    Accept: "application/json",
  },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem("auth_token");

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  const lang =
    localStorage.getItem("language") || navigator.language || navigator.userLanguage;

  config.headers["Accept-Language"] = lang;

  return config;
});

export default api;
