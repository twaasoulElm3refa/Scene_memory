<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center position-relative overflow-hidden">
        <div class="position-absolute w-100 h-100 waves"></div>

        <div class="auth-container position-relative z-2 d-flex flex-column flex-lg-row w-100 h-100">
            <!-- Left branding side -->
            <div class="d-none d-lg-flex col-lg-5 align-items-center justify-content-center p-5 position-relative">
                <div class="text-center">
                    <img src="/images/logo.png" alt="NEXTLEVEL Logo" class="height-auto logo-glow" />
                    <h1 class="display-5 fw-black mb-3">Scene Memory</h1>
                    <p class="lead fs-3 fw-medium opacity-90">Share your memories with the world</p>
                </div>
            </div>

            <!-- Form side -->
            <div class="col-12 col-lg-7 d-flex align-items-center justify-content-center p-4 p-md-5">
                <div class="glass-card rounded-4 shadow-glow p-4 p-md-5 w-100"
                    style="max-width: 480px; backdrop-filter: blur(16px)">

                    <!-- Tabs -->
                    <div class="position-relative mb-4" style="border-bottom: 1px solid rgba(255, 255, 255, 0.15)">
                        <ul class="nav nav-pills nav-fill">
                            <li class="nav-item">
                                <button class="nav-link px-0 fs-5 fw-semibold position-relative"
                                    :class="{ 'active-tab': tab === 'login' }" @click.prevent="switchToLogin">
                                    Login
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link px-0 fs-5 fw-semibold position-relative"
                                    :class="{ 'active-tab': tab === 'register' }" @click.prevent="switchToRegister">
                                    Register
                                </button>
                            </li>
                        </ul>
                        <div class="position-absolute rounded-pill underline"
                            :style="{ left: tab === 'login' ? '0%' : '50%', width: '50%' }">
                        </div>
                    </div>

                    <!-- Success Message -->
                    <transition name="fade">
                        <p v-if="successMessage" class="text-center text-success mb-4 fw-medium">
                            {{ successMessage }}
                        </p>
                    </transition>

                    <!-- Login Form -->
                    <form v-if="tab === 'login'" @submit.prevent="handleLogin" class="d-flex flex-column gap-4">
                        <input v-model="loginForm.email" type="email"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Email Address" required />

                        <div class="input-group input-group-lg">
                            <input v-model="loginForm.password" :type="showPassword ? 'text' : 'password'"
                                class="form-control glass-input rounded-3 py-3 px-4 fs-5 border-end-0"
                                placeholder="Password" required />
                            <span class="input-group-text glass-input rounded-3 border-start-0 pointer"
                                @click="showPassword = !showPassword">
                                <span class="fs-4">{{ showPassword ? "👁️" : "👁️‍🗨️" }}</span>
                            </span>
                        </div>

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5 mt-2"
                            :disabled="loading">
                            {{ loading ? "Loading..." : "Login" }}
                        </button>

                        <p class="text-end mb-0">
                            <a href="#" class="text-glow fw-medium fs-6" @click.prevent="tab = 'forgot'">
                                هل نسيت كلمة المرور؟
                            </a>
                        </p>

                        <p v-if="error" class="text-center text-danger mb-0">{{ error }}</p>

                        <button @click.prevent="handleGoogleLogin"
                            class="btn btn-google btn-lg d-flex align-items-center justify-content-center gap-2 mt-3">
                            <img src="/images/google_logo.png" alt="Google" style="width: 65px; height: 36px" />
                            Continue with Google
                        </button>
                    </form>

                    <!-- Register Form -->
                    <form v-else-if="tab === 'register' && registerStage === 'form'" @submit.prevent="handleRegister"
                        class="d-flex flex-column gap-4">
                        <input v-model="registerForm.name" type="text"
                            class="form-control form-control-lg glass-input rounded-3 px-4 fs-5" placeholder="Full Name"
                            required />

                        <input v-model="registerForm.email" type="email"
                            class="form-control form-control-lg glass-input rounded-3 px-4 fs-5"
                            placeholder="Email Address" required />

                        <input v-model="registerForm.position" type="text"
                            class="form-control form-control-lg glass-input rounded-3 px-4 fs-5"
                            placeholder="Position / Job Title (optional)" />

                        <div class="form-floating">
                            <input v-model="registerForm.date_of_birth" type="date" class="form-control glass-input"
                                id="date_of_birth" required />
                            <label for="date_of_birth">تاريخ الميلاد</label>
                        </div>

                        <div class="position-relative">
                            <input v-model="registerForm.country" list="country-list" type="text"
                                class="form-control form-control-lg glass-input rounded-3 px-4 fs-5"
                                placeholder="الدولة (اكتب للبحث...)" required autocomplete="off" />
                            <datalist id="country-list">
                                <option v-for="country in countries" :key="country.code" :value="country.name">
                                    {{ country.name }} • {{ country.en }}
                                </option>
                            </datalist>
                        </div>

                        <div class="input-group input-group-lg">
                            <input v-model="registerForm.password" :type="showPassword ? 'text' : 'password'"
                                class="form-control glass-input rounded-3 px-4 fs-5 border-end-0" placeholder="Password"
                                required />
                            <span class="input-group-text glass-input rounded-3 border-start-0 pointer"
                                @click="showPassword = !showPassword">
                                <span class="fs-4">{{ showPassword ? "👁️" : "👁️‍🗨️" }}</span>
                            </span>
                        </div>

                        <div class="input-group input-group-lg">
                            <input v-model="registerForm.password_confirmation"
                                :type="showPassword ? 'text' : 'password'"
                                class="form-control glass-input rounded-3 px-4 fs-5 border-end-0"
                                placeholder="Confirm Password" required />
                            <span class="input-group-text glass-input rounded-3 border-start-0 pointer"
                                @click="showPassword = !showPassword">
                                <span class="fs-4">{{ showPassword ? "👁️" : "👁️‍🗨️" }}</span>
                            </span>
                        </div>

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5 mt-3"
                            :disabled="loading">
                            {{ loading ? "Creating..." : "Create Account" }}
                        </button>

                        <p v-if="error" class="text-center text-danger mb-0">{{ error }}</p>

                        <button @click.prevent="handleGoogleLogin"
                            class="btn btn-google btn-lg d-flex align-items-center justify-content-center gap-2 mt-1">
                            <img src="/images/google_logo.png" alt="Google" style="width: 65px; height: 36px" />
                            Continue with Google
                        </button>
                    </form>

                    <!-- Register OTP Form -->
                    <form v-else-if="tab === 'register' && registerStage === 'otp'" @submit.prevent="handleVerifyOtp"
                        class="d-flex flex-column gap-4">
                        <div class="text-center text-white">
                            <h2 class="h4 fw-bold mb-2">Confirm your email</h2>
                            <p class="mb-0 opacity-75">
                                Enter the 6-digit code sent to {{ registerOtpMaskedEmail || pendingRegisterEmail }}
                            </p>
                        </div>

                        <input v-model="otpForm.otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                            pattern="[0-9]{6}" type="text"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5 text-center"
                            placeholder="000000" required @input="sanitizeOtp" @paste="handleOtpPaste" />

                        <p v-if="otpError || error" class="text-center text-danger mb-0">
                            {{ otpError || error }}
                        </p>

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5"
                            :disabled="loading || otpForm.otp.length !== 6">
                            {{ loading ? "Verifying..." : "Confirm" }}
                        </button>

                        <button type="button" class="btn btn-google btn-lg rounded-3 py-3 fw-semibold"
                            :disabled="resendLoading || resendRemaining > 0" @click="handleResendOtp">
                            <span v-if="resendRemaining > 0">Resend code in {{ resendRemaining }}s</span>
                            <span v-else>{{ resendLoading ? "Sending..." : "Resend OTP" }}</span>
                        </button>

                        <button type="button" class="btn btn-link text-glow fw-medium text-decoration-none"
                            @click="backToRegisterForm">
                            Change email
                        </button>
                    </form>

                    <!-- Forgot Password Form -->
                    <form v-else-if="tab === 'forgot'" @submit.prevent="handleForgot" class="d-flex flex-column gap-4">
                        <input v-model="forgotEmail" type="email"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Email Address" required />

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5">
                            إرسال الكود
                        </button>

                        <p class="text-center mt-3">
                            <a href="#" class="text-glow" @click.prevent="tab = 'login'">
                                رجوع لتسجيل الدخول
                            </a>
                        </p>
                    </form>

                    <!-- Reset Password Form -->
                    <form v-else-if="tab === 'reset'" @submit.prevent="handleReset" class="d-flex flex-column gap-4">
                        <input v-model="resetForm.email" type="email"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Email" required />

                        <input v-model="resetForm.otp" type="text"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="OTP Code" required />

                        <input v-model="resetForm.password" type="password"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="New Password" required />

                        <input v-model="resetForm.password_confirmation" type="password"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Confirm Password" required />

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5">
                            تغيير كلمة المرور
                        </button>
                    </form>

                    <!-- Switch between Login & Register -->
                    <div class="text-center mt-4 text-white fs-6">
                        <span v-if="tab === 'register'">
                            Already have an account?
                            <a href="#" class="text-glow fw-medium" @click.prevent="switchToLogin">Login</a>
                        </span>
                        <span v-else-if="tab === 'login'">
                            Don't have an account?
                            <a href="#" class="text-glow fw-medium" @click.prevent="switchToRegister">Register</a>
                        </span>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { AuthService } from "../../services/AuthService/AuthService";

const route = useRoute();
const router = useRouter();

const tab = ref("login");
const showPassword = ref(false);
const loading = ref(false);
const error = ref("");
const successMessage = ref("");
const forgotEmail = ref("");
const registerStage = ref("form");
const pendingRegisterEmail = ref("");
const registerOtpMaskedEmail = ref("");
const otpError = ref("");
const resendLoading = ref(false);
const resendRemaining = ref(0);
let resendTimer = null;

const loginForm = ref({ email: "", password: "" });
const otpForm = ref({ otp: "" });

const registerForm = ref({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    position: "",
    date_of_birth: "",
    country: ""
});

const resetForm = ref({
    email: "",
    otp: "",
    password: "",
    password_confirmation: ""
});

const getLang = () => route.params.lang || "en";

const countries = ref([
    { name: "أفغانستان", en: "Afghanistan", code: "AF" },
    { name: "مصر", en: "Egypt", code: "EG" },
    { name: "السعودية", en: "Saudi Arabia", code: "SA" },
    { name: "الإمارات العربية المتحدة", en: "United Arab Emirates", code: "AE" },
]);

// ✅ الدالة الوحيدة اللي بتخزن وبتعمل redirect — كل حاجة بتمر منها
const saveTokenAndRedirect = (token, role, user = null) => {
    if (!token) {
        error.value = "No token received";
        return;
    }

    const normalizedRole = (role || "user").toString().toLowerCase().trim();

    localStorage.setItem("auth_token", token);
    localStorage.setItem("user_role", normalizedRole);

    if (user) {
        localStorage.setItem("user_data", JSON.stringify(user));
    }

    const lang = getLang();

    if (normalizedRole === "admin") {
        router.push("/admin");
    } else {
        router.push(`/${lang}/home`);
    }
};

const startResendTimer = (seconds = 60) => {
    resendRemaining.value = seconds;
    if (resendTimer) {
        clearInterval(resendTimer);
    }

    resendTimer = setInterval(() => {
        resendRemaining.value = Math.max(0, resendRemaining.value - 1);
        if (resendRemaining.value === 0 && resendTimer) {
            clearInterval(resendTimer);
            resendTimer = null;
        }
    }, 1000);
};

const extractErrorMessage = (err, fallback) => {
    const errors = err.response?.data?.errors;
    if (errors) {
        const first = Object.values(errors)[0];
        if (Array.isArray(first) && first[0]) return first[0];
        if (typeof first === "string") return first;
    }

    return err.response?.data?.message || fallback;
};

const switchToLogin = () => {
    tab.value = "login";
    error.value = "";
};

const switchToRegister = () => {
    tab.value = "register";
    error.value = "";
};

const backToRegisterForm = () => {
    registerStage.value = "form";
    otpForm.value.otp = "";
    otpError.value = "";
    error.value = "";
};

onMounted(async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get("token");
    const errorMsg = urlParams.get("error");

    if (errorMsg) {
        error.value = decodeURIComponent(errorMsg);
        return;
    }

    if (token) {
        const url = new URL(window.location.href);
        url.search = "";
        window.history.replaceState({}, document.title, url.toString());
        localStorage.setItem("auth_token", token);
        try {
            const res = await AuthService.getProfile();
            const role = res.data.data?.role || res.data.data?.user?.role || "user";
            saveTokenAndRedirect(token, role);
        } catch {
            saveTokenAndRedirect(token, "user");
        }
    }
});

onUnmounted(() => {
    if (resendTimer) {
        clearInterval(resendTimer);
    }
});

const handleLogin = async () => {
    loading.value = true;
    error.value = "";

    try {
        const res = await AuthService.login(loginForm.value);

        if (res.data.status === "success") {
            const token = res.data.data?.token;
            const role = res.data.data?.user?.role;
            saveTokenAndRedirect(token, role);
        } else {
            error.value = res.data.message || "Login failed";
        }
    } catch (err) {
        error.value = err.response?.data?.message || "Error during login";
    } finally {
        loading.value = false;
    }
};

// ✅ Register
const handleRegister = async () => {
    loading.value = true;
    error.value = "";

    try {
        const formData = new FormData();
        Object.keys(registerForm.value).forEach(key => {
            const val = registerForm.value[key];
            if (val !== null && val !== undefined && val !== "") {
                formData.append(key, val);
            }
        });

        const res = await AuthService.register(formData);

        if (res.data.status === "otp_required") {
            pendingRegisterEmail.value = registerForm.value.email;
            registerOtpMaskedEmail.value = res.data.data?.email || "";
            registerStage.value = "otp";
            otpForm.value.otp = "";
            registerForm.value.password = "";
            registerForm.value.password_confirmation = "";
            successMessage.value = res.data.message || "Verification code sent successfully.";
            startResendTimer(60);
        } else {
            error.value = res.data.message || "Registration failed";
        }
    } catch (err) {
        error.value = extractErrorMessage(err, "Error during registration");
    } finally {
        loading.value = false;
    }
};

const sanitizeOtp = () => {
    otpForm.value.otp = String(otpForm.value.otp || "").replace(/\D/g, "").slice(0, 6);
    otpError.value = "";
};

const handleOtpPaste = (event) => {
    event.preventDefault();
    otpForm.value.otp = event.clipboardData.getData("text").replace(/\D/g, "").slice(0, 6);
    otpError.value = "";
};

const handleVerifyOtp = async () => {
    sanitizeOtp();

    if (otpForm.value.otp.length !== 6) {
        otpError.value = "Please enter the 6-digit verification code.";
        return;
    }

    loading.value = true;
    otpError.value = "";
    error.value = "";

    try {
        const res = await AuthService.verifyRegisterOtp({
            email: pendingRegisterEmail.value,
            otp: otpForm.value.otp,
        });

        if (res.data.status === "success") {
            const token = res.data.data?.token;
            const user = res.data.data?.user;
            const role = user?.role;
            pendingRegisterEmail.value = "";
            registerOtpMaskedEmail.value = "";
            otpForm.value.otp = "";
            successMessage.value = res.data.message || "Email verified successfully.";
            saveTokenAndRedirect(token, role, user);
        } else {
            otpError.value = res.data.message || "Verification failed";
        }
    } catch (err) {
        otpError.value = extractErrorMessage(err, "Verification failed");
    } finally {
        loading.value = false;
    }
};

const handleResendOtp = async () => {
    if (resendRemaining.value > 0 || !pendingRegisterEmail.value) return;

    resendLoading.value = true;
    otpError.value = "";
    error.value = "";

    try {
        const res = await AuthService.resendRegisterOtp({ email: pendingRegisterEmail.value });
        otpForm.value.otp = "";
        successMessage.value = res.data.message || "A new verification code has been sent.";
        startResendTimer(res.data.data?.resend_after || 60);
    } catch (err) {
        otpError.value = extractErrorMessage(err, "Unable to resend verification code");
    } finally {
        resendLoading.value = false;
    }
};

// Forgot Password
const handleForgot = async () => {
    error.value = "";
    try {
        await AuthService.forgotPassword({ email: forgotEmail.value });
        successMessage.value = "تم إرسال الكود على الإيميل";
        resetForm.value.email = forgotEmail.value;
        tab.value = "reset";
    } catch (err) {
        error.value = err.response?.data?.message || "حصل خطأ أثناء إرسال الكود";
    }
};

// Reset Password
const handleReset = async () => {
    error.value = "";
    try {
        await AuthService.resetPassword(resetForm.value);
        successMessage.value = "تم تغيير كلمة المرور بنجاح";
        tab.value = "login";
        resetForm.value = { email: "", otp: "", password: "", password_confirmation: "" };
    } catch (err) {
        error.value = err.response?.data?.message || "الكود غير صحيح أو حدث خطأ";
    }
};

const handleGoogleLogin = () => {
    const currentLang = String(getLang() || "en").toLowerCase();
    localStorage.setItem("lang", currentLang);
    localStorage.setItem("language", currentLang);
    document.cookie = `oauth_lang=${encodeURIComponent(currentLang)}; path=/; max-age=600`;
    window.location.href = `/api/v1/users/google-login?lang=${encodeURIComponent(currentLang)}`;
};
</script>

<style scoped>
.btn-apple {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: white;
    transition: all 0.4s ease;
    overflow: hidden;
    position: relative;
}

.btn-apple:hover {
    transform: scale(1.04);
    color: white;
}

/* Underline color appears on hover */
.btn-apple::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, #5c4033, #8b5e3c);
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: -1;
}

.btn-apple:hover::before {
    opacity: 1;
}

/* ─── Common ─── */
.waves {
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23D4AF37" fill-opacity="0.08" d="M0,96L48,112C96,128,192,160,288,176C384,192,480,192,576,186.7C672,181,768,171,864,154.7C960,139,1056,117,1152,122.7C1248,128,1344,160,1392,176L1440,192L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') bottom no-repeat;
    background-size: cover;
    animation: wave 18s linear infinite alternate;
    opacity: 0.6;
}

.glass-card {
    background: rgba(30, 41, 59, 0.35);
    border: 1px solid rgba(212, 175, 55, 0.18);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45), inset 0 0 20px rgba(212, 175, 55, 0.08);
    transition: all 0.4s ease;
}

.glass-input {
    background: rgba(30, 41, 59, 0.45);
    border: 1px solid rgba(212, 175, 55, 0.25);
    color: white;
    transition: all 0.3s;
}

.glass-input:focus {
    background: rgba(51, 65, 85, 0.55);
    border-color: #d4af37;
    box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
    outline: none;
}

.glass-input::placeholder {
    color: rgba(255, 255, 255, 0.55);
}

.btn-glow {
    background: linear-gradient(90deg, #d4af37, #eab308, #fbbf24);
    border: none;
    color: #0f172a;
    font-weight: 600;
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
    transition: all 0.35s;
}

.btn-glow:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(212, 175, 55, 0.55);
    filter: brightness(1.08);
}

/* ─── Tabs ─── */
.nav-link {
    position: relative;
    color: rgba(255, 255, 255, 0.75);
    transition: color 0.3s ease;
}

.nav-link:hover {
    color: white;
}

.nav-link::after {
    content: "";
    position: absolute;
    bottom: -4px;
    left: 50%;
    width: 0;
    height: 3px;
    background: linear-gradient(90deg, #d4af37, #eab308);
    border-radius: 3px;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateX(-50%);
}

.nav-link:hover::after,
.active-tab::after {
    width: 70%;
}

/* Active tab always has underline */
.active-tab {
    color: white !important;
}

/* ─── Google Button ─── */
.btn-google {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: white;
    transition: all 0.4s ease;
    overflow: hidden;
    position: relative;
}

.btn-google:hover {
    transform: scale(1.04);
    color: white;
}

/* Underline color appears on hover */
.btn-google::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, #d4af37, #eab308);
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: -1;
}

.btn-google:hover::before {
    opacity: 1;
}

/* ─── Dark Theme (default) ─── */
[data-theme="dark"] .glass-card {
    background: rgba(30, 41, 59, 0.35);
    border-color: rgba(212, 175, 55, 0.18);
}

[data-theme="dark"] .glass-input {
    background: rgba(30, 41, 59, 0.45);
    border-color: rgba(212, 175, 55, 0.25);
    color: white;
}

[data-theme="dark"] .glass-input::placeholder {
    color: rgba(255, 255, 255, 0.55);
}

[data-theme="dark"] .btn-glow {
    background: linear-gradient(90deg, #d4af37, #eab308, #fbbf24);
    color: #0f172a;
}

/* ─── Light Theme ─── */
[data-theme="light"] .auth-container,
[data-theme="light"] .auth-container * {
    color: #111827;
}

[data-theme="light"] .glass-card {
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    backdrop-filter: blur(12px);
}

[data-theme="light"] .glass-input {
    background: rgba(243, 244, 246, 0.9);
    border: 1px solid #d1d5db;
    color: #111827;
}

[data-theme="light"] .glass-input:focus {
    border-color: #111827;
    box-shadow: 0 0 0 0.25rem rgba(17, 24, 39, 0.15);
}

[data-theme="light"] .glass-input::placeholder {
    color: #6b7280;
}

[data-theme="light"] .btn-glow {
    background: #111827;
    color: white;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

[data-theme="light"] .btn-glow:hover {
    background: #1f2937;
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
}

/* Light theme → tabs: black underline */
[data-theme="light"] .nav-link::after,
[data-theme="light"] .active-tab::after {
    background: #111827 !important;
}

/* Light theme → Google button hover becomes black */
[data-theme="light"] .btn-google {
    background: rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.12);
    color: #111827;
}

[data-theme="light"] .btn-google:hover {
    color: white;
}

[data-theme="light"] .btn-google::before {
    background: #111827;
}

[data-theme="light"] .btn-apple {
    background: rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.12);
    color: #111827;
}

[data-theme="light"] .btn-apple:hover {
    color: white;
}

[data-theme="light"] .btn-apple::before {
    background: #111827;
}

/* Disable gold glow effects in light mode */
[data-theme="light"] .text-glow,
[data-theme="light"] .logo-glow,
[data-theme="light"] .active-tab {
    filter: none !important;
    background: none !important;
    -webkit-background-clip: unset !important;
    -webkit-text-fill-color: #111827 !important;
}

[data-theme="light"] .logo-glow {
    filter: none;
}

/* Waves in light mode → very subtle or hidden */
[data-theme="light"] .waves {
    opacity: 0.03;
    filter: brightness(0.4);
}

.btn-facebook {
    background: rgba(59, 89, 152, 0.08);
    border: 1px solid rgba(59, 89, 152, 0.15);
    color: white;
    transition: all 0.4s ease;
    overflow: hidden;
    position: relative;
}

.btn-facebook:hover {
    transform: scale(1.04);
    color: white;
}

.btn-facebook::before {
    content: "";
    position: absolute;
    inset: 0;
    background: #3b5998;
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: -1;
}

.btn-facebook:hover::before {
    opacity: 1;
}

.upload-avatar {
    cursor: pointer;
    display: inline-block;
}

.avatar-preview,
.avatar-placeholder {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(212, 175, 55, 0.5);
}

.avatar-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(212, 175, 55, 0.15);
    color: white;
    font-size: 13px;
    font-weight: 600;
}
</style>
