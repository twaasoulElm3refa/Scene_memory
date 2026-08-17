<template>
<section ref="sectionRef" class="scemory-map-section home-map-shell">
    <div id="map-main" class="map-canvas"></div>

    <div
      v-if="!isMapReady && !mapError"
      class="map-loading-surface"
      aria-hidden="true"
    >
      <div class="map-loading-shimmer"></div>
    </div>

    <div
      v-if="(!canInitMap && !isMapLoading) || (isMapLoading && !isMapReady) || !!mapError"
      class="map-state-overlay"
    >
      <button
        v-if="!canInitMap && !isMapLoading && !mapError"
        @click="$emit('load-map')"
        class="map-control-button map-load-button"
      >
        {{ $t('homeAudit.map.loadMap') }}
      </button>

      <div
        v-else-if="isMapLoading && !isMapReady"
        class="map-status-pill"
      >
        <span class="map-loading-spinner"></span>
        {{ $t('homeAudit.map.loading') }}
      </div>

      <div
        v-else-if="mapError"
        class="map-error-card"
      >
        <p>{{ mapError }}</p>
        <button
          @click="$emit('load-map')"
          class="map-control-button map-retry-button"
        >
          {{ $t('homeAudit.map.retry') }}
        </button>
      </div>
    </div>

    <div class="map-actions">
      <button
        v-if="isMapReady"
        @click="$emit('open-fullscreen')"
        class="map-icon-button"
        :aria-label="$t('homeAudit.map.openFullscreen')"
      >
        <svg class="map-control-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
      <div v-show="fullscreen" class="map-fullscreen-backdrop">
        <div class="map-fullscreen-panel">
          <div id="map-fullscreen" class="map-canvas"></div>

          <button
            @click="$emit('close-fullscreen')"
            class="map-icon-button map-close-button"
            :aria-label="$t('homeAudit.map.closeFullscreen')"
          >
            <svg class="map-control-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

@keyframes map-spin {
  to {
    transform: rotate(360deg);
  }
}

.home-map-shell {
  position: relative;
  width: 100%;
  overflow: hidden;
  border: 1px solid var(--scemory-border);
  border-radius: 24px;
  height: 624px;
  min-height: 624px;
  background: #FFFFFF;
  box-shadow: 0 12px 34px rgba(13, 77, 151, 0.08);
}

.map-canvas,
.map-loading-surface,
.map-state-overlay {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.map-loading-surface {
  z-index: 20;
  pointer-events: none;
  background: linear-gradient(145deg, var(--scemory-surface-soft), var(--scemory-surface));
}

.map-loading-shimmer {
  width: 100%;
  height: 100%;
  background: linear-gradient(110deg, #EDF4FA 45%, #F7FAFD 55%, #EDF4FA 65%);
  background-size: 200% 100%;
  animation: shimmer 1.8s linear infinite;
}

.map-state-overlay {
  z-index: 30;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
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
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border-radius: 999px;
  padding: 9px 16px;
  color: var(--scemory-text);
  font-size: 14px;
  font-weight: 600;
  pointer-events: auto;
}

.map-loading-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid var(--scemory-border-soft);
  border-top-color: var(--scemory-blue);
  border-radius: 999px;
  animation: map-spin 0.8s linear infinite;
}

.map-control-button {
  border-radius: 999px;
  font-weight: 700;
  cursor: pointer;
  pointer-events: auto;
  transition: background-color 180ms ease, color 180ms ease, transform 180ms ease, box-shadow 180ms ease;
}

.map-load-button {
  padding: 12px 24px;
}

.map-retry-button {
  padding: 8px 16px;
}

.map-error-card {
  display: inline-flex;
  max-width: min(90%, 420px);
  flex-direction: column;
  align-items: center;
  gap: 12px;
  border: 1px solid var(--scemory-border);
  border-radius: 18px;
  background: #FFFFFF;
  padding: 16px 20px;
  color: var(--scemory-text);
  font-size: 14px;
  box-shadow: var(--scemory-shadow-strong);
  pointer-events: auto;
}

.map-error-card p {
  margin: 0;
  color: var(--scemory-heading);
  font-weight: 600;
}

.map-actions {
  position: absolute;
  top: 16px;
  inset-inline-end: 16px;
  z-index: 40;
}

.map-icon-button {
  display: inline-flex;
  width: 44px;
  height: 44px;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  cursor: pointer;
  transition: background-color 180ms ease, color 180ms ease, transform 180ms ease, box-shadow 180ms ease;
}

.map-control-icon {
  width: 20px;
  height: 20px;
}

.map-fullscreen-backdrop {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(6, 20, 42, 0.55);
  padding: 16px;
  backdrop-filter: blur(10px);
}

.map-fullscreen-panel {
  position: relative;
  width: 100%;
  height: 100%;
  max-width: 1280px;
  max-height: 90vh;
  overflow: hidden;
  border: 1px solid var(--scemory-border);
  border-radius: 24px;
  box-shadow: var(--scemory-shadow-strong);
}

.map-close-button {
  position: absolute;
  top: 16px;
  inset-inline-end: 16px;
  z-index: 30;
}

@media (min-width: 992px) and (max-width: 1399px) {
  .home-map-shell {
    height: 560px;
    min-height: 560px;
  }
}

@media (max-width: 640px) {
  .home-map-shell {
    border-radius: 22px;
    height: 360px;
    min-height: 320px;
  }
}

@media (min-width: 641px) and (max-width: 991px) {
  .home-map-shell {
    height: 440px;
    min-height: 400px;
  }
}
</style>
