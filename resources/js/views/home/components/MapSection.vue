<template>
<section ref="sectionRef" class="scemory-map-section home-map-shell relative h-[500px] md:h-[600px] min-h-[420px] overflow-hidden">
    <div id="map-main" class="absolute inset-0 w-full h-full"></div>

    <div
      v-if="!isMapReady && !mapError"
      class="map-loading-surface absolute inset-0 z-20 pointer-events-none"
      aria-hidden="true"
    >
      <div class="w-full h-full animate-pulse">
        <div class="h-full w-full bg-[linear-gradient(110deg,#EDF4FA,45%,#F7FAFD,55%,#EDF4FA)] bg-[length:200%_100%] animate-[shimmer_1.8s_linear_infinite]"></div>
      </div>
    </div>

    <div
      v-if="(!canInitMap && !isMapLoading) || (isMapLoading && !isMapReady) || !!mapError"
      class="map-state-overlay absolute inset-0 flex items-center justify-center z-30 pointer-events-none"
    >
      <button
        v-if="!canInitMap && !isMapLoading && !mapError"
        @click="$emit('load-map')"
        class="map-control-button pointer-events-auto px-6 py-3 rounded-full font-semibold transition"
      >
        {{ $t('homeAudit.map.loadMap') }}
      </button>

      <div
        v-else-if="isMapLoading && !isMapReady"
        class="map-status-pill pointer-events-auto inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium"
      >
        <span class="w-4 h-4 border-2 border-gray-300 border-t-blue-600 rounded-full animate-spin"></span>
        {{ $t('homeAudit.map.loading') }}
      </div>

      <div
        v-else-if="mapError"
        class="map-error-card pointer-events-auto inline-flex flex-col items-center gap-3 px-5 py-4 rounded-2xl text-sm"
      >
        <p class="font-medium">{{ mapError }}</p>
        <button
          @click="$emit('load-map')"
          class="map-control-button px-4 py-2 rounded-full transition"
        >
          {{ $t('homeAudit.map.retry') }}
        </button>
      </div>
    </div>

    <div class="absolute top-4 right-4 z-40">
      <button
        v-if="isMapReady"
        @click="$emit('open-fullscreen')"
        class="map-icon-button p-3 rounded-full transition"
        :aria-label="$t('homeAudit.map.openFullscreen')"
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
      <div v-show="fullscreen" class="map-fullscreen-backdrop fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="map-fullscreen-panel relative w-full h-full max-w-7xl max-h-[90vh] rounded-2xl overflow-hidden">
          <div id="map-fullscreen" class="absolute inset-0 w-full h-full"></div>

          <button
            @click="$emit('close-fullscreen')"
            class="map-icon-button absolute top-4 right-4 z-30 p-3 rounded-full transition"
            :aria-label="$t('homeAudit.map.closeFullscreen')"
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

.home-map-shell {
  border: 1px solid var(--scemory-border);
  border-radius: 26px;
  background: var(--scemory-surface-soft);
  box-shadow: 0 12px 34px rgba(13, 77, 151, 0.08);
}

.map-loading-surface {
  background: linear-gradient(145deg, var(--scemory-surface-soft), var(--scemory-surface));
}

.map-state-overlay {
  background: rgba(247, 250, 253, 0.42);
  backdrop-filter: blur(2px);
}

.map-control-button,
.map-icon-button,
.map-status-pill {
  border: 1px solid var(--scemory-border);
  background: rgba(247, 250, 253, 0.94);
  color: var(--scemory-primary);
  box-shadow: var(--scemory-shadow-sm);
  backdrop-filter: blur(14px);
}

.map-control-button:hover,
.map-icon-button:hover {
  transform: translateY(-1px);
  background: var(--scemory-hover);
  color: var(--scemory-blue);
  box-shadow: var(--scemory-shadow-hover);
}

.map-status-pill {
  color: var(--scemory-text);
}

.map-status-pill span {
  border-color: var(--scemory-border-soft);
  border-top-color: var(--scemory-blue);
}

.map-error-card {
  border: 1px solid var(--scemory-border);
  background: linear-gradient(145deg, #FFFFFF, var(--scemory-surface));
  color: var(--scemory-text);
  box-shadow: var(--scemory-shadow-strong);
}

.map-error-card p {
  color: var(--scemory-heading);
}

.map-fullscreen-backdrop {
  background: rgba(6, 20, 42, 0.55);
  backdrop-filter: blur(10px);
}

.map-fullscreen-panel {
  border: 1px solid var(--scemory-border);
  box-shadow: var(--scemory-shadow-strong);
}

@media (max-width: 640px) {
  .home-map-shell {
    border-radius: 22px;
    min-height: 420px;
  }
}
</style>
