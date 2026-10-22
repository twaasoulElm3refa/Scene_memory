<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center p-3 bg-body-tertiary" :dir="rtl ? 'rtl' : 'ltr'">
        <div class="google-callback-card card rounded-4 shadow-sm p-4 w-100">
            <div v-if="loading" role="status" class="text-center">
                <span class="spinner-border text-primary mb-3" aria-hidden="true"></span>
                <p>{{ t('googleAuth.signingIn') }}</p>
            </div>
            <form v-else-if="profileRequired" @submit.prevent="exchange">
                <h1 class="h4 mb-3">{{ t('googleAuth.completeProfile') }}</h1>
                <p>{{ t('googleAuth.profileRequired') }}</p>
                <label for="google-country" class="form-label">{{ t('googleAuth.country') }}</label>
                <input id="google-country" v-model="profile.country" class="form-control mb-3" autocomplete="country-name" maxlength="255" required />
                <label for="google-birthday" class="form-label">{{ t('googleAuth.dateOfBirth') }}</label>
                <input id="google-birthday" v-model="profile.date_of_birth" type="date" class="form-control mb-3" autocomplete="bday" :max="today" required />
                <p v-if="error" role="alert" class="text-danger">{{ error }}</p>
                <button type="submit" class="btn btn-primary w-100">{{ t('googleAuth.finishSignup') }}</button>
            </form>
            <div v-else-if="error" class="text-center">
                <p role="alert" class="text-danger">{{ error }}</p>
                <button v-if="retryable" type="button" class="btn btn-primary mb-3" @click="exchange">{{ t('googleAuth.retry') }}</button>
            </div>
            <RouterLink v-if="!loading" :to="`/${currentLang}/auth`" class="text-center mt-3">{{ t('googleAuth.backToLogin') }}</RouterLink>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { AuthService } from '../../services/AuthService/AuthService';
import { isMobileRuntime } from '../../services/runtimeUrls';
import { googleErrorKey, safePostAuthRedirect } from '../../services/googleAuth';
import { clearGoogleOAuthVerifier, getGoogleOAuthVerifier, setAuthToken } from '../../services/authTokenStorage';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const loading = ref(true);
const error = ref('');
const retryable = ref(false);
const profileRequired = ref(false);
const profile = ref({ country: '', date_of_birth: '' });
const code = ref('');
const currentLang = computed(() => String(route.params.lang || 'en').toLowerCase());
const rtl = computed(() => ['ar', 'fa', 'ur'].includes(currentLang.value));
const now = new Date();
const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

const exchange = async () => {
    loading.value = true;
    error.value = '';
    retryable.value = false;
    try {
        const verifier = isMobileRuntime() ? await getGoogleOAuthVerifier() : '';
        if (isMobileRuntime() && !verifier) throw new Error('invalid_state');
        const response = isMobileRuntime()
            ? await AuthService.exchangeMobileGoogleCode(code.value, verifier, profile.value)
            : await AuthService.exchangeGoogleCode(code.value, profile.value);
        if (response.data.status === 'profile_required') {
            profileRequired.value = true;
            return;
        }
        const { token, role, user, is_profile_complete: complete } = response.data;
        if (!token || !user) throw new Error('callback_failed');
        await setAuthToken(token);
        await clearGoogleOAuthVerifier();
        localStorage.setItem('user_role', role);
        localStorage.setItem('user_data', JSON.stringify(user));
        localStorage.setItem('is_profile_complete', String(Boolean(complete)));
        localStorage.setItem('is_profile_filled', String(Boolean(complete)));
        localStorage.setItem('lang', currentLang.value);
        localStorage.setItem('language', currentLang.value);
        window.dispatchEvent(new Event('login'));
        const destination = role === 'admin' ? '/admin'
            : safePostAuthRedirect(sessionStorage.getItem('post_auth_redirect'), currentLang.value);
        sessionStorage.removeItem('post_auth_redirect');
        await router.replace(destination);
    } catch (err) {
        const status = err.response?.status;
        const errorCode = err.response?.data?.error_code;
        retryable.value = !err.response || status >= 500 || status === 429 || errorCode === 'busy';
        error.value = t(profileRequired.value && status === 422 && !errorCode
            ? 'googleAuth.profileInvalid' : googleErrorKey(errorCode || err.message));
        if (!retryable.value && errorCode) {
            profileRequired.value = false;
            await clearGoogleOAuthVerifier();
        }
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    code.value = typeof route.query.code === 'string' ? route.query.code : '';
    const callbackError = typeof route.query.error === 'string' ? route.query.error : '';
    sessionStorage.removeItem('mobile_google_oauth_pending');
    // Remove the short-lived delivery code (and any legacy or hostile parameters)
    // from history before any API call or navigation to a third-party resource.
    await router.replace({ name: 'google-callback', params: { lang: currentLang.value } });
    if (callbackError || !/^[A-Za-z0-9]{64}$/.test(code.value)) {
        error.value = t(googleErrorKey(callbackError || 'invalid_state'));
        loading.value = false;
        await clearGoogleOAuthVerifier();
        return;
    }
    await exchange();
});
</script>

<style scoped>
.google-callback-card { max-width: 480px; }
</style>
