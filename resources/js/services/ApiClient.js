import axios from "axios";
import toastr from "toastr";
import "toastr/build/toastr.min.css";

const LANG_KEY = "language";
const SUPPORTED_LANGS = ["ar", "en", "ru", "fr", "zh"];

const getLang = () => {
    const lang = String(localStorage.getItem(LANG_KEY) || "").toLowerCase();
    return SUPPORTED_LANGS.includes(lang) ? lang : "ar";
};

toastr.options = {
    closeButton: true,
    progressBar: true,
    positionClass: "toast-top-right",
    timeOut: "3000",
};

const api = axios.create({
    baseURL: "/api/v1",
    headers: {
        Accept: "application/json",
        "Accept-Language": getLang(),
        "x-api-key": "K7xP9mQ2vR8tL3sNf6GdJ1aB9zW4cH0y",
    },
});

const syncAcceptLanguageHeader = () => {
    api.defaults.headers.common["Accept-Language"] = getLang();
};

syncAcceptLanguageHeader();

window.addEventListener("lang-changed", (event) => {
    const lang = event?.detail?.lang || getLang();
    api.defaults.headers.common["Accept-Language"] = lang;
});

window.addEventListener("storage", (event) => {
    if (event.key === LANG_KEY && event.newValue) {
        api.defaults.headers.common["Accept-Language"] = event.newValue;
    }
});

api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem("auth_token");
        const lang = getLang();

        config.headers["Accept-Language"] = lang;

        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        return config;
    },
    (error) => Promise.reject(error)
);

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;

        if (!error.response) {
            toastr.error("?? ????? ?? ??????? ????????");
            return Promise.reject(error);
        }

        switch (status) {
            case 400:
                toastr.warning("??? ??? ????");
                break;

            case 401:
                toastr.error("??? ????? ??????");

                localStorage.removeItem("auth_token");

                setTimeout(() => {
                    window.location.href = `/${getLang()}/auth`;
                }, 1500);
                break;

            case 403:
                toastr.error("??? ????? ?? ???????");
                break;

            case 404:
                toastr.error("?????? ??? ?????");
                break;

            case 422: {
                const errors = error.response.data.errors;

                if (errors) {
                    Object.values(errors).forEach((fieldErrors) => {
                        fieldErrors.forEach((msg) => toastr.error(msg));
                    });
                } else {
                    toastr.error("?????? ??? ?????");
                }

                break;
            }

            case 429:
                toastr.warning("??? ??????? ????? ???? ??????");
                break;

            case 500:
                toastr.error("??? ?? ???????");
                break;

            case 502:
            case 503:
            case 504:
                toastr.error("??????? ??? ???? ??????");
                break;

            default:
                toastr.error("??? ??? ??? ?????");
        }

        return Promise.reject(error);
    }
);

export default api;
