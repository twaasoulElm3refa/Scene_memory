<template>
  <div class="min-h-screen bg-gray-50 font-sans">
    <!-- Hero Map Section -->
    <div class="relative h-[500px] md:h-[600px] bg-gray-900 overflow-hidden">
      <div
        id="map"
        class="absolute inset-0 bg-cover bg-center opacity-70"
        :style="{
          backgroundImage: `url('https://images.unsplash.com/photo-1524666041070-9d87656c25bb?auto=format&fit=crop&q=80')`,
        }"
      ></div>
      <div
        class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none"
      ></div>
    </div>

    <!-- شريط الفلاتر -->
    <div class="bg-white border-b ml-10 shadow-sm top-0 z-10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-2 items-end"
        >
          <!-- Category -->
          <div class="space-y-1">
            <select
              v-model="selectedCategory"
              @change="onMainCategoryChange"
              class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition"
            >
              <option value="">{{ $t("filters.allCategories") }}</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>

          <!-- SubCategory -->
          <div v-if="subCategories.length > 0 || selectedCategory">
            <select
              v-model="selectedSubCategory"
              :disabled="loadingSubCategories"
              class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition"
            >
              <option value="">{{ $t("filters.allSubCategories") }}</option>
              <option v-for="sub in subCategories" :key="sub.id" :value="sub.id">
                {{ sub.name }}
              </option>
            </select>
          </div>

          <!-- Country -->
          <div>
            <select
              v-model="selectedCountry"
              @change="loadCities"
              class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition"
            >
              <option value="">{{ $t("filters.country") }}</option>
              <option v-for="country in countries" :key="country.id" :value="country.id">
                {{ country.name }}
              </option>
            </select>
          </div>

          <!-- City -->
          <div>
            <select
              v-model="selectedCity"
              :disabled="!selectedCountry"
              class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors text-gray-900"
            >
              <option class="text-gray-900" value="">{{ $t("filters.city") }}</option>
              <option
                v-for="city in cities"
                :key="city.id"
                :value="city.id"
                class="text-gray-900"
              >
                {{ city.name }}
              </option>
            </select>
          </div>

          <!-- From Date -->
          <div>
            <input
              v-model="fromDate"
              type="date"
              class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition"
            />
          </div>

          <!-- To Date -->
          <div>
            <input
              v-model="toDate"
              type="date"
              class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition"
            />
          </div>

          <!-- زر البحث -->
          <div class="flex items-end">
            <button
              @click="search(true)"
              class="w-full sm:w-auto px-4 py-2 text-sm rounded-full bg-gradient-to-r from-blue-600 to-blue-700 text-white hover:from-blue-700 hover:to-blue-800 transition shadow-sm hover:shadow-md active:scale-95"
            >
              {{ $t("common.search") }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- نتائج البحث / الخريطة -->
    <section v-if="searched" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
      <div class="flex flex-col md:flex-row md:justify-between md:items-end mb-12">
        <div>
          <div
            class="inline-flex items-center gap-2 text-blue-600 bg-blue-50 px-4 py-2 rounded-full text-sm font-medium mb-4"
          >
            <span class="relative flex h-2 w-2">
              <span
                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"
              ></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-600"></span>
            </span>
            {{ $t("events.latestEvents") }}
          </div>
          <h2 class="text-4xl font-bold text-gray-900 mb-3">
            {{ $t("events.recentMemories") }}
          </h2>
          <p class="text-gray-500 text-lg max-w-2xl">
            {{ $t("events.discoverAroundYou") }}
          </p>
        </div>
      </div>

      <!-- Circular Loading Indicator -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <div class="relative w-20 h-20">
          <div class="absolute inset-0 border-4 border-blue-200 rounded-full"></div>
          <div
            class="absolute inset-0 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"
          ></div>
        </div>
        <p class="mt-6 text-lg text-gray-600 font-medium">
          {{ $t("common.loadingEvents") }}
        </p>
      </div>

      <!-- No results -->
      <div
        v-else-if="displayedEvents.length === 0"
        class="text-center py-20 bg-gray-50 rounded-3xl"
      >
        <div class="text-7xl mb-6">🎭</div>
        <h3 class="text-2xl font-bold text-gray-900 mb-3">
          {{ $t("events.noEventsFound") }}
        </h3>
        <p class="text-lg text-gray-600">
          {{ $t("events.noMatchingEvents") }}
        </p>
      </div>

      <!-- Events Grid + Pagination -->
      <template v-else>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8">
          <div
            v-for="event in paginatedEvents"
            :key="event.slug || event.id"
            class="group bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1"
          >
            <div class="aspect-[4/3] relative overflow-hidden bg-gray-100">
              <img
                :src="event.image_url || fallbackImage"
                :alt="event.title"
                class="w-full h-full object-cover group-hover:scale-110 transition duration-700"
                loading="lazy"
              />
              <div class="absolute top-4 left-4 z-10">
                <span
                  class="bg-white/90 backdrop-blur-sm text-gray-900 text-xs font-bold px-3 py-1.5 rounded-full shadow-lg"
                >
                  {{ event.category_name || $t("events.event") }}
                </span>
              </div>
            </div>

            <div class="p-5">
              <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
                <span class="text-blue-600">📅</span>
                <span class="font-medium">
                  {{ $t("events.startDate") }} :
                  {{ formatDate(event.start_date || event.date) }}
                </span>
              </div>

              <h4
                class="text-right text-md mb-2 line-clamp-2 text-gray-900 group-hover:text-blue-600 transition-colors"
              >
                {{ event.title }}
              </h4>

              <div
                class="flex justify-between items-center pt-3 border-t border-gray-100"
              >
                <div class="flex items-center gap-1.5 text-sm text-gray-600">
                  <span></span>
                  <span class="font-medium">
                    {{ event.city || $t("common.notSpecified") }}
                  </span>
                </div>
                <a
                  :href="`/single_event/${event.slug}`"
                  class="text-blue-600 text-decoration-none hover:text-blue-800 text-sm font-medium flex items-center gap-1"
                >
                  {{ $t("common.details") }}
                </a>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-center mt-10 gap-2 mb-10">
          <button
            @click="currentPage = Math.max(1, currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-3 py-1.5 rounded-full text-sm font-medium transition-all duration-200 bg-white border border-gray-200 text-gray-700 hover:bg-blue-100 hover:border-blue-300 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ $t("pagination.previous") }}
          </button>

          <button
            v-for="page in visiblePages"
            :key="page"
            @click="currentPage = page"
            :class="[
              'px-3 py-1.5 rounded-full text-sm font-medium transition-all duration-200',
              currentPage === page
                ? 'bg-blue-600 text-white shadow-md shadow-blue-200'
                : 'bg-white border border-gray-200 text-gray-700 hover:bg-blue-100 hover:border-blue-300',
            ]"
          >
            {{ page }}
          </button>

          <button
            @click="currentPage = Math.min(totalPages, currentPage + 1)"
            :disabled="currentPage === totalPages || totalPages === 0"
            class="px-3 py-1.5 rounded-full text-sm font-medium transition-all duration-200 bg-white border border-gray-200 text-gray-700 hover:bg-blue-100 hover:border-blue-300 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ $t("pagination.next") }}
          </button>
        </div>
      </template>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed, watch } from "vue";
import MapService from "@/services/MapService.js";
import { CategoryService } from "@/services/CategoryService";
import { LocationService } from "@/services/LocationService";
import { EventService } from "@/services/EventService";
import { debounce } from "lodash";
import api from "@/services/ApiClient";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

const marker = ref({ lat: 30.0444, lng: 31.2357 });
const fullscreen = ref(false);

const searchQuery = ref("");
const displayedEvents = ref([]);
const categories = ref([]);
const countries = ref([]);
const cities = ref([]);

const selectedCategory = ref("");
const selectedCountry = ref("");
const selectedCity = ref("");
const fromDate = ref("");
const toDate = ref("");
const selectedSubCategory = ref("");
const subCategories = ref([]);
const loadingSubCategories = ref(false);
const loading = ref(false);
const searched = ref(false);
const currentPage = ref(1);
const itemsPerPage = 4;

let mapService = null;

// ====================== Map Markers Layer ======================
const eventMarkersLayer = ref(null);
const fullEventMarkersLayer = ref(null);

// ====================== Computed ======================
const totalPages = computed(() => Math.ceil(displayedEvents.value.length / itemsPerPage));

const maxVisible = 5;

const visiblePages = computed(() => {
  const total = totalPages.value;
  let start = Math.max(currentPage.value - Math.floor(maxVisible / 2), 1);
  let end = start + maxVisible - 1;

  if (end > total) {
    end = total;
    start = Math.max(end - maxVisible + 1, 1);
  }

  const pages = [];
  for (let i = start; i <= end; i++) pages.push(i);
  return pages;
});

const fallbackImage =
  "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&q=80&w=800";

const paginatedEvents = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  return displayedEvents.value.slice(start, end);
});

// ====================== Format Date ======================
const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  try {
    return new Date(dateStr).toLocaleDateString("ar-EG", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  } catch {
    return "—";
  }
};

// ====================== Add Markers to Map ======================
const addEventMarkers = (events, targetMap, layerRef) => {
  if (!targetMap) return;

  // Clear previous markers
  if (layerRef.value) {
    layerRef.value.clearLayers();
  } else {
    layerRef.value = L.layerGroup().addTo(targetMap);
  }

  if (!events?.length) return;

  events.forEach((event) => {
    const lat = parseFloat(event.lattitude);
    const lng = parseFloat(event.langitude);

    if (isNaN(lat) || isNaN(lng)) return;

    const marker = L.marker([lat, lng]);

    let popupContent = `
      <div class="text-right min-w-[180px]">
        <h3 class="font-bold text-base mb-1">${event.title || "فعالية بدون عنوان"}</h3>
        <p class="text-sm text-gray-600 mb-2">
          ${event.start_date ? formatDate(event.start_date) : "التاريخ غير محدد"}
        </p>
    `;

    if (event.image_url) {
      popupContent += `
        <img src="${event.image_url}" alt="${event.title}" class="w-full h-28 object-cover rounded mb-2">
      `;
    }

    popupContent += `
        <p class="text-sm mb-2">${event.city || "غير محدد"}</p>
        <a href="/single_event/${
          event.slug
        }" class="text-blue-600 hover:underline text-sm font-medium">
          عرض التفاصيل →
        </a>
      </div>
    `;

    marker.bindPopup(popupContent, {
      maxWidth: 240,
      className: "custom-event-popup",
    });

    marker.addTo(layerRef.value);
  });

  // Optional: fit bounds if multiple markers
  if (events.length > 1) {
    const group = L.featureGroup(layerRef.value.getLayers());
    targetMap.fitBounds(group.getBounds(), { padding: [60, 60] });
  }
};

// ====================== Lifecycle ======================
onMounted(async () => {
  mapService = new MapService(marker);

  // Initialize main map
  mapService.initMap("map", 6); // zoom 6 لمصر ككل

  try {
    categories.value = await CategoryService.getAllCategories();
    countries.value = await LocationService.getAllCountries();
  } catch (err) {
    console.error("Error loading initial data:", err);
  }

  document.addEventListener("marker-events-loaded", handleMarkerEvents);
});

onUnmounted(() => {
  document.removeEventListener("marker-events-loaded", handleMarkerEvents);
  if (mapService) {
    mapService.closeFullscreen();
  }
});

const onMainCategoryChange = async () => {
  selectedSubCategory.value = "";
  subCategories.value = [];

  if (!selectedCategory.value) return;

  loadingSubCategories.value = true;

  try {
    const res = await api.get(`/categories/${selectedCategory.value}/sub_categories/get`);
    subCategories.value = res.data.data || [];
  } catch (err) {
    console.error("Error loading sub-categories:", err);
    subCategories.value = [];
  } finally {
    loadingSubCategories.value = false;
    search();
  }
};

const loadCities = async () => {
  if (!selectedCountry.value) {
    cities.value = [];
    return;
  }
  try {
    cities.value = await LocationService.getCitiesByCountry(selectedCountry.value);
    selectedCity.value = "";
  } catch (err) {
    console.error("Error loading cities:", err);
  }
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

    displayedEvents.value = (Array.isArray(result) ? result : []).map((ev) => ({
      id: ev.id,
      slug: ev.slug,
      title: ev.title || "فعالية بدون عنوان",
      start_date: ev.start_date,
      city: ev.city?.name || "غير محدد",
      category_name: ev.category?.name || ev.category_name || "فعالية",
      image: ev.image || null,
      image_url: ev.image_url || null,
      lattitude: ev.lattitude,
      langitude: ev.langitude,
    }));
  } catch (err) {
    console.error("Search error:", err);
    displayedEvents.value = [];
  } finally {
    loading.value = false;
    searched.value = true;
  }
};

const handleMarkerEvents = (e) => {
  const eventsFromMap = e.detail?.events || [];

  displayedEvents.value = eventsFromMap.map((ev) => ({
    id: ev.id || ev._id,
    slug: ev.slug,
    title: ev.title || "فعالية بدون عنوان",
    start_date: ev.start_date,
    city: ev.city?.name || ev.city || "غير محدد",
    category_name: ev.category_name || "من الخريطة",
    image_url: ev.image_url || null,
    lattitude: ev.lattitude || ev.latitude,
    langitude: ev.langitude || ev.longitude,
  }));

  // Add markers to main map
  if (mapService?.map) {
    addEventMarkers(displayedEvents.value, mapService.map, eventMarkersLayer);
  }

  // Add markers to fullscreen map if open
  if (fullscreen.value && mapService?.fullMap) {
    addEventMarkers(displayedEvents.value, mapService.fullMap, fullEventMarkersLayer);
  }

  currentPage.value = 1;
  searched.value = true;
  loading.value = false;
};

watch(
  [
    selectedCategory,
    selectedSubCategory,
    selectedCountry,
    selectedCity,
    fromDate,
    toDate,
    searchQuery,
  ],
  () => {
    debouncedSearch();
  },
  { deep: true }
);

const debouncedSearch = debounce(() => {
  search();
}, 500);

const openFullscreen = async () => {
  fullscreen.value = true;
  await nextTick();
  mapService.openFullscreen("map-full", 6);

  // Re-add markers to the new fullscreen map
  if (displayedEvents.value.length > 0) {
    addEventMarkers(displayedEvents.value, mapService.fullMap, fullEventMarkersLayer);
  }
};

const closeFullscreen = () => {
  fullscreen.value = false;
  mapService.closeFullscreen();
};
</script>

<style scoped>
.btn-small {
  background-color: #4b5563;
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 9999px;
  font-weight: 500;
  transition: all 0.2s;
  min-width: 90px;
}

.btn-small:hover:not(:disabled) {
  background-color: #374151;
}

/* تحسين شكل الـ Tabs */
button {
  min-width: 44px;
}
/* يمكنك إضافة ستايل للـ popup إذا أردت */
.leaflet-popup-content-wrapper {
  border-radius: 12px;
}

.custom-event-popup .leaflet-popup-content {
  margin: 0;
  font-family: inherit;
}

/* في App.vue أو main.css أو component مع scoped=false */
.custom-event-marker {
  position: relative;
  text-align: center;
}

.custom-event-marker .marker-label {
  position: absolute;
  bottom: 100%; /* فوق الدبوس */
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0, 0, 0, 0.75);
  color: white;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  white-space: nowrap;
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
  pointer-events: none;
  margin-bottom: 6px;
  z-index: 1000;
}

.custom-event-marker .marker-pin {
  font-size: 32px; /* حجم الدبوس */
  color: #e53e3e; /* أحمر قوي */
  line-height: 1;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
}

/* تحسين الـ popup إذا أردت */
.leaflet-popup.custom-event-popup .leaflet-popup-content-wrapper {
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}
</style>
