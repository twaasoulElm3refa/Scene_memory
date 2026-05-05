<template>
    <div class="min-h-screen bg-gray-50 font-sans">
        <div class="relative h-[500px] md:h-[600px] bg-gray-900 overflow-hidden">
            <div id="map" class="absolute inset-0"></div>
            <div class="absolute inset-0 bg-black/20 flex items-center justify-center z-10 pointer-events-none"></div>
        </div>

        <!-- ── Fullscreen Modal ── -->
        <Teleport to="body">
            <div v-if="fullscreen" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
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
                                <a :href="`/${lang}/single_event/${event.slug}`"
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


        <section v-if="licenceName === 'free'" class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-10">
            <!-- Header Section -->
            <div class="text-center mb-10">
                <div
                    class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 rounded-full text-sm font-semibold mb-4 shadow-md">
                    <span class="text-lg animate-pulse">⚡</span>
                    {{ $t("plans.chooseYourPlan") || "اختر خطتك" }}
                </div>

                <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-3">
                    {{ $t("plans.ourPlans") || "خطط الاشتراك" }}
                </h2>

                <div class="w-20 h-1 bg-gradient-to-r from-blue-500 to-blue-600 mx-auto rounded-full mb-4"></div>

                <p class="text-gray-600 text-base max-w-xl mx-auto">
                    {{ $t("plans.description") || "ابدأ مجاناً أو اختر الخطة المناسبة لك واستمتع بمزايا حصرية" }}
                </p>
            </div>

            <!-- Loading -->
            <div v-if="loadingPlans" class="flex justify-center items-center py-16">
                <div class="w-14 h-14 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
            </div>

            <!-- Plans Grid -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div v-for="(plan, index) in plans" :key="plan.id"
                    class="relative border border-gray-200 bg-white rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-2 hover:shadow-xl">

                    <!-- Popular Badge -->
                    <div v-if="plan.name.toLowerCase() === 'professional' || plan.name.toLowerCase() === 'pro'"
                        class="absolute top-0 right-0 z-20">
                        <div
                            class="bg-gradient-to-r from-orange-500 to-red-500 text-white text-xs font-bold px-4 py-1 rounded-bl-2xl">
                            {{ $t("plans.mostPopular") || "الأكثر شهرة" }}
                        </div>
                    </div>

                    <div class="p-5">
                        <!-- Plan Name & Icon -->
                        <div class="text-center mb-5">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl mb-3" :class="{
                                'bg-gray-100': plan.name === 'free',
                                'bg-blue-100': plan.name === 'basic' || plan.name === 'professional',
                                'bg-purple-100': plan.name === 'premium'
                            }">
                                <span class="text-3xl">
                                    {{ plan.name === 'free' ? '🎯' : plan.name === 'basic' ? '📘' :
                                        plan.name === 'professional' ? '💎' : '🚀' }}
                                </span>
                            </div>

                            <h3 class="text-xl font-bold text-gray-900">
                                {{ plan.translation?.name || plan.name }}
                            </h3>

                            <!-- Price -->
                            <div class="mt-3">
                                <div class="flex items-baseline justify-center gap-1">
                                    <span class="text-3xl font-extrabold text-gray-900">{{ plan.price }}</span>
                                    <span class="text-gray-500 text-sm">USD</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $t("plans.perMonth") || "/ شهرياً" }}
                                </p>
                            </div>
                        </div>

                        <!-- Features -->
                        <ul class="space-y-2 mb-6">
                            <li v-for="feature in plan.advantges" :key="feature.id"
                                class="flex items-start gap-2 text-gray-700 text-sm">
                                <span
                                    class="w-4 h-4 bg-green-500 text-white rounded-full flex items-center justify-center text-[10px]">✓</span>
                                <span>{{ feature.feature }}</span>
                            </li>

                            <li v-if="plan.advantges.length === 0" class="text-gray-400 text-xs text-center py-2">
                                {{ $t("plans.noFeatures") || "لا توجد ميزات حالياً" }}
                            </li>
                        </ul>

                        <button @click="$router.push(`/{lang}/plan/${plan.slug}`)" :class="[
                            'w-full py-2.5 rounded-lg font-semibold text-sm transition-all',
                            plan.name === 'free'
                                ? 'bg-gray-100 hover:bg-gray-200 text-gray-900 border'
                                : 'bg-blue-600 hover:bg-blue-700 text-white'
                        ]">
                            {{
                                plan.name === 'free'
                                    ? ($t("plans.getStarted") || "ابدأ مجاناً")
                                    : ($t("plans.subscribe") || "اشترك الآن")
                            }}
                        </button>

                        <!-- Extra -->
                        <p class="text-center text-xs text-gray-500 mt-3">
                            {{ plan.name === 'free'
                                ? ($t("plans.noCreditCard") || "لا تحتاج بطاقة")
                                : ($t("plans.cancelAnytime") || "إلغاء في أي وقت")
                            }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Trust -->
            <div class="mt-12 text-center">
                <div class="flex flex-wrap justify-center gap-6 text-xs text-gray-500">
                    <div class="flex items-center gap-1">
                        <span class="text-green-500">✓</span>
                        <span>{{ $t("plans.securePayments") || "مدفوعات آمنة" }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-green-500">✓</span>
                        <span>{{ $t("plans.moneyBack") || "ضمان 30 يوم" }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-green-500">✓</span>
                        <span>{{ $t("plans.support247") || "دعم 24/7" }}</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <Transition name="slide-fade">
        <div v-if="showProfileToast"
            class="fixed top-4 left-4 z-[9999] w-[280px] bg-white shadow-lg rounded-lg border border-gray-200 overflow-hidden cursor-pointer"
            @click="goToProfile">

            <!-- Header -->
            <div class="flex justify-between items-center px-3 py-2 bg-yellow-50 border-b">
                <!-- <div class="flex items-center gap-1.5">
                    <span class="text-yellow-500 text-sm">⚠️</span>
                    <p class="font-semibold text-xs text-gray-800">
                        Profile Incomplete
                    </p>
                </div> -->

                <button @click.stop="closeToast" class="text-gray-400 hover:text-red-500 text-sm transition-colors">
                    ✕
                </button>
            </div>

            <!-- Body -->
            <div class="p-3 text-xs text-gray-600">
                Your profile is not complete. Please update:
                <ul class="mt-1.5 list-disc list-inside text-[11px] text-gray-500">
                    <li v-for="field in missingFieldsList" :key="field">
                        {{ fieldLabels[field] || field }}
                    </li>
                </ul>

                <p class="mt-2 text-blue-600 font-medium text-[11px]">
                    Click to complete →
                </p>
            </div>

            <!-- Progress Bar -->
            <div class="h-1 bg-gray-100">
                <div class="h-full bg-yellow-500 transition-all duration-100 ease-linear"
                    :style="{ width: progressWidth }">
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed, watch } from "vue";
import { useRouter } from 'vue-router';
import MapService from "@/services/MapService/MapService.js";
import { CategoryService } from "@/services/CategoryService/CategoryService";
import { LocationService } from "@/services/LocationService/LocationService";
import { EventService } from "@/services/EventService/EventService";
import { PlanService } from "@/services/planService/planService";
import { AuthService } from "../../services/AuthService/AuthService";
import { debounce } from "lodash";

const marker = ref({ lat: 30.0444, lng: 31.2357 });
const fullscreen = ref(false);
const router = useRouter();
const licenceName = ref("free");
const isLoggedIn = ref(false);
const searchQuery = ref("");
const displayedEvents = ref([]);
const categories = ref([]);
const countries = ref([]);
const cities = ref([]);
const countrySearch = ref("");
const showDropdown = ref(false);
const filteredCountries = ref([]);
const lang = localStorage.getItem("language") || "en";
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
const plans = ref([]);
const loadingPlans = ref(false);

let mapService = null;

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

const fallbackImage = "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&q=80&w=800";

const paginatedEvents = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return displayedEvents.value.slice(start, start + itemsPerPage);
});

// ====================== Methods ======================
const formatDate = (dateStr) => {
    if (!dateStr) return "—";
    const language = localStorage.getItem("language") || "ar";
    try {
        return new Date(dateStr).toLocaleDateString(language, {
            year: "numeric",
            month: "short",
            day: "numeric"
        });
    } catch {
        return "—";
    }
};

const filterCountries = () => {
    const search = countrySearch.value.toLowerCase().trim();
    filteredCountries.value = countries.value.filter(country =>
        country.translation?.name?.toLowerCase().includes(search)
    );
};

const selectCountry = (country) => {
    selectedCountry.value = country.id;
    countrySearch.value = country.translation?.name || "";
    showDropdown.value = false;
};

// تحميل المدن عند تغيير الدولة
const loadCities = async () => {
    if (!selectedCountry.value) {
        cities.value = [];
        selectedCity.value = "";
        return;
    }

    try {
        cities.value = await LocationService.getCitiesByCountry(selectedCountry.value);
        selectedCity.value = ""; // reset المدينة عند تغيير الدولة
        console.log("✅ Cities loaded successfully:", cities.value.length);
    } catch (err) {
        console.error("❌ Error loading cities:", err);
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
        subCategories.value = res.data.data || [];
    } catch (err) {
        console.error("Error loading sub-categories:", err);
        subCategories.value = [];
    } finally {
        loadingSubCategories.value = false;
    }
};

// رسم الدبابيس على الخرائط
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
            image_url: ev.first_image?.full_url
                ? `/storage/${ev.first_image.full_url}`
                : null,

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
        image_url: ev.first_image?.full_url
            ? `/storage/${ev.first_image.full_url}`
            : null,
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
        const lang = localStorage.getItem("language") || "ar";
        router.push(`${lang}/single_event/${slug}`);
    }
};

// ====================== Watchers ======================

// 1. Watch للدولة → تحميل المدن تلقائياً
watch(selectedCountry, async (newVal) => {
    await loadCities();
}, { immediate: true });

// 2. Watch عام للبحث التلقائي (مع debounce)
watch(
    [selectedCategory, selectedSubCategory, selectedCountry, selectedCity, fromDate, toDate, searchQuery],
    debounce(() => {
        if (searched.value) search();
    }, 600),
    { deep: true }
);

// ====================== Lifecycle Hooks ======================
onMounted(async () => {
    mapService = new MapService(marker);
    mapService.initMap("map", 6);
    await fetchProfile();
    checkProfileWarning();
    try {
        categories.value = await CategoryService.getAllCategories();
        countries.value = await LocationService.getAllCountries();
        filteredCountries.value = [...countries.value];
        await loadPlans();
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

// ====================== Fullscreen Functions ======================
const openFullscreen = async () => {
    fullscreen.value = true;
    await nextTick();
    await new Promise(r => setTimeout(r, 100));

    mapService.openFullscreen("map-full", 6);

    if (displayedEvents.value.length > 0) {
        setTimeout(() => {
            if (mapService.fullMap) {
                mapService.addEventMarkers(displayedEvents.value, mapService.fullMap, true);
            }
        }, 400);
    }
};

const closeFullscreen = () => {
    fullscreen.value = false;
    mapService.closeFullscreen();
};

const loadPlans = async () => {
    loadingPlans.value = true;
    try {
        plans.value = await PlanService.getAllPlans();
    } catch (err) {
        console.error("Error loading plans:", err);
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
                .filter(([key, value]) => !value || value === "")
                .map(([key]) => key);
            const isFilledProfile = missingFields.length === 0;
            localStorage.setItem("isFilledProfile", isFilledProfile ? "true" : "false");
            localStorage.setItem("missingFields", JSON.stringify(missingFields));
            isLoggedIn.value = true;
        }
    } catch (err) {
        console.log("Failed to fetch profile:", err);

        licenceName.value = "free";
        isLoggedIn.value = false;
    }
};

const showProfileToast = ref(false)
let toastTimer = null
let progressInterval = null
const progressWidth = ref('100%')
const TOAST_DURATION = 7000 // 7 seconds (between 5-10 seconds)

// check profile from localStorage
const checkProfileWarning = () => {
    const isFilled = localStorage.getItem("isFilledProfile");

    if (isFilled === "false") {
        showProfileToast.value = true;
        startProgressBar();
    }
};

const missingFieldsList = computed(() => {
    const data = localStorage.getItem("missingFields");
    return data ? JSON.parse(data) : [];
});

const fieldLabels = {
    phone: "Phone",
    country: "Country",
    position: "Position",
    date_of_birth: "Date of birth"
};

const startProgressBar = () => {
    const startTime = Date.now()
    const endTime = startTime + TOAST_DURATION

    progressInterval = setInterval(() => {
        const now = Date.now()
        const remaining = endTime - now
        const percentage = Math.max(0, (remaining / TOAST_DURATION) * 100)
        progressWidth.value = `${percentage}%`

        if (percentage <= 0) {
            clearInterval(progressInterval)
        }
    }, 16) // ~60fps

    // auto hide after duration
    toastTimer = setTimeout(() => {
        showProfileToast.value = false
        clearInterval(progressInterval)
    }, TOAST_DURATION)
}


onMounted(() => {
    checkProfileWarning()
})

onUnmounted(() => {
    if (toastTimer) clearTimeout(toastTimer)
    if (progressInterval) clearInterval(progressInterval)
})

const closeToast = () => {
    showProfileToast.value = false;
    if (toastTimer) clearTimeout(toastTimer);
};

const goToProfile = () => {
    const lang = localStorage.getItem("lang") || "en";
    router.push(`/${lang}/profile`);
};

const subscribe = async (planId) => {
    const token = localStorage.getItem("auth_token");
    const lang = localStorage.getItem("language") || "en";

    if (!token) {
        alert("Please login first");
        return;
    }

    try {
        const res = await PlanService.subscribe(planId);

        if (res.data.status === "success") {
            await fetchProfile(); // يغير licenceName
            await loadPlans();    // تحديث الخطط

            alert("Subscribed successfully");
            window.location.reload();
        }
    } catch (err) {
        console.error("Subscribe error:", err);
        alert("Subscription failed ❌");
    }
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
