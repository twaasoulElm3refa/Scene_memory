<template>
  <div class="scemory-page gate-page min-h-screen bg-gray-50 p-6 rtl">

    <!-- Search Bar -->
    <div class="relative max-w-lg mx-auto mb-10">
      <div class="relative">
        <span class="absolute inset-y-0 right-3 flex items-center text-gray-400" aria-hidden="true">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 21l-4.35-4.35m1.1-5.15a6.25 6.25 0 11-12.5 0 6.25 6.25 0 0112.5 0z" />
          </svg>
        </span>
        <input
          v-model="searchQuery"
          type="text"
          class="w-full pr-10 pl-4 py-3 rounded-2xl border-2 border-gray-200 bg-white shadow-sm text-sm focus:outline-none focus:border-indigo-400 transition-all duration-200"
          :placeholder="$t('gate.searchCountry')"
          @blur="closeDropdownDelayed"
          @focus="openDropdown"
        />
      </div>

      <!-- Dropdown -->
      <ul
        v-if="showDropdown && filteredCountries.length"
        class="absolute top-[110%] right-0 left-0 bg-white border border-gray-100 rounded-2xl shadow-xl max-h-60 overflow-y-auto z-50 list-none p-1 m-0"
      >
        <li
          v-for="country in filteredCountries"
          :key="country.id"
          @mousedown.prevent="selectCountry(country)"
          class="flex items-center gap-2 px-4 py-2.5 rounded-xl cursor-pointer hover:bg-indigo-50 text-sm text-gray-700 transition-colors duration-150"
        >
          <span class="font-medium">{{ country.translation?.name || country.name }}</span>
          <span class="text-xs text-gray-400 mr-auto">{{ country.code }}</span>
        </li>
      </ul>

      <!-- No Results -->
      <div
        v-if="showDropdown && searchQuery && !filteredCountries.length"
        class="absolute top-[110%] right-0 left-0 bg-white border border-gray-100 rounded-2xl shadow-xl z-50 p-4 text-center text-sm text-gray-400"
      >
        {{ $t('common.noResults') }}
      </div>
    </div>

    <!-- Random Events -->
    <div class="max-w-7xl mx-auto">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">
        {{ $t('gate.featuredEvents') }}
      </h2>

      <!-- Skeleton Loading -->
      <div v-if="loadingEvents" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div
          v-for="n in 8"
          :key="n"
          class="bg-white rounded-2xl overflow-hidden shadow-sm animate-pulse"
        >
          <div class="h-48 bg-gray-200"></div>
          <div class="p-4 space-y-3">
            <div class="h-4 bg-gray-200 rounded-full w-3/4"></div>
            <div class="h-3 bg-gray-100 rounded-full w-full"></div>
            <div class="h-3 bg-gray-100 rounded-full w-2/3"></div>
            <div class="h-8 bg-gray-200 rounded-xl w-1/3 mt-2"></div>
          </div>
        </div>
      </div>

      <!-- Events Grid -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div
          v-for="event in randomEvents"
          :key="event.id"
          class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 flex flex-col"
        >
          <!-- Image -->
          <div class="relative h-48 overflow-hidden">
            <img
              v-if="event.first_image"
              :src="`/storage/${event.first_image.full_url}`"
              :alt="event.translation?.title || event.title"
              class="w-full h-full object-cover"
            />
            <div
              v-else
              class="w-full h-full bg-gradient-to-br from-indigo-50 to-purple-50 flex items-center justify-center text-sm font-bold"
            >
              {{ $t('gate.media') }}
            </div>

            <!-- Category Badge -->
            <span
              v-if="event.sub_categorey?.translation?.name"
              class="absolute top-3 left-3 bg-white/90 backdrop-blur-sm text-indigo-600 text-xs font-semibold px-2.5 py-1 rounded-full shadow-sm"
            >
              {{ event.sub_categorey.translation.name }}
            </span>
          </div>

          <!-- Body -->
          <div class="p-4 flex flex-col flex-1">
            <h3 class="font-bold text-gray-800 text-sm mb-2 line-clamp-2 leading-relaxed">
              {{ event.translation?.title || event.title }}
            </h3>

            <p class="text-xs text-gray-500 mb-3 line-clamp-2 leading-relaxed flex-1">
              {{ event.translation?.description || event.description }}
            </p>

            <!-- Meta -->
            <div class="flex flex-col gap-1 mb-4">
              <div class="flex items-center gap-1.5 text-xs text-gray-500">
                <span aria-hidden="true">{{ $t('gate.locationShort') }}</span>
                <span>{{ event.city?.translation?.name || event.city?.name }}</span>
              </div>
              <div class="flex items-center gap-1.5 text-xs text-gray-500">
                <span aria-hidden="true">{{ $t('gate.dateShort') }}</span>
                <span>{{ formatDate(event.start_date) }}</span>
              </div>
            </div>

            <!-- Button -->
            <router-link
              :to="`/${$route.params.lang}/${getCountryCode(event)}/scemory-gate`"
              class="block text-center bg-indigo-500 hover:bg-indigo-600 text-white text-xs font-semibold py-2 rounded-xl transition-colors duration-200"
            >
              {{ $t('common.details') }}
            </router-link>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import GateService from '@/services/GateService/GateService';

export default {
  name: 'GatePage',

  data() {
    return {
      searchQuery: '',
      showDropdown: false,
      countries: [],
      randomEvents: [],
      loadingEvents: false,
    };
  },

  computed: {
    filteredCountries() {
      if (!this.searchQuery.trim()) return [];
      const q = this.searchQuery.toLowerCase();
      // البحث على translation name فقط
      return this.countries.filter((c) =>
        (c.translation?.name?.toLowerCase() || '').includes(q)
      );
    },
  },

  methods: {
    async fetchRandomEvents() {
      this.loadingEvents = true;
      try {
        const { data } = await GateService.getRandomEvents();
        this.randomEvents = data.data;
      } catch (e) {
        console.error('Error fetching random events:', e);
      } finally {
        this.loadingEvents = false;
      }
    },

    async fetchCountries() {
      try {
        const { data } = await GateService.getAllCountries();
        this.countries = data.data;
      } catch (e) {
        console.error('Error fetching countries:', e);
      }
    },

    selectCountry(country) {
      this.searchQuery = country.translation?.name || country.name;
      this.showDropdown = false;
      // بيبعت الـ code الأصلي مش اسم الترجمة
      this.$router.push(
        `/${this.$route.params.lang}/${country.code}/scemory-gate`
      );
    },

    openDropdown() {
      this.showDropdown = true;
    },

    closeDropdownDelayed() {
      setTimeout(() => {
        this.showDropdown = false;
      }, 150);
    },

    getCountryCode(event) {
      const country = this.countries.find(
        (c) => c.id === event.city?.country_id
      );
      return country?.code || this.$route.params.code || 'EG';
    },

    formatDate(dateStr) {
      if (!dateStr) return '';
      return new Date(dateStr).toLocaleDateString('ar-EG', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
    },
  },

  mounted() {
    Promise.all([this.fetchRandomEvents(), this.fetchCountries()]);
  },
};
</script>

<style scoped>
.gate-page {
  background:
    radial-gradient(circle at 85% 10%, rgba(48, 168, 255, 0.06), transparent 28rem),
    linear-gradient(180deg, var(--scemory-surface), var(--scemory-surface-soft) 48%, var(--scemory-surface)) !important;
}

.gate-page input,
.gate-page ul,
.gate-page .bg-white {
  border-color: var(--scemory-border-soft) !important;
  background: linear-gradient(145deg, #FFFFFF, var(--scemory-surface)) !important;
  box-shadow: var(--scemory-shadow);
}

.gate-page li:hover {
  background: var(--scemory-hover) !important;
}

.gate-page h2,
.gate-page h3 {
  color: var(--scemory-heading) !important;
}

.gate-page .text-indigo-600 {
  color: var(--scemory-primary) !important;
}

.gate-page a.bg-indigo-500 {
  background: linear-gradient(135deg, var(--scemory-primary), var(--scemory-blue)) !important;
  color: #FFFFFF !important;
  box-shadow: 0 8px 20px rgba(13, 77, 151, 0.16);
}
</style>
