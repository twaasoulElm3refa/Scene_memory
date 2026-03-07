import axios from "axios";

const getToken = () => localStorage.getItem("auth_token");

const api = axios.create({
  baseURL: "/api/v1",
  headers: {
    Accept: "application/json",
  },
});

api.interceptors.request.use((config) => {
  const token = getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  const lang = localStorage.getItem("language") || "ar";
  config.headers["Accept-Language"] = lang;

  return config;
});

export default api;
