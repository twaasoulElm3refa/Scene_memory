<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
            <div v-if="loading" class="flex flex-col items-center gap-3">
                <svg
                    class="h-10 w-10 animate-spin text-sky-600"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v8z" />
                </svg>
                <p class="text-base font-medium text-slate-700">Completing Google sign in...</p>
            </div>

            <div v-else-if="error" class="space-y-3">
                <p class="font-semibold text-rose-600">{{ error }}</p>
                <RouterLink
                    :to="`/${currentLang}/auth`"
                    class="inline-flex rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700"
                >
                    Back to Sign In
                </RouterLink>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { AuthService } from "@/services/AuthService/AuthService";
import {
    clearGoogleOAuthVerifier,
    getGoogleOAuthVerifier,
    setAuthToken,
} from "@/services/authTokenStorage";

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const error = ref("");
const POST_AUTH_REDIRECT_KEY = "post_auth_redirect";
const MOBILE_GOOGLE_PENDING_KEY = "mobile_google_oauth_pending";

const mobileErrorMessages = {
    cancelled: "Google sign in was cancelled.",
    expired_state: "Google sign in expired. Please try again.",
    invalid_state: "This Google sign in request is invalid or was already used.",
    oauth_failed: "Google could not complete sign in. Please try again.",
    callback_failed: "Google sign in failed. Please try again.",
};

const currentLang = computed(() => String(route.params.lang || localStorage.getItem("lang") || "en").toLowerCase());

const normalizeBoolean = (value) => {
    if (typeof value === "boolean") return value;
    const normalized = String(value || "").toLowerCase();
    return normalized === "true" || normalized === "1" || normalized === "yes";
};

const getSafePostAuthRedirect = () => {
    const requestedRedirect = sessionStorage.getItem(POST_AUTH_REDIRECT_KEY) || "";

    if (requestedRedirect.startsWith(`/${currentLang.value}/`) && !requestedRedirect.startsWith("//")) {
        return requestedRedirect;
    }

    return `/${currentLang.value}/home`;
};

const processCallback = async () => {
    let token = route.query.token ? String(route.query.token) : "";
    let role = route.query.role ? String(route.query.role).toLowerCase() : "user";
    let isProfileComplete = normalizeBoolean(route.query.is_profile_complete);
    const callbackError = route.query.error ? String(route.query.error) : "";
    const deliveryCode = route.query.code ? String(route.query.code) : "";
    let user = null;

    sessionStorage.removeItem(MOBILE_GOOGLE_PENDING_KEY);

    if (token || deliveryCode || callbackError || route.query.role || route.query.is_profile_complete) {
        await router.replace({
            name: "google-callback",
            params: { lang: currentLang.value },
        });
    }

    if (callbackError) {
        await clearGoogleOAuthVerifier();
        error.value = mobileErrorMessages[callbackError] || callbackError;
        loading.value = false;
        return;
    }

    if (!token && deliveryCode) {
        try {
            const codeVerifier = await getGoogleOAuthVerifier();
            if (!codeVerifier) {
                throw new Error("Missing Google sign in verifier.");
            }

            const response = await AuthService.exchangeMobileGoogleCode(deliveryCode, codeVerifier);
            token = response.data.token;
            role = String(response.data.role || "user").toLowerCase();
            isProfileComplete = normalizeBoolean(response.data.is_profile_complete);
            user = response.data.user || null;
        } catch (exchangeError) {
            error.value = exchangeError.response?.data?.message || "Google sign in failed.";
            loading.value = false;
            return;
        } finally {
            await clearGoogleOAuthVerifier();
        }
    }

    if (!token) {
        error.value = "Google sign in failed: missing access token.";
        loading.value = false;
        return;
    }

    try {
        await setAuthToken(token);
    } catch {
        error.value = "Could not securely save your sign in. Please try again.";
        loading.value = false;
        return;
    }

    localStorage.setItem("user_role", role || "user");
    localStorage.setItem("is_profile_complete", String(isProfileComplete));
    localStorage.setItem("is_profile_filled", String(isProfileComplete));
    localStorage.setItem("lang", currentLang.value);
    localStorage.setItem("language", currentLang.value);

    const userName = user?.name || (route.query.name ? String(route.query.name) : "");
    const userEmail = user?.email || (route.query.email ? String(route.query.email) : "");
    if (userName || userEmail) {
        localStorage.setItem(
            "user_data",
            JSON.stringify({
                name: userName,
                email: userEmail,
            })
        );
    }

    window.dispatchEvent(new Event("login"));

    if (role === "admin") {
        sessionStorage.removeItem(POST_AUTH_REDIRECT_KEY);
        await router.replace("/admin");
        return;
    }

    const destination = getSafePostAuthRedirect();
    sessionStorage.removeItem(POST_AUTH_REDIRECT_KEY);
    await router.replace(destination);
};

onMounted(processCallback);
</script>
