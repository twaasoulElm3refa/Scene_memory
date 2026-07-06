<template>
    <section
        class="rounded-[22px] border border-[#CFE0FF] bg-white/90 p-4 shadow-[0_14px_34px_rgba(13,77,151,0.09)] backdrop-blur-xl">
        <div class="mb-3">
            <span class="inline-flex rounded-full bg-[#EAF2FF] px-2.5 py-0.5 text-[10px] font-bold text-[#0D4D97]">
                Trending
            </span>

            <h2 class="mt-2 text-lg font-bold text-[#0F172A]">
                Trending Events
            </h2>

            <p class="mt-1 text-xs leading-5 text-[#475569]">
                Events gaining attention across the archive.
            </p>
        </div>

        <div v-if="loading" class="space-y-2">
            <article v-for="item in 4" :key="`trending-skeleton-${item}`"
                class="flex h-[118px] animate-pulse gap-3 overflow-hidden rounded-xl border border-[#CFE0FF]/70 bg-[#F8FAFC] p-2.5">
                <div class="h-16 w-16 shrink-0 rounded-xl bg-[#EAF2FF]"></div>

                <div class="min-w-0 flex-1 space-y-2">
                    <div class="h-3 w-20 rounded bg-[#EAF2FF]"></div>
                    <div class="h-3 w-full rounded bg-[#EAF2FF]"></div>
                    <div class="h-3 w-4/5 rounded bg-[#EAF2FF]"></div>
                    <div class="h-3 w-24 rounded bg-[#EAF2FF]"></div>
                </div>
            </article>
        </div>

        <div v-else-if="error"
            class="rounded-xl border border-red-100 bg-red-50 px-3 py-4 text-xs font-semibold leading-5 text-red-600">
            {{ error }}
        </div>

        <div v-else-if="events.length === 0"
            class="rounded-xl border border-dashed border-[#CFE0FF] bg-[#F8FAFC] px-3 py-5 text-center">
            <p class="text-sm font-bold text-[#0F172A]">No trending events yet</p>
            <p class="mt-1 text-xs leading-5 text-[#64748B]">Check back soon for highlighted events.</p>
        </div>

        <div v-else class="space-y-2">
            <article v-for="event in events" :key="event.slug || event.id" role="button" tabindex="0"
                class="group flex h-[118px] cursor-pointer gap-3 overflow-hidden rounded-xl border border-[#CFE0FF]/70 bg-[#F8FAFC] p-2.5 transition hover:-translate-y-0.5 hover:bg-white hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[#0D4D97]/30"
                @click="goToEvent(event)" @keydown.enter.prevent="goToEvent(event)"
                @keydown.space.prevent="goToEvent(event)">
                <img :src="event.image_url || fallbackImage" :alt="event.title || 'Trending event'"
                    class="h-16 w-16 shrink-0 rounded-xl bg-[#EAF2FF] object-cover" loading="lazy" decoding="async" />

                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-[10px] font-bold text-[#0D4D97]">
                            {{ formatDate(event.start_date) }}
                        </span>

                        <span v-if="event.user_name" class="truncate text-[10px] font-semibold text-[#94A3B8]">
                            {{ event.user_name }}
                        </span>
                    </div>

                    <h6 class="mt-1 text-xs font-bold leading-4 text-[#0F172A] transition group-hover:text-[#0D4D97]">
                        {{ truncateText(event.title || "Untitled event", 15) }}
                    </h6>

                    <p v-if="event.description" class=" text-[11px] leading-4 text-[#64748B]">
                        {{ truncateText(event.description, 20) }}
                    </p>
                    <div class=" flex items-center gap-3 text-[10px] font-semibold text-[#64748B]">
                        <span>{{ formatCount(event.views_count) }} views</span>
                        <span>{{ formatCount(event.likes_count) }} likes</span>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
import { useRouter } from "vue-router";

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: "",
    },
    fallbackImage: {
        type: String,
        default: "",
    },
    formatDate: {
        type: Function,
        default: (dateValue) => dateValue || "-",
    },
    lang: {
        type: String,
        default: "en",
    },
});

const router = useRouter();

const formatCount = (value) => {
    const count = Number(value) || 0;

    try {
        return new Intl.NumberFormat(props.lang || "en", {
            notation: count >= 1000 ? "compact" : "standard",
            maximumFractionDigits: 1,
        }).format(count);
    } catch {
        return count.toString();
    }
};

const goToEvent = (event) => {
    if (!event?.slug) return;

    router.push(`/${props.lang}/single_event/${event.slug}`);
};
const truncateText = (text, limit = 20) => {
    if (!text) return "";
    return text.length > limit ? `${text.slice(0, limit)}...` : text;
};
</script>
