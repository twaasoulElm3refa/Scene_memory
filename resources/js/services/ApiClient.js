import axios from "axios";
import { Capacitor } from "@capacitor/core";
import { getAuthToken } from "./authTokenStorage";
import toastr from "toastr";
import "toastr/build/toastr.min.css";

const LANG_KEY = "language";
const LEGACY_LANG_KEY = "lang";

const SUPPORTED_LANGS = [
    "ar",
    "de",
    "en",
    "es",
    "fa",
    "fr",
    "hi",
    "it",
    "ja",
    "ru",
    "tr",
    "ur",
    "zh",
];

/*
|--------------------------------------------------------------------------
| Toastr Z-Index
|--------------------------------------------------------------------------
| يجعل رسائل Toastr تظهر فوق الـ Navbar والـ Modal وأي Overlay.
*/

const TOAST_STYLE_ID = "global-toastr-z-index";

if (!document.getElementById(TOAST_STYLE_ID)) {
    const toastrStyle = document.createElement("style");

    toastrStyle.id = TOAST_STYLE_ID;

    toastrStyle.textContent = `
        #toast-container {
            z-index: 2147483647 !important;
            position: fixed !important;
        }

        #toast-container > .toast {
            z-index: 2147483647 !important;
            position: relative !important;
            white-space: pre-line !important;
            word-break: break-word !important;
            max-width: 95vw !important;
        }
    `;

    document.head.appendChild(toastrStyle);
}

/*
|--------------------------------------------------------------------------
| Language
|--------------------------------------------------------------------------
*/

const getLang = () => {
    const routeLang = String(
        window.location.pathname.split("/").filter(Boolean)[0] || ""
    ).toLowerCase();

    if (SUPPORTED_LANGS.includes(routeLang)) {
        return routeLang;
    }

    const lang = String(
        localStorage.getItem(LANG_KEY) ||
        localStorage.getItem(LEGACY_LANG_KEY) ||
        ""
    ).toLowerCase();

    return SUPPORTED_LANGS.includes(lang)
        ? lang
        : "en";
};

/*
|--------------------------------------------------------------------------
| Toastr Settings
|--------------------------------------------------------------------------
*/

toastr.options = {
    closeButton: true,
    progressBar: false,
    positionClass: "toast-top-right",

    timeOut: 0,
    extendedTimeOut: 0,

    tapToDismiss: false,
    closeOnHover: false,

    newestOnTop: true,
    preventDuplicates: true,
};

/*
|--------------------------------------------------------------------------
| Error Helpers
|--------------------------------------------------------------------------
*/

export function normalizeErrorMessage(
    message,
    fallback = "حدث خطأ غير معروف."
) {
    if (!message || typeof message !== "string") {
        return fallback;
    }

    const cleaned = message.trim();

    const looksBrokenArabic =
        cleaned.includes("???") ||
        /^[?\s]+$/.test(cleaned);

    if (looksBrokenArabic) {
        return fallback;
    }

    return cleaned;
}

export function showSafeToast(
    type = "error",
    message,
    fallback = "حدث خطأ غير معروف."
) {
    const safeType =
        typeof toastr[type] === "function"
            ? type
            : "error";

    const safeMessage = normalizeErrorMessage(
        message,
        fallback
    );

    console.log("Toast message:", safeMessage);

    toastr[safeType](safeMessage);
}

/*
|--------------------------------------------------------------------------
| API Base URL
|--------------------------------------------------------------------------
|
| Web:
| /api/v1
|
| Mobile / Capacitor:
| VITE_API_URL + /api/v1
|
*/

const useMobileApi =
    Capacitor.isNativePlatform() ||
    import.meta.env.VITE_FORCE_MOBILE_API === "true";

const apiBaseURL =
    useMobileApi && import.meta.env.VITE_API_URL
        ? `${import.meta.env.VITE_API_URL.replace(/\/$/, "")}/api/v1`
        : "/api/v1";

/*
|--------------------------------------------------------------------------
| Axios Instance
|--------------------------------------------------------------------------
*/

const api = axios.create({
    baseURL: apiBaseURL,

    headers: {
        Accept: "application/json",
        "Accept-Language": getLang(),
    },
});

/*
|--------------------------------------------------------------------------
| Language Sync
|--------------------------------------------------------------------------
*/

const syncAcceptLanguageHeader = () => {
    api.defaults.headers.common["Accept-Language"] =
        getLang();
};

syncAcceptLanguageHeader();

window.addEventListener("lang-changed", (event) => {
    const lang =
        event?.detail?.lang ||
        getLang();

    api.defaults.headers.common["Accept-Language"] =
        lang;
});

window.addEventListener("storage", (event) => {
    if (
        (
            event.key === LANG_KEY ||
            event.key === LEGACY_LANG_KEY
        ) &&
        event.newValue
    ) {
        api.defaults.headers.common["Accept-Language"] =
            event.newValue;
    }
});

/*
|--------------------------------------------------------------------------
| Request Interceptor
|--------------------------------------------------------------------------
*/

api.interceptors.request.use(
    (config) => {
        const token = getAuthToken();

        const lang = getLang();

        config.headers =
            config.headers || {};

        config.headers["Accept-Language"] =
            lang;

        if (token) {
            config.headers.Authorization =
                `Bearer ${token}`;
        }

        return config;
    },

    (error) => {
        console.error(
            "API REQUEST INTERCEPTOR ERROR:",
            error
        );

        return Promise.reject(error);
    }
);

/*
|--------------------------------------------------------------------------
| Response Interceptor - DEBUG MODE
|--------------------------------------------------------------------------
|
| يعرض الخطأ الحقيقي مؤقتًا أثناء اختبار تطبيق الموبايل.
|
| يعرض:
| - HTTP Status
| - Axios Error Code
| - Method
| - Full URL
| - Server Message
| - Axios Message
|
| ولا يعرض Authorization Token.
|
*/

api.interceptors.response.use(
    (response) => response,

    (error) => {
        if (
            error.config?.suppressGlobalErrorToast
        ) {
            return Promise.reject(error);
        }

        if (!import.meta.env.DEV) {
            console.error("API request failed:", error);
            if (error.response?.status !== 401) {
                toastr.error(normalizeErrorMessage(error.response?.data?.message, "Request failed."));
            }
            return Promise.reject(error);
        }

        const method = String(
            error.config?.method || "GET"
        ).toUpperCase();

        const baseURL =
            error.config?.baseURL || "";

        const requestURL =
            error.config?.url || "";

        let fullURL = requestURL;

        if (
            requestURL &&
            !/^https?:\/\//i.test(requestURL)
        ) {
            fullURL =
                `${baseURL.replace(/\/$/, "")}` +
                `${requestURL.startsWith("/") ? "" : "/"}` +
                `${requestURL}`;
        }

        const status =
            error.response?.status || null;

        const code =
            error.code || "NO_CODE";

        let serverMessage = "";

        const responseData =
            error.response?.data;

        if (responseData) {
            if (
                typeof responseData === "string"
            ) {
                serverMessage =
                    responseData;
            } else if (
                typeof responseData === "object"
            ) {
                serverMessage =
                    responseData.message ||
                    responseData.error ||
                    "";

                if (!serverMessage) {
                    try {
                        serverMessage =
                            JSON.stringify(
                                responseData
                            );
                    } catch {
                        serverMessage =
                            "Unable to stringify server response";
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Debug Console
        |--------------------------------------------------------------------------
        */

        console.error(
            "================ API DEBUG ERROR ================"
        );

        console.error(
            "Message:",
            error.message
        );

        console.error(
            "Code:",
            code
        );

        console.error(
            "Status:",
            status
        );

        console.error(
            "Method:",
            method
        );

        console.error(
            "URL:",
            fullURL
        );

        console.error(
            "Response Data:",
            responseData
        );

        console.error(
            "Response Headers:",
            error.response?.headers
        );

        console.error(
            "Axios Error:",
            error
        );

        console.error(
            "================================================="
        );

        /*
        |--------------------------------------------------------------------------
        | Network Error
        |--------------------------------------------------------------------------
        |
        | مفيش Response من السيرفر أساسًا.
        |
        */

        if (!error.response) {
            const debugMessage =
                `NETWORK ERROR\n\n` +
                `Code: ${code}\n` +
                `Method: ${method}\n` +
                `URL: ${fullURL || "UNKNOWN URL"}\n` +
                `Message: ${error.message || "Unknown network error"}`;

            toastr.error(
                debugMessage,
                "API Debug Error",
                {
                    timeOut: 0,
                    extendedTimeOut: 0,
                    closeButton: true,
                    tapToDismiss: false,
                }
            );

            return Promise.reject(error);
        }

        /*
        |--------------------------------------------------------------------------
        | HTTP Error
        |--------------------------------------------------------------------------
        |
        | السيرفر رد فعليًا بـ 4xx أو 5xx.
        |
        */

        const debugMessage =
            `HTTP ${status}\n\n` +
            `Code: ${code}\n` +
            `Method: ${method}\n` +
            `URL: ${fullURL || "UNKNOWN URL"}\n` +
            `Server: ${
                serverMessage ||
                "No server message"
            }\n` +
            `Axios: ${
                error.message ||
                "No Axios message"
            }`;

        toastr.error(
            debugMessage,
            "API Debug Error",
            {
                timeOut: 0,
                extendedTimeOut: 0,
                closeButton: true,
                tapToDismiss: false,
            }
        );

        return Promise.reject(error);
    }
);

export default api;
