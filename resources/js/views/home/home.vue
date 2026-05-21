<template>
  <div class="min-h-screen bg-gray-50 font-sans">
    <MapSection
      :fullscreen="fullscreen"
      :is-map-ready="isMapReady"
      :is-map-loading="isMapLoading"
      :map-error="mapError"
      :can-init-map="canInitMap"
      @map-viewport-enter="handleMapViewportEnter"
      @load-map="handleManualMapLoad"
      @open-fullscreen="openFullscreen"
      @close-fullscreen="closeFullscreen"
    />

    <FiltersSection
      :categories="categories"
      :selected-category="selectedCategory"
      :sub-categories="subCategories"
      :selected-sub-category="selectedSubCategory"
      :loading-sub-categories="loadingSubCategories"
      :country-search="countrySearch"
      :show-dropdown="showDropdown"
      :filtered-countries="filteredCountries"
      :selected-country="selectedCountry"
      :cities="cities"
      :selected-city="selectedCity"
      :from-date="fromDate"
      :to-date="toDate"
      @update:selected-category="selectedCategory = $event"
      @update:selected-sub-category="selectedSubCategory = $event"
      @update:country-search="countrySearch = $event"
      @update:selected-city="selectedCity = $event"
      @update:from-date="fromDate = $event"
      @update:to-date="toDate = $event"
      @country-focus="onCountryFocus"
      @select-country="selectCountry"
      @category-changed="onMainCategoryChange"
      @search="search(true)"
    />

    <EventsSection
      :searched="searched"
      :loading="loading"
      :displayed-events="displayedEvents"
      :paginated-events="paginatedEvents"
      :visible-pages="visiblePages"
      :current-page="currentPage"
      :total-pages="totalPages"
      :fallback-image="fallbackImage"
      :format-date="formatDate"
      :lang="lang"
      @update:current-page="currentPage = $event"
    />

    <PlansSection
      :licence-name="licenceName"
      :plans="plans"
      :loading-plans="loadingPlans"
      @open-plan="openPlan"
    />
  </div>

  <Transition name="slide-fade">
    <div
      v-if="showProfileToast"
      class="fixed top-4 left-4 z-[9999] w-[280px] bg-white shadow-lg rounded-lg border border-gray-200 overflow-hidden cursor-pointer"
      @click="goToProfile"
    >
      <div class="flex justify-end items-center px-3 py-2 bg-yellow-50 border-b">
        <button @click.stop="closeToast" class="text-gray-400 hover:text-red-500 text-sm transition-colors">x</button>
      </div>

      <div class="p-3 text-xs text-gray-600">
        Your profile is not complete. Please update:
        <ul class="mt-1.5 list-disc list-inside text-[11px] text-gray-500">
          <li v-for="field in missingFieldsList" :key="field">
            {{ fieldLabels[field] || field }}
          </li>
        </ul>

        <p class="mt-2 text-blue-600 font-medium text-[11px]">Click to complete -></p>
      </div>

      <div class="h-1 bg-gray-100">
        <div class="h-full bg-yellow-500 transition-all duration-100 ease-linear" :style="{ width: progressWidth }"></div>
      </div>
    </div>
  </Transition>
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
import debounce from "lodash/debounce";

import { CategoryService } from "@/services/CategoryService/CategoryService";
import { LocationService } from "@/services/LocationService/LocationService";
import { EventService } from "@/services/EventService/EventService";
import { PlanService } from "@/services/planService/planService";
import { AuthService } from "@/services/AuthService/AuthService";

const MapSection = defineAsyncComponent(() => import("./components/MapSection.vue"));
const FiltersSection = defineAsyncComponent(() => import("./components/FiltersSection.vue"));
const EventsSection = defineAsyncComponent(() => import("./components/EventsSection.vue"));
const PlansSection = defineAsyncComponent(() => import("./components/PlansSection.vue"));

const router = useRouter();
const lang = localStorage.getItem("language") || "en";

const marker = ref({ lat: 30.0444, lng: 31.2357 });
const fullscreen = ref(false);
const isMapReady = ref(false);
const isMapLoading = ref(false);
const canInitMap = ref(false);
const hasRequestedMapInit = ref(false);
const mapError = ref("");

const licenceName = ref("free");
const isLoggedIn = ref(false);
const searchQuery = ref("");

const displayedEvents = shallowRef([]);
const categories = shallowRef([]);
const countries = shallowRef([]);
const cities = shallowRef([]);
const filteredCountries = shallowRef([]);
const subCategories = shallowRef([]);
const plans = shallowRef([]);

const countrySearch = ref("");
const showDropdown = ref(false);
const selectedCategory = ref("");
const selectedCountry = ref("");
const selectedCity = ref("");
const fromDate = ref("");
const toDate = ref("");
const selectedSubCategory = ref("");

const loadingSubCategories = ref(false);
const loading = ref(false);
const searched = ref(false);
const currentPage = ref(1);
const loadingPlans = ref(false);

const itemsPerPage = 8;
const maxVisible = 5;
const fallbackImage =
  "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&q=80&w=800";

const showProfileToast = ref(false);
const progressWidth = ref("100%");
const TOAST_DURATION = 7000;

let mapService = null;
let toastTimer = null;
let progressInterval = null;
let hasMapErrorListener = false;
const scheduledTasks = [];

const totalPages = computed(() => Math.ceil(displayedEvents.value.length / itemsPerPage));

const visiblePages = computed(() => {
  const total = totalPages.value;
  let start = Math.max(currentPage.value - Math.floor(maxVisible / 2), 1);
  let end = start + maxVisible - 1;

  if (end > total) {
    end = total;
    start = Math.max(end - maxVisible + 1, 1);
  }

  const pages = [];
  for (let i = start; i <= end; i += 1) pages.push(i);
  return pages;
});

const paginatedEvents = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  return displayedEvents.value.slice(start, start + itemsPerPage);
});

const missingFieldsList = computed(() => {
  const data = localStorage.getItem("missingFields");
  return data ? JSON.parse(data) : [];
});

const fieldLabels = {
  phone: "Phone",
  country: "Country",
  position: "Position",
  date_of_birth: "Date of birth",
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

const toMediaUrl = (pathValue) => {
  if (!pathValue) return null;
  if (/^https?:\/\//i.test(pathValue)) return pathValue;
  return `/storage/${pathValue}`;
};

const normalizeEvent = (ev) => ({
  id: ev.id || ev._id,
  slug: ev.slug,
  translation: ev.translation,
  title: ev.title || "Untitled event",
  start_date: ev.start_date,
  city: ev.city?.translation?.name || ev.city || "Not specified",
  category_name: ev.sub_categorey?.translation?.name || "Event",
  image_url: toMediaUrl(ev.first_image?.full_url),
  image_webp_url: toMediaUrl(ev.first_image?.webp_url || ev.first_image?.full_url_webp),
  lattitude: ev.lattitude,
  langitude: ev.langitude,
});

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
  selectedCountry.value = country.id;
  countrySearch.value = country.translation?.name || "";
  showDropdown.value = false;
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

const ensureMapInitialized = async () => {
  if (isMapReady.value || isMapLoading.value) return;

  mapError.value = "";
  hasRequestedMapInit.value = true;
  canInitMap.value = true;
  isMapLoading.value = true;

  try {
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
            mapError.value = "Map tiles are taking too long to render.";
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

    if (displayedEvents.value.length > 0) {
      renderMarkersOnMaps(displayedEvents.value);
    }
  } catch (error) {
    console.error("Error loading map:", error);
    hasRequestedMapInit.value = false;
    canInitMap.value = false;
    mapError.value = "Unable to load map right now.";
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

const search = async (isInitial = false) => {
  if (!searched.value && !isInitial) return;

  loading.value = true;

  try {
    const result = await EventService.searchEvents({
      cityId: selectedCity.value || null,
      subCategoryId: selectedSubCategory.value || null,
      fromDate: fromDate.value || null,
      toDate: toDate.value || null,
      searchQuery: searchQuery.value?.trim() || null,
    });

    displayedEvents.value = (Array.isArray(result) ? result : []).map(normalizeEvent);
    currentPage.value = 1;

    await nextTick();
    renderMarkersOnMaps(displayedEvents.value);
  } catch (error) {
    console.error("Search error:", error);
    displayedEvents.value = [];
  } finally {
    loading.value = false;
    searched.value = true;
  }
};

const handleMarkerEvents = (event) => {
  const eventsFromMap = event.detail?.events || [];
  displayedEvents.value = eventsFromMap.map(normalizeEvent);

  renderMarkersOnMaps(displayedEvents.value);
  currentPage.value = 1;
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

  if (displayedEvents.value.length > 0 && mapService.fullMap) {
    mapService.addEventMarkers(displayedEvents.value, mapService.fullMap, true);
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
  void search();
}, 450);

watch(countrySearch, filterCountries);

watch(selectedCountry, () => {
  void loadCities();
});

watch(
  [selectedCategory, selectedSubCategory, selectedCountry, selectedCity, fromDate, toDate, searchQuery],
  () => {
    debouncedSearch();
  }
);

onMounted(() => {
  document.addEventListener("marker-events-loaded", handleMarkerEvents);
  document.addEventListener("event-marker-clicked", handleEventMarkerClick);

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
    void loadPlans();
  }, 240);
});

onUnmounted(() => {
  document.removeEventListener("marker-events-loaded", handleMarkerEvents);
  document.removeEventListener("event-marker-clicked", handleEventMarkerClick);

  if (toastTimer) {
    clearTimeout(toastTimer);
    toastTimer = null;
  }

  if (progressInterval) {
    clearInterval(progressInterval);
    progressInterval = null;
  }

  debouncedSearch.cancel();
  clearScheduledTasks();

  if (mapService) {
    mapService.destroy();
    mapService = null;
    hasMapErrorListener = false;
  }
});
</script>

<style scoped>
.slide-fade-enter-active {
  transition: all 0.3s ease-out;
}

.slide-fade-leave-active {
  transition: all 0.2s ease-in;
}

.slide-fade-enter-from {
  transform: translateX(-20px);
  opacity: 0;
}

.slide-fade-leave-to {
  transform: translateX(-20px);
  opacity: 0;
}
</style>
