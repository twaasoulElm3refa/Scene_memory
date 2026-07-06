<template>
  <section class="relative overflow-hidden bg-[#F8FAFC] py-20">
    <div class="relative mx-auto grid max-w-7xl grid-cols-1 gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
      <div class="relative min-h-[420px] overflow-hidden rounded-[34px] border border-[#CFE0FF] shadow-2xl">
        <img
          src="/images/about.png"
          alt="Scemory documentation platform"
          class="absolute inset-0 h-full w-full object-cover object-center"
        />
        <div class="absolute inset-0 bg-gradient-to-tr from-[#0D4D97]/80 via-[#0D4D97]/35 to-transparent"></div>
      </div>

      <div class="flex items-center">
        <div class="rounded-[34px] border border-[#CFE0FF] bg-white/75 p-8 shadow-2xl backdrop-blur-xl md:p-10">
          <span class="inline-flex rounded-full bg-[#EAF2FF] px-4 py-2 text-sm font-bold text-[#0D4D97]">
            Trusted Event Memory
          </span>

          <h2 class="mt-5 text-3xl font-bold tracking-tight text-[#0D4D97] md:text-5xl">
            About Scemory
          </h2>

          <p class="mt-5 text-base leading-8 text-[#475569]">
            Scemory is built for journalists, media teams, and eyewitness contributors who need a trusted place to document events as they happen. The platform connects uploaded media, event metadata, locations, and timelines into one searchable visual memory.
          </p>

          <div class="mt-7 space-y-4">
            <div v-for="item in features" :key="item" class="flex items-center gap-3">
              <span class="grid h-9 w-9 place-items-center rounded-full bg-[#EAF2FF] text-[#0D4D97]">
                <i class="bi bi-check2"></i>
              </span>
              <span class="text-sm font-semibold text-[#0F172A]">{{ item }}</span>
            </div>
          </div>

          <button
            type="button"
            class="mt-8 rounded bg-[#0D4D97] px-7 py-3 mt-2 text-sm font-bold text-white shadow-lg transition hover:bg-[#1E5FB8]"
          >
            Learn more
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const features = [
  "Upload photos, videos, and field data",
  "Explore events through an interactive map",
  "Preserve verified stories in a digital archive",
];
</script>
