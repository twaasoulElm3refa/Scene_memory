<template>
  <section
    v-if="licenceName === 'free'"
    class="scemory-plans-section container mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-10"
  >
    <!-- Section Header -->
    <div class="text-center mb-10">
      <div
        class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 rounded-full text-sm font-semibold mb-4 shadow-md"
      >
        <span class="text-xs font-extrabold" aria-hidden="true">
          PLAN
        </span>

        {{ $t("plans.chooseYourPlan") || "Choose your plan" }}
      </div>

      <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-3">
        {{ $t("plans.ourPlans") || "Our plans" }}
      </h2>

      <div
        class="w-20 h-1 bg-gradient-to-r from-blue-500 to-blue-600 mx-auto rounded-full mb-4"
      ></div>

      <p class="text-gray-600 text-base max-w-xl mx-auto">
        {{
          $t("plans.description") ||
          "Start free or pick the plan that fits you best."
        }}
      </p>
    </div>

    <!-- Loading Skeleton -->
    <div
      v-if="loadingPlans"
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6"
    >
      <div
        v-for="i in 4"
        :key="`plan-skeleton-${i}`"
        class="border border-gray-200 bg-white rounded-2xl overflow-hidden animate-pulse"
      >
        <div class="p-6 space-y-4">
          <div class="h-16 w-16 rounded-xl bg-gray-200 mx-auto"></div>

          <div class="h-6 w-2/3 mx-auto bg-gray-200 rounded"></div>

          <div class="h-9 w-1/2 mx-auto bg-gray-200 rounded"></div>

          <div class="space-y-3 pt-3">
            <div class="h-4 w-full bg-gray-200 rounded"></div>
            <div class="h-4 w-5/6 bg-gray-200 rounded"></div>
            <div class="h-4 w-4/6 bg-gray-200 rounded"></div>
          </div>

          <div class="h-11 w-full bg-gray-200 rounded-lg"></div>
        </div>
      </div>
    </div>

    <!-- Plans -->
    <div
      v-else
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 items-stretch"
    >
      <article
        v-for="plan in plans"
        :key="plan.id"
        class="relative flex flex-col border bg-white rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-2 hover:shadow-xl"
        :class="[
          isProfessionalPlan(plan)
            ? 'border-blue-300 shadow-lg shadow-blue-100'
            : 'border-gray-200',
        ]"
      >
        <!-- Most Popular Badge -->
        <div
          v-if="isProfessionalPlan(plan)"
          class="absolute top-0 right-0 z-20"
        >
          <div
            class="bg-gradient-to-r from-orange-500 to-red-500 text-white text-xs font-bold px-5 py-1.5 rounded-bl-2xl"
          >
            {{ $t("plans.mostPopular") || "Most popular" }}
          </div>
        </div>

        <div class="flex flex-col h-full p-6">
          <!-- Plan Header -->
          <div class="text-center mb-6">
            <!-- Icon Container -->
            <div
              class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4"
              :class="getPlanIconBackground(plan)"
            >
              <!-- Free Icon -->
              <svg
                v-if="isFreePlan(plan)"
                class="w-8 h-8 text-gray-600"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <circle cx="12" cy="12" r="8"></circle>
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v3"></path>
                <path d="M12 19v3"></path>
                <path d="M2 12h3"></path>
                <path d="M19 12h3"></path>
              </svg>

              <!-- Basic Icon -->
              <svg
                v-else-if="isBasicPlan(plan)"
                class="w-8 h-8 text-blue-600"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5z"></path>
                <path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H13v16h4.5a2.5 2.5 0 0 1 2.5 2.5z"></path>
              </svg>

              <!-- Professional Icon -->
              <svg
                v-else-if="isProfessionalPlan(plan)"
                class="w-8 h-8 text-blue-600"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M5 8l3-4h8l3 4-7 12z"></path>
                <path d="M5 8h14"></path>
                <path d="M8 4l4 16"></path>
                <path d="M16 4l-4 16"></path>
              </svg>

              <!-- Premium Icon -->
              <svg
                v-else-if="isPremiumPlan(plan)"
                class="w-8 h-8 text-purple-600"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M14.5 5.5c2.8-2.8 5.5-2.5 5.5-2.5s.3 2.7-2.5 5.5l-4 4-4-4z"></path>
                <path d="M9.5 8.5 6 9l-3 3 5 .5"></path>
                <path d="M13.5 12.5 13 16l-3 3-.5-5"></path>
                <path d="M6 18c-1.5.3-2.7 1.5-3 3 1.5-.3 2.7-1.5 3-3z"></path>
                <circle cx="15.5" cy="7.5" r="1"></circle>
              </svg>

              <!-- Custom Plans Icon -->
              <svg
                v-else-if="isCustomPlan(plan)"
                class="w-8 h-8 text-amber-600"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M4 7h10"></path>
                <path d="M18 7h2"></path>
                <circle cx="16" cy="7" r="2"></circle>

                <path d="M4 12h2"></path>
                <path d="M10 12h10"></path>
                <circle cx="8" cy="12" r="2"></circle>

                <path d="M4 17h7"></path>
                <path d="M15 17h5"></path>
                <circle cx="13" cy="17" r="2"></circle>
              </svg>

              <!-- Default Icon -->
              <svg
                v-else
                class="w-8 h-8 text-blue-600"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M14.5 5.5c2.8-2.8 5.5-2.5 5.5-2.5s.3 2.7-2.5 5.5l-4 4-4-4z"></path>
                <path d="M9.5 8.5 6 9l-3 3 5 .5"></path>
                <path d="M13.5 12.5 13 16l-3 3-.5-5"></path>
              </svg>
            </div>

            <!-- Plan Name -->
            <h3 class="text-2xl font-bold text-gray-900 capitalize">
              {{ plan.translation?.name || plan.name }}
            </h3>

            <!-- Price -->
            <div class="mt-4">
              <template v-if="isCustomPlan(plan)">
                <div class="flex items-baseline justify-center">
                  <span class="text-2xl font-extrabold text-gray-900">
                    {{ $t("plans.customPrice") || "Contact us" }}
                  </span>
                </div>

                <p class="text-xs text-gray-500 mt-2">
                  {{
                    $t("plans.customPricingDescription") ||
                    "Pricing tailored to your needs"
                  }}
                </p>
              </template>

              <template v-else>
                <div class="flex items-baseline justify-center gap-1">
                  <span class="text-3xl font-extrabold text-gray-900">
                    {{ plan.price }}
                  </span>

                  <span class="text-gray-500 text-sm">
                    USD
                  </span>
                </div>

                <p class="text-xs text-gray-500 mt-1">
                  {{ $t("plans.perMonth") || "per month" }}
                </p>
              </template>
            </div>
          </div>

          <!-- Features -->
          <ul class="space-y-3 mb-7">
            <li
              v-for="feature in plan.advantges || []"
              :key="feature.id"
              class="flex items-start gap-3 text-gray-700 text-sm leading-5"
            >
              <!-- Fixed Check Icon -->
              <span
                class="flex-shrink-0 inline-flex items-center justify-center w-5 h-5 mt-0.5 bg-green-500 text-white rounded-full shadow-sm"
                aria-hidden="true"
              >
                <svg
                  class="w-3 h-3"
                  viewBox="0 0 20 20"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="3"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M4.5 10.5 8 14l7.5-8"></path>
                </svg>
              </span>

              <span class="flex-1">
                {{ feature.feature }}
              </span>
            </li>

            <li
              v-if="!plan.advantges || plan.advantges.length === 0"
              class="text-gray-400 text-xs text-center py-3"
            >
              {{ $t("plans.noFeatures") || "No features yet" }}
            </li>
          </ul>

          <!-- Button Area -->
          <div class="mt-auto">
            <button
              type="button"
              class="w-full min-h-11 px-4 py-3 rounded-xl font-semibold text-sm transition-all duration-200 focus:outline-none focus:ring-4"
              :class="getButtonClasses(plan)"
              @click="handlePlanClick(plan)"
            >
              {{
                isCustomPlan(plan)
                  ? ($t("plans.contactUs") || "Contact us")
                  : isFreePlan(plan)
                    ? ($t("plans.getStarted") || "Get started")
                    : ($t("plans.subscribe") || "Subscribe now")
              }}
            </button>

            <p class="text-center text-xs text-gray-500 mt-3 min-h-4">
              {{
                isCustomPlan(plan)
                  ? ($t("plans.customPlanNote") || "Built around your requirements")
                  : isFreePlan(plan)
                    ? ($t("plans.noCreditCard") || "No credit card required")
                    : ($t("plans.cancelAnytime") || "Cancel anytime")
              }}
            </p>
          </div>
        </div>
      </article>
    </div>

    <!-- Trust Indicators -->
    <div class="mt-12 text-center">
      <div class="flex flex-wrap justify-center gap-x-7 gap-y-3 text-xs text-gray-500">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex items-center justify-center w-4 h-4 bg-green-100 text-green-600 rounded-full"
            aria-hidden="true"
          >
            <svg
              class="w-2.5 h-2.5"
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M4.5 10.5 8 14l7.5-8"></path>
            </svg>
          </span>

          <span>
            {{ $t("plans.securePayments") || "Secure payments" }}
          </span>
        </div>

        <div class="flex items-center gap-2">
          <span
            class="inline-flex items-center justify-center w-4 h-4 bg-green-100 text-green-600 rounded-full"
            aria-hidden="true"
          >
            <svg
              class="w-2.5 h-2.5"
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M4.5 10.5 8 14l7.5-8"></path>
            </svg>
          </span>

          <span>
            {{ $t("plans.moneyBack") || "30-day guarantee" }}
          </span>
        </div>

        <div class="flex items-center gap-2">
          <span
            class="inline-flex items-center justify-center w-4 h-4 bg-green-100 text-green-600 rounded-full"
            aria-hidden="true"
          >
            <svg
              class="w-2.5 h-2.5"
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M4.5 10.5 8 14l7.5-8"></path>
            </svg>
          </span>

          <span>
            {{ $t("plans.support247") || "24/7 support" }}
          </span>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const props = defineProps({
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

const emit = defineEmits(["open-plan"]);

/**
 * Normalize a plan name to avoid problems caused by:
 * - Uppercase/lowercase differences
 * - Extra spaces
 * - Underscores or hyphens
 */
const normalizePlanName = (plan) => {
  return String(plan?.name || plan?.translation?.name || "")
    .trim()
    .toLowerCase()
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ");
};

const isFreePlan = (plan) => {
  return normalizePlanName(plan) === "free";
};

const isBasicPlan = (plan) => {
  return normalizePlanName(plan) === "basic";
};

const isProfessionalPlan = (plan) => {
  const name = normalizePlanName(plan);

  return name === "professional" || name === "pro";
};

const isPremiumPlan = (plan) => {
  return normalizePlanName(plan) === "premium";
};

const isCustomPlan = (plan) => {
  const name = normalizePlanName(plan);

  return (
    name === "custom" ||
    name === "custom plan" ||
    name === "custom plans"
  );
};

const getPlanIconBackground = (plan) => {
  if (isFreePlan(plan)) {
    return "bg-gray-100";
  }

  if (isBasicPlan(plan)) {
    return "bg-blue-100";
  }

  if (isProfessionalPlan(plan)) {
    return "bg-blue-100 ring-4 ring-blue-50";
  }

  if (isPremiumPlan(plan)) {
    return "bg-purple-100";
  }

  if (isCustomPlan(plan)) {
    return "bg-amber-100";
  }

  return "bg-blue-100";
};

const getButtonClasses = (plan) => {
  if (isFreePlan(plan)) {
    return [
      "bg-gray-100",
      "hover:bg-gray-200",
      "text-gray-900",
      "border",
      "border-gray-300",
      "focus:ring-gray-200",
    ];
  }

  if (isCustomPlan(plan)) {
    return [
      "bg-gray-900",
      "hover:bg-gray-800",
      "text-white",
      "shadow-md",
      "hover:shadow-lg",
      "focus:ring-gray-200",
    ];
  }

  if (isPremiumPlan(plan)) {
    return [
      "bg-purple-600",
      "hover:bg-purple-700",
      "text-white",
      "shadow-md",
      "hover:shadow-lg",
      "focus:ring-purple-200",
    ];
  }

  return [
    "bg-blue-600",
    "hover:bg-blue-700",
    "text-white",
    "shadow-md",
    "hover:shadow-lg",
    "focus:ring-blue-200",
  ];
};

const handlePlanClick = (plan) => {
  if (!plan?.slug) {
    console.warn("The selected plan does not have a slug.", plan);
    return;
  }

  emit("open-plan", plan.slug);
};
</script>

<style scoped>
.scemory-plans-section {
  color: var(--scemory-text);
}

.scemory-plans-section > .text-center > div:first-child {
  background: var(--scemory-active) !important;
  border: 1px solid var(--scemory-border);
  color: var(--scemory-primary) !important;
  box-shadow: var(--scemory-shadow-sm);
}

.scemory-plans-section h2,
.scemory-plans-section h3 {
  color: var(--scemory-heading) !important;
}

.scemory-plans-section article {
  border-color: var(--scemory-border-soft) !important;
  background: linear-gradient(145deg, #FFFFFF, var(--scemory-surface)) !important;
  box-shadow: var(--scemory-shadow);
}

.scemory-plans-section article:hover {
  transform: translateY(-2px);
  border-color: var(--scemory-border) !important;
  box-shadow: var(--scemory-shadow-hover);
}

.scemory-plans-section .shadow-blue-100 {
  box-shadow: 0 14px 34px rgba(13, 77, 151, 0.10) !important;
}

.scemory-plans-section .text-purple-600,
.scemory-plans-section .text-amber-600,
.scemory-plans-section .text-blue-600 {
  color: var(--scemory-primary) !important;
}

.scemory-plans-section .bg-purple-100,
.scemory-plans-section .bg-amber-100,
.scemory-plans-section .bg-blue-100,
.scemory-plans-section .bg-gray-100 {
  background: var(--scemory-active) !important;
  color: var(--scemory-primary) !important;
}

.scemory-plans-section button.bg-gray-900,
.scemory-plans-section button.bg-blue-600,
.scemory-plans-section button.bg-purple-600 {
  background: linear-gradient(135deg, var(--scemory-primary), var(--scemory-blue)) !important;
  color: #FFFFFF !important;
  border-color: rgba(22, 119, 255, 0.22) !important;
  box-shadow: 0 8px 20px rgba(13, 77, 151, 0.16);
}
</style>
