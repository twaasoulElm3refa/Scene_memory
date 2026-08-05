<template>
  <AdminLayout>
    <div class="min-h-screen bg-gray-50 p-4 md:p-6 font-sans">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center min-h-[60vh]">
        <div
          class="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"
        ></div>
      </div>

      <!-- Error -->
      <div
        v-else-if="error"
        class="max-w-4xl mx-auto bg-white rounded-2xl shadow border border-red-100 p-8 text-center text-red-700"
      >
        <p class="text-lg font-medium">{{ error }}</p>
      </div>

      <!-- Main Content -->
      <div v-else class="max-w-7xl mx-auto space-y-4">
        <!-- Header + Data Layout -->
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start">
          <!-- Left Side: Header + Hero Image -->
          <aside class="xl:col-span-5 space-y-3">
            <!-- Header -->
            <div
              class="bg-white rounded-xl shadow-sm border border-gray-100 p-3"
            >
              <div class="flex items-center justify-between gap-3">
                <button
                  @click="$router.back()"
                  class="flex items-center gap-2 text-gray-600 hover:text-indigo-600 transition-colors group font-medium text-sm"
                >
                  <svg
                    class="w-5 h-5 group-hover:-translate-x-1 transition-transform"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M15 19l-7-7 7-7"
                    />
                  </svg>

                  Back
                </button>

                <span
                  :class="
                    statusClasses[apiData.request.status] ||
                    'bg-gray-100 text-gray-700 border-gray-200'
                  "
                  class="px-3 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wide border shadow-sm"
                >
                  {{ apiData.request.status || "pending" }}
                </span>
              </div>
            </div>

            <!-- Hero Image -->
            <div
              class="rounded-2xl overflow-hidden shadow-md border border-gray-200 bg-white"
            >
              <img
                :src="heroImageUrl"
                :alt="apiData.event.title || 'Event image'"
                class="w-full h-64 md:h-[360px] xl:h-[720px] object-cover transition-transform duration-700 hover:scale-105"
                @error="handleImageError"
              />
            </div>
          </aside>

          <!-- Right Side: Event Data -->
          <main class="xl:col-span-7 space-y-3">
            <!-- Main Title / Description -->
            <section
              class="bg-white rounded-xl shadow-sm border border-gray-100 p-4"
            >
              <div class="flex flex-wrap items-center gap-2 mb-3">
                <span
                  :class="
                    apiData.event.is_active === '1' ||
                    apiData.event.is_active === 1 ||
                    apiData.event.is_active === true
                      ? 'bg-green-50 text-green-700 border-green-200'
                      : 'bg-red-50 text-red-700 border-red-200'
                  "
                  class="inline-flex px-3 py-1 rounded-full text-[11px] font-semibold border"
                >
                  {{
                    apiData.event.is_active === "1" ||
                    apiData.event.is_active === 1 ||
                    apiData.event.is_active === true
                      ? "Active"
                      : "Inactive"
                  }}
                </span>

                <span
                  :class="
                    statusClasses[apiData.request.status] ||
                    'bg-gray-100 text-gray-700 border-gray-200'
                  "
                  class="inline-flex px-3 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wide border"
                >
                  {{ apiData.request.status || "pending" }}
                </span>
              </div>

              <h1
                class="text-xl md:text-2xl font-bold text-gray-900 leading-snug"
              >
                {{
                  apiData.event.admin_translation?.title ||
                  apiData.event.title ||
                  "—"
                }}
              </h1>

              <p
                class="mt-2 text-sm text-gray-600 leading-relaxed line-clamp-4"
              >
                {{
                  apiData.event.admin_translation?.description ||
                  apiData.event.description ||
                  "No description provided."
                }}
              </p>
            </section>

            <!-- Small Data Cards -->
            <section
              class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-2"
            >
              <div
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 min-h-[78px]"
              >
                <p class="text-[11px] text-gray-500 mb-1">
                  Requested by
                </p>

                <p class="text-sm font-semibold text-gray-900 truncate">
                  {{ apiData.event.user?.name || "—" }}
                </p>
              </div>

              <div
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 min-h-[78px]"
              >
                <p class="text-[11px] text-gray-500 mb-1">
                  Created At
                </p>

                <p class="text-sm font-semibold text-gray-900 truncate">
                  {{ formatDate(apiData.event.created_at) }}
                </p>
              </div>

              <div
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 min-h-[78px]"
              >
                <p class="text-[11px] text-gray-500 mb-1">
                  Start Date
                </p>

                <p class="text-sm font-semibold text-gray-900 truncate">
                  {{ formatDateOnly(apiData.event.start_date) }}
                </p>
              </div>

              <div
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 min-h-[78px]"
              >
                <p class="text-[11px] text-gray-500 mb-1">
                  End Date
                </p>

                <p class="text-sm font-semibold text-gray-900 truncate">
                  {{ formatDateOnly(apiData.event.end_date) }}
                </p>
              </div>

              <div
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 min-h-[78px]"
              >
                <p class="text-[11px] text-gray-500 mb-1">
                  Time
                </p>

                <p class="text-sm font-semibold text-gray-900 truncate">
                  {{ apiData.event.time || "—" }}
                </p>
              </div>

              <div
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 min-h-[78px]"
              >
                <p class="text-[11px] text-gray-500 mb-1">
                  City
                </p>

                <p class="text-sm font-semibold text-gray-900 truncate">
                  {{ apiData.event.city?.name || "—" }}
                </p>
              </div>

              <div
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 min-h-[78px]"
              >
                <p class="text-[11px] text-gray-500 mb-1">
                  Category
                </p>

                <p class="text-sm font-semibold text-gray-900 truncate">
                  {{ apiData.event.sub_categorey?.name || "—" }}
                </p>
              </div>

              <div
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 min-h-[78px]"
              >
                <p class="text-[11px] text-gray-500 mb-1">
                  Request Status
                </p>

                <p
                  class="text-sm font-semibold text-gray-900 capitalize truncate"
                >
                  {{ apiData.request.status || "—" }}
                </p>
              </div>
            </section>

            <!-- Event Tags -->
            <section
              v-if="apiData.event.tags?.length"
              class="bg-white rounded-xl shadow-sm border border-gray-100 p-4"
              aria-labelledby="event-tags-heading"
            >
              <h2
                id="event-tags-heading"
                class="text-sm font-bold text-gray-900 mb-3"
              >
                Event Tags
              </h2>

              <div class="flex flex-wrap gap-2">
                <span
                  v-for="(tag, index) in apiData.event.tags"
                  :key="tag.id || `${tag.name}-${index}`"
                  class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 border border-indigo-100"
                >
                  {{ tag.name }}
                </span>
              </div>
            </section>

            <section
              v-if="allImageTags.length"
              class="bg-white rounded-xl shadow-sm border border-gray-100 p-4"
              aria-labelledby="image-tags-heading"
            >
              <div class="flex items-center justify-between gap-3 mb-3">
                <h2
                  id="image-tags-heading"
                  class="text-sm font-bold text-gray-900"
                >
                  Image Tags
                </h2>

                <span
                  class="inline-flex items-center justify-center min-w-6 h-6 px-2 rounded-full bg-gray-100 text-gray-600 text-[11px] font-semibold"
                >
                  {{ allImageTags.length }}
                </span>
              </div>

              <div class="flex flex-wrap gap-2">
                <span
                  v-for="(tag, index) in allImageTags"
                  :key="tag.id || `${tag.name}-${tag.mode}-${index}`"
                  :class="[
                    'inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold border',
                    tag.mode === 'user'
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : 'bg-violet-50 text-violet-700 border-violet-200',
                  ]"
                >
                  <span>{{ tag.name }}</span>

                  <span
                    :class="[
                      'rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide',
                      tag.mode === 'user'
                        ? 'bg-emerald-100 text-emerald-700'
                        : 'bg-violet-100 text-violet-700',
                    ]"
                  >
                    {{ tag.mode === "user" ? "User" : "AI" }}
                  </span>
                </span>
              </div>
            </section>
          </main>
        </div>

        <!-- Event Images Under Header + Data -->
        <section
          class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-5"
          aria-labelledby="event-images-heading"
        >
          <div
            class="flex items-center justify-between gap-4 pb-3 border-b border-gray-100"
          >
            <div>
              <p class="text-xs font-medium text-indigo-600 mb-1">
                Event Gallery
              </p>

              <h2
                id="event-images-heading"
                class="text-xl font-bold text-gray-900"
              >
                Event Images
              </h2>
            </div>

            <span
              v-if="apiData.event.images?.length"
              class="shrink-0 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100 text-xs font-semibold"
            >
              {{ apiData.event.images.length }}

              {{
                apiData.event.images.length === 1
                  ? "image"
                  : "images"
              }}
            </span>
          </div>

          <div
            v-if="apiData.event.images?.length"
            :class="[
              'grid gap-3 mt-4',
              apiData.event.images.length === 1
                ? 'grid-cols-1'
                : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
            ]"
          >
            <a
              v-for="(image, index) in apiData.event.images"
              :key="
                image.id ||
                `${image.full_url || image.preview_url}-${index}`
              "
              :href="
                resolveImageUrl(
                  image.full_url || image.preview_url
                )
              "
              target="_blank"
              rel="noopener noreferrer"
              class="group relative block overflow-hidden rounded-xl aspect-[16/10] bg-gray-100 border border-gray-200 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
              <img
                :src="
                  resolveImageUrl(
                    image.full_url || image.preview_url
                  )
                "
                :alt="`${apiData.event.admin_translation?.title || apiData.event.title || 'Event'} image ${index + 1}`"
                loading="lazy"
                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                @error="handleImageError"
              />

              <span
                class="absolute bottom-2 right-2 flex items-center justify-center min-w-7 h-7 px-2 rounded-full bg-gray-900/70 text-white text-xs font-semibold backdrop-blur-sm"
              >
                {{ index + 1 }}
              </span>
            </a>
          </div>

          <div
            v-else
            class="mt-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-8 text-center text-gray-500 text-sm"
          >
            No images attached
          </div>
        </section>

        <!-- Rejection Reason -->
        <div
          v-if="showRejectReason"
          class="bg-white rounded-xl shadow-sm border border-red-100 p-5 mt-4"
        >
          <h3 class="text-lg font-bold text-red-700 mb-3">
            Reason for Rejection
          </h3>

          <textarea
            v-model="rejectReason"
            rows="4"
            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent resize-y"
            placeholder="Please enter the reason why this request is being rejected..."
          ></textarea>

          <p
            v-if="rejectReasonError"
            class="mt-2 text-sm text-red-600"
          >
            {{ rejectReasonError }}
          </p>
        </div>

        <!-- Action Buttons -->
        <div
          class="flex flex-col sm:flex-row gap-4 pt-5 justify-center"
        >
          <button
            @click="approveRequest"
            :disabled="actionLoading"
            class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 px-8 transition-all shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 rounded-full min-w-[200px] disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {{
              actionLoading
                ? "Processing..."
                : "Approve Request"
            }}
          </button>

          <button
            v-if="!showRejectReason"
            @click="showRejectReason = true"
            :disabled="actionLoading"
            class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-8 transition-all shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded-full min-w-[200px] disabled:opacity-60 disabled:cursor-not-allowed"
          >
            Reject Request
          </button>

          <button
            v-else
            @click="declineRequest"
            :disabled="actionLoading || !rejectReason.trim()"
            class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-8 transition-all shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded-full min-w-[200px] disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {{
              actionLoading
                ? "Processing..."
                : "Confirm Rejection"
            }}
          </button>

          <button
            v-if="showRejectReason"
            @click="
              showRejectReason = false;
              rejectReason = '';
              rejectReasonError = '';
            "
            class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2.5 px-8 transition-all shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 rounded-full min-w-[200px]"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { RequestService } from "../../../services/RequestService/RequestService";

const route = useRoute();

const apiData = ref(null);
const loading = ref(true);
const error = ref(null);
const actionLoading = ref(false);
const showRejectReason = ref(false);
const rejectReason = ref("");
const rejectReasonError = ref("");

const statusClasses = {
  pending: "bg-amber-50 text-amber-800 border-amber-200",
  approved: "bg-green-50 text-green-800 border-green-200",
  rejected: "bg-red-50 text-red-800 border-red-200",
};

const resolveImageUrl = (path) => {
  if (!path) return "";

  const imagePath = String(path).trim();

  if (
    /^(https?:)?\/\//i.test(imagePath) ||
    imagePath.startsWith("data:") ||
    imagePath.startsWith("blob:")
  ) {
    return imagePath;
  }

  if (imagePath.startsWith("/storage/")) {
    return imagePath;
  }

  return `/storage/${imagePath
    .replace(/^\/+/, "")
    .replace(/^public\//, "")}`;
};

const heroImageUrl = computed(() => {
  const firstImage = apiData.value?.event?.first_image;

  const imagePath =
    firstImage?.full_url ||
    firstImage?.preview_url;

  return (
    resolveImageUrl(imagePath) ||
    "https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1200&auto=format&fit=crop&q=80"
  );
});

const allImageTags = computed(() => {
  const images = apiData.value?.event?.images;

  if (!Array.isArray(images)) {
    return [];
  }

  const tags = images.flatMap((image) => {
    return Array.isArray(image?.tags) ? image.tags : [];
  });

  const uniqueTags = new Map();

  tags.forEach((tag) => {
    const tagName = String(tag?.name || "").trim();

    if (!tagName) {
      return;
    }

    const mode = tag?.mode === "user" ? "user" : "ai";

    const key =
      tag?.id ??
      `${tagName.toLowerCase()}-${mode}`;

    if (!uniqueTags.has(key)) {
      uniqueTags.set(key, {
        ...tag,
        name: tagName,
        mode,
      });
    }
  });

  return Array.from(uniqueTags.values());
});

const formatDate = (dateStr) => {
  if (!dateStr) return "—";

  const date = new Date(dateStr);

  if (Number.isNaN(date.getTime())) {
    return "—";
  }

  return date.toLocaleString("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const formatDateOnly = (dateStr) => {
  if (!dateStr) return "—";

  const rawDate = String(dateStr).split("T")[0];

  const [year, month, day] = rawDate
    .split("-")
    .map(Number);

  if (!year || !month || !day) {
    return rawDate || "—";
  }

  const date = new Date(
    year,
    month - 1,
    day
  );

  if (Number.isNaN(date.getTime())) {
    return rawDate || "—";
  }

  return date.toLocaleDateString("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
};

const handleImageError = (event) => {
  event.target.src =
    "https://placehold.co/900x400/1f2937/9ca3af?text=No+Image+Available";
};

const fetchRequest = async () => {
  try {
    loading.value = true;
    error.value = null;

    const { data } =
      await RequestService.getSingle(
        route.params.id
      );
    console.log("Fetched request data:", data);
    apiData.value = {
      request: data.data.request,
      event: data.data.event,
    };
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      "Failed to load request details.";
  } finally {
    loading.value = false;
  }
};

const approveRequest = async () => {
  const isConfirmed = confirm(
    "Are you sure you want to APPROVE this event request?"
  );

  if (!isConfirmed) return;

  try {
    actionLoading.value = true;

    await RequestService.approve(
      route.params.id
    );

    apiData.value.request.status =
      "approved";

    alert(
      "Request approved successfully!"
    );

    window.location.href =
      "/admin/requests";
  } catch (err) {
    alert(
      err.response?.data?.message ||
      "Failed to approve request."
    );
  } finally {
    actionLoading.value = false;
  }
};

const declineRequest = async () => {
  const reason = (
    rejectReason.value || ""
  ).trim();

  rejectReasonError.value = "";

  if (!reason) {
    rejectReasonError.value =
      "Please provide a reason for rejection";

    return;
  }

  const isConfirmed = confirm(
    "Are you sure you want to REJECT this event request?"
  );

  if (!isConfirmed) return;

  try {
    actionLoading.value = true;

    await RequestService.decline(
      route.params.id,
      {
        reason,
      }
    );

    apiData.value.request.status =
      "rejected";

    alert(
      "Request rejected successfully!"
    );

    window.location.href =
      "/admin/requests";
  } catch (err) {
    alert(
      err.response?.data?.message ||
      "Failed to reject request."
    );
  } finally {
    actionLoading.value = false;
  }
};

onMounted(fetchRequest);
</script>
