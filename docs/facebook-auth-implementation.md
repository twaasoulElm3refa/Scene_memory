# Facebook authentication: direct sign-in and registration

Updated on 22 October 2026. Facebook now authenticates eligible users directly after the verified OAuth callback and protected Sanctum exchange. The normal flow has no account-completion form, country/birth-date requirement, or email-code step. Google authentication and production credentials were not changed. Nothing was deployed and no persistent database migration was executed.

## Findings and reproduced failure

The previous implementation introduced an extra registration gate:

```text
Facebook authorization succeeds
  -> stateful Socialite callback verifies provider identity
  -> session-bound delivery code reaches Vue
  -> POST facebook-exchange returns profile_required
  -> Vue asks for email/country/birth date
  -> form submission calls RegistrationOtpService::send()
  -> log-only mailer or mail delivery failure raises RegistrationOtpException
  -> controller returns email_unavailable with HTTP 503
  -> SecurityResponseHeaders replaces that key with unavailable
  -> Vue displays "Facebook authentication is currently unavailable..."
```

That path explains why successful Meta authorization could still fail when the extra form was submitted. The form and mail dependency were introduced by the earlier Facebook implementation; they were not requirements of Laravel's standard registration validation. `registerRequest` treats country and birth date as nullable, the database permits both to be null, and no basic-account eligibility/age gate was found in the application's implementation. Feature-specific profile validation remains unchanged.

Evidence was checked without printing secrets, tokens, emails, names, raw exception messages, or full log entries:

- `config/mail.php` defaults to the `log` transport. `RegistrationOtpService` deliberately rejects that transport for verification codes.
- The available local log contains a **testing** entry for `Registration OTP email mailer is unsafe for OTP delivery`, with transport `log`. Facebook provider failures in that log are also testing fixtures. These entries are not evidence of a live Meta failure.
- This checkout has no runtime `.env` and no cached configuration. Values in `.env.example` do not configure a normal Laravel process. Local CLI configuration was inspected through redacted presence/type checks. The successful authorization described by the user necessarily uses deployment configuration not available here.
- The old regression test explicitly expected the profile submission to fail with HTTP 503 when `mail.default=log`. It has been replaced with a regression proving that the same Facebook registration now succeeds with `app.debug=false` and the log mailer, without sending or queuing mail.

**Limit of the diagnosis:** the unnecessary completion/mail branch and its error rewriting are confirmed from code and local tests. The deployed mail transport, SMTP error, or exact live exception cannot be established from the local testing logs. No claim is made that the live OAuth credentials or Meta configuration are broken, and neither was changed.

## Current request flow

```text
Continue with Facebook
  -> GET /api/v1/users/facebook-login
  -> Facebook authorization
  -> GET /api/v1/users/facebook-callback
  -> session state validation and server-side Socialite identity retrieval
  -> opaque, session-bound delivery code
  -> Vue removes callback parameters from history
  -> CSRF cookie initialization and POST /api/v1/users/facebook-exchange
  -> existing account lookup by Facebook ID, or automatic account creation
  -> ordinary Sanctum bearer token in the JSON response
  -> existing token storage, Navbar login event, trusted local redirect
```

The existing state/CSRF/session protections, ten-minute OAuth attempt, fifteen-minute delivery deadline, single-use code, cache locks, HTTPS validation, request throttles, cancellation handling, safe error keys, local return-path validation, and logout behavior remain in place. The callback never puts an authentication token in a URL. Socialite exchanges the authorization code using the configured app credentials and obtains `/me` server-side; frontend IDs, emails, roles, profile fields, and old OTP parameters cannot select or establish an identity.

Facebook's state remains isolated from Socialite's shared state key. Pending Google state, its PKCE verifier, and Google session metadata are preserved. Facebook does not use `stateless()`.

## Automatic registration and account safety

- Accounts are resolved by the authenticated, app-scoped Facebook provider ID. Existing linked accounts retain their role, password, profile, and all relationships. Active Facebook-linked accounts can authenticate even when local email verification is pending; the Facebook identity is the credential.
- New users receive only role `user`, the existing default licence when available, the provider display name and safe HTTPS avatar, and a null password. Country and birth date stay null. They are not fabricated or requested during login.
- A valid available provider email is stored as contact information. This provider flow supplies no explicit email-verification evidence, so it does **not** set `email_verified_at` from Facebook's unrelated `verified` field.
- If Facebook provides no usable email, the account is created with `email=null`. No fake mailbox or extra form is introduced. Multiple email-less accounts remain distinct by their unique Facebook IDs. The profile view already displays missing contact/profile values safely.
- Existing email matches never automatically link or merge accounts. Password and Google accounts remain protected, including a concurrent email claim between callback and exchange. Existing accounts without the correct Facebook ID must use their original sign-in method; explicit linking is still unavailable.
- Disabled and deleted users remain blocked at callback and exchange. No existing account is reactivated. Database transactions, unique constraints, and locks prevent duplicate provider accounts.
- The Facebook account service no longer sends verification codes or welcome mail. Mail configuration, SMTP availability, and mail logging cannot block basic Facebook authentication or cause provider profile details to be written into mail logs by this flow.

Unverified contact data must not silently become another login/recovery credential. `ProtectFacebookEmailRecovery` therefore restricts anonymous registration, email/password login, registration OTP, and password-reset operations **only for Facebook-linked accounts whose email is still unverified**. The already authenticated active owner may later use the existing email-verification flow when setting up an email-based method. Other users and Google OAuth endpoints are unaffected. Normal Facebook login never asks for that verification.

The existing email/password controller, password rules, registration OTP service, Google controllers/services/callbacks/buttons, and feature-specific profile requirements were reused without modification.

## UI and languages

`FacebookCallback.vue` now contains only a localized signing-in state and safe error/retry/navigation states. Its profile form, email input, country input, birth-date input, OTP form, resend timer, and onboarding state handling have been removed. `FacebookAuthService.exchange()` sends only the delivery code.

Successful authentication always follows the existing home/admin or trusted saved destination, including when `is_profile_complete=false` or `user.email=null`. Profile completeness remains descriptive state; it is not a login gate. The existing shared Facebook buttons, all 13 locale dictionaries, and RTL support are retained. No new translation system or untranslated user-facing message was introduced.

This is the existing browser flow, including mobile browsers. Native Capacitor Facebook/app-link support remains outside this change; the native Google flow is unchanged.

## Migration and release requirement

New migration:

`database/migrations/2026_10_22_000003_allow_email_less_social_accounts.php`

It makes `users.email` nullable while preserving existing emails, password hashes, provider IDs, roles, status, and the unique-email constraint. Rollback refuses to impose NOT NULL while email-less accounts exist, instead of deleting users or fabricating addresses. The earlier Facebook identity migration remains unchanged.

Apply this migration through the normal release process **before enabling email-less registration**:

```sh
php artisan migrate --path=database/migrations/2026_10_22_000003_allow_email_less_social_accounts.php --force
```

Review and back up the target database first and test the schema alteration on its actual database engine. Local migrations ran only in PHPUnit's in-memory SQLite database. No staging or production migration was run.

Release the changed backend and frontend together, since the removed onboarding response states belong to the previous implementation. Rebuild frontend assets and refresh the server's configuration caches using the normal deployment workflow. Do not change the working Google settings or the Laravel application key.

## Facebook configuration retained

**Exact callback path:** `/api/v1/users/facebook-callback`

**Production callback URL:** `https://scemory.com/api/v1/users/facebook-callback`

The production origin is supported by `.env.mobile-production` (`VITE_API_URL=https://scemory.com`); the live server's private configuration was not inspected. Use the exact deployed origin for staging, and keep Meta's Valid OAuth Redirect URIs identical to that environment's server `FACEBOOK_REDIRECT_URI`.

Required variable names remain:

```text
FACEBOOK_CLIENT_ID
FACEBOOK_CLIENT_SECRET
FACEBOOK_REDIRECT_URI
```

`config/services.php`, credentials, callback paths, and Meta settings were not changed. Real secrets must stay in the untracked runtime environment; none are reproduced in this report. The implementation continues to use the installed [Laravel Socialite stateful OAuth flow](https://laravel.com/docs/12.x/socialite). Meta's public user-reference documentation returned HTTP 429 during this inspection, so no additional email-verification semantics were assumed from it.

## Files changed in this fix

- `app/Http/Controllers/api/auth/FacebookAuthController.php`: removes the forced registration/email-challenge branch and dependency.
- `app/Services/FacebookAccountService.php`: creates users directly from authenticated provider identity, permits active unverified-email linked users, supports null contact email, and removes mail side effects.
- `app/Http/Middleware/ProtectFacebookEmailRecovery.php`: protects unverified Facebook contact email from anonymous account recovery/claiming.
- `routes/api.php`: adds that Facebook-specific recovery guard to the existing local email-authentication routes; Facebook and Google OAuth route declarations are unchanged.
- `database/migrations/2026_10_22_000003_allow_email_less_social_accounts.php`: preserves accounts while supporting missing provider email.
- `resources/js/views/auth/FacebookCallback.vue` and `resources/js/services/AuthService/FacebookAuthService.js`: direct code exchange and redirect, without onboarding fields.
- `tests/Feature/Api/Auth/FacebookAuthTest.php`, `tests/Frontend/facebookAuth.test.js`, and `tests/Frontend/facebookAuthService.test.js`: replace obsolete onboarding expectations with direct-login/security regressions.
- This report supersedes the prior forced-onboarding implementation report.

A pre-change snapshot verifies that **15 Google-related files, all Google OAuth route declarations, and all 13 Google translation namespaces are unchanged**. This includes Google's controller, account service, callback, button, token storage, native bootstrap, frontend auth service, provider configuration, and existing tests. No credentials or environment files were edited.

## Validation

| Check | Result |
| --- | --- |
| Full PHP/Laravel suite | 344 total; 335 passed, 9 existing skips; 2,500 assertions; no failures |
| Facebook backend tests | 24 tests: direct new/returning login, no mail dependency, null/missing email, no fabricated profile, unverified-email recovery isolation, verified owner setup, conflicts/races, suspended users, CSRF/state, replay/expiry, logout, migrations, Google state preservation |
| Full frontend/Vitest suite | 177 passed in 20 files |
| Facebook frontend tests | 30 tests across UI and transport, including immediate redirect with incomplete profile, null email, all locales/RTL, no completion inputs, safe retry/errors, CSRF, token state, and trusted destinations |
| Production Vite build | Passed; existing Sass deprecation and large-chunk warnings remain |
| Formatting/whitespace | Laravel Pint and Git whitespace checks passed for changed code |

The nine existing skips are seven MySQL `VirtualGiftConcurrencyTest` cases and two MySQL `WeeklyConcurrencyTest` cases. No unrelated failures were hidden. Tests use mocked provider HTTP responses and synthetic test configuration; no real OAuth credentials are required or used.

The frontend tests use jsdom, not a real-device layout audit. Live staging cookies, Meta consent and provider data, and deployment database behavior still require a smoke test after release. This checkout cannot establish the exact live mail exception, and the revised login no longer depends on that mail branch.
