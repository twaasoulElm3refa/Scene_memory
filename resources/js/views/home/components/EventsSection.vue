<template>
<section v-if="searched" class="scemory-events-section home-events-results max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
    <div class="flex flex-col gap-5 md:flex-row md:justify-between md:items-end mb-10">
      <div>
        <p v-if="totalResults > 0" class="mt-2 text-sm font-medium text-gray-500">
          Showing {{ resultFrom || 0 }} - {{ resultTo || 0 }} of {{ totalResults || 0 }} events
        </p>
      </div>

      <button
        v-if="showSeeMore && displayedEvents.length > 0 && !loading"
        type="button"
        class="see-more-results-btn inline-flex items-center justify-center rounded-full px-6 py-3 text-sm font-semibold text-white transition"
        @click="$emit('see-more')"
      >
        See More
        <span class="see-more-arrow" aria-hidden="true">&#8594;</span>
      </button>
    </div>

    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 md:gap-8">
      <div
        v-for="i in Number(perPage) || 8"
        :key="`event-skeleton-${i}`"
        class="event-skeleton-card overflow-hidden animate-pulse"
      >
        <div class="aspect-[4/3] bg-gray-200"></div>
        <div class="p-5 space-y-3">
          <div class="h-4 w-2/3 bg-gray-200 rounded"></div>
          <div class="h-4 w-full bg-gray-200 rounded"></div>
          <div class="h-4 w-4/5 bg-gray-200 rounded"></div>
        </div>
      </div>
    </div>

    <div v-else-if="displayedEvents.length === 0" class="empty-events-state text-center py-20 rounded-3xl">
      <div class="empty-events-mark mb-6">EVENTS</div>
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
          class="home-event-card group rounded-2xl overflow-hidden transition-all duration-300"
        >
          <div class="aspect-[4/3] relative overflow-hidden bg-gray-100">
            <picture>
              <source v-if="event.image_webp_url" :srcset="event.image_webp_url" type="image/webp" />
              <img
                :src="event.image_url || fallbackImage"
                :alt="event.title"
                class="w-full h-full object-cover group-hover:scale-[1.02] transition duration-300"
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

      <!-- <div v-if="totalPages > 1" class="flex flex-wrap justify-center mt-12 gap-2">
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
      </div> -->
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
  showSeeMore: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:current-page", "see-more"]);

const emitPageChange = (page) => {
  emit("update:current-page", page);
};
</script>

<style scoped>
.home-events-results {
  color: var(--scemory-text);
}

.home-events-results > div:first-child {
  border: 1px solid var(--scemory-border-soft);
  border-radius: 24px;
  background: linear-gradient(145deg, rgba(255, 255, 255, 0.78), rgba(247, 250, 253, 0.92));
  padding: 24px;
  box-shadow: var(--scemory-shadow-sm);
}

.home-events-results .inline-flex.bg-blue-50 {
  border: 1px solid var(--scemory-border);
  background: var(--scemory-active) !important;
  color: var(--scemory-primary) !important;
}

.home-events-results h2,
.home-events-results h3,
.home-events-results h4 {
  color: var(--scemory-heading) !important;
}

.home-events-results p,
.home-events-results .text-gray-500,
.home-events-results .text-gray-600 {
  color: var(--scemory-muted) !important;
}

.event-skeleton-card,
.home-event-card,
.empty-events-state {
  border: 1px solid var(--scemory-border-soft);
  border-radius: 24px;
  background: linear-gradient(145deg, #FFFFFF, var(--scemory-surface));
  box-shadow: 0 8px 26px rgba(13, 77, 151, 0.06);
}

.home-event-card:hover {
  transform: translateY(-2px);
  border-color: var(--scemory-border);
  box-shadow: var(--scemory-shadow-hover);
}

.home-event-card .bg-white\/90 {
  border: 1px solid rgba(22, 119, 255, 0.16);
  background: rgba(221, 236, 249, 0.92) !important;
  color: var(--scemory-primary) !important;
}

.home-event-card .border-gray-100 {
  border-color: var(--scemory-border-soft) !important;
}

.home-event-card a,
.home-event-card .text-blue-600 {
  color: var(--scemory-primary) !important;
}

.home-event-card a:hover {
  color: var(--scemory-blue) !important;
}

.event-skeleton-card .bg-gray-200,
.event-skeleton-card .bg-gray-100 {
  background: var(--scemory-surface-soft) !important;
}

.empty-events-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 92px;
  min-height: 42px;
  border: 1px solid var(--scemory-border);
  border-radius: 999px;
  background: var(--scemory-active);
  color: var(--scemory-primary);
  font-size: 0.75rem;
  font-weight: 800;
}

.home-events-results button {
  border-color: var(--scemory-border) !important;
  border-radius: 14px !important;
  background: var(--scemory-control) !important;
  color: var(--scemory-text) !important;
  transition: var(--scemory-transition);
}

.home-events-results button:hover:not(:disabled) {
  transform: translateY(-1px);
  background: var(--scemory-hover) !important;
}

.home-events-results button.bg-blue-600,
.home-events-results button[class*="bg-blue-600"] {
  background: linear-gradient(135deg, var(--scemory-primary), var(--scemory-blue)) !important;
  color: #FFFFFF !important;
  border-color: rgba(22, 119, 255, 0.24) !important;
  box-shadow: 0 8px 20px rgba(13, 77, 151, 0.16);
}

.home-events-results .see-more-results-btn {
  min-height: 46px;
  border: 1px solid rgba(22, 119, 255, 0.22) !important;
  background: linear-gradient(135deg, var(--scemory-primary), var(--scemory-blue)) !important;
  color: #FFFFFF !important;
  box-shadow: 0 10px 24px rgba(13, 77, 151, 0.16);
}

.home-events-results .see-more-results-btn:hover {
  background: linear-gradient(135deg, var(--scemory-blue), var(--scemory-light-blue)) !important;
}

.see-more-arrow {
  margin-inline-start: 0.45rem;
}
</style>
