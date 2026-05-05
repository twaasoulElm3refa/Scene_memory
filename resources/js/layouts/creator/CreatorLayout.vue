<template>
  <div class="creator-layout min-h-screen bg-[radial-gradient(circle_at_top,_#f8fbff_0%,_#eef4ff_42%,_#f8fafc_100%)] text-slate-900">
    <div class="flex min-h-screen w-full">
      <CreatorSidebar :is-open="isSidebarOpen" @close="closeSidebar" />

      <button
        v-if="isSidebarOpen"
        type="button"
        class="fixed inset-0 z-30 bg-slate-900/30 backdrop-blur-[1px] md:hidden"
        aria-label="Close sidebar"
        @click="closeSidebar"
      />

      <div class="relative z-10 flex min-h-screen min-w-0 flex-1 flex-col">
        <!-- <header class="sticky top-0 z-20 border-b border-slate-200/90 bg-white/75 px-4 py-3 backdrop-blur md:px-6">
          <div class="flex items-center justify-between gap-3">
            <button
              type="button"
              class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 md:hidden"
              aria-label="Open sidebar"
              @click="openSidebar"
            >
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>

            <p class="text-sm font-medium text-slate-600">Creator Dashboard</p>
            <span class="hidden text-xs text-slate-500 md:inline">Manage your events and wallet</span>
          </div>
        </header> -->

        <main class="flex-1 px-4 py-5 md:px-6 md:py-6">
          <div class="w-full">
            <slot>
              <RouterView />
            </slot>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";
import { useRoute } from "vue-router";
import CreatorSidebar from "@/layouts/creator/CreatorSidebar.vue";

const route = useRoute();
const isSidebarOpen = ref(false);

const openSidebar = () => {
  isSidebarOpen.value = true;
};

const closeSidebar = () => {
  isSidebarOpen.value = false;
};

watch(
  () => route.fullPath,
  () => {
    isSidebarOpen.value = false;
  }
);
</script>
