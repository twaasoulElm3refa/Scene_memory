<template>
  <section class="scemory-about-section home-about-section relative overflow-hidden py-20">
    <div class="relative mx-auto grid max-w-7xl grid-cols-1 gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
      <div class="about-media relative min-h-[420px] overflow-hidden rounded-[34px] border">
        <img
          src="/images/about.png"
          :alt="$t('homeAudit.about.imageAlt')"
          class="absolute inset-0 h-full w-full object-cover object-center"
        />
        <div class="absolute inset-0 bg-gradient-to-tr from-[#0D4D97]/60 via-[#0D4D97]/18 to-transparent"></div>
      </div>

      <div class="flex items-center">
        <div class="about-copy-panel rounded-[34px] border p-8 backdrop-blur-xl md:p-10">
          <span class="about-eyebrow inline-flex rounded-full px-4 py-2 text-sm font-bold">
            {{ $t('homeAudit.about.kicker') }}
          </span>

          <h2 class="mt-5 text-3xl font-bold tracking-tight md:text-5xl">
            {{ $t('homeAudit.about.title') }}
          </h2>

          <p class="mt-5 text-base leading-8 text-[#475569]">
            {{ $t('homeAudit.about.description') }}
          </p>

          <div class="mt-7 space-y-4">
            <div v-for="item in features" :key="item" class="flex items-center gap-3">
              <span class="about-check grid h-9 w-9 place-items-center rounded-full">
                <i class="bi bi-check2"></i>
              </span>
              <span class="text-sm font-semibold text-[#0F172A]">{{ $t(item) }}</span>
            </div>
          </div>

          <button
            type="button"
            class="about-cta mt-8 rounded px-7 py-3 mt-2 text-sm font-bold text-white transition"
          >
            {{ $t('common.learnMore') }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const features = [
  "homeAudit.about.features.upload",
  "homeAudit.about.features.explore",
  "homeAudit.about.features.preserve",
];
</script>

<style scoped>
.home-about-section {
  background:
    radial-gradient(circle at 12% 18%, rgba(48, 168, 255, 0.06), transparent 24rem),
    linear-gradient(180deg, var(--scemory-surface), #FFFFFF 46%, var(--scemory-surface));
}

.about-media {
  border-color: var(--scemory-border);
  box-shadow: var(--scemory-shadow);
}

.about-copy-panel {
  border-color: var(--scemory-border-soft);
  background: linear-gradient(145deg, rgba(255, 255, 255, 0.86), rgba(247, 250, 253, 0.94));
  box-shadow: var(--scemory-shadow);
}

.about-eyebrow,
.about-check {
  border: 1px solid var(--scemory-border);
  background: var(--scemory-active);
  color: var(--scemory-primary);
}

.home-about-section h2 {
  color: var(--scemory-heading);
}

.home-about-section p {
  color: var(--scemory-text);
}

.home-about-section .text-\[\#0F172A\] {
  color: var(--scemory-heading);
}

.about-cta {
  border: 1px solid rgba(22, 119, 255, 0.22);
  border-radius: 14px;
  background: linear-gradient(135deg, var(--scemory-primary), var(--scemory-blue));
  box-shadow: 0 8px 20px rgba(13, 77, 151, 0.16);
}

.about-cta:hover {
  transform: translateY(-1px);
  background: linear-gradient(135deg, var(--scemory-blue), var(--scemory-light-blue));
  box-shadow: var(--scemory-shadow-hover);
}

@media (max-width: 640px) {
  .about-media,
  .about-copy-panel {
    border-radius: 24px;
  }
}
</style>
