<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center p-3 bg-body-tertiary" :dir="rtl ? 'rtl' : 'ltr'">
        <div class="facebook-callback-card card rounded-4 shadow-sm p-4 w-100">
            <div v-if="loading" role="status" class="text-center">
                <span class="spinner-border text-primary mb-3" aria-hidden="true"></span>
                <p>{{ t('facebookAuth.signingIn') }}</p>
            </div>
            <div v-else-if="error" class="text-center">
                <p role="alert" class="text-danger">{{ error }}</p>
                <button v-if="retryable" type="button" class="btn btn-primary mb-3" @click="exchange()">{{ t('facebookAuth.retry') }}</button>
            </div>
            <RouterLink v-if="!loading" :to="`/${currentLang}/auth`" class="text-center mt-3">{{ t('facebookAuth.backToLogin') }}</RouterLink>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { FacebookAuthService } from '../../services/AuthService/FacebookAuthService';
import { facebookErrorKey, safeFacebookRedirect } from '../../services/facebookAuth';
import { setAuthToken } from '../../services/authTokenStorage';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const currentLang = computed(() => String(route.params.lang || 'en').toLowerCase());
const rtl = computed(() => ['ar', 'fa', 'ur'].includes(currentLang.value));
const loading = ref(true);
const retryable = ref(false);
const error = ref('');
const code = ref('');
let exchanging = false;

const exchange = async () => {
    if (exchanging) return;
    exchanging = true;
    loading.value = true;
    error.value = '';
    retryable.value = false;
    try {
        const response = await FacebookAuthService.exchange(code.value);
        const data = response.data;
        if (data.status !== 'success' || !data.token || !data.user) throw new Error('callback_failed');
        await setAuthToken(data.token);
        localStorage.setItem('user_role', data.role);
        localStorage.setItem('user_data', JSON.stringify(data.user));
        localStorage.setItem('is_profile_complete', String(Boolean(data.is_profile_complete)));
        localStorage.setItem('is_profile_filled', String(Boolean(data.is_profile_complete)));
        localStorage.setItem('lang', currentLang.value);
        localStorage.setItem('language', currentLang.value);
        window.dispatchEvent(new Event('login'));
        const destination = data.role === 'admin' ? '/admin'
            : safeFacebookRedirect(sessionStorage.getItem('post_auth_redirect'), currentLang.value);
        sessionStorage.removeItem('post_auth_redirect');
        await router.replace(destination);
    } catch (err) {
        const status = err.response?.status;
        const errorCode = err.response?.data?.error_code;
        retryable.value = !err.response || status >= 500 || status === 429 || errorCode === 'busy';
        error.value = t(facebookErrorKey(errorCode || err.message));
        if (['linking_required', 'disabled', 'verification_required', 'invalid_state', 'expired'].includes(errorCode)) {
            retryable.value = false;
        }
    } finally {
        exchanging = false;
        loading.value = false;
    }
};

onMounted(async () => {
    code.value = typeof route.query.code === 'string' ? route.query.code : '';
    const callbackError = typeof route.query.error === 'string' ? route.query.error : '';
    await router.replace({ name: 'facebook-callback', params: { lang: currentLang.value } });
    if (callbackError || !/^[A-Za-z0-9]{64}$/.test(code.value)) {
        error.value = t(facebookErrorKey(callbackError || 'invalid_state'));
        loading.value = false;
        return;
    }
    await exchange();
});
</script>

<style scoped>
.facebook-callback-card { max-width: 480px; }
</style>
