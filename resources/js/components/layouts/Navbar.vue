<template>
  <header class="navbar-wrap shadow-lg" dir="rtl">
    <div class="container d-flex justify-content-between align-items-center p-1">
      <div class="user-avatar-wrapper">
        <img
          v-if="userImage"
          :src="userImage"
          class="user-avatar"
          alt="User"
          @error="userImage = null"
        />

        <!-- Placeholder -->
        <div v-else class="user-placeholder">
          {{ userInitial }}
        </div>
      </div>

      <!-- Links -->
      <nav class="d-none d-md-flex flex-grow-1 justify-content-center">
        <a
          v-for="link in links"
          :key="link.href"
          :href="link.href"
          class="nav-link px-2"
          :class="{ active: isActive(link.active) }"
        >
          {{ link.label }}
        </a>
      </nav>

      <!-- Hamburger for Mobile -->
      <button class="btn-icon d-md-none" @click="mobileMenu = !mobileMenu">
        <i class="bi" :class="mobileMenu ? 'bi-x-lg' : 'bi-list'"></i>
      </button>

      <!-- Actions -->
      <div class="d-flex align-items-center gap-2">
        <div class="position-relative">
          <button
            class="btn-user user-hover fw-bold shadow-gray d-flex align-items-center gap-1"
            @click="languageDropdownOpen = !languageDropdownOpen"
          >
            {{ currentLanguage }}
            <i
              class="bi"
              :class="languageDropdownOpen ? 'bi-chevron-up' : 'bi-chevron-down'"
            ></i>
          </button>

          <div
            v-if="languageDropdownOpen"
            class="dropdown-menu show shadow rounded mt-2"
            style="
              position: absolute;
              right: 0;
              top: 100%;
              z-index: 1000;
              min-width: 120px;
            "
          >
            <button
              class="dropdown-item"
              v-for="lang in languages"
              :key="lang"
              @click="selectLanguage(lang)"
            >
              {{ lang }}
            </button>
          </div>
        </div>

        <!-- Auth Area -->
        <div class="d-flex gap-2 position-relative">
          <template v-if="isLoggedIn">
            <button
              class="btn-user user-hover fw-bold d-flex align-items-center gap-2 shadow-gray"
              @click="toggleDropdown"
            >
              {{ userName }}
              <i
                class="bi"
                :class="dropdownOpen ? 'bi-chevron-up' : 'bi-chevron-down'"
              ></i>
            </button>

            <div
              v-if="dropdownOpen"
              class="dropdown-menu show shadow rounded mt-2 dropdown-dark"
              style="
                position: absolute;
                right: 0;
                top: 100%;
                margin-top: 8px;
                z-index: 1000;
                min-width: 150px;
              "
            >
              <a class="dropdown-item" href="/profile">الملف الشخصي</a>
              <hr class="dropdown-divider" />
              <button class="dropdown-item text-danger" @click="logout">
                تسجيل الخروج
              </button>
            </div>
          </template>

          <template v-else>
            <a
              href="/auth"
              class="btn-user user-hover fw-bold shadow-gray text-decoration-none"
            >
              تسجيل الدخول
            </a>
          </template>
        </div>
      </div>
    </div>

    <!-- Mobile Menu -->
    <div
      v-if="mobileMenu"
      class="d-md-none mt-2 bg-white dark:bg-dark shadow-lg rounded p-3"
    >
      <a
        v-for="link in links"
        :key="link.href"
        :href="link.href"
        class="d-block nav-link py-2 px-1"
        :class="{ active: isActive(link.active) }"
      >
        {{ link.label }}
      </a>
    </div>
  </header>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "axios";

const theme = ref("dark");
const isLoggedIn = ref(false);
const userName = ref("المستخدم");
const dropdownOpen = ref(false);
const mobileMenu = ref(false);
const userImage = ref(null);
const currentLanguage = ref("AR");
const languageDropdownOpen = ref(false);

const languages = ["AR", "EN"];

const selectLanguage = (lang) => {
  currentLanguage.value = lang;
  localStorage.setItem("language", lang.toLowerCase());
  languageDropdownOpen.value = false;

  window.location.reload();
};

const links = [
  { label: "الرئيسية", href: "/", active: "home" },
  { label: "من نحن", href: "/who", active: "who" },
  { label: "جميع الاحداث", href: "/all_events", active: "all_events" },
  { label: "تواصل معنا", href: "/contact", active: "contact" },
];
const userInitial = computed(() => {
  if (!userName.value) return "UU";
  const nameParts = userName.value.split(" ");
  let initials = nameParts[0].charAt(0).toUpperCase();
  if (nameParts.length > 1) {
    initials += nameParts[1].charAt(0).toUpperCase();
  } else if (nameParts[0].length > 1) {
    initials += nameParts[0].charAt(1).toUpperCase();
  }
  return initials;
});

const toggleDropdown = () => (dropdownOpen.value = !dropdownOpen.value);

const logout = () => {
  localStorage.removeItem("auth_token");
  localStorage.removeItem("user_role");
  axios.defaults.headers.common["Authorization"] = "";
  isLoggedIn.value = false;
  userName.value = "المستخدم";
  dropdownOpen.value = false;
  window.location.href = "/";
};

const fetchProfile = async () => {
  const token = localStorage.getItem("auth_token");
  if (!token) return;

  axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;
  try {
    const res = await axios.get("/v1/users/profile");
    if (res.data.status === "success") {
      userName.value = res.data.data.user.name || "المستخدم";
      userImage.value = res.data.data.user.image || null;
      localStorage.setItem("user_role", res.data.data.user.role);
      isLoggedIn.value = true;
    }
  } catch (err) {
    console.log("Failed to fetch profile:", err);
    localStorage.removeItem("auth_token");
    isLoggedIn.value = false;
  }
};

onMounted(() => {
  const savedLang = localStorage.getItem("language");
  if (savedLang && ["ar", "en"].includes(savedLang.toLowerCase())) {
    currentLanguage.value = savedLang.toUpperCase();
  } else {
    currentLanguage.value = "AR";
    localStorage.setItem("language", "ar");
  }

  const savedTheme = localStorage.getItem("theme") || "dark";
  theme.value = savedTheme;
  applyTheme();

  fetchProfile();

  window.addEventListener("login", () => {
    fetchProfile();
  });
});

const applyTheme = () => {
  document.documentElement.setAttribute("data-theme", theme.value);
  document.documentElement.setAttribute("data-bs-theme", theme.value);
};

const isActive = (name) => document.body.dataset.route === name;
</script>

<style scoped>
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
  position: relative;
  padding-bottom: 4px;
  transition: color 0.3s ease;
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
[data-theme="light"] .cart-btn:hover {
  background: #000000;
  /* أسود */
  color: #000000;
  /* أيقونة أبيض */
  border-color: #000000;
  transform: scale(1.08);
}

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
  /* رمادي فاتح جدًا */
  color: #111827;
  border-color: #9ca3af;
  transform: scale(1.08);
}

/* Cart Badge – في الـ light mode */
[data-theme="light"] .badge {
  background: #111827;
  /* أسود */
  color: #ffffff;
  /* أبيض */
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
  /* shadow أسود خفيف */
}
</style>
