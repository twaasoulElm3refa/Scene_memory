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
              class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition"
            >
              <option value="">{{ $t("filters.city") }}</option>
              <option v-for="city in cities" :key="city.id" :value="city.id">
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
                :src="
                  event.image ||
                  'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&q=80&w=800'
                "
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
                  class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1"
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

    <!-- قسم من نحن -->
    <div class="py-16 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <div class="text-center mb-12">
          <h2 class="text-3xl font-bold text-black sm:text-4xl">
            {{ $t("about.title") }}
          </h2>
          <p class="mt-4 text-lg text-black max-w-3xl mx-auto">
            {{ $t("about.subtitle") }}
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
          <div>
            <div class="text-black space-y-6">
              <p class="text-lg">
                {{ $t("about.text1") }}
              </p>

              <div class="space-y-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 mt-1">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center">
                      <div class="w-2 h-2 rounded-full bg-black"></div>
                    </div>
                  </div>
                  <p class="ml-3 text-black">
                    {{ $t("about.features.immersiveAlbums") }}
                  </p>
                </div>

                <div class="flex items-start">
                  <div class="flex-shrink-0 mt-1">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center">
                      <div class="w-2 h-2 rounded-full bg-black"></div>
                    </div>
                  </div>
                  <p class="ml-3 text-black">
                    {{ $t("about.features.aiOrganize") }}
                  </p>
                </div>

                <div class="flex items-start">
                  <div class="flex-shrink-0 mt-1">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center">
                      <div class="w-2 h-2 rounded-full bg-black"></div>
                    </div>
                  </div>
                  <p class="ml-3 text-black">
                    {{ $t("about.features.privacy") }}
                  </p>
                </div>
              </div>
            </div>

            <div class="mt-10">
              <a
                href="#"
                class="inline-flex items-center px-6 py-3 rounded-lg bg-gradient-to-r from-gray-400 to-gray-600 text-black font-medium hover:from-gray-500 hover:to-gray-700 transition-all duration-300 shadow-lg hover:shadow-xl"
              >
                {{ $t("about.startJourney") }}
                <svg
                  class="mr-2 w-5 h-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M10 19l-7-7m0 0l7-7m-7 7h18"
                  ></path>
                </svg>
              </a>
            </div>
          </div>

          <div class="relative">
            <div class="p-6 border border-gray-700 rounded-2xl shadow-xl">
              <div class="aspect-w-16 aspect-h-12 rounded-lg overflow-hidden">
                <div
                  class="w-full h-64 bg-gradient-to-br from-gray-400 to-gray-500 rounded-lg flex items-center justify-center"
                >
                  <div class="text-center text-black p-6">
                    <div class="text-5xl mb-4">🎞️</div>
                    <h3 class="text-2xl font-bold">
                      {{ $t("about.highlightMoments") }}
                    </h3>
                    <p class="mt-2 opacity-90">
                      {{ $t("about.organizedBy") }}
                    </p>
                  </div>
                </div>
              </div>
              <div class="grid grid-cols-3 gap-4 mt-6 text-black">
                <div class="text-center p-4">
                  <div class="text-2xl font-bold">+١٠ آلاف</div>
                  <div class="text-sm">
                    {{ $t("about.stats.memories") }}
                  </div>
                </div>
                <div class="text-center p-4">
                  <div class="text-2xl font-bold">+٥٠٠</div>
                  <div class="text-sm">
                    {{ $t("about.stats.users") }}
                  </div>
                </div>
                <div class="text-center p-4">
                  <div class="text-2xl font-bold">٢٤/٧</div>
                  <div class="text-sm">
                    {{ $t("about.stats.backup") }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- قسم النشرة البريدية -->
    <div class="py-16 px-4 sm:px-6 lg:px-8">
      <div class="max-w-4xl mx-auto">
        <div class="text-center">
          <h2 class="text-3xl font-bold text-black sm:text-4xl">
            {{ $t("newsletter.title") }}
          </h2>
          <p class="mt-4 text-lg text-black max-w-2xl mx-auto">
            {{ $t("newsletter.description") }}
          </p>
        </div>

        <div class="mt-12">
          <div class="p-8 border border-gray-700 shadow-2xl rounded-2xl">
            <div class="max-w-2xl mx-auto">
              <form class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label
                      for="firstName"
                      class="block text-sm font-medium text-black mb-2"
                    >
                      {{ $t("newsletter.firstName") }}
                    </label>
                    <input
                      type="text"
                      id="firstName"
                      :placeholder="$t('newsletter.firstNamePlaceholder')"
                      class="w-full px-4 py-3 rounded-lg border border-gray-700 text-black placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent transition-all"
                    />
                  </div>
                  <div>
                    <label for="email" class="block text-sm font-medium text-black mb-2">
                      {{ $t("newsletter.email") }}
                    </label>
                    <input
                      type="email"
                      id="email"
                      :placeholder="$t('newsletter.emailPlaceholder')"
                      class="w-full px-4 py-3 rounded-lg border border-gray-700 text-black placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent transition-all"
                    />
                  </div>
                </div>

                <div>
                  <label class="flex items-start text-black">
                    <input
                      type="checkbox"
                      class="mt-1 rounded border-gray-700 text-black focus:ring-gray-500"
                    />
                    <span class="ml-3 text-sm">
                      {{ $t("newsletter.consent") }}
                    </span>
                  </label>
                </div>

                <div class="text-center">
                  <button
                    type="submit"
                    class="inline-flex items-center px-8 py-4 rounded-xl bg-gradient-to-r from-gray-400 to-gray-600 text-black font-semibold hover:from-gray-500 hover:to-gray-700 transition-all duration-300 transform hover:-translate-y-0.5 shadow-lg hover:shadow-xl"
                  >
                    <svg
                      class="ml-3 w-5 h-5"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                      ></path>
                    </svg>
                    {{ $t("newsletter.subscribe") }}
                  </button>
                  <p class="mt-4 text-sm text-black">
                    {{ $t("newsletter.community") }}
                  </p>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Fullscreen Map -->
    <div v-if="fullscreen" class="fixed inset-0 bg-black z-50 flex flex-col">
      <div class="bg-gray-900 p-4 flex justify-between items-center">
        <h2 class="text-white text-xl font-semibold">
          {{ $t("map.exploreMap") }}
        </h2>
        <button @click="closeFullscreen" class="text-white text-3xl leading-none">
          ×
        </button>
      </div>
      <div id="map-full" class="flex-1"></div>
    </div>
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

// ====================== Computed ======================
const totalPages = computed(() => Math.ceil(displayedEvents.value.length / itemsPerPage));
const maxVisible = 5;
const debouncedSearch = debounce(() => {
  search();
}, 500);

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

// ====================== Lifecycle ======================
onMounted(async () => {
  mapService = new MapService(marker);
  mapService.initMap("map");

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
});

const onMainCategoryChange = async () => {
  selectedSubCategory.value = "";
  subCategories.value = [];

  if (!selectedCategory.value) {
    return;
  }

  loadingSubCategories.value = true;

  try {
    const res = await api.get(`/categories/${selectedCategory.value}/sub_categories/get`);
    console.log(res.data);
    subCategories.value = res.data.data || [];
  } catch (err) {
    console.error("Error loading sub-categories:", err);
    subCategories.value = [];
  } finally {
    loadingSubCategories.value = false;
    search();
  }
};
// ====================== Methods ======================
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
    id: ev.id,
    slug: ev.slug,
    title: ev.title || "فعالية بدون عنوان",
    description: ev.description || "—",
    start_date: ev.start_date,
    city: ev.city?.name || "غير محدد",
    category_name: ev.category_name || "من الخريطة",
    image: ev.image || null,
  }));

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
const openFullscreen = async () => {
  fullscreen.value = true;
  await nextTick();
  mapService.openFullscreen("map-full");
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
</style>
