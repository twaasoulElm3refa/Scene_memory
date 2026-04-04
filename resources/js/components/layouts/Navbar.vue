<template>
    <header class="navbar-wrap shadow-lg" :dir="isArabic ? 'ltr' : 'rtl'">
        <div class="container d-flex justify-content-between align-items-center p-1">

            <div class="cart-wrapper">
                <button class="cart-btn" @click="goToCart">
                    🛒
                    <span class="cart-badge">{{ count }}</span>
                </button>
            </div>

            <nav class="d-none d-md-flex flex-grow-1 justify-content-center align-items-center gap-2">
                <RouterLink v-for="link in links" :key="link.active" :to="localizedPath(link.path)"
                    class="nav-link px-2" :class="{ active: isActive(link.active) }">
                    {{ $t(link.labelKey) }}
                </RouterLink>

                <RouterLink :to="localizedPath('/plans')" class="nav-link px-2" :class="{ active: isActive('plans') }">
                    {{ $t('nav.plans') }}
                </RouterLink>

                <div class="position-relative">
                    <button class="nav-link px-2 d-flex align-items-center gap-1" @click="moreOpen = !moreOpen">
                        {{ $t('nav.more') }}
                        <i class="bi transition" :class="moreOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>

                    <transition name="fade-slide">
                        <div v-if="moreOpen" class="dropdown-menu show shadow rounded mt-2 p-2"
                            style="position:absolute; right:0; top:100%; z-index:1000; min-width:220px;">
                            <RouterLink class="dropdown-item" :to="localizedPath('/WishList')">
                                {{ $t('nav.favourites') }}
                            </RouterLink>

                            <RouterLink class="dropdown-item" :to="localizedPath('/contact')">
                                {{ $t('nav.contact') }}
                            </RouterLink>

                            <RouterLink class="dropdown-item" :to="localizedPath('/terms')">
                                {{ $t('nav.terms') }}
                            </RouterLink>

                            <RouterLink class="dropdown-item" :to="localizedPath('/who')">
                                {{ $t('nav.about') }}
                            </RouterLink>
                        </div>
                    </transition>
                </div>
            </nav>

            <button class="btn-icon d-md-none" @click="mobileMenu = !mobileMenu">
                <i class="bi" :class="mobileMenu ? 'bi-x-lg' : 'bi-list'"></i>
            </button>

            <div class="d-flex align-items-center gap-2">
                <!-- Language Selector -->
                <div class="position-relative">
                    <button class="btn-user user-hover fw-bold shadow-gray d-flex align-items-center gap-1"
                        @click="languageDropdownOpen = !languageDropdownOpen">
                        {{ currentLanguage }}
                        <i class="bi" :class="languageDropdownOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>

                    <div v-if="languageDropdownOpen" class="dropdown-menu show shadow rounded mt-2"
                        style="position:absolute; right:0; top:100%; z-index:1000; min-width:140px;">
                        <button class="dropdown-item" v-for="lang in languages" :key="lang"
                            @click="selectLanguage(lang)">
                            {{ $t(`languages.${lang}`) }}
                        </button>
                    </div>
                </div>

                <!-- User / Login -->
                <div class="d-flex gap-2 position-relative">
                    <template v-if="isLoggedIn">
                        <button class="btn-user user-hover fw-bold d-flex align-items-center gap-2 shadow-gray"
                            @click="toggleDropdown">
                            {{ userName }}
                            <i class="bi" :class="dropdownOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        </button>

                        <div v-if="dropdownOpen" class="dropdown-menu show shadow rounded mt-2 dropdown-dark"
                            style="position:absolute; right:0; top:100%; margin-top:8px; z-index:1000; min-width:150px;">

                            <RouterLink class="dropdown-item" :to="localizedPath('/profile')">
                                {{ $t("nav.profile") }}
                            </RouterLink>

                            <!-- ✅ NEW BUTTON -->
                            <RouterLink class="dropdown-item" :to="localizedPath('/downloads')">
                                {{ $t("nav.downloads") }}
                            </RouterLink>

                            <hr class="dropdown-divider" />

                            <button class="dropdown-item text-danger" @click="logout">
                                {{ $t("nav.logout") }}
                            </button>
                        </div>
                    </template>

                    <template v-else>
                        <RouterLink :to="localizedPath('/auth')"
                            class="btn-user user-hover fw-bold shadow-gray text-decoration-none">
                            {{ $t("nav.login") }}
                        </RouterLink>
                    </template>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div v-if="mobileMenu" class="d-md-none mt-2 bg-white shadow-lg rounded p-3">
            <RouterLink v-for="link in links" :key="link.active" :to="localizedPath(link.path)"
                class="d-block nav-link py-2 px-1" :class="{ active: isActive(link.active) }">
                {{ $t(link.labelKey) }}
            </RouterLink>

            <RouterLink :to="localizedPath('/plans')" class="d-block nav-link py-2 px-1">
                {{ $t('nav.plans') }}
            </RouterLink>

            <hr />

            <RouterLink :to="localizedPath('/WishList')" class="d-block nav-link py-2 px-1">
                {{ $t('nav.favourites') }}
            </RouterLink>

            <RouterLink :to="localizedPath('/contact')" class="d-block nav-link py-2 px-1">
                {{ $t('nav.contact') }}
            </RouterLink>

            <RouterLink :to="localizedPath('/terms')" class="d-block nav-link py-2 px-1">
                {{ $t('nav.terms') }}
            </RouterLink>

            <RouterLink :to="localizedPath('/who')" class="d-block nav-link py-2 px-1">
                {{ $t('nav.about') }}
            </RouterLink>
        </div>
    </header>
</template>

<script setup>
import { ref, onMounted, computed, watch } from "vue";
import axios from "axios";
import { useRouter, useRoute, RouterLink } from "vue-router";
import { useI18n } from "vue-i18n";

const router = useRouter();
const route = useRoute();
const { locale } = useI18n();

/* STATE */
const isLoggedIn = ref(false);
const userName = ref("Kullanıcı");
const count = ref(0);
const userImage = ref(null);

const dropdownOpen = ref(false);
const mobileMenu = ref(false);
const moreOpen = ref(false);
const languageDropdownOpen = ref(false);

/* Languages - Added TR */
const languages = ["AR", "EN", "FR", "DE", "RU", "ES", "IT", "HI", "JA", "FA", "ZH", "UR", "TR"];

/* ROUTE LANG */
const routeLang = computed(() => (route.params.lang || "en").toLowerCase());

const currentLanguage = computed(() => routeLang.value.toUpperCase());

const isArabic = computed(() => routeLang.value === "ar");

/* Sync i18n + axios + localStorage with URL */
watch(
    routeLang,
    (lang) => {
        locale.value = lang;
        localStorage.setItem("language", lang);
        axios.defaults.headers.common["Accept-Language"] = lang;

        document.documentElement.lang = lang;
        document.documentElement.dir = lang === "ar" ? "rtl" : "ltr";
    },
    { immediate: true }
);

const localizedPath = (path) => {
    const cleanPath = path.startsWith("/") ? path : `/${path}`;
    return `/${routeLang.value}${cleanPath}`;
};

const selectLanguage = async (lang) => {
    const newLang = lang.toLowerCase();
    languageDropdownOpen.value = false;
    const currentPath = route.fullPath;
    const newPath = currentPath.replace(/^\/[a-z]{2}(?=\/|$)/, `/${newLang}`);

    locale.value = newLang;
    localStorage.setItem("language", newLang);
    axios.defaults.headers.common["Accept-Language"] = newLang;

    await router.push(newPath);
    window.location.reload();
};

/* NAV */
const allLinks = [
    { labelKey: "nav.home", path: "/home", active: "home" },
    { labelKey: "nav.allEvents", path: "/all_events", active: "all_events" },
    { labelKey: "nav.addEvent", path: "/add_event", active: "add_event" },
    { labelKey: "nav.historical", path: "/historical", active: "historical" },
];

const links = computed(() => {
    if (!isLoggedIn.value) {
        return allLinks.filter(
            (l) =>
                l.active !== "add_event" &&
                l.active !== "Wishlist" &&
                l.active !== "historical"
        );
    }
    return allLinks;
});

/* USER */
const userInitial = computed(() => {
    if (!userName.value) return "UU";
    const parts = userName.value.split(" ");
    let initials = parts[0].charAt(0).toUpperCase();
    if (parts[1]) initials += parts[1].charAt(0).toUpperCase();
    return initials;
});

const isActive = (name) => {
    return route.name === name || route.path.includes(name);
};

const toggleDropdown = () => {
    dropdownOpen.value = !dropdownOpen.value;
};

const logout = () => {
    localStorage.removeItem("auth_token");
    localStorage.removeItem("user_role");
    localStorage.removeItem("licence_name");
    localStorage.removeItem("is_profile_filled");

    isLoggedIn.value = false;
    userName.value = "Kullanıcı";

    router.push(localizedPath("/auth"));
};

/* PROFILE */
const fetchProfile = async () => {
    const token = localStorage.getItem("auth_token");
    if (!token) return;

    axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;

    try {
        const res = await axios.get("/v1/users/profile");
        if (res.data.status === "success") {
            const userData = res.data.data.user;
            count.value = userData.items || 0;
            userName.value = userData.name || "Kullanıcı";

            userImage.value = userData.image || null;
            isLoggedIn.value = true;
        }
    } catch (err) {
        localStorage.clear();
        isLoggedIn.value = false;
    }
};

onMounted(() => {
    fetchProfile();
    window.addEventListener("login", fetchProfile);
});

const goToCart = () => {
    router.push(localizedPath("/cart"));
};
</script>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.2s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

/* ─────────────────────────────────────────────── */
.navbar-wrap {
    background: var(--nav-bg);
    color: var(--text-main);
    transition: all 0.4s ease;
}

/* Logo */
.logo {
    width: 140px;
    height: 80px;
    object-fit: cover;
}

/* ── Navigation Links ── */
.nav-link {
    margin: 0 3px;
    color: var(--text-main);
    text-decoration: none;
    font-weight: 500;
    padding: 5px 8px;
    font-size: 14px;
    position: relative;
    padding-bottom: 4px;
    transition: color 0.3s ease;
    display: inline-block;
    white-space: nowrap;
    text-align: center;
}

/* Animated Underline */
.nav-link::after {
    content: "";
    position: absolute;
    width: 0;
    height: 2.5px;
    bottom: 0;
    right: 0;
    background: var(--gray);
    transition: width 0.4s cubic-bezier(0.22, 0.61, 0.36, 1);
    border-radius: 4px;
}

/* Light Mode → Cart hover أسود */

[data-theme="dark"] .nav-link:hover,
[data-theme="dark"] .nav-link.active {
    color: var(--gray);
}

[data-theme="dark"] .nav-link:hover::after,
[data-theme="dark"] .nav-link.active::after {
    width: 100%;
}

/* ── Light Mode → كل حاجة أبيض وأسود ── */
[data-theme="light"] .nav-link,
[data-theme="light"] .nav-link:hover,
[data-theme="light"] .nav-link.active {
    color: #111827;
}

[data-theme="light"] .nav-link::after {
    background: #111827;
}

[data-theme="light"] .nav-link:hover::after,
[data-theme="light"] .nav-link.active::after {
    width: 100%;
}

/* ── Buttons ── */
.btn-icon {
    width: 42px;
    position: relative;
    height: 42px;
    border-radius: 50%;
    background: var(--btn-bg);
    color: var(--gray);
    border: none;
    display: grid;
    place-items: center;
    transition: all 0.25s ease;
}

.btn-icon .badge {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

/* Dark mode icon & bg */
[data-theme="dark"] .btn-icon {
    color: var(--gray);
}

[data-theme="dark"] .btn-icon:hover {
    background: var(--gray);
    color: #000;
    transform: scale(1.08);
}

/* ── Light mode: أبيض وأسود فقط ── */
[data-theme="light"] .btn-icon {
    background: #ffffff;
    color: #111827;
    border: 1px solid #d1d5db;
}

.user-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--gray);
}

.user-avatar-wrapper {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.user-avatar,
.user-placeholder {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    object-fit: cover;
    background-color: #6b7280;
    border: 2px solid #6b7280;
}

/* Placeholder style */
.user-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--gray);
    color: #000;
    font-weight: bold;
    font-size: 20px;
}

[data-theme="light"] .btn-icon:hover {
    background: #f3f4f6;
    color: #111827;
    border-color: #9ca3af;
    transform: scale(1.08);
}

/* Cart Badge – في الـ light mode */
[data-theme="light"] .badge {
    background: #111827;
    color: #ffffff;
}

/* ── Login / User Button ── */
.btn-user {
    background: transparent;
    border: 1.5px solid var(--gray);
    color: var(--gray);
    text-decoration: none;
    padding: 6px 10px;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
    white-space: nowrap;
}

/* Dark mode */
[data-theme="dark"] .btn-user {
    border-color: var(--gray);
    color: var(--gray);
}

[data-theme="dark"] .user-hover:hover {
    background: var(--gray);
    color: #000;
    transform: scale(1.06);
    box-shadow: 0 0 25px rgba(251, 191, 36, 0.7);
}

/* Light mode: أبيض وأسود فقط */
[data-theme="light"] .btn-user {
    border: 1.5px solid #111827;
    color: #111827;
}

[data-theme="light"] .user-hover:hover {
    background: #111827;
    color: #ffffff;
    transform: scale(1.06);
    box-shadow: 0 0 16px rgba(17, 24, 39, 0.35);
}

.cart-wrapper {
    position: relative;
}

.cart-btn {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    color: white;
    font-size: 22px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: 0.2s;
}

.cart-btn:hover {
    transform: scale(1.08);
}

.cart-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: red;
    color: white;
    font-size: 12px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}
</style>
