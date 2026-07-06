<template>
  <AdminLayout>
    <div class="edit-event-page">
      <Transition name="toast">
        <div v-if="successMessage" class="toast toast-success" role="status">
          {{ successMessage }}
        </div>
      </Transition>

      <Transition name="toast">
        <div v-if="submitError" class="toast toast-error" role="alert">
          {{ submitError }}
        </div>
      </Transition>

      <section v-if="loading" class="loading-panel">
        <div class="loader"></div>
        <div>
          <p class="loading-title">Loading event</p>
          <p class="loading-copy">Preparing the editor workspace.</p>
        </div>
      </section>

      <section v-else-if="fetchError" class="empty-panel">
        <div class="empty-icon">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 9v4m0 4h.01M10.3 3.9 2.3 18a2 2 0 0 0 1.7 3h16a2 2 0 0 0 1.7-3l-8-14.1a2 2 0 0 0-3.4 0Z" />
          </svg>
        </div>
        <h2>Could not load this event</h2>
        <p>{{ fetchError }}</p>
        <div class="empty-actions">
          <button type="button" class="secondary-button" @click="handleBack">
            Back
          </button>
          <button type="button" class="primary-button" @click="fetchEvent()">
            Retry
          </button>
        </div>
      </section>

      <template v-else>
        <header class="edit-header">
          <div class="header-main">
            <button
              type="button"
              class="icon-button"
              aria-label="Back to events"
              @click="handleBack"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M19 12H5m7 7-7-7 7-7" />
              </svg>
            </button>

            <div class="header-copy">
              <p class="eyebrow">Admin event editor</p>
              <h1>{{ headerTitle }}</h1>
              <div class="header-meta">
                <span>{{ cityName }}</span>
                <span>{{ categoryName }}</span>
                <span>ID {{ event?.id }}</span>
              </div>
            </div>
          </div>

          <div class="header-actions">
            <label class="quick-toggle">
              <input v-model="form.is_trending" type="checkbox" />
              <span class="switch" aria-hidden="true"></span>
              <span>Trending</span>
            </label>

            <span class="save-state">{{ saveState }}</span>

            <button
              type="button"
              class="primary-button"
              :disabled="submitting || uploading"
              @click="handleSubmit"
            >
              <span v-if="submitting" class="mini-loader"></span>
              <svg v-else viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20 6 9 17l-5-5" />
              </svg>
              {{ submitting ? "Saving" : "Save" }}
            </button>
          </div>
        </header>

        <form class="edit-grid" novalidate @submit.prevent="handleSubmit">
          <main class="content-panel">
            <div class="section-heading">
              <div>
                <p class="eyebrow">Content</p>
                <h2>Event details</h2>
              </div>
              <span class="status-pill" :class="{ active: form.is_trending }">
                {{ form.is_trending ? "Trending" : "Standard" }}
              </span>
            </div>

            <div class="field-group">
              <label for="event-title">Title</label>
              <input
                id="event-title"
                v-model="form.title"
                class="text-input"
                :class="{ invalid: errors.title }"
                type="text"
                autocomplete="off"
                placeholder="Event title"
              />
              <p v-if="errors.title" class="field-error">{{ errors.title }}</p>
            </div>

            <div class="field-group">
              <label for="event-description">Description</label>
              <textarea
                id="event-description"
                v-model="form.description"
                class="text-input textarea-input"
                :class="{ invalid: errors.description }"
                rows="7"
                placeholder="Event description"
              ></textarea>
              <p v-if="errors.description" class="field-error">
                {{ errors.description }}
              </p>
            </div>

            <div class="schedule-grid">
              <div class="field-group">
                <label for="start-date">Start date</label>
                <input
                  id="start-date"
                  v-model="form.start_date"
                  class="text-input"
                  :class="{ invalid: errors.start_date }"
                  type="date"
                />
                <p v-if="errors.start_date" class="field-error">
                  {{ errors.start_date }}
                </p>
              </div>

              <div class="field-group">
                <label for="end-date">End date</label>
                <input
                  id="end-date"
                  v-model="form.end_date"
                  class="text-input"
                  :class="{ invalid: errors.end_date }"
                  type="date"
                />
                <p v-if="errors.end_date" class="field-error">
                  {{ errors.end_date }}
                </p>
              </div>

              <div class="field-group">
                <label for="event-time">Time</label>
                <input
                  id="event-time"
                  v-model="form.time"
                  class="text-input"
                  type="time"
                />
              </div>
            </div>

            <div class="stats-grid">
              <div class="stat-item likes">
                <span>Likes</span>
                <strong>{{ statValue("likes") }}</strong>
              </div>
              <div class="stat-item comments">
                <span>Comments</span>
                <strong>{{ statValue("comments") }}</strong>
              </div>
              <div class="stat-item views">
                <span>Views</span>
                <strong>{{ statValue("views") }}</strong>
              </div>
            </div>

            <div class="metadata-grid">
              <div>
                <span>City</span>
                <strong>{{ cityName }}</strong>
              </div>
              <div>
                <span>Sub category</span>
                <strong>{{ categoryName }}</strong>
              </div>
              <div>
                <span>Slug</span>
                <strong>{{ event?.slug || "Not set" }}</strong>
              </div>
            </div>
          </main>

          <aside class="side-stack">
            <section class="side-panel">
              <div class="section-heading compact">
                <div>
                  <p class="eyebrow">Media</p>
                  <h2>Cover and gallery</h2>
                </div>
                <span class="count-pill">{{ mediaFiles.length }} files</span>
              </div>

              <div class="cover-block">
                <div class="cover-preview">
                  <img :src="coverImage" alt="Current event cover" @error="useFallbackImage" />
                </div>
                <div class="cover-info">
                  <span>Cover image</span>
                  <strong>{{ coverLabel }}</strong>
                  <div class="cover-actions">
                    <input
                      ref="coverInput"
                      class="file-input"
                      type="file"
                      accept="image/*"
                      @change="handleCoverChange"
                    />
                    <button type="button" class="secondary-button small" @click="triggerCoverInput">
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 5v14m7-7H5" />
                      </svg>
                      Replace
                    </button>
                    <button
                      v-if="coverPreview"
                      type="button"
                      class="ghost-button small"
                      @click="clearCoverSelection"
                    >
                      Clear
                    </button>
                  </div>
                </div>
              </div>

              <div v-if="mediaFiles.length" class="media-grid">
                <article v-for="media in mediaFiles" :key="media.id" class="media-tile">
                  <button
                    type="button"
                    class="media-thumb"
                    :aria-label="`Preview media ${media.id}`"
                    @click="openPreview(media)"
                  >
                    <video
                      v-if="isMediaVideo(media)"
                      :src="mediaUrl(media)"
                      muted
                      playsinline
                      preload="metadata"
                    ></video>
                    <img
                      v-else
                      :src="mediaUrl(media)"
                      :alt="`Event media ${media.id}`"
                      @error="useFallbackImage"
                    />
                    <span v-if="isMediaVideo(media)" class="play-badge">
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M8 5v14l11-7Z" />
                      </svg>
                    </span>
                  </button>
                  <div class="media-actions">
                    <button type="button" class="ghost-button small" @click="openPreview(media)">
                      View
                    </button>
                    <button
                      type="button"
                      class="danger-button small"
                      :disabled="deletingMediaId === media.id"
                      @click="deleteMedia(media)"
                    >
                      {{ deletingMediaId === media.id ? "Deleting" : "Delete" }}
                    </button>
                  </div>
                </article>
              </div>

              <div v-else class="media-empty">
                <span>No media files yet</span>
              </div>

              <div
                class="upload-zone"
                :class="{ dragging: isDragging, selected: selectedFile }"
                @click="triggerMediaInput"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="handleMediaDrop"
              >
                <input
                  ref="mediaInput"
                  class="file-input"
                  type="file"
                  accept="image/*,video/mp4,video/webm,video/quicktime"
                  @change="handleMediaFileChange"
                />

                <template v-if="!selectedFile">
                  <svg class="upload-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 16V4m0 0 5 5m-5-5L7 9M20 16.5V19a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2.5" />
                  </svg>
                  <strong>Drop media here</strong>
                  <span>Images or video, click to choose a file</span>
                </template>

                <div v-else class="selected-media" @click.stop>
                  <div class="selected-preview">
                    <video
                      v-if="selectedFile.type.startsWith('video/')"
                      :src="mediaPreview"
                      muted
                      playsinline
                    ></video>
                    <img v-else :src="mediaPreview" alt="Selected upload preview" />
                  </div>
                  <div>
                    <strong>{{ selectedFile.name }}</strong>
                    <span>{{ fileSize(selectedFile.size) }}</span>
                  </div>
                  <button type="button" class="ghost-button small" @click="clearMediaSelection">
                    Remove
                  </button>
                </div>
              </div>

              <div v-if="uploading" class="progress-track" aria-label="Upload progress">
                <span :style="{ width: `${uploadProgress}%` }"></span>
              </div>

              <p v-if="uploadError" class="field-error">{{ uploadError }}</p>

              <button
                type="button"
                class="secondary-button upload-button"
                :disabled="!selectedFile || uploading || !event?.id"
                @click="uploadSelectedMedia"
              >
                <span v-if="uploading" class="mini-loader dark"></span>
                <svg v-else viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 5v14m7-7H5" />
                </svg>
                {{ uploading ? `Uploading ${uploadProgress}%` : "Upload media" }}
              </button>
            </section>

            <section class="side-panel settings-panel">
              <div class="section-heading compact">
                <div>
                  <p class="eyebrow">Settings</p>
                  <h2>Visibility</h2>
                </div>
              </div>

              <label class="settings-toggle">
                <input v-model="form.is_trending" type="checkbox" />
                <span class="switch" aria-hidden="true"></span>
                <span>
                  <strong>Trending event</strong>
                  <small>Highlight this event in trending areas.</small>
                </span>
              </label>
            </section>
          </aside>
        </form>

        <div v-if="previewMedia" class="preview-modal" @click="closePreview">
          <button type="button" class="modal-close" @click.stop="closePreview">
            Close
          </button>
          <video
            v-if="isMediaVideo(previewMedia)"
            :src="mediaUrl(previewMedia)"
            controls
            autoplay
            class="modal-media"
            @click.stop
          ></video>
          <img
            v-else
            :src="mediaUrl(previewMedia)"
            alt="Event media preview"
            class="modal-media"
            @click.stop
          />
        </div>
      </template>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { EventImageService } from "../../../services/EventImageService/EventImageService";
import { normalizeErrorMessage, showSafeToast } from "../../../services/ApiClient";
import { EventService } from "../../../services/admin/events/EventService";

const route = useRoute();
const router = useRouter();

const fallbackImage = "/images/logo.png";

const event = ref(null);
const loading = ref(true);
const fetchError = ref(null);
const submitting = ref(false);
const submitError = ref(null);
const successMessage = ref(null);

const mediaFiles = ref([]);
const mediaInput = ref(null);
const coverInput = ref(null);
const selectedFile = ref(null);
const mediaPreview = ref(null);
const coverFile = ref(null);
const coverPreview = ref(null);
const uploadProgress = ref(0);
const uploading = ref(false);
const uploadError = ref(null);
const isDragging = ref(false);
const deletingMediaId = ref(null);
const previewMedia = ref(null);

const form = ref({
  title: "",
  description: "",
  start_date: "",
  end_date: "",
  time: "",
  is_trending: false,
});

const errors = ref({});

const headerTitle = computed(() => {
  return form.value.title || event.value?.translation?.title || event.value?.title || "Edit event";
});

const cityName = computed(() => {
  return displayName(event.value?.city, "No city");
});

const categoryName = computed(() => {
  return displayName(event.value?.sub_categorey, "No sub category");
});

const coverImage = computed(() => {
  return coverPreview.value || toAssetUrl(event.value?.image) || mediaUrl(mediaFiles.value[0]) || fallbackImage;
});

const coverLabel = computed(() => {
  if (coverFile.value?.name) return coverFile.value.name;
  if (event.value?.image) return fileNameFromPath(event.value.image);
  return "Current media image";
});

const saveState = computed(() => {
  if (submitting.value) return "Saving changes";
  if (uploading.value) return "Uploading media";
  return "Ready";
});

onMounted(() => {
  fetchEvent();
});

onBeforeUnmount(() => {
  revokeObjectUrl(mediaPreview.value);
  revokeObjectUrl(coverPreview.value);
});

async function fetchEvent(slug = route.params.id, options = {}) {
  const silent = Boolean(options.silent);

  if (!silent) {
    loading.value = true;
  }

  fetchError.value = null;

  try {
    const response = await EventService.getSingleEvent(slug);
    const payload = normalizeResponse(response);

    event.value = payload;
    hydrateForm(payload);
    mediaFiles.value = normalizeMediaList(payload?.images || []);

    if (payload?.id) {
      await fetchEventMedia(payload.id);
    }
  } catch (err) {
    fetchError.value = getErrorMessage(err, "Failed to load event data.");
  } finally {
    loading.value = false;
  }
}

async function fetchEventMedia(eventId) {
  try {
    const response = await EventImageService.all(eventId);
    const payload = normalizeResponse(response);

    if (Array.isArray(payload)) {
      mediaFiles.value = normalizeMediaList(payload);
    }
  } catch (err) {
    uploadError.value = getErrorMessage(err, "Could not refresh media files.");
  }
}

function hydrateForm(payload) {
  form.value = {
    title: payload?.translation?.title || payload?.title || "",
    description: payload?.translation?.description || payload?.description || "",
    start_date: toDateInput(payload?.start_date),
    end_date: toDateInput(payload?.end_date),
    time: normalizeTime(payload?.time),
    is_trending: toBoolean(payload?.is_trending),
  };
}

function validate() {
  const nextErrors = {};

  if (!form.value.title.trim()) {
    nextErrors.title = "Title is required.";
  }

  if (!form.value.description.trim()) {
    nextErrors.description = "Description is required.";
  }

  if (!form.value.start_date) {
    nextErrors.start_date = "Start date is required.";
  }

  if (!form.value.end_date) {
    nextErrors.end_date = "End date is required.";
  }

  if (form.value.start_date && form.value.end_date) {
    const start = new Date(form.value.start_date);
    const end = new Date(form.value.end_date);

    if (end < start) {
      nextErrors.end_date = "End date must be after start date.";
    }
  }

  if (typeof form.value.is_trending !== "boolean") {
    nextErrors.is_trending = "Trending value must be true or false.";
  }

  errors.value = nextErrors;
  return Object.keys(nextErrors).length === 0;
}

async function handleSubmit() {
  submitError.value = null;
  successMessage.value = null;

  if (!validate()) {
    submitError.value = "Please fix the highlighted fields.";
    showToast("error", submitError.value);
    return;
  }

  const formData = new FormData();
  formData.append("title", form.value.title.trim());
  formData.append("description", form.value.description.trim());
  formData.append("start_date", form.value.start_date);
  formData.append("end_date", form.value.end_date);
  formData.append("time", form.value.time || "");
  formData.append("is_trending", form.value.is_trending ? "1" : "0");

  if (coverFile.value) {
    formData.append("image", coverFile.value);
  }

  const currentKey = event.value?.slug || route.params.id;

  console.group("Update Event Request");
  console.log("Event ID/Slug:", currentKey);
  console.log("Event object:", event.value);
  console.log("Form state:", form.value);

  for (const pair of formData.entries()) {
    console.log("FormData:", pair[0], pair[1]);
  }

  console.groupEnd();

  if (!currentKey) {
    const message = "لا يمكن حفظ الحدث لأن رقم أو slug الحدث غير موجود.";
    submitError.value = message;
    showToast("error", message);
    return;
  }

  submitting.value = true;

  try {
    const response = await EventService.updateEvent(currentKey, formData);
    const updated = normalizeResponse(response);
    const nextSlug = updated?.slug || currentKey;

    successMessage.value = "Event saved successfully.";
    showToast("success", "تم حفظ الحدث بنجاح.");
    clearCoverSelection();

    if (nextSlug && nextSlug !== route.params.id) {
      await router.replace(`/admin/events/${nextSlug}/edit`);
    }

    await fetchEvent(nextSlug, { silent: true });
    hideSuccessLater();
  } catch (err) {
    console.group("Update Event Save Error");
    console.error("Full error:", err);
    console.error("Status:", err?.response?.status);
    console.error("Response data:", err?.response?.data);
    console.error("Message:", err?.response?.data?.message);
    console.error("Errors:", err?.response?.data?.errors);
    console.groupEnd();

    const responseMessage = normalizeErrorMessage(
      err?.response?.data?.message || err?.message,
      "حدث خطأ أثناء الحفظ. راجع تفاصيل الخطأ في Console و Laravel logs."
    );

    if (err?.response?.data?.errors) {
      errors.value = Object.fromEntries(
        Object.entries(err.response.data.errors).map(([key, value]) => [
          key,
          Array.isArray(value) ? normalizeErrorMessage(value[0], responseMessage) : normalizeErrorMessage(value, responseMessage),
        ])
      );
    }

    submitError.value = responseMessage;
    showToast("error", responseMessage);
  } finally {
    submitting.value = false;
  }
}

function triggerCoverInput() {
  coverInput.value?.click();
}

function handleCoverChange(eventTarget) {
  const file = eventTarget.target.files?.[0];

  if (!file) return;

  if (!file.type.startsWith("image/")) {
    submitError.value = "Cover must be an image file.";
    eventTarget.target.value = "";
    return;
  }

  revokeObjectUrl(coverPreview.value);
  coverFile.value = file;
  coverPreview.value = URL.createObjectURL(file);
}

function clearCoverSelection() {
  revokeObjectUrl(coverPreview.value);
  coverFile.value = null;
  coverPreview.value = null;

  if (coverInput.value) {
    coverInput.value.value = "";
  }
}

function triggerMediaInput() {
  mediaInput.value?.click();
}

function handleMediaFileChange(eventTarget) {
  const file = eventTarget.target.files?.[0];
  applyMediaFile(file);
}

function handleMediaDrop(eventTarget) {
  isDragging.value = false;
  const file = eventTarget.dataTransfer.files?.[0];
  applyMediaFile(file);
}

function applyMediaFile(file) {
  uploadError.value = null;

  if (!file) return;

  if (!file.type.startsWith("image/") && !file.type.startsWith("video/")) {
    uploadError.value = "Choose an image or video file.";
    return;
  }

  revokeObjectUrl(mediaPreview.value);
  selectedFile.value = file;
  mediaPreview.value = URL.createObjectURL(file);
  uploadProgress.value = 0;
}

function clearMediaSelection() {
  revokeObjectUrl(mediaPreview.value);
  selectedFile.value = null;
  mediaPreview.value = null;
  uploadProgress.value = 0;
  uploadError.value = null;

  if (mediaInput.value) {
    mediaInput.value.value = "";
  }
}

async function uploadSelectedMedia() {
  if (!selectedFile.value || !event.value?.id) return;

  uploadError.value = null;
  uploading.value = true;
  uploadProgress.value = 0;

  const formData = new FormData();
  formData.append("url", selectedFile.value);

  try {
    await EventImageService.create(event.value.id, formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
      onUploadProgress(progressEvent) {
        if (!progressEvent.total) return;
        uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
      },
    });

    successMessage.value = "Media uploaded successfully.";
    clearMediaSelection();
    await fetchEvent(event.value.slug || route.params.id, { silent: true });
    hideSuccessLater();
  } catch (err) {
    uploadError.value = getErrorMessage(err, "Upload failed.");
    showToast("error", uploadError.value);
  } finally {
    uploading.value = false;
  }
}

async function deleteMedia(media) {
  if (!media?.id) return;
  if (!confirm("Delete this media file?")) return;

  deletingMediaId.value = media.id;
  submitError.value = null;

  try {
    await EventImageService.delete(media.id);
    mediaFiles.value = mediaFiles.value.filter((item) => item.id !== media.id);
    successMessage.value = "Media deleted successfully.";
    await fetchEvent(event.value?.slug || route.params.id, { silent: true });
    hideSuccessLater();
  } catch (err) {
    submitError.value = getErrorMessage(err, "Could not delete this media file.");
    showToast("error", submitError.value);
  } finally {
    deletingMediaId.value = null;
  }
}

function openPreview(media) {
  previewMedia.value = media;
}

function closePreview() {
  previewMedia.value = null;
}

function handleBack() {
  router.push("/admin/events");
}

function statValue(type) {
  if (!event.value) return 0;

  if (type === "likes") {
    return event.value.likes_count ?? event.value.likes?.length ?? 0;
  }

  if (type === "comments") {
    return event.value.comments_count ?? event.value.comments?.length ?? 0;
  }

  return event.value.views_count ?? event.value.views?.length ?? 0;
}

function normalizeResponse(response) {
  return response?.data?.data || response?.data || response;
}

function normalizeMediaList(list) {
  if (!Array.isArray(list)) return [];
  return list.filter(Boolean);
}

function displayName(entity, fallback) {
  return (
    entity?.translation?.name ||
    entity?.admin_translation?.name ||
    entity?.name ||
    entity?.translation?.title ||
    fallback
  );
}

function toDateInput(value) {
  if (!value) return "";
  const text = String(value);

  if (/^\d{4}-\d{2}-\d{2}$/.test(text)) {
    return text;
  }

  if (text.includes("T")) {
    return text.split("T")[0];
  }

  if (text.includes(" ")) {
    return text.split(" ")[0];
  }

  const date = new Date(text);
  return Number.isNaN(date.getTime()) ? "" : date.toISOString().slice(0, 10);
}

function normalizeTime(value) {
  if (!value) return "";
  const text = String(value);
  return text.length >= 5 ? text.slice(0, 5) : text;
}

function toBoolean(value) {
  return value === true || value === 1 || value === "1" || value === "true";
}

function mediaUrl(media) {
  if (!media) return "";
  const raw = media.url || media.preview_url || media.full_url || media.video;
  return toAssetUrl(raw) || fallbackImage;
}

function toAssetUrl(path) {
  if (!path || typeof path !== "string") return "";

  if (
    path.startsWith("http://") ||
    path.startsWith("https://") ||
    path.startsWith("data:") ||
    path.startsWith("blob:") ||
    path.startsWith("/")
  ) {
    return path;
  }

  const cleaned = path
    .replace(/^public\//, "")
    .replace(/^storage\//, "")
    .replace(/^\/?storage\//, "");

  return `/storage/${cleaned}`;
}

function isMediaVideo(media) {
  if (!media) return false;
  if (media.type === "video" || media.video === true) return true;

  const url = mediaUrl(media).split("?")[0].toLowerCase();
  return [".mp4", ".mov", ".webm", ".avi", ".mkv"].some((extension) =>
    url.endsWith(extension)
  );
}

function fileNameFromPath(path) {
  if (!path || typeof path !== "string") return "Media";
  return decodeURIComponent(path.split("?")[0].split("/").filter(Boolean).pop() || "Media");
}

function fileSize(bytes) {
  if (!bytes) return "0 KB";
  const mb = bytes / 1024 / 1024;
  if (mb >= 1) return `${mb.toFixed(2)} MB`;
  return `${(bytes / 1024).toFixed(1)} KB`;
}

function useFallbackImage(eventTarget) {
  eventTarget.target.src = fallbackImage;
}

function getErrorMessage(err, fallback) {
  return normalizeErrorMessage(err?.response?.data?.message || err?.message, fallback);
}

function showToast(type, message) {
  const fallback =
    type === "success"
      ? "تمت العملية بنجاح."
      : "حدث خطأ غير معروف. راجع Console و Laravel log.";

  showSafeToast(type, message, fallback);
}

function hideSuccessLater() {
  window.setTimeout(() => {
    successMessage.value = null;
  }, 3500);
}

function revokeObjectUrl(url) {
  if (url?.startsWith("blob:")) {
    URL.revokeObjectURL(url);
  }
}
</script>

<style scoped>
.edit-event-page {
  min-height: 100vh;
  background: #f8fafc;
  color: #0f172a;
  padding: 24px;
}

.edit-header {
  position: sticky;
  top: 12px;
  z-index: 20;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  max-width: 1440px;
  margin: 0 auto 18px;
  padding: 16px;
  background: rgba(255, 255, 255, 0.96);
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 12px 34px rgba(15, 23, 42, 0.08);
  backdrop-filter: blur(12px);
}

.header-main,
.header-actions,
.header-meta,
.empty-actions,
.cover-actions,
.media-actions,
.selected-media,
.settings-toggle,
.quick-toggle {
  display: flex;
  align-items: center;
}

.header-main {
  gap: 14px;
  min-width: 0;
}

.header-copy {
  min-width: 0;
}

.eyebrow {
  margin: 0 0 4px;
  color: #2563eb;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}

.header-copy h1,
.section-heading h2,
.empty-panel h2 {
  margin: 0;
  color: #0f172a;
}

.header-copy h1 {
  max-width: 680px;
  overflow: hidden;
  font-size: 24px;
  font-weight: 800;
  line-height: 1.2;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.header-meta {
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}

.header-meta span,
.count-pill,
.status-pill {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 4px 8px;
  border-radius: 999px;
  background: #eef2ff;
  color: #334155;
  font-size: 12px;
  font-weight: 700;
}

.header-actions {
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 10px;
}

.save-state {
  color: #64748b;
  font-size: 13px;
  font-weight: 700;
}

.edit-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 420px;
  gap: 18px;
  max-width: 1440px;
  margin: 0 auto;
}

.content-panel,
.side-panel,
.loading-panel,
.empty-panel {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.content-panel,
.side-panel {
  padding: 18px;
}

.side-stack {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.section-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}

.section-heading h2 {
  font-size: 18px;
  font-weight: 800;
}

.section-heading.compact {
  align-items: center;
  margin-bottom: 14px;
}

.status-pill {
  background: #f1f5f9;
  color: #64748b;
}

.status-pill.active {
  background: #dcfce7;
  color: #047857;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 7px;
  margin-bottom: 16px;
}

.field-group label {
  color: #334155;
  font-size: 13px;
  font-weight: 800;
}

.text-input {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
  color: #0f172a;
  font: inherit;
  font-size: 14px;
  outline: none;
  padding: 11px 12px;
  transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}

.text-input:focus {
  background: #ffffff;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14);
}

.text-input.invalid {
  border-color: #dc2626;
  background: #fff7f7;
}

.textarea-input {
  min-height: 188px;
  line-height: 1.55;
  resize: vertical;
}

.field-error {
  margin: 0;
  color: #dc2626;
  font-size: 12px;
  font-weight: 700;
}

.schedule-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.stats-grid,
.metadata-grid {
  display: grid;
  gap: 12px;
  margin-top: 18px;
}

.stats-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.metadata-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.stat-item,
.metadata-grid div {
  min-width: 0;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px;
  background: #f8fafc;
}

.stat-item span,
.metadata-grid span,
.cover-info span,
.selected-media span,
.upload-zone span {
  display: block;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.stat-item strong,
.metadata-grid strong,
.cover-info strong,
.selected-media strong {
  display: block;
  overflow-wrap: anywhere;
  color: #0f172a;
  font-size: 15px;
  margin-top: 4px;
}

.stat-item.likes {
  background: #fff1f2;
  border-color: #fecdd3;
}

.stat-item.comments {
  background: #eff6ff;
  border-color: #bfdbfe;
}

.stat-item.views {
  background: #ecfdf5;
  border-color: #bbf7d0;
}

.cover-block {
  display: grid;
  grid-template-columns: 132px minmax(0, 1fr);
  gap: 12px;
  align-items: center;
  margin-bottom: 16px;
}

.cover-preview {
  overflow: hidden;
  aspect-ratio: 16 / 10;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f1f5f9;
}

.cover-preview img,
.media-thumb img,
.media-thumb video,
.selected-preview img,
.selected-preview video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.cover-info {
  min-width: 0;
}

.cover-actions {
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.file-input {
  display: none;
}

.media-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.media-tile {
  overflow: hidden;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #ffffff;
}

.media-thumb {
  position: relative;
  width: 100%;
  aspect-ratio: 4 / 3;
  border: 0;
  padding: 0;
  background: #0f172a;
  cursor: pointer;
}

.play-badge {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(15, 23, 42, 0.22);
  color: #ffffff;
}

.play-badge svg {
  width: 38px;
  height: 38px;
  fill: currentColor;
  stroke: none;
}

.media-actions {
  justify-content: space-between;
  gap: 8px;
  padding: 8px;
}

.media-empty {
  display: grid;
  place-items: center;
  min-height: 100px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  color: #64748b;
  font-size: 13px;
  font-weight: 800;
}

.upload-zone {
  display: grid;
  place-items: center;
  gap: 7px;
  min-height: 150px;
  margin-top: 14px;
  border: 1.5px dashed #94a3b8;
  border-radius: 8px;
  background: #f8fafc;
  color: #334155;
  cursor: pointer;
  padding: 14px;
  text-align: center;
  transition: border-color 0.2s, background 0.2s;
}

.upload-zone.dragging,
.upload-zone:hover {
  border-color: #2563eb;
  background: #eff6ff;
}

.upload-zone.selected {
  place-items: stretch;
}

.upload-icon {
  width: 34px;
  height: 34px;
  color: #2563eb;
}

.selected-media {
  justify-content: space-between;
  gap: 10px;
  text-align: left;
}

.selected-preview {
  width: 72px;
  height: 58px;
  overflow: hidden;
  border-radius: 8px;
  background: #e2e8f0;
  flex: 0 0 auto;
}

.progress-track {
  height: 8px;
  margin-top: 12px;
  overflow: hidden;
  border-radius: 999px;
  background: #e2e8f0;
}

.progress-track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #10b981;
  transition: width 0.2s;
}

.upload-button {
  width: 100%;
  justify-content: center;
  margin-top: 12px;
}

.settings-panel {
  padding-bottom: 16px;
}

.settings-toggle {
  gap: 12px;
  align-items: flex-start;
}

.settings-toggle small {
  display: block;
  margin-top: 2px;
  color: #64748b;
  font-size: 12px;
}

.quick-toggle,
.settings-toggle {
  cursor: pointer;
}

.quick-toggle input,
.settings-toggle input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.switch {
  position: relative;
  width: 42px;
  height: 24px;
  border-radius: 999px;
  background: #cbd5e1;
  flex: 0 0 auto;
  transition: background 0.2s;
}

.switch::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #ffffff;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.25);
  transition: transform 0.2s;
}

.quick-toggle input:checked + .switch,
.settings-toggle input:checked + .switch {
  background: #10b981;
}

.quick-toggle input:checked + .switch::after,
.settings-toggle input:checked + .switch::after {
  transform: translateX(18px);
}

.quick-toggle span:last-child {
  color: #334155;
  font-size: 13px;
  font-weight: 800;
}

.icon-button,
.primary-button,
.secondary-button,
.ghost-button,
.danger-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 38px;
  border-radius: 8px;
  border: 1px solid transparent;
  cursor: pointer;
  font: inherit;
  font-size: 14px;
  font-weight: 800;
  padding: 9px 14px;
  transition: background 0.2s, border-color 0.2s, color 0.2s, opacity 0.2s;
}

.icon-button {
  width: 40px;
  padding: 0;
  background: #eff6ff;
  color: #2563eb;
}

.primary-button {
  background: #2563eb;
  color: #ffffff;
}

.primary-button:hover:not(:disabled) {
  background: #1d4ed8;
}

.secondary-button {
  background: #ffffff;
  border-color: #cbd5e1;
  color: #334155;
}

.secondary-button:hover:not(:disabled) {
  border-color: #2563eb;
  color: #2563eb;
}

.ghost-button {
  background: #f8fafc;
  color: #475569;
}

.danger-button {
  background: #fff1f2;
  color: #be123c;
}

.danger-button:hover:not(:disabled) {
  background: #ffe4e6;
}

.small {
  min-height: 32px;
  padding: 6px 9px;
  font-size: 12px;
}

button:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 2;
}

.loader,
.mini-loader {
  border-radius: 50%;
  border-style: solid;
  animation: spin 0.75s linear infinite;
}

.loader {
  width: 34px;
  height: 34px;
  border-width: 3px;
  border-color: #cbd5e1;
  border-top-color: #2563eb;
}

.mini-loader {
  width: 15px;
  height: 15px;
  border-width: 2px;
  border-color: rgba(255, 255, 255, 0.45);
  border-top-color: #ffffff;
}

.mini-loader.dark {
  border-color: rgba(15, 23, 42, 0.2);
  border-top-color: #2563eb;
}

.loading-panel,
.empty-panel {
  max-width: 760px;
  margin: 70px auto;
  padding: 28px;
}

.loading-panel {
  display: flex;
  align-items: center;
  gap: 16px;
}

.loading-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.loading-copy,
.empty-panel p {
  margin: 4px 0 0;
  color: #64748b;
}

.empty-panel {
  text-align: center;
}

.empty-icon {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  margin: 0 auto 14px;
  border-radius: 50%;
  background: #fff1f2;
  color: #be123c;
}

.empty-actions {
  justify-content: center;
  gap: 10px;
  margin-top: 18px;
}

.toast {
  position: fixed;
  top: 18px;
  right: 18px;
  z-index: 60;
  max-width: min(380px, calc(100vw - 32px));
  border-radius: 8px;
  box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
  font-size: 14px;
  font-weight: 800;
  padding: 12px 14px;
}

.toast-success {
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  color: #047857;
}

.toast-error {
  background: #fff1f2;
  border: 1px solid #fecdd3;
  color: #be123c;
}

.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.2s, transform 0.2s;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

.preview-modal {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: grid;
  place-items: center;
  background: rgba(2, 6, 23, 0.88);
  padding: 24px;
}

.modal-media {
  max-width: min(1100px, 94vw);
  max-height: 86vh;
  border-radius: 8px;
  object-fit: contain;
  background: #020617;
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.45);
}

.modal-close {
  position: fixed;
  top: 18px;
  right: 18px;
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 8px;
  background: rgba(15, 23, 42, 0.72);
  color: #ffffff;
  cursor: pointer;
  font-weight: 800;
  padding: 9px 12px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1180px) {
  .edit-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 760px) {
  .edit-event-page {
    padding: 14px;
  }

  .edit-header {
    position: static;
    align-items: flex-start;
    flex-direction: column;
  }

  .header-actions {
    width: 100%;
    justify-content: space-between;
  }

  .header-copy h1 {
    white-space: normal;
  }

  .schedule-grid,
  .stats-grid,
  .metadata-grid,
  .cover-block,
  .media-grid {
    grid-template-columns: 1fr;
  }

  .selected-media {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
