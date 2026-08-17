<script setup>
import debounce from "lodash/debounce";
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { CategoryService } from "@/services/CategoryService/CategoryService";
import { EventService } from "@/services/EventService/EventService";
import { normalizePaginatedResponse } from "@/services/EventService/eventSearchHelpers";
import { LocationService } from "@/services/LocationService/LocationService";
import EventDirectoryCard from "./EventDirectoryCard.vue";
import EventsDirectoryFilters from "./EventsDirectoryFilters.vue";
import EventsDirectoryPagination from "./EventsDirectoryPagination.vue";

const props = defineProps({
    mode: {
        type: String,
        required: true,
        validator: (value) => ["historical", "normal"].includes(value),
    },
});

const { locale, t } = useI18n();
const rtlLocales = new Set(["ar", "fa", "ur"]);

const events = ref([]);
const countries = ref([]);
const cities = ref([]);
const categories = ref([]);
const subCategories = ref([]);
const loading = ref(true);
const error = ref(false);
const mobileFiltersOpen = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const totalEvents = ref(0);
const perPage = ref(8);
const resultFrom = ref(null);
const resultTo = ref(null);
const filters = reactive({
    search: "",
    countryId: "",
    cityId: "",
    categoryId: "",
    subCategoryId: "",
    fromDate: "",
    toDate: "",
    sort: "newest",
    eventType: "all",
});

let activeRequestId = 0;
let cityOptionsRequestId = 0;
let subCategoryOptionsRequestId = 0;
let mounted = false;
let previousBodyOverflow = "";

const isHistorical = computed(() => props.mode === "historical");
const pageDirection = computed(() => (rtlLocales.has(String(locale.value).toLowerCase()) ? "rtl" : "ltr"));
const createRoute = computed(() =>
    `/${locale.value}/add_event${isHistorical.value ? "/historical" : ""}`
);
const titleKey = computed(() =>
    isHistorical.value ? "events.directory.historicalTitle" : "events.directory.eventsTitle"
);
const descriptionKey = computed(() =>
    isHistorical.value ? "events.directory.historicalDescription" : "events.directory.eventsDescription"
);
const emptyTitleKey = computed(() =>
    isHistorical.value ? "events.directory.historicalEmptyTitle" : "events.directory.emptyTitle"
);
const formattedTotal = computed(() => {
    try {
        return new Intl.NumberFormat(locale.value).format(totalEvents.value);
    } catch {
        return String(totalEvents.value);
    }
});
const resultSummary = computed(() => {
    if (!totalEvents.value || resultFrom.value === null || resultTo.value === null) {
        return t("events.directory.results", { count: formattedTotal.value });
    }

    return t("events.directory.showing", {
        from: resultFrom.value,
        to: resultTo.value,
        total: formattedTotal.value,
    });
});
const visiblePages = computed(() => {
    const total = lastPage.value;
    const current = currentPage.value;
    if (total <= 7) return Array.from({ length: total }, (_, index) => index + 1);

    const pages = [1];
    if (current > 4) pages.push("…");

    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    for (let page = start; page <= end; page += 1) pages.push(page);

    if (current < total - 3) pages.push("…");
    pages.push(total);
    return pages;
});

function requestFilters() {
    return {
        q: filters.search.trim() || undefined,
        country_id: filters.countryId || undefined,
        city_id: filters.cityId || undefined,
        category_id: filters.categoryId || undefined,
        sub_category_id: filters.subCategoryId || undefined,
        from: filters.fromDate || undefined,
        to: filters.toDate || undefined,
        sort: filters.sort,
    };
}

async function fetchEvents(page = 1) {
    const requestId = ++activeRequestId;
    loading.value = true;
    error.value = false;

    try {
        const response = isHistorical.value
            ? await EventService.getHistorical(page, requestFilters())
            : await EventService.getAll(page, filters.eventType, requestFilters());

        if (requestId !== activeRequestId) return;
        if (response?.data?.status && response.data.status !== "success") {
            throw new Error("Events request failed");
        }

        const pagination = normalizePaginatedResponse(response, 8);
        events.value = pagination.events;
        currentPage.value = pagination.currentPage;
        lastPage.value = pagination.lastPage;
        totalEvents.value = pagination.total;
        perPage.value = pagination.perPage;
        resultFrom.value = pagination.from;
        resultTo.value = pagination.to;
    } catch {
        if (requestId !== activeRequestId) return;
        events.value = [];
        error.value = true;
    } finally {
        if (requestId === activeRequestId) loading.value = false;
    }
}

async function loadFilterOptions() {
    const [loadedCountries, loadedCategories] = await Promise.all([
        LocationService.getAllCountries(),
        CategoryService.getAllCategories(),
    ]);

    countries.value = Array.isArray(loadedCountries) ? loadedCountries : [];
    categories.value = Array.isArray(loadedCategories) ? loadedCategories : [];
}

async function loadCities(countryId) {
    const requestId = ++cityOptionsRequestId;
    const loadedCities = countryId ? await LocationService.getCitiesByCountry(countryId) : [];
    if (requestId === cityOptionsRequestId) {
        cities.value = loadedCities;
    }
}

async function loadSubCategories(categoryId) {
    const requestId = ++subCategoryOptionsRequestId;
    if (!categoryId) {
        subCategories.value = [];
        return;
    }

    try {
        const response = await CategoryService.getSubCategoriesByCategory(categoryId);
        if (requestId === subCategoryOptionsRequestId) {
            subCategories.value = response?.data?.data || [];
        }
    } catch {
        if (requestId === subCategoryOptionsRequestId) {
            subCategories.value = [];
        }
    }
}

const fetchDebouncedSearch = debounce(() => {
    currentPage.value = 1;
    fetchEvents(1);
}, 400);

function updateFilter({ key, value }) {
    filters[key] = value;
    currentPage.value = 1;

    if (key === "countryId") {
        filters.cityId = "";
        loadCities(value);
    }

    if (key === "categoryId") {
        filters.subCategoryId = "";
        loadSubCategories(value);
    }

    if (key === "search") {
        fetchDebouncedSearch();
        return;
    }

    fetchDebouncedSearch.cancel();
    fetchEvents(1);
}

function clearFilters() {
    fetchDebouncedSearch.cancel();
    Object.assign(filters, {
        search: "",
        countryId: "",
        cityId: "",
        categoryId: "",
        subCategoryId: "",
        fromDate: "",
        toDate: "",
        sort: "newest",
        eventType: "all",
    });
    cityOptionsRequestId += 1;
    subCategoryOptionsRequestId += 1;
    cities.value = [];
    subCategories.value = [];
    currentPage.value = 1;
    fetchEvents(1);
}

function changePage(page) {
    const nextPage = Number(page);
    if (
        !Number.isInteger(nextPage) ||
        nextPage < 1 ||
        nextPage > lastPage.value ||
        nextPage === currentPage.value
    ) return;

    fetchEvents(nextPage);
    window.scrollTo({ top: 0, behavior: "smooth" });
}

function closeFilters() {
    mobileFiltersOpen.value = false;
}

function onKeydown(event) {
    if (event.key === "Escape") closeFilters();
}

watch(locale, async (nextLocale, previousLocale) => {
    if (!mounted || nextLocale === previousLocale) return;
    currentPage.value = 1;
    await Promise.all([
        loadFilterOptions(),
        loadCities(filters.countryId),
        loadSubCategories(filters.categoryId),
        fetchEvents(1),
    ]);
});

watch(mobileFiltersOpen, (open) => {
    if (open) {
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
    } else {
        document.body.style.overflow = previousBodyOverflow;
    }
});

onMounted(async () => {
    mounted = true;
    window.addEventListener("keydown", onKeydown);
    await Promise.all([loadFilterOptions(), fetchEvents(1)]);
});

onBeforeUnmount(() => {
    activeRequestId += 1;
    cityOptionsRequestId += 1;
    subCategoryOptionsRequestId += 1;
    fetchDebouncedSearch.cancel();
    window.removeEventListener("keydown", onKeydown);
    document.body.style.overflow = previousBodyOverflow;
});
</script>

<template>
    <main class="events-directory" :dir="pageDirection">
        <div class="events-directory__container">
            <header class="events-directory__hero">
                <div class="events-directory__hero-copy">
                    <span class="events-directory__eyebrow">{{ $t("events.directory.eyebrow") }}</span>
                    <h1>{{ $t(titleKey) }}</h1>
                    <p>{{ $t(descriptionKey) }}</p>
                </div>
                <div class="events-directory__metric" aria-live="polite">
                    <span>{{ formattedTotal }}</span>
                    {{ $t(isHistorical ? "events.directory.historicalEvents" : "events.directory.events") }}
                </div>
            </header>

            <div class="events-directory__mobile-toolbar">
                <button
                    type="button"
                    class="events-directory__filter-trigger"
                    :aria-expanded="mobileFiltersOpen"
                    aria-controls="events-directory-filters"
                    @click="mobileFiltersOpen = true"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 6h16v2H4V6Zm3 5h10v2H7v-2Zm3 5h4v2h-4v-2Z" />
                    </svg>
                    {{ $t("events.directory.filters") }}
                </button>

                <label class="events-directory__mobile-sort">
                    <span class="sr-only">{{ $t("events.sort_by") }}</span>
                    <select :value="filters.sort" @change="updateFilter({ key: 'sort', value: $event.target.value })">
                        <option value="newest">{{ $t("events.sort.newest") }}</option>
                        <option value="oldest">{{ $t("events.sort.oldest") }}</option>
                        <option value="title">{{ $t("events.sort.title") }}</option>
                    </select>
                </label>
            </div>

            <div class="events-directory__layout">
                <button
                    v-if="mobileFiltersOpen"
                    type="button"
                    class="events-directory__overlay"
                    :aria-label="$t('events.directory.closeFilters')"
                    @click="closeFilters"
                ></button>

                <aside
                    id="events-directory-filters"
                    class="events-directory__sidebar"
                    :class="{ 'is-open': mobileFiltersOpen }"
                >
                    <EventsDirectoryFilters
                        :mode="mode"
                        :filters="filters"
                        :countries="countries"
                        :cities="cities"
                        :categories="categories"
                        :sub-categories="subCategories"
                        :create-route="createRoute"
                        @update="updateFilter"
                        @clear="clearFilters"
                        @close="closeFilters"
                    />
                </aside>

                <section class="events-directory__results" :aria-busy="loading">
                    <div class="events-directory__results-header">
                        <div>
                            <span>{{ $t("events.directory.collection") }}</span>
                            <h2>{{ $t(titleKey) }}</h2>
                        </div>
                        <p aria-live="polite">{{ resultSummary }}</p>
                    </div>

                    <div v-if="loading" class="events-directory__grid" role="status">
                        <span class="sr-only">{{ $t("events.loading") }}</span>
                        <article v-for="index in 6" :key="index" class="event-skeleton" aria-hidden="true">
                            <div class="event-skeleton__image"></div>
                            <div class="event-skeleton__body">
                                <span></span><span></span><span></span>
                            </div>
                        </article>
                    </div>

                    <div v-else-if="error" class="events-directory__state events-directory__state--error">
                        <div class="events-directory__state-icon" aria-hidden="true">!</div>
                        <h2>{{ $t("events.directory.errorTitle") }}</h2>
                        <p>{{ $t("events.directory.errorDescription") }}</p>
                        <button type="button" @click="fetchEvents(currentPage)">
                            {{ $t("events.retry") }}
                        </button>
                    </div>

                    <div v-else-if="events.length === 0" class="events-directory__state">
                        <div class="events-directory__state-icon" aria-hidden="true">⌕</div>
                        <h2>{{ $t(emptyTitleKey) }}</h2>
                        <p>{{ $t("events.directory.emptyDescription") }}</p>
                        <button type="button" @click="clearFilters">
                            {{ $t("events.directory.clearFilters") }}
                        </button>
                    </div>

                    <template v-else>
                        <div class="events-directory__grid">
                            <EventDirectoryCard
                                v-for="event in events"
                                :key="event.id"
                                :event="event"
                                :locale="locale"
                                :mode="mode"
                            />
                        </div>

                        <EventsDirectoryPagination
                            :current-page="currentPage"
                            :last-page="lastPage"
                            :pages="visiblePages"
                            @change="changePage"
                        />
                    </template>
                </section>
            </div>
        </div>
    </main>
</template>

<style scoped>
.events-directory {
    min-height: 100vh;
    background: #f8fafc;
    color: #06142a;
    padding: 28px 0 70px;
}

.events-directory,
.events-directory * {
    box-sizing: border-box;
}

.events-directory__container {
    width: min(100% - 32px, 1400px);
    margin-inline: auto;
}

.events-directory__hero {
    position: relative;
    display: flex;
    overflow: hidden;
    min-height: 210px;
    align-items: center;
    justify-content: space-between;
    gap: 28px;
    border: 1px solid #dce8f5;
    border-radius: 24px;
    padding: 38px 42px;
    background:
        radial-gradient(circle at 88% 20%, rgba(22, 119, 255, 0.14), transparent 30%),
        linear-gradient(135deg, #fff 0%, #f3f8ff 100%);
    box-shadow: 0 14px 38px rgba(13, 77, 151, 0.06);
}

.events-directory__hero::after {
    position: absolute;
    width: 180px;
    height: 180px;
    inset-inline-end: 9%;
    bottom: -125px;
    border: 28px solid rgba(22, 119, 255, 0.07);
    border-radius: 50%;
    content: "";
}

.events-directory__hero-copy {
    position: relative;
    z-index: 1;
    max-width: 760px;
}

.events-directory__eyebrow,
.events-directory__results-header span {
    display: block;
    margin-bottom: 7px;
    color: #1677ff;
    font-size: 0.73rem;
    font-weight: 800;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}

.events-directory__hero h1 {
    margin: 0;
    color: #06142a;
    font-size: clamp(2rem, 4vw, 3.35rem);
    font-weight: 800;
    letter-spacing: -0.035em;
    line-height: 1.08;
}

.events-directory__hero p {
    max-width: 680px;
    margin: 14px 0 0;
    color: #5d7188;
    font-size: 1rem;
    line-height: 1.75;
}

.events-directory__metric {
    position: relative;
    z-index: 1;
    min-width: 150px;
    border: 1px solid rgba(22, 119, 255, 0.16);
    border-radius: 18px;
    padding: 20px 22px;
    background: rgba(255, 255, 255, 0.76);
    color: #5d7188;
    text-align: center;
    backdrop-filter: blur(8px);
}

.events-directory__metric span {
    display: block;
    margin-bottom: 2px;
    color: #0d4d97;
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
}

.events-directory__layout {
    display: grid;
    grid-template-columns: minmax(260px, 290px) minmax(0, 1fr);
    gap: 28px;
    margin-top: 30px;
    align-items: start;
}

.events-directory__sidebar {
    position: sticky;
    top: 96px;
    z-index: 4;
}

.events-directory__results {
    min-width: 0;
}

.events-directory__results-header {
    display: flex;
    min-height: 64px;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
}

.events-directory__results-header span {
    margin-bottom: 2px;
}

.events-directory__results-header h2 {
    margin: 0;
    font-size: 1.55rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.events-directory__results-header p {
    margin: 0 0 3px;
    color: #64748b;
    font-size: 0.84rem;
}

.events-directory__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
}

.event-skeleton {
    overflow: hidden;
    border: 1px solid #e2ebf4;
    border-radius: 20px;
    background: #fff;
}

.event-skeleton__image,
.event-skeleton__body span {
    background: linear-gradient(90deg, #edf2f7 25%, #f8fafc 50%, #edf2f7 75%);
    background-size: 200% 100%;
    animation: events-directory-shimmer 1.35s infinite linear;
}

.event-skeleton__image {
    aspect-ratio: 16 / 9;
}

.event-skeleton__body {
    display: grid;
    gap: 12px;
    padding: 20px;
}

.event-skeleton__body span {
    display: block;
    height: 12px;
    border-radius: 999px;
}

.event-skeleton__body span:nth-child(2) {
    width: 82%;
    height: 18px;
}

.event-skeleton__body span:nth-child(3) {
    width: 56%;
}

@keyframes events-directory-shimmer {
    to { background-position: -200% 0; }
}

.events-directory__state {
    display: flex;
    min-height: 390px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    border: 1px dashed #c7d9ea;
    border-radius: 22px;
    padding: 42px 24px;
    background: #fff;
    text-align: center;
}

.events-directory__state-icon {
    display: grid;
    width: 58px;
    height: 58px;
    margin-bottom: 18px;
    place-items: center;
    border-radius: 18px;
    background: #eef6ff;
    color: #1677ff;
    font-size: 1.7rem;
    font-weight: 800;
}

.events-directory__state h2 {
    margin: 0;
    font-size: 1.28rem;
    font-weight: 800;
}

.events-directory__state p {
    max-width: 460px;
    margin: 9px 0 20px;
    color: #64748b;
    line-height: 1.65;
}

.events-directory__state button {
    min-height: 42px;
    border: 0;
    border-radius: 10px;
    padding: 0 18px;
    background: #0d4d97;
    color: #fff;
    font-weight: 750;
}

.events-directory__state--error .events-directory__state-icon {
    background: #fff1f2;
    color: #dc2626;
}

.events-directory__mobile-toolbar,
.events-directory__overlay {
    display: none;
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
}

@media (max-width: 1279px) {
    .events-directory__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 991px) {
    .events-directory {
        padding-top: 20px;
    }

    .events-directory__hero {
        min-height: 190px;
        padding: 30px;
    }

    .events-directory__layout {
        display: block;
        margin-top: 18px;
    }

    .events-directory__mobile-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 18px;
    }

    .events-directory__filter-trigger,
    .events-directory__mobile-sort select {
        min-height: 44px;
        border: 1px solid #d1dfed;
        border-radius: 11px;
        background: #fff;
        color: #26435f;
        font: inherit;
        font-size: 0.85rem;
        font-weight: 700;
    }

    .events-directory__filter-trigger {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 0 16px;
    }

    .events-directory__filter-trigger svg {
        width: 18px;
        height: 18px;
        fill: #1677ff;
    }

    .events-directory__mobile-sort select {
        padding: 0 34px 0 12px;
    }

    .events-directory__sidebar {
        position: fixed;
        top: 0;
        bottom: 0;
        inset-inline-start: 0;
        z-index: 1002;
        width: min(88vw, 350px);
        overflow-y: auto;
        background: #fff;
        transform: translateX(-105%);
        transition: transform 220ms ease;
    }

    [dir="rtl"] .events-directory__sidebar {
        transform: translateX(105%);
    }

    .events-directory__sidebar.is-open,
    [dir="rtl"] .events-directory__sidebar.is-open {
        transform: translateX(0);
    }

    .events-directory__overlay {
        position: fixed;
        inset: 0;
        z-index: 1001;
        display: block;
        border: 0;
        background: rgba(6, 20, 42, 0.52);
        backdrop-filter: blur(2px);
    }
}

@media (max-width: 767px) {
    .events-directory__container {
        width: min(100% - 24px, 1400px);
    }

    .events-directory__hero {
        display: block;
        min-height: 0;
        border-radius: 20px;
        padding: 26px 22px;
    }

    .events-directory__hero h1 {
        font-size: clamp(1.85rem, 9vw, 2.65rem);
    }

    .events-directory__metric {
        display: inline-flex;
        min-width: 0;
        margin-top: 20px;
        align-items: baseline;
        gap: 7px;
        padding: 11px 14px;
        text-align: start;
    }

    .events-directory__metric span {
        display: inline;
        margin: 0;
        font-size: 1.3rem;
    }

    .events-directory__results-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 7px;
        margin-bottom: 16px;
    }
}

@media (max-width: 575px) {
    .events-directory {
        padding-bottom: 48px;
    }

    .events-directory__grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
    }

    .events-directory__mobile-toolbar {
        align-items: stretch;
    }

    .events-directory__filter-trigger,
    .events-directory__mobile-sort {
        flex: 1;
    }

    .events-directory__mobile-sort select {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .event-skeleton__image,
    .event-skeleton__body span {
        animation: none;
    }

    .events-directory__sidebar {
        transition: none;
    }
}
</style>
