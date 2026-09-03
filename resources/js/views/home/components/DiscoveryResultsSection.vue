<template>
    <section v-if="searched" class="discovery-results-section">
        <!-- Header -->
        <header class="discovery-results-header">
            <div class="discovery-results-copy">
                <h2>{{ $t("discovery.title") }}</h2>

                <p v-if="totalResults > 0">
                    {{ $t("discovery.showing") }}
                    {{ resultFrom || 0 }}
                    -
                    {{ resultTo || 0 }}
                    {{ $t("discovery.of") }}
                    {{ totalResults }}
                    {{ $t("discovery.results") }}
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

        <!-- Tabs -->
        <div
            class="discovery-tabs"
            role="tablist"
            :aria-label="$t('discovery.title')"
        >
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

        <!-- Loading -->
        <div
            v-if="loading"
            class="discovery-grid"
            aria-live="polite"
        >
            <div
                v-for="index in Number(perPage) || 8"
                :key="`result-skeleton-${index}`"
                class="result-skeleton"
            >
                <div class="result-media"></div>

                <div class="result-skeleton-body">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>

        <!-- Empty -->
        <div
            v-else-if="results.length === 0"
            class="discovery-empty"
        >
            <h3>{{ $t("discovery.noResultsTitle") }}</h3>

            <p>
                {{ $t("discovery.noResultsDescription") }}
            </p>
        </div>

        <!-- Results -->
        <div
            v-else
            class="discovery-grid"
        >
            <template
                v-for="(result, index) in results"
                :key="`${result.result_type}-${result.id}`"
            >
            <article
                v-if="result.result_type === 'event'"
                class="discovery-card"
            >
                <a
                    :href="eventUrl(result)"
                    class="result-card-link"
                >
                    <!-- Media -->
                    <div class="result-media">

                        <!-- ================================= -->
                        <!-- VIDEO -->
                        <!-- ================================= -->
                        <template v-if="result.result_type === 'video'">

                            <!--
                                IMPORTANT:
                                لا نستخدم thumbnail_url هنا نهائيًا.
                                نعرض الفيديو نفسه ونأخذ أول Frame.
                            -->
                            <video
                                v-if="
                                    videoSource(result) &&
                                    !isVideoFailed(result)
                                "
                                class="result-video"
                                :src="videoSource(result)"
                                muted
                                playsinline
                                preload="auto"
                                aria-hidden="true"
                                @loadedmetadata="prepareVideoFrame"
                                @loadeddata="prepareVideoFrame"
                                @error="markVideoFailed(result)"
                            ></video>

                            <!-- Fallback image -->
                            <img
                                v-else-if="fallbackImage"
                                :src="fallbackImage"
                                :alt="result.title || ''"
                                loading="lazy"
                                decoding="async"
                                @error="handleFallbackImageError"
                            />

                            <!-- Final fallback -->
                            <div
                                v-else
                                class="result-media-empty is-video-empty"
                                aria-hidden="true"
                            >
                                <PlayIcon />
                            </div>

                        </template>

                        <!-- ================================= -->
                        <!-- IMAGE / EVENT -->
                        <!-- ================================= -->
                        <template v-else>

                            <img
                                v-if="imageSource(result)"
                                :src="imageSource(result)"
                                :alt="result.title || ''"
                                :loading="index === 0 ? 'eager' : 'lazy'"
                                :fetchpriority="index === 0 ? 'high' : 'auto'"
                                decoding="async"
                                @error="handleImageError"
                            />

                            <div
                                v-else
                                class="result-media-empty"
                                aria-hidden="true"
                            ></div>

                        </template>

                        <!-- Type Badge -->
                        <span
                            class="result-type-badge"
                            :class="`is-${result.result_type}`"
                        >
                            <PlayIcon
                                v-if="result.result_type === 'video'"
                                aria-hidden="true"
                            />

                            {{
                                $t(
                                    `discovery.resultLabels.${result.result_type}`
                                )
                            }}
                        </span>

                        <!-- Video Play Indicator -->
                        <span
                            v-if="result.result_type === 'video'"
                            class="video-play-indicator"
                            aria-hidden="true"
                        >
                            <PlayIcon />
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="result-card-body">

                        <!-- Meta -->
                        <div class="result-meta">
                            <span>
                                <CalendarDaysIcon aria-hidden="true" />

                                {{
                                    formatDate(
                                        result.start_date
                                    )
                                }}
                            </span>

                            <span>
                                <MapPinIcon aria-hidden="true" />

                                {{
                                    result.city_name ||
                                    result.city?.name ||
                                    $t("common.notSpecified")
                                }}
                            </span>
                        </div>

                        <!-- Title -->
                        <h3>
                            {{ result.title }}
                        </h3>

                        <!-- Description -->
                        <p
                            v-if="result.description"
                            class="result-description"
                        >
                            {{ result.description }}
                        </p>

                        <!-- Footer -->
                        <div class="result-card-footer">
                            <span>
                                {{
                                    result.category_name ||
                                    $t("events.event")
                                }}
                            </span>

                            <span class="result-details">
                                {{ $t("discovery.details") }}

                                <ArrowRightIcon aria-hidden="true" />
                            </span>
                        </div>
                    </div>
                </a>
            </article>

            <DiscoveryMediaCard
                v-else
                :result="result"
                :index="index"
                :fallback-image="fallbackImage"
                :preview-enabled="enableMediaPreview && Boolean(mediaPreviewSource(result))"
                @preview="openMediaPreview"
            />
            </template>
        </div>

        <!-- Pagination -->
        <nav
            v-if="showPagination && totalPages > 1"
            class="discovery-pagination"
            aria-label="Pagination"
        >
            <button
                type="button"
                :disabled="currentPage <= 1 || loading"
                aria-label="Previous page"
                @click="$emit(
                    'update:current-page',
                    currentPage - 1
                )"
            >
                &#8249;
            </button>

            <button
                v-for="page in visiblePages"
                :key="page"
                type="button"
                :class="{ 'is-active': page === currentPage }"
                :disabled="loading"
                @click="$emit(
                    'update:current-page',
                    page
                )"
            >
                {{ page }}
            </button>

            <button
                type="button"
                :disabled="currentPage >= totalPages || loading"
                aria-label="Next page"
                @click="$emit(
                    'update:current-page',
                    currentPage + 1
                )"
            >
                &#8250;
            </button>
        </nav>
    </section>

    <Teleport to="body">
        <Transition name="media-preview">
            <div
                v-if="isPreviewOpen && currentPreviewMedia"
                class="media-preview"
                role="dialog"
                aria-modal="true"
                :aria-label="currentPreviewMedia.title || $t('discovery.title')"
                @click.self="closeMediaPreview"
            >
                <button
                    type="button"
                    class="media-preview__control media-preview__close"
                    :aria-label="$t('common.close')"
                    @click="closeMediaPreview"
                >
                    &times;
                </button>

                <button
                    v-if="mediaItems.length > 1"
                    type="button"
                    class="media-preview__control media-preview__previous"
                    :aria-label="$t('common.previous')"
                    @click="showPreviousMedia"
                >
                    &#8249;
                </button>

                <button
                    v-if="mediaItems.length > 1"
                    type="button"
                    class="media-preview__control media-preview__next"
                    :aria-label="$t('common.next')"
                    @click="showNextMedia"
                >
                    &#8250;
                </button>

                <video
                    v-if="currentPreviewMedia.result_type === 'video'"
                    :key="previewMediaKey"
                    class="media-preview__media"
                    :src="mediaPreviewSource(currentPreviewMedia)"
                    controls
                    autoplay
                    playsinline
                    preload="metadata"
                ></video>

                <img
                    v-else
                    :key="previewMediaKey"
                    class="media-preview__media"
                    :src="mediaPreviewSource(currentPreviewMedia)"
                    :alt="currentPreviewMedia.title || ''"
                    @error="handlePreviewImageError"
                />
            </div>
        </Transition>
    </Teleport>
</template>


<script setup>
import { computed, onUnmounted, reactive, ref, watch } from "vue";

import {
    ArrowRightIcon,
    CalendarDaysIcon,
    MapPinIcon,
    PlayIcon,
} from "@heroicons/vue/24/outline";

import DiscoveryMediaCard from "./DiscoveryMediaCard.vue";


const props = defineProps({
    searched: {
        type: Boolean,
        default: false,
    },

    loading: {
        type: Boolean,
        default: false,
    },

    results: {
        type: Array,
        default: () => [],
    },

    activeType: {
        type: String,
        default: "all",
    },

    tabs: {
        type: Array,
        default: () => [
            "all",
            "event",
            "image",
            "video",
        ],
    },

    visiblePages: {
        type: Array,
        default: () => [],
    },

    currentPage: {
        type: Number,
        default: 1,
    },

    totalPages: {
        type: Number,
        default: 1,
    },

    totalResults: {
        type: Number,
        default: 0,
    },

    resultFrom: {
        type: Number,
        default: null,
    },

    resultTo: {
        type: Number,
        default: null,
    },

    perPage: {
        type: Number,
        default: 8,
    },

    fallbackImage: {
        type: String,
        default: "",
    },

    formatDate: {
        type: Function,
        required: true,
    },

    lang: {
        type: String,
        default: "en",
    },

    showSeeMore: {
        type: Boolean,
        default: false,
    },

    showPagination: {
        type: Boolean,
        default: false,
    },

    enableMediaPreview: {
        type: Boolean,
        default: false,
    },
});


defineEmits([
    "update:active-type",
    "update:current-page",
    "see-more",
]);


/*
|--------------------------------------------------------------------------
| Failed videos
|--------------------------------------------------------------------------
*/

const failedVideos = reactive(new Set());


/*
|--------------------------------------------------------------------------
| Media preview
|--------------------------------------------------------------------------
*/

const isPreviewOpen = ref(false);
const previewIndex = ref(0);

const mediaPreviewSource = (result) => {
    if (!result || !["image", "video"].includes(result.result_type)) {
        return "";
    }

    return (
        result.preview_url ||
        result.thumbnail_url ||
        ""
    );
};

const mediaItems = computed(() => {
    return props.results.filter((result) => {
        return (
            ["image", "video"].includes(result?.result_type) &&
            Boolean(mediaPreviewSource(result))
        );
    });
});

const currentPreviewMedia = computed(() => {
    return mediaItems.value[previewIndex.value] || null;
});

const previewMediaKey = computed(() => {
    const media = currentPreviewMedia.value;

    return `${media?.result_type || "media"}-${media?.id || mediaPreviewSource(media)}`;
});

const openMediaPreview = (result) => {
    if (!props.enableMediaPreview) {
        return;
    }

    const index = mediaItems.value.indexOf(result);

    if (index < 0) {
        return;
    }

    previewIndex.value = index;
    isPreviewOpen.value = true;
};

const closeMediaPreview = () => {
    isPreviewOpen.value = false;
};

const showPreviousMedia = () => {
    const total = mediaItems.value.length;

    if (!total) {
        return;
    }

    previewIndex.value = (previewIndex.value - 1 + total) % total;
};

const showNextMedia = () => {
    const total = mediaItems.value.length;

    if (!total) {
        return;
    }

    previewIndex.value = (previewIndex.value + 1) % total;
};

const handleMediaPreviewKeydown = (event) => {
    if (!isPreviewOpen.value) {
        return;
    }

    if (event.key === "Escape") {
        event.preventDefault();
        closeMediaPreview();
        return;
    }

    if (event.key === "ArrowRight") {
        event.preventDefault();
        showNextMedia();
        return;
    }

    if (event.key === "ArrowLeft") {
        event.preventDefault();
        showPreviousMedia();
    }
};

const handlePreviewImageError = (event) => {
    const image = event?.target;

    if (!image) {
        return;
    }

    if (props.fallbackImage && image.src !== props.fallbackImage) {
        image.src = props.fallbackImage;
    }
};

watch(isPreviewOpen, (isOpen) => {
    if (isOpen) {
        window.addEventListener("keydown", handleMediaPreviewKeydown);
        return;
    }

    window.removeEventListener("keydown", handleMediaPreviewKeydown);
});

onUnmounted(() => {
    window.removeEventListener("keydown", handleMediaPreviewKeydown);
});


/*
|--------------------------------------------------------------------------
| Event URL
|--------------------------------------------------------------------------
*/

const eventUrl = (result) => {
    return `/${props.lang}/single_event/${
        result.event_slug ||
        result.slug ||
        ""
    }`;
};


/*
|--------------------------------------------------------------------------
| Video Source
|--------------------------------------------------------------------------
|
| نحاول أكثر من field تحسبًا لاختلاف Response الـ API.
|
*/

const videoSource = (result) => {
    if (!result) {
        return "";
    }

    return (
        result.video_url ||
        result.media_url ||
        result.file_url ||
        result.url ||
        ""
    );
};


/*
|--------------------------------------------------------------------------
| Image Source
|--------------------------------------------------------------------------
*/

const imageSource = (result) => {
    if (!result) {
        return props.fallbackImage || "";
    }

    return (
        result.thumbnail_url ||
        result.media_url ||
        result.image_url ||
        props.fallbackImage ||
        ""
    );
};


/*
|--------------------------------------------------------------------------
| Create unique result key
|--------------------------------------------------------------------------
*/

const resultKey = (result) => {
    return `${result?.result_type || "result"}-${result?.id || videoSource(result)}`;
};


/*
|--------------------------------------------------------------------------
| Video Error
|--------------------------------------------------------------------------
*/

const markVideoFailed = (result) => {
    const key = resultKey(result);

    failedVideos.add(key);

    console.error(
        "Discovery video failed to load:",
        {
            id: result?.id,
            source: videoSource(result),
            result,
        }
    );
};


const isVideoFailed = (result) => {
    return failedVideos.has(
        resultKey(result)
    );
};


/*
|--------------------------------------------------------------------------
| Show first frame of video
|--------------------------------------------------------------------------
|
| بعد تحميل Metadata نحرك الفيديو لـ 0.1 ثانية.
| كده المتصفح يعرض Frame حقيقي بدل صورة thumbnail مكسورة.
|
*/

const prepareVideoFrame = (event) => {
    const video = event?.target;

    if (!video) {
        return;
    }

    try {
        video.muted = true;

        /*
         * لو الفيديو أطول من 0.1 ثانية
         * نجيب Frame قريب من البداية.
         */
        if (
            Number.isFinite(video.duration) &&
            video.duration > 0.15
        ) {
            if (
                video.currentTime === 0 ||
                video.currentTime < 0.05
            ) {
                video.currentTime = 0.1;
            }
        }

        video.pause();
    } catch (error) {
        console.error(
            "Unable to prepare video frame:",
            error
        );
    }
};


/*
|--------------------------------------------------------------------------
| Broken normal image fallback
|--------------------------------------------------------------------------
*/

const handleImageError = (event) => {
    const image = event?.target;

    if (!image) {
        return;
    }

    /*
     * لو عندنا fallback مختلف عن الصورة الحالية
     * استخدمه.
     */
    if (
        props.fallbackImage &&
        image.src !== props.fallbackImage
    ) {
        image.src = props.fallbackImage;
        return;
    }

    /*
     * لو حتى fallback مكسور
     */
    image.style.display = "none";
};


const handleFallbackImageError = (event) => {
    const image = event?.target;

    if (image) {
        image.style.display = "none";
    }
};
</script>


<style scoped>

/* =========================================================
   MAIN
========================================================= */

.discovery-results-section {
    color: var(--scemory-text);
}


/* =========================================================
   HEADER
========================================================= */

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


/* =========================================================
   SEE MORE
========================================================= */

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

    cursor: pointer;
}


.discovery-see-more svg,
.result-details svg {
    width: 16px;
    height: 16px;
}


/* =========================================================
   TABS
========================================================= */

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

    cursor: pointer;
}


.discovery-tabs button.is-active {
    background: #fff;

    color: var(--scemory-primary);

    box-shadow: 0 2px 8px rgba(
        13,
        77,
        151,
        0.1
    );
}


.discovery-tabs button:disabled {
    cursor: wait;
}


/* =========================================================
   GRID
========================================================= */

.discovery-grid {
    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0, 1fr)
        );

    gap: 20px;
}


/* =========================================================
   CARD
========================================================= */

.discovery-card,
.result-skeleton {
    min-width: 0;

    overflow: hidden;

    border: 1px solid var(--scemory-border-soft);
    border-radius: 8px;

    background: #fff;

    box-shadow:
        0 8px 24px
        rgba(13, 77, 151, 0.06);
}


.discovery-card {
    transition:
        transform 180ms ease,
        border-color 180ms ease,
        box-shadow 180ms ease;
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


/* =========================================================
   MEDIA
========================================================= */

.result-media {
    position: relative;

    aspect-ratio: 16 / 10;

    overflow: hidden;

    background: #e9eef4;
}


/*
|--------------------------------------------------------------------------
| Image + Video
|--------------------------------------------------------------------------
*/

.result-media img,
.result-media video {
    display: block;

    width: 100%;
    height: 100%;

    object-fit: cover;

    transition:
        transform 240ms ease;
}


/* =========================================================
   VIDEO
========================================================= */

.result-video {
    position: absolute;

    inset: 0;

    width: 100%;
    height: 100%;

    border: 0;

    background:
        #e9eef4;

    object-fit: cover;

    pointer-events: none;
}


/*
 * IMPORTANT:
 * نخفي أي native controls
 */

.result-video::-webkit-media-controls {
    display: none !important;
}


.result-video::-webkit-media-controls-panel {
    display: none !important;
}


/* =========================================================
   HOVER
========================================================= */

.discovery-card:hover .result-media img,
.discovery-card:hover .result-media video {
    transform: scale(1.025);
}


/* =========================================================
   EMPTY MEDIA
========================================================= */

.result-media-empty {
    display: grid;

    width: 100%;
    height: 100%;

    place-items: center;

    background: #e9eef4;

    color: #7d8995;
}


.result-media-empty.is-video-empty {
    background:
        linear-gradient(
            135deg,
            #e9eef4,
            #dce5ef
        );
}


.result-media-empty svg {
    width: 42px;
    height: 42px;
}


/* =========================================================
   TYPE BADGE
========================================================= */

.result-type-badge {
    position: absolute;

    top: 12px;

    inset-inline-start: 12px;

    z-index: 5;

    display: inline-flex;

    align-items: center;

    gap: 5px;

    border-radius: 5px;

    background: rgba(
        255,
        255,
        255,
        0.94
    );

    padding: 6px 9px;

    color: #0d4d97;

    font-size: 11px;
    font-weight: 900;

    box-shadow:
        0 4px 12px
        rgba(4, 17, 29, 0.12);
}


.result-type-badge.is-video {
    background:
        rgba(
            4,
            17,
            29,
            0.9
        );

    color: #fff;
}


.result-type-badge.is-image {
    color: #0f766e;
}


.result-type-badge svg {
    width: 13px;
    height: 13px;
}


/* =========================================================
   PLAY INDICATOR
========================================================= */

.video-play-indicator {
    position: absolute;

    top: 50%;
    left: 50%;

    z-index: 6;

    display: grid;

    width: 48px;
    height: 48px;

    place-items: center;

    transform:
        translate(-50%, -50%);

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.55
        );

    border-radius: 50%;

    background:
        rgba(
            4,
            17,
            29,
            0.72
        );

    color: #fff;

    pointer-events: none;

    backdrop-filter: blur(2px);
}


.video-play-indicator svg {
    width: 22px;
    height: 22px;
}


/* =========================================================
   BODY
========================================================= */

.result-card-body {
    display: flex;

    min-height: 190px;

    flex-direction: column;

    padding: 18px;
}


/* =========================================================
   META
========================================================= */

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


/* =========================================================
   TITLE
========================================================= */

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


/* =========================================================
   DESCRIPTION
========================================================= */

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


/* =========================================================
   FOOTER
========================================================= */

.result-card-footer {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    margin-top: auto;

    padding-top: 14px;

    border-top:
        1px solid
        var(--scemory-border-soft);

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


/* =========================================================
   SKELETON
========================================================= */

.result-skeleton .result-media,
.result-skeleton-body span {
    animation:
        result-pulse
        1.4s
        ease-in-out
        infinite;

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


.result-skeleton-body span:nth-child(1) {
    width: 45%;
}


.result-skeleton-body span:nth-child(3) {
    width: 72%;
}


/* =========================================================
   EMPTY RESULTS
========================================================= */

.discovery-empty {
    padding: 64px 20px;

    border-top:
        1px solid
        var(--scemory-border-soft);

    border-bottom:
        1px solid
        var(--scemory-border-soft);

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


/* =========================================================
   PAGINATION
========================================================= */

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

    border:
        1px solid
        var(--scemory-border);

    border-radius: 6px;

    background: #fff;

    color: var(--scemory-text);

    font-weight: 800;

    cursor: pointer;
}


.discovery-pagination button.is-active {
    border-color:
        var(--scemory-primary);

    background:
        var(--scemory-primary);

    color: #fff;
}


.discovery-pagination button:disabled {
    cursor: not-allowed;

    opacity: 0.45;
}


/* =========================================================
   MEDIA PREVIEW
========================================================= */

.media-preview {
    position: fixed;

    inset: 0;

    z-index: 9998;

    display: flex;

    align-items: center;
    justify-content: center;

    padding: clamp(56px, 7vw, 88px);

    background: rgba(0, 0, 0, 0.9);
}


.media-preview__media {
    display: block;

    max-width: 100%;
    max-height: 100%;

    border-radius: 8px;

    background: #000;

    object-fit: contain;

    box-shadow: 0 18px 60px rgba(0, 0, 0, 0.45);
}


.media-preview__control {
    position: absolute;

    z-index: 2;

    display: grid;

    width: 44px;
    height: 44px;

    place-items: center;

    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 50%;

    background: rgba(255, 255, 255, 0.14);

    color: #fff;

    font-size: 30px;
    line-height: 1;

    cursor: pointer;

    backdrop-filter: blur(5px);

    transition:
        background 180ms ease,
        transform 180ms ease;
}


.media-preview__control:hover {
    background: rgba(255, 255, 255, 0.25);
}


.media-preview__control:focus-visible {
    outline: 3px solid rgba(255, 255, 255, 0.75);
    outline-offset: 3px;
}


.media-preview__close {
    top: 16px;
    inset-inline-end: 16px;
}


.media-preview__previous,
.media-preview__next {
    top: 50%;

    transform: translateY(-50%);
}


.media-preview__previous:hover,
.media-preview__next:hover {
    transform: translateY(-50%) scale(1.06);
}


.media-preview__previous {
    left: 16px;
}


.media-preview__next {
    right: 16px;
}


.media-preview-enter-active,
.media-preview-leave-active {
    transition: opacity 180ms ease;
}


.media-preview-enter-from,
.media-preview-leave-to {
    opacity: 0;
}


/* =========================================================
   ANIMATION
========================================================= */

@keyframes result-pulse {
    50% {
        opacity: 0.55;
    }
}


/* =========================================================
   LARGE SCREEN
========================================================= */

@media (min-width: 1680px) {
    .discovery-grid {
        grid-template-columns:
            repeat(
                4,
                minmax(0, 1fr)
            );
    }
}


/* =========================================================
   TABLET
========================================================= */

@media
    (min-width: 700px)
    and
    (max-width: 1100px)
{
    .discovery-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(0, 1fr)
            );
    }
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 699px) {

    .media-preview {
        padding: 64px 12px;
    }


    .media-preview__control {
        width: 40px;
        height: 40px;
    }


    .media-preview__previous {
        left: 8px;
    }


    .media-preview__next {
        right: 8px;
    }

    .discovery-results-header {
        align-items: stretch;

        flex-direction: column;
    }


    .discovery-results-copy h2 {
        font-size: 24px;
    }


    .discovery-see-more {
        justify-content: center;
    }


    .discovery-tabs {
        display: grid;

        grid-template-columns:
            repeat(
                4,
                minmax(74px, 1fr)
            );

        width: 100%;
    }


    .discovery-tabs button {
        min-width: 74px;

        padding-inline: 8px;
    }


    .discovery-grid {
        grid-template-columns: 1fr;

        gap: 14px;
    }
}

</style>
