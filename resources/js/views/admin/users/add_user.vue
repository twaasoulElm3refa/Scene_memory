<template>
  <AdminLayout>
    <div :class="['wrapper', theme]" :data-theme="theme">
      <!-- Header -->
      <div class="header">
        <div>
          <div class="breadcrumb">
            <span class="breadcrumb-item">Users</span>
            <span class="breadcrumb-separator">›</span>
            <span class="breadcrumb-item active">Add New User</span>
          </div>
          <h1 class="title">Add New User</h1>
          <p class="subtitle">Create a new account and assign platform permissions.</p>
        </div>
        <button class="back-btn" @click="goBack">
          <span class="arrow">←</span> Back to list
        </button>
      </div>

      <form @submit.prevent="handleSubmit" novalidate>
        <!-- Personal Information -->
        <div class="section">
          <div class="section-header">
            <span class="icon">👤</span>
            <h2 class="section-title">Personal Information</h2>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Full Name <span class="required">*</span></label>
              <input v-model.trim="form.name" type="text" class="input" placeholder="e.g. John Doe" />
              <p v-if="fieldErrors.name" class="error">{{ fieldErrors.name }}</p>
            </div>

            <div class="form-group">
              <label>Email Address <span class="required">*</span></label>
              <input v-model.trim="form.email" type="email" class="input" placeholder="john@example.com" />
              <p v-if="fieldErrors.email" class="error">{{ fieldErrors.email }}</p>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Phone Number</label>
              <input v-model.trim="form.phone" type="tel" class="input" placeholder="+20 100 123 4567" />
              <p v-if="fieldErrors.phone" class="error">{{ fieldErrors.phone }}</p>
            </div>

            <div class="form-group">
              <label>Date of Birth</label>
              <input v-model="form.date_of_birth" type="date" class="input" />
              <p v-if="fieldErrors.date_of_birth" class="error">{{ fieldErrors.date_of_birth }}</p>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Country</label>
              <select v-model="form.country" class="input select">
                <option value="">Select a country</option>
                <option value="US">United States</option>
                <option value="UK">United Kingdom</option>
                <option value="CA">Canada</option>
                <option value="EG">Egypt</option>
                <option value="SA">Saudi Arabia</option>
                <option value="AE">United Arab Emirates</option>
                <!-- أضف المزيد حسب احتياجك -->
              </select>
              <p v-if="fieldErrors.country" class="error">{{ fieldErrors.country }}</p>
            </div>

            <div class="form-group">
              <label>Position / Job Title</label>
              <input v-model.trim="form.position" type="text" class="input" placeholder="e.g. Senior Editor" />
              <p v-if="fieldErrors.position" class="error">{{ fieldErrors.position }}</p>
            </div>
          </div>
        </div>

        <!-- Account Settings -->
        <div class="section">
          <div class="section-header">
            <span class="icon">🔐</span>
            <h2 class="section-title">Account Settings</h2>
          </div>

          <div class="form-row">
            <div class="form-group password-group">
              <label>Password <span class="required">*</span></label>
              <div class="password-wrapper">
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  class="input"
                  placeholder="Enter password"
                  autocomplete="new-password"
                />
                <button type="button" class="toggle-password-btn" @click="showPassword = !showPassword">
                  {{ showPassword ? '🙈' : '👁️' }}
                </button>
              </div>
              <p v-if="fieldErrors.password" class="error">{{ fieldErrors.password }}</p>
            </div>

            <div class="form-group password-group">
              <label>Confirm Password <span class="required">*</span></label>
              <div class="password-wrapper">
                <input
                  v-model="form.password_confirmation"
                  :type="showConfirmPassword ? 'text' : 'password'"
                  class="input"
                  placeholder="Confirm password"
                  autocomplete="new-password"
                />
                <button
                  type="button"
                  class="toggle-password-btn"
                  @click="showConfirmPassword = !showConfirmPassword"
                >
                  {{ showConfirmPassword ? '🙈' : '👁️' }}
                </button>
              </div>
              <p v-if="fieldErrors.password_confirmation" class="error">{{ fieldErrors.password_confirmation }}</p>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>User Role</label>
              <select v-model="form.role" class="input select">
                <option value="user">User</option>
                <option value="admin">Admin</option>
              </select>
              <p v-if="fieldErrors.role" class="error">{{ fieldErrors.role }}</p>
            </div>

            <div class="form-group">
              <label>Active Status</label>
              <div class="toggle-container">
                <label class="toggle-switch">
                  <input v-model="form.is_active" type="checkbox" />
                  <span class="slider round"></span>
                </label>
                <span class="toggle-label">{{ form.is_active ? 'Active' : 'Inactive' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Server Messages -->
        <div v-if="generalErrors.length" class="alert error-bg">
          <div v-for="(err, index) in generalErrors" :key="index">{{ err }}</div>
        </div>

        <div v-if="successMessage" class="alert success-bg">
          {{ successMessage }}
        </div>

        <!-- Actions -->
        <div class="actions">
          <button type="button" class="btn btn-secondary" @click="handleCancel" :disabled="isSubmitting">
            Cancel
          </button>
          <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
            <span v-if="isSubmitting" class="loading">⏳ Saving...</span>
            <span v-else>Save User</span>
          </button>
        </div>
      </form>

      <!-- Info Cards -->
      <div class="info-cards">
        <div class="info-card">
          <div class="info-icon">📧</div>
          <h3>Activation Email</h3>
          <p>The user will receive login instructions once saved.</p>
        </div>
        <div class="info-card">
          <div class="info-icon">🔒</div>
          <h3>Security Policy</h3>
          <p>Password must be 8+ chars, upper & lower case, and numbers.</p>
        </div>
        <div class="info-card">
          <div class="info-icon">👥</div>
          <h3>Seat Management</h3>
          <p>This will consume 1 professional seat.</p>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { userService } from '@/services/admin/user/userService'

const theme = localStorage.getItem('theme') || 'light'

const form = reactive({
  name: '',
  email: '',
  phone: '',
  date_of_birth: '',
  country: '',
  position: '',
  password: '',
  password_confirmation: '',
  role: 'user',
  is_active: true,
})

const fieldErrors = reactive({})
const generalErrors = ref([])
const successMessage = ref('')
const isSubmitting = ref(false)

const showPassword = ref(false)
const showConfirmPassword = ref(false)

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach(key => {
    delete fieldErrors[key]
  })
}

function handleValidationErrors(errors) {
  clearFieldErrors()
  generalErrors.value = []

  if (!errors || typeof errors !== 'object') {
    generalErrors.value = ['An unexpected error occurred.']
    return
  }

  Object.keys(errors).forEach(key => {
    if (Array.isArray(errors[key])) {
      fieldErrors[key] = errors[key][0] // نأخذ أول رسالة خطأ فقط
    } else if (typeof errors[key] === 'string') {
      fieldErrors[key] = errors[key]
    }
  })

  // إذا كان فيه أخطاء عامة (غير مرتبطة بحقل معين)
  if (errors.message && !Object.keys(fieldErrors).length) {
    generalErrors.value.push(errors.message)
  }
}

const handleSubmit = async () => {
  clearFieldErrors()
  generalErrors.value = []
  successMessage.value = ''
  isSubmitting.value = true

  try {
    const response = await userService.createUser(form)
    if (!response.success) {
      throw { response: { status: response.error?.status, data: response.error } }
    }

    successMessage.value = 'User created successfully!'

    // reset form
    Object.assign(form, {
      name: '',
      email: '',
      phone: '',
      date_of_birth: '',
      country: '',
      position: '',
      password: '',
      password_confirmation: '',
      role: 'user',
      is_active: true,
    })

    showPassword.value = false
    showConfirmPassword.value = false

    // اختياري: بعد 3 ثوانٍ ارجع للقائمة
    // setTimeout(() => goBack(), 3000)
  } catch (err) {
    if (err.response?.status === 422) {
      // Laravel validation error
      handleValidationErrors(err.response.data.errors)
    } else if (err.response?.data?.message) {
      generalErrors.value = [err.response.data.message]
    } else {
      generalErrors.value = ['Something went wrong. Please try again later.']
    }
  } finally {
    isSubmitting.value = false
  }
}

const handleCancel = () => {
  if (!confirm('Discard all changes?')) return

  Object.assign(form, {
    name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    country: '',
    position: '',
    password: '',
    password_confirmation: '',
    role: 'user',
    is_active: true,
  })

  clearFieldErrors()
  generalErrors.value = []
  successMessage.value = ''
  showPassword.value = false
  showConfirmPassword.value = false
}

const goBack = () => {
  window.history.back()
}
</script>

<style scoped>
/* ── أنماط إضافية لزر إظهار/إخفاء كلمة المرور ── */
.password-group {
    position: relative;
}
.required {
  color: #e53e3e;
}
.password-wrapper {
    position: relative;
}

.toggle-password-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 1.1rem;
    cursor: pointer;
    color: #666;
    padding: 0 8px;
    line-height: 1;
}

.toggle-password-btn:hover {
    color: #333;
}

/* لضمان أن الحقل لا يتداخل مع الزر */
.input {
    padding-right: 48px !important;
    /* مساحة للأيقونة */
}

/* ===== COMMON ===== */
.wrapper {
    max-width: 1000px;
    margin: auto;
    padding: 40px;
    transition: background 0.3s, color 0.3s;
}

/* ===== HEADER ===== */
.header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
}

.breadcrumb {
    font-size: 14px;
    margin-bottom: 8px;
}

.breadcrumb-item {
    opacity: 0.6;
}

.breadcrumb-item.active {
    opacity: 1;
}

.breadcrumb-separator {
    margin: 0 8px;
}

.title {
    font-size: 32px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.subtitle {
    font-size: 15px;
    opacity: 0.7;
    margin: 0;
}

.back-btn {
    padding: 8px 16px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.arrow {
    font-size: 18px;
}

[data-theme="light"] .back-btn {
    background: transparent;
    color: #3b82f6;
}

[data-theme="light"] .back-btn:hover {
    background: #eff6ff;
}

[data-theme="dark"] .back-btn {
    background: transparent;
    color: #60a5fa;
}

[data-theme="dark"] .back-btn:hover {
    background: #1e3a5f;
}

/* ===== SECTIONS ===== */
.section {
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}

[data-theme="light"] .section {
    background: #ffffff;
    border: 1px solid #e5e7eb;
}

[data-theme="dark"] .section {
    background: #111827;
    border: 1px solid #374151;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid;
}

[data-theme="light"] .section-header {
    border-bottom-color: #e5e7eb;
}

[data-theme="dark"] .section-header {
    border-bottom-color: #374151;
}

.icon {
    font-size: 20px;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

/* ===== FORM ===== */
.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

label {
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 8px;
}

.input,
.select {
    width: 100%;
    padding: 10px 12px;
    border-radius: 8px;
    outline: none;
    font-size: 14px;
    transition: all 0.2s;
}

/* Light Theme Inputs */
[data-theme="light"] .input,
[data-theme="light"] .select {
    background: #ffffff;
    color: #000000;
    border: 1px solid #d1d5db;
}

.text-color {
    columns: #000000;
}

[data-theme="light"] .input:focus,
[data-theme="light"] .select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

[data-theme="light"] .input::placeholder {
    color: #9ca3af;
}

/* Dark Theme Inputs */
[data-theme="dark"] .input,
[data-theme="dark"] .select {
    background: #1f2937;
    color: #f3f4f6;
    border: 1px solid #374151;
}

[data-theme="dark"] .input:focus,
[data-theme="dark"] .select:focus {
    border-color: #60a5fa;
    box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.1);
}

[data-theme="dark"] .input::placeholder {
    color: #6b7280;
}

.select {
    cursor: pointer;
}

.help-text {
    font-size: 12px;
    margin-top: 6px;
    opacity: 0.7;
}

.error {
    color: #ef4444;
    font-size: 12px;
    margin-top: 4px;
}

/* ===== TOGGLE SWITCH ===== */
.toggle-container {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 4px;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e1;
    transition: 0.3s;
    border-radius: 26px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked+.slider {
    background-color: #3b82f6;
}

input:checked+.slider:before {
    transform: translateX(22px);
}

.toggle-label {
    font-size: 14px;
    font-weight: 500;
}

/* ===== ALERTS ===== */
.alert {
    padding: 14px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

[data-theme="light"] .error-bg {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

[data-theme="light"] .success-bg {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

[data-theme="dark"] .error-bg {
    background: #7f1d1d;
    color: #fecaca;
    border: 1px solid #991b1b;
}

[data-theme="dark"] .success-bg {
    background: #14532d;
    color: #bbf7d0;
    border: 1px solid #166534;
}

/* ===== BUTTONS ===== */
.actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-icon {
    font-size: 16px;
}

.btn-primary {
    background: #3b82f6;
    color: #ffffff;
}

.btn-primary:hover {
    background: #2563eb;
}

.btn-secondary {
    background: transparent;
    border: 1px solid;
}

[data-theme="light"] .btn-secondary {
    color: #374151;
    border-color: #d1d5db;
}

[data-theme="light"] .btn-secondary:hover {
    background: #f3f4f6;
}

[data-theme="dark"] .btn-secondary {
    color: #f3f4f6;
    border-color: #4b5563;
}

[data-theme="dark"] .btn-secondary:hover {
    background: #1f2937;
}

/* ===== INFO CARDS ===== */
.info-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 30px;
}

.info-card {
    padding: 20px;
    border-radius: 12px;
    text-align: center;
}

[data-theme="light"] .info-card {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
}

[data-theme="dark"] .info-card {
    background: #0f172a;
    border: 1px solid #1e293b;
}

.info-icon {
    font-size: 32px;
    margin-bottom: 12px;
}

.info-card h3 {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 8px 0;
}

.info-card p {
    font-size: 13px;
    opacity: 0.7;
    margin: 0;
    line-height: 1.5;
}

/* ===== WRAPPER BACKGROUND ===== */
[data-theme="light"] .wrapper {
    background: #f9fafb;
    color: #000000;
}

[data-theme="dark"] .wrapper {
    background: #020617;
    color: #ffffff;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .wrapper {
        padding: 20px;
    }

    .header {
        flex-direction: column;
        gap: 16px;
    }

    .back-btn {
        align-self: flex-start;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .info-cards {
        grid-template-columns: 1fr;
    }

    .actions {
        flex-direction: column-reverse;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
