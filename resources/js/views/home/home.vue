<template>
    <div class="min-h-screen bg-gray-50 font-sans">
        <div class="relative h-[500px] md:h-[600px] bg-gray-900 overflow-hidden">
            <div id="map" class="absolute inset-0"></div>

            <!-- زرار Fullscreen -->
            <button @click="openFullscreen"
                class="absolute top-4 right-4 z-20 bg-white/90 backdrop-blur-sm p-3 rounded-full shadow-lg hover:bg-white transition text-gray-800"
                title="عرض الخريطة كامل الشاشة">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                </svg>
            </button>

            <div class="absolute inset-0 bg-black/20 flex items-center justify-center z-10 pointer-events-none"></div>
        </div>

        <!-- ── Fullscreen Modal ── -->
        <Teleport to="body">
            <div v-if="fullscreen"
                class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
                <div class="relative w-full h-full max-w-7xl max-h-[90vh] rounded-2xl overflow-hidden shadow-2xl">
                    <!-- خريطة الـ fullscreen -->
                    <div id="map-full" class="absolute inset-0"></div>

                    <!-- زرار الإغلاق -->
                    <button @click="closeFullscreen"
                        class="absolute top-4 right-4 z-30 bg-white/90 backdrop-blur-sm p-3 rounded-full shadow-lg hover:bg-white transition text-gray-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </Teleport>

        <!-- شريط الفلاتر -->
        <div class="bg-white border-b shadow-sm sticky top-0 z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-3 items-end">
                    <!-- Category -->
                    <div class="space-y-1">
                        <select v-model="selectedCategory" @change="onMainCategoryChange"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                            <option value="">{{ $t("filters.allCategories") }}</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.translation.name }}
                            </option>
                        </select>
                    </div>

                    <!-- SubCategory -->
                    <div v-if="subCategories.length > 0 || selectedCategory">
                        <select v-model="selectedSubCategory" :disabled="loadingSubCategories"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                            <option value="">{{ $t("filters.allSubCategories") }}</option>
                            <option v-for="sub in subCategories" :key="sub.id" :value="sub.id">
                                {{ sub.translation.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Country Search -->
                    <div class="relative">
                        <input v-model="countrySearch" @focus="showDropdown = true" @input="filterCountries" type="text"
                            :placeholder="$t('filters.country')"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition" />

                        <div v-if="showDropdown && filteredCountries.length"
                            class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <div v-for="country in filteredCountries" :key="country.id" @click="selectCountry(country)"
                                class="px-3 py-2 text-sm cursor-pointer hover:bg-blue-50">
                                {{ country.translation.name }}
                            </div>
                        </div>
                    </div>

                    <!-- City -->
                    <div>
                        <select v-model="selectedCity" :disabled="!selectedCountry"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-gray-900">
                            <option value="">{{ $t("filters.city") }}</option>
                            <option v-for="city in cities" :key="city.id" :value="city.id">
                                {{ city.translation.name }}
                            </option>
                        </select>
                    </div>

                    <!-- From Date -->
                    <div>
                        <input v-model="fromDate" type="date"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition" />
                    </div>

                    <!-- To Date -->
                    <div>
                        <input v-model="toDate" type="date"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition" />
                    </div>

                    <!-- Search Button -->
                    <div class="flex items-end">
                        <button @click="search(true)"
                            class="w-full sm:w-auto px-5 py-2 text-sm rounded-full bg-gradient-to-r from-blue-600 to-blue-700 text-white hover:from-blue-700 hover:to-blue-800 transition shadow hover:shadow-md active:scale-95">
                            {{ $t("common.search") }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- نتائج -->
        <section v-if="searched" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:justify-between md:items-end mb-10">
                <div>
                    <div
                        class="inline-flex items-center gap-2 text-blue-600 bg-blue-50 px-4 py-2 rounded-full text-sm font-medium mb-4">
                        <span class="relative flex h-2 w-2">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-600"></span>
                        </span>
                        {{ $t("events.latestEvents") }}
                    </div>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">
                        {{ $t("events.recentMemories") }}
                    </h2>
                    <p class="text-gray-500 text-base md:text-lg max-w-xl">
                        {{ $t("events.discoverAroundYou") }}
                    </p>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex flex-col items-center justify-center py-20">
                <div class="relative w-20 h-20">
                    <div class="absolute inset-0 border-4 border-blue-200 rounded-full"></div>
                    <div
                        class="absolute inset-0 border-4 border-blue-600 border-t-transparent rounded-full animate-spin">
                    </div>
                </div>
                <p class="mt-6 text-lg text-gray-600 font-medium">
                    {{ $t("common.loadingEvents") }}
                </p>
            </div>

            <!-- No results -->
            <div v-else-if="displayedEvents.length === 0" class="text-center py-20 bg-gray-50 rounded-3xl">
                <div class="text-7xl mb-6">🎭</div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">
                    {{ $t("events.noEventsFound") }}
                </h3>
                <p class="text-lg text-gray-600">
                    {{ $t("events.noMatchingEvents") }}
                </p>
            </div>

            <!-- Events + Pagination -->
            <template v-else>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 md:gap-8">
                    <div v-for="event in paginatedEvents" :key="event.slug || event.id"
                        class="group bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                        <div class="aspect-[4/3] relative overflow-hidden bg-gray-100">
                            <img :src="event.image_url || fallbackImage" :alt="event.title"
                                class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                                loading="lazy" />
                            <div class="absolute top-4 left-4 z-10">
                                <span
                                    class="bg-white/90 backdrop-blur-sm text-gray-900 text-xs font-bold px-3 py-1.5 rounded-full shadow">
                                    {{ event.category_name || $t("events.event") }}
                                </span>
                            </div>
                        </div>
                        <div class="p-5">
                            <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
                                <span class="text-blue-600">📅</span>
                                <span class="font-medium">
                                    {{ $t("events.startDate") }} : {{ formatDate(event.start_date) }}
                                </span>
                            </div>
                            <h4
                                class="text-right text-base md:text-lg font-semibold mb-3 line-clamp-2 text-gray-900 group-hover:text-blue-600 transition-colors">
                                {{ event.translation?.title || $t("common.notSpecified") }}
                            </h4>
                            <div class="flex justify-between items-center pt-3 border-t border-gray-100 text-sm">
                                <span class="text-gray-600">
                                    {{ event.city || $t("common.notSpecified") }}
                                </span>
                                <a :href="`/single_event/${event.slug}`"
                                    class="text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
                                    {{ $t("common.details") }} →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="flex flex-wrap justify-center mt-12 gap-2">
                    <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1"
                        class="px-4 py-2 rounded-full text-sm font-medium bg-white border border-gray-200 text-gray-700 hover:bg-blue-50 disabled:opacity-50">
                        {{ $t("pagination.previous") }}
                    </button>

                    <button v-for="page in visiblePages" :key="page" @click="currentPage = page" :class="[
                        'px-4 py-2 rounded-full text-sm font-medium min-w-[40px]',
                        currentPage === page ? 'bg-blue-600 text-white shadow' : 'bg-white border border-gray-200 text-gray-700 hover:bg-blue-50'
                    ]">
                        {{ page }}
                    </button>

                    <button @click="currentPage = Math.min(totalPages, currentPage + 1)"
                        :disabled="currentPage === totalPages || totalPages === 0"
                        class="px-4 py-2 rounded-full text-sm font-medium bg-white border border-gray-200 text-gray-700 hover:bg-blue-50 disabled:opacity-50">
                        {{ $t("pagination.next") }}
                    </button>
                </div>
            </template>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed, watch } from "vue";
import { useRouter } from 'vue-router';
import MapService from "@/services/MapService.js";
import { CategoryService } from "@/services/CategoryService";
import { LocationService } from "@/services/LocationService";
import { EventService } from "@/services/EventService";
import { debounce } from "lodash";
import api from "@/services/ApiClient";

const marker = ref({ lat: 30.0444, lng: 31.2357 });
const fullscreen = ref(false);
const router = useRouter();

const searchQuery = ref("");
const displayedEvents = ref([]);
const categories = ref([]);
const countries = ref([]);
const cities = ref([]);
const countrySearch = ref("");
const showDropdown = ref(false);
const filteredCountries = ref([]);

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
const itemsPerPage = 8;

let mapService = null;

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

const fallbackImage = "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&q=80&w=800";

const paginatedEvents = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return displayedEvents.value.slice(start, start + itemsPerPage);
});

const formatDate = (dateStr) => {
    if (!dateStr) return "—";
    const language = localStorage.getItem("language") || "ar";
    try {
        return new Date(dateStr).toLocaleDateString(language, { year: "numeric", month: "short", day: "numeric" });
    } catch {
        return "—";
    }
};

const filterCountries = () => {
    const search = countrySearch.value.toLowerCase();
    filteredCountries.value = countries.value.filter(country =>
        country.translation?.name?.toLowerCase().includes(search)
    );
};

const selectCountry = (country) => {
    selectedCountry.value = country.id;
    countrySearch.value = country.translation?.name || "";
    showDropdown.value = false;
    loadCities();
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

// ── Helper: رسم الدبابيس على الخرائط المفتوحة ──
const renderMarkersOnMaps = (events) => {
    if (mapService?.map) {
        mapService.addEventMarkers(events, mapService.map, false);
    }
    if (fullscreen.value && mapService?.fullMap) {
        mapService.addEventMarkers(events, mapService.fullMap, true);
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

        displayedEvents.value = (Array.isArray(result) ? result : []).map(ev => ({
            id: ev.id,
            slug: ev.slug,
            translation: ev.translation,
            title: ev.title || "فعالية بدون عنوان",
            start_date: ev.start_date,
            city: ev.city?.translation?.name || "غير محدد",
            category_name: ev.sub_categorey?.translation?.name || "فعالية",
            image_url: ev.image_url || null,
            lattitude: ev.lattitude,
            langitude: ev.langitude,
        }));

        await nextTick();
        renderMarkersOnMaps(displayedEvents.value);
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

    displayedEvents.value = eventsFromMap.map(ev => ({
        id: ev.id || ev._id,
        slug: ev.slug,
        title: ev.title || 'فعالية بدون عنوان',
        start_date: ev.start_date,
        city: ev.city?.translation?.name || ev.city || 'غير محدد',
        category_name: ev.sub_categorey?.translation?.name || 'فعالية',
        image_url: ev.image_url || null,
        translation: ev.translation,
        lattitude: ev.lattitude,
        langitude: ev.langitude,
    }));

    renderMarkersOnMaps(displayedEvents.value);

    currentPage.value = 1;
    searched.value = true;
    loading.value = false;
};

const handleEventMarkerClick = (e) => {
    const { slug } = e.detail;
    if (slug) {
        router.push(`/single_event/${slug}`);
    }
};

watch(
    [selectedCategory, selectedSubCategory, selectedCountry, selectedCity, fromDate, toDate, searchQuery],
    debounce(() => search(), 500),
    { deep: true }
);

onMounted(async () => {
    mapService = new MapService(marker);
    mapService.initMap("map", 6);

    try {
        categories.value = await CategoryService.getAllCategories();
        countries.value = await LocationService.getAllCountries();
        filteredCountries.value = countries.value;
    } catch (err) {
        console.error("Error loading initial data:", err);
    }

    document.addEventListener("marker-events-loaded", handleMarkerEvents);
    document.addEventListener("event-marker-clicked", handleEventMarkerClick);
});

onUnmounted(() => {
    document.removeEventListener("marker-events-loaded", handleMarkerEvents);
    document.removeEventListener("event-marker-clicked", handleEventMarkerClick);
    if (mapService) mapService.closeFullscreen();
});

const openFullscreen = async () => {
    fullscreen.value = true;
    await nextTick();

    // انتظر شوية عشان الـ DOM يتبني
    await new Promise(r => setTimeout(r, 50));

    mapService.openFullscreen("map-full", 6);

    // لو عندنا أحداث محملة → رسمها على خريطة الـ fullscreen بعد ما تتبني
    if (displayedEvents.value.length > 0) {
        // نستنى الـ map يخلص load
        const waitForFullMap = () => {
            if (mapService.fullMap) {
                mapService.addEventMarkers(displayedEvents.value, mapService.fullMap, true);
            } else {
                setTimeout(waitForFullMap, 100);
            }
        };
        setTimeout(waitForFullMap, 300);
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

#map {
    width: 100%;
    height: 100%;
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
    bottom: 100%;
    /* فوق الدبوس */
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
    font-size: 32px;
    /* حجم الدبوس */
    color: #e53e3e;
    /* أحمر قوي */
    line-height: 1;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
}

/* تحسين الـ popup إذا أردت */
.leaflet-popup.custom-event-popup .leaflet-popup-content-wrapper {
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}
</style>
