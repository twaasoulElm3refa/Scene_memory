<template>
    <div dir="rtl" class="min-h-screen bg-gradient-to-b from-gray-50 to-white">
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center min-h-screen">
            <div class="text-center">
                <div class="relative">
                    <div class="w-16 h-16 border-4 border-gray-100 border-t-blue-500 rounded-full animate-spin mx-auto mb-4"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-8 h-8 bg-blue-500 rounded-full animate-pulse"></div>
                    </div>
                </div>
                <p class="text-gray-400 text-sm font-medium mt-2">جاري التحميل...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center min-h-screen p-4">
            <div class="text-center max-w-md mx-auto bg-white rounded-2xl shadow-xl p-8 border border-red-50">
                <div class="w-20 h-20 bg-gradient-to-br from-red-50 to-red-100 rounded-full flex items-center justify-center mx-auto mb-5">
                    <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-red-500 font-medium">{{ error }}</p>
                <button @click="fetchData(1)" class="mt-5 px-6 py-2 bg-blue-500 text-white rounded-xl text-sm font-medium hover:bg-blue-600 transition-colors">
                    محاولة مرة أخرى
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div v-else-if="country && events" class="max-w-6xl mx-auto px-4 sm:px-6 py-8 md:py-12">

            <!-- Hero Section -->
            <div class="relative mb-12">
                <div class="absolute inset-0 bg-gradient-to-r from-blue-600/5 to-purple-600/5 rounded-3xl blur-3xl -z-10"></div>
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent">
                                {{ country.translation.name }}
                            </h1>
                            <span class="px-3 py-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-sm rounded-full font-medium shadow-sm">
                                {{ country.code }}
                            </span>
                        </div>
                        <p class="text-gray-500 text-sm md:text-base">اكتشف الفعاليات المميزة في مدن {{ country.translation.name }}</p>
                    </div>

                    <button @click="$router.back()" class="group flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-200 rounded-2xl text-gray-600 hover:border-gray-300 hover:shadow-md transition-all duration-200">
                        <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span class="text-sm font-medium">رجوع</span>
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
                <div class="group bg-white rounded-2xl p-6 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-gray-50">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-gray-400 text-sm font-medium">المدن</span>
                        <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800">{{ CountCitites }}</p>
                    <p class="text-xs text-gray-400 mt-1">مدينة متاحة</p>
                </div>

                <div class="group bg-white rounded-2xl p-6 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-gray-50">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-gray-400 text-sm font-medium">الفعاليات</span>
                        <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800">{{ events.length }}</p>
                    <p class="text-xs text-gray-400 mt-1">فعالية حالية</p>
                </div>

                <div class="group bg-white rounded-2xl p-6 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-gray-50">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-gray-400 text-sm font-medium">مدن متنوعة</span>
                        <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800">{{ CountCitites }}</p>
                    <p class="text-xs text-gray-400 mt-1">وجهة سياحية</p>
                </div>

                <div class="group bg-white rounded-2xl p-6 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-gray-50">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-gray-400 text-sm font-medium">التصنيفات</span>
                        <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-800">{{ getUniqueCategoriesCount }}</p>
                    <p class="text-xs text-gray-400 mt-1">فئة مختلفة</p>
                </div>
            </div>

            <!-- Cities Section -->
            <div class="mb-12">
                <div class="flex items-center gap-2 mb-5">
                    <div class="w-1 h-6 bg-gradient-to-b from-blue-500 to-purple-500 rounded-full"></div>
                    <h2 class="text-xl font-bold text-gray-800">المدن المتاحة</h2>
                    <span class="text-xs bg-gray-100 text-gray-600 px-2.5 py-0.5 rounded-full">{{ CountCitites }}</span>
                </div>

                <div class="flex flex-wrap gap-3">
                    <span v-for="city in country.cities" :key="city.id"
                        class="group inline-flex items-center gap-2 text-sm bg-white border border-gray-200 text-gray-700 px-5 py-2.5 rounded-full shadow-sm hover:shadow-md hover:border-blue-300 hover:text-blue-600 transition-all duration-200 cursor-default">
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ city.translation.name }}
                    </span>
                </div>
            </div>

            <!-- Events Section -->
            <div>
                <div class="flex items-center gap-2 mb-6">
                    <div class="w-1 h-6 bg-gradient-to-b from-blue-500 to-purple-500 rounded-full"></div>
                    <h2 class="text-xl font-bold text-gray-800">جميع الفعاليات</h2>
                    <span class="text-xs bg-gray-100 text-gray-600 px-2.5 py-0.5 rounded-full">{{ events.length }}</span>
                </div>

                <!-- No Events Message -->
                <div v-if="events.length === 0" class="text-center py-16 bg-white rounded-2xl border border-gray-100">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <p class="text-gray-400 font-medium">لا توجد فعاليات حالياً</p>
                    <p class="text-sm text-gray-300 mt-1">ترقب المزيد من الفعاليات قريباً</p>
                </div>

                <!-- Events Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="event in events" :key="event.id"
                        class="group bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-300">

                        <!-- Image Container -->
                        <div class="relative h-52 overflow-hidden bg-gradient-to-br from-gray-100 to-gray-50">
                            <img v-if="event.first_image" :src="`/storage/${event.first_image.full_url}`"
                                :alt="event.translation.title"
                                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                            <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-300">
                                <svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-xs">لا توجد صورة</span>
                            </div>

                            <!-- Category Badge -->
                            <div class="absolute top-3 right-3">
                                <span class="text-xs bg-white/95 backdrop-blur-sm text-gray-700 px-3 py-1.5 rounded-full font-medium shadow-sm">
                                    {{ event.sub_categorey.translation.name }}
                                </span>
                            </div>

                            <!-- Date Badge (top left) -->
                            <div class="absolute bottom-3 left-3">
                                <span class="text-xs bg-black/50 backdrop-blur-sm text-white px-3 py-1.5 rounded-full font-medium flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ formatDate(event.start_date) }}
                                </span>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-5">
                            <h3 class="text-lg font-bold text-gray-800 line-clamp-1 mb-2 group-hover:text-blue-600 transition-colors">
                                {{ event.translation.title }}
                            </h3>

                            <p class="text-sm text-gray-500 leading-relaxed line-clamp-2 mb-4">
                                {{ event.translation.description }}
                            </p>

                            <div class="flex items-center gap-2 text-xs text-gray-400 mb-4 pb-3 border-b border-gray-50">
                                <div class="flex items-center gap-1.5 bg-gray-50 px-3 py-1.5 rounded-full">
                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="text-gray-600">{{ event.city.translation.name }}</span>
                                </div>
                            </div>

                            <button @click="goToEvent(event.slug)" class="w-full inline-flex items-center justify-center gap-2 text-sm font-medium
                                bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white
                                px-4 py-2.5 rounded-xl transition-all duration-200
                                shadow-md hover:shadow-lg active:scale-[0.98]">
                                <span>عرض الفعالية</span>
                                <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            events: null,
            loading: false,
            error: null,
            CountCitites: 0
        };
    },

    computed: {
        countryCode() {
            return this.$route.params.code;
        },

        getUniqueCategoriesCount() {
            if (!this.events) return 0;
            const uniqueCategories = new Set(this.events.map(event => event.sub_categorey?.translation?.name));
            return uniqueCategories.size;
        }
    },

    async created() {
        await this.fetchData();
    },

    methods: {
        goToEvent(slug) {
            this.$router.push({ name: 'event-details', params: { slug } });
        },

        async fetchData() {
            this.loading = true;
            this.error = null;

            try {
                const response = await CountryService.getCountryStats(this.countryCode);

                this.country = response.data.data.country;
                this.events = response.data.data.events;

                this.CountCitites = this.country.cities_count || this.country.cities?.length || 0;

            } catch (err) {
                console.error(err);

                if (err.response?.status === 401) {
                    this.error = 'غير مصرح. يرجى تسجيل الدخول.';
                } else if (err.response?.status === 404) {
                    this.error = 'الدولة غير موجودة.';
                } else {
                    this.error = 'حدث خطأ أثناء تحميل البيانات.';
                }
            } finally {
                this.loading = false;
            }
        },

        formatDate(date) {
            if (!date) return '';

            const dateObj = new Date(date);
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);

            if (dateObj.toDateString() === today.toDateString()) {
                return 'اليوم';
            } else if (dateObj.toDateString() === tomorrow.toDateString()) {
                return 'غداً';
            }

            return new Intl.DateTimeFormat('ar-EG', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }).format(dateObj);
        }
    }
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
</style>
