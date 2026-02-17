<template>
  <AdminLayout>
    <div class="contact-wrapper">
      <!-- Loading -->
      <div v-if="loading" class="state-box">
        <div class="spinner"></div>
        <p>جاري التحميل...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="state-box error-state">
        <span class="icon">⚠️</span>
        <p>{{ error }}</p>
        <button class="btn btn-outline" @click="fetchContact">إعادة المحاولة</button>
      </div>

      <!-- Contact Card -->
      <template v-else-if="contact">
        <!-- Header -->
        <div class="page-header">
          <div class="header-left">
            <button class="btn btn-ghost back-btn" @click="$router.back()">
              <span>←</span> رجوع
            </button>
            <h2 class="page-title">تفاصيل الرسالة</h2>
          </div>
          <div class="header-badges">
            <span class="badge badge-id">#{{ contact.id }}</span>
            <span v-if="contact.user_id" class="badge badge-user">مستخدم مسجل</span>
            <span v-else class="badge badge-guest">زائر</span>
          </div>
        </div>

        <!-- Contact Info Card -->
        <div class="card contact-card">
          <div class="card-header">
            <div class="avatar">
              {{ getInitials(contact.name) }}
            </div>
            <div class="contact-meta">
              <h3 class="contact-name">{{ contact.name }}</h3>
              <a :href="`mailto:${contact.email}`" class="contact-email">{{
                contact.email
              }}</a>
              <div class="contact-time">
                <span class="icon-sm">🕐</span>
                {{ formatDate(contact.created_at) }}
              </div>
            </div>
          </div>

          <div class="card-divider"></div>

          <div class="subject-row">
            <span class="label">الموضوع</span>
            <p class="subject-text">{{ contact.subject }}</p>
          </div>

          <div class="message-box">
            <span class="label">الرسالة</span>
            <p class="message-text">{{ contact.message }}</p>
          </div>
        </div>

        <!-- Previous Responds -->
        <div
          v-if="contact.contact_responds && contact.contact_responds.length"
          class="card responds-card"
        >
          <h4 class="section-title">
            <span class="icon-sm">💬</span>
            الردود السابقة
            <span class="badge badge-count">{{ contact.contact_responds.length }}</span>
          </h4>
          <div class="responds-list">
            <div
              v-for="respond in contact.contact_responds"
              :key="respond.id"
              class="respond-item"
            >
              <div class="respond-header">
                <span class="respond-label">رد الإدارة</span>
                <span class="respond-date">{{ formatDate(respond.created_at) }}</span>
              </div>
              <p class="respond-message">{{ respond.message }}</p>
            </div>
          </div>
        </div>

        <!-- Respond Form -->
        <div class="card respond-form-card">
          <h4 class="section-title">
            <span class="icon-sm">✏️</span>
            إرسال رد
          </h4>

          <form @submit.prevent="handleSubmit" class="respond-form">
            <div class="form-group" :class="{ 'has-error': formError }">
              <label class="form-label" for="respond-message">نص الرد</label>
              <textarea
                id="respond-message"
                v-model="form.message"
                class="form-textarea"
                placeholder="اكتب ردك هنا..."
                rows="5"
                :disabled="submitting"
              ></textarea>
              <span v-if="formError" class="form-error">{{ formError }}</span>
            </div>

            <!-- Success message -->
            <div v-if="successMsg" class="success-alert">
              <span>✅</span> {{ successMsg }}
            </div>

            <div class="form-actions">
              <button
                type="button"
                class="btn btn-outline"
                @click="form.message = ''"
                :disabled="submitting"
              >
                مسح
              </button>
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="submitting || !form.message.trim()"
              >
                <span v-if="submitting" class="btn-spinner"></span>
                <span v-else>إرسال الرد</span>
              </button>
            </div>
          </form>
        </div>
      </template>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { ContactService } from "../../../services/admin/contacts/contctsService";

// ─── Route ───────────────────────────────────────────────
const route = useRoute();
const contactId = route.params.id;

// ─── State ───────────────────────────────────────────────
const contact = ref(null);
const loading = ref(true);
const error = ref(null);

const form = ref({ message: "" });
const formError = ref(null);
const submitting = ref(false);
const successMsg = ref(null);

// ─── Fetch Contact ────────────────────────────────────────
async function fetchContact() {
  loading.value = true;
  error.value = null;
  try {
    const res = await ContactService.getContact(contactId);
    contact.value = res.data;
  } catch (err) {
    error.value = err?.response?.data?.message || "حدث خطأ أثناء تحميل البيانات";
  } finally {
    loading.value = false;
  }
}

// ─── Submit Respond ───────────────────────────────────────
async function handleSubmit() {
  formError.value = null;
  successMsg.value = null;

  if (!form.value.message.trim()) {
    formError.value = "الرجاء كتابة نص الرد قبل الإرسال";
    return;
  }

  submitting.value = true;
  try {
    await ContactService.respondToContact(contactId, form.value.message);
    successMsg.value = "تم إرسال الرد بنجاح ✅";
    form.value.message = "";
    // Refresh to show new respond in list
    await fetchContact();
  } catch (err) {
    formError.value = err?.response?.data?.message || "فشل إرسال الرد، حاول مرة أخرى";
  } finally {
    submitting.value = false;
  }
}

// ─── Helpers ──────────────────────────────────────────────
function formatDate(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleString("ar-EG", {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function getInitials(name) {
  if (!name) return "?";
  return name
    .split(" ")
    .slice(0, 2)
    .map((w) => w[0])
    .join("")
    .toUpperCase();
}

// ─── Lifecycle ────────────────────────────────────────────
onMounted(fetchContact);
</script>

<style scoped>
/* ── Reset & Base ── */
.contact-wrapper {
  direction: rtl;
  max-width: 780px;
  margin: 0 auto;
  padding: 24px 16px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: "Tajawal", "Cairo", sans-serif;
}

/* ── Page Header ── */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.page-title {
  font-size: 1.4rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}
.header-badges {
  display: flex;
  gap: 8px;
}

/* ── Cards ── */
.card {
  background: #ffffff;
  border: 1px solid #e8edf5;
  border-radius: 14px;
  padding: 24px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
}

/* ── Contact Card ── */
.card-header {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 20px;
}
.avatar {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, #3b82f6, #6366f1);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  font-weight: 700;
  flex-shrink: 0;
}
.contact-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 4px;
}
.contact-email {
  color: #3b82f6;
  font-size: 0.9rem;
  text-decoration: none;
  display: block;
  margin-bottom: 6px;
}
.contact-email:hover {
  text-decoration: underline;
}
.contact-time {
  font-size: 0.82rem;
  color: #94a3b8;
  display: flex;
  align-items: center;
  gap: 4px;
}
.card-divider {
  height: 1px;
  background: #f1f5f9;
  margin: 4px 0 18px;
}
.label {
  display: block;
  font-size: 0.75rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 6px;
}
.subject-row {
  margin-bottom: 16px;
}
.subject-text {
  font-size: 1rem;
  font-weight: 600;
  color: #334155;
  margin: 0;
}
.message-box {
  background: #f8fafc;
  border-radius: 10px;
  padding: 16px;
}
.message-text {
  margin: 0;
  color: #475569;
  line-height: 1.8;
  font-size: 0.95rem;
  white-space: pre-wrap;
}

/* ── Responds Card ── */
.section-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.responds-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.respond-item {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  padding: 14px 16px;
}
.respond-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.respond-label {
  font-size: 0.78rem;
  font-weight: 700;
  color: #16a34a;
  background: #dcfce7;
  padding: 2px 10px;
  border-radius: 20px;
}
.respond-date {
  font-size: 0.78rem;
  color: #86efac;
}
.respond-message {
  margin: 0;
  color: #166534;
  line-height: 1.7;
  font-size: 0.93rem;
}

/* ── Respond Form ── */
.respond-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #475569;
}
.form-textarea {
  width: 100%;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px 14px;
  font-size: 0.95rem;
  font-family: inherit;
  color: #334155;
  resize: vertical;
  transition: border-color 0.2s, box-shadow 0.2s;
  background: #f8fafc;
  box-sizing: border-box;
  direction: rtl;
}
.form-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  background: #fff;
}
.form-textarea:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.has-error .form-textarea {
  border-color: #f87171;
}
.form-error {
  font-size: 0.82rem;
  color: #ef4444;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.success-alert {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  padding: 10px 14px;
  color: #16a34a;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* ── Buttons ── */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 20px;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
  font-family: inherit;
}
.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.btn-primary {
  background: #3b82f6;
  color: #fff;
  min-width: 120px;
  justify-content: center;
}
.btn-primary:hover:not(:disabled) {
  background: #2563eb;
}
.btn-outline {
  background: transparent;
  border: 1.5px solid #e2e8f0;
  color: #64748b;
}
.btn-outline:hover:not(:disabled) {
  border-color: #94a3b8;
  color: #334155;
}
.btn-ghost {
  background: transparent;
  color: #64748b;
  padding: 7px 12px;
}
.btn-ghost:hover {
  background: #f1f5f9;
}
.back-btn {
  font-size: 0.88rem;
}

/* ── Badges ── */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 12px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 700;
}
.badge-id {
  background: #eff6ff;
  color: #3b82f6;
}
.badge-user {
  background: #f0fdf4;
  color: #16a34a;
}
.badge-guest {
  background: #fef9c3;
  color: #ca8a04;
}
.badge-count {
  background: #eff6ff;
  color: #3b82f6;
  font-size: 0.72rem;
  padding: 2px 8px;
}
.icon-sm {
  font-size: 0.9rem;
}

/* ── States ── */
.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 60px 20px;
  color: #64748b;
  text-align: center;
}
.error-state {
  color: #ef4444;
}
.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
.btn-spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
