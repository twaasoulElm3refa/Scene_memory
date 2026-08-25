<template>
    <header class="navbar-wrap" :class="{ 'is-arabic': isArabic }" :dir="isArabic ? 'rtl' : 'ltr'">
        <div class="container-fluid navbar-shell">
            <div class="navbar-inner">
                <nav class="desktop-nav d-none d-md-flex align-items-center">
                    <RouterLink
                        v-for="link in links"
                        :key="link.active"
                        :to="localizedPath(link.path)"
                        class="nav-link premium-nav-link"
                        :class="{ active: isActive(link.active) }"
                    >
                        {{ $t(link.labelKey) }}
                    </RouterLink>

                    <RouterLink
                        :to="localizedPath('/plans')"
                        class="nav-link premium-nav-link"
                        :class="{ active: isActive('plans') }"
                    >
                        {{ $t('nav.plans') }}
                    </RouterLink>

                    <!-- Events Dropdown -->
                    <div class="navbar-dropdown-wrap">
                        <button
                            class="nav-link premium-nav-link nav-dropdown-trigger d-flex align-items-center gap-1"
                            @click="eventsOpen = !eventsOpen"
                        >
                            {{ $t('nav.events') }}
                            <i class="bi transition" :class="eventsOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        </button>

                        <transition name="fade-slide">
                            <div
                                v-if="eventsOpen"
                                class="dropdown-menu show navbar-dropdown"
                            >
                                <RouterLink class="dropdown-item" :to="localizedPath('/all_events')">
                                    {{ $t('nav.allEvents') }}
                                </RouterLink>

                                <RouterLink
                                    v-if="isLoggedIn"
                                    class="dropdown-item"
                                    :to="localizedPath('/add_event')"
                                >
                                    {{ $t('nav.addEvent') }}
                                </RouterLink>

                                <RouterLink
                                    v-if="isLoggedIn"
                                    class="dropdown-item"
                                    :to="localizedPath('/historical')"
                                >
                                    {{ $t('nav.historical') }}
                                </RouterLink>
                            </div>
                        </transition>
                    </div>

                    <!-- More Dropdown -->
                    <div class="navbar-dropdown-wrap">
                        <button
                            class="nav-link premium-nav-link nav-dropdown-trigger d-flex align-items-center gap-1"
                            @click="moreOpen = !moreOpen"
                        >
                            {{ $t('nav.more') }}
                            <i class="bi transition" :class="moreOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        </button>

                        <transition name="fade-slide">
                            <div
                                v-if="moreOpen"
                                class="dropdown-menu show navbar-dropdown"
                            >
                                <RouterLink class="dropdown-item" :to="localizedPath('/WishList')">
                                    {{ $t('nav.favourites') }}
                                </RouterLink>

                                <RouterLink class="dropdown-item" :to="localizedPath('/contact')">
                                    {{ $t('nav.contact') }}
                                </RouterLink>

                                <!-- <RouterLink class="dropdown-item" :to="localizedPath('/terms')">
                                    {{ $t('nav.terms') }}
                                </RouterLink>

                                <RouterLink class="dropdown-item" :to="localizedPath('/who')">
                                    {{ $t('nav.about') }}
                                </RouterLink> -->
                            </div>
                        </transition>
                    </div>
                </nav>

                <div class="navbar-actions">
                    <!-- Language Selector -->
                    <div class="navbar-dropdown-wrap">
                        <button
                            class="control-btn language-btn d-flex align-items-center gap-1"
                            @click="languageDropdownOpen = !languageDropdownOpen"
                        >
                            <i class="bi bi-globe2"></i>
                            <span>{{ currentLanguage }}</span>
                            <i class="bi chevron" :class="languageDropdownOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        </button>

                        <transition name="fade-slide">
                            <div
                                v-if="languageDropdownOpen"
                                class="dropdown-menu show navbar-dropdown language-dropdown"
                            >
                                <button
                                    class="dropdown-item"
                                    v-for="lang in languages"
                                    :key="lang"
                                    @click="selectLanguage(lang)"
                                >
                                    {{ $t(`languages.${lang}`) }}
                                </button>
                            </div>
                        </transition>
                    </div>

                    <!-- User / Login -->
                    <div class="navbar-dropdown-wrap user-control">
                        <template v-if="isLoggedIn">
                            <button
                                class="control-btn user-menu-button d-flex align-items-center gap-2"
                                @click="toggleDropdown"
                            >
                                <span class="user-initial">{{ userInitial }}</span>
                                <span class="user-name">{{ userName }}</span>
                                <i class="bi chevron" :class="dropdownOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                            </button>

                            <transition name="fade-slide">
                                <div
                                    v-if="dropdownOpen"
                                    class="dropdown-menu show navbar-dropdown user-dropdown"
                                >
                                    <RouterLink class="dropdown-item" :to="localizedPath('/profile')">
                                        {{ $t("nav.profile") }}
                                    </RouterLink>

                                    <RouterLink class="dropdown-item" :to="localizedPath('/downloads')">
                                        {{ $t("nav.downloads") }}
                                    </RouterLink>

                                    <RouterLink
                                        v-if="eventCount > 0"
                                        class="dropdown-item"
                                        :to="localizedPath('/creator/events')"
                                    >
                                        {{ $t("nav.dashboard") }}
                                    </RouterLink>

                                    <hr class="dropdown-divider" />

                                    <button class="dropdown-item logout-item" @click="logout">
                                        {{ $t("nav.logout") }}
                                    </button>
                                </div>
                            </transition>
                        </template>

                        <template v-else>
                            <RouterLink
                                :to="localizedPath('/auth')"
                                class="control-btn login-btn text-decoration-none"
                            >
                                {{ $t("nav.login") }}
                            </RouterLink>
                        </template>
                    </div>

                    <div class="cart-wrapper">
                        <button class="cart-btn" type="button" @click="goToCart" :aria-label="$t('nav.cart')">
                            <i class="bi bi-bag"></i>
                            <span class="cart-badge">{{ count }}</span>
                        </button>
                    </div>

                    <RouterLink :to="localizedPath('/home')" class="brand-mark text-decoration-none" :aria-label="$t('nav.scemoryHome')">
                        <span class="brand-logo">
                            <img src="/images/logo.png" alt="Scemory logo" />
                        </span>
                        <span class="brand-name d-none d-lg-inline">Scemory</span>
                    </RouterLink>

                    <button class="mobile-toggle d-md-none" type="button" @click="mobileMenu = !mobileMenu" :aria-label="$t('nav.toggleMenu')">
                        <i class="bi" :class="mobileMenu ? 'bi-x-lg' : 'bi-list'"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <transition name="fade-slide">
            <div
                v-if="mobileMenu"
                class="container d-md-none navbar-mobile-menu"
            >
                <div class="mobile-menu-panel">
                    <div class="mobile-section">
                        <RouterLink
                            v-for="link in links"
                            :key="link.active"
                            :to="localizedPath(link.path)"
                            class="mobile-nav-link"
                            :class="{ active: isActive(link.active) }"
                        >
                            {{ $t(link.labelKey) }}
                        </RouterLink>

                        <RouterLink
                            :to="localizedPath('/plans')"
                            class="mobile-nav-link"
                            :class="{ active: isActive('plans') }"
                        >
                            {{ $t('nav.plans') }}
                        </RouterLink>
                    </div>

                    <div class="mobile-section">
                        <p class="mobile-section-title">{{ $t('nav.events') }}</p>
                        <RouterLink class="mobile-nav-link" :to="localizedPath('/all_events')">
                            {{ $t('nav.allEvents') }}
                        </RouterLink>

                        <RouterLink
                            v-if="isLoggedIn"
                            class="mobile-nav-link"
                            :to="localizedPath('/add_event')"
                        >
                            {{ $t('nav.addEvent') }}
                        </RouterLink>

                        <RouterLink
                            v-if="isLoggedIn"
                            class="mobile-nav-link"
                            :to="localizedPath('/historical')"
                        >
                            {{ $t('nav.historical') }}
                        </RouterLink>
                    </div>

                    <div class="mobile-section">
                        <p class="mobile-section-title">{{ $t('nav.more') }}</p>
                        <RouterLink :to="localizedPath('/WishList')" class="mobile-nav-link">
                            {{ $t('nav.favourites') }}
                        </RouterLink>

                        <RouterLink :to="localizedPath('/contact')" class="mobile-nav-link">
                            {{ $t('nav.contact') }}
                        </RouterLink>

                        <RouterLink :to="localizedPath('/terms')" class="mobile-nav-link">
                            {{ $t('nav.terms') }}
                        </RouterLink>

                        <RouterLink :to="localizedPath('/who')" class="mobile-nav-link">
                            {{ $t('nav.about') }}
                        </RouterLink>
                    </div>
                </div>
            </div>
        </transition>
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
const userName = ref("UserName");
const count = ref(0);
const userImage = ref(null);
const eventCount = ref(0);

const dropdownOpen = ref(false);
const mobileMenu = ref(false);
const moreOpen = ref(false);
const languageDropdownOpen = ref(false);
const eventsOpen = ref(false);

/* Languages */
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
    { labelKey: "nav.scemoryGate", path: "/scemory-gate", active: "scemory-gate" },
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

    if (parts[1]) {
        initials += parts[1].charAt(0).toUpperCase();
    }

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
    localStorage.removeItem("admin_token");
    localStorage.removeItem("admin_user");
    localStorage.removeItem("user_role");
    localStorage.removeItem("licence_name");
    localStorage.removeItem("is_profile_filled");

    isLoggedIn.value = false;
    userName.value = "Kullanici";
    eventCount.value = 0;

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
            userName.value = userData.name || "UserName";
            userImage.value = userData.image || null;
            eventCount.value = Number(userData.event_count || 0);

            isLoggedIn.value = true;
        }
    } catch (err) {
        localStorage.clear();
        isLoggedIn.value = false;
        eventCount.value = 0;
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
    transition: opacity 0.22s ease, transform 0.22s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

.navbar-wrap {
    --navy-950: #f7fafd;
    --navy-900: #f7fafd;
    --blue-500: #1677ff;
    --blue-400: #30a8ff;
    --text-soft: #334a62;
    --glass-border: #c9ddef;
    position: sticky !important;
    top: 0 !important;
    left: 0;
    right: 0;
    z-index: 2147483000 !important;
    width: 100%;
    padding: 14px 0 8px;
    color: #334a62;
    background: transparent;
    isolation: isolate;
    overflow: visible !important;
}

.navbar-shell {
    position: relative;
    z-index: 2147483001 !important;
    width: calc(100% - 96px);
    max-width: 1600px;
    margin: 0 auto;
    padding: 0;
    overflow: visible !important;
}

.navbar-inner {
    position: relative;
    z-index: 2147483001 !important;
    min-height: 70px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 10px 14px 10px 20px;
    direction: ltr;
    overflow: visible !important;
    border: 1px solid var(--glass-border);
    border-radius: 999px;
    background:
        radial-gradient(circle at 88% 50%, rgba(48, 168, 255, 0.10), transparent 32%),
        linear-gradient(135deg, rgba(247, 250, 253, 0.96), rgba(237, 244, 250, 0.92));
    box-shadow:
        0 14px 35px rgba(13, 77, 151, 0.10),
        0 3px 10px rgba(15, 23, 42, 0.05),
        inset 0 1px 0 rgba(255, 255, 255, 0.65);
    backdrop-filter: blur(22px);
    -webkit-backdrop-filter: blur(22px);
}

.desktop-nav {
    min-width: 0;
    flex: 0 1 auto;
    justify-content: flex-start;
    gap: 6px;
}

.premium-nav-link {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    padding: 0 14px;
    border: 1px solid transparent;
    border-radius: 999px;
    color: var(--text-soft);
    background: transparent;
    font-size: 14px;
    font-weight: 650;
    line-height: 1;
    letter-spacing: 0;
    text-align: center;
    text-decoration: none;
    white-space: nowrap;
    transition: color 0.22s ease, background 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease, transform 0.22s ease;
}

.premium-nav-link:hover,
.premium-nav-link.active {
    color: #0d4d97;
    border-color: rgba(22, 119, 255, 0.22);
    background: linear-gradient(135deg, #ddecf9, #e6f0f9);
    box-shadow: 0 5px 16px rgba(13, 77, 151, 0.09);
}

.premium-nav-link:hover {
    transform: translateY(-1px);
}

.premium-nav-link::after {
    display: none;
}

.nav-dropdown-trigger {
    cursor: pointer;
}

.transition {
    font-size: 12px;
    transition: transform 0.22s ease;
}

.navbar-actions {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    margin-left: auto;
}

.navbar-dropdown-wrap {
    position: relative;
    z-index: 2147483002 !important;
}

.control-btn,
.cart-btn,
.mobile-toggle {
    min-height: 42px;
    border: 1px solid #c9ddef;
    color: #334a62;
    background: rgba(243, 247, 252, 0.92);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.70);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    transition: color 0.22s ease, background 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease, transform 0.22s ease;
}

.control-btn {
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 13px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 750;
    line-height: 1;
    white-space: nowrap;
}

.control-btn:hover,
.cart-btn:hover,
.mobile-toggle:hover {
    color: #0d4d97;
    border-color: rgba(22, 119, 255, 0.30);
    background: #e6f0f9;
    box-shadow: 0 5px 16px rgba(13, 77, 151, 0.10), inset 0 1px 0 rgba(255, 255, 255, 0.72);
    transform: translateY(-1px);
}

.language-btn {
    min-width: 82px;
}

.chevron {
    font-size: 11px;
    opacity: 0.78;
}

.login-btn {
    min-width: 88px;
    color: #ffffff;
    border-color: rgba(22, 119, 255, 0.28);
    background: linear-gradient(135deg, #0d4d97, #1677ff);
    box-shadow: 0 8px 20px rgba(13, 77, 151, 0.18);
}

.login-btn:hover {
    color: #ffffff;
    border-color: rgba(48, 168, 255, 0.36);
    background: linear-gradient(135deg, #1677ff, #30a8ff);
    box-shadow: 0 10px 22px rgba(13, 77, 151, 0.20);
}

.user-menu-button {
    max-width: 190px;
    padding-left: 8px;
}

.user-initial {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    border-radius: 50%;
    color: #06142a;
    background: linear-gradient(135deg, #ddecf9, #30a8ff);
    font-size: 11px;
    font-weight: 900;
}

.user-name {
    max-width: 110px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.cart-wrapper {
    position: relative;
    z-index: 2147483002;
}

.cart-btn {
    position: relative;
    width: 46px;
    height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    color: #0d4d97;
    background: #f3f7fc;
    border-color: #c9ddef;
}

.cart-badge {
    position: absolute;
    top: -4px;
    right: -5px;
    min-width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    border: 2px solid #f7fafd;
    border-radius: 999px;
    color: #ffffff;
    background: linear-gradient(135deg, #1677ff, #30a8ff);
    box-shadow: 0 4px 12px rgba(22, 119, 255, 0.22);
    font-size: 11px;
    font-weight: 900;
    line-height: 1;
}

.brand-mark {
    height: 50px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 12px 5px 6px;
    border: 1px solid #c9ddef;
    border-radius: 999px;
    color: #06142a;
    background: linear-gradient(135deg, #f7fafd, #edf4fa);
    box-shadow: 0 6px 18px rgba(13, 77, 151, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.72);
    transition: transform 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease;
}

.brand-mark:hover {
    border-color: rgba(22, 119, 255, 0.30);
    box-shadow: 0 8px 20px rgba(13, 77, 151, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.75);
    transform: translateY(-1px);
}

.brand-logo {
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 50%;
    background: #f3f7fc;
    box-shadow: 0 4px 12px rgba(13, 77, 151, 0.10);
}

.brand-logo img {
    width: 31px;
    height: 31px;
    object-fit: contain;
}

.brand-name {
    color: #06142a;
    font-size: 15px;
    font-weight: 850;
    letter-spacing: 0;
}

.mobile-toggle {
    width: 44px;
    height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 20px;
}

.navbar-dropdown {
    position: absolute !important;
    top: calc(100% + 12px);
    right: 0;
    z-index: 2147483003 !important;
    min-width: 220px;
    padding: 8px;
    overflow: hidden;
    border: 1px solid #c9ddef;
    border-radius: 18px;
    background:
        radial-gradient(circle at top right, rgba(48, 168, 255, 0.10), transparent 45%),
        linear-gradient(145deg, rgba(247, 250, 253, 0.98), rgba(237, 244, 250, 0.97));
    box-shadow: 0 18px 45px rgba(13, 77, 151, 0.14), inset 0 1px 0 rgba(255, 255, 255, 0.72);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

.language-dropdown {
    min-width: 150px;
    max-height: 330px;
    overflow-y: auto;
}

.user-dropdown {
    min-width: 180px;
}

.dropdown-item {
    min-height: 40px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: 0;
    border-radius: 12px;
    color: #334a62;
    background: transparent;
    font-size: 14px;
    font-weight: 650;
    text-decoration: none;
    transition: color 0.18s ease, background 0.18s ease, transform 0.18s ease;
}

.dropdown-item:hover,
.dropdown-item:focus {
    color: #0d4d97;
    background: #e6f0f9;
    transform: translateX(2px);
}

.dropdown-divider {
    margin: 8px 4px;
    border-color: #dce8f5;
}

.logout-item {
    color: #dc5868;
}

.logout-item:hover,
.logout-item:focus {
    color: #b42335;
    background: #fdecef;
}

.navbar-mobile-menu {
    position: relative;
    z-index: 2147483003 !important;
    margin-top: 10px;
}

.mobile-menu-panel {
    padding: 14px;
    border: 1px solid #c9ddef;
    border-radius: 24px;
    background:
        radial-gradient(circle at top right, rgba(48, 168, 255, 0.10), transparent 42%),
        linear-gradient(145deg, rgba(247, 250, 253, 0.98), rgba(237, 244, 250, 0.97));
    box-shadow: 0 18px 45px rgba(13, 77, 151, 0.14), inset 0 1px 0 rgba(255, 255, 255, 0.72);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

.mobile-section {
    display: grid;
    gap: 6px;
    padding: 10px 0;
}

.mobile-section + .mobile-section {
    border-top: 1px solid #dce8f5;
}

.mobile-section-title {
    margin: 0 0 4px;
    color: #64748b;
    font-size: 11px;
    font-weight: 850;
    letter-spacing: 0;
    text-transform: uppercase;
}

.mobile-nav-link {
    min-height: 44px;
    display: flex;
    align-items: center;
    padding: 0 14px;
    border: 1px solid transparent;
    border-radius: 14px;
    color: #334a62;
    font-size: 14px;
    font-weight: 750;
    text-decoration: none;
    transition: color 0.2s ease, background 0.2s ease, border-color 0.2s ease;
}

.mobile-nav-link:hover,
.mobile-nav-link.active {
    color: #0d4d97;
    border-color: rgba(22, 119, 255, 0.22);
    background: #e6f0f9;
}

.navbar-wrap :deep(.dropdown-menu),
.navbar-wrap .dropdown-menu {
    z-index: 2147483003 !important;
}

.is-arabic .desktop-nav,
.is-arabic .dropdown-item,
.is-arabic .mobile-menu-panel {
    direction: rtl;
}

.is-arabic .dropdown-item:hover,
.is-arabic .dropdown-item:focus {
    transform: translateX(-2px);
}

@media (max-width: 1199.98px) {
    .navbar-shell {
        width: calc(100% - 48px);
    }

    .navbar-inner {
        gap: 10px;
        padding-left: 14px;
    }

    .desktop-nav {
        gap: 4px;
    }

    .premium-nav-link {
        padding: 0 10px;
        font-size: 13px;
    }

    .user-name {
        max-width: 82px;
    }
}

@media (max-width: 767.98px) {
    .navbar-wrap {
        padding-top: 10px;
    }

    .navbar-shell {
        width: calc(100% - 24px);
    }

    .navbar-inner {
        min-height: 64px;
        padding: 9px;
        border-radius: 26px;
    }

    .navbar-actions {
        width: 100%;
        gap: 6px;
    }

    .control-btn {
        height: 40px;
        min-height: 40px;
        padding: 0 10px;
        font-size: 12px;
    }

    .language-btn {
        min-width: 68px;
    }

    .user-control {
        min-width: 0;
    }

    .user-menu-button {
        max-width: 118px;
        padding-right: 9px;
    }

    .user-name {
        max-width: 46px;
    }

    .login-btn {
        min-width: 68px;
    }

    .cart-btn,
    .mobile-toggle {
        width: 40px;
        height: 40px;
        min-height: 40px;
    }

    .brand-mark {
        height: 42px;
        padding: 4px;
    }

    .brand-logo {
        width: 34px;
        height: 34px;
    }

    .brand-logo img {
        width: 27px;
        height: 27px;
    }

    .navbar-dropdown {
        right: 0;
        min-width: 180px;
    }
}

@media (max-width: 420px) {
    .navbar-inner {
        padding: 8px;
    }

    .navbar-actions {
        gap: 5px;
    }

    .control-btn {
        padding: 0 8px;
    }

    .language-btn .bi-globe2,
    .language-btn .chevron,
    .user-menu-button .chevron {
        display: none;
    }

    .user-menu-button {
        max-width: 74px;
        padding: 0 7px;
    }

    .user-name {
        display: none;
    }

    .login-btn {
        min-width: auto;
    }
}
</style>
