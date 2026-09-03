<template>
    <article class="discovery-media-card">
        <!-- =========================
             MEDIA
        ========================== -->
        <div
            class="discovery-media-card__media"
            :class="{ 'is-previewable': previewEnabled && hasMediaSource }"
            @click="handleMediaClick"
        >
            <!-- VIDEO -->
            <template v-if="isVideoResult">
                <video
                    v-if="resolvedVideoUrl"
                    ref="videoRef"
                    class="discovery-media-card__video"
                    muted
                    playsinline
                    preload="metadata"
                    @loadedmetadata="prepareVideoFrame"
                    @loadeddata="prepareVideoFrame"
                    @playing="isPlaying = true"
                    @pause="isPlaying = false"
                    @ended="isPlaying = false"
                    @error="handleVideoError"
                >
                    <source :src="resolvedVideoUrl" />
                </video>

                <div
                    v-else
                    class="discovery-media-card__empty"
                    aria-hidden="true"
                ></div>

                <!-- PLAY / PAUSE -->
                <button
                    v-if="resolvedVideoUrl"
                    type="button"
                    class="discovery-media-card__play"
                    :aria-label="
                        $t(
                            isPlaying
                                ? 'discovery.media.pauseVideo'
                                : 'discovery.media.playVideo'
                        )
                    "
                    @click.stop="handleMediaClick"
                >
                    <PauseIcon
                        v-if="isPlaying"
                        aria-hidden="true"
                    />

                    <PlayIcon
                        v-else
                        aria-hidden="true"
                    />
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
        </div>

        <!-- =========================
             FOOTER
        ========================== -->
        <footer class="discovery-media-card__footer">
            <!-- PRICE -->
            <div class="discovery-media-card__price">
                <span>
                    {{ $t("discovery.media.price") }}
                </span>

                <strong>
                    {{ formattedPrice }} $
                </strong>
            </div>

            <!-- DIVIDER -->
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
                <!-- LOADING -->
                <span
                    v-if="isAdding"
                    class="discovery-media-card__spinner"
                    aria-hidden="true"
                ></span>

                <!-- ADDED -->
                <CheckIcon
                    v-else-if="isAdded"
                    class="discovery-media-card__cart-icon"
                    aria-hidden="true"
                />

                <!-- CART -->
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


/* =====================================================
   PROPS
===================================================== */

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

    previewEnabled: {
        type: Boolean,
        default: false,
    },
});


const emit = defineEmits(["preview"]);


/* =====================================================
   I18N
===================================================== */

const { t } = useI18n();


/* =====================================================
   STATE
===================================================== */

const videoRef = ref(null);

const isPlaying = ref(false);

const isAdding = ref(false);

const isAdded = ref(false);


/* =====================================================
   MEDIA TYPE
===================================================== */

const isVideoResult = computed(() => {
    return props.result?.result_type === "video";
});


/* =====================================================
   MEDIA PATH / URL
===================================================== */

const getMediaRawPath = (mediaOrPath) => {
    if (!mediaOrPath) return "";

    if (typeof mediaOrPath === "string") {
        return mediaOrPath;
    }

    return (
        mediaOrPath.image_url ||
        mediaOrPath.full_url ||
        mediaOrPath.url ||
        mediaOrPath.preview_url ||
        ""
    );
};


const getStorageUrl = (mediaOrPath) => {
    const rawPath = getMediaRawPath(mediaOrPath);

    if (!rawPath || typeof rawPath !== "string") {
        return "";
    }

    const path = rawPath.replace(/\\/g, "/").trim();

    if (path.startsWith("http://") || path.startsWith("https://")) {
        try {
            const url = new URL(path);

            if (
                url.pathname.startsWith("/storage/") ||
                url.pathname.startsWith("/uploads/")
            ) {
                return `${url.pathname}${url.search}`;
            }
        } catch {
            return path;
        }

        return path;
    }

    if (path.startsWith("/storage/")) {
        return path;
    }

    if (path.startsWith("storage/")) {
        return `/${path}`;
    }

    if (path.startsWith("public/")) {
        return `/storage/${path.replace(/^public\//, "")}`;
    }

    if (path.startsWith("/uploads/")) {
        return path;
    }

    if (path.startsWith("uploads/")) {
        return `/${path}`;
    }

    return `/storage/${path.replace(/^\/+/, "")}`;
};


const isVideo = (path) => {
    const rawPath = getMediaRawPath(path);

    if (!rawPath || typeof rawPath !== "string") {
        return false;
    }

    return /\.(mp4|webm|ogg|mov|m4v)(\?.*)?$/i.test(rawPath);
};


const resolvedVideoPath = computed(() => {
    if (!isVideoResult.value) return "";

    return (
        props.result?.full_url ||
        props.result?.video_url ||
        props.result?.media_url ||
        props.result?.file_url ||
        props.result?.url ||
        ""
    );
});


const resolvedVideoUrl = computed(() => {
    if (!isVideo(resolvedVideoPath.value)) {
        return "";
    }

    return getStorageUrl(resolvedVideoPath.value);
});


/* =====================================================
   THUMBNAIL
===================================================== */

const thumbnailSource = computed(() => {
    const imagePath =
        props.result?.thumbnail_url ||
        props.result?.image_url ||
        props.result?.media_url ||
        props.fallbackImage ||
        "";

    return imagePath
        ? getStorageUrl(imagePath)
        : "";
});


const hasMediaSource = computed(() => {
    return isVideoResult.value
        ? Boolean(resolvedVideoUrl.value)
        : Boolean(thumbnailSource.value);
});


const handleVideoError = (event) => {
    const video = event?.target;

    console.error(
        "Unable to load discovery video:",
        {
            id: props.result?.id,
            path: resolvedVideoPath.value,
            url: resolvedVideoUrl.value,
            error: video?.error || null,
        }
    );
};


/* =====================================================
   PRICE
===================================================== */

const formattedPrice = computed(() => {
    const price = Number(
        props.result?.price
    );

    return Number.isFinite(price)
        ? price.toFixed(2)
        : "0.00";
});


/* =====================================================
   CART BUTTON LABEL
===================================================== */

const cartButtonLabel = computed(() => {
    if (isAdded.value) {
        return t(
            "discovery.media.added"
        );
    }

    if (isAdding.value) {
        return t(
            "discovery.media.adding"
        );
    }

    return t(
        "discovery.media.addToCart"
    );
});


/* =====================================================
   PREPARE VIDEO FRAME
   نفس الكود القديم
===================================================== */

const prepareVideoFrame = (event) => {
    const video = event?.target;

    if (
        !video ||
        isPlaying.value
    ) {
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


/* =====================================================
   PLAY / PAUSE VIDEO
   نفس الكود القديم
===================================================== */

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


const handleMediaClick = () => {
    if (props.previewEnabled) {
        if (hasMediaSource.value) {
            emit("preview", props.result);
        }

        return;
    }

    if (isVideoResult.value) {
        toggleVideo();
    }
};


/* =====================================================
   IMAGE FALLBACK
===================================================== */

const handleImageError = (
    event
) => {
    const image = event?.target;

    if (!image) {
        return;
    }

    if (
        props.fallbackImage &&
        image.src !== props.fallbackImage
    ) {
        image.src =
            props.fallbackImage;

        return;
    }

    image.style.display =
        "none";
};


/* =====================================================
   ADD TO CART
===================================================== */

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

    if (
        !localStorage.getItem(
            "auth_token"
        )
    ) {
        showSafeToast(
            "error",
            t(
                "event.comment_login_required"
            ),
            t(
                "cart.errors.addFailed"
            )
        );

        return;
    }

    isAdding.value = true;

    try {
        await CartService.addToCart(
            mediaId
        );

        isAdded.value = true;

        showSafeToast(
            "success",
            t(
                "cart.messages.added"
            ),
            "Added to cart successfully."
        );

        window.dispatchEvent(
            new CustomEvent(
                "cart-updated"
            )
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

    border: 1px solid
        rgba(
            13,
            77,
            151,
            0.12
        );

    border-radius: 20px;

    background: #ffffff;

    box-shadow:
        0 8px 20px
            rgba(
                15,
                23,
                42,
                0.04
            ),
        0 18px 42px
            rgba(
                13,
                77,
                151,
                0.08
            );

    transition:
        transform
            0.3s
            cubic-bezier(
                0.22,
                1,
                0.36,
                1
            ),
        border-color
            0.3s ease,
        box-shadow
            0.3s ease;
}


.discovery-media-card:hover {
    transform:
        translateY(-5px);

    border-color:
        rgba(
            48,
            168,
            255,
            0.28
        );

    box-shadow:
        0 14px 28px
            rgba(
                15,
                23,
                42,
                0.06
            ),
        0 24px 55px
            rgba(
                13,
                77,
                151,
                0.15
            );
}


/* =====================================================
   MEDIA
   نفس طريقة الكود القديم
===================================================== */

.discovery-media-card__media {
    position: relative;

    aspect-ratio: 16 / 10;

    overflow: hidden;

    background: #e9eef4;
}


.discovery-media-card__media.is-previewable {
    cursor: zoom-in;
}


.discovery-media-card__media img,
.discovery-media-card__media video {
    display: block;

    width: 100%;
    height: 100%;

    object-fit: cover;

    transition:
        transform
            0.4s
            cubic-bezier(
                0.22,
                1,
                0.36,
                1
            );
}


.discovery-media-card:hover
.discovery-media-card__media img,

.discovery-media-card:hover
.discovery-media-card__media video {
    transform:
        scale(1.035);
}


/* =====================================================
   VIDEO
===================================================== */

.discovery-media-card__video {
    cursor: pointer;
}


/* =====================================================
   EMPTY
===================================================== */

.discovery-media-card__empty {
    width: 100%;
    height: 100%;

    background: #e9eef4;
}


/* =====================================================
   PLAY BUTTON
===================================================== */

.discovery-media-card__play {
    position: absolute;

    top: 50%;
    left: 50%;

    z-index: 4;

    display: grid;

    width: 58px;
    height: 58px;

    place-items: center;

    transform:
        translate(
            -50%,
            -50%
        );

    border: 2px solid
        rgba(
            255,
            255,
            255,
            0.78
        );

    border-radius: 50%;

    background:
        rgba(
            4,
            17,
            29,
            0.75
        );

    color: #ffffff;

    cursor: pointer;

    box-shadow:
        0 8px 24px
            rgba(
                4,
                17,
                29,
                0.3
            );

    backdrop-filter:
        blur(7px);

    -webkit-backdrop-filter:
        blur(7px);

    transition:
        transform
            0.25s ease,
        background
            0.25s ease,
        box-shadow
            0.25s ease;
}


.discovery-media-card__play:hover {
    transform:
        translate(
            -50%,
            -50%
        )
        scale(1.08);

    background:
        rgba(
            13,
            77,
            151,
            0.9
        );

    box-shadow:
        0 12px 30px
            rgba(
                13,
                77,
                151,
                0.35
            );
}


.discovery-media-card__play:active {
    transform:
        translate(
            -50%,
            -50%
        )
        scale(0.96);
}


.discovery-media-card__play svg {
    width: 25px;
    height: 25px;
}


/* =====================================================
   FOOTER
===================================================== */

.discovery-media-card__footer {
    display: flex;

    flex-direction: column;

    gap: 13px;

    padding:
        17px 18px
        19px;

    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #fbfdff 100%
        );
}


/* =====================================================
   PRICE ROW
===================================================== */

.discovery-media-card__price {
    display: flex;

    width: 100%;

    align-items: center;
    justify-content:
        space-between;

    gap: 16px;

    min-height: 34px;
}


.discovery-media-card__price span {
    color: #64748b;

    font-size: 13px;

    font-weight: 700;

    line-height: 1;
}


.discovery-media-card__price strong {
    color: #071c2d;

    font-size: 20px;

    font-weight: 900;

    line-height: 1;

    letter-spacing:
        -0.3px;

    white-space: nowrap;
}


/* =====================================================
   DIVIDER
===================================================== */

.discovery-media-card__divider {
    width: 100%;

    height: 1px;

    background:
        linear-gradient(
            90deg,
            transparent 0%,
            rgba(
                    13,
                    77,
                    151,
                    0.13
                )
                12%,
            rgba(
                    13,
                    77,
                    151,
                    0.13
                )
                88%,
            transparent 100%
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

    border: 1px solid
        rgba(
            48,
            168,
            255,
            0.48
        );

    border-radius: 13px;

    color: #ffffff;

    background:
        linear-gradient(
            110deg,
            #0d4d97 0%,
            #1267d2 30%,
            #1677ff 58%,
            #30a8ff 100%
        );

    background-size:
        190% 190%;

    background-position:
        0% 50%;

    font-size: 14px;

    font-weight: 850;

    line-height: 1;

    cursor: pointer;

    box-shadow:
        0 9px 20px
            rgba(
                22,
                119,
                255,
                0.22
            ),
        0 4px 10px
            rgba(
                48,
                168,
                255,
                0.15
            ),
        inset
            0 1px 0
            rgba(
                255,
                255,
                255,
                0.28
            );

    transition:
        transform
            0.25s
            cubic-bezier(
                0.22,
                1,
                0.36,
                1
            ),
        box-shadow
            0.25s ease,
        border-color
            0.25s ease,
        background-position
            0.45s ease,
        filter
            0.25s ease;
}


/* =====================================================
   BUTTON SHINE
===================================================== */

.discovery-media-card__cart::before {
    content: "";

    position: absolute;

    top: 0;

    left: -120%;

    z-index: -1;

    width: 80%;

    height: 100%;

    transform:
        skewX(-22deg);

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(
                255,
                255,
                255,
                0.22
            ),
            transparent
        );

    transition:
        left
            0.55s ease;
}


/* =====================================================
   CART HOVER
===================================================== */

.discovery-media-card__cart:hover:not(
        :disabled
    ) {
    transform:
        translateY(-2px);

    background-position:
        100% 50%;

    border-color:
        rgba(
            83,
            195,
            255,
            0.85
        );

    box-shadow:
        0 14px 28px
            rgba(
                22,
                119,
                255,
                0.28
            ),
        0 7px 18px
            rgba(
                48,
                168,
                255,
                0.2
            ),
        inset
            0 1px 0
            rgba(
                255,
                255,
                255,
                0.32
            );

    filter:
        brightness(1.04)
        saturate(1.08);
}


.discovery-media-card__cart:hover:not(
        :disabled
    )::before {
    left: 140%;
}


/* =====================================================
   CART ACTIVE
===================================================== */

.discovery-media-card__cart:active:not(
        :disabled
    ) {
    transform:
        translateY(0)
        scale(0.985);

    box-shadow:
        0 6px 14px
            rgba(
                22,
                119,
                255,
                0.2
            );
}


/* =====================================================
   FOCUS
===================================================== */

.discovery-media-card__cart:focus-visible {
    outline: 3px solid
        rgba(
            48,
            168,
            255,
            0.24
        );

    outline-offset: 3px;
}


/* =====================================================
   CART ICON
===================================================== */

.discovery-media-card__cart-icon {
    width: 20px;

    height: 20px;

    flex: 0 0 auto;

    transition:
        transform
            0.25s ease;
}


.discovery-media-card__cart:hover:not(
        :disabled
    )
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

    opacity: 0.92;
}


.discovery-media-card__spinner {
    width: 18px;

    height: 18px;

    flex: 0 0 auto;

    border: 2px solid
        rgba(
            255,
            255,
            255,
            0.35
        );

    border-top-color:
        #ffffff;

    border-radius: 50%;

    animation:
        discovery-cart-spin
            0.7s
            linear
            infinite;
}


@keyframes discovery-cart-spin {
    to {
        transform:
            rotate(360deg);
    }
}


/* =====================================================
   ADDED
===================================================== */

.discovery-media-card__cart.is-added {
    cursor: default;

    border-color:
        rgba(
            22,
            163,
            74,
            0.35
        );

    background:
        linear-gradient(
            110deg,
            #15803d,
            #16a34a,
            #22c55e
        );

    box-shadow:
        0 9px 22px
            rgba(
                22,
                163,
                74,
                0.2
            ),
        inset
            0 1px 0
            rgba(
                255,
                255,
                255,
                0.25
            );
}


/* =====================================================
   DISABLED
===================================================== */

.discovery-media-card__cart:disabled {
    pointer-events: none;
}


/* =====================================================
   TABLET / MOBILE
===================================================== */

@media (
    max-width: 767.98px
) {
    .discovery-media-card {
        border-radius: 17px;
    }

    .discovery-media-card__footer {
        gap: 12px;

        padding:
            15px 15px
            17px;
    }

    .discovery-media-card__price span {
        font-size: 12px;
    }

    .discovery-media-card__price strong {
        font-size: 19px;
    }

    .discovery-media-card__cart {
        min-height: 47px;

        border-radius: 12px;

        font-size: 13px;
    }

    .discovery-media-card__play {
        width: 52px;

        height: 52px;
    }

    .discovery-media-card__play svg {
        width: 23px;

        height: 23px;
    }
}


/* =====================================================
   SMALL MOBILE
===================================================== */

@media (
    max-width: 480px
) {
    .discovery-media-card {
        border-radius: 15px;
    }

    .discovery-media-card__footer {
        padding: 14px;
    }

    .discovery-media-card__price strong {
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

@media (
    prefers-reduced-motion:
        reduce
) {
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
