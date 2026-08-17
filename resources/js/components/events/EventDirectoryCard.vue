<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import {
    applyEventImageFallback,
    formatEventDate,
    getEventImageUrl,
} from "@/services/EventService/eventMedia";

const props = defineProps({
    event: { type: Object, required: true },
    locale: { type: String, required: true },
    mode: { type: String, required: true },
});

const { t } = useI18n();

const title = computed(() => props.event?.translation?.title || props.event?.title || t("events.no_title"));
const city = computed(() =>
    props.event?.city?.translation?.name ||
    props.event?.city?.name ||
    t("events.directory.unknownLocation")
);
const category = computed(() =>
    props.event?.sub_categorey?.translation?.name ||
    props.event?.sub_categorey?.name ||
    t("events.directory.uncategorized")
);
const detailRoute = computed(() => ({
    name: "single_event",
    params: { lang: props.locale, slug: props.event?.slug },
}));
const formattedDate = computed(() => formatEventDate(props.event?.start_date, props.locale));
</script>

<template>
    <article class="event-directory-card">
        <RouterLink
            :to="detailRoute"
            class="event-directory-card__image-link"
            :aria-label="title"
        >
            <img
                class="event-directory-card__image"
                :src="getEventImageUrl(event)"
                :alt="title"
                loading="lazy"
                width="640"
                height="360"
                @error="applyEventImageFallback"
            />
            <span class="event-directory-card__date">{{ formattedDate }}</span>
            <span class="event-directory-card__badge">
                {{ $t(mode === "historical" ? "events.directory.historicalBadge" : "events.directory.eventBadge") }}
            </span>
        </RouterLink>

        <div class="event-directory-card__body">
            <div class="event-directory-card__location">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z" />
                </svg>
                <span>{{ city }}</span>
            </div>

            <h2 class="event-directory-card__title">
                <RouterLink :to="detailRoute">{{ title }}</RouterLink>
            </h2>

            <div class="event-directory-card__footer">
                <span class="event-directory-card__category">{{ category }}</span>
                <RouterLink :to="detailRoute" class="event-directory-card__action">
                    {{ $t("events.directory.viewEvent") }}
                    <span class="event-directory-card__arrow" aria-hidden="true">→</span>
                </RouterLink>
            </div>
        </div>
    </article>
</template>

<style scoped>
.event-directory-card {
    display: flex;
    min-width: 0;
    height: 100%;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #dce8f5;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(13, 77, 151, 0.06);
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.event-directory-card:hover {
    transform: translateY(-3px);
    border-color: #b9d5f2;
    box-shadow: 0 15px 34px rgba(13, 77, 151, 0.11);
}

.event-directory-card__image-link {
    position: relative;
    display: block;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: #eef6ff;
}

.event-directory-card__image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 240ms ease;
}

.event-directory-card:hover .event-directory-card__image {
    transform: scale(1.025);
}

.event-directory-card__date,
.event-directory-card__badge {
    position: absolute;
    top: 14px;
    z-index: 1;
    border: 1px solid rgba(255, 255, 255, 0.72);
    border-radius: 999px;
    backdrop-filter: blur(10px);
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1;
}

.event-directory-card__date {
    inset-inline-start: 14px;
    padding: 9px 11px;
    background: rgba(255, 255, 255, 0.92);
    color: #06142a;
}

.event-directory-card__badge {
    inset-inline-end: 14px;
    padding: 8px 10px;
    background: rgba(13, 77, 151, 0.9);
    color: #fff;
}

.event-directory-card__body {
    display: flex;
    flex: 1;
    flex-direction: column;
    padding: 18px;
}

.event-directory-card__location {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 7px;
    color: #64748b;
    font-size: 0.82rem;
}

.event-directory-card__location svg {
    width: 16px;
    height: 16px;
    flex: 0 0 auto;
    fill: #1677ff;
}

.event-directory-card__location span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.event-directory-card__title {
    min-height: 3.15rem;
    margin: 10px 0 16px;
    color: #06142a;
    font-size: 1.08rem;
    font-weight: 750;
    line-height: 1.45;
}

.event-directory-card__title a {
    display: -webkit-box;
    overflow: hidden;
    color: inherit;
    text-decoration: none;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.event-directory-card__footer {
    display: flex;
    margin-top: auto;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-top: 1px solid #edf2f7;
    padding-top: 14px;
}

.event-directory-card__category {
    min-width: 0;
    overflow: hidden;
    border-radius: 999px;
    padding: 6px 9px;
    background: #f1f6fb;
    color: #49627d;
    font-size: 0.75rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.event-directory-card__action {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 6px;
    border-radius: 8px;
    color: #0d4d97;
    font-size: 0.82rem;
    font-weight: 750;
    text-decoration: none;
}

.event-directory-card__action:hover {
    color: #1677ff;
}

.event-directory-card a:focus-visible {
    outline: 3px solid rgba(22, 119, 255, 0.3);
    outline-offset: 3px;
}

:global([dir="rtl"]) .event-directory-card__arrow {
    transform: scaleX(-1);
}

@media (prefers-reduced-motion: reduce) {
    .event-directory-card,
    .event-directory-card__image {
        transition: none;
    }
}
</style>
