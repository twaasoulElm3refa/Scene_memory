<template>
    <div class="home-page-shell min-h-screen font-sans">
        <main class="relative">
            <!-- FULL WIDTH HERO -->
            <section class="relative min-h-screen overflow-hidden">

                <!-- Background Video -->
                <video class="absolute inset-0 z-0 h-full w-full object-cover" autoplay muted loop playsinline
                    preload="auto">
                    <source src="/video/Final_Scemory.mp4" type="video/mp4" />
                    {{ $t('homeAudit.home.videoUnsupported') }}
                </video>

                <!-- Dark / Blue Overlay -->
                <div class="absolute inset-0 bg-gradient-to-b
               from-[#04111D]/70
               via-[#06233B]/65
               to-[#071C2D]/80">
                </div>

                <!-- Soft Blue Center Glow -->
                <div
                    class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(13,77,151,0.20),transparent_55%)]">
                </div>

                <!-- Bottom Fade
                <div class="absolute inset-x-0 bottom-0 h-40
               bg-gradient-to-t
               from-[#F8FAFC]
               via-[#F8FAFC]/35
               to-transparent">
                </div> -->

                <!-- Hero Navbar -->
                <div class="hero-navbar absolute inset-x-0 top-2 z-30">
                    <Navbar />
                </div>

                <!-- Content -->
                <div class="relative z-10 mx-auto flex min-h-screen
               max-w-7xl items-center justify-center
               px-4 pt-24 sm:px-6 sm:pt-28 lg:px-8">
                    <div class="mx-auto flex w-full max-w-5xl
                   flex-col items-center text-center">

                        <!-- Badge -->
                        <span class="inline-flex items-center gap-2
                       rounded-full border border-white/20
                       bg-white/10 px-5 py-2
                       text-sm font-semibold text-white
                       shadow-lg backdrop-blur-md">
                            {{ $t('homeAudit.home.badge') }}

                            <span class="text-[#38AEEA]">
                                ✦
                            </span>
                        </span>

                        <!-- Heading -->
                        <h1 class="mt-6 max-w-5xl
                       text-4xl font-bold leading-[1.1]
                       tracking-tight text-white
                       drop-shadow-[0_8px_24px_rgba(0,0,0,0.45)]
                       sm:text-5xl md:text-6xl lg:text-7xl">
                            {{ $t('homeAudit.home.title') }}

                            <span class="mt-1 block text-[#38AEEA]">
                                {{ $t('homeAudit.home.titleHighlight') }}
                            </span>
                        </h1>

                        <!-- Description -->
                        <p class="mt-6 max-w-2xl
                       text-base leading-8
                       text-white/85
                       md:text-lg">
                            {{ $t('homeAudit.home.description') }}
                        </p>

                        <!-- Buttons -->
                        <div class="mt-8 flex flex-wrap
                       items-center justify-center gap-3">

                            <button type="button" @click="scrollToEventsSearch" class="search-events-btn">
                                {{ $t('homeAudit.home.searchEvents') }}
                            </button>

                            <RouterLink :to="`/${lang}/add_event`" class="inline-flex items-center justify-center
                           rounded-full border border-white/35
                           bg-white/10
                           px-8 py-3.5
                           text-sm font-bold text-white
                           shadow-lg backdrop-blur-md
                           transition duration-300
                           hover:-translate-y-1
                           hover:bg-white/20">
                                {{ $t('homeAudit.home.uploadStory') }}
                            </RouterLink>

                        </div>

                        <!-- Small Bottom Text -->
                        <p class="mt-6 text-sm text-white/60">
                            {{ $t('homeAudit.home.bottomText') }}
                        </p>

                    </div>
                </div>

            </section>

            <!-- MAP + TRENDING + FILTERS -->
            <section id="explore-events" class="home-discovery-section">
                <div class="home-discovery-container">
                    <section id="events-search-section" ref="eventsSearchSectionRef"
                        class="home-events-search-target home-discovery-search">
                        <UnifiedSearchBar ref="eventsSearchRef" v-model="searchQuery" :tags="tags"
                            :selected-tags="selectedTags" :loading="loadingTags" :tag-suggestions="tagSuggestions"
                            :loading-suggestions="loadingTagSuggestions" @update:selected-tags="handleTagsUpdate"
                            @fetch-tag-suggestions="fetchTagSuggestions" @search="handleSearchClick" />
                    </section>

                    <button type="button" class="home-mobile-filter-toggle"
                        :aria-expanded="mobileFiltersOpen ? 'true' : 'false'" aria-controls="filters-section"
                        @click="mobileFiltersOpen = !mobileFiltersOpen">
                        <span>{{ $t('filters.title') }}</span>
                        <span aria-hidden="true">{{ mobileFiltersOpen ? '-' : '+' }}</span>
                    </button>

                    <div class="home-discovery-workspace">
                        <aside class="home-discovery-filters-column" :class="{ 'is-open': mobileFiltersOpen }">
                            <section id="filters-section" ref="filtersSectionRef" class="home-discovery-filters">
                                <FiltersSection :categories="categories" :selected-category="selectedCategory"
                                    :sub-categories="subCategories" :selected-sub-category="selectedSubCategory"
                                    :loading-sub-categories="loadingSubCategories" :country-search="countrySearch"
                                    :show-dropdown="showDropdown" :filtered-countries="filteredCountries"
                                    :selected-country="selectedCountry" :cities="cities" :selected-city="selectedCity"
                                    :from-date="fromDate" :to-date="toDate"
                                    @update:selected-category="handleCategoryUpdate"
                                    @update:selected-sub-category="handleSubCategoryUpdate"
                                    @update:country-search="countrySearch = $event"
                                    @update:selected-city="handleCityUpdate" @update:from-date="handleFromDateUpdate"
                                    @update:to-date="handleToDateUpdate" @country-focus="onCountryFocus"
                                    @select-country="selectCountry" @category-changed="handleMainCategoryChange"
                                    @search="handleSearchClick" />
                            </section>
                        </aside>

                        <main class="home-discovery-map-column">
                            <MapSection :fullscreen="fullscreen" :is-map-ready="isMapReady"
                                :is-map-loading="isMapLoading" :map-error="mapError" :can-init-map="canInitMap"
                                @map-viewport-enter="handleMapViewportEnter" @load-map="handleManualMapLoad"
                                @open-fullscreen="openFullscreen" @close-fullscreen="closeFullscreen" />
                        </main>

                        <aside class="home-discovery-trending-column">
                            <TrendingEventsSection :events="trendingEvents" :loading="loadingTrendingEvents"
                                :error="trendingEventsError" :fallback-image="fallbackImage" :format-date="formatDate"
                                :lang="lang" />
                        </aside>
                    </div>
                </div>
            </section>

            <!-- EVENTS RESULTS -->
            <section id="events-results" class="home-results-band">
                <div class="home-results-container">
                    <DiscoveryResultsSection :searched="searched" :loading="loading" :results="paginatedResults"
                        :active-type="selectedType" :visible-pages="visiblePages" :current-page="currentPage"
                        :total-pages="totalPages" :total-results="totalResults" :result-from="resultFrom"
                        :result-to="resultTo" :per-page="perPage" :fallback-image="fallbackImage"
                        :format-date="formatDate" :lang="lang" :show-see-more="canSeeMoreSearchResults"
                        @update:active-type="handleTypeChange" @update:current-page="handlePageChange"
                        @see-more="goToMoreSearchResults" />
                </div>
            </section>

            <!-- SPECIAL COVERAGE -->
            <SpecialCoverageSection />

            <!-- SCEMORY EXPERIENCE -->
            <ScemoryExperienceTabs />

            <!-- PLANS -->
            <section class="home-plans-band py-14">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <PlansSection :licence-name="licenceName" :plans="plans" :loading-plans="loadingPlans"
                        @open-plan="openPlan" />
                </div>
            </section>


            <!-- NEWSLETTER -->
            <NewsletterSection />
        </main>

        <Transition name="slide-fade" class="mt-15">
            <div v-if="showProfileToast"
                class="fixed top-4 left-4 z-[9999] w-[280px] cursor-pointer overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg"
                @click="goToProfile">
                <div class="flex items-center justify-between border-b bg-yellow-50 px-3 py-2">
                    <span class="text-sm font-semibold text-yellow-800">
                        {{ $t('homeAudit.profileToast.title') }}
                    </span>

                    <button type="button" @click.stop="closeToast"
                        class="text-4xl font-bold leading-none text-gray-400 transition-colors hover:text-red-500"
                        :aria-label="$t('common.close')">
                        ×
                    </button>
                </div>

                <div class="p-3 text-xs text-gray-600">
                    {{ $t('homeAudit.profileToast.message') }}
                    <ul class="mt-1.5 list-inside list-disc text-[11px] text-gray-500">
                        <li v-for="field in missingFieldsList" :key="field">
                            {{ fieldLabels[field] || field }}
                        </li>
                    </ul>

                    <p class="mt-2 text-[11px] font-medium text-blue-600">{{ $t('homeAudit.profileToast.complete') }}
                    </p>
                </div>

                <div class="h-1 bg-gray-100">
                    <div class="h-full bg-yellow-500 transition-all duration-100 ease-linear"
                        :style="{ width: progressWidth }"></div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import {
    computed,
    defineAsyncComponent,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    shallowRef,
    watch,
} from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import debounce from "lodash/debounce";

import { CategoryService } from "@/services/CategoryService/CategoryService";
import { TagService } from "@/services/TagService/TagService";
import { LocationService } from "@/services/LocationService/LocationService";
import { EventService } from "@/services/EventService/EventService";
import {
    createDiscoverySeed,
    discoveryResultsToMapEvents,
    eventFiltersToQuery,
    normalizeDiscoveryResult,
    normalizePaginatedResponse,
    toMediaUrl,
} from "@/services/EventService/eventSearchHelpers";
import { PlanService } from "@/services/planService/planService";
import { AuthService } from "@/services/AuthService/AuthService";
import Navbar from "@/components/layouts/Navbar.vue";

const MapSection = defineAsyncComponent(() => import("./components/MapSection.vue"));
const UnifiedSearchBar = defineAsyncComponent(() => import("./components/UnifiedSearchBar.vue"));
const FiltersSection = defineAsyncComponent(() => import("./components/FiltersSection.vue"));
const DiscoveryResultsSection = defineAsyncComponent(() => import("./components/DiscoveryResultsSection.vue"));
const PlansSection = defineAsyncComponent(() => import("./components/PlansSection.vue"));
const TrendingEventsSection = defineAsyncComponent(() => import("./components/TrendingEventsSection.vue"));
const SpecialCoverageSection = defineAsyncComponent(() => import("./components/SpecialCoverageSection.vue"));
const ScemoryExperienceTabs = defineAsyncComponent(() => import("./components/ScemoryExperienceTabs.vue"));
const NewsletterSection = defineAsyncComponent(() => import("./components/NewsletterSection.vue"));

const router = useRouter();
const { t } = useI18n();
const lang = localStorage.getItem("language") || "en";

const DEFAULT_LOCATION = { lat: 30.0444, lng: 31.2357 };
const marker = ref({ ...DEFAULT_LOCATION });
const hasTriedUserLocation = ref(false);
const eventsSearchSectionRef = ref(null);
const eventsSearchRef = ref(null);
const filtersSectionRef = ref(null);
const mobileFiltersOpen = ref(false);
const fullscreen = ref(false);
const isMapReady = ref(false);
const isMapLoading = ref(false);
const canInitMap = ref(false);
const hasRequestedMapInit = ref(false);
const mapError = ref("");

const licenceName = ref("free");
const isLoggedIn = ref(false);
const searchQuery = ref("");

const displayedResults = shallowRef([]);
const categories = shallowRef([]);
const countries = shallowRef([]);
const cities = shallowRef([]);
const filteredCountries = shallowRef([]);
const subCategories = shallowRef([]);
const tags = shallowRef([]);
const tagSuggestions = shallowRef([]);
const plans = shallowRef([]);
const trendingEvents = shallowRef([]);

const countrySearch = ref("");
const showDropdown = ref(false);
const selectedCategory = ref("");
const selectedCountry = ref("");
const selectedCity = ref("");
const fromDate = ref("");
const toDate = ref("");
const selectedSubCategory = ref("");
const selectedTags = ref([]);
const selectedType = ref("all");
const discoverySeed = ref(createDiscoverySeed());

const loadingSubCategories = ref(false);
const loadingTags = ref(false);
const loadingTagSuggestions = ref(false);
const loading = ref(false);
const searched = ref(false);
const currentPage = ref(1);
const perPage = ref(8);
const totalPages = ref(1);
const totalResults = ref(0);
const resultFrom = ref(null);
const resultTo = ref(null);
const loadingPlans = ref(false);
const loadingTrendingEvents = ref(false);
const trendingEventsError = ref("");

const maxVisible = 5;
const fallbackImage = "https://developers.elementor.com/docs/assets/img/elementor-placeholder-image.png";

const showProfileToast = ref(false);
const progressWidth = ref("100%");
const TOAST_DURATION = 7000;

let mapService = null;
let toastTimer = null;
let progressInterval = null;
let hasMapErrorListener = false;
const scheduledTasks = [];

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
    for (let i = start; i <= end; i += 1) pages.push(i);
    return pages;
});

const paginatedResults = computed(() => {
    return displayedResults.value;
});

const mapEvents = computed(() => discoveryResultsToMapEvents(displayedResults.value));

const canSeeMoreSearchResults = computed(() => {
    return searched.value
        && !loading.value
        && displayedResults.value.length > 0
        && totalResults.value > displayedResults.value.length;
});

const missingFieldsList = computed(() => {
    const data = localStorage.getItem("missingFields");
    return data ? JSON.parse(data) : [];
});

const fieldLabels = {
    phone: t("homeAudit.profileToast.fields.phone"),
    country: t("homeAudit.profileToast.fields.country"),
    position: t("homeAudit.profileToast.fields.position"),
    date_of_birth: t("homeAudit.profileToast.fields.dateOfBirth"),
};

const formatDate = (dateStr) => {
    if (!dateStr) return "-";
    const language = localStorage.getItem("language") || "ar";

    try {
        return new Date(dateStr).toLocaleDateString(language, {
            year: "numeric",
            month: "short",
            day: "numeric",
        });
    } catch {
        return "-";
    }
};

const normalizeTrendingEvent = (ev = {}) => {
    const firstImage = ev.first_image;
    const imagePath = typeof firstImage === "string" ? firstImage : firstImage?.full_url;

    return {
        id: ev.id || ev._id,
        slug: ev.slug,
        title: ev.translation?.title || ev.title || t("events.no_title"),
        description: ev.translation?.description || "",
        start_date: ev.start_date,
        image_url: toMediaUrl(imagePath) || fallbackImage,
        location_name:
            ev.city?.translation?.name ||
            ev.country?.translation?.name ||
            ev.location ||
            "",
        category_name:
            ev.sub_categorey?.translation?.name ||
            ev.sub_category?.translation?.name ||
            ev.category?.translation?.name ||
            "",
        user_name: ev.user?.name || "",
        likes_count: ev.likes_count ?? 0,
        views_count: ev.views_count ?? 0,
    };
};

const resetPaginationMeta = () => {
    currentPage.value = 1;
    totalPages.value = 1;
    totalResults.value = 0;
    resultFrom.value = null;
    resultTo.value = null;
};

const loadTrendingEvents = async () => {
    loadingTrendingEvents.value = true;
    trendingEventsError.value = "";

    try {
        const response = await EventService.getTrending();
        const payload = response?.data?.data ?? response?.data ?? response;
        const events = Array.isArray(payload) ? payload : [];

        trendingEvents.value = events.map(normalizeTrendingEvent);
    } catch (error) {
        console.error("Error loading trending events:", error);
        trendingEvents.value = [];
        trendingEventsError.value = error.response?.data?.message || t("homeAudit.trending.loadError");
    } finally {
        loadingTrendingEvents.value = false;
    }
};

const loadTags = async () => {
    loadingTags.value = true;

    try {
        const response = await TagService.searchTags({ q: "", limit: 5 });
        const payload = response?.data?.data ?? response?.data ?? response;
        const loadedTags = Array.isArray(payload) ? payload : [];

        tags.value = loadedTags;
        tagSuggestions.value = loadedTags;

        return loadedTags;
    } catch (error) {
        console.error("Error loading tags:", error);
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
    } catch (error) {
        console.error("Error loading tag suggestions:", error);
        tagSuggestions.value = [];
    } finally {
        loadingTagSuggestions.value = false;
    }
}, 250);

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

const selectCountry = (country) => {
    const countryId = country?.id || "";

    if (String(selectedCountry.value) !== String(countryId)) {
        selectedCity.value = "";
        cities.value = [];
    }

    selectedCountry.value = countryId;
    countrySearch.value = country?.translation?.name || "";
    showDropdown.value = false;
};

const closeCountryDropdown = () => {
    showDropdown.value = false;
};

const handleCategoryUpdate = (value) => {
    selectedCategory.value = value;
    closeCountryDropdown();
};

const handleSubCategoryUpdate = (value) => {
    selectedSubCategory.value = value;
    closeCountryDropdown();
};

const handleTagsUpdate = (value) => {
    selectedTags.value = Array.isArray(value) ? [...value] : [];
    closeCountryDropdown();
};

const handleCityUpdate = (value) => {
    selectedCity.value = value;
    closeCountryDropdown();
};

const handleFromDateUpdate = (value) => {
    fromDate.value = value;
    closeCountryDropdown();
};

const handleToDateUpdate = (value) => {
    toDate.value = value;
    closeCountryDropdown();
};

const handleMainCategoryChange = async () => {
    closeCountryDropdown();
    await onMainCategoryChange();
};

const handleSearchClick = () => {
    closeCountryDropdown();
    currentPage.value = 1;
    search(true, 1);
};

const scrollToEventsSearch = async () => {
    await nextTick();

    eventsSearchSectionRef.value?.scrollIntoView({
        behavior: "smooth",
        block: "start",
    });

    window.setTimeout(() => {
        eventsSearchRef.value?.focusInput?.();
    }, 550);
};

const buildHomeEventSearchQuery = () => eventFiltersToQuery({
    type: selectedType.value,
    seed: discoverySeed.value,
    searchQuery: searchQuery.value,
    categoryId: selectedCategory.value,
    subCategoryId: selectedSubCategory.value,
    countryId: selectedCountry.value,
    cityId: selectedCity.value,
    tagsIds: selectedTags.value,
    fromDate: fromDate.value,
    toDate: toDate.value,
});

const goToMoreSearchResults = () => {
    router.push({
        path: `/${lang}/events`,
        query: buildHomeEventSearchQuery(),
    });
};

const handleTypeChange = (value) => {
    if (value === selectedType.value || loading.value) return;

    selectedType.value = value;
    currentPage.value = 1;
    void search(true, 1);
};

const handleClickOutsideFilters = (event) => {
    if (!filtersSectionRef.value) return;

    if (!filtersSectionRef.value.contains(event.target)) {
        closeCountryDropdown();
    }
};

const loadCities = async () => {
    if (!selectedCountry.value) {
        cities.value = [];
        selectedCity.value = "";
        return;
    }

    try {
        cities.value = await LocationService.getCitiesByCountry(selectedCountry.value);
        selectedCity.value = "";
    } catch (error) {
        console.error("Error loading cities:", error);
        cities.value = [];
        selectedCity.value = "";
    }
};

const onMainCategoryChange = async () => {
    selectedSubCategory.value = "";
    subCategories.value = [];

    if (!selectedCategory.value) return;

    loadingSubCategories.value = true;

    try {
        const res = await CategoryService.getSubCategoriesByCategory(selectedCategory.value);
        subCategories.value = res.data?.data || [];
    } catch (error) {
        console.error("Error loading sub-categories:", error);
        subCategories.value = [];
    } finally {
        loadingSubCategories.value = false;
    }
};

const renderMarkersOnMaps = (events) => {
    if (!mapService) return;

    if (mapService.map) {
        mapService.addEventMarkers(events, mapService.map, false);
    }

    if (fullscreen.value && mapService.fullMap) {
        mapService.addEventMarkers(events, mapService.fullMap, true);
    }
};

const setCurrentLocationFromDevice = () => {
    return new Promise((resolve) => {
        if (hasTriedUserLocation.value) {
            resolve(false);
            return;
        }

        hasTriedUserLocation.value = true;

        if (!navigator.geolocation) {
            console.warn("Geolocation is not supported by this browser.");
            resolve(false);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                marker.value = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                };

                resolve(true);
            },
            (error) => {
                console.warn("User location error:", error.message);
                marker.value = { ...DEFAULT_LOCATION };
                resolve(false);
            },
            {
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 60000,
            }
        );
    });
};

const ensureMapInitialized = async () => {
    if (isMapReady.value || isMapLoading.value) return;

    mapError.value = "";
    hasRequestedMapInit.value = true;
    canInitMap.value = true;
    isMapLoading.value = true;

    try {
        await setCurrentLocationFromDevice();

        await nextTick();
        await waitForNextPaint();

        const mapContainer = await waitForContainerReady("map-main");

        if (!mapContainer) {
            throw new Error("Map container is not ready.");
        }

        if (!mapService) {
            const module = await import("@/services/MapService/MapService.js");
            mapService = new module.default(marker);
        }

        await mapService.initMap(mapContainer, 6);
        mapService.refreshMap();

        await waitForNextPaint();

        mapService.refreshMap();

        if (mapService.map) {
            if (!hasMapErrorListener) {
                mapService.map.on("error", () => {
                    if (!isMapReady.value) {
                        mapError.value = t("homeAudit.map.tilesSlow");
                    }
                });

                hasMapErrorListener = true;
            }

            mapService.map.once("idle", () => {
                isMapReady.value = true;
                mapError.value = "";
            });

            setTimeout(() => {
                if (!isMapReady.value && mapService?.map) {
                    mapService.refreshMap();
                    isMapReady.value = true;
                }
            }, 1200);
        } else {
            isMapReady.value = true;
        }

        if (displayedResults.value.length > 0) {
            renderMarkersOnMaps(mapEvents.value);
        }
    } catch (error) {
        console.error("Error loading map:", error);
        hasRequestedMapInit.value = false;
        canInitMap.value = false;
        mapError.value = t("homeAudit.map.loadError");
    } finally {
        isMapLoading.value = false;
    }
};

const handleMapViewportEnter = () => {
    if (!hasRequestedMapInit.value) {
        void ensureMapInitialized();
    }
};

const handleManualMapLoad = () => {
    void ensureMapInitialized();
};

const search = async (isInitial = false, page = currentPage.value) => {
    if (!searched.value && !isInitial) return;

    loading.value = true;

    try {
        const requestedPage = Number(page) || 1;
        const response = await EventService.searchEvents({
            type: selectedType.value,
            seed: discoverySeed.value,
            countryId: selectedCountry.value || null,
            cityId: selectedCity.value || null,
            categoryId: selectedCategory.value || null,
            subCategoryId: selectedSubCategory.value || null,
            tagsIds: selectedTags.value,
            fromDate: fromDate.value || null,
            toDate: toDate.value || null,
            searchQuery: searchQuery.value?.trim() || null,
            page: requestedPage,
            perPage: perPage.value,
        });
        const paginator = normalizePaginatedResponse(response, perPage.value);

        displayedResults.value = paginator.results.map(normalizeDiscoveryResult);
        discoverySeed.value = paginator.seed || discoverySeed.value;
        currentPage.value = paginator.currentPage;
        totalPages.value = paginator.lastPage;
        totalResults.value = paginator.total;
        resultFrom.value = paginator.from;
        resultTo.value = paginator.to;

        await nextTick();

        renderMarkersOnMaps(mapEvents.value);
    } catch (error) {
        console.error("Search error:", error);
        displayedResults.value = [];
        resetPaginationMeta();
        renderMarkersOnMaps([]);
    } finally {
        loading.value = false;
        searched.value = true;
    }
};

const handlePageChange = async (page) => {
    const nextPage = Number(page);

    if (!nextPage || nextPage < 1 || nextPage > totalPages.value) return;
    if (nextPage === currentPage.value) return;

    currentPage.value = nextPage;
    await search(true, nextPage);

    await nextTick();

    document
        .querySelector("#events-results")
        ?.scrollIntoView({ behavior: "smooth", block: "start" });
};

const handleMarkerEvents = (event) => {
    const eventsFromMap = event.detail?.events || [];

    selectedType.value = "event";
    displayedResults.value = eventsFromMap.map(normalizeDiscoveryResult);

    renderMarkersOnMaps(mapEvents.value);

    currentPage.value = 1;
    totalPages.value = 1;
    totalResults.value = displayedResults.value.length;
    resultFrom.value = displayedResults.value.length ? 1 : null;
    resultTo.value = displayedResults.value.length;
    searched.value = true;
    loading.value = false;
};

const handleEventMarkerClick = (event) => {
    const { slug } = event.detail;

    if (!slug) return;

    const currentLang = localStorage.getItem("language") || "ar";
    router.push(`/${currentLang}/single_event/${slug}`);
};

const openFullscreen = async () => {
    fullscreen.value = true;

    await ensureMapInitialized();

    if (mapError.value) return;

    await nextTick();
    await waitForNextPaint();

    if (!mapService) return;

    const fullContainer = await waitForContainerReady("map-fullscreen");

    if (!fullContainer) return;

    await mapService.openFullscreen(fullContainer, 6);
    mapService.refreshFullscreenMap();

    if (displayedResults.value.length > 0 && mapService.fullMap) {
        mapService.addEventMarkers(mapEvents.value, mapService.fullMap, true);
    }
};

const closeFullscreen = () => {
    fullscreen.value = false;
    mapService?.closeFullscreen();
};

const loadPlans = async () => {
    loadingPlans.value = true;

    try {
        plans.value = await PlanService.getAllPlans();
    } catch (error) {
        console.error("Error loading plans:", error);
        plans.value = [];
    } finally {
        loadingPlans.value = false;
    }
};

const fetchProfile = async () => {
    const token = localStorage.getItem("auth_token");

    if (!token) return;

    try {
        const res = await AuthService.getProfile();

        if (res.data.status === "success") {
            const userData = res.data.data.user;

            licenceName.value = userData.licenceType?.name || "free";

            localStorage.setItem("licence_name", licenceName.value);
            localStorage.setItem("user_role", userData.role);

            const requiredFields = {
                phone: userData.phone,
                country: userData.country,
                position: userData.position,
                date_of_birth: userData.date_of_birth,
            };

            const missingFields = Object.entries(requiredFields)
                .filter(([, value]) => !value || value === "")
                .map(([key]) => key);

            const isFilledProfile = missingFields.length === 0;

            localStorage.setItem("isFilledProfile", isFilledProfile ? "true" : "false");
            localStorage.setItem("missingFields", JSON.stringify(missingFields));

            isLoggedIn.value = true;
        }
    } catch (error) {
        console.log("Failed to fetch profile:", error);

        licenceName.value = "free";
        isLoggedIn.value = false;
    }
};

const checkProfileWarning = () => {
    const isFilled = localStorage.getItem("isFilledProfile");

    if (isFilled === "false") {
        showProfileToast.value = true;
        startProgressBar();
    }
};

const startProgressBar = () => {
    const startTime = Date.now();
    const endTime = startTime + TOAST_DURATION;

    progressInterval = setInterval(() => {
        const now = Date.now();
        const remaining = endTime - now;
        const percentage = Math.max(0, (remaining / TOAST_DURATION) * 100);

        progressWidth.value = `${percentage}%`;

        if (percentage <= 0) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
    }, 16);

    toastTimer = setTimeout(() => {
        showProfileToast.value = false;

        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
    }, TOAST_DURATION);
};

const closeToast = () => {
    showProfileToast.value = false;

    if (toastTimer) {
        clearTimeout(toastTimer);
        toastTimer = null;
    }
};

const goToProfile = () => {
    const currentLang = localStorage.getItem("lang") || "en";
    router.push(`/${currentLang}/profile`);
};

const openPlan = (slug) => {
    router.push(`/${lang}/plan/${slug}`);
};

const scheduleNonCritical = (callback, fallbackDelay = 180) => {
    if (typeof window !== "undefined" && "requestIdleCallback" in window) {
        const handle = window.requestIdleCallback(() => callback(), { timeout: 2000 });

        scheduledTasks.push({ type: "idle", handle });

        return;
    }

    const handle = window.setTimeout(callback, fallbackDelay);

    scheduledTasks.push({ type: "timeout", handle });
};

const waitForNextPaint = () =>
    new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

const waitForContainerReady = async (containerId, retries = 20) => {
    for (let i = 0; i < retries; i += 1) {
        const container = document.getElementById(containerId);

        if (container) {
            const rect = container.getBoundingClientRect();
            const style = window.getComputedStyle(container);
            const isVisible = style.display !== "none" && style.visibility !== "hidden";

            if (isVisible && rect.width > 0 && rect.height > 0) {
                return container;
            }
        }

        await waitForNextPaint();
    }

    return null;
};

const clearScheduledTasks = () => {
    for (const task of scheduledTasks) {
        if (task.type === "idle" && typeof window.cancelIdleCallback === "function") {
            window.cancelIdleCallback(task.handle);
            continue;
        }

        clearTimeout(task.handle);
    }

    scheduledTasks.length = 0;
};

const debouncedSearch = debounce(() => {
    if (!searched.value) return;

    currentPage.value = 1;
    void search(true, 1);
}, 450);

watch(countrySearch, (value) => {
    filterCountries();

    if (!String(value || "").trim()) {
        selectedCountry.value = "";
        selectedCity.value = "";
        cities.value = [];
    }
});

watch(selectedCountry, () => {
    void loadCities();
});

watch(
    [selectedCategory, selectedSubCategory, selectedTags, selectedCountry, selectedCity, fromDate, toDate, searchQuery],
    () => {
        debouncedSearch();
    }
);

onMounted(() => {
    document.addEventListener("marker-events-loaded", handleMarkerEvents);
    document.addEventListener("event-marker-clicked", handleEventMarkerClick);
    document.addEventListener("mousedown", handleClickOutsideFilters);

    checkProfileWarning();

    scheduleNonCritical(async () => {
        await fetchProfile();
        checkProfileWarning();
    }, 50);

    scheduleNonCritical(async () => {
        try {
            const [loadedCategories, loadedCountries] = await Promise.all([
                CategoryService.getAllCategories(),
                LocationService.getAllCountries(),
            ]);

            categories.value = loadedCategories;
            countries.value = loadedCountries;
            filteredCountries.value = [...loadedCountries];
        } catch (error) {
            console.error("Error loading initial data:", error);
        }
    }, 120);

    scheduleNonCritical(() => {
        void loadTags();
    }, 140);

    scheduleNonCritical(() => {
        void loadTrendingEvents();
    }, 180);

    scheduleNonCritical(() => {
        void loadPlans();
    }, 240);
});

onUnmounted(() => {
    document.removeEventListener("marker-events-loaded", handleMarkerEvents);
    document.removeEventListener("event-marker-clicked", handleEventMarkerClick);
    document.removeEventListener("mousedown", handleClickOutsideFilters);

    if (toastTimer) {
        clearTimeout(toastTimer);
        toastTimer = null;
    }

    if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
    }

    debouncedSearch.cancel();
    fetchTagSuggestions.cancel();
    clearScheduledTasks();

    if (mapService) {
        mapService.destroy();
        mapService = null;
        hasMapErrorListener = false;
    }
});
</script>

<style scoped>
.hero-navbar :deep(.navbar-wrap) {
    position: relative !important;
    top: auto !important;
}

.home-discovery-section {
    position: relative;
    width: 100%;
    padding: 44px 0 48px;
    background: linear-gradient(180deg, #FFFFFF 0%, #F4F9FE 100%);
}

.home-discovery-container,
.home-results-container {
    box-sizing: border-box;
    width: 100%;
    max-width: 1760px;
    margin-inline: auto;
    padding-inline: 40px;
}

.home-events-search-target {
    scroll-margin-top: 110px;
}

.home-discovery-search {
    position: relative;
    z-index: 30;
    width: 100%;
    max-width: 1020px;
    margin: 0 auto 28px;
}

.home-discovery-workspace {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 24px;
    align-items: start;
    direction: inherit;
}

.home-discovery-filters-column,
.home-discovery-map-column,
.home-discovery-trending-column {
    width: 100%;
    min-width: 0;
}

.home-discovery-filters-column,
.home-discovery-trending-column {
    position: static;
}

.home-discovery-filters {
    position: relative;
    z-index: 30;
}

.home-mobile-filter-toggle {
    display: none;
    width: 100%;
    min-height: 48px;
    margin: 0 0 14px;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border: 1px solid var(--scemory-border);
    border-radius: 16px;
    background: #FFFFFF;
    color: var(--scemory-primary);
    padding: 0 16px;
    font-size: 14px;
    font-weight: 800;
    box-shadow: var(--scemory-shadow-sm);
}

.home-results-band {
    padding: 48px 0 56px;
    background:
        radial-gradient(circle at 85% 0%, rgba(48, 168, 255, 0.08), transparent 26rem),
        linear-gradient(180deg, #F4F9FE 0%, var(--scemory-surface-soft) 100%);
}

@media (min-width: 1400px) {
    .home-discovery-workspace {
        grid-template-columns: 270px minmax(0, 1fr) 300px;
        gap: 24px;
    }

    .home-discovery-filters-column,
    .home-discovery-trending-column {
        position: sticky;
        top: 104px;
        z-index: 25;
    }

    .home-discovery-trending-column {
        display: flex;
    }
}

@media (min-width: 992px) and (max-width: 1399px) {
    .home-discovery-container,
    .home-results-container {
        padding-inline: 28px;
    }

    .home-discovery-workspace {
        grid-template-columns: 250px minmax(0, 1fr);
        gap: 22px;
    }

    .home-discovery-filters-column {
        position: sticky;
        top: 104px;
        z-index: 25;
    }

    .home-discovery-trending-column {
        grid-column: 1 / -1;
    }
}

@media (max-width: 991px) {
    .home-discovery-section {
        padding: 34px 0 34px;
    }

    .home-discovery-container,
    .home-results-container {
        padding-inline: 20px;
    }

    .home-discovery-search {
        margin-bottom: 16px;
    }

    .home-discovery-workspace {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .home-discovery-filters-column {
        position: static;
    }

    .home-discovery-trending-column {
        position: static;
    }
}

@media (max-width: 640px) {
    .home-mobile-filter-toggle {
        display: flex;
    }

    .home-discovery-filters-column {
        display: none;
    }

    .home-discovery-filters-column.is-open {
        display: block;
    }
}

@media (max-width: 480px) {
    .home-discovery-container,
    .home-results-container {
        padding-inline: 14px;
    }
}

.search-events-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 48px;
    padding: 0 32px;

    border: 1px solid #1597d4;
    border-radius: 999px;

    background: #1597d4;
    color: #ffffff;

    font-size: 14px;
    font-weight: 700;

    cursor: pointer;

    box-shadow: 0 12px 30px rgba(21, 151, 212, 0.3);

    transition:
        transform 0.3s ease,
        background-color 0.3s ease,
        border-color 0.3s ease,
        box-shadow 0.3s ease;
}

.search-events-btn:hover {
    background: #28a9e6;
    border-color: #28a9e6;
    transform: translateY(-2px);

    box-shadow: 0 14px 34px rgba(21, 151, 212, 0.36);
}

.search-events-btn:focus-visible {
    outline: 3px solid rgba(21, 151, 212, 0.25);
    outline-offset: 3px;
}
</style>
