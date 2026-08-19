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

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const error = ref("");
const POST_AUTH_REDIRECT_KEY = "post_auth_redirect";

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
    const token = route.query.token ? String(route.query.token) : "";
    const role = route.query.role ? String(route.query.role).toLowerCase() : "user";
    const isProfileComplete = normalizeBoolean(route.query.is_profile_complete);
    const callbackError = route.query.error ? String(route.query.error) : "";

    if (callbackError) {
        error.value = callbackError;
        loading.value = false;
        return;
    }

    if (!token) {
        error.value = "Google sign in failed: missing access token.";
        loading.value = false;
        return;
    }

    localStorage.setItem("auth_token", token);
    localStorage.setItem("user_role", role || "user");
    localStorage.setItem("is_profile_complete", String(isProfileComplete));
    localStorage.setItem("is_profile_filled", String(isProfileComplete));
    localStorage.setItem("lang", currentLang.value);
    localStorage.setItem("language", currentLang.value);

    const userName = route.query.name ? String(route.query.name) : "";
    const userEmail = route.query.email ? String(route.query.email) : "";
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
