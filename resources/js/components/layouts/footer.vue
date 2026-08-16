<template>
  <footer class="footer-gradient py-5 mt-10">
    <div class="container">
      <div class="row">
        <!-- {{ $t('footer.quickLinks') }} -->
        <div class="col-md-4 mb-4 mb-md-0">
          <h5 class="fw-bold mb-3 text-glow">{{ $t('footer.quickLinks') }}</h5>
          <ul class="list-unstyled">
            <li v-for="link in quickLinks" :key="link.labelKey">
              <a :href="link.href" class="footer-link d-block mb-2">
                {{ $t(link.labelKey) }}
              </a>
            </li>
          </ul>
        </div>

        <!-- {{ $t('footer.supportContact') }} -->
        <div class="col-md-4 mb-4 mb-md-0">
          <h5 class="fw-bold mb-3 text-glow">{{ $t('footer.supportContact') }}</h5>
          <ul class="list-unstyled">
            <li v-for="link in supportLinks" :key="link.labelKey">
              <a :href="link.href" class="footer-link d-block mb-2">
                {{ $t(link.labelKey) }}
              </a>
            </li>
          </ul>
        </div>

        <!-- Logo & Socials -->
        <div class="col-md-4 text-center text-md-end">
          <!-- Logo -->
          <div class="mb-3">
            <img
              src="/images/logo.png"
              :alt="$t('footer.logoAlt')"
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
                :alt="$t('footer.googlePlayAlt')"
                class="store-badge"
              />
            </a>
            <a href="#" target="_blank">
              <img
                src="https://developer.apple.com/assets/elements/badges/download-on-the-app-store.svg"
                :alt="$t('footer.appStoreAlt')"
                class="store-badge"
              />
            </a>
          </div>

          <!-- Copyright -->
          <p class="small mb-0 copyright-text">
{{ $t('footer.copyright2025') }}
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
        { labelKey: "nav.home", href: "/" },
        { labelKey: "footer.services", href: "#" },
      ],

      supportLinks: [
        { labelKey: "nav.about", href: "/who" },
        { labelKey: "nav.contact", href: "/contact" },
        { labelKey: "footer.copyrightLink", href: "#" },
        { labelKey: "footer.termsOfService", href: "/terms" },
        { labelKey: "footer.privacyPolicy", href: "/privacy-policy" },
        { labelKey: "footer.dataProtection", href: "/data-protection" },
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
.footer-gradient {
  position: relative;
  overflow: hidden;
  margin-top: 0;
  border-top: 1px solid var(--scemory-border, #C9DDEF);
  background:
    radial-gradient(circle at 85% 20%, rgba(48, 168, 255, 0.08), transparent 30%),
    linear-gradient(145deg, var(--scemory-surface-soft, #EDF4FA), var(--scemory-surface, #F7FAFD));
  color: var(--scemory-text, #334A62);
}

.footer-gradient::before {
  content: "";
  position: absolute;
  inset-inline: 0;
  top: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(22, 119, 255, 0.26), transparent);
}

.footer-gradient .container {
  position: relative;
  z-index: 1;
}

.text-glow {
  position: relative;
  display: inline-flex;
  align-items: center;
  color: var(--scemory-heading, #06142A);
  font-size: 1rem;
  letter-spacing: 0;
  -webkit-text-fill-color: currentColor;
  filter: none;
}

.text-glow::after {
  content: "";
  position: absolute;
  inset-inline-start: 0;
  bottom: -8px;
  width: 34px;
  height: 2px;
  border-radius: 999px;
  background: linear-gradient(90deg, var(--scemory-blue, #1677FF), var(--scemory-bright-blue, #30A8FF));
}

.footer-link {
  width: fit-content;
  color: var(--scemory-text, #334A62);
  font-weight: 500;
  text-decoration: none;
  transition: color 0.22s ease, background 0.22s ease, transform 0.22s ease;
}

.footer-link:hover {
  color: var(--scemory-primary, #0D4D97);
  transform: translateX(2px);
}

[dir="rtl"] .footer-link:hover,
:global([dir="rtl"]) .footer-link:hover {
  transform: translateX(-2px);
}

.social-btn {
  width: 38px;
  height: 38px;
  border: 1px solid var(--scemory-border, #C9DDEF);
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--scemory-control, #F3F7FC);
  color: var(--scemory-primary, #0D4D97);
  transition: color 0.22s ease, background 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease, transform 0.22s ease;
}

.social-btn svg {
  width: 19px;
  height: 19px;
  fill: currentColor;
}

.social-btn:hover {
  transform: translateY(-1px);
  border-color: rgba(22, 119, 255, 0.24);
  background: var(--scemory-active, #DDECF9);
  color: var(--scemory-blue, #1677FF);
  box-shadow: 0 5px 14px rgba(13, 77, 151, 0.08);
}

.logo-glow {
  max-width: 180px;
  border: 1px solid var(--scemory-border-soft, #DCE8F5);
  background: var(--scemory-surface, #F7FAFD);
  box-shadow: 0 10px 30px rgba(13, 77, 151, 0.07);
  filter: none;
  transition: box-shadow 0.22s ease, transform 0.22s ease;
}

.logo-glow:hover {
  transform: translateY(-1px);
  box-shadow: 0 14px 34px rgba(13, 77, 151, 0.10);
}

.store-badge {
  height: 43px;
  width: auto;
  max-width: 150px;
  border-radius: 12px;
  transition: transform 0.22s ease, filter 0.22s ease, box-shadow 0.22s ease;
}

.store-badge:hover {
  transform: translateY(-1px);
  box-shadow: 0 5px 14px rgba(13, 77, 151, 0.08);
}

.copyright-text {
  display: inline-flex;
  border-top: 1px solid var(--scemory-border-soft, #DCE8F5);
  padding-top: 14px;
  color: var(--scemory-muted, #64748B);
  transition: color 0.22s ease;
}

@media (max-width: 767px) {
  .footer-gradient {
    text-align: center;
  }

  .text-glow {
    justify-content: center;
  }

  .text-glow::after {
    inset-inline-start: 50%;
    transform: translateX(-50%);
  }

  .footer-link {
    margin-inline: auto;
  }

  .logo-glow {
    width: min(54%, 170px) !important;
  }
}
</style>
