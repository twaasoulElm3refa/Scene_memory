```vue
<template>
    <section class="mx-auto w-full max-w-7xl space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <RouterLink :to="{ name: 'creator-events', params: { lang: currentLang } }"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                <span aria-hidden="true">&larr;</span>
                <span>Back to Events</span>
            </RouterLink>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                Creator Event Details
            </p>
        </header>

        <section v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-10 shadow-sm" aria-live="polite">
            <div class="flex items-center justify-center gap-3 text-slate-600">
                <svg class="h-6 w-6 animate-spin text-sky-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25" />
                    <path d="M4 12a8 8 0 018-8v8H4z" fill="currentColor" class="opacity-75" />
                </svg>
                <span class="text-sm font-medium">Loading event details...</span>
            </div>
        </section>

        <section v-else-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-700" role="alert">
            <p class="text-base font-semibold">Failed to load event details.</p>
            <p class="mt-1 text-sm">{{ error }}</p>
            <button type="button"
                class="mt-4 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
                @click="fetchEvent">
                Retry
            </button>
        </section>

        <section v-else-if="!event" class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
            <p class="text-lg font-semibold text-slate-700">Event not found</p>
            <p class="mt-2 text-sm text-slate-500">No event was returned for this slug.</p>
        </section>

        <article v-else class="space-y-6">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="grid grid-cols-1 gap-0 lg:grid-cols-5">
                    <div class="h-full lg:col-span-2">
                        <div class="aspect-[16/10] bg-slate-100 lg:h-full lg:aspect-auto">
                            <img v-if="heroImage" :src="heroImage" :alt="translatedTitle"
                                class="h-full w-full object-cover" />
                            <div v-else
                                class="flex h-full min-h-[260px] items-center justify-center text-sm font-medium text-slate-500">
                                No image available
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 p-6 lg:col-span-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold"
                                :class="isActive(event?.is_active) ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                                {{ isActive(event?.is_active) ? 'Active' : 'Inactive' }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                {{ event?.is_historical ? 'Historical' : 'Regular Event' }}
                            </span>
                            <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">
                                Translation Locale: {{ translationLocale }}
                            </span>
                        </div>

                        <h1 class="text-2xl font-bold leading-tight text-slate-900">
                            {{ translatedTitle }}
                        </h1>

                        <p class="whitespace-pre-line text-sm leading-7 text-slate-700">
                            {{ translatedDescription }}
                        </p>

                        <dl class="grid grid-cols-1 gap-3 text-sm text-slate-700 sm:grid-cols-2">
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slug</dt>
                                <dd class="mt-1 break-all font-medium">{{ event?.slug || 'N/A' }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Time</dt>
                                <dd class="mt-1 font-medium">{{ event?.time || 'N/A' }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Start Date</dt>
                                <dd class="mt-1 font-medium">{{ formatDate(event?.start_date) }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">End Date</dt>
                                <dd class="mt-1 font-medium">{{ formatDate(event?.end_date) }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Created At</dt>
                                <dd class="mt-1 font-medium">{{ formatDate(event?.created_at) }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Updated At</dt>
                                <dd class="mt-1 font-medium">{{ formatDate(event?.updated_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Original Content</h2>
                <div class="mt-4 space-y-3 text-sm text-slate-700">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Original Title</p>
                        <p class="mt-1 font-medium text-slate-900">{{ event?.title || 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Original Description</p>
                        <p class="mt-1 whitespace-pre-line leading-7">{{ event?.description || 'N/A' }}</p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Location</h2>
                        <dl class="mt-4 grid grid-cols-1 gap-3 text-sm text-slate-700 sm:grid-cols-2">
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">City Name</dt>
                                <dd class="mt-1 font-medium">{{ event?.city?.name || 'N/A' }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">City Slug</dt>
                                <dd class="mt-1 font-medium">{{ event?.city?.slug || 'N/A' }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Latitude
                                    (lattitude)</dt>
                                <dd class="mt-1 font-medium">{{ event?.lattitude || 'N/A' }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Longitude
                                    (langitude)</dt>
                                <dd class="mt-1 font-medium">{{ event?.langitude || 'N/A' }}</dd>
                            </div>
                        </dl>

                        <a v-if="hasMapCoordinates" :href="googleMapsUrl" target="_blank" rel="noopener noreferrer"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-700">
                            Open in Google Maps
                        </a>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Media Gallery</h2>

                        <div v-if="images.length" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <article v-for="media in images" :key="media?.id || media?.preview_url || media?.full_url"
                                class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <div class="aspect-[16/10] bg-slate-100">
                                    <!-- Video -->
                                    <video v-if="isVideo(media)"
                                        :src="storageUrl(media?.full_url || media?.preview_url)"
                                        class="h-full w-full object-cover" controls preload="metadata">
                                        Your browser does not support the video tag.
                                    </video>

                                    <!-- Image -->
                                    <img v-else-if="storageUrl(media?.preview_url || media?.full_url)"
                                        :src="storageUrl(media?.preview_url || media?.full_url)"
                                        alt="Event media preview" class="h-full w-full object-cover" />

                                    <!-- Empty -->
                                    <div v-else class="flex h-full items-center justify-center text-sm text-slate-500">
                                        No media preview
                                    </div>
                                </div>

                                <div class="space-y-3 p-4 text-sm text-slate-700">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="isVideo(media)
                                            ? 'bg-purple-100 text-purple-700'
                                            : 'bg-sky-100 text-sky-700'">
                                            {{ isVideo(media) ? 'Video' : 'Image' }}
                                        </span>

                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="isActive(media?.is_active)
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-rose-100 text-rose-700'">
                                            {{ isActive(media?.is_active) ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>

                                    <a v-if="storageUrl(media?.full_url || media?.preview_url)"
                                        :href="storageUrl(media?.full_url || media?.preview_url)" target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700">
                                        {{ isVideo(media) ? 'Open Full Video' : 'Open Full Image' }}
                                    </a>

                                    <dl class="grid grid-cols-2 gap-2">
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Dimensions
                                            </dt>
                                            <dd class="mt-1">
                                                {{ media?.width || 'N/A' }} x {{ media?.height || 'N/A' }}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Size
                                            </dt>
                                            <dd class="mt-1">
                                                {{ formatBytes(media?.size) }}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Licence
                                            </dt>
                                            <dd class="mt-1">
                                                {{ media?.licence_type || 'N/A' }}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Price
                                            </dt>
                                            <dd class="mt-1">
                                                {{ media?.price ?? 'N/A' }}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Points
                                            </dt>
                                            <dd class="mt-1">
                                                {{ media?.points ?? 0 }}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Type
                                            </dt>
                                            <dd class="mt-1">
                                                {{ media?.type || detectMediaType(media) }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </article>
                        </div>

                        <p v-else
                            class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                            No media available for this event.
                        </p>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Comments</h2>

                        <div v-if="comments.length" class="mt-4 space-y-3">
                            <article v-for="comment in comments"
                                :key="comment?.id || `${comment?.user_id}-${comment?.created_at}`"
                                class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="whitespace-pre-line text-sm text-slate-800">{{ comment?.comment || 'No comment text' }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span>Created: {{ formatDate(comment?.created_at) }}</span>
                                </div>
                            </article>
                        </div>

                        <p v-else
                            class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                            No comments yet.
                        </p>
                    </section>
                </div>

                <div class="space-y-6">
                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Category</h2>
                        <dl class="mt-4 space-y-3 text-sm text-slate-700">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Name</dt>
                                <dd class="mt-1 font-medium">{{ event?.sub_categorey?.name || 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slug</dt>
                                <dd class="mt-1 font-medium break-all">{{ event?.sub_categorey?.slug || 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    sub_categorey_id</dt>
                                <dd class="mt-1 font-medium">{{ event?.sub_categorey_id ?? 'N/A' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Creator</h2>
                        <dl class="mt-4 space-y-3 text-sm text-slate-700">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Name</dt>
                                <dd class="mt-1 font-medium">{{ event?.user?.name || 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt>
                                <dd class="mt-1 font-medium break-all">{{ event?.user?.email || 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role</dt>
                                <dd class="mt-1 font-medium">{{ event?.user?.role || 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last Login</dt>
                                <dd class="mt-1 font-medium">{{ formatDate(event?.user?.last_login_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Points</dt>
                                <dd class="mt-1 font-medium">{{ event?.user?.points ?? 'N/A' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Stats</h2>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm text-slate-700">
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Images</dt>
                                <dd class="mt-1 text-lg font-bold text-slate-900">{{ images.length }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Comments</dt>
                                <dd class="mt-1 text-lg font-bold text-slate-900">{{ comments.length }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Likes</dt>
                                <dd class="mt-1 text-lg font-bold text-slate-900">{{ likes.length }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Views
                                </dt>
                                <dd class="mt-1 text-lg font-bold text-slate-900">{{ totalViews }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">First Image Meta</p>
                            <p class="mt-1">License: {{ firstImageMeta?.licence_type || 'N/A' }}</p>
                            <p class="mt-1">Price: {{ firstImageMeta?.price ?? 'N/A' }}</p>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Likes</h2>
                        <p class="mt-2 text-sm text-slate-600">Total Likes: {{ likes.length }}</p>

                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Views</h2>
                        <p class="mt-2 text-sm text-slate-600">Total Views: {{ totalViews }}</p>
                    </section>
                </div>
            </section>
        </article>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { getCreatorEvent } from "@/services/CreatorService/CreatorService";

const route = useRoute();

const loading = ref(false);
const error = ref("");
const event = ref(null);

const slug = computed(() => String(route.params.slug || ""));
const currentLang = computed(() =>
    String(route.params.lang || localStorage.getItem("language") || localStorage.getItem("lang") || "en").toLowerCase()
);

const normalizeEvent = (payload) => {
    if (!payload) return null;
    if (payload?.data?.data && !Array.isArray(payload.data.data)) return payload.data.data;
    if (payload?.data && !Array.isArray(payload.data)) return payload.data;
    if (payload?.event && !Array.isArray(payload.event)) return payload.event;
    return payload;
};

const storageUrl = (path) => {
    if (!path) return "";
    const value = String(path);
    if (value.startsWith("http://") || value.startsWith("https://")) return value;
    if (value.startsWith("/storage/")) return value;
    return `/storage/${value.replace(/^\/+/, "")}`;
};

const getMediaPath = (media) => {
    return String(media?.full_url || media?.preview_url || "").toLowerCase();
};

const isVideo = (media) => {
    const type = String(media?.type || "").toLowerCase();
    const path = getMediaPath(media);

    return (
        type.includes("video") ||
        path.endsWith(".mp4") ||
        path.endsWith(".webm") ||
        path.endsWith(".ogg") ||
        path.endsWith(".mov") ||
        path.endsWith(".m4v") ||
        path.endsWith(".avi") ||
        path.endsWith(".mkv")
    );
};

const detectMediaType = (media) => {
    return isVideo(media) ? "video" : "image";
};


const formatDate = (value) => {
    if (!value) return "N/A";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat(currentLang.value || "en", {
        year: "numeric",
        month: "short",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    }).format(date);
};

const formatBytes = (value) => {
    if (value === null || value === undefined || value === "") return "N/A";
    const bytes = Number(value);
    if (!Number.isFinite(bytes) || bytes < 0) return "N/A";
    if (bytes < 1024) return `${bytes} B`;

    const kb = bytes / 1024;
    if (kb < 1024) return `${kb.toFixed(2)} KB`;

    const mb = kb / 1024;
    return `${mb.toFixed(2)} MB`;
};

const isActive = (value) => value === true || value === 1 || value === "1";

const translation = computed(() => {
    const t = event.value?.translation;
    return t && typeof t === "object" ? t : null;
});

const translationLocale = computed(() => translation.value?.locale || "N/A");

const translatedTitle = computed(() => translation.value?.title || event.value?.title || "N/A");
const translatedDescription = computed(() => translation.value?.description || event.value?.description || "N/A");

const images = computed(() => (Array.isArray(event.value?.images) ? event.value.images : []));
const comments = computed(() => (Array.isArray(event.value?.comments) ? event.value.comments : []));
const likes = computed(() => (Array.isArray(event.value?.likes) ? event.value.likes : []));
const views = computed(() => (Array.isArray(event.value?.views) ? event.value.views : []));

const totalViews = computed(() => {
    return views.value.reduce((sum, item) => sum + Number(item?.count || 0), 0);
});

const likeUserIds = computed(() => {
    return likes.value
        .map((item) => item?.user_id ?? item?.id)
        .filter((id) => id !== null && id !== undefined);
});

const firstImageMeta = computed(() => images.value[0] || null);

const heroImage = computed(() => {
    return (
        storageUrl(event.value?.first_image?.preview_url) ||
        storageUrl(event.value?.first_image?.full_url) ||
        storageUrl(event.value?.image) ||
        storageUrl(images.value[0]?.preview_url) ||
        storageUrl(images.value[0]?.full_url) ||
        ""
    );
});

const hasMapCoordinates = computed(() => {
    return Boolean(event.value?.lattitude && event.value?.langitude);
});

const googleMapsUrl = computed(() => {
    if (!hasMapCoordinates.value) return "";
    return `https://www.google.com/maps?q=${encodeURIComponent(event.value?.lattitude)},${encodeURIComponent(event.value?.langitude)}`;
});

const fetchEvent = async () => {
    if (!slug.value) {
        event.value = null;
        error.value = "Missing event slug.";
        return;
    }

    loading.value = true;
    error.value = "";

    try {
        const response = await getCreatorEvent(slug.value);
        event.value = normalizeEvent(response?.data);

        if (!event.value) {
            error.value = "No event data returned from API.";
        }
    } catch (err) {
        event.value = null;
        error.value = err?.response?.data?.message || err?.message || "Failed to load event details.";
    } finally {
        loading.value = false;
    }
};

watch(() => route.params.slug, fetchEvent);
onMounted(fetchEvent);
</script>
```
