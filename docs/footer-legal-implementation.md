# Scemory footer and legal policies — implementation report

Review date: 20 October 2026. Publication status: **legal drafts awaiting approval**. No deployment, schema change, company identity, jurisdiction or effective date was introduced.

## Implemented behavior

The existing footer now has three navigation areas plus the existing brand area, arranged in four desktop columns, two tablet columns and a mobile stack. It uses Scemory’s existing logo, pearl/blue CSS variables and Bootstrap icons. Audience descriptions are informational rather than links to invented audience pages.

Navbar and footer share one navigation configuration and the Navbar’s verified profile state. Account destinations require a verified logged-in state; the creator dashboard retains the existing positive event-count condition. Logout remains a Navbar action. Contact, About and Terms are placed in Support to avoid duplicate footer destinations. Special Coverage preserves the existing authentication redirect and current home-page query. Internal links preserve the active locale.

Social and store destinations come from the existing administrator-managed footer record through a new public, field-restricted read endpoint. HTTPS, expected hosts and nonempty destination paths are checked; Google Play and App Store also require an app-detail destination. Invalid/missing values, including the repository’s seed placeholders, appear as unavailable and are not links. This validates URL structure, not ownership of the destination account. No real social profile or app-store listing was supplied or invented. The official logo asset is unchanged. Copyright year is calculated at runtime.

The existing contact submission route was incorrectly restricted to administrators. It now accepts public submissions with a five-per-minute limit and bounded field lengths. Copyright, privacy and legal inquiry subjects use the same persisted inbox. Inbox reading, replies, deletion and settings updates retain authentication and administrator restrictions. No statutory-agent workflow, attachments, automatic email acknowledgment, automatic takedown or appeal-deadline automation was added.

## Policy routes

Every path below is prefixed with `/{lang}`. Existing Terms, Privacy and Data Protection URLs and components remain in place; their presentation now delegates to the shared policy component.

| Page | Path |
| --- | --- |
| Legal overview | `/policies` |
| Copyright Policy | `/copyright-policy` |
| Terms of Service | `/terms` |
| Privacy Policy | `/privacy-policy` |
| Data Protection | `/data-protection` |
| Cookie Policy | `/cookie-policy` |
| Community Guidelines | `/community-guidelines` |
| Intellectual Property & Content Rights | `/intellectual-property` |
| Copyright Infringement Reporting | `/copyright-reporting` |
| Marketplace Content & Licensing Policy | `/marketplace-policy` |

The shared page provides the official logo, semantic headings, table of contents, related policies, keyboard focus styles, reading width, RTL support, localized title/description, canonical URL and language alternatives. Metadata survives navigation between new routes and existing wrapper components and is cleaned up when leaving the policy area. Navigation opens a legal page at its top while retaining back-navigation positions and anchor destinations.

`approvedPolicyDate` in `resources/js/policies.js` intentionally remains `null`. Pages display a translated draft notice and pending effective-date field. Policy metadata uses `noindex, follow` until approval. Set an effective date only after approval of the actual policy text and operator information; adding a date alone does not supply the missing organizational facts. Metadata is generated in the SPA; server rendering/prerendering was not added.

## Internationalization

The exact existing locales are **ar, de, en, es, fa, fr, hi, it, ja, ru, tr, ur, zh**. No locale was added, removed or renamed.

The new `policies` namespace follows the existing feature-message module pattern and is merged into the existing Vue I18n instance. It provides **105 text values per locale, 1,365 in total**, covering interface labels, seven grouped audiences, nine policy titles/descriptions and 30 policy sections. Every locale has the same complete key/section structure and its own body text. Tests disable English fallback when rendering the feature.

Existing Navbar/Footer translation keys remain reused and available in every existing JSON locale. Navbar language choices are derived from Vue I18n’s available locales. The Laravel HTML shell derives its supported locale names from the existing JSON files, fixing its previous omission of Turkish. The root Vue layout now treats Arabic, Persian and Urdu as RTL consistently.

These are authored draft translations, not certified legal translations. A qualified reviewer should check terminology and substantive equivalence in every locale before production approval, especially statutory notices, liability, consent, consumer rights and licensing language.

## Basis in the application

The wording reflects the inspected implementation: Laravel/Sanctum authentication and administrative restrictions; profile fields; normal and historical events; image/video uploads and previews; comments/reactions/favorites; media purchases and download entitlements; plans, wallet, PayPal, gifts and withdrawals; OpenRouter image/text processing; Google Translate jobs; configurable n8n moderation/translation; Google sign-in; contact records; external maps, geocoding and asset hosts.

Download delivery checks entitlements, but media is also stored on the public disk and published URLs/previews may be reachable. Neither the implementation nor the policy promises comprehensive file secrecy or DRM. A purchase creates a download entitlement and does not itself establish copyright assignment or an unrestricted commercial license.

No application advertising/analytics tracker or functional consent manager was identified in the inspected code. The policy describes actual cookies, local/session storage and configurable duration, including the repository’s 120-minute session default without claiming that is the production setting. No decorative consent banner was introduced. Hosting-level monitoring, deployed cookies and external providers still require a production audit.

## Validation

| Check | Result |
| --- | --- |
| Frontend Vitest suite | 112 tests across 15 files passed after the final metadata regression test. |
| New frontend policy tests | 24 passed, including all nine policies in every locale, guest/account/creator visibility, actual Navbar state sharing, language changes, links, metadata cleanup, URL rejection and contact submission. |
| New Laravel integration tests | 6 passed, 30 assertions: guest and user submissions, validation, throttling, private inbox/settings and whitelisted public footer fields. |
| Existing API route registration test | Passed, 9 assertions. |
| Full PHPUnit suite | 262 tests: 249 passed, 7 skipped, 6 errors in existing `EventAiTagsDispatchTest` image processing. |
| Baseline comparison | An isolated archive of original `HEAD` reproduced the same six `GeometryException` errors through the unchanged `ImageAnalysisService.php:311`; they are not introduced by this change. |
| Production Vite build | Passed; existing Sass/Bootstrap deprecation, Browserslist age and large-chunk warnings remain. |
| PHP formatting/syntax | Pint passed for the touched PHP controller/request/new tests; route syntax passed. |
| Browser layout | Chrome checked English/Arabic at 1440, 768, 390 and 320 pixels, and all 13 locales at 390 pixels: no document horizontal overflow; correct columns and text direction. |
| Browser behavior | Language selector preserved `/fa/copyright-policy`, locale preference and RTL; legal-page navigation and metadata checked. |
| Accessibility | axe-core WCAG A/AA checks on the new policy area and footer: zero reported violations on English desktop and Arabic mobile. This is an automated scoped check, not a site-wide accessibility certification. |
| Working tree hygiene | `git diff --check` passed. No new project dependency or lockfile change. Temporary browser entry was removed. |

PHP checks used SQLite in memory, a temporary test-only application key and the required PHP extensions. Browser checks used the real Vue application with the optional footer settings request mocked and the unchanged public logo served locally; they do not verify live production service availability. MySQL-specific concurrency scenarios remain skipped where their configured service is absent.

There is no frontend lint or type-check script configured in `package.json`; Vite compilation and Vitest were used for frontend validation.

## Changed and created files

| File | Change |
| --- | --- |
| `resources/js/components/layouts/footer.vue` | Redesigned existing footer; shared navigation; supported audiences; actual settings; accessible unavailable states; dynamic year. |
| `resources/js/components/layouts/Navbar.vue` | Shared navigation and verified session state; derive existing locale choices; retain original actions/dropdowns. |
| `resources/js/navigation.js` | New shared destinations, visibility conditions and Special Coverage destination builder. |
| `resources/js/policies.js` | New policy route registry, pending approval date and external destination validator. |
| `resources/js/views/home/PolicyPage.vue` | New shared policy/overview presentation and metadata lifecycle. |
| `resources/js/views/home/terms.vue` | Existing component delegates to the shared Terms presentation. |
| `resources/js/views/home/privacy.vue` | Existing component delegates to the translated, verified Privacy presentation. |
| `resources/js/views/home/data-protction.vue` | Existing component delegates to Data Protection; removes unsupported compliance/security assertions. |
| `resources/js/i18n/policies.js` | New complete policy feature translations for existing 13 locales. |
| `resources/js/app.js` | Merge new namespace into the existing i18n instance. |
| `resources/js/App.vue` | Correct Persian/Urdu root direction. |
| `resources/js/router/index.js` | Register new policy routes; retain existing paths; scoped policy scrolling. |
| `resources/js/views/home/contact.vue` | Localized legal topics and preselection; localized failure message. |
| `resources/js/services/ContactService/ContactService.js` | Let the contact view handle localized errors without a second global toast. |
| `resources/js/services/FooterService/FooterService.js` | Public destination read with optional-error handling. |
| `resources/views/index.blade.php` | Derive language metadata from existing locale files. |
| `routes/api.php` | Public throttled submission and footer destinations; keep inbox/settings private. |
| `app/Http/Controllers/api/admin/FooterController.php` | Public destination whitelist; reuse existing repository and cache. |
| `app/Http/Requests/ContactRequest.php` | Bound public submission fields to stored/UI limits. |
| `tests/Frontend/policies.test.js` | New feature translation, rendering, navigation, metadata and contact tests. |
| `tests/Feature/Api/LegalContactTest.php` | New backend public/private boundary and persistence tests. |
| `docs/footer-legal-implementation.md` | This implementation and review report. |

## Required before production legal publication

The following remain decisions/operational work for the operator and legal reviewer, rather than implemented promises:

- Confirm the legal entity/controller, postal address, relevant jurisdictions and age/eligibility rules; identify a representative or DPO only if required and actually appointed.
- Approve the actual text, licensing grant, consumer transaction terms, applicable liability provisions, translation equivalence and effective date. Confirm how revised terms will be presented and accepted where required.
- Confirm lawful bases by processing purpose, actual processors/subprocessors, AI provider/model routing, processing countries, data-transfer safeguards and required agreements.
- Document retention schedules for accounts, originals/previews, transactions, logs, support, disputes and backups; define an end-to-end correction, account closure and erasure process. Profile editing and the request inbox exist; full automated erasure does not.
- Establish operational handling of privacy requests, access reviews, incidents, required notifications, moderation reasons and reconsideration. The support inbox exists; staffing, response rules and statutory deadlines are not automated here.
- Determine applicable copyright notice/counter-notice and DSA obligations, including any designated agent, notice disclosures, reason statements, required declarations and restoration procedures. Do not present the general contact form as a registered DMCA agent.
- Confirm seller and buyer license terms, third-party releases and refund/withdrawal procedures. No universal commercial license, escrow, automatic media refund or blanket refusal of mandatory consumer remedies was added.
- Audit production storage/cookies and external services. Add real blocking consent/withdrawal controls before introducing nonessential technologies that legally require consent.
- Replace seed destinations with verified official profiles and published mobile-app listings in the existing admin settings. Unconfigured destinations remain visibly unavailable.

The unrelated six event-image test errors remain unresolved in this task. No other implementation blocker was identified by the checks above.

## Official references consulted

These informed the drafting principles and the distinction between verified behavior and legal applicability; they do not establish that Scemory satisfies the instruments.

- [GDPR official text, especially Articles 5, 6, 12–22, 28, 32–34 and 44 onward](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX%3A32016R0679).
- [US Copyright Office: Section 512 notice, counter-notice and safe-harbor resources](https://copyright.gov/512/) and [designated-agent directory guidance](https://www.copyright.gov/dmca-directory/faq.html).
- [European Commission: DSA transparency and moderation reasons](https://digital-strategy.ec.europa.eu/en/policies/dsa-brings-transparency) and [DSA questions and answers](https://digital-strategy.ec.europa.eu/en/faqs/digital-services-act-questions-and-answers).
- [ICO: storage and access technologies](https://ico.org.uk/for-organisations/direct-marketing-and-privacy-and-electronic-communications/guidance-on-the-use-of-storage-and-access-technologies/what-are-storage-and-access-technologies/).
