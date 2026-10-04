import axios from "axios";
import { backendOrigin, isMobileRuntime } from "./runtimeUrls";
import { getAuthToken } from "./authTokenStorage";

const LANG_KEY = "language";
const SUPPORTED_LANGS = ["ar", "en", "ru", "fr", "zh"];

const getLang = () => {
    const lang = String(localStorage.getItem(LANG_KEY) || "").toLowerCase();
    return SUPPORTED_LANGS.includes(lang) ? lang : "ar";
};

const AdminApiClient = axios.create({
    baseURL: isMobileRuntime() && backendOrigin()
        ? `${backendOrigin()}/api/v1`
        : "/api/v1",
    headers: {
        Accept: "application/json",
        "Accept-Language": getLang(),
    },
});

AdminApiClient.interceptors.request.use(
    (config) => {
        const token =
            localStorage.getItem("admin_token") || getAuthToken();

        config.headers["Accept-Language"] = getLang();

        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        return config;
    },
    (error) => Promise.reject(error)
);

export default AdminApiClient;
