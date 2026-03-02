<template>
  <footer class="footer-gradient py-5 mt-10">
    <div class="container">
      <div class="row">
        <!-- Quick Links -->
        <div class="col-md-4 mb-4 mb-md-0">
          <h5 class="fw-bold mb-3 text-glow">Quick Links</h5>
          <ul class="list-unstyled">
            <li v-for="link in quickLinks" :key="link.label">
              <a :href="link.href" class="footer-link d-block mb-2">
                {{ link.label }}
              </a>
            </li>
          </ul>
        </div>

        <!-- Support & Contact -->
        <div class="col-md-4 mb-4 mb-md-0">
          <h5 class="fw-bold mb-3 text-glow">Support & Contact</h5>
          <ul class="list-unstyled">
            <li v-for="link in supportLinks" :key="link.label">
              <a :href="link.href" class="footer-link d-block mb-2">
                {{ link.label }}
              </a>
            </li>
          </ul>
        </div>

        <!-- Logo & Socials -->
        <div class="col-md-4 text-center text-md-end">
          <!-- Logo -->
          <div class="mb-3">
            <img
              src="/images/event_logo.png"
              alt="Logo"
              class="rounded-circle logo-glow p-1 w-50"
            />
          </div>

          <!-- Social Icons -->
          <div class="mb-3">
            <a
              v-for="social in socials"
              :key="social.name"
              :href="social.href"
              class="social-btn me-2"
              v-html="social.icon"
            ></a>
          </div>

          <!-- Store Badges -->
          <div
            class="mb-3 d-flex justify-content-center justify-content-md-end gap-2 flex-wrap"
          >
            <a href="#" target="_blank">
              <img
                src="https://upload.wikimedia.org/wikipedia/commons/7/78/Google_Play_Store_badge_EN.svg"
                alt="Get it on Google Play"
                class="store-badge"
              />
            </a>
            <a href="#" target="_blank">
              <img
                src="https://developer.apple.com/assets/elements/badges/download-on-the-app-store.svg"
                alt="Download on the App Store"
                class="store-badge"
              />
            </a>
          </div>

          <!-- Copyright -->
          <p class="small mb-0 copyright-text">
            © 2025 Scene Memory — جميع الحقوق محفوظة
          </p>
        </div>
      </div>
    </div>
  </footer>
</template>

<script>
export default {
  name: "Footer",

  data() {
    return {
      quickLinks: [
        { label: "Home", href: "/" },
        { label: "Services", href: "#" },
      ],

      supportLinks: [
        { label: "Who Are We?", href: "/who" },
        { label: "Contact Us", href: "/contact" },
        { label: "Copyright", href: "#" },
        { label: "Terms of Services", href: "/terms" },
        { label: "Privacy Policy", href: "/privacy-policy" },
        { label: "Data Protection", href: "/data-protection" },
      ],

      socials: [
        {
          name: "facebook",
          href: "#",
          icon: `<svg viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.879V14.89h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.989C18.343 21.129 22 16.99 22 12z"/></svg>`,
        },
        {
          name: "x",
          href: "#",
          icon: `<svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.92 21.75H1.61l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>`,
        },
        {
          name: "instagram",
          href: "#",
          icon: `<svg viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069z"/></svg>`,
        },
      ],
    };
  },
};
</script>

<style scoped>
/* ===== Footer Base ===== */
.footer-gradient {
  border-top: 1px solid rgba(212, 175, 55, 0.12);
  transition: background 0.4s ease, border-color 0.4s ease;
}

/* Dark theme (default) */
[data-theme="dark"] .footer-gradient {
  background: linear-gradient(185deg, #0f172a, #1e293b, #111827, #1e293b);
  border-top-color: rgba(212, 175, 55, 0.12);
}

/* Light theme */
[data-theme="light"] .footer-gradient {
  background: linear-gradient(185deg, #f8fafc, #e2e8f0, #f1f5f9, #e2e8f0);
  border-top: 1px solid #cbd5e1;
}

/* ===== Headings & Glow ===== */
.text-glow {
  background: linear-gradient(90deg, #d4af37, #eab308, #fbbf24);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  filter: drop-shadow(0 0 8px rgba(212, 175, 55, 0.55));
  transition: all 0.3s ease;
}

[data-theme="light"] .text-glow {
  background: none;
  -webkit-background-clip: unset;
  -webkit-text-fill-color: #111827;
  filter: none;
  color: #111827;
}

/* ===== Links ===== */
.footer-link {
  color: var(--color-text-muted, #94a3b8);
  text-decoration: none;
  transition: all 0.3s ease;
}

[data-theme="dark"] .footer-link {
  color: #94a3b8;
}

[data-theme="light"] .footer-link {
  color: #4b5563;
}

.footer-link:hover {
  transform: translateX(4px);
}

[data-theme="dark"] .footer-link:hover {
  color: #d4af37;
}

[data-theme="light"] .footer-link:hover {
  color: #111827;
  font-weight: 500;
}

/* ===== Social Buttons ===== */
.social-btn {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.35s ease;
  border: 1px solid;
}

[data-theme="dark"] .social-btn {
  background: rgba(30, 41, 59, 0.6);
  border-color: #475569;
  color: #cbd5e1;
}

[data-theme="light"] .social-btn {
  background: #f1f5f9;
  border-color: #cbd5e1;
  color: #374151;
}

.social-btn svg {
  width: 20px;
  height: 20px;
  fill: currentColor;
}

.social-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.18);
}

[data-theme="dark"] .social-btn:hover {
  background: linear-gradient(90deg, #d4af37, #eab308);
  border-color: #d4af37;
  color: #0f172a;
  box-shadow: 0 6px 20px rgba(212, 175, 55, 0.35);
}

[data-theme="light"] .social-btn:hover {
  background: #111827;
  border-color: #111827;
  color: white;
}

/* ===== Logo Glow ===== */
.logo-glow {
  filter: drop-shadow(0 0 14px rgba(212, 175, 55, 0.65));
  transition: filter 0.4s ease;
}

[data-theme="light"] .logo-glow {
  filter: none;
}

/* ===== Store Badges ===== */
.store-badge {
  height: 45px;
  width: auto;
  transition: transform 0.3s ease, filter 0.3s ease;
}

.store-badge:hover {
  transform: translateY(-2px);
}

/* Light mode – خلي البادجات أبيض */
[data-theme="light"] .store-badge {
  filter: invert(100%) brightness(100%);
}

/* ===== Copyright ===== */
.copyright-text {
  transition: color 0.4s ease;
}

[data-theme="dark"] .copyright-text {
  color: #64748b;
}

[data-theme="light"] .copyright-text {
  color: #6b7280;
}
</style>
