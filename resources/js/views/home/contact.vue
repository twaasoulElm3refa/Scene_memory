<template>
  <div class="contact-page">
    <!-- Hero Section -->
    <section class="hero-section">
      <div class="container">
        <div class="row">
          <!-- Left Column - Form -->
          <div class="col-lg-6">
            <h1 class="page-title">{{ $t("contacts.title") }}</h1>
            <p class="page-description">{{ $t("contacts.description") }}</p>

            <form @submit.prevent="submitForm" class="contact-form">
              <!-- Full Name -->
              <div class="form-group">
                <label>{{ $t("contacts.form.fullName") }}</label>
                <input
                  v-model="form.fullName"
                  type="text"
                  class="form-control"
                  :placeholder="$t('contact.form.fullNamePlaceholder')"
                  required
                />
              </div>

              <!-- Email Address -->
              <div class="form-group">
                <label>{{ $t("contacts.form.emailAddress") }}</label>
                <input
                  v-model="form.email"
                  type="email"
                  class="form-control"
                  :placeholder="$t('contact.form.emailPlaceholder')"
                  required
                />
              </div>

              <!-- Subject -->
              <div class="form-group">
                <label>{{ $t("contacts.form.subject") }}</label>
                <select v-model="form.subject" class="form-control" required>
                  <option value="general">
                    {{ $t("contacts.form.generalInquiry") }}
                  </option>
                  <option value="support">{{ $t("contacts.form.support") }}</option>
                  <option value="feedback">{{ $t("contacts.form.feedback") }}</option>
                  <option value="other">{{ $t("contacts.form.other") }}</option>
                </select>
              </div>

              <!-- Message -->
              <div class="form-group">
                <label>{{ $t("contacts.form.message") }}</label>
                <textarea
                  v-model="form.message"
                  class="form-control message-textarea"
                  :placeholder="$t('contact.form.messagePlaceholder')"
                  rows="5"
                  required
                ></textarea>
              </div>

              <!-- Submit Button -->
              <button type="submit" class="btn-submit" :disabled="isSubmitting">
                <span v-if="isSubmitting">{{ $t("contacts.form.sending") }}</span>
                <span v-else>
                  {{ $t("contacts.form.sendMessage") }}
                  <span class="arrow">→</span>
                </span>
              </button>

              <!-- حالة الإرسال (نجاح أو خطأ) -->
              <div v-if="successMessage" class="alert alert-success mt-3">
                {{ successMessage }}
              </div>
              <div v-if="errorMessage" class="alert alert-danger mt-3">
                {{ errorMessage }}
              </div>
            </form>
          </div>

          <!-- Right Column - Contact Info -->
          <div class="col-lg-6">
            <div class="contact-info">
              <!-- Email Us -->
              <div class="info-card">
                <div class="icon-wrapper email-icon">
                  <i class="bi bi-envelope"></i>
                </div>
                <div class="info-content">
                  <h3>{{ $t("contacts.info.emailUs") }}</h3>
                  <p class="info-subtitle">{{ $t("contacts.info.available") }}</p>
                  <a href="mailto:hello@scenememory.com" class="info-link">
                    hello@scenememory.com
                  </a>
                </div>
              </div>

              <!-- Visit Our Studio -->
              <div class="info-card">
                <div class="icon-wrapper location-icon">
                  <i class="bi bi-geo-alt"></i>
                </div>
                <div class="info-content">
                  <h3>{{ $t("contacts.info.visitStudio") }}</h3>
                  <p class="info-address">{{ $t("contacts.info.address") }}</p>
                </div>
              </div>

              <!-- Follow the Journey -->
              <div class="info-card">
                <div class="icon-wrapper social-icon">
                  <i class="bi bi-globe"></i>
                </div>
                <div class="info-content">
                  <h3>{{ $t("contacts.info.followJourney") }}</h3>
                  <div class="social-links">
                    <a href="#" class="social-link" aria-label="Facebook">
                      <i class="bi bi-facebook"></i>
                    </a>
                    <a href="#" class="social-link" aria-label="Twitter">
                      <i class="bi bi-twitter-x"></i>
                    </a>
                    <a href="#" class="social-link" aria-label="Instagram">
                      <i class="bi bi-instagram"></i>
                    </a>
                  </div>
                </div>
              </div>

              <!-- Map Card -->
              <div class="map-card">
                <img src="/images/world-map.jpg" alt="World Map" class="map-image" />
                <div class="map-overlay">
                  <div class="map-icon">
                    <i class="bi bi-geo-alt-fill"></i>
                  </div>
                  <div class="map-content">
                    <div class="map-icon-wrapper">
                      <i class="bi bi-globe2"></i>
                    </div>
                    <h4>{{ $t("contacts.map.title") }}</h4>
                    <p>{{ $t("contacts.map.description") }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
      <div class="container">
        <h2 class="section-title">{{ $t("faq.title") }}</h2>

        <div class="faq-grid">
          <div v-for="(faq, index) in faqs" :key="index" class="faq-card">
            <h3 class="faq-question">{{ $t(`faq.questions.${index}.question`) }}</h3>
            <p class="faq-answer">{{ $t(`faq.questions.${index}.answer`) }}</p>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script>
import axios from "axios";

export default {
  name: "ContactUs",
  data() {
    return {
      form: {
        fullName: "",
        email: "",
        subject: "general",
        message: "",
      },
      faqs: [{ id: 0 }, { id: 1 }, { id: 2 }],
      isSubmitting: false,
      successMessage: "",
      errorMessage: "",
    };
  },
  methods: {
    async submitForm() {
      this.isSubmitting = true;
      this.successMessage = "";
      this.errorMessage = "";

      const payload = {
        name: this.form.fullName,
        email: this.form.email,
        subject: this.form.subject,
        message: this.form.message,
      };

      try {
        const response = await axios.post("v1/contacts/create", payload);

        if (response.status === 200 || response.status === 201) {
          this.successMessage =
            this.$t("contacts.form.successMessage") || "تم إرسال رسالتك بنجاح!";
          this.resetForm();
        }
      } catch (error) {
        console.error("Error submitting contact form:", error);

        this.errorMessage =
          error.response?.data?.message ||
          this.$t("contacts.form.errorMessage") ||
          "حدث خطأ أثناء الإرسال، حاول مرة أخرى لاحقًا";
      } finally {
        this.isSubmitting = false;
      }
    },

    resetForm() {
      this.form = {
        fullName: "",
        email: "",
        subject: "general",
        message: "",
      };
    },
  },
};
</script>

<style scoped>
.contact-page {
  background-color: #f8f9fa;
  min-height: 100vh;
}

.hero-section {
  padding: 80px 0;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

/* لو عايز تضيف أي ستايل إضافي للـ alerts أو الـ disabled state */
.alert {
  margin-top: 1rem;
  padding: 0.75rem;
  border-radius: 6px;
}

.alert-success {
  background-color: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.alert-danger {
  background-color: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.btn-submit:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.row {
  display: flex;
  gap: 60px;
  flex-wrap: wrap;
}

.col-lg-6 {
  flex: 1;
  min-width: 300px;
}

/* Left Column - Form */
.page-title {
  font-size: 48px;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 20px;
}

.page-description {
  font-size: 16px;
  color: #6b7280;
  margin-bottom: 40px;
  line-height: 1.6;
}

.contact-form {
  background: white;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.form-group {
  margin-bottom: 24px;
}

.form-group label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 8px;
}

.form-control {
  width: 100%;
  padding: 12px 16px;
  font-size: 15px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  transition: all 0.3s;
  font-family: inherit;
}

.form-control:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.message-textarea {
  resize: vertical;
  min-height: 120px;
}

.btn-submit {
  background: #3b82f6;
  color: white;
  border: none;
  padding: 14px 32px;
  font-size: 16px;
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s;
}

.btn-submit:hover {
  background: #2563eb;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.arrow {
  font-size: 18px;
  transition: transform 0.3s;
}

.btn-submit:hover .arrow {
  transform: translateX(4px);
}

/* Right Column - Contact Info */
.contact-info {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.info-card {
  background: white;
  padding: 24px;
  border-radius: 12px;
  display: flex;
  gap: 20px;
  align-items: flex-start;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 24px;
}

.email-icon {
  background: #dbeafe;
  color: #3b82f6;
}

.location-icon {
  background: #dbeafe;
  color: #3b82f6;
}

.social-icon {
  background: #dbeafe;
  color: #3b82f6;
}

.info-content h3 {
  font-size: 18px;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 8px;
}

.info-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 8px;
}

.info-link {
  color: #3b82f6;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.3s;
}

.info-link:hover {
  color: #2563eb;
}

.info-address {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.6;
}

.social-links {
  display: flex;
  gap: 12px;
  margin-top: 12px;
}

.social-link {
  width: 40px;
  height: 40px;
  background: #f3f4f6;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  transition: all 0.3s;
  text-decoration: none;
}

.social-link:hover {
  background: #3b82f6;
  color: white;
  transform: translateY(-2px);
}

/* Map Card */
.map-card {
  position: relative;
  border-radius: 12px;
  overflow: hidden;
  height: 250px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.map-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.map-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
  padding: 24px;
  color: white;
}

.map-icon {
  position: absolute;
  top: -80px;
  left: 50%;
  transform: translateX(-50%);
  width: 48px;
  height: 48px;
  background: #3b82f6;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.map-content {
  display: flex;
  align-items: center;
  gap: 12px;
}

.map-icon-wrapper {
  width: 32px;
  height: 32px;
  background: rgba(59, 130, 246, 0.2);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #3b82f6;
}

.map-content h4 {
  font-size: 16px;
  font-weight: 600;
  margin: 0 0 4px 0;
}

.map-content p {
  font-size: 13px;
  margin: 0;
  opacity: 0.9;
}

/* FAQ Section */
.faq-section {
  padding: 80px 0;
  background: white;
}

.section-title {
  text-align: center;
  font-size: 36px;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 60px;
}

.faq-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 30px;
}

.faq-card {
  background: #f8f9fa;
  padding: 32px;
  border-radius: 12px;
  transition: all 0.3s;
}

.faq-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.faq-question {
  font-size: 18px;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 12px;
}

.faq-answer {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.6;
  margin: 0;
}

/* RTL Support */
[dir="rtl"] .arrow {
  transform: rotate(180deg);
}

[dir="rtl"] .btn-submit:hover .arrow {
  transform: rotate(180deg) translateX(4px);
}

/* Responsive */
@media (max-width: 992px) {
  .row {
    flex-direction: column;
  }

  .page-title {
    font-size: 36px;
  }
}

@media (max-width: 768px) {
  .hero-section,
  .faq-section {
    padding: 40px 0;
  }

  .contact-form {
    padding: 24px;
  }

  .page-title {
    font-size: 32px;
  }

  .section-title {
    font-size: 28px;
  }

  .faq-grid {
    grid-template-columns: 1fr;
  }
}
</style>
