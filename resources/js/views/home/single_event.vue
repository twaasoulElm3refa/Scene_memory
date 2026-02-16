<template>
  <div class="min-h-screen bg-gray-50">
    <div v-if="loading" class="text-center py-40">
      <div class="animate-spin rounded-full h-20 w-20 border-t-4 border-blue-600 mx-auto mb-8"></div>
      <p class="text-gray-700 text-2xl font-medium">جاري تحميل الحدث...</p>
    </div>

    <div v-else-if="!event" class="text-center py-32 text-gray-600 text-2xl">
      الحدث غير موجود أو تم حذفه.
    </div>

    <div v-else>
      <div class="relative">
        <component
          :is="heroMediaComponent"
          v-if="heroMedia"
          :src="heroMedia.url"
          :alt="event.title"
          class="w-full h-[300px] md:h-[400px] lg:h-[500px] object-cover"
          controls
          autoplay
          muted
          loop
          playsinline
        />
        <img
          v-else
          src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&q=80&w=2400"
          :alt="event.title"
          class="w-full h-[300px] md:h-[400px] lg:h-[500px] object-cover"
        />

        <!-- Tags -->
        <div class="absolute top-6 left-6 md:left-10 flex flex-wrap gap-3">
          <span
            class="bg-blue-100/90 backdrop-blur-sm text-blue-900 px-5 py-2 rounded-full text-base font-bold shadow-lg uppercase tracking-wider"
          >
            {{ event.city?.name || 'غير محدد' }}
          </span>
          <span
            v-if="event.categorey?.name"
            class="bg-green-100/90 backdrop-blur-sm text-green-900 px-5 py-2 rounded-full text-base font-bold shadow-lg uppercase tracking-wider"
          >
            {{ event.categorey.name }}
          </span>
        </div>
      </div>

      <!-- Content -->
      <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 -mt-16 relative z-10">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-0">
            <!-- Main Content -->
            <div class="lg:col-span-2 p-8 md:p-12 lg:p-16">
              <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 mb-6 leading-tight">
                {{ event.title }}
              </h1>

              <p class="text-lg md:text-xl text-gray-700 mb-10 leading-relaxed">
                {{ event.description }}
              </p>

              <!-- Media Gallery -->
              <div v-if="hasMedia" class="mb-12">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6 border-b border-gray-200 pb-4">
                  وسائط الحدث
                </h2>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                  <div
                    v-for="(media, index) in event.images"
                    :key="media.id || index"
                    class="aspect-[4/3] overflow-hidden rounded-xl shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                    @click="openLightbox(index)"
                  >
                    <!-- صورة -->
                    <img
                      v-if="!media.video"
                      :src="media.url"
                      :alt="`${event.title} - صورة ${index + 1}`"
                      class="w-full h-full object-cover"
                    />

                    <!-- فيديو -->
                    <div v-else class="relative w-full h-full bg-black">
                      <video
                        :src="media.video"
                        class="w-full h-full object-cover"
                        muted
                        loop
                        playsinline
                      ></video>
                      <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-white text-5xl opacity-70">▶</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- نبذة عن الحدث -->
              <div class="mb-12">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6 border-b border-gray-200 pb-4">
                  نبذة عن الحدث
                </h2>
                <p class="text-gray-700 mb-8 text-lg leading-relaxed">
                  {{ event.description }}
                </p>
              </div>
            </div>

            <!-- Sidebar -->
            <div class="bg-gray-50/70 p-8 md:p-12 lg:p-16 border-t lg:border-t-0 lg:border-l border-gray-200 lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto">
              <div class="space-y-10">
                <div>
                  <h3 class="text-2xl font-bold text-gray-900 mb-6">معلومات الحدث</h3>

                  <div class="space-y-3 text-gray-800">
                    <div class="flex items-center gap-2">
                      <span class="text-3xl">📅</span>
                      <div>
                        <p class="font-semibold text-base">من</p>
                        <p class="text-lg">{{ formatDate(event.start_date) }}</p>
                      </div>
                    </div>

                    <div class="flex items-center gap-2">
                      <span class="text-3xl">📅</span>
                      <div>
                        <p class="font-semibold text-base">إلى</p>
                        <p class="text-lg">{{ formatDate(event.end_date) }}</p>
                      </div>
                    </div>

                    <div class="flex items-center gap-2">
                      <span class="text-3xl">🕒</span>
                      <div>
                        <p class="font-semibold text-base">الوقت</p>
                        <p class="text-lg">09:00 ص – 06:00 م</p>
                      </div>
                    </div>

                    <div class="flex items-center gap-2">
                      <span class="text-3xl">📍</span>
                      <div>
                        <p class="font-semibold text-base">المكان</p>
                        <p class="text-lg">{{ event.city?.name || 'غير محدد' }}</p>
                      </div>
                    </div>
                    <div v-if="event.user?.name" class="flex items-center gap-2">
                      <span class="text-3xl">👤</span>
                      <div>
                        <p class="font-semibold text-base">منظم الحدث</p>
                        <p class="text-lg">{{ event.user.name }}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="pt-8 border-t border-gray-200 space-y-4">
                  <button
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold text-lg py-4 px-8 rounded-xl transition shadow-lg"
                  >
                    ♥ أضف إلى المفضلة
                  </button>
                  <button
                    class="w-full border-2 border-gray-300 hover:bg-gray-100 hover:border-gray-400 font-semibold text-lg py-4 px-8 rounded-xl transition"
                  >
                    + أضف إلى التقويم
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lightbox for images & videos -->
      <div
        v-if="lightboxOpen"
        class="fixed inset-0 bg-black/95 z-50 flex items-center justify-center"
        @click="lightboxOpen = false"
      >
        <div class="relative max-w-[95vw] max-h-[95vh]">
          <!-- Image in lightbox -->
          <img
            v-if="!currentMedia?.video"
            :src="currentMedia?.url"
            class="max-w-full max-h-[90vh] object-contain"
            @click.stop
          />

          <!-- Video in lightbox -->
          <video
            v-else
            :src="currentMedia?.video"
            class="max-w-full max-h-[90vh]"
            controls
            autoplay
            @click.stop
          ></video>

          <button
            class="absolute top-4 right-4 text-white text-5xl font-bold drop-shadow-lg"
            @click="lightboxOpen = false"
          >
            ×
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import { EventService } from "@/services/singleEventService";

const route = useRoute();
const slug = route.params.slug;

const event = ref(null);
const loading = ref(true);
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);

const hasMedia = computed(() => event.value?.images?.length > 0);

const heroMedia = computed(() => {
  return event.value?.images?.[0] || null;
});

const heroMediaComponent = computed(() => {
  if (heroMedia.value?.video) return 'video';
  return 'img';
});

const currentMedia = computed(() => {
  return event.value?.images?.[lightboxIndex.value] || null;
});

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  try {
    return new Date(dateStr).toLocaleDateString("ar-EG", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  } catch {
    return "—";
  }
};

const openLightbox = (index) => {
  lightboxIndex.value = index;
  lightboxOpen.value = true;
};

onMounted(async () => {
  if (!slug) {
    loading.value = false;
    return;
  }

  loading.value = true;
  try {
    event.value = await EventService.getSingleEvent(slug);
  } catch (err) {
    console.error("خطأ في جلب الحدث:", err);
    event.value = null;
  } finally {
    loading.value = false;
  }
});
</script>