<template>
  <aside class="admin-sidebar overflow-auto">
    <!-- Logo -->
    <div class="sidebar-logo">
      <img
        src="https://lh3.googleusercontent.com/aida-public/AB6AXuBf6eTWBxo2vcWWGFr0Fjp8Ky36aj9RjGsHgrNcxEplG3TidGpQzteoJsqUJuC3fkmkAkhQvWsb3qGAmRa0v4gG2WDGL97IeE8W-HbtOl2Js0vb_-mybWEOy_C1CIa3kM_HkJ6qRwJ_6S2vsnpmjzB2klEFYPMv8mcWdKWMokcomgHxXvAJELZKcllv9Hu6J_nvzjeKjVe_6q1HwuBsi8stTZK-p4OTwBvPokuDXXHxLpneGJ9Cnoj3ABgtor56i6f3oCnr5-KLsNM"
        alt="Site Logo"
        class="w-24 h-24 rounded-full object-contain"
      />
      <span class="logo-text mt-2">
        <i class="fa-solid fa-camera me-2"></i>
        Scene Memory
      </span>
    </div>

    <div class="sidebar-spacer my-4"></div>

    <!-- Dashboard -->
    <RouterLink
      to="/admin"
      class="sidebar-btn group text-decoration-none"
      :class="{ active: route.path === '/admin' }"
    >
      <span class="flex items-center gap-3">
        <DashboardIcon class="w-5 h-5" />
        Dashboard
      </span>
    </RouterLink>

    <!-- Create Event Requests -->
    <RouterLink
      to="/admin/requests"
      class="sidebar-btn group text-decoration-none"
      :class="{ active: route.path === '/admin/requests' }"
    >
      <span class="flex items-center gap-3">
        <MessagesIcon class="w-5 h-5" />
        Create Event Requests
      </span>
    </RouterLink>

    <!-- USERS -->
    <div class="sidebar-group">
      <button
        class="sidebar-btn dropdown-toggle group"
        :class="{ 'active-parent': usersActive }"
        @click="toggle('users')"
      >
        <span class="flex items-center gap-3">
          <UsersIcon class="w-5 h-5" />
          Users
        </span>
        <ChevronIcon
          class="w-5 h-5 transition-transform duration-300"
          :class="{ 'rotate-180': open.users }"
        />
      </button>

      <Transition name="dropdown">
        <div v-if="open.users" class="dropdown">
          <RouterLink
            to="/admin/users"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/users' }"
          >
            All Users
          </RouterLink>
          <RouterLink
            to="/admin/users/add"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/users/add' }"
          >
            Add User
          </RouterLink>
        </div>
      </Transition>
    </div>

    <!-- CATEGORIES -->
    <div class="sidebar-group">
      <button
        class="sidebar-btn dropdown-toggle group"
        :class="{ 'active-parent': categoriesActive }"
        @click="toggle('categories')"
      >
        <span class="flex items-center gap-3">
          <CategoriesIcon class="w-5 h-5" />
          Categories
        </span>
        <ChevronIcon
          class="w-5 h-5 transition-transform duration-300"
          :class="{ 'rotate-180': open.categories }"
        />
      </button>

      <Transition name="dropdown">
        <div v-if="open.categories" class="dropdown">
          <RouterLink
            to="/admin/categories"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/categories' }"
          >
            All Categories
          </RouterLink>
          <RouterLink
            to="/admin/categories/create"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/categories/create' }"
          >
            Add Category
          </RouterLink>
        </div>
      </Transition>
    </div>

    <!-- Countries -->
    <div class="sidebar-group">
      <button
        class="sidebar-btn dropdown-toggle group"
        :class="{ 'active-parent': productsActive }"
        @click="toggle('products')"
      >
        <span class="flex items-center gap-3">
          <GlobeIcon class="w-5 h-5" />
          Countries
        </span>
        <ChevronIcon
          class="w-5 h-5 transition-transform duration-300"
          :class="{ 'rotate-180': open.products }"
        />
      </button>

      <Transition name="dropdown">
        <div v-if="open.products" class="dropdown">
          <RouterLink
            to="/admin/countries"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/countries' }"
          >
            All Countries
          </RouterLink>
          <RouterLink
            to="/admin/countries/create"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/countries/create' }"
          >
            Add Country
          </RouterLink>
        </div>
      </Transition>
    </div>

    <!-- Cities -->
    <div class="sidebar-group">
      <button
        class="sidebar-btn dropdown-toggle group"
        :class="{ 'active-parent': brandsActive }"
        @click="toggle('brands')"
      >
        <span class="flex items-center gap-3">
          <BuildingOfficeIcon class="w-5 h-5" />
          Cities
        </span>
        <ChevronIcon
          class="w-5 h-5 transition-transform duration-300"
          :class="{ 'rotate-180': open.brands }"
        />
      </button>

      <Transition name="dropdown">
        <div v-if="open.brands" class="dropdown">
          <RouterLink
            to="/admin/cities"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/cities' }"
          >
            All Cities
          </RouterLink>
          <RouterLink
            to="/admin/cities/create"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/cities/create' }"
          >
            Add City
          </RouterLink>
        </div>
      </Transition>
    </div>

    <!-- Events -->
    <div class="sidebar-group">
      <button
        class="sidebar-btn dropdown-toggle group"
        :class="{ 'active-parent': eventsActive }"
        @click="toggle('events')"
      >
        <span class="flex items-center gap-3">
          <CalendarIcon class="w-5 h-5" />
          Events
        </span>
        <ChevronIcon
          class="w-5 h-5 transition-transform duration-300"
          :class="{ 'rotate-180': open.events }"
        />
      </button>

      <Transition name="dropdown">
        <div v-if="open.events" class="dropdown">
          <RouterLink
            to="/admin/events"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/events' }"
          >
            All Events
          </RouterLink>
          <RouterLink
            to="/admin/events/create"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/events/create' }"
          >
            Add Event
          </RouterLink>
        </div>
      </Transition>
    </div>

    <hr />

    <!-- SETTINGS -->
    <div class="sidebar-group">
      <button
        class="sidebar-btn dropdown-toggle group"
        :class="{ 'active-parent': settingsActive }"
        @click="toggle('settings')"
      >
        <span class="flex items-center gap-3">
          <AdjustmentsHorizontalIcon class="w-5 h-5" />
          Settings
        </span>
        <ChevronIcon
          class="w-5 h-5 transition-transform duration-300"
          :class="{ 'rotate-180': open.settings }"
        />
      </button>

      <Transition name="dropdown">
        <div v-if="open.settings" class="dropdown">
          <RouterLink
            to="/admin/contacts"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/contacts' }"
          >
            Contacts
          </RouterLink>
          <RouterLink
            to="/admin/newsletters"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/newsletters' }"
          >
            Newsletters
          </RouterLink>
          <RouterLink
            to="/admin/footer"
            class="sidebar-btn dropdown-item"
            :class="{ active: route.path === '/admin/footer' }"
          >
            Footer
          </RouterLink>
        </div>
      </Transition>
    </div>

    <div class="flex-grow"></div>
  </aside>
</template>

<script setup>
import { reactive, computed } from "vue";
import { useRoute } from "vue-router";

import {
  HomeIcon as DashboardIcon,
  InboxIcon as MessagesIcon,
  UsersIcon,
  GlobeAltIcon as GlobeIcon,
  BuildingOfficeIcon,
  Cog6ToothIcon as CogIcon,
  Squares2X2Icon as CategoriesIcon,
  CalendarIcon,
  AdjustmentsHorizontalIcon,
} from "@heroicons/vue/24/outline";

const route = useRoute();

const open = reactive({
  users: route.path.startsWith("/admin/users"),
  categories: route.path.startsWith("/admin/categories"),
  products: route.path.startsWith("/admin/countries"),
  brands: route.path.startsWith("/admin/cities"),
  events: route.path.startsWith("/admin/events"),
  settings: route.path.startsWith("/admin/settings"),
});

const toggle = (key) => {
  open[key] = !open[key];
};

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
