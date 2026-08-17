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
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}

.scemory-trending-panel {
    box-sizing: border-box;
    width: 100%;
    min-height: 0;
    overflow: visible;
    border: 1px solid var(--scemory-border-soft);
    border-radius: 22px;
    background: #FFFFFF;
    padding: 18px;
    box-shadow: 0 14px 34px rgba(13, 77, 151, 0.08);
}

.trending-panel-header {
    margin-bottom: 16px;
}

.trending-eyebrow {
    display: inline-flex;
    border: 1px solid var(--scemory-border);
    border-radius: 999px;
    background: var(--scemory-active);
    padding: 4px 10px;
    color: var(--scemory-primary);
    font-size: 10px;
    font-weight: 800;
}

.trending-title {
    margin: 9px 0 0;
    color: var(--scemory-heading);
    font-size: 18px;
    font-weight: 800;
    line-height: 1.3;
}

.trending-description {
    margin: 5px 0 0;
    color: var(--scemory-muted);
    font-size: 12px;
    line-height: 1.6;
}

.trending-carousel-shell {
    position: relative;
    width: 100%;
    min-width: 0;
}

.trending-feature-card {
    display: flex;
    width: 100%;
    min-width: 0;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--scemory-border-soft);
    border-radius: 18px;
    background: #FFFFFF;
    box-shadow: 0 7px 20px rgba(13, 77, 151, 0.07);
    cursor: pointer;
    outline: none;
    transition: border-color 180ms ease, box-shadow 180ms ease, transform 180ms ease;
}

.trending-feature-card:hover {
    transform: translateY(-2px);
    border-color: var(--scemory-border);
    background: #FFFFFF;
    box-shadow: 0 12px 28px rgba(13, 77, 151, 0.12);
}

.trending-feature-card:focus-visible {
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.12);
}

.trending-media {
    position: relative;
    width: 100%;
    height: auto;
    aspect-ratio: 16 / 10;
    flex: 0 0 auto;
    overflow: hidden;
    background: var(--scemory-active);
}

.trending-image {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 280ms ease;
}

.trending-feature-card:hover .trending-image {
    transform: scale(1.025);
}

.trending-image-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(6, 20, 42, 0.04) 35%, rgba(6, 20, 42, 0.48) 100%);
    pointer-events: none;
}

.trending-date-badge {
    position: absolute;
    top: 12px;
    inset-inline-start: 12px;
    border: 1px solid rgba(255, 255, 255, 0.72);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    padding: 6px 10px;
    color: var(--scemory-primary);
    font-size: 10px;
    font-weight: 800;
    box-shadow: 0 5px 14px rgba(6, 20, 42, 0.12);
    backdrop-filter: blur(8px);
}

.trending-card-content {
    display: flex;
    min-width: 0;
    flex: 1 1 auto;
    flex-direction: column;
    gap: 9px;
    padding: 16px;
}

.trending-category {
    width: fit-content;
    max-width: 100%;
    overflow: hidden;
    color: var(--scemory-blue);
    font-size: 10px;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.trending-event-title {
    display: -webkit-box;
    margin: 0;
    overflow: hidden;
    color: var(--scemory-heading);
    font-size: 15px;
    font-weight: 800;
    line-height: 1.45;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.trending-event-meta {
    display: flex;
    min-width: 0;
    flex-wrap: wrap;
    gap: 5px 10px;
    color: #64748B;
    font-size: 11px;
    font-weight: 600;
}

.trending-event-meta span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.trending-event-description {
    display: block;
    margin: 0;
    overflow: visible;
    color: var(--scemory-muted);
    font-size: 11px;
    line-height: 1.55;
    white-space: normal;
    overflow-wrap: anywhere;
}

.trending-stats {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: auto;
    border-top: 1px solid var(--scemory-border-soft);
    padding-top: 12px;
    color: #64748B;
    font-size: 10px;
    font-weight: 700;
}

.trending-stat {
    display: inline-flex;
    min-width: 0;
    align-items: center;
    gap: 5px;
}

.trending-stat svg {
    width: 15px;
    height: 15px;
    flex: 0 0 auto;
}

.trending-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-top: 15px;
}

.trending-nav-button {
    display: inline-flex;
    width: 34px;
    height: 34px;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--scemory-border);
    border-radius: 999px;
    background: #FFFFFF;
    color: var(--scemory-primary);
    font-size: 16px;
    font-weight: 900;
    box-shadow: 0 5px 14px rgba(13, 77, 151, 0.08);
    cursor: pointer;
    transition: background-color 180ms ease, color 180ms ease, transform 180ms ease;
}

.trending-nav-button:hover {
    transform: translateY(-1px);
    background: var(--scemory-primary);
    color: #FFFFFF;
}

.trending-position {
    min-width: 54px;
    color: var(--scemory-heading);
    font-size: 12px;
    font-weight: 800;
    text-align: center;
}

.trending-state {
    box-sizing: border-box;
    width: 100%;
    border-radius: 16px;
    padding: 18px 14px;
    font-size: 12px;
    line-height: 1.6;
}

.trending-error-state {
    border: 1px solid #FECACA;
    background: #FEF2F2;
    color: #DC2626;
    font-weight: 700;
}

.trending-empty {
    border: 1px dashed var(--scemory-border);
    background: var(--scemory-surface);
    text-align: center;
}

.trending-empty-title {
    margin: 0;
    color: var(--scemory-heading);
    font-size: 14px;
    font-weight: 800;
}

.trending-empty-description {
    margin: 5px 0 0;
    color: var(--scemory-muted);
    font-size: 12px;
}

.trending-skeleton-card {
    cursor: default;
    pointer-events: none;
}

.trending-skeleton-block,
.trending-skeleton-line {
    background: linear-gradient(110deg, #EDF4FA 45%, #F8FBFE 55%, #EDF4FA 65%);
    background-size: 200% 100%;
    animation: trending-shimmer 1.8s linear infinite;
}

.trending-skeleton-line {
    width: 100%;
    height: 13px;
    border-radius: 999px;
}

.trending-skeleton-line.is-short {
    width: 38%;
}

.trending-skeleton-line.is-medium {
    width: 72%;
}

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

@media (min-width: 992px) and (max-width: 1399px) {
    .scemory-trending-panel {
        display: grid;
        grid-template-columns: 240px minmax(0, 1fr);
        gap: 22px;
        align-items: center;
        padding: 20px;
    }

    .trending-panel-header {
        margin: 0;
    }

    .trending-feature-card {
        display: grid;
        grid-template-columns: minmax(250px, 42%) minmax(0, 1fr);
        min-height: 246px;
    }

    .trending-media {
        height: 100%;
        min-height: 246px;
        aspect-ratio: auto;
    }

    .trending-card-content {
        padding: 20px;
    }

    .trending-state {
        align-self: stretch;
    }
}

@media (min-width: 1400px) {
    .scemory-trending-panel {
        display: flex;
        min-height: 560px;
        flex-direction: column;
    }

    .trending-carousel-shell {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
    }

    .trending-feature-card {
        flex: 1 1 auto;
    }

    .trending-description {
        display: none;
    }

    .trending-card-content {
        gap: 7px;
        padding: 14px;
    }

    .trending-event-description {
        -webkit-line-clamp: 1;
    }

    .trending-controls {
        margin-top: 12px;
    }
}

@media (max-width: 640px) {
    .scemory-trending-panel {
        border-radius: 20px;
        padding: 14px;
    }

    .trending-media {
        aspect-ratio: 16 / 9;
    }

    .trending-card-content {
        padding: 14px;
    }
}
</style>
