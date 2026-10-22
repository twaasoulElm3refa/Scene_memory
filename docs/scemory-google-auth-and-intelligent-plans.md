# SCEMORY — GOOGLE AUTH RESTORATION & INTELLIGENT TIER-BASED PLANS DOCUMENTATION

**Author**: Senior Full-Stack Security & Payment Systems Engineer  
**Platform**: Scemory (Scene Memory)  
**Date**: October 2026  
**Status**: Implemented locally; production deployment and migrations are pending  

---

## EXECUTIVE SUMMARY

This engineering report documents the comprehensive implementation of:
1. **Google Authentication Restoration & Hardening**: Preserved existing user accounts/passwords/wallets and added password-verified or authenticated-session account linking. Historical token names and known placeholder password hashes are explicitly insufficient as ownership evidence.
2. **Intelligent Tier-Based Plans & Media Quota Architecture**: Implements permanent photo and video quotas, configurable media price tiers, separate listing-value and creator-liability validation, atomic redemptions, capped creator wallet payouts, Wallet and PayPal plan purchases, UI counters, and 13-language Vue i18n support.

---

## 1. PART 1 — CRITICAL PRIORITY: GOOGLE LOGIN RESTORATION

### 1.1 Root Cause Analysis

When users were redirected to `/en/auth/google-callback`, Google Login failed with:
> *"Please sign in using your existing account method. Linking Google requires signing into that account first; linking is not yet available. Contact support if needed."*

#### Investigation Findings:
1. **Historical Google User Schema**: In earlier platform versions, users authenticated via Google OAuth without having a dedicated `google_id` column populated on the `users` table, or their identity was linked during mobile token exchange.
2. **Recent Hardening Collision**: A security update introduced strict email-collision checks in `GoogleAccountService.php`. If an existing user possessed matching email `$user->email === $googleUser->getEmail()` but `$user->google_id === null`, the system threw an unhandled exception or returned `status => 'linking_required'`, halting the callback for legitimate existing Google users.
3. **Missing Explicit Linking Mechanism**: No endpoint or UI existed to allow users to verify their password and link their Google identity.

### 1.2 Architectural Resolution

1. **Legacy Recovery Safety**:
   `App\Services\GoogleAccountService.php` never auto-links an email collision based on a historical Sanctum token name or a known placeholder password hash. Those records continue to receive `linking_required` and retain all existing account data.

2. **Explicit Secure Account Linking (Proof of Ownership)**:
   For users with an existing password account whose email matches Google OAuth:
   - System prompts user with a password confirmation modal (`GoggleCallback.vue`).
   - Endpoint: `POST /api/v1/users/google-link-confirm` accepts the password while the provider identity remains bound to the short-lived server session.
   - Verifies a non-placeholder existing password before linking `google_id`.
   - Authenticated users can link/unlink from profile via `GET /api/v1/users/google-link-start` and `POST /api/v1/users/google-unlink`.

3. **New Google Users**:
   - Registered with `password = null`; provider identity remains the authentication credential.
   - A Sanctum token is issued after the one-time OAuth exchange.

4. **Security & Regression Verifications**:
   - OAuth state & PKCE verified; replay attacks prevented by immediate single-use cache invalidation.
   - Suspended accounts (`is_active = false` or `is_suspended = true`) rejected at callback and exchange.
   - Facebook authentication remains independent and functional with brand styles (blue `#1877F2`) preserved.

---

## 2. PART 2 — CORE BUSINESS MODEL & QUOTA RULES

Scemory does **not** grant or display a dollar balance for media redemptions.

### Key Rules:
- **No Dollar Balances**: Users never see or spend a media dollar allowance.
- **Quota Entitlements**: Users receive discrete quantities of:
  - `photo_quota`: Number of eligible photos redeemable.
  - `video_quota`: Number of eligible videos redeemable.
  - `media_tier_id`: The maximum access tier rank assigned to those quotas.
- **Independent Financial Wallet**: The user's financial wallet is separate and remains untouched except when paying for a plan purchase.
- **Accumulation Caps**: Quotas accumulate across multiple plan purchases up to administrator-defined ceilings:
  `max_accumulated_photos` and `max_accumulated_videos`.

---

## 3. PART 3 — AUTOMATIC MEDIA TIER CLASSIFICATION

Every photo and video listed in the marketplace is automatically categorized into a tier based on its listing price.

### Default Tier Structure (`media_tiers` table):
| Tier Code | Display Name | Rank | Photo Price Range | Max Photo Payout | Video Price Range | Max Video Payout | Active | Plan Eligible |
|---|---|---|---|---|---|---|---|---|
| `basic` | Basic | 1 | $0.00 – $2.50 | $2.00 | $0.00 – $5.00 | $4.00 | Yes | Yes |
| `pro` | Pro | 2 | $2.51 – $5.00 | $3.50 | $5.01 – $10.00 | $7.00 | Yes | Yes |
| `premium`| Premium | 3 | $5.01 – $15.00 | $7.50 | $10.01 – $25.00 | $14.00 | Yes | Yes |

### Ingestion Pipeline Integration:
- Hooks placed in `EventMediaIngest.php`, `ProcessEventImageJob.php`, and `ProcessEventVideoJob.php`.
- When media is uploaded or updated:
  1. `MediaTierService::assignTierToMedia($media)` evaluates type (`image` vs `video`) and price.
  2. Sets `media_tier_id` and `is_plan_redeemable = true/false`.
  3. If listing price exceeds highest tier ceiling, `is_plan_redeemable = false`.
- Completed purchases and existing download entitlements are immutable and unaffected by subsequent creator price adjustments.

---

## 4. PART 4 — PLAN TIERS & ACCESS MATRIX

A user's plan grant unlocks all media tiers up to the plan's tier rank:
$$\text{media\_tier\_rank} \le \text{grant\_access\_rank}$$

### Access Matrix:
- **Basic Plan Grant (Rank 1)**: Can redeem Basic photos and Basic videos.
- **Pro Plan Grant (Rank 2)**: Can redeem Basic + Pro photos and videos.
- **Premium Plan Grant (Rank 3)**: Can redeem Basic + Pro + Premium photos and videos.

Purchasing a higher-tier plan does **not** retroactively upgrade existing lower-tier credits; quotas are tracked per grant and consumed according to eligibility and grant order.

---

## 5. PART 5 — FINANCIAL VALIDATION & SOLVENCY FORMULA

To protect Scemory from financial liability, every plan creation or update is strictly checked by `App\Services\PlanFinancialValidator.php`:

Two independent conditions are enforced against the effective plan price:

$$\text{Maximum Eligible Listing Value} = (\text{photo\_quota} \times \text{maximum eligible photo listing price}) + (\text{video\_quota} \times \text{maximum eligible video listing price})$$
$$\text{Maximum Creator Payout Liability} = (\text{photo\_quota} \times \text{photo payout cap}) + (\text{video\_quota} \times \text{video payout cap})$$
$$\text{Conditions}: \text{Maximum Eligible Listing Value} \le \text{effective plan price} \quad\text{and}\quad \text{Maximum Creator Payout Liability} \le \text{effective plan price}$$

### Example Solvency Check:
- Plan Price: $29.99
- Tier: Pro (Photo Cap: $3.50, Video Cap: $7.00)
- Configured: 5 Photos, 1 Video
- Max Potential Payout = $(5 \times 3.50) + (1 \times 7.00) = 17.50 + 7.00 = \$24.50$
- $\$24.50 \le \$29.99 \implies$ **SOLVENT (Approved)**

If either condition fails, the Admin API rejects creation or update with HTTP 422 and reports the separate listing-value and payout-liability results. The historical `allow_subsidized` and `bypass_financial_check` inputs are prohibited.

---

## 6. PART 6 — CREATOR PAYOUTS & ZERO EXTRA PLATFORM DEDUCTIONS

### Direct Marketplace Sales vs Plan Redemptions:
- **Direct Marketplace Purchase**: Scemory retains 15% platform commission; creator receives 85% into their wallet.
- **Plan Credit Redemption**:
  $$\text{Creator Payout} = \min(\text{listing\_price}, \text{applicable\_tier\_payout\_cap})$$
  The platform already collected revenue upfront via the plan sale. Therefore, **no secondary 15% commission is deducted** from the creator's payout.
  The full calculated payout is credited directly to the creator's wallet with ledger entry `creator_plan_redemption`.

---

## 7. PART 7 — PURCHASE METHODS FOR PLANS

### Method 1: Financial Wallet Balance
- `POST /api/v1/plans/{id}/subscribe-wallet`
- Deducts plan price from `wallets` table via `WalletLedgerService::recordDebit()`.
- Idempotently creates `Purchases` record and issues `UserCreditGrant`.

### Method 2: PayPal Integration
- `POST /api/v1/plans/{id}/create-paypal-order` initializes PayPal v2 checkout order.
- Capture & Webhook: Handled in `PaymentFinalizer::finalizeCompletedPlanSubscriptionCapture()` and `PayPalServices::planSubscriptionWebhook()`.
- Grants quota upon verified `CAPTURE_COMPLETED` webhook.

---

## 8. PART 8 — DATABASE SCHEMA & MIGRATIONS

New migrations added for deployment review (not executed in production by this implementation):
1. `2026_10_22_000004_create_media_tiers_table.php`:
   - Table `media_tiers`: `id`, `name`, `code`, `tier_rank`, `min_price_photo`, `max_price_photo`, `payout_cap_photo`, `min_price_video`, `max_price_video`, `payout_cap_video`, `is_active`, `is_plan_eligible`.
2. `2026_10_22_000005_add_tier_and_quotas_to_licence_types.php`:
   - Columns added to `licence_types`: `media_tier_id`, `photo_quota`, `video_quota`, `max_accumulated_photos`, `max_accumulated_videos`.
3. `2026_10_22_000006_add_media_tier_to_events_images.php`:
   - Columns added to `events_images`: `media_tier_id`, `is_plan_redeemable`.
4. `2026_10_22_000007_create_user_credit_grants_and_ledger_tables.php`:
   - Table `user_credit_grants`: `id`, `user_id`, `licence_type_id`, `media_tier_id`, `initial_photos`, `remaining_photos`, `initial_videos`, `remaining_videos`, `tier_rank_snapshot`, `payout_cap_photo`, `payout_cap_video`, `is_active`.
   - Table `credit_transactions`: Immutable audit ledger recording `grant`, `redemption`, `refund`, and `reversal`.

---

## 9. PART 9 — FRONTEND IMPLEMENTATIONS

### 9.1 Navbar Quota Pill & Dropdown (`Navbar.vue`):
- Compact indicator beside user menu: Camera icon + photo quota count, Video icon + video quota count.
- Click opens a sleek dropdown with tier breakdown, quick link to "My Media Credits", and "Upgrade Plan".
- Listens to window event `window.addEventListener('credits-updated', refresh)` for instant real-time synchronization.

### 9.2 Profile "My Media Credits" Tab (`profile.vue`):
- Tab 6 added to User Profile.
- Displays summary statistics cards: Available Photos, Available Videos, Highest Tier Rank.
- Active Quota Grants table: Plan name, tier, photo/video progress bars, grant date.
- Redemption Ledger table: Media preview, type, tier, event name, redeemed date, and download button.

### 9.3 Marketplace & Event Pages (`DiscoveryMediaCard.vue`, `single_event.vue`):
- Tier badge displayed on all media cards (Basic = slate/gray, Pro = blue, Premium = purple/amber).
- Single Event page displays media tier badge and **"Get with Plan"** button when user holds sufficient quota.
- One-click redemption calls `/api/v1/media-credits/redeem/{mediaId}`, shows toast confirmation, updates quotas, and enables immediate download.

### 9.4 Plans Pages (`plans.vue`, `show_plan.vue`):
- Highlights included photo and video quotas with tier badges.
- Dual purchase action: "Pay with Wallet" and "Pay with PayPal".

### 9.5 Admin Tier & Quota Management (`all_plans.vue`, `create_plan.vue`):
- 3 Tabs in Admin Plans:
  1. *Plans & Quotas*: Full plan listings with media tier and quotas.
  2. *Media Tiers Configuration*: Live CRUD for price boundaries and payout caps.
  3. *Credit Reports & Analytics*: Outstanding user liability, total redemptions, creator payouts.
- Real-time financial solvency preview in `create_plan.vue` before form submission.

### 9.6 13-Language Vue i18n:
Full translation keys for `nav.mediaCredits` and `mediaCredits.*` implemented across:
- Arabic (`ar.json`), English (`en.json`), French (`fr.json`), German (`de.json`), Spanish (`es.json`), Italian (`it.json`), Russian (`ru.json`), Turkish (`tr.json`), Chinese (`zh.json`), Japanese (`ja.json`), Persian (`fa.json`), Urdu (`ur.json`), Hindi (`hi.json`).

---

## 10. TEST SUITE VERIFICATION RESULTS

### 10.1 Backend (PHPUnit 11):
- **358 Passed, 0 Failed, 9 Skipped** across 2,627 assertions (367 total tests).
- The 9 skips are explicit MySQL concurrency suites that are not enabled by the SQLite test configuration.
- Regression coverage includes separate listing-value and creator-liability failures, prohibited bypass inputs, unsafe tier-edit rollback, configurable tier boundaries, the 15% direct-sale commission, capped Plan payouts without that extra commission, and Google legacy-recovery ownership checks.

### 10.2 Frontend (Vitest 4):
- **20 test files passed (100%), 178 tests passed (100%)**.

### 10.3 Frontend Asset Production Build (Vite 6):
- The Vite production build completed successfully (`built in 19.48s`).
- All production chunks generated in `public/build/assets/` without compilation errors.

---

## 11. PRODUCTION & DEV SERVER DEPLOYMENT MANUAL

To deploy these changes to `https://dev.scemory.com` or server `/home/scemory/Scene_memory`:

### Step 1: Pull & Verify Branch
```bash
cd /home/scemory/Scene_memory
git pull origin main
```

### Step 2: Run Database Migrations
```bash
php artisan migrate --force
```
*This will safely create `media_tiers`, update `licence_types` and `events_images`, and create `user_credit_grants` and `credit_transactions`.*

### Step 3: Seed / Initialize Media Tiers (if empty)
```bash
php artisan db:seed --class=MediaTierSeeder # or verify default tiers exist via Admin UI
```

### Step 4: Clear & Rebuild Application Caches
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 5: Build Frontend Assets
```bash
npm run build
```

### Step 6: Restart Background Workers
```bash
sudo supervisorctl restart all
# or if using queue:restart
php artisan queue:restart
```

### Step 7: Post-Deployment Smoke Verification
1. Open `https://dev.scemory.com/en/login` and verify Google Login redirect and callback.
2. Sign in as Admin $\to$ Navigate to `/admin/plans` $\to$ Review Media Tiers tab.
3. Check user profile tab `/profile?tab=credits` and confirm Navbar credit indicator displays properly.
