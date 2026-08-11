<template>
    <section
        class="scemory-trending-panel min-h-[620px] rounded-[22px] border p-4 backdrop-blur-xl"
        @mouseenter="pauseAutoplay"
        @mouseleave="resumeAutoplay"
    >
        <div class="mb-3">
            <span class="trending-eyebrow inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold">
                Trending
            </span>

            <h2 class="mt-2 text-lg font-bold text-[#0F172A]">
                Trending Events
            </h2>

            <p class="mt-1 text-xs leading-5 text-[#475569]">
                Events gaining attention across the archive.
            </p>
        </div>

        <div v-if="loading" class="trending-carousel-shell">
            <article class="trending-feature-card h-[490px] animate-pulse overflow-hidden rounded-xl border">
                <div class="h-[240px] w-full"></div>
                <div class="space-y-3 p-4">
                    <div class="h-3 w-24 rounded"></div>
                    <div class="h-4 w-full rounded"></div>
                    <div class="h-4 w-4/5 rounded"></div>
                    <div class="h-3 w-full rounded"></div>
                    <div class="h-3 w-3/4 rounded"></div>
                </div>
            </article>
        </div>

        <div
            v-else-if="error"
            class="rounded-xl border border-red-100 bg-red-50 px-3 py-4 text-xs font-semibold leading-5 text-red-600"
        >
            {{ error }}
        </div>

        <div
            v-else-if="events.length === 0"
            class="trending-empty rounded-xl border border-dashed px-3 py-5 text-center"
        >
            <p class="text-sm font-bold text-[#0F172A]">No trending events yet</p>
            <p class="mt-1 text-xs leading-5 text-[#64748B]">Check back soon for highlighted events.</p>
        </div>

        <div v-else class="trending-carousel-shell">
            <div class="trending-carousel-window">
                <Transition name="trending-slide" mode="out-in">
                    <article
                        v-if="currentEvent"
                        :key="currentEvent.slug || currentEvent.id || currentIndex"
                        role="button"
                        tabindex="0"
                        class="trending-feature-card group flex min-h-[490px] cursor-pointer flex-col overflow-hidden rounded-xl border transition focus:outline-none focus:ring-2"
                        @click="goToEvent(currentEvent)"
                        @keydown.enter.prevent="goToEvent(currentEvent)"
                        @keydown.space.prevent="goToEvent(currentEvent)"
                    >
                        <div class="trending-media relative h-[240px] shrink-0 overflow-hidden">
                            <img
                                :src="currentEvent.image_url || fallbackImage"
                                :alt="currentEvent.title || 'Trending event'"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                                loading="lazy"
                                decoding="async"
                            />

                            <div class="absolute inset-0 bg-gradient-to-t from-[#06142A]/70 via-transparent to-transparent"></div>

                            <span class="absolute left-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-bold text-[#0D4D97] shadow-sm backdrop-blur">
                                {{ formatDate(currentEvent.start_date) }}
                            </span>
                        </div>

                        <div class="flex flex-1 flex-col gap-3 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span
                                    v-if="currentEvent.user_name"
                                    class="truncate text-[10px] font-semibold text-[#94A3B8]"
                                >
                                    {{ currentEvent.user_name }}
                                </span>

                                <span class="shrink-0 text-[10px] font-bold text-[#0D4D97]">
                                    {{ activeSlideLabel }}
                                </span>
                            </div>

                            <h6 class="line-clamp-2 text-sm font-bold leading-5 text-[#0F172A] transition group-hover:text-[#0D4D97]">
                                {{ truncateText(currentEvent.title || "Untitled event", 78) }}
                            </h6>

                            <p
                                v-if="currentEvent.description"
                                class="line-clamp-4 min-h-[64px] text-[11px] leading-4 text-[#64748B]"
                            >
                                {{ truncateText(currentEvent.description, 130) }}
                            </p>

                            <div class="trending-stats mt-auto flex items-center justify-between gap-3 border-t pt-3 text-[10px] font-semibold text-[#64748B]">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="1.8" />
                                    </svg>
                                    {{ formatCount(currentEvent.views_count) }} views
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M20.8 5.7a5.1 5.1 0 0 0-7.2 0L12 7.3l-1.6-1.6a5.1 5.1 0 0 0-7.2 7.2L12 21l8.8-8.1a5.1 5.1 0 0 0 0-7.2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    {{ formatCount(currentEvent.likes_count) }} likes
                                </span>
                            </div>
                        </div>
                    </article>
                </Transition>
            </div>

            <div
                v-if="events.length > 1"
                class="trending-indicators"
                aria-label="Trending event slides"
            >
                <button
                    v-for="(event, index) in events"
                    :key="event.slug || event.id || `indicator-${index}`"
                    type="button"
                    class="trending-dot"
                    :class="{ 'is-active': index === currentIndex }"
                    :aria-label="`Show trending event ${index + 1}`"
                    :aria-current="index === currentIndex ? 'true' : 'false'"
                    @click.stop="goToSlide(index)"
                ></button>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
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
const currentIndex = ref(0);
const isPaused = ref(false);
let autoplayTimer = null;

const canAutoplay = computed(() => {
    return !props.loading && !props.error && props.events.length > 1;
});

const currentEvent = computed(() => {
    return props.events[currentIndex.value] || props.events[0] || null;
});

const activeSlideLabel = computed(() => {
    return `${currentIndex.value + 1}/${props.events.length}`;
});

const clearAutoplay = () => {
    if (autoplayTimer) {
        clearInterval(autoplayTimer);
        autoplayTimer = null;
    }
};

const startAutoplay = () => {
    clearAutoplay();

    if (!canAutoplay.value || isPaused.value) {
        return;
    }

    autoplayTimer = setInterval(() => {
        currentIndex.value = (currentIndex.value + 1) % props.events.length;
    }, 3500);
};

const pauseAutoplay = () => {
    isPaused.value = true;
    clearAutoplay();
};

const resumeAutoplay = () => {
    isPaused.value = false;
    startAutoplay();
};

const goToSlide = (index) => {
    currentIndex.value = index;
    startAutoplay();
};

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

    return text.length > limit ? `${text.slice(0, limit).trim()}...` : text;
};

watch(
    () => props.events.length,
    (length) => {
        if (!length) {
            currentIndex.value = 0;
            clearAutoplay();
            return;
        }

        if (currentIndex.value >= length) {
            currentIndex.value = 0;
        }

        startAutoplay();
    }
);

watch(canAutoplay, () => {
    startAutoplay();
});

onMounted(() => {
    startAutoplay();
});

onUnmounted(() => {
    clearAutoplay();
});
</script>

<style scoped>
.scemory-trending-panel {
    border-color: var(--scemory-border-soft);
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.92), rgba(247, 250, 253, 0.96));
    box-shadow: var(--scemory-shadow);
}

.trending-eyebrow {
    border: 1px solid var(--scemory-border);
    background: var(--scemory-active);
    color: var(--scemory-primary);
}

.trending-carousel-shell {
    position: relative;
    min-height: 500px;
}

.trending-carousel-window {
    position: relative;
    overflow: hidden;
    min-height: 500px;
}

.trending-feature-card {
    min-height: 490px;
    border-color: var(--scemory-border-soft);
    background: var(--scemory-control);
    box-shadow: var(--scemory-shadow-sm);
}

.trending-feature-card:hover {
    transform: translateY(-2px);
    border-color: var(--scemory-border);
    background: var(--scemory-hover);
    box-shadow: var(--scemory-shadow-hover);
}

.trending-feature-card:focus {
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.10);
}

.trending-feature-card .rounded,
.trending-media {
    background: var(--scemory-active);
}

.trending-feature-card .border-t {
    border-color: var(--scemory-border-soft) !important;
}

.trending-empty {
    border-color: var(--scemory-border);
    background: var(--scemory-surface);
}

.trending-indicators {
    position: static;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    margin-top: 14px;
    transform: none;
}

.trending-dot {
    width: 0.55rem;
    height: 0.55rem;
    border: 1px solid var(--scemory-border);
    border-radius: 999px;
    background: #FFFFFF;
    box-shadow: var(--scemory-shadow-sm);
    transition: var(--scemory-transition);
}

.trending-dot.is-active {
    width: 1.45rem;
    height: 0.55rem;
    background: linear-gradient(
        90deg,
        var(--scemory-primary),
        var(--scemory-light-blue)
    );
    border-color: rgba(22, 119, 255, 0.35);
}

.trending-slide-enter-active,
.trending-slide-leave-active {
    transition: opacity 0.28s ease, transform 0.28s ease;
}

.trending-slide-enter-from {
    opacity: 0;
    transform: translateY(22px);
}

.trending-slide-leave-to {
    opacity: 0;
    transform: translateY(-22px);
}

.scemory-trending-panel h2,
.scemory-trending-panel h6 {
    color: var(--scemory-heading);
}

.scemory-trending-panel p,
.scemory-trending-panel .text-\[\#475569\],
.scemory-trending-panel .text-\[\#64748B\] {
    color: var(--scemory-muted);
}

.scemory-trending-panel span {
    border-color: rgba(22, 119, 255, 0.15);
}
</style>
