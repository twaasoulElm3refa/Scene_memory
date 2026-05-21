<template>
  <section v-if="licenceName === 'free'" class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-10">
    <div class="text-center mb-10">
      <div
        class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 rounded-full text-sm font-semibold mb-4 shadow-md"
      >
        <span class="text-lg animate-pulse">&#9889;</span>
        {{ $t("plans.chooseYourPlan") || "Choose your plan" }}
      </div>

      <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-3">
        {{ $t("plans.ourPlans") || "Our plans" }}
      </h2>

      <div class="w-20 h-1 bg-gradient-to-r from-blue-500 to-blue-600 mx-auto rounded-full mb-4"></div>

      <p class="text-gray-600 text-base max-w-xl mx-auto">
        {{ $t("plans.description") || "Start free or pick the plan that fits you best." }}
      </p>
    </div>

    <div v-if="loadingPlans" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div
        v-for="i in 4"
        :key="`plan-skeleton-${i}`"
        class="border border-gray-200 bg-white rounded-2xl overflow-hidden animate-pulse"
      >
        <div class="p-5 space-y-3">
          <div class="h-12 w-12 rounded-xl bg-gray-200 mx-auto"></div>
          <div class="h-5 w-2/3 mx-auto bg-gray-200 rounded"></div>
          <div class="h-8 w-1/2 mx-auto bg-gray-200 rounded"></div>
          <div class="space-y-2 pt-2">
            <div class="h-3 w-full bg-gray-200 rounded"></div>
            <div class="h-3 w-5/6 bg-gray-200 rounded"></div>
            <div class="h-3 w-4/6 bg-gray-200 rounded"></div>
          </div>
          <div class="h-10 w-full bg-gray-200 rounded-lg"></div>
        </div>
      </div>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div
        v-for="plan in plans"
        :key="plan.id"
        class="relative border border-gray-200 bg-white rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-2 hover:shadow-xl"
      >
        <div v-if="plan.name.toLowerCase() === 'professional' || plan.name.toLowerCase() === 'pro'" class="absolute top-0 right-0 z-20">
          <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white text-xs font-bold px-4 py-1 rounded-bl-2xl">
            {{ $t("plans.mostPopular") || "Most popular" }}
          </div>
        </div>

        <div class="p-5">
          <div class="text-center mb-5">
            <div
              class="inline-flex items-center justify-center w-16 h-16 rounded-xl mb-3"
              :class="{
                'bg-gray-100': plan.name === 'free',
                'bg-blue-100': plan.name === 'basic' || plan.name === 'professional',
                'bg-purple-100': plan.name === 'premium'
              }"
            >
              <span class="text-3xl" v-if="plan.name === 'free'">&#127919;</span>
              <span class="text-3xl" v-else-if="plan.name === 'basic'">&#128218;</span>
              <span class="text-3xl" v-else-if="plan.name === 'professional'">&#128142;</span>
              <span class="text-3xl" v-else>&#128640;</span>
            </div>

            <h3 class="text-xl font-bold text-gray-900">
              {{ plan.translation?.name || plan.name }}
            </h3>

            <div class="mt-3">
              <div class="flex items-baseline justify-center gap-1">
                <span class="text-3xl font-extrabold text-gray-900">{{ plan.price }}</span>
                <span class="text-gray-500 text-sm">USD</span>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                {{ $t("plans.perMonth") || "/ month" }}
              </p>
            </div>
          </div>

          <ul class="space-y-2 mb-6">
            <li v-for="feature in plan.advantges" :key="feature.id" class="flex items-start gap-2 text-gray-700 text-sm">
              <span class="w-4 h-4 bg-green-500 text-white rounded-full flex items-center justify-center text-[10px]">&#10003;</span>
              <span>{{ feature.feature }}</span>
            </li>

            <li v-if="plan.advantges.length === 0" class="text-gray-400 text-xs text-center py-2">
              {{ $t("plans.noFeatures") || "No features yet" }}
            </li>
          </ul>

          <button
            @click="$emit('open-plan', plan.slug)"
            :class="[
              'w-full py-2.5 rounded-lg font-semibold text-sm transition-all',
              plan.name === 'free'
                ? 'bg-gray-100 hover:bg-gray-200 text-gray-900 border'
                : 'bg-blue-600 hover:bg-blue-700 text-white'
            ]"
          >
            {{
              plan.name === 'free'
                ? ($t("plans.getStarted") || "Get started")
                : ($t("plans.subscribe") || "Subscribe now")
            }}
          </button>

          <p class="text-center text-xs text-gray-500 mt-3">
            {{
              plan.name === 'free'
                ? ($t("plans.noCreditCard") || "No credit card required")
                : ($t("plans.cancelAnytime") || "Cancel anytime")
            }}
          </p>
        </div>
      </div>
    </div>

    <div class="mt-12 text-center">
      <div class="flex flex-wrap justify-center gap-6 text-xs text-gray-500">
        <div class="flex items-center gap-1">
          <span class="text-green-500">&#10003;</span>
          <span>{{ $t("plans.securePayments") || "Secure payments" }}</span>
        </div>
        <div class="flex items-center gap-1">
          <span class="text-green-500">&#10003;</span>
          <span>{{ $t("plans.moneyBack") || "30-day guarantee" }}</span>
        </div>
        <div class="flex items-center gap-1">
          <span class="text-green-500">&#10003;</span>
          <span>{{ $t("plans.support247") || "24/7 support" }}</span>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  licenceName: {
    type: String,
    default: "free",
  },
  plans: {
    type: Array,
    default: () => [],
  },
  loadingPlans: {
    type: Boolean,
    default: false,
  },
});

defineEmits(["open-plan"]);
</script>
