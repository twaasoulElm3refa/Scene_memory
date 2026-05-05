<template>
    <section class="space-y-5">
        <div class="flex items-center justify-between gap-3">
            <RouterLink
                :to="{ name: 'creator-events', params: { lang: currentLang } }"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500"
            >
                <span aria-hidden="true">&lt;-</span>
                <span>Back to Events</span>
            </RouterLink>
        </div>

        <div v-if="loading" class="space-y-4">
            <div class="h-8 w-48 animate-pulse rounded bg-slate-200"></div>
            <div class="h-72 animate-pulse rounded-2xl bg-slate-200"></div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div v-for="n in 4" :key="n" class="h-24 animate-pulse rounded-xl bg-slate-200"></div>
            </div>
        </div>

        <div
            v-else-if="error"
            class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-700"
            role="alert"
        >
            <p class="font-semibold">Could not load event details.</p>
            <p class="mt-1 text-sm">{{ error }}</p>
            <button
                type="button"
                class="mt-4 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
                @click="loadEvent"
            >
                Retry
            </button>
        </div>

        <div
            v-else-if="!event"
            class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
        >
            <p class="text-lg font-semibold text-slate-700">Event not found</p>
            <p class="mt-2 text-sm text-slate-500">This event may be unavailable or removed.</p>
        </div>

        <article v-else class="space-y-5">
            <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h1 class="text-2xl font-semibold text-slate-900">
                        {{ event.title || event.name || "Untitled event" }}
                    </h1>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                        {{ event.status || "N/A" }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    Slug: {{ event.slug || slug || "N/A" }}
                </p>
            </header>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="aspect-[16/8] bg-slate-100">
                    <img
                        v-if="mainImage"
                        :src="mainImage"
                        :alt="event.title || event.name || 'Event image'"
                        class="h-full w-full object-cover"
                    />
                    <div v-else class="flex h-full items-center justify-center text-sm text-slate-500">
                        No image available
                    </div>
                </div>
            </div>

            <div
                v-if="galleryImages.length"
                class="grid grid-cols-2 gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4"
            >
                <img
                    v-for="(img, idx) in galleryImages"
                    :key="img + idx"
                    :src="img"
                    alt="Event gallery"
                    class="h-24 w-full rounded-lg object-cover"
                />
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 text-lg font-semibold text-slate-900">Details</h2>
                    <dl class="space-y-2 text-sm text-slate-700">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="font-medium text-slate-500">Date</dt>
                            <dd>{{ formatDate(event.start_date || event.date || event.event_date) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="font-medium text-slate-500">Category</dt>
                            <dd>{{ event.category?.name || event.category_name || "N/A" }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="font-medium text-slate-500">Sub Category</dt>
                            <dd>{{ event.sub_category?.name || event.sub_category_name || "N/A" }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="font-medium text-slate-500">Created</dt>
                            <dd>{{ formatDate(event.created_at) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="font-medium text-slate-500">Updated</dt>
                            <dd>{{ formatDate(event.updated_at) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 text-lg font-semibold text-slate-900">Location</h2>
                    <dl class="space-y-2 text-sm text-slate-700">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="font-medium text-slate-500">City</dt>
                            <dd>{{ event.city?.name || event.city_name || event.city || "N/A" }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="font-medium text-slate-500">Country</dt>
                            <dd>{{ event.country?.name || event.country_name || event.country || "N/A" }}</dd>
                        </div>
                    </dl>
                </section>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-lg font-semibold text-slate-900">Description</h2>
                <p class="whitespace-pre-line text-sm leading-7 text-slate-700">
                    {{ event.description || "No description available." }}
                </p>
            </section>
        </article>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { getCreatorEvent } from "@/services/CreatorService/CreatorService";

const route = useRoute();
const loading = ref(true);
const error = ref("");
const event = ref(null);

const slug = computed(() => route.params.slug);
const currentLang = computed(() => String(route.params.lang || "en").toLowerCase());

const normalizeEvent = (payload) => {
    if (!payload) return null;
    if (payload?.data?.data && !Array.isArray(payload.data.data)) return payload.data.data;
    if (payload?.data && !Array.isArray(payload.data)) return payload.data;
    if (payload?.event) return payload.event;
    return payload;
};

const resolveImage = (value) => {
    if (!value) return "";
    if (String(value).startsWith("http")) return value;
    return `/storage/${String(value).replace(/^\/+/, "")}`;
};

const mainImage = computed(() => {
    const raw =
        event.value?.image ||
        event.value?.thumbnail ||
        event.value?.cover_image ||
        event.value?.first_image?.url ||
        event.value?.first_image?.image ||
        event.value?.images?.[0]?.url ||
        event.value?.images?.[0]?.image;
    return resolveImage(raw);
});

const galleryImages = computed(() => {
    const images = event.value?.images;
    if (!Array.isArray(images)) return [];
    return images
        .map((item) => resolveImage(item?.url || item?.image || item))
        .filter(Boolean)
        .slice(0, 8);
});

const formatDate = (value) => {
    if (!value) return "N/A";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString(undefined, {
        year: "numeric",
        month: "short",
        day: "2-digit",
    });
};

const loadEvent = async () => {
    if (!slug.value) {
        event.value = null;
        error.value = "Missing event slug";
        loading.value = false;
        return;
    }

    loading.value = true;
    error.value = "";

    try {
        const response = await getCreatorEvent(slug.value);
        event.value = normalizeEvent(response?.data);
    } catch (err) {
        error.value = err?.response?.data?.message || err?.message || "Unexpected error";
        event.value = null;
    } finally {
        loading.value = false;
    }
};

watch(() => route.params.slug, loadEvent);
onMounted(loadEvent);
</script>
