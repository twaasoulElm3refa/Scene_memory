<template>
    <article class="discovery-media-card">
        <div class="discovery-media-card__media">
            <template v-if="isVideo">
                <video
                    v-if="videoSource && !videoFailed"
                    ref="videoRef"
                    class="discovery-media-card__video"
                    :src="videoSource"
                    :poster="thumbnailSource || undefined"
                    muted
                    playsinline
                    preload="metadata"
                    @click="toggleVideo"
                    @loadedmetadata="prepareVideoFrame"
                    @loadeddata="prepareVideoFrame"
                    @playing="isPlaying = true"
                    @pause="isPlaying = false"
                    @ended="isPlaying = false"
                    @error="videoFailed = true"
                ></video>

                <img
                    v-else-if="thumbnailSource"
                    :src="thumbnailSource"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    @error="handleImageError"
                />

                <div
                    v-else
                    class="discovery-media-card__empty"
                    aria-hidden="true"
                ></div>

                <button
                    v-if="videoSource && !videoFailed"
                    type="button"
                    class="discovery-media-card__play"
                    :aria-label="$t(isPlaying ? 'discovery.media.pauseVideo' : 'discovery.media.playVideo')"
                    @click="toggleVideo"
                >
                    <PauseIcon v-if="isPlaying" aria-hidden="true" />
                    <PlayIcon v-else aria-hidden="true" />
                </button>
            </template>

            <template v-else>
                <img
                    v-if="thumbnailSource"
                    :src="thumbnailSource"
                    alt=""
                    :loading="index === 0 ? 'eager' : 'lazy'"
                    :fetchpriority="index === 0 ? 'high' : 'auto'"
                    decoding="async"
                    @error="handleImageError"
                />

                <div
                    v-else
                    class="discovery-media-card__empty"
                    aria-hidden="true"
                ></div>
            </template>
        </div>

        <footer class="discovery-media-card__footer">
            <div class="discovery-media-card__price">
                <span>{{ $t("discovery.media.price") }}</span>
                <strong>{{ formattedPrice }} $</strong>
            </div>

            <button
                type="button"
                class="discovery-media-card__cart"
                :class="{ 'is-added': isAdded }"
                :disabled="isAdding || isAdded"
                @click="addToCart"
            >
                {{ cartButtonLabel }}
            </button>
        </footer>
    </article>
</template>

<script setup>
import { computed, ref } from "vue";
import { PauseIcon, PlayIcon } from "@heroicons/vue/24/solid";
import { useI18n } from "vue-i18n";

import { CartService } from "@/services/CartService/CartService";
import { showSafeToast } from "@/services/ApiClient";

const props = defineProps({
    result: {
        type: Object,
        required: true,
    },
    index: {
        type: Number,
        default: 0,
    },
    fallbackImage: {
        type: String,
        default: "",
    },
});

const { t } = useI18n();
const videoRef = ref(null);
const videoFailed = ref(false);
const isPlaying = ref(false);
const isAdding = ref(false);
const isAdded = ref(false);

const isVideo = computed(() => props.result?.result_type === "video");

const videoSource = computed(() => {
    if (!isVideo.value) return "";

    return props.result?.video_url || props.result?.media_url || props.result?.file_url || props.result?.url || "";
});

const thumbnailSource = computed(() => {
    return props.result?.thumbnail_url || props.result?.image_url || props.result?.media_url || props.fallbackImage || "";
});

const formattedPrice = computed(() => {
    const price = Number(props.result?.price);

    return Number.isFinite(price) ? price.toFixed(2) : "0.00";
});

const cartButtonLabel = computed(() => {
    if (isAdded.value) return t("discovery.media.added");
    if (isAdding.value) return t("discovery.media.adding");

    return t("discovery.media.addToCart");
});

const prepareVideoFrame = (event) => {
    const video = event?.target;

    if (!video || isPlaying.value) return;

    try {
        if (Number.isFinite(video.duration) && video.duration > 0.15 && video.currentTime < 0.05) {
            video.currentTime = 0.1;
        }

        video.pause();
    } catch (error) {
        console.error("Unable to prepare discovery video frame:", error);
    }
};

const toggleVideo = async () => {
    const video = videoRef.value;

    if (!video) return;

    try {
        if (video.paused) {
            await video.play();
            return;
        }

        video.pause();
    } catch (error) {
        console.error("Unable to play discovery video:", error);
    }
};

const handleImageError = (event) => {
    const image = event?.target;

    if (!image) return;

    if (props.fallbackImage && image.src !== props.fallbackImage) {
        image.src = props.fallbackImage;
        return;
    }

    image.style.display = "none";
};

const addToCart = async () => {
    const mediaId = props.result?.media_id || props.result?.id;

    if (!mediaId || isAdding.value || isAdded.value) return;

    if (!localStorage.getItem("auth_token")) {
        showSafeToast("error", t("event.comment_login_required"), t("cart.errors.addFailed"));
        return;
    }

    isAdding.value = true;

    try {
        await CartService.addToCart(mediaId);
        isAdded.value = true;
        showSafeToast("success", t("cart.messages.added"), "Added to cart successfully.");
        window.dispatchEvent(new CustomEvent("cart-updated"));
    } catch (error) {
        console.error("Unable to add discovery media to cart:", error);
    } finally {
        isAdding.value = false;
    }
};
</script>

<style scoped>
.discovery-media-card {
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--scemory-border-soft);
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(13, 77, 151, 0.06);
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.discovery-media-card:hover {
    transform: translateY(-2px);
    border-color: var(--scemory-border);
    box-shadow: var(--scemory-shadow-hover);
}

.discovery-media-card__media {
    position: relative;
    aspect-ratio: 16 / 10;
    overflow: hidden;
    background: #e9eef4;
}

.discovery-media-card__media img,
.discovery-media-card__media video {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 240ms ease;
}

.discovery-media-card:hover .discovery-media-card__media img,
.discovery-media-card:hover .discovery-media-card__media video {
    transform: scale(1.025);
}

.discovery-media-card__video {
    cursor: pointer;
}

.discovery-media-card__empty {
    width: 100%;
    height: 100%;
    background: #e9eef4;
}

.discovery-media-card__play {
    position: absolute;
    top: 50%;
    left: 50%;
    z-index: 2;
    display: grid;
    width: 48px;
    height: 48px;
    place-items: center;
    transform: translate(-50%, -50%);
    border: 1px solid rgba(255, 255, 255, 0.55);
    border-radius: 50%;
    background: rgba(4, 17, 29, 0.72);
    color: #fff;
    cursor: pointer;
    backdrop-filter: blur(2px);
}

.discovery-media-card__play svg {
    width: 22px;
    height: 22px;
}

.discovery-media-card__footer {
    display: flex;
    min-height: 68px;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 12px 14px;
}

.discovery-media-card__price {
    display: grid;
    gap: 2px;
}

.discovery-media-card__price span {
    color: var(--scemory-muted);
    font-size: 11px;
    font-weight: 700;
}

.discovery-media-card__price strong {
    color: var(--scemory-heading);
    font-size: 16px;
    font-weight: 900;
    white-space: nowrap;
}

.discovery-media-card__cart {
    min-height: 38px;
    border: 1px solid var(--scemory-primary);
    border-radius: 6px;
    background: var(--scemory-primary);
    padding: 8px 13px;
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
    cursor: pointer;
    transition: opacity 180ms ease, background-color 180ms ease, border-color 180ms ease;
}

.discovery-media-card__cart:hover:not(:disabled) {
    background: #0b4384;
    border-color: #0b4384;
}

.discovery-media-card__cart:disabled {
    cursor: wait;
    opacity: 0.72;
}

.discovery-media-card__cart.is-added {
    border-color: #15803d;
    background: #15803d;
    cursor: default;
}
</style>
