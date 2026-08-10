<template>
  <div class="scemory-page who-page min-h-screen bg-gradient-to-b from-gray-50 to-white">
    <!-- Hero Section -->
    <section
      class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-blue-800 to-cyan-700 text-white"
    >
      <div class="absolute inset-0 bg-black/20"></div>
      <div class="relative max-w-7xl mx-auto px-4 py-24 md:py-32">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
          <div class="text-center lg:text-right">
            <div class="inline-block mb-6">
              <div
                class="w-20 h-20 bg-gradient-to-br from-white/20 to-white/10 rounded-2xl backdrop-blur-sm flex items-center justify-center mx-auto lg:mx-0"
              >
                <span class="text-xl font-extrabold tracking-normal">SC</span>
              </div>
            </div>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 leading-tight">
              {{ $t("hero.title_main") }}
              <span
                class="block text-transparent bg-clip-text bg-gradient-to-r from-cyan-300 to-purple-300"
              >
                {{ $t("hero.title_highlight") }}
              </span>
            </h1>
            <p class="text-xl opacity-90 mb-8 max-w-2xl mx-auto lg:mx-0">
              {{ $t("hero.description") }}
            </p>
            <div class="flex flex-wrap gap-4 justify-center lg:justify-start">
              <button
                class="px-8 py-3 bg-white text-purple-900 font-bold rounded-full hover:bg-gray-100 transition transform hover:-translate-y-1"
              >
                {{ $t("hero.cta_start") }}
              </button>
              <button
                class="px-8 py-3 border-2 border-white/30 text-white rounded-full hover:bg-white/10 transition"
              >
                {{ $t("hero.cta_watch") }}
              </button>
            </div>
          </div>

          <div class="relative">
            <div class="relative z-10">
              <div
                class="bg-gradient-to-br from-white/10 to-white/5 rounded-3xl p-2 backdrop-blur-lg border border-white/20"
              >
                <div class="grid grid-cols-2 gap-4">
                  <div
                    class="bg-gradient-to-br from-cyan-500/20 to-blue-500/20 rounded-2xl p-6 backdrop-blur-sm"
                  >
                    <span class="text-xs font-extrabold mb-4 block text-[#0D4D97]">01</span>
                    <h3 class="font-bold text-lg mb-2">
                      {{ $t("hero.feature_organized") }}
                    </h3>
                    <p class="text-sm opacity-80">
                      {{ $t("hero.feature_organized_desc") }}
                    </p>
                  </div>
                  <div
                    class="bg-gradient-to-br from-purple-500/20 to-pink-500/20 rounded-2xl p-6 backdrop-blur-sm mt-8"
                  >
                    <span class="text-xs font-extrabold mb-4 block text-[#0D4D97]">02</span>
                    <h3 class="font-bold text-lg mb-2">
                      {{ $t("hero.feature_private") }}
                    </h3>
                    <p class="text-sm opacity-80">
                      {{ $t("hero.feature_private_desc") }}
                    </p>
                  </div>
                  <div
                    class="bg-gradient-to-br from-green-500/20 to-emerald-500/20 rounded-2xl p-6 backdrop-blur-sm"
                  >
                    <span class="text-xs font-extrabold mb-4 block text-[#0D4D97]">03</span>
                    <h3 class="font-bold text-lg mb-2">
                      {{ $t("hero.feature_reminders") }}
                    </h3>
                    <p class="text-sm opacity-80">
                      {{ $t("hero.feature_reminders_desc") }}
                    </p>
                  </div>
                  <div
                    class="bg-gradient-to-br from-orange-500/20 to-red-500/20 rounded-2xl p-6 backdrop-blur-sm mt-8"
                  >
                    <span class="text-xs font-extrabold mb-4 block text-[#0D4D97]">04</span>
                    <h3 class="font-bold text-lg mb-2">
                      {{ $t("hero.feature_evolving") }}
                    </h3>
                    <p class="text-sm opacity-80">
                      {{ $t("hero.feature_evolving_desc") }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div
              class="absolute -top-6 -right-6 w-24 h-24 bg-gradient-to-r from-cyan-400 to-blue-500 rounded-full blur-xl opacity-30"
            ></div>
            <div
              class="absolute -bottom-6 -left-6 w-32 h-32 bg-gradient-to-r from-purple-400 to-pink-500 rounded-full blur-xl opacity-30"
            ></div>
          </div>
        </div>
      </div>

      <!-- Wave divider -->
      <div class="absolute bottom-0 left-0 right-0">
        <svg
          class="w-full h-auto"
          viewBox="0 0 1440 120"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            d="M0 120L60 100C120 80 240 40 360 30C480 20 600 40 720 50C840 60 960 60 1080 45C1200 30 1320 0 1380 0L1440 0V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z"
            fill="white"
          />
        </svg>
      </div>
    </section>

    <!-- Our Story -->
    <section class="py-20 px-4">
      <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
          <span
            class="inline-block px-4 py-2 bg-gradient-to-r from-purple-100 to-blue-100 text-purple-700 rounded-full font-bold mb-4"
          >
            {{ $t("our_story.badge") }}
          </span>
          <h2 class="text-4xl font-bold text-gray-800 mb-6">
            {{ $t("our_story.title") }}
          </h2>
          <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            {{ $t("our_story.intro") }}
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div class="relative">
            <div class="bg-white rounded-2xl p-8 shadow-xl border border-gray-100 h-full">
              <div class="text-sm font-extrabold text-[#0D4D97] mb-6">01</div>
              <h3 class="text-2xl font-bold text-gray-800 mb-4">
                {{ $t("our_story.stage1_title") }}
              </h3>
              <p class="text-gray-600 mb-6">
                {{ $t("our_story.stage1_desc") }}
              </p>
              <div class="flex items-center">
                <div
                  class="w-10 h-10 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center text-white font-bold"
                >
                  ١
                </div>
                <div
                  class="h-px flex-1 bg-gradient-to-r from-purple-100 to-blue-100 mx-4"
                ></div>
              </div>
            </div>
          </div>

          <div class="relative">
            <div class="bg-white rounded-2xl p-8 shadow-xl border border-gray-100 h-full">
              <div class="text-sm font-extrabold text-[#0D4D97] mb-6">02</div>
              <h3 class="text-2xl font-bold text-gray-800 mb-4">
                {{ $t("our_story.stage2_title") }}
              </h3>
              <p class="text-gray-600 mb-6">
                {{ $t("our_story.stage2_desc") }}
              </p>
              <div class="flex items-center">
                <div
                  class="h-px flex-1 bg-gradient-to-r from-blue-100 to-green-100 mx-4"
                ></div>
                <div
                  class="w-10 h-10 bg-gradient-to-r from-blue-500 to-green-500 rounded-full flex items-center justify-center text-white font-bold"
                >
                  ٢
                </div>
                <div
                  class="h-px flex-1 bg-gradient-to-r from-green-100 to-blue-100 mx-4"
                ></div>
              </div>
            </div>
          </div>

          <div class="relative">
            <div class="bg-white rounded-2xl p-8 shadow-xl border border-gray-100 h-full">
              <div class="text-sm font-extrabold text-[#0D4D97] mb-6">03</div>
              <h3 class="text-2xl font-bold text-gray-800 mb-4">
                {{ $t("our_story.stage3_title") }}
              </h3>
              <p class="text-gray-600 mb-6">
                {{ $t("our_story.stage3_desc") }}
              </p>
              <div class="flex items-center">
                <div
                  class="h-px flex-1 bg-gradient-to-r from-green-100 to-purple-100 mx-4"
                ></div>
                <div
                  class="w-10 h-10 bg-gradient-to-r from-green-500 to-purple-500 rounded-full flex items-center justify-center text-white font-bold"
                >
                  ٣
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Mission & Vision -->
    <section class="py-20 bg-gradient-to-b from-white to-gray-50 px-4">
      <div class="max-w-6xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
          <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-3xl p-8 md:p-12">
            <div
              class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-700 rounded-full font-bold mb-6"
            >
              <span class="ml-2 text-xs font-extrabold">M</span>
              <span>{{ $t("mission_vision.mission_badge") }}</span>
            </div>
            <h2 class="text-3xl font-bold text-gray-800 mb-6">
              {{ $t("mission_vision.mission_title") }}
            </h2>
            <p class="text-gray-700 text-lg mb-8">
              {{ $t("mission_vision.mission_text") }}
            </p>

            <div class="space-y-6">
              <div class="flex items-start">
                <div
                  class="w-12 h-12 bg-white rounded-xl flex items-center justify-center shadow-sm ml-4 flex-shrink-0"
                >
                  <span class="text-sm font-extrabold text-[#0D4D97]">01</span>
                </div>
                <div>
                  <h4 class="font-bold text-gray-800 mb-2">
                    {{ $t("mission_vision.value_people_first") }}
                  </h4>
                  <p class="text-gray-600">
                    {{ $t("mission_vision.value_people_first_desc") }}
                  </p>
                </div>
              </div>

              <div class="flex items-start">
                <div
                  class="w-12 h-12 bg-white rounded-xl flex items-center justify-center shadow-sm ml-4 flex-shrink-0"
                >
                  <span class="text-sm font-extrabold text-[#0D4D97]">02</span>
                </div>
                <div>
                  <h4 class="font-bold text-gray-800 mb-2">
                    {{ $t("mission_vision.value_privacy") }}
                  </h4>
                  <p class="text-gray-600">
                    {{ $t("mission_vision.value_privacy_desc") }}
                  </p>
                </div>
              </div>

              <div class="flex items-start">
                <div
                  class="w-12 h-12 bg-white rounded-xl flex items-center justify-center shadow-sm ml-4 flex-shrink-0"
                >
                  <span class="text-sm font-extrabold text-[#0D4D97]">03</span>
                </div>
                <div>
                  <h4 class="font-bold text-gray-800 mb-2">
                    {{ $t("mission_vision.value_growth") }}
                  </h4>
                  <p class="text-gray-600">
                    {{ $t("mission_vision.value_growth_desc") }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div
            class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-3xl p-8 md:p-12"
          >
            <div
              class="inline-flex items-center px-4 py-2 bg-purple-100 text-purple-700 rounded-full font-bold mb-6"
            >
              <span class="ml-2 text-xs font-extrabold">V</span>
              <span>{{ $t("mission_vision.vision_badge") }}</span>
            </div>
            <h2 class="text-3xl font-bold text-gray-800 mb-6">
              {{ $t("mission_vision.vision_title") }}
            </h2>
            <p class="text-gray-700 text-lg mb-8">
              {{ $t("mission_vision.vision_text") }}
            </p>

            <div class="relative">
              <div
                class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/50"
              >
                <h4 class="font-bold text-gray-800 mb-4 text-xl">
                  {{ $t("mission_vision.goals_2025") }}
                </h4>
                <div class="space-y-4">
                  <div>
                    <div class="flex justify-between mb-2">
                      <span class="font-medium text-gray-700">{{
                        $t("mission_vision.goal_users")
                      }}</span>
                      <span class="font-bold text-purple-600">65%</span>
                    </div>
                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                      <div
                        class="h-full bg-gradient-to-r from-purple-500 to-pink-500 rounded-full"
                        style="width: 65%"
                      ></div>
                    </div>
                  </div>

                  <div>
                    <div class="flex justify-between mb-2">
                      <span class="font-medium text-gray-700">{{
                        $t("mission_vision.goal_languages")
                      }}</span>
                      <span class="font-bold text-blue-600">40%</span>
                    </div>
                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                      <div
                        class="h-full bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full"
                        style="width: 40%"
                      ></div>
                    </div>
                  </div>

                  <div>
                    <div class="flex justify-between mb-2">
                      <span class="font-medium text-gray-700">{{
                        $t("mission_vision.goal_ai")
                      }}</span>
                      <span class="font-bold text-green-600">80%</span>
                    </div>
                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                      <div
                        class="h-full bg-gradient-to-r from-green-500 to-emerald-500 rounded-full"
                        style="width: 80%"
                      ></div>
                    </div>
                  </div>
                </div>
              </div>

              <div
                class="absolute -top-4 -right-4 w-20 h-20 bg-gradient-to-r from-pink-400 to-purple-500 rounded-full blur-xl opacity-30"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Our Team -->
    <section class="py-20 px-4">
      <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
          <span
            class="inline-block px-4 py-2 bg-gradient-to-r from-green-100 to-emerald-100 text-green-700 rounded-full font-bold mb-4"
          >
            {{ $t("team.badge") }}
          </span>
          <h2 class="text-4xl font-bold text-gray-800 mb-6">
            {{ $t("team.title") }}
          </h2>
          <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            {{ $t("team.description") }}
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
          <div v-for="member in teamMembers" :key="member.id" class="group">
            <div
              class="bg-white rounded-2xl p-6 shadow-lg border border-gray-100 hover:shadow-2xl transition-all duration-300 transform group-hover:-translate-y-2"
            >
              <div class="relative mb-6">
                <div
                  class="w-24 h-24 mx-auto rounded-full overflow-hidden border-4 border-white shadow-lg"
                >
                  <div
                    :class="member.color"
                    class="w-full h-full flex items-center justify-center"
                  >
                    <span class="text-xl font-extrabold text-[#0D4D97]">{{ member.initials }}</span>
                  </div>
                </div>
                <div
                  class="absolute -bottom-2 -right-2 w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white text-sm font-bold"
                >
                  {{ member.initials }}
                </div>
              </div>

              <h3 class="text-xl font-bold text-gray-800 text-center mb-2">
                {{ member.name }}
              </h3>
              <p class="text-gray-600 text-center mb-4">{{ member.role }}</p>
              <p class="text-gray-500 text-sm text-center mb-6">{{ member.bio }}</p>

              <div class="flex justify-center space-x-3 space-x-reverse">
                <a
                  href="#"
                  class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-blue-100 hover:text-blue-600 transition"
                >
                  <span class="text-xs font-bold">IN</span>
                </a>
                <a
                  href="#"
                  class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-green-100 hover:text-green-600 transition"
                >
                  <span class="text-xs font-bold">X</span>
                </a>
                <a
                  href="#"
                  class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-purple-100 hover:text-purple-600 transition"
                >
                  <span class="text-xs font-bold">GH</span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Values -->
    <section class="py-20 bg-gradient-to-b from-gray-50 to-white px-4">
      <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
          <span
            class="inline-block px-4 py-2 bg-gradient-to-r from-orange-100 to-red-100 text-orange-700 rounded-full font-bold mb-4"
          >
            {{ $t("values.badge") }}
          </span>
          <h2 class="text-4xl font-bold text-gray-800 mb-6">
            {{ $t("values.title") }}
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div
            v-for="value in values"
            :key="value.id"
            class="bg-white rounded-2xl p-6 shadow-lg border border-gray-100 hover:shadow-xl transition-all"
          >
            <div
              class="w-16 h-16 rounded-2xl flex items-center justify-center text-2xl mb-6 mx-auto"
              :class="value.bgColor"
            >
              {{ value.id.toString().padStart(2, "0") }}
            </div>
            <h3 class="text-xl font-bold text-gray-800 text-center mb-4">
              {{ value.title }}
            </h3>
            <p class="text-gray-600 text-center">{{ value.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats -->
    <section class="py-20 bg-gradient-to-r from-purple-900 to-blue-800 text-white px-4">
      <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
          <h2 class="text-4xl font-bold mb-6">
            {{ $t("stats.title") }}
          </h2>
          <p class="text-xl opacity-90 max-w-3xl mx-auto">
            {{ $t("stats.description") }}
          </p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
          <div v-for="stat in stats" :key="stat.id" class="text-center">
            <div class="text-5xl font-bold mb-4">{{ stat.value }}</div>
            <div class="text-lg opacity-90">{{ stat.label }}</div>
            <div
              class="h-1 w-16 bg-gradient-to-r from-cyan-400 to-purple-400 mx-auto mt-4 rounded-full"
            ></div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-20 px-4">
      <div class="max-w-4xl mx-auto">
        <div
          class="bg-gradient-to-r from-cyan-500 to-blue-600 rounded-3xl p-8 md:p-12 text-white text-center relative overflow-hidden"
        >
          <div class="absolute inset-0 opacity-10">
            <div class="absolute top-10 right-10 text-4xl font-extrabold">SC</div>
            <div class="absolute bottom-10 left-10 text-4xl font-extrabold">AI</div>
          </div>

          <div class="relative z-10">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">
              {{ $t("cta.title") }}
            </h2>
            <p class="text-xl opacity-90 mb-8 max-w-2xl mx-auto">
              {{ $t("cta.description") }}
            </p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
              <button
                class="px-8 py-3 bg-white text-blue-600 font-bold rounded-full hover:bg-gray-100 transition transform hover:scale-105"
              >
                {{ $t("cta.btn_signup") }}
              </button>
              <button
                class="px-8 py-3 border-2 border-white/30 text-white rounded-full hover:bg-white/10 transition"
              >
                {{ $t("cta.btn_features") }}
              </button>
            </div>

            <p class="mt-8 opacity-80">
              {{ $t("cta.trust") }}
            </p>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref } from "vue";

const teamMembers = [
  {
    id: 1,
    name: "أحمد خالد",
    role: "المؤسس والرئيس التنفيذي",
    bio: "مؤمن بقوة الذكريات وتأثيرها على حياتنا",
    emoji: "AK",
    initials: "AK",
    color: "bg-gradient-to-r from-blue-100 to-cyan-100",
  },
  {
    id: 2,
    name: "سارة محمد",
    role: "مديرة التصميم",
    bio: "تعمل على جعل تجربة حفظ الذكريات جميلة وسلسة",
    emoji: "SM",
    initials: "SM",
    color: "bg-gradient-to-r from-purple-100 to-pink-100",
  },
  {
    id: 3,
    name: "محمد علي",
    role: "رئيس المطورين",
    bio: "يضمن أن كل سطر كود يحمي خصوصيتك",
    emoji: "MA",
    initials: "MA",
    color: "bg-gradient-to-r from-green-100 to-emerald-100",
  },
  {
    id: 4,
    name: "فاطمة حسن",
    role: "مديرة خدمة العملاء",
    bio: "تساعدك في كل خطوة من رحلتك معنا",
    emoji: "FH",
    initials: "FH",
    color: "bg-gradient-to-r from-orange-100 to-red-100",
  },
];

const values = [
  {
    id: 1,
    title: "الخصوصية أولاً",
    description: "ذكرياتك تبقى خاصة بك، ونحميها بأفضل تقنيات التشفير",
    icon: "01",
    bgColor: "bg-gradient-to-r from-blue-100 to-cyan-100",
  },
  {
    id: 2,
    title: "البساطة",
    description: "نجعل حفظ الذكريات سهلاً وسلساً للجميع",
    icon: "02",
    bgColor: "bg-gradient-to-r from-purple-100 to-pink-100",
  },
  {
    id: 3,
    title: "الابتكار",
    description: "نطور باستمرار لنقدم لك أفضل تجربة ممكنة",
    icon: "03",
    bgColor: "bg-gradient-to-r from-green-100 to-emerald-100",
  },
  {
    id: 4,
    title: "المجتمع",
    description: "نبني منصة تربط الناس بذكرياتهم وأحبائهم",
    icon: "04",
    bgColor: "bg-gradient-to-r from-orange-100 to-red-100",
  },
];

const stats = [
  { id: 1, value: "50K+", label: "مستخدم سعيد" },
  { id: 2, value: "2M+", label: "ذاكرة محفوظة" },
  { id: 3, value: "99.9%", label: "وقت تشغيل" },
  { id: 4, value: "24/7", label: "دعم فني" },
];
</script>

<style scoped>
/* Custom animations */
@keyframes float {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

.group:hover .group-hover\\:animate-float {
  animation: float 2s ease-in-out infinite;
}

/* Smooth scrolling */
html {
  scroll-behavior: smooth;
}

/* Custom gradient borders */
.gradient-border {
  border: double 3px transparent;
  background-image: linear-gradient(white, white),
    linear-gradient(to right, #0D4D97, #30A8FF);
  background-origin: border-box;
  background-clip: padding-box, border-box;
}

.who-page {
  background:
    radial-gradient(circle at 85% 10%, rgba(48, 168, 255, 0.06), transparent 28rem),
    radial-gradient(circle at 10% 40%, rgba(13, 77, 151, 0.04), transparent 30rem),
    linear-gradient(180deg, var(--scemory-surface), var(--scemory-surface-soft) 48%, var(--scemory-surface));
}

.who-page section:first-child {
  background:
    radial-gradient(circle at 86% 20%, rgba(48, 168, 255, 0.10), transparent 22rem),
    linear-gradient(145deg, #FFFFFF, var(--scemory-surface) 55%, var(--scemory-surface-soft)) !important;
  color: var(--scemory-text) !important;
  border-bottom: 1px solid var(--scemory-border-soft);
}

.who-page h1,
.who-page h2,
.who-page h3 {
  letter-spacing: 0;
  color: var(--scemory-heading) !important;
}

.who-page section:first-child p,
.who-page section:first-child h3 {
  color: var(--scemory-text) !important;
  opacity: 1 !important;
}

.who-page section:first-child .text-white,
.who-page section:first-child .text-white\/30,
.who-page section:first-child .text-white\/10 {
  color: var(--scemory-text) !important;
}

.who-page section:first-child .bg-black\/20 {
  background: transparent !important;
}

.who-page section:first-child .bg-white\/10,
.who-page section:first-child .bg-white\/5,
.who-page section:first-child .bg-white\/20 {
  background: rgba(243, 247, 252, 0.86) !important;
  border-color: var(--scemory-border-soft) !important;
}

.who-page section:first-child path[fill="white"] {
  fill: var(--scemory-surface);
}

.who-page .bg-purple-100,
.who-page .from-purple-100,
.who-page .bg-green-100,
.who-page .from-green-100,
.who-page .bg-orange-100,
.who-page .from-orange-100 {
  background: var(--scemory-active) !important;
  --tw-gradient-from: var(--scemory-active) !important;
  --tw-gradient-to: var(--scemory-surface-soft) !important;
}

.who-page .text-purple-900,
.who-page .text-purple-700,
.who-page .text-purple-600,
.who-page .text-green-700,
.who-page .text-orange-700 {
  color: #0D4D97 !important;
}

.who-page .bg-white {
  border-color: var(--scemory-border-soft);
  background: linear-gradient(145deg, #FFFFFF, var(--scemory-surface)) !important;
  box-shadow: var(--scemory-shadow);
}

.who-page .from-purple-500,
.who-page .from-pink-400,
.who-page .from-cyan-500,
.who-page .from-orange-500,
.who-page .from-green-500,
.who-page .to-pink-500,
.who-page .to-purple-500,
.who-page .to-emerald-500,
.who-page .to-red-500 {
  --tw-gradient-from: var(--scemory-primary) !important;
  --tw-gradient-to: var(--scemory-blue) !important;
}

.who-page .blur-xl {
  opacity: 0.08 !important;
}

/* Responsive text sizes */
@media (max-width: 640px) {
  .text-6xl {
    font-size: 2.5rem;
  }

  .text-5xl {
    font-size: 2rem;
  }

  .text-4xl {
    font-size: 1.75rem;
  }
}

/* Smooth transitions */
* {
  transition: var(--scemory-transition);
}
</style>
