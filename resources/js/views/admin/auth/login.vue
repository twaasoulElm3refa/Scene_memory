<template>
    <section class="admin-login-page">
        <div class="login-card">
            <h1 class="login-title">Admin Login</h1>
            <p class="login-subtitle">Sign in to access the admin dashboard.</p>

            <form class="login-form" @submit.prevent="handleLogin">
                <div class="form-group">
                    <label for="admin-email">Email</label>
                    <input
                        id="admin-email"
                        v-model.trim="email"
                        type="email"
                        autocomplete="email"
                        placeholder="admin@example.com"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="admin-password">Password</label>
                    <input
                        id="admin-password"
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        required
                    />
                </div>

                <p v-if="errorMessage" class="message error-message">
                    {{ errorMessage }}
                </p>
                <p v-if="successMessage" class="message success-message">
                    {{ successMessage }}
                </p>

                <button type="submit" :disabled="loading">
                    {{ loading ? "Logging in..." : "Login" }}
                </button>
            </form>
        </div>
    </section>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { adminLogin } from "../../../services/admin/auth/authServices";

const router = useRouter();

const email = ref("");
const password = ref("");
const loading = ref(false);
const errorMessage = ref("");
const successMessage = ref("");

const handleLogin = async () => {
    if (loading.value) return;

    loading.value = true;
    errorMessage.value = "";
    successMessage.value = "";

    try {
        const data = await adminLogin({
            email: email.value,
            password: password.value,
        });

        const token = data?.data?.token;
        const user = data?.data?.user;

        if (!token || !user) {
            throw new Error("Invalid login response");
        }

        localStorage.setItem("admin_token", token);
        localStorage.setItem("admin_user", JSON.stringify(user));
        localStorage.setItem("auth_token", token);
        localStorage.setItem("user_role", user.role || "admin");

        successMessage.value = data?.message || "Login successful.";
        await router.push("/admin");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Login failed. Please try again.";
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.admin-login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: linear-gradient(135deg, #f4f6fb 0%, #e9eef8 100%);
}

.login-card {
    width: 100%;
    max-width: 420px;
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 16px 40px rgba(20, 28, 45, 0.12);
    padding: 28px;
}

.login-title {
    margin: 0;
    font-size: 1.5rem;
    color: #1f2a44;
}

.login-subtitle {
    margin: 8px 0 22px;
    color: #5b6785;
    font-size: 0.95rem;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-group label {
    font-size: 0.9rem;
    color: #2a3553;
    font-weight: 600;
}

.form-group input {
    border: 1px solid #ccd5e3;
    border-radius: 10px;
    padding: 11px 13px;
    font-size: 0.95rem;
    color: #1f2a44;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-group input:focus {
    border-color: #2d5bdb;
    box-shadow: 0 0 0 3px rgba(45, 91, 219, 0.12);
}

button[type="submit"] {
    border: none;
    border-radius: 10px;
    padding: 12px 14px;
    background: #2d5bdb;
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease;
}

button[type="submit"]:hover:not(:disabled) {
    background: #244ab3;
}

button[type="submit"]:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.message {
    margin: 0;
    font-size: 0.9rem;
}

.error-message {
    color: #c62828;
}

.success-message {
    color: #1f8f47;
}
</style>
