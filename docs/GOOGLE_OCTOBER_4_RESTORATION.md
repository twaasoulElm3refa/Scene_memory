# Google October 4 restoration review

- **Reference commit:** `132bb49eb5fc6d001a0d8265a0b4618d7940c5e5`
- **Reference date:** 2026-10-04 12:17:21 +03:00
- **Audited current commit:** `31ee81e735350b2882ec79aeb33bdf1927510afa`
- **Review date:** 2026-10-24

## Outcome

No October 4 runtime source was copied into the current application. Current HEAD already implements the smallest safe Google-only compatibility flow that provides the requested direct experience for securely recognized users:

`Continue with Google → choose Google account → direct Scemory login`

This direct path applies to:

- an active existing account whose `google_id` equals Google's verified stable subject; and
- a new verified Google identity that has no provider-ID or email collision.

The remaining October 4 behavior that could make every email match one-click cannot be restored without violating the task's explicit security requirements. October 4 treated a verified email match as account ownership, did not bind the row to Google's stable subject, used stateless web OAuth, reactivated existing rows, and placed the Sanctum bearer token in the redirect URL. Restoring those lines would reintroduce account takeover, suspended-account bypass, OAuth-state, and credential-leak risks.

Current HEAD already includes the required safe compatibility adaptation for historical Google-only rows: one session-bound, rate-limited mailbox proof persists the verified Google subject on the original row. Later Google logins are direct. A historical token name or shared placeholder password hash is not treated as ownership proof.

Because no safe missing runtime delta was found, this report is the only file created by this restoration task. No Google, Facebook, email/password, financial, dashboard, route, migration, environment, or credential source was changed.

## Pre-modification protection

Before review, the repository was at:

`31ee81e735350b2882ec79aeb33bdf1927510afa`

The working tree already contained:

- `entrypoint.sh` — modified;
- `resources/js/components/auth/FacebookAuthButton.vue` — modified;
- `docs/SCEMORY_GOOGLE_AUTH_DIAGNOSTIC.md` — untracked.

Those changes were not overwritten or altered. A non-destructive pre-task reference was created at:

`/tmp/20261024-google-oct4-pre`

It contains the original HEAD, porcelain status, a binary patch of tracked changes, the untracked-file list, and a copy of the existing Google diagnostic report. No stash, reset, checkout, restore, or revert was used.

## 1. Original October 4 implementation

The historical controller at `132bb49:app/Http/Controllers/api/auth/GoogleAuthController.php` performed the following web flow:

1. `googleLogin()` used `Socialite::driver('google')->stateless()` and returned Google's authorization URL (historical lines 29-50).
2. `googleCallback()` called the stateless Socialite user exchange (line 72).
3. It resolved the account using `firstOrCreateByEmail()` (lines 73-83).
4. New Google users received `Hash::make('password')`, role `user`, active status, verified email, and licence type 1 (lines 75-82).
5. Existing rows were forced active and had their verification/login timestamps updated (lines 84-90).
6. The callback created a Sanctum `google-auth-token` (line 109).
7. The frontend redirect contained the bearer token, role, and profile flag in its query string (lines 127-137).
8. A callback exception was placed in the frontend URL through `error={$e->getMessage()}` (lines 139-163).

Historical routes at `132bb49:routes/api.php:89-92` exposed:

- `GET /api/v1/users/google-login` with `guest`;
- `GET /api/v1/users/google-callback` with `guest`;
- `POST /api/v1/users/google-mobile-exchange` with throttling.

The historical callback component accepted a query-string token, stripped it from visible browser history, stored it, and redirected (`132bb49:resources/js/views/auth/GoggleCallback.vue:74-164`). Removing it from history after page load did not prevent the URL from reaching browser history, server/proxy logs, monitoring, or referrer handling before the component ran.

The October 4 implementation did not store Google's provider subject. Its direct user experience came from automatically claiming any row whose email matched the email returned by Google.

## 2. Current implementation differences

Current Google authentication is split across:

- `app/Http/Controllers/api/auth/GoogleAuthController.php`
- `app/Services/GoogleAccountService.php`
- `app/Services/GoogleLegacyRecoveryService.php`
- `resources/js/services/googleAuth.js`
- `resources/js/services/AuthService/AuthService.js`
- `resources/js/views/auth/GoggleCallback.vue`
- `resources/js/components/auth/GoogleAuthButton.vue`
- `routes/api.php:110-120`

The current web flow:

1. creates a server-correlated OAuth attempt and enables PKCE;
2. validates the callback state before exchanging the provider code;
3. requires Google's stable `sub`, matching Socialite ID, boolean `email_verified: true`, and a valid email;
4. resolves `google_id` before considering email;
5. rejects inactive/deleted accounts and conflicting provider subjects;
6. redirects with a random five-minute delivery code rather than a bearer token;
7. binds that code to the browser session and consumes it under a cache lock;
8. re-resolves or creates the account during exchange;
9. returns the Sanctum token in a no-store JSON body;
10. redirects directly after Vue establishes the existing authentication state.

New eligible users do not receive a profile-completion, country, birth-date, application-password, or OTP screen. Existing accounts with a matching `google_id` also sign in directly.

## 3. Exact code restored

No historical runtime code was restored because every missing October 4 behavior is either already represented safely by current HEAD or would violate an explicit acceptance constraint.

The current implementation already restores the intended behavior rather than the unsafe mechanism:

| October 4 intent | Current safe equivalent |
|---|---|
| Google account selection | Socialite authorization URL from `googleLogin()` |
| Existing Google user enters directly | Stable `google_id` lookup in `GoogleAccountService::existingAccount()` |
| New Google user created automatically | Locked transactional creation in `GoogleAccountService::complete()` |
| Sanctum authentication | Session-bound exchange endpoint returns token in JSON |
| Automatic frontend redirect | `GoggleCallback.vue::completeSignIn()` updates auth state and redirects |
| Preserve original legacy row | One-time recovery links the verified subject to that row, preserving its ID and relationships |

Copying the October 4 controller, route, service, or callback files wholesale would also remove later native authentication, i18n, status checks, account-conflict handling, recovery, CSRF, session binding, and token-confidentiality protections. It would overwrite newer unrelated integration points, so no broad historical checkout was performed.

## 4. Compatibility adaptations retained

### Existing linked accounts

`GoogleAccountService::existingAccount()` first finds `users.google_id === verified sub`. Active rows proceed directly; their name, email, role, password, wallet, plans, credits, purchases, and relationships remain on the same record.

### New accounts

When no subject or email collision exists, the exchange creates a basic passwordless account with the provider subject and verified email. It does not request country, date of birth, profile completion, another OTP, or an application password.

### Historical October 4 Google-only accounts

October 4 rows have no authoritative provider subject and commonly contain a shared placeholder hash. Current code classifies null, empty, and known placeholder credentials as unusable. It does not display an impossible password form for those rows.

After a valid state-checked Google callback, an eligible historical row can complete one mailbox proof. The recovery code is random, stored only as a hash, expires after ten minutes, is limited to five attempts, has a send cooldown, is bound to the browser session, and is refused when the mail transport could log the message body. Success stores `google_id` on the original row. Every later Google login is direct.

### Genuine password or provider collision

A row with a real password requires that password once before Google is linked. A Facebook-bound passwordless row must be authenticated through its existing provider and linked from the profile. A different existing Google subject is rejected. These steps are ownership controls, not normal Google onboarding.

## 5. Historical security risks and treatment

| Historical behavior | Risk | Current treatment |
|---|---|---|
| `firstOrCreateByEmail()` for every callback | A current holder of a reused/reassigned email could claim an unrelated account | Provider subject is authoritative; ambiguous email collision requires independent ownership proof |
| Stateless web Socialite | No server-correlated web OAuth state | Session attempt, state validation, expiry, and PKCE |
| No stored Google subject | Google identity could not be recognized independently of email | Nullable unique `google_id` stores verified `sub` |
| Force `is_active=true` | Suspended/deleted accounts could be restored by login | Status checked at callback, exchange, link, and recovery |
| Shared placeholder password | Known value could be mistaken for ownership proof | Known placeholders are explicitly rejected |
| Sanctum token in redirect query | Exposure through URL/history/logs/referrers/monitoring | Short-lived session-bound code in URL; bearer token only in JSON body |
| Raw exception text in callback URL | Internal/provider details could leak | Stable allowlisted error codes and exception-class-only logs |
| No concurrent email/subject controls | Duplicate or incorrectly claimed accounts during races | Unique subject, cache locks, transaction, and unique-violation re-resolution |

The requirement for direct login cannot safely cover an unmapped historical row on its first post-migration login. Email, a historical Sanctum token name, a placeholder hash, timestamps, or other circumstantial row attributes cannot prove that the present Google subject owned the historical account. The one-time recovery step is the smallest safe exception.

## 6. Files modified by this task

Created:

- `docs/GOOGLE_OCTOBER_4_RESTORATION.md` — records the historical comparison, security conflict, current compatibility implementation, validation, and deployment procedure.

No runtime file was modified.

The pre-existing modifications to `entrypoint.sh` and `resources/js/components/auth/FacebookAuthButton.vue`, plus the pre-existing untracked `docs/SCEMORY_GOOGLE_AUTH_DIAGNOSTIC.md`, remain outside this task.

## 7. Facebook preservation

No Facebook controller, route, service, configuration, credential, callback, registration logic, test, translation, or style was modified by this task.

`resources/js/components/auth/FacebookAuthButton.vue` was already modified before this task began. Its working-tree diff was captured in the pre-task backup and left byte-for-byte outside this restoration work.

The focused backend Facebook suite and frontend Facebook suites passed after the review.

## 8. Preservation of post-October 4 features

No code for Plans, subscriptions, media tiers, permanent photo/video quotas, Marketplace, Wallet, PayPal, creator earnings, commission calculations, credit counters, user dashboards, admin dashboards, or other post-October 4 features was changed.

No migration, dependency, route, environment file, credential, database record, or built asset was committed or edited. No Git reset, revert, checkout, or restore was performed.

## 9. Test results

### Backend

Command:

```text
php artisan test --compact \
  tests/Feature/Api/Auth/GoogleWebAuthTest.php \
  tests/Feature/Api/GoogleMobileExchangeTest.php \
  tests/Feature/Security/GoogleAccountSecurityTest.php \
  tests/Feature/Api/Auth/FacebookAuthTest.php \
  tests/Feature/Api/Auth/AuthApiTest.php \
  tests/Feature/Api/Auth/RegisterOtpApiTest.php
```

Result: **87 passed, 850 assertions**.

Coverage includes Google initiation, state/PKCE, provider callback, verified identity, direct linked login, new-user creation, Sanctum exchange and protected-route use, disabled accounts, subject/email conflicts, historical recovery, real-password linking, native exchange, Facebook regression, email/password login, registration, and OTP behavior.

Google provider token and user-info calls are mocked. These tests do not prove that the Google console, live credentials, reverse proxy, session cookie, or development deployment is configured correctly.

### Frontend

Command:

```text
npx vitest run \
  tests/Frontend/googleAuth.test.js \
  tests/Frontend/googleAuthService.test.js \
  tests/Frontend/facebookAuth.test.js \
  tests/Frontend/facebookAuthService.test.js \
  tests/Frontend/authTokenStorage.test.js
```

Result: **5 files passed, 68 tests passed**.

Coverage includes Google button behavior, callback code exchange, direct authenticated redirect, rejection of legacy URL tokens, legacy recovery, password collision handling, Facebook UI/service regression, and token storage behavior.

### Production build

Command: `npm run build`

Result: **passed**; Vite transformed 1,151 modules and completed the production build. Existing Sass deprecation warnings and large-chunk warnings remain. The build produced no tracked working-tree changes.

No actual Google OAuth flow, live user creation, production session, or production token was created.

## 10. Remaining limitations

- The current local `.env` does not contain Google client ID or client secret settings, so local Google initiation returns `unavailable`. Credentials were not added or changed because environment and credential changes were prohibited.
- Static frontend assets can indicate deployed client behavior but cannot prove the deployed Laravel revision or effective configuration.
- A historical row without `google_id` needs one ownership proof. Making that first login silent would require an authoritative old-row-to-Google-subject mapping that the October 4 implementation never stored.
- Legacy recovery requires access to the account's registered mailbox and a real non-logging mail transport. The local logging mailer is deliberately refused.
- Browser bearer tokens remain in localStorage under the application's existing architecture. That was not changed by this Google-only restoration review.
- `https://dev.scemory.com` was not declared working because no live login was performed.

## 11. Manual deployment instructions

There is no new runtime code from this task to deploy. If `dev.scemory.com` does not already run current HEAD, deploy the reviewed current Google implementation through the normal release process rather than copying October 4 files.

Before a controlled smoke test:

1. Verify the deployed release SHA and ensure it includes the current `GoogleAuthController`, `GoogleAccountService`, `GoogleLegacyRecoveryService`, Google routes, and current Vue callback.
2. Verify presence without printing values of `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`.
3. Verify `GOOGLE_REDIRECT_URI` exactly equals `https://dev.scemory.com/api/v1/users/google-callback` and that the same value is registered in Google Cloud Console.
4. Verify `FRONTEND_URL=https://dev.scemory.com`, the configured mobile app link is valid, and HTTPS/proxy/session-cookie settings preserve the browser session through Google's callback.
5. Verify shared, writable session and cache stores and a non-logging mail transport for legacy recovery.
6. Rebuild Laravel configuration/route caches and frontend assets only through the authorized deployment procedure.
7. Run a controlled smoke test for an existing linked account, a new test identity, and a controlled historical fixture. Confirm direct login for the first two, one-time recovery for the historical fixture, preservation of its user ID/data, and direct login on its second attempt.
8. Run Facebook and email/password smoke tests without changing their configuration.

No deployment, production migration, credential modification, or live OAuth test was performed by this task.
