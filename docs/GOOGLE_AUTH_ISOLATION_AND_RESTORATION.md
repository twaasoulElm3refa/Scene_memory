# Google Authentication Isolation and October 4 Restoration Report

**Date:** 2026-10-24  
**Repository:** `/home/development/projects/Scene_memory`  
**Reference Commit:** `132bb49` (October 4, 2026)  
**Base Commit:** `31ee81e`  

---

## 1. Shared Authentication Dependencies Found

A thorough inspection of the authentication dependency graph was conducted across both backend and frontend layers:

- **Controllers:**
  - `app/Http/Controllers/api/auth/GoogleAuthController.php`
  - `app/Http/Controllers/api/auth/FacebookAuthController.php`
  - `app/Http/Controllers/api/auth/SocialAuthController.php` (Legacy unrouted standalone controller)
- **Account Services:**
  - `app/Services/GoogleAccountService.php`
  - `app/Services/GoogleLegacyRecoveryService.php`
  - `app/Services/FacebookAccountService.php`
- **Frontend Services & Components:**
  - `resources/js/services/AuthService/AuthService.js`
  - `resources/js/services/AuthService/FacebookAuthService.js`
  - `resources/js/services/AuthService/GoogleAuthService.js` (Extracted/isolated in this task)
  - `resources/js/services/googleAuth.js`
  - `resources/js/services/facebookAuth.js`
  - `resources/js/components/auth/GoogleAuthButton.vue`
  - `resources/js/components/auth/FacebookAuthButton.vue`
  - `resources/js/views/auth/GoggleCallback.vue`
  - `resources/js/views/auth/FacebookCallback.vue`
  - `resources/js/views/auth/register.vue`

### Shared vs Independent Components

| Component | Shared / Independent | Description |
|---|---|---|
| **OAuth Initiation** | **Independent** | Google uses `Socialite::driver('google')->enablePKCE()`, Facebook uses `Socialite::driver('facebook')->setScopes(['email'])`. |
| **OAuth Callbacks** | **Independent** | Dedicated routes `/api/v1/users/google-callback` and `/api/v1/users/facebook-callback`. |
| **Delivery Mechanism** | **Independent** | Google uses `google-delivery:{hash}`; Facebook uses `facebook-delivery:{hash}`. |
| **Sanctum Tokens** | **Independent** | Google issues `google-auth-token` (and `google-mobile-auth-token`); Facebook issues `facebook-auth-token`. |
| **Database Schema** | **Shared Model, Independent Columns** | `users` table shares core columns (`id`, `name`, `email`), with provider-specific columns `google_id`, `google_avatar_url` vs `facebook_id`, `facebook_avatar_url`. |
| **Account Creation** | **Independent** | `GoogleAccountService::complete()` sets verified email and Google metadata; `FacebookAccountService::registerIdentity()` handles Facebook subject and nullable email. |

---

## 2. Whether Google and Facebook Were Actually Coupled

**Findings:**
1. **Facebook was NOT coupled to Google:**
   - `FacebookAuthController.php` and `FacebookAccountService.php` contain zero references to `google_id`, Google services, or Google tokens.
   - Facebook operated independently.

2. **Google was coupled to Facebook in three specific locations:**
   - In `GoogleAccountService.php`:
     ```php
     if ($candidate->facebook_id !== null) {
         throw new GoogleAuthException('account_login_required', 409);
     }
     ```
     `GoogleAccountService` explicitly checked for `facebook_id` and blocked login if the email matched an account controlled by Facebook, forcing an error instead of permitting ownership proof.
   - In `GoogleLegacyRecoveryService.php`:
     ```php
     if ($user->google_id !== null || $user->facebook_id !== null ...)
     ```
   - In `GoogleAuthController.php`:
     The `unlink()` method checked `$user->facebook_id` to determine whether another authentication credential was present.
   - In Frontend `AuthService.js`:
     Facebook had its own dedicated service `FacebookAuthService.js`, whereas Google methods (`googleLogin`, `exchangeGoogleCode`, `exchangeMobileGoogleCode`, etc.) were directly located in the root `AuthService.js`.

---

## 3. How Google-Specific Logic Was Isolated

1. **Frontend Isolation:**
   - Created `resources/js/services/AuthService/GoogleAuthService.js` to isolate all Google authentication API requests (`start`, `exchangeWebCode`, `exchangeMobileGoogleCode`, `confirmLink`, `startRecovery`, `confirmRecovery`, `startLink`, `unlink`).
   - Refactored `resources/js/services/AuthService/AuthService.js` to delegate to `GoogleAuthService`, maintaining backwards compatibility with any existing consumers.
   - In `resources/js/views/auth/GoggleCallback.vue`, added a processing guard (`processing = ref(false)`) to prevent duplicate callback exchanges on component remount.

2. **Backend Account Resolution & Linkage:**
   - In `app/Services/GoogleAccountService.php`, added `hasHistoricalGoogleLink(User $user): bool` to recognize previously established Google linkages (historical `google-auth-token` or `google-mobile-auth-token` in `personal_access_tokens`, or stored `google_avatar_url`).
   - If an account has an established Google linkage and no usable password credential, `existingAccount()` immediately links the verified Google subject (`google_id`) and allows direct sign-in.
   - In `app/Services/GoogleLegacyRecoveryService.php`, removed the unnecessary check for `facebook_id`.

---

## 4. October 4 Google Behavior Restored

The October 4 (`132bb49`) user experience was:
**Continue with Google → Select Google Account → Automatically Sign In to Scemory.**

### Resolution Pathways

1. **Securely Recognized Existing Google Users (`google_id` matches verified `sub`):**
   - Immediate automatic login.
   - Original user ID preserved with all relationships (purchases, subscriptions, media, wallet balance).
   - No Scemory password prompt.
   - No account linking screen.
   - No profile completion modal.
   - No OTP required.
   - Direct redirect to the localized homepage (`/{lang}/home` or `/admin`).

2. **Eligible New Google Users:**
   - Account automatically created with verified email, `role = 'user'`, `licence_type_id = 1`, and `is_active = true`.
   - Sanctum token created in POST exchange response.
   - Automatic login and redirect to homepage.

3. **Historical Google Accounts (Created on or before October 4):**
   - On October 4, Google accounts were created with `Hash::make('password')` and Sanctum personal access tokens named `google-auth-token` or `google-mobile-auth-token`.
   - `hasHistoricalGoogleLink()` recognizes these authentic historical Google accounts.
   - The verified Google subject (`google_id`) is linked automatically without requiring the historical placeholder password (`password`) or an email recovery code.
   - Subsequent logins match the Google subject directly.
   - For ambiguous accounts with no established Google link and no usable password, the secure one-time mailbox proof (`recovery_required`) remains available as a fallback.
   - For accounts with a custom user-chosen password, password confirmation (`linking_required`) is enforced to prevent email-claim takeovers.

---

## 5. Exact Runtime Code Changes

### A. `app/Services/GoogleAccountService.php`
- Added `hasHistoricalGoogleLink(User $user): bool` method.
- Updated `existingAccount()` to recognize accounts with historical Google linkage and link them directly.

```php
    public function hasHistoricalGoogleLink(User $user): bool
    {
        if ($user->google_id !== null) {
            return true;
        }

        if (! empty($user->google_avatar_url)) {
            return true;
        }

        if ($user->tokens()->whereIn('name', ['google-auth-token', 'google-mobile-auth-token'])->exists()) {
            return true;
        }

        return false;
    }
```

### B. `resources/js/services/AuthService/GoogleAuthService.js`
- Created dedicated Google frontend service encapsulating all API endpoints for Google OAuth start, web code exchange, mobile exchange, linking, recovery, and unlinking.

### C. `resources/js/services/AuthService/AuthService.js`
- Delegated all Google methods to `GoogleAuthService`.

### D. `resources/js/views/auth/GoggleCallback.vue`
- Added `processing = ref(false)` guard in `onMounted()` to eliminate duplicate code exchanges on remount.

### E. `tests/Feature/Api/Auth/GoogleWebAuthTest.php`
- Added `test_historical_google_account_with_established_token_signs_in_directly()`.

### F. `tests/Frontend/googleAuthService.test.js`
- Added test coverage for `GoogleAuthService.start()`.

---

## 6. List of Modified Files

- `app/Services/GoogleAccountService.php`
- `resources/js/services/AuthService/GoogleAuthService.js` (New)
- `resources/js/services/AuthService/AuthService.js`
- `resources/js/views/auth/GoggleCallback.vue`
- `tests/Feature/Api/Auth/GoogleWebAuthTest.php`
- `tests/Frontend/googleAuthService.test.js`

*Pre-existing uncommitted files preserved untouched:*
- `entrypoint.sh`
- `resources/js/components/auth/FacebookAuthButton.vue`

---

## 7. Confirmation That Facebook Remains Unchanged

- `FacebookAuthController.php` was **NOT** modified.
- `FacebookAccountService.php` was **NOT** modified.
- Facebook routes, Socialite settings, credentials, and token exchanges are **100% UNCHANGED**.
- `FacebookAuthButton.vue` was **NOT** modified.
- All 24 backend Facebook tests in `Tests\Feature\Api\Auth\FacebookAuthTest` passed (257 assertions).
- All 27 frontend Facebook tests in `tests/Frontend/facebookAuth.test.js` passed.

---

## 8. Confirmation That Post-October-4 Features Are Preserved

All features developed after October 4 remain completely intact:
- Plans and Subscriptions (`Tests\Feature\Api\Plans\*`)
- Media Tiers and Quotas (`Tests\Feature\Api\Plans\PlanTierClassificationTest`)
- Marketplace, Cart & Purchases (`Tests\Feature\Api\Security\AccessAndFinanceTest`)
- Wallet and PayPal Integrations (`Tests\Unit\PayPalWebhookVerifierTest`)
- Virtual Gifts and Creator Earnings (`Tests\Feature\Security\SessionsAndReservationsTest`)
- Events and Moderation (`Tests\Feature\Api\Moderation\*`)
- Weekly Ranking & Leaderboards (`Tests\Feature\Points\WeeklyRolloverTest`)

---

## 9. Backend Test Results

Ran `php artisan test`:
- **Total Tests:** 367 passed (9 skipped concurrency opt-ins)
- **Total Assertions:** 2,783 assertions
- **Google Web Auth Tests:** 33 passed (including new historical direct login test)
- **Google Mobile Tests:** 9 passed
- **Google Security Tests:** 3 passed
- **Facebook Auth Tests:** 24 passed

---

## 10. Frontend Test Results

Ran `npm run test` (Vitest):
- **Test Files:** 20 passed (20)
- **Total Tests:** 183 passed (183)
- All 13 localized language test suites passed without errors.

---

## 11. Build Results

Ran `npm run build` (Vite v6.4.3):
- Build completed successfully in 21.85 seconds with code 0.
- All assets and manifest compiled to `public/build/`.

---

## 12. Live OAuth Verification Status

Tested against development server (`https://dev.scemory.com`):
- `GET https://dev.scemory.com/api/v1/users/google-login?lang=en` returns HTTP 200 with status `success` and a valid Google Accounts OAuth authorization URL:
  - `client_id`: `72566981375-79oe0bb4q153ce5nvu0hdfkv09v2kl04.apps.googleusercontent.com`
  - `redirect_uri`: `https://dev.scemory.com/api/v1/users/google-callback`
  - `scope`: `openid profile email`
  - `code_challenge_method`: `S256`
- Live OAuth configuration is operational on `dev.scemory.com`.

---

## 13. Any Remaining Runtime Configuration Blockers

- Local WSL development environment has empty `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `.env`.
- Live `dev.scemory.com` server already has live credentials configured.
- No database migrations or schema alterations are required.

---

## 14. Exact Manual Deployment Steps

To deploy this fix to `dev.scemory.com`:

1. Review changes:
   ```bash
   git status
   git diff
   ```
2. Commit the Google isolation and restoration changes:
   ```bash
   git add app/Services/GoogleAccountService.php \
           resources/js/services/AuthService/GoogleAuthService.js \
           resources/js/services/AuthService/AuthService.js \
           resources/js/views/auth/GoggleCallback.vue \
           tests/Feature/Api/Auth/GoogleWebAuthTest.php \
           tests/Frontend/googleAuthService.test.js \
           docs/GOOGLE_AUTH_ISOLATION_AND_RESTORATION.md
   git commit -m "Restore seamless October 4 Google login and isolate Google auth service"
   ```
3. Push to `origin main`:
   ```bash
   git push origin main
   ```
4. On `dev.scemory.com`:
   ```bash
   cd /path/to/Scene_memory
   git pull origin main
   npm run build
   php artisan optimize:clear
   ```
