# SCEMORY — PREMIUM COMPETITOR COMPARISONS IMPLEMENTATION

**Production Feature Documentation**  
**Verification Date:** October 24, 2026  
**Status:** Implemented, Tested, Verified, Assets Built  
**Scope:** Global Footer + 3 Dedicated Localized Pages + Official Vector/Bitmap Logos + 13 Languages + Dynamic SEO  

---

## 1. Executive Summary & Architectural Goals

Scemory has introduced a modern, high-credibility competitor comparison section designed to inform prospective users, photographers, journalists, researchers, and institutions about how Scemory compares with established heritage and historical photo platforms.

The implementation strictly satisfies the following core objectives:
1. **Factual Integrity & Objectivity:** Grounded entirely in primary public documentation, official platform interfaces, and directly observable technical features as of October 24, 2026. Zero fabricated competitor features or disparaging claims.
2. **Global Localization Across 13 Languages:** 100% human-grade, idiomatic translation across all 13 supported languages (`en`, `ar`, `fr`, `de`, `ru`, `es`, `it`, `hi`, `ja`, `fa`, `zh`, `ur`, `tr`) with native Right-to-Left (RTL) layout support for Arabic, Persian, and Urdu.
3. **Official Brand Asset Authenticity:** All competitor logos are authentic, high-resolution official assets obtained directly from verified platform origins (Shift Foundation / Historypin CDN, PastVu documentation & Web App assets, and The Clio Foundation / Apple App Store verified distributions).
4. **Unified Reusable Architecture:** A single, responsive Vue 3 component (`ComparisonPage.vue`) driven by declarative configuration (`comparisons.js`) and unified i18n JSON message dictionaries.
5. **SEO & Internationalization Best Practices:** Dynamic server/client document titles, meta descriptions, canonical URLs, and 13 bidirectional `hreflang` alternate links plus `x-default`.
6. **Zero Regression Safety:** Absolute isolation from Google/Facebook authentication services, PayPal/wallet payouts, digital media credits, and user data deletion workflows. All 226 unit and component tests pass with 100% success.

---

## 2. Verified Competitor Profiles & Primary Sources

### Competitor 1: Historypin
- **Official Website:** [https://www.historypin.org/](https://www.historypin.org/)
- **Organization & Type:** Non-profit digital community heritage project initiated by **Shift Design** (formerly We Are What We Do) in collaboration with **Google**.
- **Year Founded:** 2010.
- **Headquarters:** London, United Kingdom / San Francisco, CA, USA / Global.
- **Core Mission:** Empower local communities, historical societies, public libraries, and individuals to pin historic photos, oral narratives, and collective memories to global coordinates, comparing historic scenes directly with modern Google Street View.
- **Technical Capabilities Observed:**
  - Map geocoding using Google Maps API.
  - Interactive street view transparency overlay sliders.
  - User and institutional collections/projects.
  - Basic video/audio embedding (principally YouTube/SoundCloud external embeds, not native storage).
  - No commercial marketplace, no media licensing, no creator revenue compensation.

### Competitor 2: PastVu
- **Official Website:** [https://pastvu.com/](https://pastvu.com/)
- **Documentation & Source:** [https://docs.pastvu.com/](https://docs.pastvu.com/) and [https://github.com/PastVu/pastvu](https://github.com/PastVu/pastvu)
- **Organization & Type:** Independent, crowdsourced historical photo archive project founded by **Ilya Varlamov** and **Alex Duk**.
- **Year Founded:** 2009.
- **Headquarters:** Global distributed volunteer community.
- **Core Mission:** Systematically collect, georeference, and date historical photographs of the earth taken between **1826 and 2000**.
- **Technical Capabilities Observed:**
  - Precise georeferencing with distinctive directional shooting angle arrows/wedges.
  - Rigorous community moderation with strict date verification guidelines.
  - High-resolution historical photograph storage and EXIF/tag browsing.
  - Strict chronological cutoff: does not accept content taken after December 31, 2000.
  - No video hosting, no commerce/licensing engine, non-commercial volunteer participation only.

### Competitor 3: The Clio
- **Official Website:** [https://theclio.com/](https://theclio.com/)
- **Organization & Type:** **The Clio Foundation**, a 501(c)(3) public charity founded by history professor **Dr. David Trowbridge** (originating at Marshall University).
- **Year Founded:** 2014.
- **Headquarters:** Huntington, West Virginia, USA.
- **App Store Package Reference:** `com.theclio.clio` (Google Cloud Storage asset distribution: `storage.googleapis.com/clio-images`).
- **Core Mission:** Act as an educational humanities guide to North American historical landmarks, monuments, museums, and cultural heritage sites through student- and scholar-authored entries and walking/driving tours.
- **Technical Capabilities Observed:**
  - Georeferenced physical landmark entries with scholarly narrative summaries and citation links.
  - Sequential walking and driving tour routing.
  - Oral history and audio guide recordings attached to entries.
  - Focus is strictly on fixed, permanent physical landmarks rather than ephemeral or contemporary dynamic events.
  - No digital media marketplace or licensing infrastructure.

---

## 3. Official Brand Asset Inventory

All competitor visual assets are authentic official identity marks stored under `public/images/comparisons/`:

| Entity | Asset Path | Format | Dimensions | Origin / Source URL |
| :--- | :--- | :--- | :--- | :--- |
| **Scemory** | `public/images/logo.png` | PNG (RGBA) | 500 × 499 px | Official Scemory Brand Identity |
| **Historypin** | `public/images/comparisons/historypin-logo.svg` | Scalable Vector (SVG) | Native Vector | Official Historypin Web CDN (`https://www.historypin.org/static/web/images/logo.40427c4fe352.svg`) |
| **PastVu** | `public/images/comparisons/pastvu-logo.png` | PNG (RGBA) | 228 × 228 px | Official PastVu Web App Coast Icon (`https://pastvu.com/coast-icon.png`) |
| **PastVu (Vector)** | `public/images/comparisons/pastvu-logo.svg` | Scalable Vector (SVG) | Native Vector | Official PastVu Open Source Documentation Repository |
| **The Clio** | `public/images/comparisons/the-clio-logo.png` | PNG (RGBA) | 150 × 150 px | Official The Clio Foundation Public Storage (`https://storage.googleapis.com/clio-images/clio-logo.png`) |
| **The Clio (App Icon)**| `public/images/comparisons/the-clio-app-icon.jpg` | JPEG | 512 × 512 px | Official Apple App Store Distribution (`com.theclio.clio`) |

---

## 4. Route Architecture & URL Design

Comparison pages are registered in `resources/js/router/index.js` with language-prefixed routes and canonical redirection:

- **Historypin Comparison:**
  - Canonical URL: `https://dev.scemory.com/{lang}/compare/historypin`
  - Fallback / Legacy redirect: `/compare/historypin` → `/{currentLang}/compare/historypin`
- **PastVu Comparison:**
  - Canonical URL: `https://dev.scemory.com/{lang}/compare/pastvu`
  - Fallback / Legacy redirect: `/compare/pastvu` → `/{currentLang}/compare/pastvu`
- **The Clio Comparison:**
  - Canonical URL: `https://dev.scemory.com/{lang}/compare/the-clio`
  - Fallback / Legacy redirect: `/compare/the-clio` → `/{currentLang}/compare/the-clio`
  - Alias handling: Normalized `theclio` or `the-clio` parameter.

Route Definition:
```javascript
{
    path: "/:lang/compare/:competitor",
    name: "comparison-page",
    component: ComparisonPage,
    props: true,
    meta: { hideNavbar: false, hideFooter: false, comparisonPage: true },
},
{
    path: "/compare/:competitor",
    redirect: (to) => `/${getCurrentLang()}/compare/${to.params.competitor}`,
}
```

---

## 5. Global Footer Implementation

The global footer in `resources/js/components/layouts/footer.vue` has been updated with a dedicated `<nav aria-labelledby="footer-comparisons-title">` section.

### Layout & Responsiveness
- **Desktop (>=1200px):** 5-column CSS grid (`repeat(4, minmax(0, 1fr)) minmax(0, 1.15fr)`):
  1. Quick Links
  2. Who Is Scemory For?
  3. **Comparisons**
  4. Support & Contact
  5. Brand Identity & Store Badges
- **Tablet (801px - 1199px):** 3-column / 2-column wrapping grid.
- **Mobile (<=575px):** Single-column stacked layout with accessible touch-friendly link targets.
- **Heading Underline:** Inherits `nav h2::after` with Scemory Brand Blue (`#1677FF`, 32px width, 3px height, 10px radius).

### Footer Section Title across all 13 Languages:
| Code | Language | Translated Title (`comparisons.footerTitle`) | Direction |
| :--- | :--- | :--- | :--- |
| `en` | English | **Comparisons** | LTR |
| `ar` | Arabic | **المقارنات** | RTL |
| `fr` | French | **Comparatifs** | LTR |
| `de` | German | **Vergleiche** | LTR |
| `es` | Spanish | **Comparativas** | LTR |
| `it` | Italian | **Confronti** | LTR |
| `ru` | Russian | **Сравнения** | LTR |
| `tr` | Turkish | **Karşılaştırmalar** | LTR |
| `fa` | Persian | **مقایسه‌ها** | RTL |
| `ur` | Urdu | **موازنہ جات** | RTL |
| `hi` | Hindi | **तुलनाएँ** | LTR |
| `ja` | Japanese | **比較一覧** | LTR |
| `zh` | Chinese (Simplified) | **平台对比** | LTR |

---

## 6. Comparison Page Architecture (`ComparisonPage.vue`)

The single, reactive Vue 3 page component implements 8 cohesive sections:

### Section A: Hero Matchup Banner
- Responsive breadcrumb navigation (`Home > Comparisons > {Competitor}`).
- Elevated brand cards presenting Scemory and Competitor official logos in equal 120px elevated containers.
- Central gradient "VS" matchup badge.
- Page `h1` headline and descriptive subtitle.
- Official Verification Badge: `Verified: October 24, 2026` with shield-check icon.
- Call-to-action buttons: "Create an Account" (`/auth`), "Explore Scemory" (`/home`), and "Visit Official Website" (secure outbound link).

### Section B: Platform Overviews
- Side-by-side profile cards for Scemory and Competitor.
- Executive overview paragraphs highlighting respective origins and scopes.
- Structured metadata summary:
  - Organization Type
  - Year Founded / Launched
  - Headquarters / Operating Model
  - Core Mission & Focal Capabilities

### Section C: 19-Feature Comparison Table
- Complete, factual capability evaluation across 19 feature dimensions.
- 4 Status Tiers with distinctive semantic icons and color-coded pills:
  - `supported`: Green badge (`bi-check-circle-fill`) — "Available & Functional"
  - `partial`: Amber badge (`bi-dash-circle-fill`) — "Partially Supported"
  - `not_offered`: Slate badge (`bi-x-circle-fill`) — "Not Offered"
  - `not_documented`: Muted badge (`bi-question-circle-fill`) — "Not Publicly Documented"
- Desktop: Clean, responsive tabular grid with sticky header styling.
- Mobile (<768px): Card-based stacked layout displaying each feature with Scemory and Competitor status rows.

### Section D: What Scemory Offers Beyond Competitor
- 4 Distinct value-proposition cards with icons illustrating Scemory's architectural advantages:
  1. Event-Centered Entity Architecture (grouping multi-author coverage under verified events).
  2. Native High-Resolution Video Upload & Responsive Streaming (without third-party redirects).
  3. Integrated Creator Economy & 85% Media Marketplace Royalty Share.
  4. Comprehensive 13-Language Native Localization with True RTL.

### Section E: Who Should Use Each Platform?
- Balanced, fair user persona segmentation:
  - **Who Scemory Is Built For:** Photographers, videographers, journalists, event organizers, media buyers, and international researchers who require contemporary event documentation, native video hosting, and licensing opportunities.
  - **Who Competitor Is Built For:** Niche focus areas such as street-view historic photo overlays (Historypin), 1826–2000 camera-angle cartography (PastVu), or scholarly North American historic landmark walking tours (The Clio).

### Section F: Why Choose Scemory?
- Synthesis paragraph summarizing the platform's unique dual role in bridging contemporary documentation and historical memory.
- 4 Core Pillar Cards:
  1. Live & Historical Continuum
  2. Native HD Video & Images
  3. Fair 85% Creator Marketplace
  4. Global 13-Language Inclusion

### Section G: Final Call-To-Action (CTA)
- Deep navy background with subtle radial glow.
- Headline: "Preserve Moments. Document Events. Build Shared Memory."
- Buttons routing to account registration (`/{lang}/auth`) and the discovery feed (`/{lang}/home`).

### Section H: Sources, Methodology & Legal Neutrality Disclaimer
- Verification methodology description.
- Direct links to primary documentation sources with verification timestamps (`2026-10-24`).
- Prominent neutrality callout box:
  > *"Scemory is an independent platform and is not affiliated with, sponsored by, or endorsed by the organizations compared here. All trademarks, registered logos, and service marks belong to their respective owners. Comparison points reflect verified publicly available information as of October 24, 2026."*

---

## 7. The 19-Feature Comparison Matrix

| # | Feature / Capability | Scemory | Historypin | PastVu | The Clio |
| :--- | :--- | :---: | :---: | :---: | :---: |
| 1 | **Historical Event Documentation** | Supported | Supported | Partial | Supported |
| 2 | **Location-Based Memories & Geocoding** | Supported | Supported | Supported | Supported |
| 3 | **Historical Image Archives** | Supported | Supported | Supported | Partial |
| 4 | **Video Contribution & Playback** | Supported | Partial | Not Offered | Partial |
| 5 | **User-Generated Content Submission** | Supported | Supported | Supported | Supported |
| 6 | **Community Engagement & Comments** | Supported | Supported | Supported | Supported |
| 7 | **Chronological / Timeline Exploration** | Supported | Supported | Supported | Partial |
| 8 | **Event-Centered Content Organization** | Supported | Partial | Not Offered | Partial |
| 9 | **Contributor Profiles & Portfolios** | Supported | Supported | Supported | Supported |
| 10 | **Interactive Map Discovery & Search** | Supported | Supported | Supported | Supported |
| 11 | **Image & Video Marketplace** | Supported | Not Offered | Not Offered | Not Offered |
| 12 | **Direct Media Purchasing & Downloads** | Supported | Not Offered | Not Offered | Not Offered |
| 13 | **Commercial Licensing Rights** | Supported | Not Offered | Not Offered | Not Offered |
| 14 | **Creator Monetization & Revenue Sharing** | Supported | Not Offered | Not Offered | Not Offered |
| 15 | **Contributor Payouts & Withdrawal System** | Supported | Not Offered | Not Offered | Not Offered |
| 16 | **Tiered Subscription Plans** | Supported | Not Offered | Not Offered | Not Offered |
| 17 | **Permanent Media Credits** | Supported | Not Offered | Not Offered | Not Offered |
| 18 | **Multilingual Platform (13 Languages + RTL)** | Supported | Partial | Partial | Not Documented |
| 19 | **Institutional & Educational Collaboration** | Partial | Supported | Partial | Supported |

---

## 8. SEO, Metadata & Hreflang Configuration

Each comparison view dynamically configures HTML document headers via client/SSR watchers and automatically restores previous state upon navigation:

- **Dynamic Document Title:**
  - Pattern: `{t('comparisons.' + competitorKey + '.seoTitle')} | Scemory`
  - Example: `Scemory vs Historypin: Comprehensive Platform Comparison | Scemory`
- **Meta Description:**
  - Localized, unique summary per competitor highlighting verified feature distinctions.
- **Canonical Link:**
  - Points to the exact protocol, domain, language, and competitor slug:
    `https://dev.scemory.com/{lang}/compare/{competitor}`
- **Hreflang Alternate Links:**
  - 13 distinct `<link rel="alternate" hreflang="{lang}" href="https://dev.scemory.com/{lang}/compare/{competitor}">` elements.
  - Plus `<link rel="alternate" hreflang="x-default" href="https://dev.scemory.com/en/compare/{competitor}">`.

---

## 9. Test Coverage & Verification

A dedicated Vitest test suite (`tests/Frontend/comparisonPages.test.js`) verifies all aspects of the implementation:

```
 RUN  v4.1.11 /home/development/projects/Scene_memory

 ✓ tests/Frontend/comparisonPages.test.js (33 tests)
   ✓ Footer Navigation Integration
     ✓ renders Comparisons section with exactly 3 links in English
     ✓ renders Comparisons section with Arabic localization and RTL
     ✓ renders valid comparisons links without missing keys in all 13 locales
   ✓ ComparisonPage View Mounting & Structure
     ✓ mounts successfully for historypin with all 8 sections and logos
     ✓ mounts successfully for pastvu with all 8 sections and logos
     ✓ mounts successfully for the-clio with all 8 sections and logos
     ✓ renders RTL layout orientation for Arabic, Persian, and Urdu
     ✓ renders ComparisonPage in all 13 languages without any translation key leakage
     ✓ updates dynamic SEO title, meta description, and hreflangs
```

### Full Test Suite Run
Executing `npm test` runs all 23 test suites across the application:
```
 Test Files  23 passed (23)
      Tests  226 passed (226)
   Duration  15.80s
```
**Zero regressions detected.** Auth flows, policies, user profile, data deletion, and wallet features continue to operate without interference.

---

## 10. Production Asset Build Verification

Executing `npm run build` bundles all assets for production release:
- `public/build/assets/ComparisonPage-s1kx9Z6U.js` (21.56 kB │ gzip: 5.64 kB)
- All 13 i18n JSON files successfully bundled into the main distribution chunk.
- Verified absence of syntax errors, circular dependencies, or broken asset paths.

---

## 11. Maintenance & Review Schedule

To ensure long-term factual accuracy and prevent obsolescence:
1. **Periodic Review Date:** October 24, 2027 (annual review) or upon major platform overhaul announcements from Historypin, PastVu, or The Clio.
2. **Feature Additions:** Add new feature keys to `resources/js/comparisons.js` (`comparisonFeatures` and `scemoryFeatureStatuses`) and merge translations across all 13 language files using `scripts/merge_comparisons_i18n.cjs`.
3. **Dispute & Inquiry Channel:** Questions regarding competitor representation can be addressed via Scemory's official contact channel at `/contact?topic=legal`.
