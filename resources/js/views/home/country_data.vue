<template>
  <div dir="rtl" class="min-h-screen bg-gray-50 p-4">

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center min-h-[300px]">
      <p class="text-gray-400 text-sm">جاري التحميل...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex items-center justify-center min-h-[300px]">
      <p class="text-red-400 text-sm">{{ error }}</p>
    </div>

    <!-- Content -->
    <div v-else-if="country && events" class="max-w-3xl mx-auto">

      <!-- Header -->
      <div class="flex items-center gap-2 mb-6">
        <h1 class="text-xl font-medium text-gray-800">
          {{ country.translation.name }}
        </h1>
        <span class="text-xs bg-blue-100 text-blue-600 px-3 py-1 rounded-full">
          {{ country.code }}
        </span>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <p class="text-xs text-gray-400 mb-1">المدن</p>
          <p class="text-2xl font-medium text-gray-800">
            {{ CountCitites }}
          </p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
          <p class="text-xs text-gray-400 mb-1">الفعاليات</p>
          <p class="text-2xl font-medium text-gray-800">
            {{ events.total ?? 0 }}
          </p>
        </div>
      </div>

      <!-- Cities -->
      <div class="mb-6">
        <h2 class="text-sm font-medium text-gray-500 mb-2">المدن المتاحة</h2>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="city in country.cities"
            :key="city.id"
            class="text-xs bg-gray-100 text-gray-600 px-3 py-1 rounded-full"
          >
            {{ city.translation.name }}
          </span>
        </div>
      </div>

      <!-- Events -->
      <div>
        <h2 class="text-sm font-medium text-gray-500 mb-3">الفعاليات</h2>
        <div class="flex flex-col gap-3">
          <div
            v-for="event in events.data"
            :key="event.id"
            class="bg-white rounded-xl border border-gray-100 p-4 flex gap-3 items-start"
          >
            <!-- Image -->
            <div class="w-16 h-16 rounded-lg overflow-hidden flex-shrink-0 bg-gray-100">
              <img
                v-if="event.first_image"
                :src="`/storage/${event.first_image.full_url}`"
                :alt="event.translation.title"
                class="w-full h-full object-cover"
              />
              <div
                v-else
                class="w-full h-full flex items-center justify-center text-gray-300 text-xs text-center"
              >
                لا توجد صورة
              </div>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <h3 class="text-sm font-medium text-gray-800">
                  {{ event.translation.title }}
                </h3>
                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                  {{ event.sub_categorey.translation.name }}
                </span>
              </div>

              <p class="text-xs text-gray-400 leading-relaxed line-clamp-2 mb-2">
                {{ event.translation.description }}
              </p>

              <div class="flex items-center gap-1 text-xs text-gray-400">
                <span>{{ event.city.translation.name }}</span>
                <span class="text-gray-200">·</span>
                <span>{{ event.start_date }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div
        v-if="events.last_page > 1"
        class="flex items-center justify-center gap-4 mt-6"
      >
        <button
          :disabled="!events.prev_page_url"
          @click="fetchData(currentPage - 1)"
          class="text-sm px-4 py-2 rounded-lg border border-gray-200 text-gray-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50 transition"
        >
          السابق
        </button>
        <span class="text-sm text-gray-400">
          {{ events.current_page }} / {{ events.last_page }}
        </span>
        <button
          :disabled="!events.next_page_url"
          @click="fetchData(currentPage + 1)"
          class="text-sm px-4 py-2 rounded-lg border border-gray-200 text-gray-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50 transition"
        >
          التالي
        </button>
      </div>

    </div>
  </div>
</template>

<script>
import CountryService from '@/services/CountryService';
import { ref } from 'vue';
export default {
  name: 'CountryData',

  data() {
    return {
      country: null,
      events: null,
      currentPage: 1,
      loading: false,
      error: null,
      CountCitites:0
    };
  },

  computed: {
    countryCode() {
      return this.$route.params.code;
    },
  },

  async created() {
    await this.fetchData(this.currentPage);
  },

  methods: {
    async fetchData(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await CountryService.getCountryStats(this.countryCode, page);
        this.country = response.data.data.country;
        this.events  = response.data.data.events;
        this.currentPage = this.events.current_page;
        this.CountCitites = this.country.cities_count;
      } catch (err) {
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
  },
};
</script>
