<template>
  <UserLayout>
    <!-- Loading State -->
    <div v-if="loading" class="loading-wrapper">
      <div class="spinner"></div>
      <p>جاري تحميل البيانات...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="fetchError" class="error-banner">
      <span>⚠️ {{ fetchError }}</span>
    </div>

    <!-- Main Form -->
    <div v-else class="edit-page">
      <div class="page-header">
        <h1 class="page-title">Edit Event Details</h1>
        <p class="page-subtitle">
          Update the information for your upcoming event and manage its visibility.
        </p>
      </div>

      <form @submit.prevent="handleSubmit" class="event-form" novalidate>
        <!-- Event Title -->
        <div class="form-row">
          <div class="form-label-col">
            <label class="field-label">Event Title</label>
            <p class="field-hint">The primary name for the event.</p>
          </div>
          <div class="form-input-col">
            <input
              v-model="form.title"
              type="text"
              class="input-field"
              :class="{ 'input-error': errors.title }"
              placeholder="e.g. Golden Hour Photography Workshop"
            />
            <span v-if="errors.title" class="error-text">{{ errors.title }}</span>
          </div>
        </div>

        <!-- Description -->
        <div class="form-row">
          <div class="form-label-col">
            <label class="field-label">Description</label>
            <p class="field-hint">A brief overview of what to expect.</p>
          </div>
          <div class="form-input-col">
            <textarea
              v-model="form.description"
              class="input-field textarea-field"
              :class="{ 'input-error': errors.description }"
              rows="5"
              placeholder="Describe your event..."
            ></textarea>
            <span v-if="errors.description" class="error-text">{{
              errors.description
            }}</span>
          </div>
        </div>

        <!-- Event Cover Image -->
        <div class="form-row">
          <div class="form-label-col">
            <label class="field-label">Event Cover Image</label>
            <p class="field-hint">Recommended size: 1200×600px</p>
          </div>
          <div class="form-input-col">
            <!-- Drop Zone -->
            <div
              class="drop-zone"
              :class="{ 'drop-zone-active': isDragging }"
              @click="triggerFileInput"
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
              @drop.prevent="handleDrop"
            >
              <div class="drop-zone-icon">
                <svg
                  width="40"
                  height="40"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                >
                  <polyline points="16 16 12 12 8 16"></polyline>
                  <line x1="12" y1="12" x2="12" y2="21"></line>
                  <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path>
                </svg>
              </div>
              <p class="drop-text">
                Drag and drop or
                <span class="drop-link">click to upload</span>
              </p>
              <p class="drop-hint">PNG, JPG up to 10MB</p>
              <input
                ref="fileInput"
                type="file"
                accept="image/png, image/jpeg"
                class="hidden-input"
                @change="handleFileChange"
              />
            </div>

            <!-- Preview of new image -->
            <div v-if="imagePreview" class="image-preview-card">
              <img :src="imagePreview" class="preview-thumb" alt="preview" />
              <div class="preview-info">
                <span class="preview-name">{{ selectedFile?.name }}</span>
                <span class="preview-meta">New image selected</span>
              </div>
              <button
                type="button"
                class="remove-btn"
                @click="removeNewImage"
                title="Remove"
              >
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"></path>
                  <path d="M10 11v6M14 11v6"></path>
                  <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"></path>
                </svg>
              </button>
            </div>

            <!-- Current active image -->
            <div v-else-if="currentImageName" class="image-preview-card current-image">
              <div class="preview-thumb-placeholder">
                <svg
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                >
                  <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                  <circle cx="8.5" cy="8.5" r="1.5"></circle>
                  <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
              </div>
              <div class="preview-info">
                <span class="preview-name">{{ currentImageName }}</span>
                <span class="preview-meta">Current active image</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Schedule -->
        <div class="form-row">
          <div class="form-label-col">
            <label class="field-label">Schedule</label>
            <p class="field-hint">Define the date and time range.</p>
          </div>
          <div class="form-input-col">
            <div class="date-grid">
              <div class="date-field">
                <label class="sub-label">START DATE</label>
                <div class="input-icon-wrap">
                  <svg
                    class="input-icon"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                  </svg>
                  <input
                    v-model="form.start_date"
                    type="date"
                    class="input-field date-input"
                    :class="{ 'input-error': errors.start_date }"
                  />
                </div>
                <span v-if="errors.start_date" class="error-text">{{
                  errors.start_date
                }}</span>
              </div>

              <div class="date-field">
                <label class="sub-label">END DATE</label>
                <div class="input-icon-wrap">
                  <svg
                    class="input-icon"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                  </svg>
                  <input
                    v-model="form.end_date"
                    type="date"
                    class="input-field date-input"
                    :class="{ 'input-error': errors.end_date }"
                  />
                </div>
                <span v-if="errors.end_date" class="error-text">{{
                  errors.end_date
                }}</span>
              </div>
            </div>

            <div class="time-field">
              <label class="sub-label">SESSION TIME</label>
              <div class="input-icon-wrap">
                <svg
                  class="input-icon"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <input v-model="form.time" type="time" class="input-field time-input" />
              </div>
            </div>
          </div>
        </div>

        <!-- Success Message -->
        <Transition name="fade">
          <div v-if="successMessage" class="success-banner">✅ {{ successMessage }}</div>
        </Transition>

        <!-- Submit Error -->
        <Transition name="fade">
          <div v-if="submitError" class="error-banner">⚠️ {{ submitError }}</div>
        </Transition>

        <!-- Actions -->
        <div class="form-actions">
          <button type="button" class="btn-cancel" @click="handleCancel">Cancel</button>
          <button type="submit" class="btn-save" :disabled="submitting">
            <span v-if="submitting" class="btn-spinner"></span>
            <svg
              v-else
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
            >
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            {{ submitting ? "Saving..." : "Save Changes" }}
          </button>
        </div>
      </form>
    </div>
  </UserLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { EventService } from "../../services/admin/events/userEvent";
import UserLayout from "../../layouts/user/UserLayout.vue";

// ─── Router ────────────────────────────────────────────────────────────────
const route = useRoute();
const router = useRouter();

// ─── State ─────────────────────────────────────────────────────────────────
const loading = ref(true);
const fetchError = ref(null);
const submitting = ref(false);
const submitError = ref(null);
const successMessage = ref(null);

const isDragging = ref(false);
const fileInput = ref(null);
const selectedFile = ref(null);
const imagePreview = ref(null);
const currentImageName = ref(null);

const eventId = ref(null);

const form = ref({
  title: "",
  description: "",
  start_date: "",
  end_date: "",
  time: "",
});

const errors = ref({});

// ─── Fetch on mount ────────────────────────────────────────────────────────
onMounted(async () => {
  const id = route.params.slug;
  try {
    const res = await EventService.getSingleEvent(id);
    const data = res.data;

    eventId.value = data.slug;

    form.value = {
      title: data.title ?? "",
      description: data.description ?? "",
      start_date: data.start_date ?? "",
      end_date: data.end_date ?? "",
      time: data.time ?? "",
    };

    if (data.image) {
      currentImageName.value = data.image.split("/").pop();
    } else if (data.images && data.images.length > 0) {
      currentImageName.value = data.images[0].split("/").pop();
    }
  } catch (err) {
    fetchError.value = err?.response?.data?.message ?? "Failed to load event data.";
  } finally {
    loading.value = false;
  }
});

// ─── File Handling ─────────────────────────────────────────────────────────
function triggerFileInput() {
  fileInput.value?.click();
}

function handleFileChange(e) {
  const file = e.target.files[0];
  if (file) applyFile(file);
}

function handleDrop(e) {
  isDragging.value = false;
  const file = e.dataTransfer.files[0];
  if (file && (file.type === "image/png" || file.type === "image/jpeg")) {
    applyFile(file);
  }
}

function applyFile(file) {
  selectedFile.value = file;
  imagePreview.value = URL.createObjectURL(file);
}

function removeNewImage() {
  selectedFile.value = null;
  imagePreview.value = null;
  if (fileInput.value) fileInput.value.value = "";
}

// ─── Validation ────────────────────────────────────────────────────────────
function validate() {
  const e = {};
  if (!form.value.title.trim()) e.title = "Title is required.";
  if (!form.value.description.trim()) e.description = "Description is required.";
  if (!form.value.start_date) e.start_date = "Start date is required.";
  if (!form.value.end_date) e.end_date = "End date is required.";
  if (form.value.start_date && form.value.end_date) {
    if (new Date(form.value.end_date) < new Date(form.value.start_date)) {
      e.end_date = "End date must be after start date.";
    }
  }
  errors.value = e;
  return Object.keys(e).length === 0;
}

// ─── Submit ────────────────────────────────────────────────────────────────
async function handleSubmit() {
  submitError.value = null;
  successMessage.value = null;

  if (!validate()) return;

  const formData = new FormData();
  formData.append("title", form.value.title);
  formData.append("description", form.value.description);
  formData.append("start_date", form.value.start_date);
  formData.append("end_date", form.value.end_date);
  if (form.value.time) formData.append("time", form.value.time);
  if (selectedFile.value) formData.append("image", selectedFile.value);
  formData.append("_method", "post");

  submitting.value = true;
  try {
    await EventService.updateEvent(eventId.value, formData);
    successMessage.value = "Event updated successfully!";
    setTimeout(() => (successMessage.value = null), 4000);
    window.location.href = "/admin/events";
  } catch (err) {
    submitError.value =
      err?.response?.data?.message ?? "Something went wrong. Please try again.";
  } finally {
    submitting.value = false;
  }
}

function handleCancel() {
  router.back();
}
</script>

<style scoped>
/* ── Base ──────────────────────────────────────────────────── */
.edit-page {
  max-width: 860px;
  margin: 0 auto;
  padding: 32px 24px 60px;
  font-family: "Segoe UI", system-ui, sans-serif;
}

/* ── Header ───────────────────────────────────────────────── */
.page-title {
  font-size: 28px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 6px;
  letter-spacing: -0.5px;
}
.page-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0 0 32px;
}

/* ── Form card ────────────────────────────────────────────── */
.event-form {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 0 0 8px;
  overflow: hidden;
}

/* ── Form row ─────────────────────────────────────────────── */
.form-row {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 24px;
  padding: 28px 32px;
  border-bottom: 1px solid #f1f5f9;
  align-items: flex-start;
}
.form-row:last-of-type {
  border-bottom: none;
}

.form-label-col {
  padding-top: 4px;
}
.field-label {
  display: block;
  font-weight: 700;
  font-size: 14px;
  color: #1e293b;
  margin-bottom: 4px;
}
.field-hint {
  font-size: 12.5px;
  color: #94a3b8;
  margin: 0;
  line-height: 1.5;
}

/* ── Inputs ───────────────────────────────────────────────── */
.input-field {
  width: 100%;
  padding: 11px 14px;
  font-size: 14px;
  color: #1e293b;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
}
.input-field:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
  background: #fff;
}
.input-field.input-error {
  border-color: #ef4444;
  background: #fff5f5;
}
.textarea-field {
  resize: vertical;
  min-height: 110px;
  line-height: 1.6;
}
.error-text {
  display: block;
  font-size: 12px;
  color: #ef4444;
  margin-top: 5px;
}

/* ── Drop Zone ────────────────────────────────────────────── */
.drop-zone {
  border: 2px dashed #cbd5e1;
  border-radius: 12px;
  padding: 36px 20px;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
  background: #f8fafc;
}
.drop-zone:hover,
.drop-zone-active {
  border-color: #3b82f6;
  background: #eff6ff;
}
.drop-zone-icon {
  color: #94a3b8;
  margin-bottom: 10px;
}
.drop-text {
  font-size: 14px;
  color: #475569;
  margin: 0 0 4px;
}
.drop-link {
  color: #3b82f6;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}
.drop-hint {
  font-size: 12px;
  color: #94a3b8;
  margin: 0;
}
.hidden-input {
  display: none;
}

/* ── Image Preview Card ───────────────────────────────────── */
.image-preview-card {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px 14px;
  margin-top: 12px;
}
.preview-thumb {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid #e2e8f0;
}
.preview-thumb-placeholder {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  background: #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  flex-shrink: 0;
}
.preview-info {
  flex: 1;
  min-width: 0;
}
.preview-name {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.preview-meta {
  display: block;
  font-size: 12px;
  color: #94a3b8;
  margin-top: 2px;
}
.remove-btn {
  background: #fee2e2;
  border: none;
  border-radius: 7px;
  padding: 7px;
  cursor: pointer;
  color: #ef4444;
  display: flex;
  align-items: center;
  transition: background 0.2s;
  flex-shrink: 0;
}
.remove-btn:hover {
  background: #fecaca;
}

/* ── Date / Time Grid ─────────────────────────────────────── */
.date-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}
.date-field,
.time-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.sub-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.07em;
  color: #94a3b8;
  text-transform: uppercase;
}
.input-icon-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.input-icon {
  position: absolute;
  left: 12px;
  color: #94a3b8;
  pointer-events: none;
  z-index: 1;
}
.date-input,
.time-input {
  padding-left: 38px;
}

/* ── Banners ──────────────────────────────────────────────── */
.success-banner {
  margin: 0 32px;
  padding: 12px 16px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  font-size: 14px;
  color: #15803d;
}
.error-banner {
  margin: 0 32px;
  padding: 12px 16px;
  background: #fff5f5;
  border: 1px solid #fecaca;
  border-radius: 10px;
  font-size: 14px;
  color: #dc2626;
}

/* ── Actions ──────────────────────────────────────────────── */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 32px 24px;
}
.btn-cancel {
  padding: 11px 22px;
  font-size: 14px;
  font-weight: 600;
  color: #475569;
  background: transparent;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.2s;
}
.btn-cancel:hover {
  background: #f1f5f9;
}
.btn-save {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 11px 24px;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  background: #2563eb;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.2s, opacity 0.2s;
}
.btn-save:hover:not(:disabled) {
  background: #1d4ed8;
}
.btn-save:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

/* ── Spinner ──────────────────────────────────────────────── */
.btn-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* ── Loading ──────────────────────────────────────────────── */
.loading-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 0;
  gap: 16px;
  color: #64748b;
}
.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

/* ── Transition ───────────────────────────────────────────── */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* ── Responsive ───────────────────────────────────────────── */
@media (max-width: 640px) {
  .form-row {
    grid-template-columns: 1fr;
    padding: 20px 18px;
  }
  .date-grid {
    grid-template-columns: 1fr;
  }
  .form-actions {
    padding: 16px 18px 20px;
  }
}
</style>
