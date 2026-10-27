import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import { Capacitor } from "@capacitor/core";
import { App as CapacitorApp } from "@capacitor/app";
import { Browser } from "@capacitor/browser";
import { mobileAppLinkOrigin } from "./services/runtimeUrls";
import { clearGoogleOAuthVerifier, restoreAuthToken } from "./services/authTokenStorage";
import { createI18n } from "vue-i18n";

// messages Languages: English (the fallback) is bundled; the others load on demand,
// before the first render and before any navigation that switches to them.
import en from "./i18n/en.json";
import eventDirectoryMessages from "./i18n/eventDirectory";
import discoverySearchMessages from "./i18n/discoverySearch";
import profileTimelineMessages from "./i18n/profileTimeline";
import weeklyCompetitionMessages from "./i18n/weeklyCompetition";
import virtualGiftMessages from "./i18n/virtualGifts";
import policyMessages from "./i18n/policies";
import planOptimizerMessages from "./i18n/planOptimizer";
import planCheckoutMessages from "./i18n/planCheckout";
import supportConversationMessages from "./i18n/supportConversation";

// styles
import "../css/app.css";
import "animate.css";
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";
import "bootstrap-icons/font/bootstrap-icons.css";
import "./bootstrap";

// components
import navbarComponent from "./components/layouts/Navbar.vue";
import footer from "./components/layouts/footer.vue";
import AdminHeader from "./layouts/AdminHeader.vue";
import AdminLayout from "./layouts/AdminLayout.vue";
import AdminSidebar from "./layouts/AdminSidebar.vue";

// Every supported language stays registered (availableLocales, language menu, hreflang).
const messages = {
    ar: {},
    en,
    fr: {},
    de: {},
    ru: {},
    es: {},
    it: {},
    hi: {},
    ja: {},
    zh: {},
    fa: {},
    ur: {},
    tr: {},
};
const localeLoaders = import.meta.glob(["./i18n/*.json", "!./i18n/en.json"], { import: "default" });
const loadedLocales = new Set();

const mergeFeatureMessages = (lang) => {
    if (eventDirectoryMessages[lang]) {
        messages[lang].events = {
            ...messages[lang].events,
            directory: eventDirectoryMessages[lang],
        };
    }

    if (discoverySearchMessages[lang]) messages[lang].discovery = discoverySearchMessages[lang];
    if (profileTimelineMessages[lang]) messages[lang].timeline = profileTimelineMessages[lang];
    if (weeklyCompetitionMessages[lang]) messages[lang].weeklyCompetition = weeklyCompetitionMessages[lang];
    if (virtualGiftMessages[lang]) messages[lang].gifts = virtualGiftMessages[lang];
    if (policyMessages[lang]) messages[lang].policies = policyMessages[lang];
    const planOptimizer = planOptimizerMessages[lang];
    if (planOptimizer) messages[lang].planOptimizer = planOptimizer;
    if (planCheckoutMessages[lang]) messages[lang].planCheckout = planCheckoutMessages[lang];
    if (supportConversationMessages[lang]) messages[lang].supportConversation = supportConversationMessages[lang];
};

mergeFeatureMessages("en");
loadedLocales.add("en");

const DEFAULT_LANG = "en";
const RTL_LANGS = ["ar", "fa", "ur"];

// تنظيف اللغة
const normalizeLang = (lang) => {
    const supported = Object.keys(messages);
    lang = (lang || "").toLowerCase();
    return supported.includes(lang) ? lang : DEFAULT_LANG;
};

const i18n = createI18n({
    legacy: false,
    locale: DEFAULT_LANG,
    fallbackLocale: "en",
    messages,
});

// Loads a language's messages once; on a network error English stays the fallback.
const ensureLocale = async (lang) => {
    if (loadedLocales.has(lang)) return;
    const loader = localeLoaders[`./i18n/${lang}.json`];
    if (!loader) return;
    try {
        messages[lang] = { ...(await loader()) };
        mergeFeatureMessages(lang);
        i18n.global.setLocaleMessage(lang, messages[lang]);
        loadedLocales.add(lang);
    } catch (error) {
        console.error(`Unable to load the ${lang} translations:`, error);
    }
};

router.beforeEach(async (to) => {
    await ensureLocale(normalizeLang(to.params.lang));
});

const app = createApp(App);

// register components
app.component("navbar-component", navbarComponent);
app.component("footer-component", footer);
app.component("admin-header", AdminHeader);
app.component("admin-layout", AdminLayout);
app.component("admin-sidebar", AdminSidebar);

/**
 * 🔥 أهم جزء: ربط اللغة مع الراوتر + الاتجاه
 */
router.afterEach((to) => {
    const lang = normalizeLang(to.params.lang);

    // i18n
    i18n.global.locale.value = lang;

    // axios (لو مستخدم)
    if (window.axios) {
        window.axios.defaults.headers.common["Accept-Language"] = lang;
    }

    // HTML attributes
    document.documentElement.lang = lang;
    document.documentElement.dir = RTL_LANGS.includes(lang) ? "rtl" : "ltr";

    localStorage.setItem("language", lang);
    localStorage.setItem("lang", lang);
    window.dispatchEvent(new CustomEvent("lang-changed", {
        detail: { lang },
    }));
});

/**
 * 🔥 أول تحميل (مهم جدًا)
 */
const initialLang = normalizeLang(router.currentRoute.value.params.lang);

i18n.global.locale.value = initialLang;

document.documentElement.lang = initialLang;
document.documentElement.dir = RTL_LANGS.includes(initialLang) ? "rtl" : "ltr";
localStorage.setItem("language", initialLang);
localStorage.setItem("lang", initialLang);

const MOBILE_GOOGLE_PENDING_KEY = "mobile_google_oauth_pending";

const configureNativeAppLinks = async () => {
    const handledUrls = new Set();
    let oauthWasBackgrounded = false;

    const handleAppUrl = async ({ url }) => {
        try {
            if (!url || handledUrls.has(url)) return;

            const callback = new URL(url);
            if (callback.origin !== mobileAppLinkOrigin() || callback.pathname !== "/mobile/auth/callback") return;

            const lang = normalizeLang(callback.searchParams.get("lang"));
            const code = callback.searchParams.get("code");
            const error = callback.searchParams.get("error");

            if ((!code || !/^[A-Za-z0-9]{64}$/.test(code)) && !error) return;

            handledUrls.add(url);
            sessionStorage.removeItem(MOBILE_GOOGLE_PENDING_KEY);

            try {
                await Browser.close();
            } catch {
            }

            await router.replace({
                name: "google-callback",
                params: { lang },
                query: code ? { code } : { error },
            });
        } catch (error) {
            console.error("Invalid app link:", error);
        }
    };

    await CapacitorApp.addListener("appUrlOpen", handleAppUrl);

    const launch = await CapacitorApp.getLaunchUrl();
    if (launch?.url) {
        await handleAppUrl(launch);
    }

    await CapacitorApp.addListener("appStateChange", ({ isActive }) => {
        const pendingSince = Number(sessionStorage.getItem(MOBILE_GOOGLE_PENDING_KEY) || 0);

        if (!isActive && pendingSince) {
            oauthWasBackgrounded = true;
            return;
        }

        if (!isActive || !oauthWasBackgrounded || !pendingSince) return;

        oauthWasBackgrounded = false;
        window.setTimeout(async () => {
            if (!sessionStorage.getItem(MOBILE_GOOGLE_PENDING_KEY)) return;

            sessionStorage.removeItem(MOBILE_GOOGLE_PENDING_KEY);
            await clearGoogleOAuthVerifier();
            await router.replace({
                name: "google-callback",
                params: { lang: normalizeLang(localStorage.getItem("lang")) },
                query: { error: "cancelled" },
            });
        }, 1200);
    });
};

const bootstrap = async () => {
    // The URL's language (as index.blade.php reads it) is ready before the first render.
    await Promise.all([
        restoreAuthToken(),
        ensureLocale(normalizeLang(window.location.pathname.split("/")[1])),
    ]);

    app.use(router);
    app.use(i18n);
    app.mount("#app");

    if (Capacitor.isNativePlatform()) {
        await configureNativeAppLinks();
    }
};

void bootstrap();
