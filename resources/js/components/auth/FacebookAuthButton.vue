<template>
    <div class="facebook-auth mt-3" :dir="['ar', 'fa', 'ur'].includes(locale) ? 'rtl' : 'ltr'">
        <button type="button" class="facebook-button" :disabled="disabled || loading" :aria-busy="loading" @click="$emit('continue')">
            <span v-if="loading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <svg class="text-white" v-else width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M24 12a12 12 0 1 0-13.875 11.854v-8.385H7.078V12h3.047V9.356c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953h-1.513c-1.491 0-1.956.925-1.956 1.874V12h3.328l-.532 3.469h-2.796v8.385A12 12 0 0 0 24 12Z" />
            </svg>
            <span class="text-white">{{ t(loading ? 'facebookAuth.signingIn' : 'facebookAuth.continue') }}</span>
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
.facebook-auth { width: 100%; min-width: 0; }
.facebook-button { display: flex; align-items: center; justify-content: center; gap: .75rem; width: 100%; min-height: 48px; padding: 12px 16px; border: 1px solid #0866ff; border-radius: 8px; background: #0866ff; color: #FFFFFF !important; font-family: inherit; font-size: 1rem; font-weight: 600; cursor: pointer; }
.facebook-button svg { color: #FFFFFF; fill: #FFFFFF; }
.facebook-button svg, .facebook-button .spinner-border { flex-shrink: 0; }
.facebook-button:hover:not(:disabled) { background: #0757d8; }
.facebook-button:hover:not(:disabled), .facebook-button:focus, .facebook-button:active:not(:disabled) { color: #FFFFFF !important; }
.facebook-button:focus-visible { outline: 3px solid #0866ff; outline-offset: 3px; }
.facebook-button:disabled { opacity: .6; cursor: wait; }
</style>
