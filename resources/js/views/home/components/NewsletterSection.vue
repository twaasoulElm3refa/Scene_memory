<template>
    <section class="scemory-newsletter-section home-newsletter-section py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div
                class="newsletter-panel relative overflow-hidden rounded-[36px] border p-8 md:p-12">
                <div class="relative grid grid-cols-1 gap-8 lg:grid-cols-[1fr_420px] lg:items-center">
                    <div>
                        <span
                            class="newsletter-eyebrow inline-flex rounded-full px-4 py-2 text-sm font-bold">
                            {{ $t('newsletter.kicker') }}
                        </span>

                        <h2 class="mt-5 text-3xl font-bold tracking-tight text-[#0F172A] md:text-5xl">
                            {{ $t('newsletter.verifiedTitle') }}
                        </h2>

                        <p class="mt-5 max-w-2xl text-base leading-8 text-[#475569]">
                            {{ $t('newsletter.verifiedDescription') }}
                        </p>
                    </div>

                    <form class="newsletter-form rounded-[26px] border p-4 backdrop-blur-xl"
                        @submit.prevent="submit">
                        <label class="sr-only" for="newsletter-email">{{ $t('newsletter.email') }}</label>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <input id="newsletter-email" v-model="email" type="email" :placeholder="$t('newsletter.emailPlaceholder')"
                                class="newsletter-input min-h-[52px] w-full rounded-full border px-5 text-sm outline-none transition sm:w-[200px]" />

                            <button
                                class="newsletter-button min-h-[52px] rounded px-7 text-sm font-bold text-white transition"
                                type="submit">
                                {{ $t('newsletter.subscribeShort') }}
                            </button>
                        </div>

                        <p v-if="error" class="mt-3 text-sm font-semibold text-red-600">
                            {{ error }}
                        </p>

                        <p v-if="success" class="mt-3 text-sm font-semibold text-[#0D4D97]">
                            {{ $t('newsletter.pendingApiMessage') }}
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref } from "vue";

const email = ref("");
const error = ref("");
const success = ref(false);

const submit = () => {
    error.value = "";
    success.value = false;

    const value = email.value.trim();

    if (!value) {
        error.value = "Please enter your email.";
        return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        error.value = "Please enter a valid email address.";
        return;
    }

    success.value = true;
    email.value = "";
};
</script>

<style scoped>
.home-newsletter-section {
    background: linear-gradient(180deg, var(--scemory-surface), var(--scemory-surface-soft));
}

.newsletter-panel {
    border-color: var(--scemory-border);
    background:
        radial-gradient(circle at 88% 18%, rgba(48, 168, 255, 0.10), transparent 24rem),
        linear-gradient(135deg, var(--scemory-surface-soft), var(--scemory-active));
    box-shadow: var(--scemory-shadow);
}

.newsletter-eyebrow {
    border: 1px solid var(--scemory-border);
    background: rgba(247, 250, 253, 0.78);
    color: var(--scemory-primary);
    box-shadow: var(--scemory-shadow-sm);
}

.home-newsletter-section h2 {
    color: var(--scemory-heading);
}

.home-newsletter-section p {
    color: var(--scemory-text);
}

.newsletter-form {
    border-color: var(--scemory-border);
    background: rgba(247, 250, 253, 0.82);
    box-shadow: var(--scemory-shadow);
}

.newsletter-input {
    border-color: var(--scemory-border);
    background: #FFFFFF;
    color: var(--scemory-heading);
}

.newsletter-input::placeholder {
    color: #94A3B8;
}

.newsletter-input:focus {
    border-color: var(--scemory-blue);
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.10);
}

.newsletter-button {
    border: 1px solid rgba(22, 119, 255, 0.22);
    border-radius: 14px;
    background: linear-gradient(135deg, var(--scemory-primary), var(--scemory-blue));
    box-shadow: 0 8px 20px rgba(13, 77, 151, 0.16);
}

.newsletter-button:hover {
    transform: translateY(-1px);
    background: linear-gradient(135deg, var(--scemory-blue), var(--scemory-light-blue));
    box-shadow: var(--scemory-shadow-hover);
}

@media (max-width: 640px) {
    .newsletter-panel,
    .newsletter-form {
        border-radius: 24px;
    }
}
</style>
