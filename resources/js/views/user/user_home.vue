<template>
  <UserLayout>
    <div class="p-6">
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <!-- Card 1: Total Events -->
        <div class="bg-white rounded-xl shadow-sm p-6 text-center border border-gray-100">
          <div
            class="mx-auto w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center mb-4"
          >
            <svg
              class="w-8 h-8 text-blue-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
              />
            </svg>
          </div>
          <h4 class="text-3xl font-bold text-gray-800">{{ eventsCount }}</h4>
          <p class="text-gray-500 mt-1">إجمالي الرحلات</p>
        </div>

        <!-- Card 2: Cities Explored -->
        <div class="bg-white rounded-xl shadow-sm p-6 text-center border border-gray-100">
          <div
            class="mx-auto w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mb-4"
          >
            <svg
              class="w-8 h-8 text-green-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
              />
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
              />
            </svg>
          </div>
          <h4 class="text-3xl font-bold text-gray-800">{{ uniqueCitiesCount }}</h4>
          <p class="text-gray-500 mt-1">عدد المدن</p>
        </div>

        <!-- Card 3: Total Images -->
        <div class="bg-white rounded-xl shadow-sm p-6 text-center border border-gray-100">
          <div
            class="mx-auto w-14 h-14 bg-purple-100 rounded-full flex items-center justify-center mb-4"
          >
            <svg
              class="w-8 h-8 text-purple-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
              />
            </svg>
          </div>
          <h4 class="text-3xl font-bold text-gray-800">{{ totalImages }}</h4>
          <p class="text-gray-500 mt-1">إجمالي الصور في الاحداث</p>
        </div>
      </div>

      <!-- Recent Events -->
      <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-6 flex-wrap gap-4">
          <h2 class="text-2xl font-bold text-gray-800">الرحلات الأخيرة</h2>
          <router-link
            to="/owner/create"
            class="px-5 py-2.5 text-decoration-none bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium"
          >
            إضافة رحلة جديدة
          </router-link>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="event in events"
            :key="event.id"
            class="group relative rounded-xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 bg-white"
          >
            <!-- الصورة – استخدام first_image.url مع /storage/ -->
            <img
              :src="
                event.first_image
                  ? `/storage/${event.first_image.url}`
                  : '/images/default-event.jpg'
              "
              alt="صورة الرحلة"
              class="w-full h-56 object-cover transition-transform duration-500 group-hover:scale-105"
            />

            <!-- Overlay gradient -->
            <div
              class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent"
            ></div>

            <!-- المحتوى السفلي -->
            <div class="absolute bottom-0 left-0 right-0 p-5 text-white">
              <h3 class="text-lg font-semibold line-clamp-2 mb-1">{{ event.title }}</h3>
              <p class="text-sm opacity-90">
                {{ event.city?.name || "غير محدد" }} •
                {{
                  new Date(event.start_date).toLocaleDateString("ar-EG", {
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                  })
                }}
              </p>

              <!-- زرار عرض التفاصيل -->
              <div class="mt-4">
                <router-link
                  :to="`/owner/${event.slug}`"
                  class="inline-flex items-center text-decoration-none px-5 py-2.5 bg-white/95 hover:bg-white text-blue-900 font-medium rounded-lg text-sm transition-all backdrop-blur-sm shadow-md hover:shadow-lg active:scale-95 gap-2"
                >
                  <span>عرض التفاصيل</span>
                  <svg
                    class="w-4 h-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 5l7 7-7 7"
                    />
                  </svg>
                </router-link>
              </div>
            </div>
          </div>

          <!-- لا توجد رحلات -->
          <div
            v-if="events.length === 0"
            class="col-span-full text-center py-20 text-gray-500 text-lg border-2 border-dashed border-gray-300 rounded-xl"
          >
            <p class="mb-6">لم تقم بإضافة أي رحلات بعد..</p>
            <router-link
              to="/owner/create"
              class="inline-block px-8 py-3.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-md hover:shadow-lg"
            >
              ابدأ بإضافة رحلتك الأولى
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </UserLayout>
</template>

<script setup>
import UserLayout from "../../layouts/user/UserLayout.vue";
import { ref, computed, onMounted } from "vue";
import { UserDashboardService } from "../../services/UserDashboardService";

const events = ref([]);
const eventsCount = ref(0);
const totalImages = ref(0);

const uniqueCitiesCount = computed(() => {
  if (!events.value.length) return 0;
  const cities = new Set(events.value.map((e) => e.city_id || e.city?.name));
  return cities.size;
});

  onMounted(async () => {
    try {
    const response = await UserDashboardService.getMyEvents();
    if (response.data?.status === "success") {
      events.value = response.data.data.events || [];
      eventsCount.value = response.data.data.count || events.value.length;
      totalImages.value = response.data.data.totalImages || 0;
    }
  } catch (error) {
    console.error("خطأ في جلب الرحلات:", error);
  }
});
</script>
