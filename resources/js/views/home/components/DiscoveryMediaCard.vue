<template>
    <article class="discovery-media-card">
        <!-- =========================
             MEDIA
        ========================== -->
        <div class="discovery-media-card__media">
            <!-- VIDEO -->
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

                <!-- PLAY / PAUSE -->
                <button
                    v-if="videoSource && !videoFailed"
                    type="button"
                    class="discovery-media-card__play"
                    :aria-label="
                        $t(
                            isPlaying
                                ? 'discovery.media.pauseVideo'
                                : 'discovery.media.playVideo'
                        )
                    "
                    @click.stop="toggleVideo"
                >
                    <PauseIcon v-if="isPlaying" aria-hidden="true" />
                    <PlayIcon v-else aria-hidden="true" />
                </button>
            </template>

            <!-- IMAGE -->
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

            <!-- subtle overlay -->
            <div class="discovery-media-card__media-overlay"></div>
        </div>

        <!-- =========================
             FOOTER
        ========================== -->
        <footer class="discovery-media-card__footer">
            <!-- PRICE ROW -->
            <div class="discovery-media-card__price">
                <span class="discovery-media-card__price-label">
                    {{ $t("discovery.media.price") }}
                </span>

                <strong class="discovery-media-card__price-value">
                    {{ formattedPrice }} $
                </strong>
            </div>

            <div class="discovery-media-card__divider"></div>

            <!-- ADD TO CART -->
            <button
                type="button"
                class="discovery-media-card__cart"
                :class="{
                    'is-added': isAdded,
                    'is-loading': isAdding
                }"
                :disabled="isAdding || isAdded"
                @click="addToCart"
            >
                <span
                    v-if="isAdding"
                    class="discovery-media-card__spinner"
                    aria-hidden="true"
                ></span>

                <CheckIcon
                    v-else-if="isAdded"
                    class="discovery-media-card__cart-icon"
                    aria-hidden="true"
                />

                <ShoppingCartIcon
                    v-else
                    class="discovery-media-card__cart-icon"
                    aria-hidden="true"
                />

                <span>
                    {{ cartButtonLabel }}
                </span>
            </button>
        </footer>
    </article>
</template>

<script setup>
import { computed, ref } from "vue";
import {
    CheckIcon,
    PauseIcon,
    PlayIcon,
    ShoppingCartIcon,
} from "@heroicons/vue/24/solid";
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

/* =========================
   MEDIA TYPE
========================= */

const isVideo = computed(() => {
    return props.result?.result_type === "video";
});

/* =========================
   VIDEO SOURCE
========================= */

const videoSource = computed(() => {
    if (!isVideo.value) return "";

    return (
        props.result?.video_url ||
        props.result?.media_url ||
        props.result?.file_url ||
        props.result?.url ||
        ""
    );
});

/* =========================
   THUMBNAIL
========================= */

const thumbnailSource = computed(() => {
    return (
        props.result?.thumbnail_url ||
        props.result?.image_url ||
        props.result?.media_url ||
        props.fallbackImage ||
        ""
    );
});

/* =========================
   PRICE
========================= */

const formattedPrice = computed(() => {
    const price = Number(props.result?.price);

    return Number.isFinite(price)
        ? price.toFixed(2)
        : "0.00";
});

/* =========================
   CART BUTTON TEXT
========================= */

const cartButtonLabel = computed(() => {
    if (isAdded.value) {
        return t("discovery.media.added");
    }

    if (isAdding.value) {
        return t("discovery.media.adding");
    }

    return t("discovery.media.addToCart");
});

/* =========================
   VIDEO
========================= */

const prepareVideoFrame = (event) => {
    const video = event?.target;

    if (!video || isPlaying.value) {
        return;
    }

    try {
        if (
            Number.isFinite(video.duration) &&
            video.duration > 0.15 &&
            video.currentTime < 0.05
        ) {
            video.currentTime = 0.1;
        }

        video.pause();
    } catch (error) {
        console.error(
            "Unable to prepare discovery video frame:",
            error
        );
    }
};

const toggleVideo = async () => {
    const video = videoRef.value;

    if (!video) {
        return;
    }

    try {
        if (video.paused) {
            await video.play();
            return;
        }

        video.pause();
    } catch (error) {
        console.error(
            "Unable to play discovery video:",
            error
        );
    }
};

/* =========================
   IMAGE ERROR
========================= */

const handleImageError = (event) => {
    const image = event?.target;

    if (!image) {
        return;
    }

    if (
        props.fallbackImage &&
        image.src !== props.fallbackImage
    ) {
        image.src = props.fallbackImage;
        return;
    }

    image.style.display = "none";
};

/* =========================
   ADD TO CART
========================= */

const addToCart = async () => {
    const mediaId =
        props.result?.media_id ||
        props.result?.id;

    if (
        !mediaId ||
        isAdding.value ||
        isAdded.value
    ) {
        return;
    }

    if (!localStorage.getItem("auth_token")) {
        showSafeToast(
            "error",
            t("event.comment_login_required"),
            t("cart.errors.addFailed")
        );

        return;
    }

    isAdding.value = true;

    try {
        await CartService.addToCart(mediaId);

        isAdded.value = true;

        showSafeToast(
            "success",
            t("cart.messages.added"),
            "Added to cart successfully."
        );

        window.dispatchEvent(
            new CustomEvent("cart-updated")
        );
    } catch (error) {
        console.error(
            "Unable to add discovery media to cart:",
            error
        );
    } finally {
        isAdding.value = false;
    }
};
</script>

<style scoped>
/* =====================================================
   CARD
===================================================== */

.discovery-media-card {
    position: relative;

    width: 100%;
    min-width: 0;

    overflow: hidden;

    border:
        1px solid
        rgba(13, 77, 151, 0.12);

    border-radius: 20px;

    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #ffffff 100%
        );

    box-shadow:
        0 8px 20px rgba(15, 23, 42, 0.04),
        0 18px 45px rgba(13, 77, 151, 0.06);

    transition:
        transform 0.32s cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 0.32s cubic-bezier(0.22, 1, 0.36, 1),
        border-color 0.32s ease;
}

.discovery-media-card:hover {
    transform: translateY(-6px);

    border-color:
        rgba(48, 168, 255, 0.28);

    box-shadow:
        0 14px 28px rgba(15, 23, 42, 0.06),
        0 26px 60px rgba(13, 77, 151, 0.14);
}


/* =====================================================
   MEDIA
===================================================== */

.discovery-media-card__media {
    position: relative;

    width: 100%;

    aspect-ratio: 16 / 10;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #e8eff6,
            #f4f8fc
        );
}


/* Image + Video */

.discovery-media-card__media img,
.discovery-media-card__media video {
    position: relative;

    z-index: 1;

    display: block;

    width: 100%;
    height: 100%;

    object-fit: cover;

    transform: scale(1);

    transition:
        transform 0.6s
        cubic-bezier(0.22, 1, 0.36, 1),
        filter 0.4s ease;
}


/* Hover media zoom */

.discovery-media-card:hover
.discovery-media-card__media img,
.discovery-media-card:hover
.discovery-media-card__media video {
    transform: scale(1.045);
}


/* Slight overlay */

.discovery-media-card__media-overlay {
    position: absolute;

    inset: 0;

    z-index: 2;

    pointer-events: none;

    background:
        linear-gradient(
            180deg,
            transparent 65%,
            rgba(4, 17, 29, 0.04) 100%
        );

    opacity: 0;

    transition:
        opacity 0.3s ease;
}

.discovery-media-card:hover
.discovery-media-card__media-overlay {
    opacity: 1;
}


/* Video */

.discovery-media-card__video {
    cursor: pointer;
}


/* Empty media */

.discovery-media-card__empty {
    width: 100%;
    height: 100%;

    background:
        radial-gradient(
            circle at 25% 28%,
            rgba(255, 255, 255, 0.9) 0 7%,
            transparent 7.5%
        ),
        linear-gradient(
            135deg,
            #dce5ee,
            #eef3f8
        );
}


/* =====================================================
   PLAY BUTTON
===================================================== */

.discovery-media-card__play {
    position: absolute;

    top: 50%;
    left: 50%;

    z-index: 5;

    display: flex;

    width: 62px;
    height: 62px;

    align-items: center;
    justify-content: center;

    transform:
        translate(-50%, -50%)
        scale(1);

    border:
        2px solid
        rgba(255, 255, 255, 0.85);

    border-radius: 50%;

    color: #ffffff;

    background:
        rgba(5, 21, 43, 0.76);

    cursor: pointer;

    box-shadow:
        0 10px 28px
        rgba(4, 17, 29, 0.28);

    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);

    transition:
        transform 0.25s
        cubic-bezier(0.22, 1, 0.36, 1),
        background 0.25s ease,
        box-shadow 0.25s ease;
}

.discovery-media-card__play:hover {
    transform:
        translate(-50%, -50%)
        scale(1.1);

    background:
        rgba(13, 77, 151, 0.9);

    box-shadow:
        0 14px 34px
        rgba(13, 77, 151, 0.35);
}

.discovery-media-card__play:active {
    transform:
        translate(-50%, -50%)
        scale(0.96);
}

.discovery-media-card__play svg {
    width: 27px;
    height: 27px;
}


/* =====================================================
   FOOTER
===================================================== */

.discovery-media-card__footer {
    position: relative;

    z-index: 3;

    display: flex;

    flex-direction: column;

    gap: 14px;

    padding:
        18px 18px 20px;

    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #fbfdff 100%
        );
}


/* =====================================================
   PRICE
===================================================== */

.discovery-media-card__price {
    display: flex;

    width: 100%;

    align-items: center;
    justify-content: space-between;

    gap: 16px;

    min-height: 36px;
}


/* Price label */

.discovery-media-card__price-label {
    color:
        #64748b;

    font-size: 14px;
    font-weight: 700;

    line-height: 1.2;
}


/* Price amount */

.discovery-media-card__price-value {
    color:
        #071c2d;

    font-size: 21px;
    font-weight: 900;

    line-height: 1;

    letter-spacing:
        -0.35px;

    white-space: nowrap;
}


/* Divider */

.discovery-media-card__divider {
    width: 100%;
    height: 1px;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(13, 77, 151, 0.14) 12%,
            rgba(13, 77, 151, 0.14) 88%,
            transparent
        );
}


/* =====================================================
   ADD TO CART
===================================================== */

.discovery-media-card__cart {
    position: relative;

    isolation: isolate;

    display: flex;

    width: 100%;

    min-height: 50px;

    align-items: center;
    justify-content: center;

    gap: 9px;

    overflow: hidden;

    padding:
        12px 18px;

    border:
        1px solid
        rgba(48, 168, 255, 0.42);

    border-radius: 13px;

    color: #ffffff;

    background:
        linear-gradient(
            110deg,
            #0d4d97 0%,
            #1677ff 48%,
            #30a8ff 100%
        );

    background-size:
        180% 180%;

    font-size: 14px;
    font-weight: 850;

    line-height: 1;

    cursor: pointer;

    box-shadow:
        0 9px 20px
        rgba(22, 119, 255, 0.20),
        0 4px 10px
        rgba(48, 168, 255, 0.13),
        inset 0 1px 0
        rgba(255, 255, 255, 0.25);

    transition:
        transform 0.25s
        cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 0.25s ease,
        border-color 0.25s ease,
        filter 0.25s ease,
        background-position 0.45s ease;
}


/* Shine layer */

.discovery-media-card__cart::before {
    content: "";

    position: absolute;

    top: 0;
    left: -120%;

    z-index: -1;

    width: 80%;
    height: 100%;

    transform: skewX(-22deg);

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255, 255, 255, 0.22),
            transparent
        );

    transition:
        left 0.55s ease;
}


/* Hover */

.discovery-media-card__cart:hover:not(:disabled) {
    transform:
        translateY(-2px);

    background-position:
        100% 50%;

    border-color:
        rgba(80, 191, 255, 0.75);

    box-shadow:
        0 14px 28px
        rgba(22, 119, 255, 0.28),
        0 7px 16px
        rgba(48, 168, 255, 0.18),
        inset 0 1px 0
        rgba(255, 255, 255, 0.30);

    filter:
        saturate(1.06)
        brightness(1.04);
}

.discovery-media-card__cart:hover:not(:disabled)::before {
    left: 140%;
}


/* Active click */

.discovery-media-card__cart:active:not(:disabled) {
    transform:
        translateY(0)
        scale(0.985);

    box-shadow:
        0 6px 14px
        rgba(22, 119, 255, 0.20);
}


/* Focus */

.discovery-media-card__cart:focus-visible {
    outline:
        3px solid
        rgba(48, 168, 255, 0.24);

    outline-offset: 3px;
}


/* Icons */

.discovery-media-card__cart-icon {
    width: 20px;
    height: 20px;

    flex: 0 0 auto;

    transition:
        transform 0.25s ease;
}

.discovery-media-card__cart:hover:not(:disabled)
.discovery-media-card__cart-icon {
    transform:
        translateY(-1px)
        scale(1.08);
}


/* =====================================================
   LOADING
===================================================== */

.discovery-media-card__cart.is-loading {
    cursor: wait;

    opacity: 0.9;
}


/* Spinner */

.discovery-media-card__spinner {
    width: 18px;
    height: 18px;

    flex: 0 0 auto;

    border:
        2px solid
        rgba(255, 255, 255, 0.34);

    border-top-color:
        #ffffff;

    border-radius: 50%;

    animation:
        discovery-cart-spin
        0.72s linear infinite;
}

@keyframes discovery-cart-spin {
    to {
        transform:
            rotate(360deg);
    }
}


/* =====================================================
   ADDED STATE
===================================================== */

.discovery-media-card__cart.is-added {
    cursor: default;

    border-color:
        rgba(22, 163, 74, 0.32);

    background:
        linear-gradient(
            110deg,
            #15803d,
            #16a34a,
            #22c55e
        );

    box-shadow:
        0 9px 22px
        rgba(22, 163, 74, 0.20),
        inset 0 1px 0
        rgba(255, 255, 255, 0.25);
}


/* =====================================================
   DISABLED
===================================================== */

.discovery-media-card__cart:disabled {
    pointer-events: none;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 767.98px) {
    .discovery-media-card {
        border-radius: 17px;
    }

    .discovery-media-card__footer {
        gap: 12px;

        padding:
            15px 15px 17px;
    }

    .discovery-media-card__price-label {
        font-size: 13px;
    }

    .discovery-media-card__price-value {
        font-size: 19px;
    }

    .discovery-media-card__cart {
        min-height: 47px;

        border-radius: 12px;

        font-size: 13px;
    }

    .discovery-media-card__play {
        width: 54px;
        height: 54px;
    }

    .discovery-media-card__play svg {
        width: 24px;
        height: 24px;
    }
}


@media (max-width: 480px) {
    .discovery-media-card {
        border-radius: 15px;
    }

    .discovery-media-card__footer {
        padding:
            14px;
    }

    .discovery-media-card__price-value {
        font-size: 18px;
    }

    .discovery-media-card__cart {
        min-height: 46px;

        padding:
            11px 14px;
    }
}


/* =====================================================
   REDUCED MOTION
===================================================== */

@media (prefers-reduced-motion: reduce) {
    .discovery-media-card,
    .discovery-media-card__media img,
    .discovery-media-card__media video,
    .discovery-media-card__play,
    .discovery-media-card__cart,
    .discovery-media-card__cart::before,
    .discovery-media-card__cart-icon {
        transition: none;
    }
}
</style>
