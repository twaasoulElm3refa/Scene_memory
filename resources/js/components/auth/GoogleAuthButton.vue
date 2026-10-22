<template>
    <div class="google-auth" :dir="['ar', 'fa', 'ur'].includes(locale) ? 'rtl' : 'ltr'">
        <div class="google-divider"><span>{{ t('googleAuth.or') }}</span></div>
        <button type="button" class="google-button" :disabled="disabled || loading" :aria-busy="loading" @click="$emit('continue')">
            <svg v-if="!loading" width="20" height="20" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5Z" />
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65Z" />
                <path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 0 1 9.75 24c0-1.59.27-3.13.79-4.59l-7.98-6.19A23.9 23.9 0 0 0 0 24c0 3.88.93 7.55 2.56 10.78l7.97-6.19Z" />
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.73-6c-2.15 1.45-4.92 2.3-8.18 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.97 6.19C6.51 42.62 14.62 48 24 48Z" />
            </svg>
            <span v-else class="google-spinner" aria-hidden="true"></span>
            <span>{{ t(loading ? 'googleAuth.signingIn' : 'googleAuth.continue') }}</span>
        </button>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
defineProps({ loading: Boolean, disabled: Boolean });
defineEmits(['continue']);
const { t, locale } = useI18n();
</script>

<style scoped>
.google-auth { width: 100%; min-width: 0; }
.google-divider { display: flex; align-items: center; gap: 1rem; margin-block: 1.4rem; color: inherit; font-size: .85rem; opacity: .75; }
.google-divider::before, .google-divider::after { content: ''; flex: 1; height: 1px; background: currentColor; opacity: .3; }
.google-button { display: flex; align-items: center; justify-content: center; gap: .75rem; width: 100%; min-height: 48px; padding: 12px 16px; border: 1px solid #747775; border-radius: 8px; background: #fff; color: #1f1f1f; font-family: inherit; font-size: 1rem; font-weight: 500; cursor: pointer; transition: background .2s, box-shadow .2s; }
.google-button svg, .google-spinner { flex-shrink: 0; }
.google-button:hover:not(:disabled) { background: #f8fafd; box-shadow: 0 1px 3px #0003; }
.google-button:focus-visible { outline: 3px solid #4285f4; outline-offset: 3px; }
.google-button:disabled { opacity: .6; cursor: wait; }
.google-spinner { width: 20px; height: 20px; border: 2px solid #dadce0; border-top-color: #4285f4; border-radius: 50%; animation: google-spin .8s linear infinite; }
@keyframes google-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .google-spinner { animation: none; } }
</style>
