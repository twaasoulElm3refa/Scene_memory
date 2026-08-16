<template>
  <section class="scemory-experience-section" aria-labelledby="scemory-experience-title">
    <div class="experience-inner">
      <header class="experience-header">
        <span class="experience-kicker">{{ $t('experience.kicker') }}</span>
        <h3 id="scemory-experience-title">{{ $t('experience.title') }}</h3>
        <!-- <p>
          Explore moments, document your own, or discover media captured by contributors around the world.
        </p> -->
      </header>

      <div class="experience-tabs" role="tablist" :aria-label="$t('experience.pathsLabel')">
        <button
          v-for="(tab, index) in tabs"
          :id="`scemory-tab-${tab.id}`"
          :key="tab.id"
          type="button"
          class="experience-tab"
          :class="{ 'is-active': activeTabId === tab.id }"
          role="tab"
          :aria-selected="activeTabId === tab.id"
          :aria-controls="`scemory-panel-${tab.id}`"
          :tabindex="activeTabId === tab.id ? 0 : -1"
          @click="setActiveTab(tab.id)"
          @keydown="handleTabKeydown($event, index)"
        >
          <span class="tab-number">{{ String(index + 1).padStart(2, "0") }}</span>
          <span class="tab-label">{{ $t(tab.label) }}</span>
        </button>
      </div>

      <Transition name="experience-fade" mode="out-in">
        <div
          :id="`scemory-panel-${activeTab.id}`"
          :key="activeTab.id"
          class="experience-body"
          :class="`is-${activeTab.id}`"
          role="tabpanel"
          :aria-labelledby="`scemory-tab-${activeTab.id}`"
        >
          <div class="experience-media">
            <img :src="activeTab.image" :alt="$t(activeTab.imageAltKey)" />
            <div class="media-sheen"></div>

            <div class="media-card media-card-primary">
              <span>{{ $t(activeTab.visual.kickerKey) }}</span>
              <strong>{{ $t(activeTab.visual.titleKey) }}</strong>
            </div>

            <div class="media-card media-card-secondary">
              <span>{{ $t(activeTab.visual.statKey) }}</span>
              <strong>{{ $t(activeTab.visual.noteKey) }}</strong>
            </div>

            <div class="media-path" aria-hidden="true">
              <span v-for="dot in 4" :key="dot"></span>
            </div>
          </div>

          <article class="experience-copy">
            <span class="copy-kicker">{{ $t(activeTab.eyebrowKey) }}</span>
            <h3>{{ $t(activeTab.headingKey) }}</h3>
            <p>{{ $t(activeTab.descriptionKey) }}</p>

            <ul class="experience-features" :aria-label="$t('experience.highlightsLabel')">
              <li v-for="featureKey in activeTab.featureKeys" :key="featureKey">
                <span class="feature-check" aria-hidden="true">
                  <i class="bi bi-check2"></i>
                </span>
                <span>{{ $t(featureKey) }}</span>
              </li>
            </ul>

            <RouterLink class="experience-cta" :to="localizedPath(activeTab.to)">
              {{ $t(activeTab.ctaKey) }}
            </RouterLink>
          </article>
        </div>
      </Transition>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from "vue";
import { RouterLink } from "vue-router";

const lang = localStorage.getItem("language") || localStorage.getItem("lang") || "en";

const tabs = [
  {
    id: "explore",
    label: "experience.tabs.explore.label",
    eyebrowKey: "experience.tabs.explore.eyebrow",
    headingKey: "experience.tabs.explore.heading",
    descriptionKey: "experience.tabs.explore.description",
    ctaKey: "experience.tabs.explore.cta",
    to: "/all_events",
    image: "/images/photo1.png",
    imageAltKey: "experience.tabs.explore.imageAlt",
    featureKeys: [
      "experience.tabs.explore.features.browse",
      "experience.tabs.explore.features.discover",
      "experience.tabs.explore.features.move",
    ],
    visual: {
      kickerKey: "experience.tabs.explore.visual.kicker",
      titleKey: "experience.tabs.explore.visual.title",
      statKey: "experience.tabs.explore.visual.stat",
      noteKey: "experience.tabs.explore.visual.note",
    },
  },
  {
    id: "share",
    label: "experience.tabs.share.label",
    eyebrowKey: "experience.tabs.share.eyebrow",
    headingKey: "experience.tabs.share.heading",
    descriptionKey: "experience.tabs.share.description",
    ctaKey: "experience.tabs.share.cta",
    to: "/add_event",
    image: "/images/photo2.png",
    imageAltKey: "experience.tabs.share.imageAlt",
    featureKeys: [
      "experience.tabs.share.features.add",
      "experience.tabs.share.features.build",
      "experience.tabs.share.features.keep",
    ],
    visual: {
      kickerKey: "experience.tabs.share.visual.kicker",
      titleKey: "experience.tabs.share.visual.title",
      statKey: "experience.tabs.share.visual.stat",
      noteKey: "experience.tabs.share.visual.note",
    },
  },
  {
    id: "license",
    label: "experience.tabs.license.label",
    eyebrowKey: "experience.tabs.license.eyebrow",
    headingKey: "experience.tabs.license.heading",
    descriptionKey: "experience.tabs.license.description",
    ctaKey: "experience.tabs.license.cta",
    to: "/all_events",
    image: "/images/photo3.png",
    imageAltKey: "experience.tabs.license.imageAlt",
    featureKeys: [
      "experience.tabs.license.features.preview",
      "experience.tabs.license.features.use",
      "experience.tabs.license.features.keep",
    ],
    visual: {
      kickerKey: "experience.tabs.license.visual.kicker",
      titleKey: "experience.tabs.license.visual.title",
      statKey: "experience.tabs.license.visual.stat",
      noteKey: "experience.tabs.license.visual.note",
    },
  },
];

const activeTabId = ref(tabs[0].id);

const activeTab = computed(() => tabs.find((tab) => tab.id === activeTabId.value) || tabs[0]);

const localizedPath = (path) => `/${lang}${path}`;

const setActiveTab = (tabId) => {
  activeTabId.value = tabId;
};

const focusTab = (index) => {
  const tab = tabs[index];

  if (!tab) {
    return;
  }

  setActiveTab(tab.id);

  requestAnimationFrame(() => {
    document.getElementById(`scemory-tab-${tab.id}`)?.focus();
  });
};

const handleTabKeydown = (event, index) => {
  const lastIndex = tabs.length - 1;

  if (event.key === "ArrowRight" || event.key === "ArrowDown") {
    event.preventDefault();
    focusTab(index === lastIndex ? 0 : index + 1);
  }

  if (event.key === "ArrowLeft" || event.key === "ArrowUp") {
    event.preventDefault();
    focusTab(index === 0 ? lastIndex : index - 1);
  }

  if (event.key === "Home") {
    event.preventDefault();
    focusTab(0);
  }

  if (event.key === "End") {
    event.preventDefault();
    focusTab(lastIndex);
  }
};
</script>

<style scoped>
.scemory-experience-section {
  position: relative;
  overflow: hidden;
  padding: clamp(72px, 8vw, 112px) 0;
  background:
    radial-gradient(circle at 16% 18%, rgba(48, 168, 255, 0.13), transparent 28rem),
    radial-gradient(circle at 84% 80%, rgba(13, 77, 151, 0.10), transparent 30rem),
    linear-gradient(180deg, var(--scemory-surface), #FFFFFF 47%, var(--scemory-surface-soft));
}

.experience-inner {
  width: min(100% - 32px, 1240px);
  margin: 0 auto;
}

.experience-header {
  max-width: 760px;
  margin: 0 auto;
  text-align: center;
}

.experience-kicker,
.copy-kicker {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  border: 1px solid var(--scemory-border);
  border-radius: 999px;
  background: rgba(221, 236, 249, 0.78);
  color: var(--scemory-primary);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  line-height: 1;
}

.experience-kicker {
  padding: 0.72rem 1rem;
}

.copy-kicker {
  padding: 0.66rem 0.95rem;
}

.experience-header h2 {
  margin: 1.1rem 0 0;
  color: var(--scemory-heading);
  font-size: clamp(2rem, 4.2vw, 4rem);
  font-weight: 850;
  letter-spacing: 0;
  line-height: 1.08;
}

.experience-header p {
  margin: 1rem auto 0;
  max-width: 650px;
  color: var(--scemory-text);
  font-size: clamp(1rem, 1.4vw, 1.15rem);
  line-height: 1.85;
}

.experience-tabs {
  display: flex;
  justify-content: center;
  gap: 0.75rem;
  margin: clamp(2rem, 4vw, 3rem) auto clamp(2.25rem, 4vw, 3.4rem);
}

.experience-tab {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.7rem;
  min-height: 54px;
  border: 1px solid var(--scemory-border-soft);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.78);
  color: var(--scemory-text);
  padding: 0.45rem 1.1rem;
  box-shadow: var(--scemory-shadow-sm);
  font-weight: 800;
  transition: var(--scemory-transition);
  white-space: nowrap;
}

.experience-tab:hover,
.experience-tab:focus-visible {
  border-color: var(--scemory-border);
  background: var(--scemory-hover);
  color: var(--scemory-primary);
  outline: none;
  transform: translateY(-1px);
}

.experience-tab.is-active {
  border-color: rgba(22, 119, 255, 0.28);
  background: linear-gradient(135deg, var(--scemory-primary), var(--scemory-blue));
  color: #FFFFFF;
  box-shadow: 0 18px 38px rgba(13, 77, 151, 0.18);
}

.tab-number {
  display: inline-grid;
  width: 2.25rem;
  height: 2.25rem;
  place-items: center;
  border-radius: 999px;
  background: var(--scemory-active);
  color: var(--scemory-primary);
  font-size: 0.78rem;
}

.experience-tab.is-active .tab-number {
  background: rgba(255, 255, 255, 0.18);
  color: #FFFFFF;
}

.tab-label {
  font-size: 0.95rem;
}

.experience-body {
  display: grid;
  grid-template-columns: minmax(0, 1.14fr) minmax(0, 0.86fr);
  align-items: center;
  gap: clamp(2rem, 5vw, 4.25rem);
}

.experience-media {
  position: relative;
  min-height: clamp(360px, 42vw, 540px);
  overflow: hidden;
  border: 1px solid var(--scemory-border-soft);
  border-radius: 32px;
  background: linear-gradient(145deg, #FFFFFF, var(--scemory-surface));
  box-shadow: var(--scemory-shadow-strong);
  isolation: isolate;
}

.experience-media img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.28s ease, filter 0.28s ease;
}

.experience-media:hover img {
  transform: scale(1.025);
}

.is-explore .experience-media img,
.is-license .experience-media img {
  object-position: center;
  filter: saturate(1.03) contrast(1.02);
}

.is-share .experience-media img {
  object-position: center 45%;
  filter: saturate(0.98) contrast(1.02);
}

.media-sheen {
  position: absolute;
  inset: 0;
  z-index: 1;
  background:
    linear-gradient(90deg, rgba(6, 20, 42, 0.16), transparent 42%, rgba(255, 255, 255, 0.18)),
    linear-gradient(180deg, transparent 48%, rgba(6, 20, 42, 0.40));
  pointer-events: none;
}

.media-card {
  position: absolute;
  z-index: 2;
  width: min(270px, calc(100% - 3rem));
  border: 1px solid rgba(255, 255, 255, 0.58);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.84);
  padding: 1rem;
  box-shadow: 0 18px 36px rgba(2, 8, 23, 0.16);
  backdrop-filter: blur(16px);
}

.media-card span {
  display: block;
  color: var(--scemory-primary);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.media-card strong {
  display: block;
  margin-top: 0.35rem;
  color: var(--scemory-heading);
  font-size: 1rem;
  line-height: 1.45;
}

.media-card-primary {
  top: 1.4rem;
  inset-inline-start: 1.4rem;
}

.media-card-secondary {
  right: 1.4rem;
  bottom: 1.4rem;
}

.media-path {
  position: absolute;
  z-index: 2;
  inset-inline-start: 12%;
  bottom: 23%;
  display: flex;
  align-items: center;
  gap: clamp(1.8rem, 5vw, 4rem);
}

.media-path::before {
  position: absolute;
  inset-inline: 1rem;
  top: 50%;
  height: 1px;
  background: linear-gradient(90deg, rgba(255, 255, 255, 0.20), rgba(48, 168, 255, 0.82), rgba(255, 255, 255, 0.24));
  content: "";
  transform: translateY(-50%);
}

.media-path span {
  position: relative;
  width: 13px;
  height: 13px;
  border: 2px solid rgba(255, 255, 255, 0.86);
  border-radius: 999px;
  background: var(--scemory-light-blue);
  box-shadow: 0 0 0 8px rgba(48, 168, 255, 0.16);
}

.is-share .media-path span:nth-child(2),
.is-license .media-path span:nth-child(3) {
  background: #FFFFFF;
  box-shadow: 0 0 0 8px rgba(255, 255, 255, 0.18);
}

.experience-copy {
  min-width: 0;
}

.experience-copy h3 {
  margin: 1.15rem 0 0;
  color: var(--scemory-heading);
  font-size: clamp(2rem, 3.4vw, 3.25rem);
  font-weight: 850;
  letter-spacing: 0;
  line-height: 1.12;
}

.experience-copy p {
  margin: 1.1rem 0 0;
  color: var(--scemory-text);
  font-size: 1.04rem;
  line-height: 1.85;
}

.experience-features {
  display: grid;
  gap: 0.9rem;
  margin: 1.6rem 0 0;
  padding: 0;
  list-style: none;
}

.experience-features li {
  display: flex;
  align-items: center;
  gap: 0.78rem;
  color: var(--scemory-heading);
  font-size: 0.98rem;
  font-weight: 750;
  line-height: 1.55;
}

.feature-check {
  display: inline-grid;
  flex: 0 0 auto;
  width: 2.15rem;
  height: 2.15rem;
  place-items: center;
  border: 1px solid var(--scemory-border);
  border-radius: 999px;
  background: var(--scemory-active);
  color: var(--scemory-primary);
}

.experience-cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 52px;
  margin-top: 2rem;
  border: 1px solid rgba(22, 119, 255, 0.22);
  border-radius: 999px;
  background: linear-gradient(135deg, var(--scemory-primary), var(--scemory-blue));
  color: #FFFFFF;
  padding: 0.88rem 1.45rem;
  box-shadow: 0 14px 30px rgba(13, 77, 151, 0.18);
  font-size: 0.95rem;
  font-weight: 850;
  text-decoration: none;
  transition: var(--scemory-transition);
}

.experience-cta:hover,
.experience-cta:focus-visible {
  background: linear-gradient(135deg, var(--scemory-blue), var(--scemory-light-blue));
  box-shadow: var(--scemory-shadow-hover);
  color: #FFFFFF;
  outline: none;
  transform: translateY(-2px);
}

.experience-fade-enter-active,
.experience-fade-leave-active {
  transition: opacity 0.26s ease, transform 0.26s ease;
}

.experience-fade-enter-from,
.experience-fade-leave-to {
  opacity: 0;
  transform: translateY(10px);
}

@media (max-width: 900px) {
  .experience-body {
    grid-template-columns: 1fr;
  }

  .experience-copy {
    max-width: 680px;
  }
}

@media (max-width: 720px) {
  .experience-inner {
    width: min(100% - 24px, 1240px);
  }

  .experience-tabs {
    justify-content: flex-start;
    margin-inline: -12px;
    overflow-x: auto;
    padding: 0 12px 0.35rem;
    scrollbar-width: none;
  }

  .experience-tabs::-webkit-scrollbar {
    display: none;
  }

  .experience-tab {
    min-height: 50px;
    padding-inline: 0.8rem;
  }

  .experience-media {
    min-height: 330px;
    border-radius: 24px;
  }

  .media-card {
    width: min(240px, calc(100% - 2rem));
    border-radius: 16px;
    padding: 0.85rem;
  }

  .media-card-primary {
    top: 1rem;
    inset-inline-start: 1rem;
  }

  .media-card-secondary {
    right: 1rem;
    bottom: 1rem;
  }

  .media-path {
    inset-inline-start: 10%;
    bottom: 33%;
  }

  .experience-copy h3 {
    font-size: clamp(1.85rem, 11vw, 2.55rem);
  }
}

@media (max-width: 460px) {
  .experience-header {
    text-align: start;
  }

  .experience-media {
    min-height: 300px;
  }

  .media-card-secondary {
    display: none;
  }

  .experience-features li {
    align-items: flex-start;
  }
}
</style>
