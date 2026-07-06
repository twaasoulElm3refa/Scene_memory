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

export function normalizeErrorMessage(message, fallback = "حدث خطأ غير معروف.") {
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

export function showSafeToast(type = "error", message, fallback = "حدث خطأ غير معروف.") {
    const safeType = typeof toastr[type] === "function" ? type : "error";
    const safeMessage = normalizeErrorMessage(message, fallback);

    console.log("Toast message:", safeMessage);
    toastr[safeType](safeMessage);
}

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

        console.group("Axios Global Error");
        console.error("Full error:", error);
        console.error("Status:", status);
        console.error("Response data:", error?.response?.data);
        console.groupEnd();

        if (error.config?.suppressGlobalErrorToast) {
            return Promise.reject(error);
        }

        if (!error.response) {
            showSafeToast(
                "error",
                error.message,
                "تعذر الاتصال بالخادم. تحقق من اتصال الشبكة وحاول مرة أخرى."
            );
            return Promise.reject(error);
        }

        switch (status) {
            case 400:
                showSafeToast(
                    "warning",
                    error.response?.data?.message,
                    "الطلب غير صحيح. راجع البيانات المرسلة."
                );
                break;

            case 401:
                showSafeToast(
                    "error",
                    error.response?.data?.message,
                    "انتهت الجلسة. من فضلك سجل الدخول مرة أخرى."
                );

                localStorage.removeItem("auth_token");

                setTimeout(() => {
                    window.location.href = `/${getLang()}/auth`;
                }, 1500);
                break;

            case 403:
                showSafeToast(
                    "error",
                    error.response?.data?.message,
                    "ليس لديك صلاحية لتنفيذ هذا الإجراء."
                );
                break;

            case 404:
                showSafeToast(
                    "error",
                    error.response?.data?.message,
                    "العنصر المطلوب غير موجود."
                );
                break;

            case 422: {
                const errors = error.response.data.errors;

                if (errors) {
                    Object.values(errors).forEach((fieldErrors) => {
                        const messages = Array.isArray(fieldErrors) ? fieldErrors : [fieldErrors];
                        messages.forEach((msg) => showSafeToast(
                            "error",
                            msg,
                            "من فضلك راجع الحقول المطلوبة."
                        ));
                    });
                } else {
                    showSafeToast(
                        "error",
                        error.response?.data?.message,
                        "من فضلك راجع الحقول المطلوبة."
                    );
                }

                break;
            }

            case 429:
                showSafeToast(
                    "warning",
                    error.response?.data?.message,
                    "تم إرسال طلبات كثيرة. حاول مرة أخرى بعد قليل."
                );
                break;

            case 500:
                showSafeToast(
                    "error",
                    error.response?.data?.message,
                    "حدث خطأ في الخادم. راجع Console و Laravel log لمعرفة التفاصيل."
                );
                break;

            case 502:
            case 503:
            case 504:
                showSafeToast(
                    "error",
                    error.response?.data?.message,
                    "الخدمة غير متاحة حاليًا. حاول مرة أخرى بعد قليل."
                );
                break;

            default:
                showSafeToast(
                    "error",
                    error.response?.data?.message || error.message,
                    "حدث خطأ غير معروف."
                );
        }

        return Promise.reject(error);
    }
);

export default api;
