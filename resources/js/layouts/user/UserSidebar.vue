<template>
  <aside class="user-sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
      <div class="brand-icon">
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <rect
            x="3"
            y="3"
            width="8"
            height="8"
            rx="2"
            fill="currentColor"
            opacity="0.9"
          />
          <rect
            x="13"
            y="3"
            width="8"
            height="8"
            rx="2"
            fill="currentColor"
            opacity="0.6"
          />
          <rect
            x="3"
            y="13"
            width="8"
            height="8"
            rx="2"
            fill="currentColor"
            opacity="0.6"
          />
          <rect
            x="13"
            y="13"
            width="8"
            height="8"
            rx="2"
            fill="currentColor"
            opacity="0.3"
          />
        </svg>
      </div>
      <span class="brand-name">Scene Memory</span>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
      <router-link
        to="/dashboard/memories"
        class="nav-item"
        active-class="nav-item--active"
      >
        <svg
          class="nav-icon"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <path
            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
          />
        </svg>
        My Memories
      </router-link>

      <router-link to="/dashboard/add" class="nav-item" active-class="nav-item--active">
        <svg
          class="nav-icon"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="16" />
          <line x1="8" y1="12" x2="16" y2="12" />
        </svg>
        Add New Memory
      </router-link>

      <router-link
        to="/dashboard/settings"
        class="nav-item"
        active-class="nav-item--active"
      >
        <svg
          class="nav-icon"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <circle cx="12" cy="12" r="3" />
          <path
            d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"
          />
        </svg>
        Settings
      </router-link>
    </nav>

    <!-- User profile at bottom -->
    <div class="sidebar-footer">
      <div class="user-profile">
        <div class="user-avatar">
          <img
            v-if="user"
            :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(
              user.name
            )}&background=2563eb&color=fff&size=40`"
            :alt="user?.name"
          />
          <div v-else class="avatar-placeholder"></div>
        </div>
        <div class="user-info">
          <span class="user-name">{{ user?.name ?? "..." }}</span>
          <span class="user-role">{{ formatRole(user?.role) }}</span>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { ref, onMounted } from "vue";

const user = ref(null);

const formatRole = (role) => {
  if (!role) return "";
  // e.g. "admin" → "PRO EXPLORER" style badge or just uppercase
  const map = {
    admin: "PRO EXPLORER",
    user: "EXPLORER",
    moderator: "MODERATOR",
  };
  return map[role] ?? role.toUpperCase();
};

onMounted(async () => {
  try {
    const token = localStorage.getItem("auth_token");
    const response = await fetch("/v1/users", {
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
    });
    const data = await response.json();
    if (data.status === "success") {
      user.value = data.data.user;
    }
  } catch (err) {
    console.error("Failed to fetch user profile:", err);
  }
});
</script>

<style scoped>
.user-sidebar {
  width: 240px;
  min-width: 240px;
  height: 100vh;
  background: #ffffff;
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 0;
  overflow: hidden;
}

[data-theme="dark"] .user-sidebar {
  background: #0f172a;
  border-right: 1px solid #1e293b;
}

/* ── Brand ── */
.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 24px 20px 20px;
}

.brand-icon {
  width: 36px;
  height: 36px;
  background: #2563eb;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  flex-shrink: 0;
}

.brand-name {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
  letter-spacing: -0.2px;
}

[data-theme="dark"] .brand-name {
  color: #f1f5f9;
}

/* ── Nav ── */
.sidebar-nav {
  flex: 1;
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
  text-decoration: none;
  transition: background 0.15s ease, color 0.15s ease;
  cursor: pointer;
}

.nav-item:hover {
  background: #f3f4f6;
  color: #111827;
}

[data-theme="dark"] .nav-item:hover {
  background: #1e293b;
  color: #e2e8f0;
}

.nav-item--active {
  background: #eff6ff !important;
  color: #2563eb !important;
  font-weight: 600;
}

[data-theme="dark"] .nav-item--active {
  background: #1e3a5f !important;
  color: #60a5fa !important;
}

.nav-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

/* ── Footer ── */
.sidebar-footer {
  padding: 16px 16px 20px;
  border-top: 1px solid #e5e7eb;
}

[data-theme="dark"] .sidebar-footer {
  border-top: 1px solid #1e293b;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  background: #e5e7eb;
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-placeholder {
  width: 100%;
  height: 100%;
  background: #d1d5db;
  border-radius: 50%;
}

.user-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow: hidden;
}

.user-name {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

[data-theme="dark"] .user-name {
  color: #f1f5f9;
}

.user-role {
  font-size: 10px;
  font-weight: 600;
  color: #6b7280;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

[data-theme="dark"] .user-role {
  color: #64748b;
}
</style>
