<template>
    <section class="bg-white py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div
                class="relative overflow-hidden rounded-[36px] border border-[#CFE0FF] bg-gradient-to-br from-[#EAF2FF] via-white to-[#F8FAFC] p-8 shadow-[0_24px_70px_rgba(13,77,151,0.12)] md:p-12">
                <div class="relative grid grid-cols-1 gap-8 lg:grid-cols-[1fr_420px] lg:items-center">
                    <div>
                        <span
                            class="inline-flex rounded-full bg-white px-4 py-2 text-sm font-bold text-[#0D4D97] shadow-sm">
                            Newsletter
                        </span>

                        <h2 class="mt-5 text-3xl font-bold tracking-tight text-[#0F172A] md:text-5xl">
                            Stay updated with verified event stories
                        </h2>

                        <p class="mt-5 max-w-2xl text-base leading-8 text-[#475569]">
                            Get curated updates about trending events, new media archives, and major stories documented
                            by contributors around the world.
                        </p>
                    </div>

                    <form class="rounded-[26px] border border-[#CFE0FF] bg-white/80 p-4 shadow-xl backdrop-blur-xl"
                        @submit.prevent="submit">
                        <label class="sr-only" for="newsletter-email">Email</label>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <input id="newsletter-email" v-model="email" type="email" placeholder="Enter your email"
                                class="min-h-[52px] w-full rounded-full border border-[#CFE0FF] bg-white px-5 text-sm outline-none transition focus:border-[#0D4D97] focus:ring-4 focus:ring-[#0D4D97]/10 sm:w-[200px]" />

                            <button
                                class="min-h-[52px] rounded bg-[#0D4D97] px-7 text-sm font-bold text-white shadow-lg transition hover:bg-[#1E5FB8]"
                                type="submit">
                                Subscribe
                            </button>
                        </div>

                        <p v-if="error" class="mt-3 text-sm font-semibold text-red-600">
                            {{ error }}
                        </p>

                        <p v-if="success" class="mt-3 text-sm font-semibold text-[#0D4D97]">
                            Thanks for subscribing. Newsletter API will be connected soon.
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
