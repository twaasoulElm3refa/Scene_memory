<template>
    <aside class="creator-sidebar overflow-auto" :class="{ 'is-open': isOpen }" aria-label="Creator navigation">
        <!-- Logo / Brand -->
        <div class="sidebar-logo">
            <div class="logo-circle">
                <i class="fa-solid fa-camera"></i>
            </div>

            <span class="logo-text mt-2">
                <i class="fa-solid fa-user-pen me-2"></i>
                SceMory
            </span>
        </div>

        <div class="sidebar-spacer my-4"></div>

        <!-- Main Navigation -->
        <nav class="sidebar-nav" aria-label="Creator main navigation">
            <!-- Events Dropdown -->
            <div class="sidebar-group">
                <button type="button" class="sidebar-btn group" :class="{ 'active-parent': eventsActive }"
                    @click="toggle('events')">
                    <span class="flex items-center gap-3">
                        <CalendarIcon class="w-5 h-5" />
                        Event
                    </span>

                    <ChevronIcon class="w-5 h-5 transition-transform duration-300"
                        :class="{ 'rotate-180': open.events }" />
                </button>

                <Transition name="dropdown">
                    <div v-if="open.events" class="dropdown">
                        <RouterLink :to="{ name: 'creator-events', params: { lang: currentLang } }"
                            class="sidebar-btn dropdown-item text-decoration-none"
                            :class="{ active: route.name === 'creator-events' }"
                            :aria-current="route.name === 'creator-events' ? 'page' : undefined" @click="emit('close')">
                            <CalendarIcon class="w-5 h-5" />
                            Events
                        </RouterLink>
                    </div>
                </Transition>
            </div>

            <!-- Wallet Dropdown -->
            <div class="sidebar-group">
                <button type="button" class="sidebar-btn group" :class="{ 'active-parent': walletActive }"
                    @click="toggle('wallet')">
                    <span class="flex items-center gap-3">
                        <WalletIcon class="w-5 h-5" />
                        Wallet
                    </span>

                    <ChevronIcon class="w-5 h-5 transition-transform duration-300"
                        :class="{ 'rotate-180': open.wallet }" />
                </button>

                <Transition name="dropdown">
                    <div v-if="open.wallet" class="dropdown">
                        <RouterLink :to="{ name: 'creator-wallet', params: { lang: currentLang } }"
                            class="sidebar-btn dropdown-item text-decoration-none"
                            :class="{ active: route.name === 'creator-wallet' }"
                            :aria-current="route.name === 'creator-wallet' ? 'page' : undefined" @click="emit('close')">
                            <WalletIcon class="w-5 h-5" />
                            Wallet
                        </RouterLink>
                    </div>
                </Transition>
            </div>
        </nav>

        <div class="flex-grow"></div>

        <!-- Bottom Buttons -->
        <div class="bottom-actions">
            <RouterLink :to="visitSitePath" class="sidebar-btn visit-site-btn group text-decoration-none"
                @click="emit('close')">
                <span class="flex items-center gap-3 justify-center font-medium">
                    <ArrowUpRightIcon class="w-5 h-5" />
                    Visit Site
                </span>
            </RouterLink>

            <button type="button" class="sidebar-btn logout-btn group" aria-label="Logout" @click="logout">
                <span class="flex items-center gap-3 justify-center font-medium">
                    <ArrowLeftOnRectangleIcon class="w-5 h-5" />
                    Logout
                </span>
            </button>
        </div>
    </aside>
</template>

<script setup>
import { computed, reactive } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
    CalendarIcon,
    WalletIcon,
    ArrowUpRightIcon,
    ArrowLeftOnRectangleIcon,
    ChevronDownIcon as ChevronIcon,
} from "@heroicons/vue/24/outline";

defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["close"]);

const route = useRoute();
const router = useRouter();

const currentLang = computed(() => {
    const lang = route.params.lang || localStorage.getItem("lang") || "en";
    return String(lang).toLowerCase();
});

const visitSitePath = computed(() => `/${currentLang.value}/home`);

const open = reactive({
    events: route.name === "creator-events" || route.name === "creator-event-show",
    wallet: route.name === "creator-wallet",
});

const toggle = (key) => {
    open[key] = !open[key];
};

const eventsActive = computed(() =>
    route.name === "creator-events" || route.name === "creator-event-show"
);
const walletActive = computed(() => route.name === "creator-wallet");

const logout = async () => {
    localStorage.removeItem("auth_token");
    localStorage.removeItem("user");
    localStorage.removeItem("user_data");
    localStorage.removeItem("user_role");

    emit("close");

    await router.push(`/${currentLang.value}/auth`);
};
</script>

<style scoped>
.creator-sidebar {
    width: 260px;
    min-height: 100vh;
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #ffffff;
    border-right: 1px solid #e5e7eb;
    transition: all 0.4s ease;
    position: sticky;
    top: 0;
    inset-inline-start: 0;
    z-index: 40;
}

[dir="rtl"] .creator-sidebar {
    border-right: 0;
    border-left: 1px solid #e5e7eb;
}

[data-theme="dark"] .creator-sidebar {
    background: linear-gradient(180deg, #0f172a 0%, #020617 100%);
    border-right-color: #334155;
}

[dir="rtl"][data-theme="dark"] .creator-sidebar {
    border-left-color: #334155;
}

/* Logo */
.sidebar-logo {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 16px;
}

.logo-circle {
    width: 88px;
    height: 88px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    color: #111827;
    border: 1px solid #e5e7eb;
    font-size: 2rem;
}

[data-theme="dark"] .logo-circle {
    background: rgba(212, 175, 55, 0.12);
    color: #d4af37;
    border-color: rgba(212, 175, 55, 0.35);
}

.logo-text {
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    text-align: center;
}

[data-theme="dark"] .logo-text {
    color: #f1f5f9;
}

.sidebar-spacer {
    height: 1px;
    width: 100%;
    background: #e5e7eb;
}

[data-theme="dark"] .sidebar-spacer {
    background: #334155;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Common button styles */
.sidebar-btn {
    width: 100%;
    text-align: left;
    padding: 12px 16px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.25s ease;
    color: #374151;
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
}

[dir="rtl"] .sidebar-btn {
    text-align: right;
}

.sidebar-btn:hover {
    background: #cacfd4;
    transform: translateX(8px);
}

[dir="rtl"] .sidebar-btn:hover {
    transform: translateX(-8px);
}

.sidebar-btn:focus-visible {
    outline: 2px solid #111827;
    outline-offset: 2px;
}

[data-theme="dark"] .sidebar-btn {
    color: #d1d5db;
}

[data-theme="dark"] .sidebar-btn:hover {
    background: rgba(212, 175, 55, 0.12);
}

/* Active / Current page */
.sidebar-btn.active,
.active-parent {
    background: #000000 !important;
    color: #ffffff !important;
    border-color: #000000;
}

[data-theme="dark"] .sidebar-btn.active,
[data-theme="dark"] .active-parent {
    background: #d4af37 !important;
    color: #0f172a !important;
    border-color: #d4af37;
}

/* Dropdown */
.sidebar-group {
    display: flex;
    flex-direction: column;
}

.dropdown {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-left: 12px;
    margin-top: 4px;
}

[dir="rtl"] .dropdown {
    padding-left: 0;
    padding-right: 12px;
}

.dropdown-item {
    justify-content: flex-start;
    gap: 12px;
    padding-left: 28px;
    font-weight: 500;
    font-size: 0.92rem;
    border-radius: 8px;
    color: #4b5563;
    border: 1px solid transparent;
}

[dir="rtl"] .dropdown-item {
    padding-left: 16px;
    padding-right: 28px;
}

[data-theme="dark"] .dropdown-item {
    color: #9ca3af;
}

.dropdown-item.active {
    background: #000000 !important;
    color: #ffffff !important;
    border-color: #000000;
}

[data-theme="dark"] .dropdown-item.active {
    background: #d4af37 !important;
    color: #0f172a !important;
    border-color: #d4af37;
}

/* Dropdown animation */
.dropdown-enter-active,
.dropdown-leave-active {
    transition: all 0.3s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
}

.dropdown-enter-to,
.dropdown-leave-from {
    opacity: 1;
    transform: translateY(0) scale(1);
}

/* Bottom buttons */
.bottom-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-top: 24px;
    margin-top: auto;
}

.visit-site-btn {
    justify-content: center;
    border-color: #e5e7eb;
    background: #f9fafb;
}

.visit-site-btn:hover {
    background: #111827;
    color: #ffffff;
    border-color: #111827;
}

[data-theme="dark"] .visit-site-btn {
    border-color: #334155;
    background: rgba(15, 23, 42, 0.85);
}

[data-theme="dark"] .visit-site-btn:hover {
    background: #d4af37;
    color: #0f172a;
    border-color: #d4af37;
}

.logout-btn {
    justify-content: center;
    color: #b91c1c;
    background: #fff1f2;
    border-color: #fecdd3;
}

.logout-btn:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}

[data-theme="dark"] .logout-btn {
    color: #fecdd3;
    background: rgba(127, 29, 29, 0.25);
    border-color: rgba(248, 113, 113, 0.3);
}

[data-theme="dark"] .logout-btn:hover {
    background: #ef4444;
    color: #ffffff;
    border-color: #ef4444;
}

/* Mobile behavior */
@media (max-width: 767px) {
    .creator-sidebar {
        position: fixed;
        inset-block: 0;
        inset-inline-start: 0;
        transform: translateX(-100%);
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);
    }

    [dir="rtl"] .creator-sidebar {
        transform: translateX(100%);
    }

    .creator-sidebar.is-open {
        transform: translateX(0);
    }
}
</style>
