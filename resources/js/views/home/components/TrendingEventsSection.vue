<template>
    <section class="scemory-trending-panel" @mouseenter="pauseAutoplay" @mouseleave="resumeAutoplay">
        <div class="trending-panel-header">
            <span class="trending-eyebrow">
                {{ $t('homeAudit.trending.kicker') }}
            </span>

            <h2 class="trending-title">
                {{ $t('homeAudit.trending.title') }}
            </h2>

            <p class="trending-description">
                {{ $t('homeAudit.trending.description') }}
            </p>
        </div>

        <div v-if="loading" class="trending-carousel-shell">
            <article class="trending-feature-card trending-skeleton-card" aria-hidden="true">
                <div class="trending-media trending-skeleton-block"></div>
                <div class="trending-card-content">
                    <div class="trending-skeleton-line is-short"></div>
                    <div class="trending-skeleton-line"></div>
                    <div class="trending-skeleton-line is-medium"></div>
                </div>
            </article>
        </div>

        <div v-else-if="error" class="trending-state trending-error-state">
            {{ error }}
        </div>

        <div v-else-if="events.length === 0" class="trending-state trending-empty">
            <p class="trending-empty-title">{{ $t('homeAudit.trending.emptyTitle') }}</p>
            <p class="trending-empty-description">{{ $t('homeAudit.trending.emptyDescription') }}</p>
        </div>

        <div v-else class="trending-carousel-shell">
            <Transition name="trending-slide" mode="out-in">
                    <article v-if="currentEvent"
                        :key="currentEvent.slug || currentEvent.id || currentIndex"
                        role="button"
                        tabindex="0"
                        class="trending-feature-card"
                        @click="goToEvent(currentEvent)"
                        @keydown.enter.prevent="goToEvent(currentEvent)"
                        @keydown.space.prevent="goToEvent(currentEvent)"
                    >
                        <div class="trending-media">
                            <img
                                :src="currentEvent.image_url || fallbackImage"
                                :alt="currentEvent.title || $t('homeAudit.trending.title')"
                                class="trending-image"
                                loading="lazy"
                                decoding="async"
                            />

                            <div class="trending-image-overlay"></div>

                            <span class="trending-date-badge">
                                {{ formatDate(currentEvent.start_date) }}
                            </span>
                        </div>

                        <div class="trending-card-content">
                            <div v-if="currentEvent.category_name" class="trending-category">
                                {{ currentEvent.category_name }}
                            </div>

                            <h3 class="trending-event-title">
                                {{ truncateText(currentEvent.title || $t('homeAudit.trending.untitledEvent'), 72) }}
                            </h3>

                            <div v-if="currentEvent.location_name || currentEvent.user_name" class="trending-event-meta">
                                <span v-if="currentEvent.location_name">{{ currentEvent.location_name }}</span>
                                <span v-if="currentEvent.user_name">{{ currentEvent.user_name }}</span>
                            </div>

                            <p v-if="currentEvent.description" class="trending-event-description">
                                {{ truncateText(currentEvent.description, 150) }}
                            </p>

                            <div class="trending-stats">
                                <span class="trending-stat">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="1.8" />
                                    </svg>
                                    {{ formatCount(currentEvent.views_count) }} {{ $t('homeAudit.trending.views') }}
                                </span>
                                <span class="trending-stat">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M20.8 5.7a5.1 5.1 0 0 0-7.2 0L12 7.3l-1.6-1.6a5.1 5.1 0 0 0-7.2 7.2L12 21l8.8-8.1a5.1 5.1 0 0 0 0-7.2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    {{ formatCount(currentEvent.likes_count) }} {{ $t('homeAudit.trending.likes') }}
                                </span>
                            </div>
                        </div>
                    </article>
            </Transition>

            <div v-if="events.length > 1" class="trending-controls" :aria-label="$t('homeAudit.trending.slidesLabel')">
                <button type="button" class="trending-nav-button" @click.stop="goToPrevious" aria-label="Previous">
                    <span aria-hidden="true">&#8592;</span>
                </button>

                <span class="trending-position" aria-live="polite">
                    {{ activeSlideLabel }}
                </span>

                <button type="button" class="trending-nav-button" @click.stop="goToNext" aria-label="Next">
                    <span aria-hidden="true">&#8594;</span>
                </button>
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
    return `${currentIndex.value + 1} / ${props.events.length}`;
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
    }, 6000);
};

const pauseAutoplay = () => {
    isPaused.value = true;
    clearAutoplay();
};

const resumeAutoplay = () => {
    isPaused.value = false;
    startAutoplay();
};

const goToPrevious = () => {
    if (!props.events.length) return;

    currentIndex.value = (currentIndex.value - 1 + props.events.length) % props.events.length;
    startAutoplay();
};

const goToNext = () => {
    if (!props.events.length) return;

    currentIndex.value = (currentIndex.value + 1) % props.events.length;
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

const truncateText = (text, limit = 100) => {
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
@keyframes trending-shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.scemory-trending-panel {
    position: relative;
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    border: 1px solid rgba(213, 226, 240, .9);
    border-radius: 26px;
    background: rgba(255, 255, 255, .95);
    padding: 9px;
    color: #0b274b;
    box-shadow: 0 20px 48px rgba(14, 40, 73, .2);
    backdrop-filter: blur(14px);
    text-align: start;
}
.trending-panel-header {
    position: absolute;
    z-index: 4;
    top: 21px;
    inset-inline-start: 20px;
    pointer-events: none;
}
.trending-eyebrow {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    background: rgba(7, 34, 53, .84);
    padding: 5px 10px;
    color: #fff;
    font-size: 11px;
    font-weight: 850;
    letter-spacing: .02em;
    box-shadow: 0 5px 15px rgba(6, 20, 42, .15);
}
.trending-eyebrow::before {
    content: "";
    width: 7px;
    height: 7px;
    margin-inline-end: 6px;
    border-radius: 50%;
    background: #fff;
}
.trending-title,
.trending-description {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
}
.trending-carousel-shell {
    position: relative;
    min-width: 0;
}
.trending-feature-card {
    display: flex;
    min-width: 0;
    flex-direction: column;
    overflow: hidden;
    border: 0;
    border-radius: 18px;
    background: #fff;
    cursor: pointer;
    outline: none;
    transition: transform 180ms ease, box-shadow 180ms ease;
}
.trending-feature-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(13, 49, 86, .12);
}
.trending-feature-card:focus-visible {
    outline: 3px solid #1685eb;
    outline-offset: 2px;
}
.trending-media {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 8.5;
    overflow: hidden;
    background: #e6f0fa;
}
.trending-image {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 260ms ease;
}
.trending-feature-card:hover .trending-image {
    transform: scale(1.025);
}
.trending-image-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(6, 20, 42, .03), rgba(6, 20, 42, .15));
    pointer-events: none;
}
.trending-date-badge {
    position: absolute;
    inset-inline-end: 10px;
    bottom: 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .92);
    padding: 5px 9px;
    color: #0d4d97;
    font-size: 11px;
    font-weight: 750;
}
.trending-card-content {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 5px;
    padding: 12px 12px 9px;
}
.trending-category {
    overflow: hidden;
    color: #1263b1;
    font-size: 10px;
    font-weight: 850;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.trending-event-title {
    display: -webkit-box;
    margin: 0;
    overflow: hidden;
    color: #0c213f;
    font-size: 15px;
    font-weight: 850;
    line-height: 1.35;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}
.trending-event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 8px;
    color: #657588;
    font-size: 11px;
    font-weight: 650;
}
.trending-event-meta span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.trending-event-description {
    display: -webkit-box;
    margin: 0;
    overflow: hidden;
    color: #617084;
    font-size: 11px;
    line-height: 1.4;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}
.trending-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-block-start: 3px;
    color: #0d4d97;
    font-size: 10px;
    font-weight: 800;
}
.trending-stat {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.trending-stat svg {
    width: 13px;
    height: 13px;
}
.trending-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-block-start: 5px;
}
.trending-nav-button {
    display: grid;
    width: 28px;
    height: 28px;
    place-items: center;
    border: 1px solid #d9e6f2;
    border-radius: 50%;
    background: #fff;
    color: #0d4d97;
    cursor: pointer;
}
.trending-nav-button:hover {
    background: #eaf4ff;
}
.trending-nav-button:focus-visible {
    outline: 3px solid #1685eb;
}
.trending-position {
    min-width: 42px;
    color: #526479;
    font-size: 11px;
    font-weight: 750;
    text-align: center;
}
.trending-state {
    border-radius: 18px;
    padding: 20px;
    font-size: 13px;
    line-height: 1.5;
}
.trending-error-state {
    background: #fef2f2;
    color: #b91c1c;
}
.trending-empty {
    background: #f1f7fd;
}
.trending-empty-title {
    margin: 0;
    font-weight: 800;
}
.trending-empty-description {
    margin: 5px 0 0;
}
.trending-skeleton-card {
    pointer-events: none;
}
.trending-skeleton-block,
.trending-skeleton-line {
    background: linear-gradient(110deg, #e5f0fa 45%, #f8fbfe 55%, #e5f0fa 65%);
    background-size: 200% 100%;
    animation: trending-shimmer 1.8s linear infinite;
}
.trending-skeleton-line {
    height: 12px;
    border-radius: 999px;
}
.trending-skeleton-line.is-short { width: 38%; }
.trending-skeleton-line.is-medium { width: 72%; }
.trending-slide-enter-active,
.trending-slide-leave-active {
    transition: opacity 220ms ease, transform 220ms ease;
}
.trending-slide-enter-from {
    opacity: 0;
    transform: translateX(14px);
}
.trending-slide-leave-to {
    opacity: 0;
    transform: translateX(-14px);
}
@media (max-width: 640px) {
    .scemory-trending-panel { border-radius: 22px; }
    .trending-media { aspect-ratio: 16 / 8; }
}
@media (prefers-reduced-motion: reduce) {
    .trending-feature-card,
    .trending-image,
    .trending-slide-enter-active,
    .trending-slide-leave-active {
        transition: none;
    }
}
</style>
