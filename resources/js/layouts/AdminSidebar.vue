<template>
    <aside class="admin-sidebar overflow-auto">
        <!-- Logo -->
        <div class="sidebar-logo">
            <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuBf6eTWBxo2vcWWGFr0Fjp8Ky36aj9RjGsHgrNcxEplG3TidGpQzteoJsqUJuC3fkmkAkhQvWsb3qGAmRa0v4gG2WDGL97IeE8W-HbtOl2Js0vb_-mybWEOy_C1CIa3kM_HkJ6qRwJ_6S2vsnpmjzB2klEFYPMv8mcWdKWMokcomgHxXvAJELZKcllv9Hu6J_nvzjeKjVe_6q1HwuBsi8stTZK-p4OTwBvPokuDXXHxLpneGJ9Cnoj3ABgtor56i6f3oCnr5-KLsNM"
                alt="Site Logo" class="w-24 h-24 rounded-full object-contain" />
            <span class="logo-text mt-2">
                <i class="fa-solid fa-camera me-2"></i>
                SceMory
            </span>
        </div>
        <div class="sidebar-spacer my-4"></div>

        <!-- Dashboard -->
        <RouterLink to="/admin" class="sidebar-btn group text-decoration-none"
            :class="{ active: route.path === '/admin' }">
            <span class="flex items-center gap-3">
                <HomeIcon class="w-5 h-5" />
                Dashboard
            </span>
        </RouterLink>

        <!-- Reports -->
        <RouterLink to="/admin/reports" class="sidebar-btn group text-decoration-none"
            :class="{ active: route.path === '/admin/reports' }">
            <span class="flex items-center gap-3">
                <PresentationChartBarIcon class="w-5 h-5" />
                Reports
            </span>
        </RouterLink>

        <!-- PLANS -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle group" :class="{ 'active-parent': plansActive }"
                @click="toggle('plans')">
                <span class="flex items-center gap-3">
                    <ClipboardDocumentListIcon class="w-5 h-5" />
                    Plans
                </span>
                <ChevronIcon class="w-5 h-5 transition-transform duration-300" :class="{ 'rotate-180': open.plans }" />
            </button>
            <Transition name="dropdown">
                <div v-if="open.plans" class="dropdown">
                    <RouterLink to="/admin/plans" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/plans' }">
                        <ClipboardDocumentListIcon class="w-5 h-5" />
                        All Plans
                    </RouterLink>
                    <RouterLink to="/admin/plans/create" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/plans/create' }">
                        <PlusIcon class="w-5 h-5" />
                        Create Plan
                    </RouterLink>
                </div>
            </Transition>
        </div>

        <!-- Purchases -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle group" :class="{ 'active-parent': purchasesActive }"
                @click="toggle('purchases')">
                <span class="flex items-center gap-3">
                    <CreditCardIcon class="w-5 h-5" />
                    Purchases
                </span>
                <ChevronIcon class="w-5 h-5 transition-transform duration-300" :class="{ 'rotate-180': open.purchases }" />
            </button>
            <Transition name="dropdown">
                <div v-if="open.purchases" class="dropdown">
                    <RouterLink to="/admin/purchases" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/purchases' }">
                        <CreditCardIcon class="w-5 h-5" />
                        All Purchases
                    </RouterLink>

                    <RouterLink to="/admin/purchases/withdrawls" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path.startsWith('/admin/purchases/withdrawls') }">
                        <ArrowPathIcon class="w-5 h-5" />
                        Withdrawls
                    </RouterLink>

                </div>
            </Transition>
        </div>

        <!-- Create Event Requests Dropdown -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle group" :class="{ 'active-parent': requestsActive }"
                @click="toggle('requests')">
                <span class="flex items-center gap-3">
                    <InboxIcon class="w-5 h-5" />
                    Events Requests
                </span>
                <ChevronIcon class="w-5 h-5 transition-transform duration-300"
                    :class="{ 'rotate-180': open.requests }" />
            </button>
            <Transition name="dropdown">
                <div v-if="open.requests" class="dropdown">
                    <RouterLink to="/admin/requests" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/requests' }">
                        <DocumentTextIcon class="w-5 h-5" />
                        Events Requests
                    </RouterLink>
                </div>
            </Transition>
        </div>

        <!-- USERS -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle group" :class="{ 'active-parent': usersActive }"
                @click="toggle('users')">
                <span class="flex items-center gap-3">
                    <UsersIcon class="w-5 h-5" />
                    Users
                </span>
                <ChevronIcon class="w-5 h-5 transition-transform duration-300" :class="{ 'rotate-180': open.users }" />
            </button>
            <Transition name="dropdown">
                <div v-if="open.users" class="dropdown">
                    <RouterLink to="/admin/users" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/users' }">
                        <UsersIcon class="w-5 h-5" />
                        All Users
                    </RouterLink>
                    <RouterLink to="/admin/users/add" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/users/add' }">
                        <UserPlusIcon class="w-5 h-5" />
                        Add User
                    </RouterLink>
                </div>
            </Transition>
        </div>

        <!-- CATEGORIES -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle group" :class="{ 'active-parent': categoriesActive }"
                @click="toggle('categories')">
                <span class="flex items-center gap-3">
                    <Squares2X2Icon class="w-5 h-5" />
                    Categories
                </span>
                <ChevronIcon class="w-5 h-5 transition-transform duration-300"
                    :class="{ 'rotate-180': open.categories }" />
            </button>
            <Transition name="dropdown">
                <div v-if="open.categories" class="dropdown">
                    <RouterLink to="/admin/categories" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/categories' }">
                        <Squares2X2Icon class="w-5 h-5" />
                        All Categories
                    </RouterLink>
                    <RouterLink to="/admin/categories/create" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/categories/create' }">
                        <PlusIcon class="w-5 h-5" />
                        Add Category
                    </RouterLink>
                </div>
            </Transition>
        </div>

        <!-- Countries -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle group" :class="{ 'active-parent': productsActive }"
                @click="toggle('products')">
                <span class="flex items-center gap-3">
                    <GlobeAltIcon class="w-5 h-5" />
                    Countries
                </span>
                <ChevronIcon class="w-5 h-5 transition-transform duration-300"
                    :class="{ 'rotate-180': open.products }" />
            </button>
            <Transition name="dropdown">
                <div v-if="open.products" class="dropdown">
                    <RouterLink to="/admin/countries" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/countries' }">
                        <GlobeAltIcon class="w-5 h-5" />
                        All Countries
                    </RouterLink>
                    <RouterLink to="/admin/countries/create" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/countries/create' }">
                        <PlusIcon class="w-5 h-5" />
                        Add Country
                    </RouterLink>
                </div>
            </Transition>
        </div>

        <!-- Cities -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle group" :class="{ 'active-parent': brandsActive }"
                @click="toggle('brands')">
                <span class="flex items-center gap-3">
                    <BuildingOfficeIcon class="w-5 h-5" />
                    Cities
                </span>
                <ChevronIcon class="w-5 h-5 transition-transform duration-300" :class="{ 'rotate-180': open.brands }" />
            </button>
            <Transition name="dropdown">
                <div v-if="open.brands" class="dropdown">
                    <RouterLink to="/admin/cities" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/cities' }">
                        <BuildingOfficeIcon class="w-5 h-5" />
                        All Cities
                    </RouterLink>
                    <RouterLink to="/admin/cities/create" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/cities/create' }">
                        <PlusIcon class="w-5 h-5" />
                        Add City
                    </RouterLink>
                </div>
            </Transition>
        </div>

        <!-- Events -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle group" :class="{ 'active-parent': eventsActive }"
                @click="toggle('events')">
                <span class="flex items-center gap-3">
                    <CalendarIcon class="w-5 h-5" />
                    Events
                </span>
                <ChevronIcon class="w-5 h-5 transition-transform duration-300" :class="{ 'rotate-180': open.events }" />
            </button>
            <Transition name="dropdown">
                <div v-if="open.events" class="dropdown">
                    <RouterLink to="/admin/events" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/events' }">
                        <CalendarIcon class="w-5 h-5" />
                        All Events
                    </RouterLink>
                    <RouterLink to="/admin/events/create" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/events/create' }">
                        <PlusIcon class="w-5 h-5" />
                        Add Event
                    </RouterLink>
                </div>
            </Transition>
        </div>

        <hr />

        <!-- SETTINGS -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle group" :class="{ 'active-parent': settingsActive }"
                @click="toggle('settings')">
                <span class="flex items-center gap-3">
                    <AdjustmentsHorizontalIcon class="w-5 h-5" />
                    Settings
                </span>
                <ChevronIcon class="w-5 h-5 transition-transform duration-300"
                    :class="{ 'rotate-180': open.settings }" />
            </button>
            <Transition name="dropdown">
                <div v-if="open.settings" class="dropdown">
                    <RouterLink to="/admin/contacts" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/contacts' }">
                        <PhoneIcon class="w-5 h-5" />
                        Contacts
                    </RouterLink>
                    <RouterLink to="/admin/footer" class="sidebar-btn dropdown-item"
                        :class="{ active: route.path === '/admin/footer' }">
                        <DocumentIcon class="w-5 h-5" />
                        Footer
                    </RouterLink>
                </div>
            </Transition>
        </div>

        <div class="flex-grow"></div>

        <!-- Visit Site Button -->
        <RouterLink to="/"
            class="sidebar-btn group item-center justify-center text-decoration-none mt-auto visit-site-btn">
            <span class="flex items-center gap-3 justify-center font-medium">
                <ArrowUpRightIcon class="w-5 h-5" />
                Visit Site
            </span>
        </RouterLink>
    </aside>
</template>

<script setup>
import { reactive, computed } from "vue";
import { useRoute } from "vue-router";
import {
    HomeIcon,
    PresentationChartBarIcon,
    InboxIcon,
    UsersIcon,
    Squares2X2Icon,
    ClipboardDocumentListIcon,
    GlobeAltIcon,
    BuildingOfficeIcon,
    CalendarIcon,
    AdjustmentsHorizontalIcon,
    UserPlusIcon,
    PlusIcon,
    DocumentTextIcon,
    PhoneIcon,
    DocumentIcon,
    ArrowUpRightIcon,
    ArrowPathIcon,
    CreditCardIcon,
} from "@heroicons/vue/24/outline";

const route = useRoute();

const open = reactive({
    users: route.path.startsWith("/admin/users"),
    categories: route.path.startsWith("/admin/categories"),
    products: route.path.startsWith("/admin/countries"),
    brands: route.path.startsWith("/admin/cities"),
    events: route.path.startsWith("/admin/events"),
    settings: route.path.startsWith("/admin/settings"),
    plans: route.path.startsWith("/admin/plans"),
    purchases: route.path.startsWith("/admin/purchases"),   // جديد
    requests: route.path.startsWith("/admin/requests") || route.path.startsWith("/admin/media"),
});

const toggle = (key) => {
    open[key] = !open[key];
};

// Computed Properties for Active States
const plansActive = computed(() => route.path.startsWith("/admin/plans"));
const purchasesActive = computed(() => route.path.startsWith("/admin/purchases")); // جديد
const requestsActive = computed(() =>
    route.path.startsWith("/admin/requests") || route.path.startsWith("/admin/media")
);
const usersActive = computed(() => route.path.startsWith("/admin/users"));
const categoriesActive = computed(() => route.path.startsWith("/admin/categories"));
const productsActive = computed(() => route.path.startsWith("/admin/countries"));
const brandsActive = computed(() => route.path.startsWith("/admin/cities"));
const eventsActive = computed(() => route.path.startsWith("/admin/events"));
const settingsActive = computed(() => route.path.startsWith("/admin/settings"));
</script>

<style scoped>
.admin-sidebar {
    width: 260px;
    min-height: 100vh;
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #ffffff;
    border-right: 1px solid #e5e7eb;
    transition: all 0.4s ease;
}

[data-theme="dark"] .admin-sidebar {
    background: linear-gradient(180deg, #0f172a 0%, #020617 100%);
    border-right-color: #334155;
}

/* Logo */
.sidebar-logo {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 16px;
}

.logo-text {
    font-size: 1.35rem;
    font-weight: 700;
    color: #111827;
}

[data-theme="dark"] .logo-text {
    color: #f1f5f9;
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
}

.sidebar-btn:hover {
    background: #cacfd4;
    transform: translateX(8px);
}

.group:hover .sidebar-btn {
    background: #f1f5f9;
    transform: translateX(4px);
}

[data-theme="dark"] .sidebar-btn {
    color: #d1d5db;
}

[data-theme="dark"] .group:hover .sidebar-btn {
    background: rgba(212, 175, 55, 0.12);
    transform: translateX(4px);
}

/* Active / Current page */
.sidebar-btn.active,
.active-parent {
    background: #000000 !important;
    color: white !important;
    border-color: #000000;
}

[data-theme="dark"] .sidebar-btn.active,
[data-theme="dark"] .active-parent {
    background: #d4af37 !important;
    color: #0f172a !important;
    border-color: #d4af37;
}

/* Dropdown items – مهم active */
.dropdown {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-left: 12px;
    margin-top: 4px;
}

.dropdown-item {
    padding-left: 28px;
    font-weight: 500;
    font-size: 0.92rem;
    border-radius: 8px;
    color: #4b5563;
    border: 1px solid transparent;
}

[data-theme="dark"] .dropdown-item {
    color: #9ca3af;
}

.dropdown-item.active {
    background: #000000 !important;
    color: white !important;
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
</style>
