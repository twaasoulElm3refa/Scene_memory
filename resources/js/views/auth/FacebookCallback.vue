<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center p-3 bg-body-tertiary" :dir="rtl ? 'rtl' : 'ltr'">
        <div class="facebook-callback-card card rounded-4 shadow-sm p-4 w-100">
            <div v-if="loading" role="status" class="text-center">
                <span class="spinner-border text-primary mb-3" aria-hidden="true"></span>
                <p>{{ t('facebookAuth.signingIn') }}</p>
            </div>
            <form v-else-if="stage === 'profile'" @submit.prevent="exchange()">
                <h1 class="h4 mb-3">{{ t('facebookAuth.completeProfile') }}</h1>
                <p>{{ t('facebookAuth.profileRequired') }}</p>
                <label for="facebook-email" class="form-label">{{ t('facebookAuth.email') }}</label>
                <input id="facebook-email" v-model="profile.email" type="email" class="form-control mb-3" autocomplete="email" maxlength="255" required />
                <label for="facebook-country" class="form-label">{{ t('facebookAuth.country') }}</label>
                <input id="facebook-country" v-model="profile.country" class="form-control mb-3" autocomplete="country-name" maxlength="255" required />
                <label for="facebook-birthday" class="form-label">{{ t('facebookAuth.dateOfBirth') }}</label>
                <input id="facebook-birthday" v-model="profile.date_of_birth" type="date" class="form-control mb-3" autocomplete="bday" :max="today" required />
                <p v-if="error" role="alert" class="text-danger">{{ error }}</p>
                <button type="submit" class="btn btn-primary w-100">{{ t('facebookAuth.sendCode') }}</button>
            </form>
            <form v-else-if="stage === 'otp'" @submit.prevent="exchange({ otp })">
                <h1 class="h4 mb-3">{{ t('facebookAuth.verifyEmail') }}</h1>
                <p>{{ t('facebookAuth.codeSent', { email: maskedEmail }) }}</p>
                <label for="facebook-otp" class="form-label">{{ t('facebookAuth.verificationCode') }}</label>
                <input id="facebook-otp" v-model="otp" class="form-control mb-3 text-center" type="text" inputmode="numeric"
                    autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required />
                <p v-if="error" role="alert" class="text-danger">{{ error }}</p>
                <button type="submit" class="btn btn-primary w-100">{{ t('facebookAuth.finishSignup') }}</button>
                <button type="button" class="btn btn-link w-100 mt-2" :disabled="resendRemaining > 0" @click="exchange({ resend: true })">
                    {{ resendRemaining > 0 ? t('facebookAuth.resendIn', { seconds: resendRemaining }) : t('facebookAuth.resend') }}
                </button>
            </form>
            <div v-else-if="error" class="text-center">
                <p role="alert" class="text-danger">{{ error }}</p>
                <button v-if="retryable" type="button" class="btn btn-primary mb-3" @click="exchange()">{{ t('facebookAuth.retry') }}</button>
            </div>
            <RouterLink v-if="!loading" :to="`/${currentLang}/auth`" class="text-center mt-3">{{ t('facebookAuth.backToLogin') }}</RouterLink>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
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
const stage = ref('');
const retryable = ref(false);
const error = ref('');
const code = ref('');
const profile = ref({ email: '', country: '', date_of_birth: '' });
const otp = ref('');
const maskedEmail = ref('');
const resendRemaining = ref(0);
let resendDeadline = 0;
const timer = setInterval(() => { resendRemaining.value = Math.max(0, Math.ceil((resendDeadline - Date.now()) / 1000)); }, 1000);
onUnmounted(() => clearInterval(timer));
const now = new Date();
const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

const exchange = async (extra = {}) => {
    if (loading.value && stage.value) return;
    loading.value = true;
    error.value = '';
    retryable.value = false;
    try {
        const response = await FacebookAuthService.exchange(code.value, { ...profile.value, ...extra });
        const data = response.data;
        if (data.status === 'profile_required') {
            stage.value = 'profile';
            if (!profile.value.email) profile.value.email = data.email || '';
            return;
        }
        if (data.status === 'otp_required') {
            stage.value = 'otp';
            otp.value = '';
            maskedEmail.value = data.email;
            resendRemaining.value = data.resend_after || 0;
            resendDeadline = Date.now() + resendRemaining.value * 1000;
            return;
        }
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
        error.value = t(status === 422 && !errorCode ? 'facebookAuth.profileInvalid' : facebookErrorKey(errorCode || err.message));
        if (['linking_required', 'disabled', 'verification_required', 'invalid_state', 'expired'].includes(errorCode)) {
            stage.value = '';
            retryable.value = false;
        }
    } finally {
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
