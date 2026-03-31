import axios from "axios";
import router from "@/router";

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

  const route = router.currentRoute.value;
  const langFromUrl = route.params?.lang;
  const langFromStorage = localStorage.getItem("language");

  const lang = (langFromUrl || langFromStorage || "en").toLowerCase();

  config.headers["Accept-Language"] = lang;

  return config;
});

export default api;
