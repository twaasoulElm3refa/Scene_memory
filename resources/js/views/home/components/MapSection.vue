<template>
  <section ref="sectionRef" class="relative h-[500px] md:h-[600px] min-h-[420px] bg-gray-900 overflow-hidden">
    <div id="map-main" class="absolute inset-0 w-full h-full"></div>

    <div
      v-if="!isMapReady && !mapError"
      class="absolute inset-0 z-20 pointer-events-none bg-gradient-to-b from-gray-100 to-gray-200"
      aria-hidden="true"
    >
      <div class="w-full h-full animate-pulse">
        <div class="h-full w-full bg-[linear-gradient(110deg,#e5e7eb,45%,#f3f4f6,55%,#e5e7eb)] bg-[length:200%_100%] animate-[shimmer_1.8s_linear_infinite]"></div>
      </div>
    </div>

    <div
      v-if="(!canInitMap && !isMapLoading) || (isMapLoading && !isMapReady) || !!mapError"
      class="absolute inset-0 bg-black/20 flex items-center justify-center z-30 pointer-events-none"
    >
      <button
        v-if="!canInitMap && !isMapLoading && !mapError"
        @click="$emit('load-map')"
        class="pointer-events-auto px-6 py-3 rounded-full bg-white text-gray-900 font-semibold shadow-lg hover:bg-gray-100 transition"
      >
        Load Map
      </button>

      <div
        v-else-if="isMapLoading && !isMapReady"
        class="pointer-events-auto inline-flex items-center gap-2 bg-white/90 px-4 py-2 rounded-full text-sm font-medium text-gray-700"
      >
        <span class="w-4 h-4 border-2 border-gray-300 border-t-blue-600 rounded-full animate-spin"></span>
        Loading map...
      </div>

      <div
        v-else-if="mapError"
        class="pointer-events-auto inline-flex flex-col items-center gap-3 bg-white/95 px-5 py-4 rounded-2xl text-sm text-gray-700 shadow"
      >
        <p class="font-medium">{{ mapError }}</p>
        <button
          @click="$emit('load-map')"
          class="px-4 py-2 rounded-full bg-blue-600 text-white hover:bg-blue-700 transition"
        >
          Retry loading map
        </button>
      </div>
    </div>

    <div class="absolute top-4 right-4 z-40">
      <button
        v-if="isMapReady"
        @click="$emit('open-fullscreen')"
        class="bg-white/90 backdrop-blur-sm p-3 rounded-full shadow-lg hover:bg-white transition text-gray-800"
        aria-label="Open fullscreen map"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"
          />
        </svg>
      </button>
    </div>

    <Teleport to="body">
      <div v-show="fullscreen" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
        <div class="relative w-full h-full max-w-7xl max-h-[90vh] rounded-2xl overflow-hidden shadow-2xl">
          <div id="map-fullscreen" class="absolute inset-0 w-full h-full"></div>

          <button
            @click="$emit('close-fullscreen')"
            class="absolute top-4 right-4 z-30 bg-white/90 backdrop-blur-sm p-3 rounded-full shadow-lg hover:bg-white transition text-gray-800"
            aria-label="Close fullscreen map"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from "vue";

defineProps({
  fullscreen: {
    type: Boolean,
    default: false,
  },
  isMapReady: {
    type: Boolean,
    default: false,
  },
  isMapLoading: {
    type: Boolean,
    default: false,
  },
  mapError: {
    type: String,
    default: "",
  },
  canInitMap: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["load-map", "open-fullscreen", "close-fullscreen", "map-viewport-enter"]);

const sectionRef = ref(null);
let observer = null;

onMounted(() => {
  if (!sectionRef.value || typeof IntersectionObserver === "undefined") {
    emit("map-viewport-enter");
    return;
  }

  observer = new IntersectionObserver(
    (entries) => {
      const entry = entries[0];
      if (entry?.isIntersecting) {
        emit("map-viewport-enter");
        observer?.disconnect();
      }
    },
    {
      root: null,
      rootMargin: "200px 0px",
      threshold: 0.01,
    }
  );

  observer.observe(sectionRef.value);
});

onUnmounted(() => {
  observer?.disconnect();
});
</script>

<style scoped>
@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }

  100% {
    background-position: -200% 0;
  }
}
</style>
