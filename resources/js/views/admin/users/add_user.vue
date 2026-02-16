<template>
  <AdminLayout>
    <div :class="['wrapper', theme]" :data-theme="theme">
      <h1 class="title">Create User</h1>

      <form @submit.prevent="submit">
        <!-- Name -->
        <div class="form-group">
          <label>Name</label>
          <input v-model="form.name" type="text" class="input" />
          <p v-if="fieldErrors.name" class="error">{{ fieldErrors.name }}</p>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label>Email</label>
          <input v-model="form.email" type="email" class="input" />
          <p v-if="fieldErrors.email" class="error">{{ fieldErrors.email }}</p>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label>Password</label>
          <input v-model="form.password" type="password" class="input" />
          <p v-if="fieldErrors.password" class="error">{{ fieldErrors.password }}</p>
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
          <label>Confirm Password</label>
          <input v-model="form.password_confirmation" type="password" class="input" />
          <p v-if="fieldErrors.password_confirmation" class="error">
            {{ fieldErrors.password_confirmation }}
          </p>
        </div>

        <!-- Phone -->
        <div class="form-group">
          <label>Phone</label>
          <input v-model="form.phone" type="text" class="input" />
          <p v-if="fieldErrors.phone" class="error">{{ fieldErrors.phone }}</p>
        </div>

        <!-- General Error -->
        <div v-if="generalErrors.length" class="alert error-bg">
          <div v-for="err in generalErrors" :key="err">{{ err }}</div>
        </div>

        <!-- Success -->
        <div v-if="successMessage" class="alert success-bg">
          {{ successMessage }}
        </div>

        <button type="submit" class="btn">Create User</button>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive, ref } from "vue";
import axios from "axios";
import AdminLayout from "@/layouts/AdminLayout.vue";

const theme = localStorage.getItem("theme") || "light";

const form = reactive({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  phone: "",
});

const fieldErrors = reactive({});
const generalErrors = ref([]);
const successMessage = ref("");

const submit = async () => {
  Object.keys(fieldErrors).forEach((k) => (fieldErrors[k] = ""));
  generalErrors.value = [];
  successMessage.value = "";

  if (form.password !== form.password_confirmation) {
    fieldErrors.password_confirmation = "Password confirmation does not match";
    return;
  }

  try {
    await axios.post("/v1/users/create", form);

    successMessage.value = "User created successfully!";
    Object.keys(form).forEach((k) => (form[k] = ""));
  } catch (error) {
    if (error.response?.status === 422) {
      const errs = error.response.data.errors;
      Object.keys(errs).forEach((key) => {
        fieldErrors[key] = errs[key][0];
      });
    } else {
      generalErrors.value.push("Something went wrong");
    }
  }
};
</script>

<style scoped>
/* ===== COMMON ===== */
.wrapper {
  max-width: 600px;
  margin: auto;
  padding: 30px;
  border-radius: 14px;
  transition: background 0.3s, color 0.3s, box-shadow 0.3s;
}

.title {
  font-size: 26px;
  font-weight: bold;
  margin-bottom: 20px;
}

.form-group {
  margin-bottom: 16px;
}

/* ===== INPUTS ===== */
.input {
  width: 100%;
  padding: 10px;
  border-radius: 8px;
  outline: none;
  transition: background 0.3s, color 0.3s, border 0.3s;
}

/* Light Theme ──────────────── */
[data-theme="light"] .input {
  background: #ffffff;
  color: #000000;
  border: 1px solid #d1d5db;
  /* رمادي فاتح */
}

[data-theme="light"] .input:focus {
  border-color: #3b82f6;
  /* أزرق لطيف */
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

/* Dark Theme ──────────────── */
[data-theme="dark"] .input {
  background: #111827;
  /* رمادي غامق مريح (مش أسود 100%) */
  color: #f3f4f6;
  /* أبيض فاتح */
  border: 1px solid #374151;
  /* حدود رمادي غامق */
}

[data-theme="dark"] .input:focus {
  border-color: #60a5fa;
  /* أزرق فاتح */
  background: #1f2937;
  box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
}

.btn {
  padding: 10px 20px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.3s, color 0.3s;
}

/* Light Theme Button */
[data-theme="light"] .btn {
  background: #000000;
  color: #ffffff;
  border: 1px solid #000;
}

[data-theme="light"] .btn:hover {
  background: #1f2937;
}

/* Dark Theme Button */
[data-theme="dark"] .btn {
  background: #374151;
  color: #ffffff;
  border: 1px solid #4b5563;
}

[data-theme="dark"] .btn:hover {
  background: #4b5563;
}

/* ===== ALERTS ===== */
.alert {
  padding: 12px;
  border-radius: 8px;
  margin-bottom: 10px;
}

.error {
  color: #ef4444;
  font-size: 13px;
}

/* Light Theme Alerts */
[data-theme="light"] .error-bg {
  background: #fee2e2;
  color: #991b1b;
}

[data-theme="light"] .success-bg {
  background: #dcfce7;
  color: #166534;
}

/* Dark Theme Alerts */
[data-theme="dark"] .error-bg {
  background: #7f1d1d;
  color: #fecaca;
}

[data-theme="dark"] .success-bg {
  background: #14532d;
  color: #bbf7d0;
}

/* ===== WRAPPER BACKGROUND ===== */
[data-theme="light"] .wrapper {
  background: #ffffff;
  color: #000000;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

[data-theme="dark"] .wrapper {
  background: #020617;
  color: #ffffff;
}
</style>
