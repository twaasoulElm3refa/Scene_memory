import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import { createI18n  } from "vue-i18n";
import ar from "./i18n/ar.json";
import en from "./i18n/en.json";
import fr from "./i18n/fr.json";
import de from "./i18n/de.json";
import ru from "./i18n/ru.json";
import es from "./i18n/es.json";
import it from "./i18n/it.json";
import hi from "./i18n/hi.json";
import '../css/app.css';

const messages = {
    ar,
    en,
    fr,
    de,
    ru,
    es,
    it,
    hi
}
const i18n = createI18n({
    legacy: false,
    locale: localStorage.getItem('language')?.toLowerCase() || 'ar',
    fallbackLocale: 'ar',
    messages
})
import "./bootstrap";
import "animate.css";
import "../css/app.css";
import "bootstrap/dist/css/bootstrap.css";
import "bootstrap-icons/font/bootstrap-icons.css";
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";

// My components imports
import navbarComponent from "./components/layouts/Navbar.vue";
import footer from "./components/layouts/footer.vue";
import AdminHeader from "./layouts/AdminHeader.vue";
import AdminLayout from "./layouts/AdminLayout.vue";
import AdminSidebar from "./layouts/AdminSidebar.vue";
const app = createApp(App);

app.component("navbar-component", navbarComponent);
app.component("footer-component", footer);
app.component("admin-header", AdminHeader);
app.component("admin-layout", AdminLayout);
app.component("admin-sidebar", AdminSidebar);

localStorage.setItem('theme', 'light');
document.documentElement.classList.remove('dark');
app.use(router);
app.use(i18n);
app.mount("#app");
