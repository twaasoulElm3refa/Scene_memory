<template>
  <section v-if="searched" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
    <div class="flex flex-col md:flex-row md:justify-between md:items-end mb-10">
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
        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">
          {{ $t("events.recentMemories") }}
        </h2>
        <p class="text-gray-500 text-base md:text-lg max-w-xl">
          <!-- {{ $t("events.discoverAroundYou") }} -->
        </p>
        <p v-if="totalResults > 0" class="mt-2 text-sm font-medium text-gray-500">
          Showing {{ resultFrom || 0 }} - {{ resultTo || 0 }} of {{ totalResults || 0 }} events
        </p>
      </div>
    </div>

    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 md:gap-8">
      <div
        v-for="i in Number(perPage) || 8"
        :key="`event-skeleton-${i}`"
        class="bg-white rounded overflow-hidden shadow-lg animate-pulse"
      >
        <div class="aspect-[4/3] bg-gray-200"></div>
        <div class="p-5 space-y-3">
          <div class="h-4 w-2/3 bg-gray-200 rounded"></div>
          <div class="h-4 w-full bg-gray-200 rounded"></div>
          <div class="h-4 w-4/5 bg-gray-200 rounded"></div>
        </div>
      </div>
    </div>

    <div v-else-if="displayedEvents.length === 0" class="text-center py-20 bg-gray-50 rounded-3xl">
      <div class="text-7xl mb-6">&#127917;</div>
      <h3 class="text-2xl font-bold text-gray-900 mb-3">
        {{ $t("events.noEventsFound") }}
      </h3>
      <p class="text-lg text-gray-600">
        {{ $t("events.noMatchingEvents") }}
      </p>
    </div>

    <template v-else>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 md:gap-8">
        <div
          v-for="(event, index) in paginatedEvents"
          :key="event.slug || event.id"
          class="group bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1"
        >
          <div class="aspect-[4/3] relative overflow-hidden bg-gray-100">
            <picture>
              <source v-if="event.image_webp_url" :srcset="event.image_webp_url" type="image/webp" />
              <img
                :src="event.image_url || fallbackImage"
                :alt="event.title"
                class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                :loading="index === 0 ? 'eager' : 'lazy'"
                :fetchpriority="index === 0 ? 'high' : 'auto'"
                decoding="async"
              />
            </picture>
            <div class="absolute top-4 left-4 z-10">
              <span
                class="bg-white/90 backdrop-blur-sm text-gray-900 text-xs font-bold px-3 py-1.5 rounded-full shadow"
              >
                {{ event.category_name || $t("events.event") }}
              </span>
            </div>
          </div>
          <div class="p-5">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
              <span class="text-blue-600">&#128197;</span>
              <span class="font-medium">
                {{ $t("events.startDate") }} : {{ formatDate(event.start_date) }}
              </span>
            </div>
            <h4
              class="text-right text-base md:text-lg font-semibold mb-3 line-clamp-2 text-gray-900 group-hover:text-blue-600 transition-colors"
            >
              {{ event.translation?.title || $t("common.notSpecified") }}
            </h4>
            <div class="flex justify-between items-center pt-3 border-t border-gray-100 text-sm">
              <span class="text-gray-600">
                {{ event.city || $t("common.notSpecified") }}
              </span>
              <a
                :href="`/${lang}/single_event/${event.slug}`"
                class="text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1"
              >
                {{ $t("common.details") }} &#8594;
              </a>
            </div>
          </div>
        </div>
      </div>

      <div v-if="totalPages > 1" class="flex flex-wrap justify-center mt-12 gap-2">
        <button
          @click="emitPageChange(Math.max(1, currentPage - 1))"
          :disabled="currentPage <= 1 || loading"
          class="px-4 py-2 rounded text-sm font-medium bg-white border border-gray-200 text-gray-700 hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {{ $t("pagination.previous") }}
        </button>

        <button
          v-for="page in visiblePages"
          :key="page"
          @click="emitPageChange(page)"
          :disabled="loading || page === currentPage"
          :class="[
            'px-4 py-2 rounded text-sm font-medium min-w-[40px] disabled:cursor-not-allowed',
            currentPage === page
              ? 'bg-blue-600 text-white shadow'
              : 'bg-white border border-gray-200 text-gray-700 hover:bg-blue-50'
          ]"
        >
          {{ page }}
        </button>

        <button
          @click="emitPageChange(Math.min(totalPages, currentPage + 1))"
          :disabled="currentPage >= totalPages || loading"
          class="px-4 py-2 rounded text-sm font-medium bg-white border border-gray-200 text-gray-700 hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {{ $t("pagination.next") }}
        </button>
      </div>
    </template>
  </section>
</template>

<script setup>
defineProps({
  searched: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  displayedEvents: {
    type: Array,
    default: () => [],
  },
  paginatedEvents: {
    type: Array,
    default: () => [],
  },
  visiblePages: {
    type: Array,
    default: () => [],
  },
  currentPage: {
    type: Number,
    default: 1,
  },
  totalPages: {
    type: Number,
    default: 1,
  },
  totalResults: {
    type: Number,
    default: 0,
  },
  resultFrom: {
    type: Number,
    default: null,
  },
  resultTo: {
    type: Number,
    default: null,
  },
  perPage: {
    type: Number,
    default: 8,
  },
  fallbackImage: {
    type: String,
    default: "",
  },
  formatDate: {
    type: Function,
    required: true,
  },
  lang: {
    type: String,
    default: "en",
  },
});

const emit = defineEmits(["update:current-page"]);

const emitPageChange = (page) => {
  emit("update:current-page", page);
};
</script>
