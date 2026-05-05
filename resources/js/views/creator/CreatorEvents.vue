<template>
    <section class="creator-events-page space-y-6">
        <!-- Header -->
        <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">
                        Creator Dashboard
                    </p>

                    <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 md:text-4xl">
                        My Events
                    </h1>

                    <p class="mt-2 text-sm text-slate-500">
                        Manage your translated events, track views, likes, and open event details.
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-2xl bg-slate-950 px-5 py-4 text-white shadow-sm">
                        <p class="text-xs text-slate-300">Events</p>
                        <p class="mt-1 text-3xl font-black">{{ events.length }}</p>
                    </div>

                    <div class="rounded-2xl bg-white px-5 py-4 text-slate-950 shadow-sm ring-1 ring-slate-200">
                        <p class="text-xs text-slate-400">Views</p>
                        <p class="mt-1 text-3xl font-black">{{ totalAllViews }}</p>
                    </div>

                    <div class="rounded-2xl bg-white px-5 py-4 text-slate-950 shadow-sm ring-1 ring-slate-200">
                        <p class="text-xs text-slate-400">Likes</p>
                        <p class="mt-1 text-3xl font-black">{{ totalAllLikes }}</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Loading -->
        <div v-if="loading" class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="n in 6"
                :key="n"
                class="h-96 animate-pulse rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="h-40 rounded-2xl bg-slate-100"></div>
                <div class="mt-5 h-4 w-2/3 rounded bg-slate-100"></div>
                <div class="mt-3 h-4 w-1/2 rounded bg-slate-100"></div>
                <div class="mt-3 h-4 w-full rounded bg-slate-100"></div>
                <div class="mt-6 h-10 rounded-xl bg-slate-100"></div>
            </div>
        </div>

        <!-- Error -->
        <div
            v-else-if="error"
            class="rounded-3xl border border-rose-200 bg-rose-50 p-6 text-rose-700 shadow-sm"
            role="alert"
        >
            <p class="text-lg font-bold">Could not load creator events.</p>
            <p class="mt-1 text-sm">{{ error }}</p>

            <button
                type="button"
                class="mt-5 rounded-xl bg-rose-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
                @click="loadEvents"
            >
                Retry
            </button>
        </div>

        <!-- Empty -->
        <div
            v-else-if="filteredEvents.length === 0"
            class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center shadow-sm"
        >
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-2xl">
                📅
            </div>

            <p class="mt-5 text-xl font-black text-slate-800">No events found</p>
            <p class="mt-2 text-sm text-slate-500">
                {{ events.length ? "Try changing your search keywords." : "Your creator dashboard has no events yet." }}
            </p>
        </div>

        <!-- Events Grid -->
        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="event in filteredEvents"
                :key="event.id || event.slug"
                class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl"
            >
                <!-- Cover -->
                <div
                    class="relative flex aspect-[16/10] items-center justify-center overflow-hidden"
                    :style="{ background: coverColor(event) }"
                >
                    <img
                        v-if="resolveImage(event)"
                        :src="resolveImage(event)"
                        :alt="translatedTitle(event)"
                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    />

                    <div v-else class="px-6 text-center">
                        <p class="text-sm font-bold uppercase tracking-[0.18em] text-white/70">
                            {{ translatedCategory(event) }}
                        </p>

                        <h2 class="mt-4 line-clamp-2 text-xl font-black text-white">
                            {{ translatedTitle(event) }}
                        </h2>
                    </div>

                    <span class="absolute end-4 top-4 rounded-xl bg-white px-4 py-2 text-xs font-black text-slate-950 shadow">
                        {{ formatDate(event.start_date) }}
                    </span>

                    <span
                        v-if="translatedLocale(event)"
                        class="absolute start-4 top-4 rounded-xl bg-slate-950/80 px-3 py-2 text-xs font-black uppercase text-white shadow"
                    >
                        {{ translatedLocale(event) }}
                    </span>
                </div>

                <!-- Body -->
                <div class="space-y-4 p-5">
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <p class="truncate text-xs font-bold uppercase tracking-[0.16em] text-slate-400">
                                {{ translatedCategory(event) }}
                            </p>

                            <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(event.status)">
                                {{ event.status || "Active" }}
                            </span>
                        </div>

                        <h3 class="mt-3 line-clamp-2 text-xl font-black leading-tight text-slate-950">
                            {{ translatedTitle(event) }}
                        </h3>

                        <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-500">
                            {{ translatedDescription(event) }}
                        </p>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <dt class="text-xs font-semibold text-slate-400">City</dt>
                            <dd class="mt-1 truncate font-bold text-slate-700">
                                {{ translatedCity(event) }}
                            </dd>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-3">
                            <dt class="text-xs font-semibold text-slate-400">Date</dt>
                            <dd class="mt-1 font-bold text-slate-700">
                                {{ formatDate(event.start_date) }}
                            </dd>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-3">
                            <dt class="text-xs font-semibold text-slate-400">Views</dt>
                            <dd class="mt-1 font-bold text-slate-700">
                                {{ totalViews(event) }}
                            </dd>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-3">
                            <dt class="text-xs font-semibold text-slate-400">Likes</dt>
                            <dd class="mt-1 font-bold text-slate-700">
                                {{ totalLikes(event) }}
                            </dd>
                        </div>
                    </dl>

                    <div class="rounded-2xl bg-slate-50 p-3 text-sm">
                        <p class="text-xs font-semibold text-slate-400">Slug</p>
                        <p class="mt-1 truncate font-bold text-slate-700">
                            {{ event.slug || "N/A" }}
                        </p>
                    </div>

                    <RouterLink
                        v-if="event.slug"
                        :to="{
                            name: 'creator-event-show',
                            params: { lang: currentLang, slug: event.slug },
                        }"
                        class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500"
                    >
                        View Details
                    </RouterLink>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { getCreatorEvents } from "@/services/CreatorService/CreatorService";

const route = useRoute();

const loading = ref(true);
const error = ref("");
const events = ref([]);
const search = ref("");
const sortBy = ref("newest");

const currentLang = computed(() => {
    return String(route.params.lang || localStorage.getItem("lang") || "en").toLowerCase();
});

const normalizeEvents = (payload) => {
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload?.data?.data)) return payload.data.data;
    if (Array.isArray(payload?.events)) return payload.events;
    if (Array.isArray(payload?.data?.events)) return payload.data.events;

    return [];
};

/**
 * Everything displayed here comes from translation objects.
 * Event: event.translation.title / event.translation.description
 * Category: event.sub_categorey.translation.name
 * City: event.city.translation.name
 */
const translatedTitle = (event) => {
    return event?.translation?.title || "N/A";
};

const translatedDescription = (event) => {
    return event?.translation?.description || "N/A";
};

const translatedCategory = (event) => {
    return event?.sub_categorey?.translation?.name || "N/A";
};

const translatedCity = (event) => {
    return event?.city?.translation?.name || "N/A";
};

const translatedLocale = (event) => {
    return event?.translation?.locale || "";
};

const totalViews = (event) => {
    if (!Array.isArray(event?.views)) return 0;

    return event.views.reduce((total, view) => {
        const count = Number(view?.count || 0);
        return total + count;
    }, 0);
};

const totalLikes = (event) => {
    if (!Array.isArray(event?.likes)) return 0;
    return event.likes.length;
};

const totalAllViews = computed(() => {
    return events.value.reduce((total, event) => total + totalViews(event), 0);
});

const totalAllLikes = computed(() => {
    return events.value.reduce((total, event) => total + totalLikes(event), 0);
});

const resolveImage = (event) => {
    const raw =
        event?.first_image?.url ||
        event?.first_image?.image ||
        event?.image ||
        event?.thumbnail ||
        event?.cover_image ||
        event?.images?.[0]?.url ||
        event?.images?.[0]?.image;

    if (!raw) return "";

    const image = String(raw);

    if (image.startsWith("http://") || image.startsWith("https://")) {
        return image;
    }

    if (image.startsWith("/storage/")) {
        return image;
    }

    return `/storage/${image.replace(/^\/+/, "")}`;
};

const formatDate = (value) => {
    if (!value) return "N/A";

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleDateString(undefined, {
        year: "numeric",
        month: "short",
        day: "2-digit",
    });
};

const statusClass = (status) => {
    const key = String(status || "active").toLowerCase();

    if (["active", "published", "approved"].includes(key)) {
        return "bg-emerald-100 text-emerald-700";
    }

    if (["pending", "draft"].includes(key)) {
        return "bg-amber-100 text-amber-700";
    }

    if (["rejected", "inactive", "cancelled", "canceled"].includes(key)) {
        return "bg-rose-100 text-rose-700";
    }

    return "bg-slate-100 text-slate-700";
};

const coverColor = (event) => {
    const colors = [
        "#1f3f68",
        "#5f3b2a",
        "#2f6845",
        "#432f63",
        "#285460",
        "#6b4b1f",
        "#314155",
    ];

    const id = Number(event?.id || 0);

    return colors[id % colors.length];
};

const filteredEvents = computed(() => {
    const keyword = search.value.toLowerCase();

    let list = [...events.value];

    if (keyword) {
        list = list.filter((event) => {
            const haystack = [
                translatedTitle(event),
                translatedDescription(event),
                translatedCategory(event),
                translatedCity(event),
                translatedLocale(event),
                event?.slug,
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();

            return haystack.includes(keyword);
        });
    }

    return list.sort((a, b) => {
        if (sortBy.value === "oldest") {
            return new Date(a?.start_date || a?.created_at || 0) - new Date(b?.start_date || b?.created_at || 0);
        }

        if (sortBy.value === "title") {
            return translatedTitle(a).localeCompare(translatedTitle(b));
        }

        if (sortBy.value === "views") {
            return totalViews(b) - totalViews(a);
        }

        if (sortBy.value === "likes") {
            return totalLikes(b) - totalLikes(a);
        }

        return new Date(b?.start_date || b?.created_at || 0) - new Date(a?.start_date || a?.created_at || 0);
    });
});

const loadEvents = async () => {
    loading.value = true;
    error.value = "";

    try {
        const response = await getCreatorEvents();
        events.value = normalizeEvents(response?.data);
    } catch (err) {
        error.value =
            err?.response?.data?.message ||
            err?.message ||
            "Unexpected error while loading events.";

        events.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(loadEvents);
</script>

<style scoped>
.creator-events-page {
    width: 100%;
}

.line-clamp-2,
.line-clamp-3 {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-2 {
    -webkit-line-clamp: 2;
}

.line-clamp-3 {
    -webkit-line-clamp: 3;
}
</style>
