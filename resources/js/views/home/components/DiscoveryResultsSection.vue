<template>
    <section v-if="searched" class="discovery-results-section">
        <header class="discovery-results-header">
            <div class="discovery-results-copy">
                <h2>{{ $t("discovery.title") }}</h2>
                <p v-if="totalResults > 0">
                    {{ $t("discovery.showing") }} {{ resultFrom || 0 }} - {{ resultTo || 0 }}
                    {{ $t("discovery.of") }} {{ totalResults }} {{ $t("discovery.results") }}
                </p>
            </div>

            <button
                v-if="showSeeMore && results.length > 0 && !loading"
                type="button"
                class="discovery-see-more"
                @click="$emit('see-more')"
            >
                {{ $t("homeAudit.events.seeMore") }}
                <ArrowRightIcon aria-hidden="true" />
            </button>
        </header>

        <div class="discovery-tabs" role="tablist" :aria-label="$t('discovery.title')">
            <button
                v-for="tab in tabs"
                :key="tab"
                type="button"
                role="tab"
                :aria-selected="activeType === tab"
                :class="{ 'is-active': activeType === tab }"
                :disabled="loading"
                @click="$emit('update:active-type', tab)"
            >
                {{ $t(`discovery.tabs.${tab}`) }}
            </button>
        </div>

        <div v-if="loading" class="discovery-grid" aria-live="polite">
            <div v-for="index in Number(perPage) || 8" :key="`result-skeleton-${index}`" class="result-skeleton">
                <div class="result-media"></div>
                <div class="result-skeleton-body">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>

        <div v-else-if="results.length === 0" class="discovery-empty">
            <h3>{{ $t("discovery.noResultsTitle") }}</h3>
            <p>{{ $t("discovery.noResultsDescription") }}</p>
        </div>

        <div v-else class="discovery-grid">
            <article
                v-for="(result, index) in results"
                :key="`${result.result_type}-${result.id}`"
                class="discovery-card"
            >
                <a :href="eventUrl(result)" class="result-card-link">
                    <div class="result-media">
                        <img
                            :src="result.thumbnail_url || result.media_url || fallbackImage"
                            :alt="result.title"
                            :loading="index === 0 ? 'eager' : 'lazy'"
                            :fetchpriority="index === 0 ? 'high' : 'auto'"
                            decoding="async"
                        />
                        <span class="result-type-badge" :class="`is-${result.result_type}`">
                            <PlayIcon v-if="result.result_type === 'video'" aria-hidden="true" />
                            {{ $t(`discovery.resultLabels.${result.result_type}`) }}
                        </span>
                        <span v-if="result.result_type === 'video'" class="video-play-indicator" aria-hidden="true">
                            <PlayIcon />
                        </span>
                    </div>

                    <div class="result-card-body">
                        <div class="result-meta">
                            <span>
                                <CalendarDaysIcon aria-hidden="true" />
                                {{ formatDate(result.start_date) }}
                            </span>
                            <span>
                                <MapPinIcon aria-hidden="true" />
                                {{ result.city_name || result.city?.name || $t("common.notSpecified") }}
                            </span>
                        </div>

                        <h3>{{ result.title }}</h3>
                        <p v-if="result.description" class="result-description">{{ result.description }}</p>

                        <div class="result-card-footer">
                            <span>{{ result.category_name || $t("events.event") }}</span>
                            <span class="result-details">
                                {{ $t("discovery.details") }}
                                <ArrowRightIcon aria-hidden="true" />
                            </span>
                        </div>
                    </div>
                </a>
            </article>
        </div>

        <nav v-if="showPagination && totalPages > 1" class="discovery-pagination" aria-label="Pagination">
            <button
                type="button"
                :disabled="currentPage <= 1 || loading"
                aria-label="Previous page"
                @click="$emit('update:current-page', currentPage - 1)"
            >
                &#8249;
            </button>
            <button
                v-for="page in visiblePages"
                :key="page"
                type="button"
                :class="{ 'is-active': page === currentPage }"
                :disabled="loading"
                @click="$emit('update:current-page', page)"
            >
                {{ page }}
            </button>
            <button
                type="button"
                :disabled="currentPage >= totalPages || loading"
                aria-label="Next page"
                @click="$emit('update:current-page', currentPage + 1)"
            >
                &#8250;
            </button>
        </nav>
    </section>
</template>

<script setup>
import {
    ArrowRightIcon,
    CalendarDaysIcon,
    MapPinIcon,
    PlayIcon,
} from "@heroicons/vue/24/outline";

const props = defineProps({
    searched: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    results: { type: Array, default: () => [] },
    activeType: { type: String, default: "all" },
    tabs: { type: Array, default: () => ["all", "event", "image", "video"] },
    visiblePages: { type: Array, default: () => [] },
    currentPage: { type: Number, default: 1 },
    totalPages: { type: Number, default: 1 },
    totalResults: { type: Number, default: 0 },
    resultFrom: { type: Number, default: null },
    resultTo: { type: Number, default: null },
    perPage: { type: Number, default: 8 },
    fallbackImage: { type: String, default: "" },
    formatDate: { type: Function, required: true },
    lang: { type: String, default: "en" },
    showSeeMore: { type: Boolean, default: false },
    showPagination: { type: Boolean, default: false },
});

defineEmits(["update:active-type", "update:current-page", "see-more"]);

const eventUrl = (result) => `/${props.lang}/single_event/${result.event_slug || result.slug}`;
</script>

<style scoped>
.discovery-results-section {
    color: var(--scemory-text);
}

.discovery-results-header {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 18px;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--scemory-border-soft);
}

.discovery-results-copy h2 {
    margin: 0;
    color: var(--scemory-heading);
    font-size: 28px;
    font-weight: 900;
    letter-spacing: 0;
}

.discovery-results-copy p {
    margin: 6px 0 0;
    color: var(--scemory-muted);
    font-size: 14px;
}

.discovery-see-more {
    display: inline-flex;
    min-height: 42px;
    align-items: center;
    gap: 8px;
    border: 1px solid var(--scemory-primary);
    border-radius: 6px;
    background: var(--scemory-primary);
    padding: 9px 16px;
    color: #fff;
    font-size: 14px;
    font-weight: 800;
}

.discovery-see-more svg,
.result-details svg {
    width: 16px;
    height: 16px;
}

.discovery-tabs {
    display: inline-flex;
    max-width: 100%;
    gap: 4px;
    overflow-x: auto;
    margin-bottom: 22px;
    border: 1px solid var(--scemory-border);
    border-radius: 8px;
    background: var(--scemory-surface-soft);
    padding: 4px;
}

.discovery-tabs button {
    min-width: 82px;
    min-height: 38px;
    border: 0;
    border-radius: 5px;
    background: transparent;
    padding: 8px 14px;
    color: var(--scemory-muted);
    font-size: 13px;
    font-weight: 800;
    white-space: nowrap;
}

.discovery-tabs button.is-active {
    background: #fff;
    color: var(--scemory-primary);
    box-shadow: 0 2px 8px rgba(13, 77, 151, 0.1);
}

.discovery-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.discovery-card,
.result-skeleton {
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--scemory-border-soft);
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(13, 77, 151, 0.06);
}

.discovery-card {
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.discovery-card:hover {
    transform: translateY(-2px);
    border-color: var(--scemory-border);
    box-shadow: var(--scemory-shadow-hover);
}

.result-card-link {
    display: block;
    height: 100%;
    color: inherit;
    text-decoration: none;
}

.result-media {
    position: relative;
    aspect-ratio: 16 / 10;
    overflow: hidden;
    background: #e9eef4;
}

.result-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 240ms ease;
}

.discovery-card:hover .result-media img {
    transform: scale(1.025);
}

.result-type-badge {
    position: absolute;
    top: 12px;
    inset-inline-start: 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border-radius: 5px;
    background: rgba(255, 255, 255, 0.94);
    padding: 6px 9px;
    color: #0d4d97;
    font-size: 11px;
    font-weight: 900;
    box-shadow: 0 4px 12px rgba(4, 17, 29, 0.12);
}

.result-type-badge.is-video {
    background: rgba(4, 17, 29, 0.9);
    color: #fff;
}

.result-type-badge.is-image {
    color: #0f766e;
}

.result-type-badge svg {
    width: 13px;
    height: 13px;
}

.video-play-indicator {
    position: absolute;
    inset: 50% auto auto 50%;
    display: grid;
    width: 48px;
    height: 48px;
    place-items: center;
    transform: translate(-50%, -50%);
    border: 1px solid rgba(255, 255, 255, 0.55);
    border-radius: 50%;
    background: rgba(4, 17, 29, 0.72);
    color: #fff;
}

.video-play-indicator svg {
    width: 22px;
    height: 22px;
}

.result-card-body {
    display: flex;
    min-height: 190px;
    flex-direction: column;
    padding: 18px;
}

.result-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 14px;
    color: var(--scemory-muted);
    font-size: 12px;
}

.result-meta span {
    display: inline-flex;
    min-width: 0;
    align-items: center;
    gap: 5px;
}

.result-meta svg {
    width: 15px;
    height: 15px;
    flex: 0 0 auto;
    color: var(--scemory-primary);
}

.result-card-body h3 {
    display: -webkit-box;
    overflow: hidden;
    margin: 14px 0 0;
    color: var(--scemory-heading);
    font-size: 17px;
    font-weight: 850;
    letter-spacing: 0;
    line-height: 1.45;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.result-description {
    display: -webkit-box;
    overflow: hidden;
    margin: 8px 0 0;
    color: var(--scemory-muted);
    font-size: 13px;
    line-height: 1.6;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.result-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: auto;
    padding-top: 14px;
    border-top: 1px solid var(--scemory-border-soft);
    color: var(--scemory-muted);
    font-size: 12px;
}

.result-details {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--scemory-primary);
    font-weight: 800;
}

.result-skeleton .result-media,
.result-skeleton-body span {
    animation: result-pulse 1.4s ease-in-out infinite;
    background: #e8edf3;
}

.result-skeleton-body {
    display: grid;
    gap: 10px;
    padding: 18px;
}

.result-skeleton-body span {
    height: 14px;
    border-radius: 4px;
}

.result-skeleton-body span:nth-child(1) { width: 45%; }
.result-skeleton-body span:nth-child(3) { width: 72%; }

.discovery-empty {
    padding: 64px 20px;
    border-top: 1px solid var(--scemory-border-soft);
    border-bottom: 1px solid var(--scemory-border-soft);
    text-align: center;
}

.discovery-empty h3 {
    margin: 0;
    color: var(--scemory-heading);
    font-size: 22px;
    font-weight: 850;
}

.discovery-empty p {
    margin: 8px 0 0;
    color: var(--scemory-muted);
}

.discovery-pagination {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    margin-top: 30px;
}

.discovery-pagination button {
    display: grid;
    width: 40px;
    height: 40px;
    place-items: center;
    border: 1px solid var(--scemory-border);
    border-radius: 6px;
    background: #fff;
    color: var(--scemory-text);
    font-weight: 800;
}

.discovery-pagination button.is-active {
    border-color: var(--scemory-primary);
    background: var(--scemory-primary);
    color: #fff;
}

.discovery-pagination button:disabled {
    cursor: not-allowed;
    opacity: 0.45;
}

@keyframes result-pulse {
    50% { opacity: 0.55; }
}

@media (min-width: 1680px) {
    .discovery-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

@media (min-width: 700px) and (max-width: 1100px) {
    .discovery-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 699px) {
    .discovery-results-header { align-items: stretch; flex-direction: column; }
    .discovery-results-copy h2 { font-size: 24px; }
    .discovery-see-more { justify-content: center; }
    .discovery-tabs { display: grid; grid-template-columns: repeat(4, minmax(74px, 1fr)); width: 100%; }
    .discovery-tabs button { min-width: 74px; padding-inline: 8px; }
    .discovery-grid { grid-template-columns: 1fr; gap: 14px; }
}
</style>
