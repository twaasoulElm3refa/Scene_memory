<template>
    <div dir="rtl" class="min-h-screen bg-gradient-to-b from-gray-50 to-white">
        <div v-if="loading" class="flex items-center justify-center min-h-screen">
            <div class="text-center">
                <div class="relative">
                    <div class="w-16 h-16 border-4 border-gray-100 border-t-blue-500 rounded-full animate-spin mx-auto mb-4"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-8 h-8 bg-blue-500 rounded-full animate-pulse"></div>
                    </div>
                </div>
                <p class="text-gray-400 text-sm font-medium mt-2">Loading...</p>
            </div>
        </div>

        <div v-else-if="error" class="flex items-center justify-center min-h-screen p-4">
            <div class="text-center max-w-md mx-auto bg-white rounded-2xl shadow-xl p-8 border border-red-50">
                <div class="w-20 h-20 bg-gradient-to-br from-red-50 to-red-100 rounded-full flex items-center justify-center mx-auto mb-5">
                    <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-red-500 font-medium whitespace-pre-line">{{ error }}</p>
                <button
                    @click="fetchData"
                    class="mt-5 px-6 py-2 bg-blue-500 text-white rounded-xl text-sm font-medium hover:bg-blue-600 transition-colors"
                >
                    Try Again
                </button>
            </div>
        </div>

        <div v-else-if="country" class="max-w-6xl mx-auto px-4 sm:px-6 py-8 md:py-12">
            <div class="relative mb-12">
                <div class="absolute inset-0 bg-gradient-to-r from-blue-600/5 to-purple-600/5 rounded-3xl blur-3xl -z-10"></div>
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent">
                                {{ getDisplayCountryName(country) }}
                            </h1>
                            <span class="px-3 py-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-sm rounded-full font-medium shadow-sm">
                                {{ country.code }}
                            </span>
                        </div>
                        <p class="text-gray-500 text-sm md:text-base">
                            Discover featured events in {{ getDisplayCountryName(country) }}
                        </p>
                    </div>

                    <button
                        @click="$router.back()"
                        class="group flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-200 rounded-2xl text-gray-600 hover:border-gray-300 hover:shadow-md transition-all duration-200"
                    >
                        <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span class="text-sm font-medium">Back</span>
                    </button>
                </div>
            </div>

            <div class="mb-12">
                <div
                    ref="mapContainer"
                    class="country-map w-full h-[550px] rounded-3xl overflow-hidden border border-gray-100 shadow-sm bg-white"
                ></div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
                <div class="group bg-white rounded-2xl p-6 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-gray-50">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-gray-400 text-sm font-medium">Cities</span>
                        <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800">{{ CountCitites }}</p>
                    <p class="text-xs text-gray-400 mt-1">Available city</p>
                </div>

                <div class="group bg-white rounded-2xl p-6 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-gray-50">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-gray-400 text-sm font-medium">Events</span>
                        <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800">{{ events.length }}</p>
                    <p class="text-xs text-gray-400 mt-1">Current event</p>
                </div>

                <div class="group bg-white rounded-2xl p-6 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-gray-50">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-gray-400 text-sm font-medium">Event Markers</span>
                        <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800">{{ mappedEventsCount }}</p>
                    <p class="text-xs text-gray-400 mt-1">Clickable marker</p>
                </div>

                <div class="group bg-white rounded-2xl p-6 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-gray-50">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-gray-400 text-sm font-medium">Categories</span>
                        <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800">{{ getUniqueCategoriesCount }}</p>
                    <p class="text-xs text-gray-400 mt-1">Different category</p>
                </div>
            </div>

            <div class="mb-12">
                <div class="flex items-center gap-2 mb-5">
                    <div class="w-1 h-6 bg-gradient-to-b from-blue-500 to-purple-500 rounded-full"></div>
                    <h2 class="text-xl font-bold text-gray-800">Available Cities</h2>
                    <span class="text-xs bg-gray-100 text-gray-600 px-2.5 py-0.5 rounded-full">{{ CountCitites }}</span>
                </div>

                <div class="flex flex-wrap gap-3">
                    <span
                        v-for="city in country.cities || []"
                        :key="city.id"
                        class="group inline-flex items-center gap-2 text-sm bg-white border border-gray-200 text-gray-700 px-5 py-2.5 rounded-full shadow-sm hover:shadow-md hover:border-blue-300 hover:text-blue-600 transition-all duration-200 cursor-default"
                    >
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ getDisplayCityName(city) }}
                    </span>
                </div>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-6">
                    <div class="w-1 h-6 bg-gradient-to-b from-blue-500 to-purple-500 rounded-full"></div>
                    <h2 class="text-xl font-bold text-gray-800">All Events</h2>
                    <span class="text-xs bg-gray-100 text-gray-600 px-2.5 py-0.5 rounded-full">{{ events.length }}</span>
                </div>

                <div v-if="events.length === 0" class="text-center py-16 bg-white rounded-2xl border border-gray-100">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <p class="text-gray-400 font-medium">No events available right now</p>
                    <p class="text-sm text-gray-300 mt-1">Check back again soon</p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="event in events"
                        :key="event.id"
                        class="group bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-300"
                    >
                        <div class="relative h-52 overflow-hidden bg-gradient-to-br from-gray-100 to-gray-50">
                            <img
                                v-if="event.first_image"
                                :src="normalizeEventImage(event.first_image)"
                                :alt="getDisplayEventTitle(event)"
                                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                            />

                            <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-300">
                                <svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-xs">No image</span>
                            </div>

                            <div class="absolute top-3 right-3">
                                <span class="text-xs bg-white/95 backdrop-blur-sm text-gray-700 px-3 py-1.5 rounded-full font-medium shadow-sm">
                                    {{ getDisplayCategoryName(event.sub_categorey) || 'No Category' }}
                                </span>
                            </div>

                            <div class="absolute bottom-3 left-3">
                                <span class="text-xs bg-black/50 backdrop-blur-sm text-white px-3 py-1.5 rounded-full font-medium flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ formatDate(event.start_date) }}
                                </span>
                            </div>
                        </div>

                        <div class="p-5">
                            <h3 class="text-lg font-bold text-gray-800 line-clamp-1 mb-2 group-hover:text-blue-600 transition-colors">
                                {{ getDisplayEventTitle(event) }}
                            </h3>

                            <p class="text-sm text-gray-500 leading-relaxed line-clamp-2 mb-4">
                                {{ getDisplayEventDescription(event) }}
                            </p>

                            <div class="flex items-center gap-2 text-xs text-gray-400 mb-4 pb-3 border-b border-gray-50">
                                <div class="flex items-center gap-1.5 bg-gray-50 px-3 py-1.5 rounded-full">
                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="text-gray-600">{{ getDisplayCityName(event.city) }}</span>
                                </div>
                            </div>

                            <button
                                @click="goToEvent(event.slug)"
                                class="w-full inline-flex items-center justify-center gap-2 text-sm font-medium bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-4 py-2.5 rounded-xl transition-all duration-200 shadow-md hover:shadow-lg active:scale-[0.98]"
                            >
                                <span>View Event</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import CountryService from '@/services/CountryService';

export default {
    name: 'CountryData',

    data() {
        return {
            country: null,
            events: [],
            loading: false,
            error: null,
            CountCitites: 0,
            map: null,
            eventMarkers: [],
            isMapInitializing: false,
            maplibrePromise: null,
        };
    },

    computed: {
        countryParam() {
            return this.$route.params.code || this.$route.params.slug || '';
        },

        currentLang() {
            return this.$route.params.lang || 'en';
        },

        getUniqueCategoriesCount() {
            if (!this.events?.length) return 0;

            return new Set(
                this.events
                    .map((e) => this.getDisplayCategoryName(e.sub_categorey))
                    .filter(Boolean)
            ).size;
        },

        mappedEventsCount() {
            if (!this.events?.length) return 0;
            return this.events.filter((event) => !!this.getEventCoordinates(event)).length;
        },
    },

    async mounted() {
        await this.fetchData();
    },

    beforeUnmount() {
        this.destroyMap();
    },

    watch: {
        async '$route.params.code'() {
            await this.fetchData();
        },

        async '$route.params.slug'() {
            await this.fetchData();
        },

        async '$route.params.lang'() {
            await this.fetchData();
        },
    },

    methods: {
        goToEvent(slug) {
            this.$router.push({
                name: 'single_event',
                params: {
                    lang: this.currentLang,
                    slug,
                },
            });
        },

        getDisplayCountryName(country) {
            return (
                country?.translation?.name ||
                country?.name_en ||
                country?.english_name ||
                country?.name ||
                'Country'
            );
        },

        getDisplayCityName(city) {
            return (
                city?.translation?.name ||
                city?.name_en ||
                city?.english_name ||
                city?.name ||
                'City'
            );
        },

        getDisplayCategoryName(category) {
            return (
                category?.translation?.name ||
                category?.name_en ||
                category?.english_name ||
                category?.name ||
                ''
            );
        },

        getDisplayEventTitle(event) {
            return (
                event?.translation?.title ||
                event?.title_en ||
                event?.english_title ||
                event?.title ||
                'Event'
            );
        },

        getDisplayEventDescription(event) {
            return (
                event?.translation?.description ||
                event?.description_en ||
                event?.english_description ||
                event?.description ||
                ''
            );
        },

        normalizeEventImage(firstImage) {
            if (!firstImage) return '';
            const url = firstImage.full_url || firstImage.url || '';
            if (!url) return '';
            if (/^https?:\/\//i.test(url)) return url;
            return `/storage/${String(url).replace(/^\/+/, '')}`;
        },

        destroyMap() {
            this.eventMarkers.forEach((marker) => marker.remove());
            this.eventMarkers = [];

            if (this.map) {
                this.map.remove();
                this.map = null;
            }

            const container = this.$refs.mapContainer;
            if (container) {
                container.innerHTML = '';
            }
        },

        async fetchData() {
            this.loading = true;
            this.error = null;
            this.country = null;
            this.events = [];
            this.CountCitites = 0;
            this.destroyMap();

            try {
                const response = await CountryService.getCountryStats(this.countryParam);
                console.log(response.data);

                this.country = response?.data?.data?.country || null;
                this.events = Array.isArray(response?.data?.data?.events)
                    ? response.data.data.events
                    : [];
                this.CountCitites = this.country?.cities_count ?? this.country?.cities?.length ?? 0;
            } catch (err) {
                console.error('Error while loading data:', err);

                if (err.response?.status === 401) {
                    this.error = 'Unauthorized. Please sign in.';
                } else if (err.response?.status === 404) {
                    this.error = 'Country not found.';
                } else {
                    this.error = err?.message || 'An error occurred while loading data.';
                }
            } finally {
                this.loading = false;

                await this.$nextTick();

                if (this.country && !this.error) {
                    await this.renderMapWhenReady();
                }
            }
        },

        async renderMapWhenReady() {
            if (this.loading || !this.country || this.error || this.isMapInitializing) {
                return;
            }

            this.isMapInitializing = true;

            try {
                await this.$nextTick();
                await new Promise((resolve) => requestAnimationFrame(() => resolve()));
                await new Promise((resolve) => setTimeout(resolve, 80));

                const container = this.$refs.mapContainer;
                if (!container) {
                    console.warn('⚠️ mapContainer ref is missing after render');
                    return;
                }

                await this.initMap();
            } finally {
                this.isMapInitializing = false;
            }
        },

        formatDate(date) {
            if (!date) return '';

            const d = new Date(date);
            if (Number.isNaN(d.getTime())) return '';

            return new Intl.DateTimeFormat('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            }).format(d);
        },

        getCountryCode() {
            if (!this.country) return null;

            const code =
                this.country.code ||
                this.country.iso2 ||
                this.country.iso_code ||
                this.country.country_code;

            return code ? String(code).trim().toUpperCase() : null;
        },

        getCountryEnglishName() {
            return (
                this.country?.translation?.name ||
                this.country?.english_name ||
                this.country?.name_en ||
                ''
            );
        },

        getCountryArabicName() {
            return this.country?.name || '';
        },

        normalizeCountryName(name) {
            return String(name || '')
                .trim()
                .toLowerCase()
                .replace(/&/g, 'and')
                .replace(/\./g, '')
                .replace(/\s+/g, ' ');
        },

        getCodeAliases(code) {
            const normalizedCode = String(code || '').trim().toUpperCase();

            const aliasMap = {
                UK: ['UK', 'GB', 'GBR'],
                UAE: ['UAE', 'AE', 'ARE'],
                US: ['US', 'USA'],
                SA: ['SA', 'SAU'],
                IR: ['IR', 'IRN'],
                EG: ['EG', 'EGY'],
            };

            return aliasMap[normalizedCode] || [normalizedCode];
        },

        extractFeatureCodes(properties) {
            return [
                properties?.ISO_A2,
                properties?.ISO_A2_EH,
                properties?.iso_a2,
                properties?.ISO_A3,
                properties?.iso_a3,
                properties?.ADM0_A3,
                properties?.ADM0_A3_US,
                properties?.SOV_A3,
                properties?.GU_A3,
                properties?.BRK_A3,
                properties?.code,
                properties?.country_code,
                properties?.id,
            ]
                .filter(Boolean)
                .map((value) => String(value).trim().toUpperCase());
        },

        extractFeatureNames(properties) {
            return [
                properties?.ADMIN,
                properties?.admin,
                properties?.NAME,
                properties?.name,
                properties?.NAME_EN,
                properties?.name_en,
                properties?.SOVEREIGNT,
                properties?.sovereignt,
                properties?.BRK_NAME,
                properties?.FORMAL_EN,
                properties?.formal_en,
                properties?.NAME_LONG,
                properties?.name_long,
            ]
                .filter(Boolean)
                .map((value) => this.normalizeCountryName(value));
        },

        findCountryFeature(geojson, country) {
            if (!geojson?.features?.length || !country) return null;

            const code = this.getCountryCode();
            const englishName = this.normalizeCountryName(this.getCountryEnglishName());
            const arabicName = this.normalizeCountryName(this.getCountryArabicName());
            const codeAliases = this.getCodeAliases(code);

            let feature = geojson.features.find((item) => {
                const props = item.properties || {};
                const featureCodes = this.extractFeatureCodes(props);
                return codeAliases.some((alias) => featureCodes.includes(alias));
            });

            if (feature) return feature;

            if (englishName) {
                feature = geojson.features.find((item) => {
                    const props = item.properties || {};
                    const featureNames = this.extractFeatureNames(props);
                    return featureNames.includes(englishName);
                });

                if (feature) return feature;
            }

            if (arabicName) {
                feature = geojson.features.find((item) => {
                    const props = item.properties || {};
                    const featureNames = this.extractFeatureNames(props);
                    return featureNames.includes(arabicName);
                });

                if (feature) return feature;
            }

            return null;
        },

        async loadMapLibre() {
            if (window.maplibregl) {
                return window.maplibregl;
            }

            if (this.maplibrePromise) {
                return this.maplibrePromise;
            }

            this.maplibrePromise = new Promise((resolve, reject) => {
                if (!document.getElementById('maplibre-css')) {
                    const link = document.createElement('link');
                    link.id = 'maplibre-css';
                    link.rel = 'stylesheet';
                    link.href = 'https://unpkg.com/maplibre-gl@5.23.0/dist/maplibre-gl.css';
                    document.head.appendChild(link);
                }

                const existingScript = document.getElementById('maplibre-js');
                if (existingScript) {
                    existingScript.addEventListener('load', () => resolve(window.maplibregl));
                    existingScript.addEventListener('error', () =>
                        reject(new Error('Failed to load MapLibre'))
                    );
                    return;
                }

                const script = document.createElement('script');
                script.id = 'maplibre-js';
                script.src = 'https://unpkg.com/maplibre-gl@5.23.0/dist/maplibre-gl.js';
                script.async = true;
                script.onload = () => resolve(window.maplibregl);
                script.onerror = () => reject(new Error('Failed to load MapLibre'));
                document.body.appendChild(script);
            });

            return this.maplibrePromise;
        },

        async loadCountriesGeoJSON() {
            const url = 'https://raw.githubusercontent.com/datasets/geo-countries/master/data/countries.geojson';

            const res = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!res.ok) {
                throw new Error(`Failed to load countries data (${res.status})`);
            }

            const data = await res.json();

            if (data.type !== 'FeatureCollection' || !Array.isArray(data.features)) {
                throw new Error('Invalid border file.');
            }

            return data;
        },

        createNaturalRasterStyle() {
            return {
                version: 8,
                sources: {
                    'osm-raster': {
                        type: 'raster',
                        tiles: [
                            'https://a.tile.openstreetmap.org/{z}/{x}/{y}.png',
                            'https://b.tile.openstreetmap.org/{z}/{x}/{y}.png',
                            'https://c.tile.openstreetmap.org/{z}/{x}/{y}.png',
                        ],
                        tileSize: 256,
                        attribution: '© OpenStreetMap contributors',
                        maxzoom: 19,
                    },
                },
                layers: [
                    {
                        id: 'osm-raster-layer',
                        type: 'raster',
                        source: 'osm-raster',
                    },
                ],
            };
        },

        getGeometryBounds(geometry) {
            let minLng = Infinity;
            let minLat = Infinity;
            let maxLng = -Infinity;
            let maxLat = -Infinity;

            const walk = (coords) => {
                if (!Array.isArray(coords)) return;

                if (typeof coords[0] === 'number' && typeof coords[1] === 'number') {
                    const lng = coords[0];
                    const lat = coords[1];

                    if (lng < minLng) minLng = lng;
                    if (lat < minLat) minLat = lat;
                    if (lng > maxLng) maxLng = lng;
                    if (lat > maxLat) maxLat = lat;
                    return;
                }

                coords.forEach(walk);
            };

            walk(geometry?.coordinates);

            if (
                !Number.isFinite(minLng) ||
                !Number.isFinite(minLat) ||
                !Number.isFinite(maxLng) ||
                !Number.isFinite(maxLat)
            ) {
                return null;
            }

            return [
                [minLng, minLat],
                [maxLng, maxLat],
            ];
        },

        getEventCoordinates(event) {
            const candidates = [
                [event?.lattitude, event?.langitude],
                [event?.latitude, event?.longitude],
                [event?.lat, event?.lng],
                [event?.lat, event?.lon],
                [event?.coordinates?.lat, event?.coordinates?.lng],
                [event?.coordinates?.latitude, event?.coordinates?.longitude],
            ];

            for (const pair of candidates) {
                const lat = Number(pair[0]);
                const lng = Number(pair[1]);

                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                    return { lat, lng };
                }
            }

            return null;
        },

        renderEventMarkers(maplibregl) {
            this.eventMarkers.forEach((marker) => marker.remove());
            this.eventMarkers = [];

            (this.events || []).forEach((event) => {
                const coords = this.getEventCoordinates(event);
                if (!coords) return;

                const markerEl = document.createElement('button');
                markerEl.type = 'button';
                markerEl.className = 'event-marker';
                markerEl.title = this.getDisplayEventTitle(event);
                markerEl.setAttribute('aria-label', this.getDisplayEventTitle(event));

                markerEl.innerHTML = `
                    <span class="event-marker-pin"></span>
                `;

                markerEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.goToEvent(event.slug);
                });

                const popup = new maplibregl.Popup({
                    closeButton: false,
                    closeOnClick: true,
                    offset: 20,
                }).setHTML(`
                    <div style="direction: ltr; text-align: left; min-width: 180px;">
                        <strong>${this.escapeHtml(this.getDisplayEventTitle(event))}</strong>
                        <div style="margin-top: 6px; font-size: 12px; color: #6b7280;">
                            ${this.escapeHtml(this.getDisplayCityName(event.city))}
                        </div>
                        <div style="margin-top: 8px; font-size: 12px; color: #2563eb;">
                            Click marker to open
                        </div>
                    </div>
                `);

                const marker = new maplibregl.Marker({
                    element: markerEl,
                    anchor: 'bottom',
                })
                    .setLngLat([coords.lng, coords.lat])
                    .setPopup(popup)
                    .addTo(this.map);

                this.eventMarkers.push(marker);
            });
        },

        escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        async initMap() {
            const container = this.$refs.mapContainer;

            if (!container) {
                console.warn('⚠️ mapContainer ref is missing');
                return;
            }

            if (!this.country) return;

            this.destroyMap();

            try {
                const maplibregl = await this.loadMapLibre();
                const geojson = await this.loadCountriesGeoJSON();
                const feature = this.findCountryFeature(geojson, this.country);

                if (!feature) {
                    const debugInfo = {
                        code: this.getCountryCode(),
                        englishName: this.getCountryEnglishName(),
                        arabicName: this.getCountryArabicName(),
                    };
                    console.error('Country match debug:', debugInfo);
                    throw new Error(
                        `Could not find borders for country (${debugInfo.code || 'UNKNOWN'}).`
                    );
                }

                const bounds = this.getGeometryBounds(feature.geometry);
                if (!bounds) {
                    throw new Error('Could not calculate country bounds.');
                }

                this.map = new maplibregl.Map({
                    container,
                    style: this.createNaturalRasterStyle(),
                    center: [0, 0],
                    zoom: 2,
                    attributionControl: false,
                    dragRotate: false,
                    touchPitch: false,
                    pitchWithRotate: false,
                    maxPitch: 0,
                    renderWorldCopies: false,
                });

                this.map.addControl(
                    new maplibregl.NavigationControl({ visualizePitch: false }),
                    'top-left'
                );

                this.map.on('load', () => {
                    this.map.fitBounds(bounds, {
                        padding: { top: 20, bottom: 20, left: 20, right: 20 },
                        duration: 0,
                        maxZoom: 6.5,
                    });

                    this.map.setMaxBounds(bounds);

                    this.renderEventMarkers(maplibregl);

                    setTimeout(() => {
                        this.map?.resize();
                    }, 150);
                });
            } catch (err) {
                console.error('Map error:', err);
                this.error = err?.message || 'An error occurred while loading the map.';
            }
        },
    },
};
</script>

<style scoped>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.group {
    animation: fadeInUp 0.5s ease-out forwards;
}

.country-map {
    position: relative;
}

.country-map :deep(.maplibregl-map) {
    width: 100%;
    height: 100%;
    font-family: inherit;
}

.country-map :deep(.maplibregl-canvas) {
    outline: none;
}

.country-map :deep(.maplibregl-popup-content) {
    border-radius: 12px;
    padding: 10px 12px;
    font-family: inherit;
}

.country-map :deep(.maplibregl-ctrl-group) {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
}

.country-map :deep(.event-marker) {
    background: transparent;
    border: 0;
    padding: 0;
    cursor: pointer;
    transform: translateY(2px);
}

.country-map :deep(.event-marker-pin) {
    display: block;
    width: 30px;
    height: 30px;
    background: #000000;
    border: 3px solid #111827;
    border-radius: 9999px 9999px 9999px 0;
    transform: rotate(-45deg);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
    position: relative;
}

.country-map :deep(.event-marker-pin::after) {
    content: '';
    position: absolute;
    width: 6px;
    height: 6px;
    background: #ffffff;
    border-radius: 9999px;
    top: 3px;
    left: 3px;
}
</style>
