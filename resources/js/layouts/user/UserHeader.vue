<template>
  <header class="user-header">
    <div class="header-left">
      <h1 class="page-title">{{ pageTitle }}</h1>
    </div>
    <div class="header-right">
      <div v-if="user" class="user-info">
        <span class="user-name">{{ user.name }}</span>
        <span class="user-role">{{ user.role }}</span>
      </div>

      <button class="create-btn" @click="$emit('create')">
        <span class="btn-icon">+</span>
        Create New Memory
      </button>
    </div>
  </header>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";

const props = defineProps({
  pageTitle: {
    type: String,
    default: "My Memories",
  },
});

const emit = defineEmits(["create"]);

const user = ref(null);

onMounted(async () => {
  try {
    const token = localStorage.getItem("auth_token");

    const response = await axios.get("/v1/users/profile", {
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
    });

    if (response.data.status === "success") {
      user.value = response.data.data.user;
    }
  } catch (err) {
    console.error("Failed to fetch user profile:", err);
  }
});
</script>

<style scoped>
.user-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 32px;
  background: #ffffff;
  border-bottom: 1px solid #e5e7eb;
  min-height: 72px;
  position: sticky;
  top: 0;
  z-index: 100;
}

[data-theme="dark"] .user-header {
  background: #0f172a;
  border-bottom: 1px solid #1e293b;
}

/* ── Left ── */
.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  margin-right: 10px;
}

.user-name {
  font-weight: 600;
  font-size: 14px;
  color: #111827;
}

.user-role {
  font-size: 12px;
  color: #6b7280;
  text-transform: capitalize;
}

[data-theme="dark"] .user-name {
  color: #f1f5f9;
}

[data-theme="dark"] .user-role {
  color: #94a3b8;
}

.page-title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
  letter-spacing: -0.3px;
}

[data-theme="dark"] .page-title {
  color: #f1f5f9;
}

/* ── Right ── */
.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

/* Create button */
.create-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #2563eb;
  color: #ffffff;
  border: none;
  border-radius: 10px;
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.15s ease;
  white-space: nowrap;
}

.create-btn:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
}

.create-btn:active {
  transform: translateY(0);
}

.btn-icon {
  font-size: 18px;
  line-height: 1;
  font-weight: 400;
}
</style>
