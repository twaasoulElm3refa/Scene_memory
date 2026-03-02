<template>
  <header class="admin-header z-3">
    <!-- LEFT -->
    <div class="left">
      <span class="user-name">{{ userName }}</span>
    </div>

    <!-- RIGHT -->
    <div class="actions">
      <span class="role">{{ role }}</span>

      <!-- <button class="btn-icon" @click="toggleTheme" title="Change Theme">
                <i class="bi" :class="theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill'"></i>
            </button> -->

      <button class="logout-btn" @click="logout">Logout</button>
    </div>
  </header>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();
const theme = ref("dark");
const userName = ref("");
const role = ref("");

/* ===== Theme ===== */
const applyTheme = () => {
  document.documentElement.setAttribute("data-theme", theme.value);
};

const toggleTheme = () => {
  theme.value = theme.value === "dark" ? "light" : "dark";
  localStorage.setItem("theme", theme.value);
  applyTheme();
};

/* ===== Fetch Profile ===== */

const fetchProfile = async () => {
  try {
    const token = localStorage.getItem("auth_token");

    const res = await fetch("/api/v1/users/profile", {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    });

    const data = await res.json();

    if (data?.data?.user?.name) {
      userName.value = data.data.user.name;
      role.value = data.data.user.role;
    }
  } catch (err) {
    console.error("Profile error:", err);
  }
};

onMounted(() => {
  const savedTheme = localStorage.getItem("theme");
  if (savedTheme) theme.value = savedTheme;

  applyTheme();
  fetchProfile();
});

/* ===== Logout ===== */

const logout = () => {
  localStorage.removeItem("auth_token");
  localStorage.removeItem("user_role");
  router.push("/auth");
};
</script>

<style scoped>
.user-name {
  font-weight: bold;
  margin-right: 10px;
  font-size: 0.95rem;
}

[data-theme="dark"] .user-name {
  color: #fbbf24;
}

[data-theme="light"] .user-name {
  color: #111827;
}

.admin-header {
  height: 60px;
  padding: 0 20px;
  display: flex;
  justify-content: space-between;
  /* المهم */
  align-items: center;
}

/* left user name */
.left {
  display: flex;
  align-items: center;
}

/* right actions */
.actions {
  display: flex;
  align-items: center;
  gap: 14px;
}

/* role */
.role {
  font-weight: bold;
  opacity: 0.85;
}

/* dark */
[data-theme="dark"] .role {
  color: #fbbf24;
}

/* light */
[data-theme="light"] .role {
  color: #111827;
}

/* Title */
.title {
  font-size: 1.2rem;
  font-weight: bold;
}

/* Theme toggle button */
.btn-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  cursor: pointer;
  font-size: 1.2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.35s ease;
}

/* Logout */
.logout-btn {
  padding: 8px 14px;
  border-radius: 8px;
  border: none;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.35s ease;
}

/* ================= DARK MODE ================= */
[data-theme="dark"] .admin-header {
  background: rgba(15, 23, 42, 0.95);
  color: #fbbf24;
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.45);
}

[data-theme="dark"] .btn-icon {
  background: rgba(212, 175, 55, 0.25);
  color: #fbbf24;
}

[data-theme="dark"] .btn-icon:hover {
  background: rgba(212, 175, 55, 0.45);
  transform: rotate(15deg) scale(1.1);
  box-shadow: 0 0 18px rgba(251, 191, 36, 0.55);
}

[data-theme="dark"] .logout-btn {
  background: #ef4444;
  color: white;
}

[data-theme="dark"] .logout-btn:hover {
  background: #dc2626;
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(239, 68, 68, 0.45);
}

/* ================= LIGHT MODE ================= */
[data-theme="light"] .admin-header {
  background: #ffffff;
  color: #111827;
  border-bottom: 1px solid #e5e7eb;
}

[data-theme="light"] .btn-icon {
  background: #111827;
  color: white;
}

[data-theme="light"] .btn-icon:hover {
  background: #1f2937;
  transform: rotate(-15deg) scale(1.1);
}

[data-theme="light"] .logout-btn {
  background: #111827;
  color: white;
}

[data-theme="light"] .logout-btn:hover {
  background: #000000;
  transform: translateY(-2px);
}
</style>
