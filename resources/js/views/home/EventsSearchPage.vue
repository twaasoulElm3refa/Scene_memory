<template>
    <div class="events-search-page scemory-page">

        <section v-if="error" class="events-search-error mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div>
                <h2>{{ $t('searchResults.loadError') }}</h2>
                <p>{{ error }}</p>
                <button type="button" @click="fetchEventsFromRoute">
                    {{ $t('common.tryAgain') }}
                </button>
            </div>
        </section>

        <EventsSection
            v-else
            :searched="true"
            :loading="loading"
            :displayed-events="displayedEvents"
            :paginated-events="paginatedEvents"
            :visible-pages="visiblePages"
            :current-page="currentPage"
            :total-pages="totalPages"
            :total-results="totalResults"
            :result-from="resultFrom"
            :result-to="resultTo"
            :per-page="perPage"
            :fallback-image="fallbackImage"
            :format-date="formatDate"
            :lang="lang"
            @update:current-page="handlePageChange"
        />
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, shallowRef, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import debounce from "lodash/debounce";

import { CategoryService } from "@/services/CategoryService/CategoryService";
import { EventService } from "@/services/EventService/EventService";
import {
    DEFAULT_EVENT_FALLBACK_IMAGE,
    compactQuery,
    eventFiltersToQuery,
    normalizeEvent,
    normalizePaginatedResponse,
    queryToEventFilters,
} from "@/services/EventService/eventSearchHelpers";
import { LocationService } from "@/services/LocationService/LocationService";
import { TagService } from "@/services/TagService/TagService";
import EventsSection from "./components/EventsSection.vue";
import FiltersSection from "./components/FiltersSection.vue";
import UnifiedSearchBar from "./components/UnifiedSearchBar.vue";

const route = useRoute();
const router = useRouter();

const lang = computed(() => route.params.lang || localStorage.getItem("language") || "en");

const searchQuery = ref("");
const selectedCategory = ref("");
const selectedSubCategory = ref("");
const selectedCountry = ref("");
const selectedCity = ref("");
const fromDate = ref("");
const toDate = ref("");
const selectedTags = ref([]);

const categories = shallowRef([]);
const subCategories = shallowRef([]);
const countries = shallowRef([]);
const filteredCountries = shallowRef([]);
const cities = shallowRef([]);
const tags = shallowRef([]);
const tagSuggestions = shallowRef([]);
const displayedEvents = shallowRef([]);

const countrySearch = ref("");
const showDropdown = ref(false);
const filtersSectionRef = ref(null);

const loading = ref(false);
const loadingSubCategories = ref(false);
const loadingTags = ref(false);
const loadingTagSuggestions = ref(false);
const error = ref("");

const currentPage = ref(1);
const perPage = ref(12);
const totalPages = ref(1);
const totalResults = ref(0);
const resultFrom = ref(null);
const resultTo = ref(null);

const fallbackImage = DEFAULT_EVENT_FALLBACK_IMAGE;
const maxVisible = 5;

const isSyncingRoute = ref(false);
let baseOptionsPromise = null;
let activeRequestId = 0;

const paginatedEvents = computed(() => displayedEvents.value);

const visiblePages = computed(() => {
    const total = Number(totalPages.value) || 1;
    const current = Number(currentPage.value) || 1;

    let start = Math.max(current - Math.floor(maxVisible / 2), 1);
    let end = start + maxVisible - 1;

    if (end > total) {
        end = total;
        start = Math.max(end - maxVisible + 1, 1);
    }

    const pages = [];
    for (let page = start; page <= end; page += 1) {
        pages.push(page);
    }

    return pages;
});

const resultsSummary = computed(() => {
    if (loading.value) {
        return "Loading matching events...";
    }

    if (totalResults.value > 0) {
        return `Showing ${resultFrom.value || 0} - ${resultTo.value || 0} of ${totalResults.value} events`;
    }

    return "Use the filters below to refine the full event archive.";
});

const formatDate = (dateStr) => {
    if (!dateStr) return "-";

    try {
        return new Date(dateStr).toLocaleDateString(lang.value, {
            year: "numeric",
            month: "short",
            day: "numeric",
        });
    } catch {
        return "-";
    }
};

const resetPaginationMeta = () => {
    currentPage.value = 1;
    totalPages.value = 1;
    totalResults.value = 0;
    resultFrom.value = null;
    resultTo.value = null;
};

const normalizeComparableQuery = (query = {}) => {
    const compacted = compactQuery(query);

    return Object.keys(compacted)
        .sort()
        .reduce((result, key) => {
            result[key] = Array.isArray(compacted[key])
                ? compacted[key].join(",")
                : String(compacted[key]);

            return result;
        }, {});
};

const queriesMatch = (left, right) => {
    return JSON.stringify(normalizeComparableQuery(left)) === JSON.stringify(normalizeComparableQuery(right));
};

const loadBaseOptions = async () => {
    if (!baseOptionsPromise) {
        baseOptionsPromise = Promise.all([
            CategoryService.getAllCategories(),
            LocationService.getAllCountries(),
            loadTags(),
        ]).then(([loadedCategories, loadedCountries]) => {
            categories.value = loadedCategories;
            countries.value = loadedCountries;
            filteredCountries.value = [...loadedCountries];
        });
    }

    await baseOptionsPromise;
};

const loadTags = async () => {
    loadingTags.value = true;

    try {
        const response = await TagService.searchTags({ q: "", limit: 10 });
        const payload = response?.data?.data ?? response?.data ?? response;
        const loadedTags = Array.isArray(payload) ? payload : [];

        tags.value = loadedTags;
        tagSuggestions.value = loadedTags;

        return loadedTags;
    } catch (tagError) {
        console.error("Error loading tags:", tagError);
        tags.value = [];
        tagSuggestions.value = [];

        return [];
    } finally {
        loadingTags.value = false;
    }
};

const fetchTagSuggestions = debounce(async (term = "") => {
    loadingTagSuggestions.value = true;

    try {
        const query = String(term || "").trim();
        const response = await TagService.searchTags({
            q: query,
            limit: query ? 10 : 5,
        });
        const payload = response?.data?.data ?? response?.data ?? response;

        tagSuggestions.value = Array.isArray(payload) ? payload : [];
    } catch (tagError) {
        console.error("Error loading tag suggestions:", tagError);
        tagSuggestions.value = [];
    } finally {
        loadingTagSuggestions.value = false;
    }
}, 250);

const loadSubCategoriesForCategory = async (categoryId) => {
    subCategories.value = [];

    if (!categoryId) {
        return;
    }

    loadingSubCategories.value = true;

    try {
        const response = await CategoryService.getSubCategoriesByCategory(categoryId);
        subCategories.value = response.data?.data || [];
    } catch (subCategoryError) {
        console.error("Error loading sub-categories:", subCategoryError);
        subCategories.value = [];
    } finally {
        loadingSubCategories.value = false;
    }
};

const loadCitiesForCountry = async (countryId) => {
    cities.value = [];

    if (!countryId) {
        return;
    }

    cities.value = await LocationService.getCitiesByCountry(countryId);
};

const filterCountries = () => {
    const search = countrySearch.value.toLowerCase().trim();

    if (!search) {
        filteredCountries.value = [...countries.value];
        return;
    }

    filteredCountries.value = countries.value.filter((country) =>
        country.translation?.name?.toLowerCase().includes(search)
    );
};

const onCountryFocus = () => {
    showDropdown.value = true;
    filterCountries();
};

const closeCountryDropdown = () => {
    showDropdown.value = false;
};

const getCurrentControlFilters = (page = 1) => ({
    searchQuery: searchQuery.value,
    categoryId: selectedCategory.value,
    subCategoryId: selectedSubCategory.value,
    countryId: selectedCountry.value,
    cityId: selectedCity.value,
    tagsIds: selectedTags.value,
    fromDate: fromDate.value,
    toDate: toDate.value,
    page,
    perPage: perPage.value,
});

const updateRouteFromState = async (page = 1) => {
    const query = eventFiltersToQuery(getCurrentControlFilters(page), {
        includePagination: true,
        defaultPerPage: perPage.value,
    });

    if (queriesMatch(route.query, query)) {
        return;
    }

    await router.push({
        path: `/${lang.value}/events`,
        query,
    });
};

const handleSearchSubmit = () => {
    closeCountryDropdown();
    void updateRouteFromState(1);
};

const handleTagsUpdate = (value) => {
    selectedTags.value = Array.isArray(value) ? [...value] : [];
    closeCountryDropdown();
    void updateRouteFromState(1);
};

const handleCategoryUpdate = async (value) => {
    selectedCategory.value = value;
    selectedSubCategory.value = "";
    closeCountryDropdown();
    await loadSubCategoriesForCategory(value);
    void updateRouteFromState(1);
};

const handleSubCategoryUpdate = (value) => {
    selectedSubCategory.value = value;
    closeCountryDropdown();
    void updateRouteFromState(1);
};

const selectCountry = async (country) => {
    selectedCountry.value = country?.id || "";
    selectedCity.value = "";
    countrySearch.value = country?.translation?.name || "";
    closeCountryDropdown();
    await loadCitiesForCountry(selectedCountry.value);
    void updateRouteFromState(1);
};

const handleCityUpdate = (value) => {
    selectedCity.value = value;
    closeCountryDropdown();
    void updateRouteFromState(1);
};

const handleFromDateUpdate = (value) => {
    fromDate.value = value;
    closeCountryDropdown();
    void updateRouteFromState(1);
};

const handleToDateUpdate = (value) => {
    toDate.value = value;
    closeCountryDropdown();
    void updateRouteFromState(1);
};

const handlePageChange = (page) => {
    const nextPage = Number(page);

    if (!nextPage || nextPage < 1 || nextPage > totalPages.value) return;
    if (nextPage === currentPage.value || loading.value) return;

    void updateRouteFromState(nextPage).then(() => {
        nextTick(() => {
            document
                .querySelector(".home-events-results")
                ?.scrollIntoView({ behavior: "smooth", block: "start" });
        });
    });
};

const syncControlsFromFilters = async (filters) => {
    isSyncingRoute.value = true;

    await loadBaseOptions();

    searchQuery.value = filters.searchQuery || "";
    selectedCategory.value = filters.categoryId || "";
    selectedSubCategory.value = "";
    selectedCountry.value = filters.countryId || "";
    selectedCity.value = "";
    selectedTags.value = [...filters.tagsIds];
    fromDate.value = filters.fromDate || "";
    toDate.value = filters.toDate || "";
    currentPage.value = filters.page;
    perPage.value = filters.perPage;

    await loadSubCategoriesForCategory(selectedCategory.value);
    selectedSubCategory.value = filters.subCategoryId || "";

    await loadCitiesForCountry(selectedCountry.value);
    selectedCity.value = filters.cityId || "";

    const selectedCountryRecord = countries.value.find(
        (country) => String(country.id) === String(selectedCountry.value)
    );

    countrySearch.value = selectedCountryRecord?.translation?.name || "";
    filteredCountries.value = [...countries.value];
    showDropdown.value = false;
    isSyncingRoute.value = false;
};

const fetchEvents = async (filters) => {
    const requestId = activeRequestId + 1;
    activeRequestId = requestId;
    loading.value = true;
    error.value = "";

    try {
        const response = await EventService.searchEvents(filters);

        if (requestId !== activeRequestId) {
            return;
        }

        const paginator = normalizePaginatedResponse(response, filters.perPage);

        displayedEvents.value = paginator.events.map(normalizeEvent);
        currentPage.value = paginator.currentPage;
        totalPages.value = paginator.lastPage;
        totalResults.value = paginator.total;
        resultFrom.value = paginator.from;
        resultTo.value = paginator.to;
    } catch (searchError) {
        if (requestId !== activeRequestId) {
            return;
        }

        console.error("Search results error:", searchError);
        displayedEvents.value = [];
        resetPaginationMeta();
        error.value = searchError.response?.data?.message || searchError.message || "Unable to load events.";
    } finally {
        if (requestId === activeRequestId) {
            loading.value = false;
        }
    }
};

const fetchEventsFromRoute = async () => {
    const filters = queryToEventFilters(route.query, {
        defaultPerPage: perPage.value || 12,
    });

    await syncControlsFromFilters(filters);
    await fetchEvents(filters);
};

const handleClickOutsideFilters = (event) => {
    if (!filtersSectionRef.value) return;

    if (!filtersSectionRef.value.contains(event.target)) {
        closeCountryDropdown();
    }
};

watch(countrySearch, (value) => {
    filterCountries();

    if (isSyncingRoute.value) return;

    if (!String(value || "").trim() && selectedCountry.value) {
        selectedCountry.value = "";
        selectedCity.value = "";
        cities.value = [];
        void updateRouteFromState(1);
    }
});

watch(
    () => route.query,
    () => {
        void fetchEventsFromRoute();
    },
    { immediate: true }
);

onMounted(() => {
    document.addEventListener("mousedown", handleClickOutsideFilters);
});

onUnmounted(() => {
    document.removeEventListener("mousedown", handleClickOutsideFilters);
    fetchTagSuggestions.cancel();
});
</script>

<style scoped>
.events-search-page {
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(48, 168, 255, 0.10), transparent 34rem),
        linear-gradient(180deg, var(--scemory-surface), #FFFFFF 45%, var(--scemory-surface));
}

.events-search-hero {
    padding: 120px 0 34px;
}

.events-search-eyebrow {
    display: inline-flex;
    border: 1px solid var(--scemory-border);
    border-radius: 999px;
    background: var(--scemory-active);
    color: var(--scemory-primary);
    padding: 0.6rem 0.9rem;
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.events-search-hero h1 {
    margin: 1rem 0 0;
    color: var(--scemory-heading);
    font-size: clamp(2rem, 4vw, 4rem);
    font-weight: 850;
    letter-spacing: 0;
    line-height: 1.08;
}

.events-search-hero p {
    margin: 0.9rem 0 0;
    max-width: 680px;
    color: var(--scemory-text);
    font-size: 1.05rem;
    line-height: 1.8;
}

.events-search-controls {
    position: relative;
    z-index: 30;
}

.home-discovery-panel {
    position: relative;
    overflow: visible;
    border: none;
    border-radius: 24px;
    background:
        radial-gradient(circle at 88% 10%, rgba(48, 168, 255, 0.06), transparent 30%),
        linear-gradient(145deg, #F7FAFD, #EDF4FA);
    box-shadow: 0 12px 34px rgba(13, 77, 151, 0.07);
    padding: 22px;
}

.home-discovery-divider {
    height: 1px;
    margin: 18px 0;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(13, 77, 151, 0.13),
        transparent
    );
}

.events-search-error {
    padding-top: 44px;
}

.events-search-error > div {
    border: 1px solid var(--scemory-border-soft);
    border-radius: 24px;
    background: #FFFFFF;
    box-shadow: var(--scemory-shadow-sm);
    padding: 28px;
}

.events-search-error h2 {
    color: var(--scemory-heading);
    font-size: 1.35rem;
    font-weight: 800;
}

.events-search-error p {
    margin: 0.6rem 0 0;
    color: var(--scemory-text);
}

.events-search-error button {
    margin-top: 1rem;
    border: 1px solid rgba(22, 119, 255, 0.22);
    border-radius: 999px;
    background: linear-gradient(135deg, var(--scemory-primary), var(--scemory-blue));
    color: #FFFFFF;
    padding: 0.72rem 1.2rem;
    font-size: 0.9rem;
    font-weight: 800;
}

@media (max-width: 640px) {
    .events-search-hero {
        padding-top: 96px;
    }

    .home-discovery-panel {
        border-radius: 20px;
        padding: 14px;
    }

    .home-discovery-divider {
        margin: 14px 0;
    }
}
</style>
