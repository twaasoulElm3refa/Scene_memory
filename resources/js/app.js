import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import { createI18n } from "vue-i18n";

// messages
import ar from "./i18n/ar.json";
import en from "./i18n/en.json";
import fr from "./i18n/fr.json";
import de from "./i18n/de.json";
import ru from "./i18n/ru.json";
import es from "./i18n/es.json";
import it from "./i18n/it.json";
import hi from "./i18n/hi.json";
import ja from "./i18n/ja.json";
import zh from "./i18n/zh.json";
import fa from "./i18n/fa.json";
import ur from "./i18n/ur.json";

// styles
import "../css/app.css";
import "animate.css";
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";
import "bootstrap-icons/font/bootstrap-icons.css";

// bootstrap (مرة واحدة بس)
import "./bootstrap";

// components
import navbarComponent from "./components/layouts/Navbar.vue";
import footer from "./components/layouts/footer.vue";
import AdminHeader from "./layouts/AdminHeader.vue";
import AdminLayout from "./layouts/AdminLayout.vue";
import AdminSidebar from "./layouts/AdminSidebar.vue";

const messages = {
    ar,
    en,
    fr,
    de,
    ru,
    es,
    it,
    hi,
    ja,
    zh,
    fa,
    ur,
};

// ✅ ممنوع localStorage هنا نهائيًا
const DEFAULT_LANG = "en";

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
    document.documentElement.dir = lang === "ar" ? "rtl" : "ltr";
});

/**
 * 🔥 أول تحميل (مهم جدًا)
 */
const initialLang = normalizeLang(router.currentRoute.value.params.lang);

i18n.global.locale.value = initialLang;

document.documentElement.lang = initialLang;
document.documentElement.dir = initialLang === "ar" ? "rtl" : "ltr";

app.use(router);
app.use(i18n);

app.mount("#app");
