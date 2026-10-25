<template>

    <header class="navbar-wrap">

        <div class="container-fluid navbar-shell">

            <div class="navbar-inner">

                <nav class="desktop-nav d-none d-md-flex align-items-center">

                    <RouterLink v-for="link in links" :key="link.active" :to="localizedPath(link.path)"
                        class="nav-link premium-nav-link" :class="{ active: isActive(link.active) }">

                        {{ $t(link.labelKey) }}

                    </RouterLink>

                    <RouterLink :to="localizedPath(navigationGroups.plans.path)" class="nav-link premium-nav-link"
                        :class="{ active: isActive('plans') }">

                        {{ $t(navigationGroups.plans.labelKey) }}

                    </RouterLink>

                    <button type="button" class="nav-link premium-nav-link nav-dropdown-trigger"
                        @click="openSpecialCoverage">

                        {{ $t(navigationGroups.specialCoverage.labelKey) }}

                    </button>

                    <!-- Events Dropdown -->

                    <div class="navbar-dropdown-wrap">

                        <button class="nav-link premium-nav-link nav-dropdown-trigger d-flex align-items-center gap-1"
                            @click="eventsOpen = !eventsOpen">

                            {{ $t('nav.events') }}

                            <i class="bi transition" :class="eventsOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>

                        </button>

                        <transition name="fade-slide">

                            <div v-if="eventsOpen" class="dropdown-menu show navbar-dropdown">

                                <RouterLink v-for="link in eventLinks" :key="link.path" class="dropdown-item" :to="localizedPath(link.path)">{{ $t(link.labelKey) }}</RouterLink>

                            </div>

                        </transition>

                    </div>

                    <!-- More Dropdown -->

                    <div class="navbar-dropdown-wrap">

                        <button class="nav-link premium-nav-link nav-dropdown-trigger d-flex align-items-center gap-1"
                            @click="moreOpen = !moreOpen">

                            {{ $t('nav.more') }}

                            <i class="bi transition" :class="moreOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>

                        </button>

                        <transition name="fade-slide">

                            <div v-if="moreOpen" class="dropdown-menu show navbar-dropdown">

                                <RouterLink v-for="link in moreLinks" :key="link.path" class="dropdown-item" :to="localizedPath(link.path)">{{ $t(link.labelKey) }}</RouterLink>

                            </div>

                        </transition>

                    </div>

                </nav>

                <div class="navbar-actions">

                    <!-- Language Selector -->

                    <div class="navbar-dropdown-wrap">

                        <button class="control-btn language-btn d-flex align-items-center gap-1"
                            @click="languageDropdownOpen = !languageDropdownOpen">

                            <i class="bi bi-globe2"></i>

                            <span>{{ currentLanguage }}</span>

                            <i class="bi chevron"
                                :class="languageDropdownOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>

                        </button>

                        <transition name="fade-slide">

                            <div v-if="languageDropdownOpen"
                                class="dropdown-menu show navbar-dropdown language-dropdown">

                                <button class="dropdown-item" v-for="lang in languages" :key="lang"
                                    @click="selectLanguage(lang)">

                                    {{ $t(`languages.${lang}`) }}

                                </button>

                            </div>

                        </transition>

                    </div>

                    <!-- Media Credits / Quotas Pill -->
                    <div v-if="isLoggedIn" class="navbar-dropdown-wrap credits-control">
                        <button class="control-btn credits-menu-button d-flex align-items-center gap-2"
                            @click="creditsDropdownOpen = !creditsDropdownOpen"
                            :title="$t('mediaCredits.myCredits')">
                            <span class="credit-pill photo-pill d-flex align-items-center gap-1">
                                <i class="bi bi-camera-fill text-primary"></i>
                                <span class="credit-count">{{ userCredits.total_photos }}</span>
                            </span>
                            <span class="credit-pill-divider">|</span>
                            <span class="credit-pill video-pill d-flex align-items-center gap-1">
                                <i class="bi bi-camera-reels-fill text-info"></i>
                                <span class="credit-count">{{ userCredits.total_videos }}</span>
                            </span>
                            <i class="bi chevron" :class="creditsDropdownOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        </button>

                        <transition name="fade-slide">
                            <div v-if="creditsDropdownOpen" class="dropdown-menu show navbar-dropdown credits-dropdown p-3" style="min-width: 260px;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold">{{ $t('mediaCredits.myCredits') }}</span>
                                    <span class="badge bg-primary text-uppercase">{{ userCredits.highest_tier || $t('mediaCredits.noTier') }}</span>
                                </div>
                                <hr class="dropdown-divider my-2" />
                                <div class="d-flex justify-content-between align-items-center py-1">
                                    <span class="text-muted"><i class="bi bi-camera-fill text-primary me-1"></i> {{ $t('mediaCredits.photosAvailable') }}</span>
                                    <strong class="fs-6">{{ userCredits.total_photos }}</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1 mb-2">
                                    <span class="text-muted"><i class="bi bi-camera-reels-fill text-info me-1"></i> {{ $t('mediaCredits.videosAvailable') }}</span>
                                    <strong class="fs-6">{{ userCredits.total_videos }}</strong>
                                </div>

                                <div v-if="userCredits.by_tier && userCredits.by_tier.length > 0" class="mb-3">
                                    <div class="small text-muted mb-1">{{ $t('mediaCredits.breakdownByTier') }}:</div>
                                    <div v-for="t in userCredits.by_tier" :key="t.tier_rank" class="d-flex justify-content-between small py-1 border-bottom border-light">
                                        <span class="fw-semibold">{{ t.tier_name }}</span>
                                        <span>{{ t.remaining_photos }} <i class="bi bi-camera-fill text-muted"></i> / {{ t.remaining_videos }} <i class="bi bi-camera-reels-fill text-muted"></i></span>
                                    </div>
                                </div>

                                <div class="d-flex flex-column gap-2 mt-2">
                                    <RouterLink :to="localizedPath('/profile?tab=credits')" class="btn btn-sm btn-outline-primary text-center" @click="creditsDropdownOpen = false">
                                        {{ $t('mediaCredits.viewDashboard') }}
                                    </RouterLink>
                                    <RouterLink :to="localizedPath('/plans')" class="btn btn-sm btn-primary text-center" @click="creditsDropdownOpen = false">
                                        {{ $t('mediaCredits.getMoreCredits') }}
                                    </RouterLink>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <!-- User / Login -->

                    <div class="navbar-dropdown-wrap user-control">

                        <template v-if="isLoggedIn">

                            <button class="control-btn user-menu-button d-flex align-items-center gap-2"
                                @click="toggleDropdown">

                                <span class="user-initial">{{ userInitial }}</span>

                                <span class="user-name">{{ userName }}</span>

                                <i class="bi chevron" :class="dropdownOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>

                            </button>

                            <transition name="fade-slide">

                                <div v-if="dropdownOpen" class="dropdown-menu show navbar-dropdown user-dropdown">

                                    <RouterLink v-for="link in accountLinks" :key="link.path" class="dropdown-item" :to="localizedPath(link.path)">{{ $t(link.labelKey) }}</RouterLink>

                                    <hr class="dropdown-divider" />

                                    <button class="dropdown-item logout-item" @click="logout">

                                        {{ $t("nav.logout") }}

                                    </button>

                                </div>

                            </transition>

                        </template>

                        <template v-else>

                            <RouterLink :to="localizedPath(navigationGroups.login.path)" class="control-btn login-btn text-decoration-none">

                                {{ $t(navigationGroups.login.labelKey) }}

                            </RouterLink>

                        </template>

                    </div>

                    <div class="cart-wrapper">

                        <button class="cart-btn" type="button" @click="goToCart" :aria-label="$t('nav.cart')">

                            <i class="bi bi-bag"></i>

                            <span class="cart-badge">{{ count }}</span>

                        </button>

                    </div>

                    <RouterLink :to="localizedPath('/home')" class="brand-mark text-decoration-none"
                        :aria-label="$t('nav.scemoryHome')">

                        <span class="brand-logo">

                            <img :src="logoUrl" alt="Scemory logo" />

                        </span>

                        <span class="brand-name d-none d-lg-inline">Scemory</span>

                    </RouterLink>

                    <button class="mobile-toggle d-md-none" type="button" @click="mobileMenu = !mobileMenu"
                        :aria-label="$t('nav.toggleMenu')">

                        <i class="bi" :class="mobileMenu ? 'bi-x-lg' : 'bi-list'"></i>

                    </button>

                </div>

            </div>

        </div>

        <!-- Mobile Menu -->

        <transition name="fade-slide">

            <div v-if="mobileMenu" class="container d-md-none navbar-mobile-menu">

                <div class="mobile-menu-panel">

                    <div class="mobile-section">

                        <RouterLink v-for="link in links" :key="link.active" :to="localizedPath(link.path)"
                            class="mobile-nav-link" :class="{ active: isActive(link.active) }">

                            {{ $t(link.labelKey) }}

                        </RouterLink>

                        <RouterLink :to="localizedPath(navigationGroups.plans.path)" class="mobile-nav-link"
                            :class="{ active: isActive('plans') }">

                            {{ $t(navigationGroups.plans.labelKey) }}

                        </RouterLink>

                        <button type="button" class="mobile-nav-link mobile-special-coverage-button"
                            @click="openSpecialCoverage">

                            {{ $t(navigationGroups.specialCoverage.labelKey) }}

                        </button>

                    </div>

                    <div class="mobile-section">

                        <p class="mobile-section-title">{{ $t('nav.events') }}</p>

                        <RouterLink v-for="link in eventLinks" :key="link.path" class="mobile-nav-link" :to="localizedPath(link.path)">{{ $t(link.labelKey) }}</RouterLink>

                    </div>

                    <div class="mobile-section">

                        <p class="mobile-section-title">{{ $t('nav.more') }}</p>

                        <RouterLink v-for="link in mobileMoreLinks" :key="link.path" class="mobile-nav-link" :to="localizedPath(link.path)">{{ $t(link.labelKey) }}</RouterLink>

                    </div>

                </div>

            </div>

        </transition>

    </header>

</template>

<script setup>

import { ref, toRef, onMounted, onUnmounted, computed, watch } from "vue";
import { Capacitor } from "@capacitor/core";
import axios from "axios";
import api from "@/services/ApiClient";
import { isMobileRuntime } from "@/services/runtimeUrls";
import { clearAuthToken, getAuthToken } from "@/services/authTokenStorage";
import { PlanService } from '@/services/planService/planService';
import { clearAccountState, refreshAccount } from '@/services/accountState';

import { useRouter, useRoute, RouterLink } from "vue-router";

import { useI18n } from "vue-i18n";

import { navigationGroups, navigationSession, visibleNavigation, specialCoverageDestination } from '../../navigation';

const router = useRouter();

const route = useRoute();

const { locale, availableLocales } = useI18n();

/*** STATE ***/

const isLoggedIn = toRef(navigationSession, 'isLoggedIn');

const userName = ref("UserName");

const count = ref(0);

const userImage = ref(null);

const eventCount = toRef(navigationSession, 'eventCount');

const dropdownOpen = ref(false);

const creditsDropdownOpen = ref(false);

const userCredits = ref({
    total_photos: 0,
    total_videos: 0,
    highest_tier: null,
    by_tier: []
});

const fetchCredits = async () => {
    if (!isLoggedIn.value) return;
    try {
        const res = await PlanService.getUserCredits();
        if (res.data?.data) {
            userCredits.value = res.data.data;
        }
    } catch {
        // Non-blocking
    }
};

const mobileMenu = ref(false);

const moreOpen = ref(false);

const languageDropdownOpen = ref(false);

const eventsOpen = ref(false);

/*** Languages ***/

const languages = availableLocales.map(lang => lang.toUpperCase());

/*** ROUTE LANG ***/

const routeLang = computed(() => (route.params.lang || "en").toLowerCase());

const currentLanguage = computed(() => routeLang.value.toUpperCase());
/*** Sync i18n + axios + localStorage with URL ***/

watch(

    routeLang,

    (lang) => {

        locale.value = lang;

        localStorage.setItem("language", lang);

        axios.defaults.headers.common["Accept-Language"] = lang;
    },

    { immediate: true }

);

const localizedPath = (path) => {

    const cleanPath = path.startsWith("/") ? path : `/${path}`;

    return `/${routeLang.value}${cleanPath}`;

};

const useMobileAssets =
    Capacitor.isNativePlatform() ||
    import.meta.env.VITE_FORCE_MOBILE_API === "true";

const assetBaseUrl = String(
    import.meta.env.VITE_API_URL || ""
).replace(/\/+$/, "");

const logoUrl =
    useMobileAssets && assetBaseUrl
        ? `${assetBaseUrl}/images/logo.png`
        : "/images/logo.png";

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

/*** NAV ***/

const links = computed(() => visibleNavigation(navigationGroups.primary, navigationSession));
const eventLinks = computed(() => visibleNavigation(navigationGroups.events, navigationSession));
const accountLinks = computed(() => visibleNavigation(navigationGroups.account, navigationSession));
const moreLinks = navigationGroups.more;
const mobileMoreLinks = [...navigationGroups.more, ...navigationGroups.mobileMore];
const openSpecialCoverage = async () => {
    mobileMenu.value = false;
    await router.push(specialCoverageDestination(routeLang.value, Boolean(getAuthToken()),
        route.path === localizedPath('/home') ? route.query : {}));
};

/*** USER ***/

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

const logout = async () => {
    try {
        await api.post('/users/logout', {}, { suppressGlobalErrorToast: true });
    } catch {
        // Offline sign-out still clears local credentials; server revocation requires connectivity.
    }

    await clearAuthToken();
    clearAccountState();

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

/*** PROFILE ***/

const fetchProfile = async () => {

    const token = getAuthToken();

    if (!token) {
        isLoggedIn.value = false;
        eventCount.value = 0;
        clearAccountState();
        return;
    }

    try {
        const data = await refreshAccount();
        if (data?.user) {
            const userData = data.user;

            count.value = userData.items || 0;

            userName.value = userData.name || "UserName";

            userImage.value = userData.image || null;

            eventCount.value = Number(userData.event_count || 0);

            isLoggedIn.value = true;

            void fetchCredits();

        }

    } catch (err) {
        if (err.response?.status !== 401) return;
        await clearAuthToken();
        localStorage.removeItem("user_role");
        localStorage.removeItem("user_data");

        isLoggedIn.value = false;

        eventCount.value = 0;
        clearAccountState();

    }

};

const handleCartUpdated = () => {

    void fetchProfile();

};

const handleVisibilityChange = () => {
    if (document.visibilityState === "visible") void fetchProfile();
};

watch(() => route.fullPath, () => { void fetchProfile(); });

onMounted(() => {

    fetchProfile();

    window.addEventListener("login", fetchProfile);

    window.addEventListener("cart-updated", handleCartUpdated);

    window.addEventListener("credits-updated", fetchCredits);
    window.addEventListener("focus", fetchProfile);
    document.addEventListener("visibilitychange", handleVisibilityChange);

});

onUnmounted(() => {

    window.removeEventListener("login", fetchProfile);

    window.removeEventListener("cart-updated", handleCartUpdated);

    window.removeEventListener("credits-updated", fetchCredits);
    window.removeEventListener("focus", fetchProfile);
    document.removeEventListener("visibilitychange", handleVisibilityChange);

});

const goToCart = () => {

    router.push(localizedPath(navigationGroups.cart.path));

};

</script>

<style scoped>
.fade-slide-enter-active,

.fade-slide-leave-active {

    transition: opacity 0.22s ease, transform 0.22s ease;

}

.credit-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.credit-pill-divider {
    opacity: 0.35;
    margin: 0 1px;
}

.credits-dropdown {
    width: 280px;
    background: #ffffff;
    color: #1e293b;
    border: 1px solid #c9ddef;
    border-radius: 16px;
    box-shadow: 0 12px 30px rgba(13, 77, 151, 0.15);
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

    margin-inline-start: auto;

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

.mobile-section+.mobile-section {

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

.mobile-special-coverage-button {

    width: 100%;

    background: transparent;

    font-family: inherit;

    text-align: inherit;

    cursor: pointer;

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

        min-width: 0;

        margin-left: 0;

        display: flex;

        align-items: center;

        justify-content: flex-start;

        flex-wrap: nowrap;

        gap: 6px;

    }

    .user-control {

        order: 1;

        min-width: 0;

        flex: 0 0 auto;

        margin-right: auto;

    }

    .navbar-actions>.navbar-dropdown-wrap:not(.user-control) {

        order: 2;

        flex: 0 0 auto;

    }

    .cart-wrapper {

        order: 3;

        flex: 0 0 auto;

    }

    .brand-mark {

        order: 4;

        flex: 0 0 auto;

        height: 42px;

        padding: 4px;

    }

    .mobile-toggle {

        order: 5;

        flex: 0 0 auto;

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

    .brand-logo {

        width: 34px;

        height: 34px;

    }

    .brand-logo img {

        width: 27px;

        height: 27px;

    }

    .user-control .navbar-dropdown {

        left: 0;

        right: auto;

        min-width: 180px;

    }

    .language-dropdown {

        left: auto;

        right: 0;

        min-width: 150px;

    }

    .navbar-mobile-menu {

        width: calc(100% - 24px);

        max-width: none;

        margin: 10px auto 0;

        padding-left: 0;

        padding-right: 0;

    }

}

@media (max-width: 420px) {

    .navbar-shell {

        width: calc(100% - 16px);

    }

    .navbar-inner {

        padding: 7px;

        border-radius: 23px;

    }

    .navbar-actions {

        gap: 4px;

    }

    .control-btn {

        height: 38px;

        min-height: 38px;

        padding: 0 8px;

        font-size: 11px;

    }

    .language-btn {

        min-width: 46px;

        width: 46px;

        padding: 0 6px;

    }

    .language-btn .bi-globe2,

    .language-btn .chevron,

    .user-menu-button .chevron {

        display: none;

    }

    .user-menu-button {

        width: 40px;

        max-width: 40px;

        min-width: 40px;

        padding: 0;

    }

    .user-name {

        display: none;

    }

    .login-btn {

        min-width: 64px;

        max-width: 88px;

        padding: 0 9px;

        overflow: hidden;

        text-overflow: ellipsis;

    }

    .cart-btn,

    .mobile-toggle {

        width: 38px;

        height: 38px;

        min-height: 38px;

    }

    .brand-mark {

        width: 40px;

        height: 40px;

        padding: 3px;

        justify-content: center;

    }

    .brand-logo {

        width: 32px;

        height: 32px;

    }

    .brand-logo img {

        width: 25px;

        height: 25px;

    }

    .navbar-mobile-menu {

        width: calc(100% - 16px);

    }

}
</style>
