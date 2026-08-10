<template>
  <div class="scemory-page contact-page">
    <!-- Main Contact Section -->
    <section class="contact-section">
      <div class="container">
        <!-- Page Header -->
        <div class="page-header">
          <span class="page-badge">
            {{ $t("contacts.badge") || "Contact us" }}
          </span>

          <h1 class="page-title">
            {{ $t("contacts.title") || "Let’s talk" }}
          </h1>

          <p class="page-description">
            {{
              $t("contacts.description") ||
              "Have a question, need support, or want a custom plan? Send us a message and our team will get back to you."
            }}
          </p>
        </div>

        <div class="contact-layout">
          <!-- Contact Form -->
          <div class="form-column">
            <div class="contact-form-card">
              <div class="card-header">
                <div>
                  <h2 class="card-title">
                    {{ $t("contacts.form.title") || "Send us a message" }}
                  </h2>

                  <p class="card-description">
                    {{
                      $t("contacts.form.description") ||
                      "Complete the form below and we will respond as soon as possible."
                    }}
                  </p>
                </div>

                <div class="header-icon" aria-hidden="true">
                  <i class="bi bi-send"></i>
                </div>
              </div>

              <form class="contact-form" @submit.prevent="submitForm">
                <!-- Full Name -->
                <div class="form-group">
                  <label for="full-name">
                    {{ $t("contacts.form.fullName") || "Full name" }}
                  </label>

                  <div class="input-wrapper">
                    <i class="bi bi-person input-icon" aria-hidden="true"></i>

                    <input
                      id="full-name"
                      v-model.trim="form.fullName"
                      type="text"
                      class="form-control"
                      :placeholder="
                        $t('contacts.form.fullNamePlaceholder') ||
                        'Enter your full name'
                      "
                      autocomplete="name"
                      required
                    />
                  </div>
                </div>

                <!-- Email -->
                <div class="form-group">
                  <label for="email">
                    {{ $t("contacts.form.emailAddress") || "Email address" }}
                  </label>

                  <div class="input-wrapper">
                    <i class="bi bi-envelope input-icon" aria-hidden="true"></i>

                    <input
                      id="email"
                      v-model.trim="form.email"
                      type="email"
                      class="form-control"
                      :placeholder="
                        $t('contacts.form.emailPlaceholder') ||
                        'Enter your email address'
                      "
                      autocomplete="email"
                      required
                    />
                  </div>
                </div>

                <!-- Subject -->
                <div class="form-group">
                  <label for="subject">
                    {{ $t("contacts.form.subject") || "Subject" }}
                  </label>

                  <div class="input-wrapper">
                    <i class="bi bi-chat-square-text input-icon" aria-hidden="true"></i>

                    <select
                      id="subject"
                      v-model="form.subject"
                      class="form-control form-select"
                      required
                    >
                      <option value="general">
                        {{
                          $t("contacts.form.generalInquiry") ||
                          "General inquiry"
                        }}
                      </option>

                      <option value="custom-plan">
                        {{
                          $t("contacts.form.customPlan") ||
                          "Custom plan"
                        }}
                      </option>

                      <option value="support">
                        {{ $t("contacts.form.support") || "Technical support" }}
                      </option>

                      <option value="feedback">
                        {{ $t("contacts.form.feedback") || "Feedback" }}
                      </option>

                      <option value="other">
                        {{ $t("contacts.form.other") || "Other" }}
                      </option>
                    </select>

                    <i
                      class="bi bi-chevron-down select-arrow"
                      aria-hidden="true"
                    ></i>
                  </div>
                </div>

                <!-- Message -->
                <div class="form-group">
                  <label for="message">
                    {{ $t("contacts.form.message") || "Message" }}
                  </label>

                  <textarea
                    id="message"
                    v-model.trim="form.message"
                    class="form-control message-textarea"
                    :placeholder="
                      $t('contacts.form.messagePlaceholder') ||
                      'Tell us how we can help you...'
                    "
                    rows="6"
                    required
                  ></textarea>

                  <div class="message-meta">
                    <span>
                      {{
                        $t("contacts.form.minimumCharacters") ||
                        "Please provide enough details."
                      }}
                    </span>

                    <span>{{ form.message.length }}/2000</span>
                  </div>
                </div>

                <!-- Submit Button -->
                <button
                  type="submit"
                  class="btn-submit"
                  :disabled="isSubmitting || !isFormValid"
                >
                  <span v-if="isSubmitting" class="button-content">
                    <span class="button-spinner" aria-hidden="true"></span>

                    {{ $t("contacts.form.sending") || "Sending..." }}
                  </span>

                  <span v-else class="button-content">
                    {{ $t("contacts.form.sendMessage") || "Send message" }}

                    <i
                      class="bi bi-arrow-right button-arrow"
                      aria-hidden="true"
                    ></i>
                  </span>
                </button>

                <!-- Success Message -->
                <div
                  v-if="successMessage"
                  class="form-alert alert-success"
                  role="status"
                >
                  <span class="alert-icon">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                  </span>

                  <span>{{ successMessage }}</span>
                </div>

                <!-- Error Message -->
                <div
                  v-if="errorMessage"
                  class="form-alert alert-danger"
                  role="alert"
                >
                  <span class="alert-icon">
                    <i class="bi bi-exclamation-lg" aria-hidden="true"></i>
                  </span>

                  <span>{{ errorMessage }}</span>
                </div>
              </form>
            </div>
          </div>

          <!-- Contact Information -->
          <aside class="info-column">
            <div class="contact-info-card">
              <div class="info-header">
                <span class="info-eyebrow">
                  {{ $t("contacts.info.getInTouch") || "Get in touch" }}
                </span>

                <h2>
                  {{
                    $t("contacts.info.title") ||
                    "We’re here to help"
                  }}
                </h2>

                <p>
                  {{
                    $t("contacts.info.description") ||
                    "Reach our team for general questions, technical support, or custom pricing."
                  }}
                </p>
              </div>

              <div class="info-list">
                <!-- Email -->
                <div class="info-item">
                  <div class="info-icon">
                    <i class="bi bi-envelope" aria-hidden="true"></i>
                  </div>

                  <div class="info-content">
                    <h3>
                      {{ $t("contacts.info.emailUs") || "Email us" }}
                    </h3>

                    <p>
                      {{
                        $t("contacts.info.available") ||
                        "Our team will reply as soon as possible."
                      }}
                    </p>

                    <a
                      href="mailto:scemorygmail@gmail.com"
                      class="info-link"
                    >
                      scemorygmail@gmail.com
                    </a>
                  </div>
                </div>

                <!-- Custom Plans -->
                <div class="info-item">
                  <div class="info-icon">
                    <i class="bi bi-sliders" aria-hidden="true"></i>
                  </div>

                  <div class="info-content">
                    <h3>
                      {{
                        $t("contacts.info.customPlans") ||
                        "Custom plans"
                      }}
                    </h3>

                    <p>
                      {{
                        $t("contacts.info.customPlansDescription") ||
                        "Need a tailored solution for your team or business? Tell us what you need."
                      }}
                    </p>
                  </div>
                </div>

                <!-- Support -->
                <div class="info-item">
                  <div class="info-icon">
                    <i class="bi bi-headset" aria-hidden="true"></i>
                  </div>

                  <div class="info-content">
                    <h3>
                      {{
                        $t("contacts.info.customerSupport") ||
                        "Customer support"
                      }}
                    </h3>

                    <p>
                      {{
                        $t("contacts.info.supportDescription") ||
                        "Contact us if you need help with your account, subscription, or services."
                      }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Social Links -->
              <div class="social-section">
                <h3>
                  {{
                    $t("contacts.info.followJourney") ||
                    "Follow our journey"
                  }}
                </h3>

                <div class="social-links">
                  <a
                    href="#"
                    class="social-link"
                    aria-label="Facebook"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <i class="bi bi-facebook" aria-hidden="true"></i>
                  </a>

                  <a
                    href="#"
                    class="social-link"
                    aria-label="X"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <i class="bi bi-twitter-x" aria-hidden="true"></i>
                  </a>

                  <a
                    href="#"
                    class="social-link"
                    aria-label="Instagram"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <i class="bi bi-instagram" aria-hidden="true"></i>
                  </a>
                </div>
              </div>
            </div>

            <!-- Response Notice -->
            <div class="response-card">
              <div class="response-icon">
                <i class="bi bi-clock" aria-hidden="true"></i>
              </div>

              <div>
                <h3>
                  {{
                    $t("contacts.info.responseTime") ||
                    "Fast response"
                  }}
                </h3>

                <p>
                  {{
                    $t("contacts.info.responseTimeDescription") ||
                    "We usually respond to messages within one business day."
                  }}
                </p>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
      <div class="container">
        <div class="faq-header">
          <span class="page-badge">
            {{ $t("faq.badge") || "FAQ" }}
          </span>

          <h2 class="section-title">
            {{ $t("faq.title") || "Frequently asked questions" }}
          </h2>

          <p class="section-description">
            {{
              $t("faq.description") ||
              "Quick answers to some of the most common questions."
            }}
          </p>
        </div>

        <div class="faq-grid">
          <article
            v-for="(faq, index) in faqs"
            :key="faq.id"
            class="faq-card"
          >
            <div class="faq-number">
              {{ String(index + 1).padStart(2, "0") }}
            </div>

            <h3 class="faq-question">
              {{
                $t(`faq.questions.${index}.question`) ||
                faq.fallbackQuestion
              }}
            </h3>

            <p class="faq-answer">
              {{
                $t(`faq.questions.${index}.answer`) ||
                faq.fallbackAnswer
              }}
            </p>
          </article>
        </div>
      </div>
    </section>
  </div>
</template>

<script>
import { ContactService } from "../../services/ContactService/ContactService";

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

      faqs: [
        {
          id: 1,
          fallbackQuestion: "How quickly will I receive a response?",
          fallbackAnswer:
            "Our team usually responds within one business day.",
        },
        {
          id: 2,
          fallbackQuestion: "Can I request a custom plan?",
          fallbackAnswer:
            "Yes. Choose Custom Plan in the subject field and describe your requirements.",
        },
        {
          id: 3,
          fallbackQuestion: "Can I contact you for technical support?",
          fallbackAnswer:
            "Yes. Select Technical Support and include all relevant details about the issue.",
        },
      ],

      isSubmitting: false,
      successMessage: "",
      errorMessage: "",
    };
  },

  computed: {
    isFormValid() {
      return (
        this.form.fullName.trim().length >= 2 &&
        this.isValidEmail(this.form.email) &&
        this.form.subject &&
        this.form.message.trim().length >= 5 &&
        this.form.message.length <= 2000
      );
    },
  },

  methods: {
    isValidEmail(email) {
      const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailPattern.test(email);
    },

    async submitForm() {
      if (!this.isFormValid || this.isSubmitting) {
        return;
      }

      this.isSubmitting = true;
      this.successMessage = "";
      this.errorMessage = "";

      const payload = {
        name: this.form.fullName.trim(),
        email: this.form.email.trim(),
        subject: this.form.subject,
        message: this.form.message.trim(),
      };

      try {
        const response = await ContactService.create(payload);

        if (response?.status === 200 || response?.status === 201) {
          this.successMessage =
            this.$t("contacts.form.successMessage") ||
            "Your message has been sent successfully.";

          this.resetForm();
          return;
        }

        throw new Error("Unexpected response from the server.");
      } catch (error) {
        console.error("Error submitting contact form:", error);

        this.errorMessage =
          error?.response?.data?.message ||
          this.$t("contacts.form.errorMessage") ||
          "Something went wrong while sending your message. Please try again.";
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
* {
  box-sizing: border-box;
}

.contact-page {
  min-height: 100vh;
  background:
    radial-gradient(
      circle at top left,
      rgba(59, 130, 246, 0.08),
      transparent 30%
    ),
    #f8fafc;
  color: #111827;
}

.container {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

/* Main Section */

.contact-section {
  padding: 88px 0 96px;
}

.page-header {
  max-width: 720px;
  margin: 0 auto 56px;
  text-align: center;
}

.page-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 34px;
  padding: 7px 15px;
  margin-bottom: 18px;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  background: #eff6ff;
  color: #2563eb;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.page-title {
  margin: 0 0 16px;
  color: #0f172a;
  font-size: clamp(38px, 5vw, 58px);
  font-weight: 800;
  line-height: 1.08;
  letter-spacing: -0.04em;
}

.page-description {
  max-width: 640px;
  margin: 0 auto;
  color: #64748b;
  font-size: 17px;
  line-height: 1.8;
}

.contact-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(340px, 0.75fr);
  gap: 32px;
  align-items: start;
}

.form-column,
.info-column {
  min-width: 0;
}

/* Form Card */

.contact-form-card,
.contact-info-card {
  border: 1px solid #e2e8f0;
  border-radius: 24px;
  background: #ffffff;
  box-shadow:
    0 20px 50px rgba(15, 23, 42, 0.06),
    0 4px 12px rgba(15, 23, 42, 0.03);
}

.contact-form-card {
  padding: 36px;
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 32px;
}

.card-title {
  margin: 0 0 8px;
  color: #0f172a;
  font-size: 25px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.card-description {
  margin: 0;
  color: #64748b;
  font-size: 14px;
  line-height: 1.7;
}

.header-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  flex: 0 0 52px;
  border-radius: 16px;
  background: #eff6ff;
  color: #2563eb;
  font-size: 22px;
}

/* Form */

.contact-form {
  width: 100%;
}

.form-group {
  margin-bottom: 22px;
}

.form-group label {
  display: block;
  margin-bottom: 9px;
  color: #1e293b;
  font-size: 14px;
  font-weight: 700;
}

.input-wrapper {
  position: relative;
}

.input-icon {
  position: absolute;
  top: 50%;
  left: 16px;
  z-index: 1;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: 17px;
  pointer-events: none;
}

.form-control {
  width: 100%;
  min-height: 52px;
  padding: 13px 16px;
  border: 1px solid #dbe3ed;
  border-radius: 13px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
  font-family: inherit;
  font-size: 15px;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    background-color 0.2s ease;
}

.input-wrapper .form-control:not(.message-textarea) {
  padding-inline-start: 46px;
}

.form-control::placeholder {
  color: #94a3b8;
}

.form-control:hover {
  border-color: #b8c5d5;
}

.form-control:focus {
  border-color: #3b82f6;
  background: #ffffff;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.11);
}

.form-select {
  appearance: none;
  padding-inline-end: 44px;
  cursor: pointer;
}

.select-arrow {
  position: absolute;
  top: 50%;
  right: 16px;
  transform: translateY(-50%);
  color: #64748b;
  font-size: 13px;
  pointer-events: none;
}

.message-textarea {
  min-height: 148px;
  resize: vertical;
  line-height: 1.7;
}

.message-meta {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  margin-top: 8px;
  color: #94a3b8;
  font-size: 12px;
}

/* Button */

.btn-submit {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 54px;
  margin-top: 4px;
  padding: 14px 24px;
  border: 0;
  border-radius: 14px;
  background: #2563eb;
  color: #ffffff;
  cursor: pointer;
  font-family: inherit;
  font-size: 15px;
  font-weight: 700;
  box-shadow: 0 10px 25px rgba(37, 99, 235, 0.22);
  transition:
    transform 0.2s ease,
    background-color 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease;
}

.btn-submit:hover:not(:disabled) {
  transform: translateY(-2px);
  background: #1d4ed8;
  box-shadow: 0 14px 30px rgba(37, 99, 235, 0.3);
}

.btn-submit:active:not(:disabled) {
  transform: translateY(0);
}

.btn-submit:disabled {
  cursor: not-allowed;
  opacity: 0.55;
  box-shadow: none;
}

.button-content {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.button-arrow {
  font-size: 17px;
  transition: transform 0.2s ease;
}

.btn-submit:hover:not(:disabled) .button-arrow {
  transform: translateX(4px);
}

.button-spinner {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.75s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Alerts */

.form-alert {
  display: flex;
  align-items: flex-start;
  gap: 11px;
  margin-top: 18px;
  padding: 14px 16px;
  border: 1px solid;
  border-radius: 13px;
  font-size: 14px;
  line-height: 1.6;
}

.alert-success {
  border-color: #bbf7d0;
  background: #f0fdf4;
  color: #166534;
}

.alert-danger {
  border-color: #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.alert-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  flex: 0 0 22px;
  margin-top: 1px;
  border-radius: 50%;
  background: currentColor;
  color: #ffffff;
  font-size: 12px;
}

/* Contact Information */

.info-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.contact-info-card {
  overflow: hidden;
}

.info-header {
  padding: 32px;
  border-bottom: 1px solid #e2e8f0;
  background: linear-gradient(145deg, #0f172a, #1e3a8a);
  color: #ffffff;
}

.info-eyebrow {
  display: block;
  margin-bottom: 12px;
  color: #bfdbfe;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.09em;
  text-transform: uppercase;
}

.info-header h2 {
  margin: 0 0 12px;
  font-size: 27px;
  font-weight: 800;
  letter-spacing: -0.025em;
}

.info-header p {
  margin: 0;
  color: rgba(255, 255, 255, 0.75);
  font-size: 14px;
  line-height: 1.75;
}

.info-list {
  padding: 10px 28px;
}

.info-item {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 22px 0;
  border-bottom: 1px solid #edf2f7;
}

.info-item:last-child {
  border-bottom: 0;
}

.info-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 46px;
  height: 46px;
  flex: 0 0 46px;
  border-radius: 14px;
  background: #eff6ff;
  color: #2563eb;
  font-size: 20px;
}

.info-content {
  min-width: 0;
}

.info-content h3 {
  margin: 0 0 6px;
  color: #0f172a;
  font-size: 16px;
  font-weight: 800;
}

.info-content p {
  margin: 0;
  color: #64748b;
  font-size: 13px;
  line-height: 1.7;
}

.info-link {
  display: inline-block;
  margin-top: 8px;
  color: #2563eb;
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
  word-break: break-word;
  transition: color 0.2s ease;
}

.info-link:hover {
  color: #1d4ed8;
  text-decoration: underline;
}

.social-section {
  padding: 24px 28px 28px;
  border-top: 1px solid #edf2f7;
  background: #f8fafc;
}

.social-section h3 {
  margin: 0 0 14px;
  color: #334155;
  font-size: 14px;
  font-weight: 800;
}

.social-links {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.social-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border: 1px solid #dbe3ed;
  border-radius: 12px;
  background: #ffffff;
  color: #475569;
  font-size: 17px;
  text-decoration: none;
  transition:
    transform 0.2s ease,
    border-color 0.2s ease,
    background-color 0.2s ease,
    color 0.2s ease;
}

.social-link:hover {
  transform: translateY(-3px);
  border-color: #2563eb;
  background: #2563eb;
  color: #ffffff;
}

.response-card {
  display: flex;
  align-items: flex-start;
  gap: 15px;
  padding: 22px;
  border: 1px solid #bfdbfe;
  border-radius: 20px;
  background: #eff6ff;
}

.response-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  border-radius: 13px;
  background: #ffffff;
  color: #2563eb;
  font-size: 18px;
}

.response-card h3 {
  margin: 0 0 5px;
  color: #1e3a8a;
  font-size: 15px;
  font-weight: 800;
}

.response-card p {
  margin: 0;
  color: #475569;
  font-size: 13px;
  line-height: 1.65;
}

/* FAQ */

.faq-section {
  padding: 88px 0;
  border-top: 1px solid #e2e8f0;
  background: #ffffff;
}

.faq-header {
  max-width: 680px;
  margin: 0 auto 46px;
  text-align: center;
}

.section-title {
  margin: 0 0 13px;
  color: #0f172a;
  font-size: clamp(30px, 4vw, 42px);
  font-weight: 800;
  letter-spacing: -0.035em;
}

.section-description {
  margin: 0;
  color: #64748b;
  font-size: 16px;
  line-height: 1.7;
}

.faq-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 22px;
}

.faq-card {
  position: relative;
  min-width: 0;
  padding: 30px;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  background: #f8fafc;
  transition:
    transform 0.25s ease,
    border-color 0.25s ease,
    box-shadow 0.25s ease;
}

.faq-card:hover {
  transform: translateY(-5px);
  border-color: #bfdbfe;
  box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
}

.faq-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 42px;
  height: 30px;
  margin-bottom: 20px;
  padding: 0 10px;
  border-radius: 999px;
  background: #dbeafe;
  color: #2563eb;
  font-size: 12px;
  font-weight: 800;
}

.faq-question {
  margin: 0 0 12px;
  color: #0f172a;
  font-size: 18px;
  font-weight: 800;
  line-height: 1.45;
}

.faq-answer {
  margin: 0;
  color: #64748b;
  font-size: 14px;
  line-height: 1.8;
}

/* RTL */

[dir="rtl"] .input-icon {
  right: 16px;
  left: auto;
}

[dir="rtl"] .select-arrow {
  right: auto;
  left: 16px;
}

[dir="rtl"] .button-arrow {
  transform: rotate(180deg);
}

[dir="rtl"] .btn-submit:hover:not(:disabled) .button-arrow {
  transform: rotate(180deg) translateX(4px);
}

/* Responsive */

@media (max-width: 992px) {
  .contact-section {
    padding: 70px 0 76px;
  }

  .contact-layout {
    grid-template-columns: 1fr;
  }

  .info-column {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 0.55fr);
    align-items: start;
  }

  .faq-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .container {
    padding: 0 18px;
  }

  .contact-section {
    padding: 50px 0 58px;
  }

  .page-header {
    margin-bottom: 36px;
  }

  .page-description {
    font-size: 15px;
  }

  .contact-form-card {
    padding: 24px;
    border-radius: 20px;
  }

  .card-header {
    margin-bottom: 26px;
  }

  .header-icon {
    width: 46px;
    height: 46px;
    flex-basis: 46px;
  }

  .info-column {
    display: flex;
  }

  .faq-section {
    padding: 58px 0;
  }

  .faq-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .container {
    padding: 0 14px;
  }

  .contact-form-card {
    padding: 20px 16px;
  }

  .card-header {
    gap: 12px;
  }

  .card-title {
    font-size: 21px;
  }

  .header-icon {
    width: 42px;
    height: 42px;
    flex-basis: 42px;
    border-radius: 13px;
    font-size: 18px;
  }

  .message-meta {
    flex-direction: column;
    gap: 3px;
  }

  .info-header {
    padding: 26px 22px;
  }

  .info-list {
    padding: 8px 20px;
  }

  .social-section {
    padding: 22px 20px;
  }

  .faq-card {
    padding: 24px 20px;
  }
}

.contact-page {
  background:
    radial-gradient(circle at top left, rgba(48, 168, 255, 0.10), transparent 30rem),
    linear-gradient(180deg, #FFFFFF, #F8FAFC);
}

.page-badge {
  border-color: #CFE2F6;
  background: #EAF4FF;
  color: #0D4D97;
}

.page-title,
.card-title,
.info-title {
  color: #06142A;
}

.contact-form-card,
.contact-info-card,
.info-card,
.faq-card {
  border-color: #E5EDF6;
  box-shadow: 0 10px 35px rgba(13, 77, 151, 0.06);
}

.submit-button {
  background: linear-gradient(135deg, #0D4D97, #1677FF);
  box-shadow: 0 14px 30px rgba(22, 119, 255, 0.18);
}

.form-control:focus,
.form-input:focus,
.form-textarea:focus {
  border-color: #1677FF;
  box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.10);
}
</style>
