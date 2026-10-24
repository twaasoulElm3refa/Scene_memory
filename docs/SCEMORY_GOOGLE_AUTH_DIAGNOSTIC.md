# Scemory Google authentication diagnostic

**Audit date:** 2026-10-24  
**Repository:** `/home/development/projects/Scene_memory`  
**Audited revision:** `31ee81e735350b2882ec79aeb33bdf1927510afa` (`main`, equal to `origin/main` when inspected)  
**Scope:** Google authentication only; diagnostic and read-only except for this report

## 1. Executive summary

The current source implements a security-focused Google OAuth flow for web and native clients. It validates OAuth state, uses PKCE, requires Google's stable subject (`sub`) and a boolean `email_verified: true`, stores `google_id`, blocks inactive/deleted accounts, redirects the browser with a short-lived exchange code instead of a Sanctum bearer token, and rechecks account state while completing the exchange.

Two separate failure classes explain the reported experience:

1. **The local checkout is confirmed to return `unavailable` before contacting Google.** Its active `.env` has no `GOOGLE_CLIENT_ID` or `GOOGLE_CLIENT_SECRET`. `GoogleAuthController::configured()` requires both, so `GET /api/v1/users/google-login` returns HTTP 503 with `error_code: unavailable`. No cached Laravel configuration file was present when inspected. This is a confirmed local configuration cause, not proof of the development server's runtime configuration.
2. **The password-link screen was introduced by the secure account-resolution refactor.** Historical Google registrations were found by email, were assigned a shared placeholder password hash, and did not store Google's stable subject. The `google_id` migration could not backfill data that had never been stored. The first secure resolver therefore treated every matching email without `google_id` as an unlinked account and required a Scemory password. A historical Google-only user did not know the placeholder and the password verifier deliberately rejected it, making that screen impossible to complete.

Current HEAD (`31ee81e`) changes that legacy classification. A matching account with a real password still receives the password proof screen; a passwordless account tied to Facebook must use that existing method; a passwordless account with no provider binding receives a one-time, session-bound email recovery flow; and a genuine `google_id` match signs in directly. This repairs the specific impossible-password classification in source, but it does not prove the deployed backend is running that revision or has a working mail/session/cache configuration.

Static assets served by `https://dev.scemory.com` at audit time contained the current recovery routes and both `recovery_required` and `linking_required` branches, and did not contain the removed `profile_required` branch. That shows the deployed frontend resembles current HEAD. The backend revision and runtime environment cannot be established from public static assets. No live OAuth request was made because it would create OAuth/session state and was prohibited by the audit scope.

The smallest safe path is to deploy and configure the current provider-ID-based flow correctly, verify the backend revision and sanitized runtime configuration, and retain the one-time recovery step only for truly ambiguous historical rows. Direct email-only linking must not be restored.

## 2. Current architecture

The web flow has four trust boundaries:

- Vue starts OAuth through the same-origin Laravel API.
- Laravel creates a server-side session attempt, lets Socialite build the Google URL, and later validates the returned state and exchanges the authorization code with Google.
- Laravel reduces the provider response to a verified identity `{sub, email, name, avatar}`, resolves account conflicts, and stores only that identity behind a random five-minute delivery code.
- Vue exchanges that code in the same browser session. Laravel binds the code to the session ID, resolves or creates the account under cache locks, and returns a Sanctum bearer token in the JSON response body.

Native clients use encrypted, cached, single-use OAuth state and a PKCE-style verifier for the delivery exchange because they cannot share the browser's Laravel session. The detailed native path is present in the same controller but is secondary to the reported web failure.

Key source locations:

| Concern | Source |
|---|---|
| Login/register UI and click handler | `resources/js/views/auth/register.vue:575-589` |
| Reusable Google button | `resources/js/components/auth/GoogleAuthButton.vue:1-35` |
| Frontend OAuth start and error allowlist | `resources/js/services/googleAuth.js:7-55` |
| Frontend API methods | `resources/js/services/AuthService/AuthService.js:29-63` |
| Frontend callback | `resources/js/views/auth/GoggleCallback.vue:1-170` |
| Frontend callback route | `resources/js/router/index.js:342-345` |
| Laravel routes | `routes/api.php:110-120` |
| OAuth controller | `app/Http/Controllers/api/auth/GoogleAuthController.php:22-510` |
| Identity/account resolver | `app/Services/GoogleAccountService.php:18-175` |
| Legacy recovery | `app/Services/GoogleLegacyRecoveryService.php:14-101` |
| User authentication model | `app/Models/User.php:13-49` |
| Google identity migration | `database/migrations/2026_10_22_000001_add_google_identity_to_users.php:9-24` |
| Socialite settings | `config/services.php:23-28` |
| Session settings | `config/session.php:21-37,159-202` |
| Sanctum settings | `config/sanctum.php:18-50` |

## 3. Complete web request and redirect flow

The `api` middleware group applies because these routes are declared in `routes/api.php`. The route-specific `web` middleware adds cookie session and CSRF behavior. Application-wide API middleware is configured in `bootstrap/app.php:18-23`.

| # | Step and source | Input | Success output | Failure conditions |
|---:|---|---|---|---|
| 1 | User clicks `GoogleAuthButton`; `resources/js/components/auth/GoogleAuthButton.vue:1-20` | Click while enabled | Emits `continue` | Disabled/loading prevents the click |
| 2 | `handleGoogleLogin`; `resources/js/views/auth/register.vue:575-589` | Current language and requested local redirect | Stores language and safe post-auth destination, then calls `startGoogleAuth()` | Every thrown error without a backend `error_code` is displayed as `googleAuth.unavailable` |
| 3 | `startGoogleAuth`; `resources/js/services/googleAuth.js:30-55` | Normalized language | Calls `AuthService.googleLogin({lang})`; accepts only an `https://accounts.google.com` URL | Network/HTML/proxy errors, malformed URL, or unexpected origin throw; the caller normally maps these to `unavailable` |
| 4 | `AuthService.googleLogin`; `resources/js/services/AuthService/AuthService.js:29-31` | `GET /api/v1/users/google-login?lang=...`, `Accept: application/json` | Axios response containing `{status:"success", url}` | Any non-2xx response rejects |
| 5 | Laravel route; `routes/api.php:110` | Same-origin GET | Invokes `GoogleAuthController::googleLogin` | Middleware may reject/rate-limit; effective middleware is `api`, `web`, `guest`, `throttle:10,1` |
| 6 | Configuration and Socialite initialization; `GoogleAuthController.php:31-54` | Session, language, Google config | Regenerates session, stores `google_oauth` with ten-minute expiry, enables PKCE, asks Socialite for target URL | Missing/unsafe config returns 503 `unavailable`; validation or Socialite exception also fails |
| 7 | Browser navigation; `googleAuth.js:49-53` | Google authorization URL | `window.location.assign(url)` opens Google | Frontend refuses a non-Google origin |
| 8 | Google authorization response | Provider consent result | Redirect to configured backend callback with `code` and `state`, or `error` | Cancellation produces `access_denied`; provider/config errors may return another error or reject code exchange |
| 9 | Callback route; `routes/api.php:111` | `GET /api/v1/users/google-callback?...` | Invokes `googleCallback` | Effective middleware is `api`, `web`, `throttle:20,1` |
| 10 | State and provider response validation; `GoogleAuthController.php:67-100` | Session attempt, session `state`, query `state`, query `code/error` | Pulls the one-time attempt, compares state, then calls `Socialite::driver('google')->enablePKCE()->user()` | Missing/expired attempt, lost cookie, state mismatch, cancellation, absent code, OAuth token exchange failure, or user-info failure |
| 11 | Identity verification; `GoogleAccountService.php:25-50` | Socialite Google user | Normalized `{sub,email,name,avatar}` | Missing/oversized subject, Socialite ID mismatch, anything other than boolean `email_verified === true`, invalid email, or unsafe avatar yields `identity_invalid` |
| 12 | Stable provider lookup; `GoogleAccountService.php:73-82` | Verified `sub` | Active row with matching `google_id` | Inactive/deleted row yields `disabled`; no match proceeds to email resolution |
| 13 | Email candidate/conflict handling; `GoogleAccountService.php:84-105` | Lowercase verified email | No candidate means eligible new identity | Disabled row → `disabled`; different stored Google subject → `identity_conflict`; real password → `linking_required`; Facebook binding → `account_login_required`; otherwise → `recovery_required` |
| 14 | New-user eligibility at callback | No provider-ID or email collision | Callback continues without creating the user yet | Any conflict above prevents silent creation/linking |
| 15 | Legacy Google-only handling; `GoogleAuthController.php:115-130`, `GoogleAccountService.php:102-105` | Matching email, no provider ID, no usable password | Web session stores `pending_google_link` in `recovery_required` mode | Native gets `recovery_web_required`; web recovery later needs functioning mail/session |
| 16 | Account-link decision; `GoogleAuthController.php:118-124` | `linking_required` or `recovery_required` | Regenerates session, stores verified identity/mode with ten-minute expiry; callback redirects with stable error code | Other account errors redirect directly and do not create pending proof state |
| 17 | Delivery-code preparation; `GoogleAuthController.php:132-140` | Verified identity after direct/new resolution | Random 64-character code; cache entry expires in five minutes and contains identity plus SHA-256 session ID hash | Cache failure or unexpected exception becomes `callback_failed` |
| 18 | Backend-to-frontend redirect; `GoogleAuthController.php:490-499` | Language and `{code}` or `{error}` | `FRONTEND_URL/{lang}/auth/google-callback?code=...`; `Cache-Control: no-store`, `Referrer-Policy: no-referrer` | Unsafe frontend URL returns JSON 503 `unavailable` |
| 19 | Vue callback processing; `GoggleCallback.vue:145-170` | Query `code` or stable `error` | Immediately removes query parameters from browser history; chooses exchange, password link, recovery, or localized error | Invalid/missing 64-character code becomes `invalid_state`; unknown errors map to `callback_failed` |
| 20 | Exchange request; `AuthService.js:33-35`, route at `api.php:112` | CSRF cookie request, then POST `{code}` in same browser session | Invokes `exchangeWebCode`; effective middleware is `api`, `web`, `throttle:10,1` | CSRF/session loss, throttling, expired code, or reused code rejects |
| 21 | Account completion and auth state; `GoogleAuthController.php:324-360`, `GoogleAccountService.php:130-175`, `GoggleCallback.vue:58-74` | Code and session ID | Lock; session binding check; re-resolve/create user; consume cache entry; create Sanctum token; return `{token,role,is_profile_complete,user}`; Vue stores token/user state and dispatches `login` | Lock timeout → `busy`; missing entry → `expired_state`; binding mismatch → `invalid_state`; account conflict/constraint error returns its stable error |
| 22 | Final redirect; `GoggleCallback.vue:69-73`, `googleAuth.js:7-19` | Authenticated role and saved destination | Admin goes to `/admin`; user goes to an allowlisted same-origin `/{lang}/...` path, normally `/{lang}/home` | Unsafe, malformed, auth-page, cross-origin, or wrong-language destination falls back to home |

### Sequence diagram

```mermaid
sequenceDiagram
    actor U as User
    participant V as Vue auth page
    participant L as Laravel/Socialite
    participant G as Google
    participant C as Cache/Session
    participant D as Users DB

    U->>V: Continue with Google
    V->>L: GET /api/v1/users/google-login?lang=...
    L->>L: Validate config; regenerate session; store attempt; enable PKCE
    L-->>V: JSON {url}
    V->>G: Navigate to Google authorization URL
    G-->>L: GET /api/v1/users/google-callback?code&state
    L->>L: Pull attempt; verify state; exchange code; verify sub/email
    L->>D: Find google_id, then email candidate
    alt linked Google account or new eligible identity
        L->>C: Store identity behind session-bound 5-minute code
        L-->>V: 302 /{lang}/auth/google-callback?code=...
        V->>V: Remove callback query from history
        V->>L: GET /sanctum/csrf-cookie
        V->>L: POST /api/v1/users/google-exchange {code}
        L->>C: Lock, validate session binding, consume code
        L->>D: Re-resolve or create account; update last login
        L-->>V: JSON {Sanctum token, role, user, profile flag}
        V->>V: Store auth state and redirect
    else existing real-password account
        L-->>V: 302 callback?error=linking_required
        V->>L: POST google-link-confirm {password}
        L->>D: Verify real password; persist google_id
        L-->>V: Auth JSON
    else historical passwordless account
        L-->>V: 302 callback?error=recovery_required
        V->>L: POST google-recovery-start
        L-->>U: One-time code via registered mailbox
        U->>V: Enter one-time code
        V->>L: POST google-recovery-confirm {code}
        L->>D: Revalidate candidate; persist google_id
        L-->>V: Auth JSON
    else conflict/disabled/provider error
        L-->>V: Stable error code
        V-->>U: Localized error
    end
```

## 4. Frontend files, methods, and routes

### Login UI

`GoogleAuthButton.vue:1-20` is a presentation component. It emits `continue`; it does not call Google or Laravel itself. The auth view's `handleGoogleLogin()` at `register.vue:575-589` stores the language and sanitized post-auth redirect and calls `startGoogleAuth()`.

`startGoogleAuth()` at `googleAuth.js:30-55` has separate web/native behavior. Web calls the backend and verifies that the returned URL has exactly the `https://accounts.google.com` origin and no embedded username/password. Native creates a verifier/challenge and navigates to the backend start URL.

The error allowlist at `googleAuth.js:21-28` maps recognized stable backend codes to i18n keys. Unknown callback codes become `callback_failed`. The initial login handler is broader: `register.vue:586-588` treats any rejection lacking `response.data.error_code` as `unavailable`. Therefore a network error, reverse-proxy HTML error, CORS error, malformed response, or rejected Google URL origin can display the same configuration-oriented message. This frontend fallback is confirmed code behavior; which condition occurred on the live site is unverified.

### Callback UI

The route is `/:lang/auth/google-callback` (`router/index.js:342-345`). `GoggleCallback.vue`:

- strips callback query data from history before making the exchange (`145-151`);
- shows the real-password link form only for `linking_required` (`152-157`);
- starts one-time legacy recovery for `recovery_required` (`158-162`);
- exchanges a valid code (`76-96`, `163-169`);
- stores the returned bearer token and user state, emits the `login` event, and performs a safe local redirect (`58-74`);
- shows the backend password error verbatim before using a localized fallback (`98-110`).

On web, `setAuthToken()` stores the Sanctum token under the legacy `auth_token` localStorage key (`resources/js/services/authTokenStorage.js:44-59`). `ApiClient.js:165-187,230-245` uses same-origin `/api/v1` and attaches that value as a Bearer token. Native uses secure storage instead.

## 5. Backend controllers, services, and routes

All relevant routes are under `/api/v1/users`:

| Method and path | Handler | Effective route middleware |
|---|---|---|
| `GET /api/v1/users/google-login` | `googleLogin` | `api`, `web`, `guest`, `throttle:10,1` |
| `GET /api/v1/users/google-callback` | `googleCallback` | `api`, `web`, `throttle:20,1` |
| `POST /api/v1/users/google-exchange` | `exchangeWebCode` | `api`, `web`, `throttle:10,1` |
| `POST /api/v1/users/google-mobile-exchange` | `exchangeMobileCode` | `api`, `throttle:10,1` |
| `POST /api/v1/users/google-link-confirm` | `confirmLinkWithPassword` | `api`, `web`, `throttle:10,1` |
| `POST /api/v1/users/google-recovery-start` | `startLegacyRecovery` | `api`, `web`, `throttle:3,1` |
| `POST /api/v1/users/google-recovery-confirm` | `confirmLegacyRecovery` | `api`, `web`, `throttle:6,1` |
| `GET /api/v1/users/google-link-start` | `linkStart` | `api`, `auth:sanctum`, `web`, `throttle:10,1` |
| `POST /api/v1/users/google-unlink` | `unlink` | `api`, `auth:sanctum`, `throttle:10,1` |

These route definitions are at `routes/api.php:110-120`; a read-only `php artisan route:list --path=api/v1/users/google --json` inspection confirmed the registered methods, paths, handlers, and middleware.

`GoogleAuthController` owns protocol/session behavior. `GoogleAccountService` owns provider identity validation, password classification, conflict resolution, linking, and account creation. `GoogleLegacyRecoveryService` owns the narrow historical recovery proof.

## 6. Account matching and Google ID logic

Scemory recognizes a Google user primarily by Google's verified stable subject stored in `users.google_id` (`GoogleAccountService.php:73-82`). The schema makes that column nullable and unique (`2026_10_22_000001_add_google_identity_to_users.php:11-14`). The model hides `google_id`, `facebook_id`, and password from serialization and uses Laravel's hashed password cast (`User.php:30-49`).

The current resolver is ordered deliberately:

| Account state | Result |
|---|---|
| `google_id === verified Google sub`, active | Direct sign-in path |
| Matching `google_id`, inactive/deleted | `disabled` |
| No subject match, email row inactive/deleted | `disabled` |
| Email row already has another `google_id` | `identity_conflict` |
| Email row has a usable real password | `linking_required`; require that password once |
| Email row has `facebook_id` and no usable password | `account_login_required`; authenticate through that provider and link from profile |
| Email row has no provider ID and no usable password | `recovery_required`; one-time mailbox proof |
| No subject or email row | Eligible for automatic basic account creation |

New users are created only during the delivery exchange, inside subject and email cache locks plus a database transaction (`GoogleAccountService.php:130-163`). The new record receives `google_id`, safe provider fields, a null password, user role, active status, and verified email timestamp (`145-155`). A uniqueness race is caught and re-resolved rather than blindly overwriting another account (`157-160`).

## 7. Legacy account behavior

The historical controller at commit `132bb49` called `firstOrCreateByEmail()` and created Google users with `Hash::make('password')` (`GoogleAuthController.php` in that revision, lines 71-90). It did not persist Google's subject. Consequently:

- historical Google-only rows are indistinguishable from other email-matched rows by provider ID alone;
- the later migration had no authoritative subject to backfill;
- a historical token name, old Sanctum token, placeholder hash, or absence of a password is not proof that the current Google subject owns the row;
- requiring the placeholder as a password is both unusable and unsafe.

`GoogleAccountService::hasUsablePasswordCredential()` now rejects null/empty credentials and hashes matching either known placeholder (`GoogleAccountService.php:20-65`). A real password is accepted as ownership proof only after that classification (`67-71`).

The one-time recovery service re-fetches the row by the verified email and confirms the same user ID, active state, absence of both provider IDs, and absence of a usable password (`GoogleLegacyRecoveryService.php:65-80`). It sends a random six-digit code, stores only its hash, expires it after ten minutes, limits confirmation to five attempts, and rate-limits sending for 60 seconds (`16-54`; controller `229-283`). It refuses any mail transport that can write the code to logs (`89-100`). Successful confirmation revalidates the candidate and stores the verified `google_id` on the original row (`57-63`). Subsequent sign-ins then use direct subject matching.

This mechanism can work for historical Google-only rows if the deployed backend is current, the browser session survives the callback and recovery requests, cache locking works, and a non-logging mail transport successfully delivers to the registered mailbox. In the inspected local environment, `MAIL_MAILER=log`, so recovery intentionally returns `recovery_unavailable`; that is a confirmed local limitation, not proof about development/production.

## 8. Account linking and password verification

The password screen exists only when the callback redirects with `error=linking_required`. Current HEAD produces that code for a verified Google email matching an active account with a usable real Scemory password and no subject match (`GoogleAccountService.php:84-95`). The callback stores the verified identity and link mode in the session for ten minutes (`GoogleAuthController.php:115-124`).

`POST /google-link-confirm` validates a non-empty password, requires unexpired session state in `linking_required` mode, re-fetches the account by normalized email, rechecks account status, and calls `passwordProvesOwnership()` (`GoogleAuthController.php:164-196`). Success persists `google_id`, updates `last_login_at`, issues a token, and removes the pending session state (`196-202`).

Authenticated users can instead initiate linking from their profile through `GET /google-link-start` (`286-304`). The callback takes the authenticated user ID from the session, checks status, checks subject conflicts, and links that row (`103-113`). Unlinking is refused if it would leave the user without Facebook or a usable password (`306-321`).

## 9. Sanctum token and exchange-code handling

The current web callback never places a Sanctum bearer token in the redirect URL. It creates a random 64-character delivery code and stores only a hash-derived cache key containing the verified identity and SHA-256 hash of the current session ID for five minutes (`GoogleAuthController.php:132-140,368-370`). The callback response adds `no-store` and `no-referrer` headers (`490-499`).

The exchange validates code shape, serializes completion with a cache lock, checks the session binding with `hash_equals`, resolves or creates the user again, removes the cache item, and returns the Sanctum token in a no-store JSON body (`324-365`). The cache item is effectively one-use after successful completion. A failed account resolution leaves it until expiry, allowing a legitimate retry while the same account condition persists.

The frontend first obtains `/sanctum/csrf-cookie` and then POSTs the code (`AuthService.js:33-35`). The web route has the `web` middleware, so session and CSRF protections apply. Sanctum uses the `web` guard and falls back to bearer tokens (`config/sanctum.php:18-37`). Global Sanctum expiration is `null` (`config/sanctum.php:50`), so token lifetime depends on explicit revocation/record expiry behavior elsewhere.

## 10. Exact origins of the reported messages

### “Google authentication is currently unavailable. Please use email and password or try later.”

- **Literal text:** `resources/js/i18n/en.json:1367`, key `googleAuth.unavailable`.
- **Backend start causes:** `googleLogin()` returns HTTP 503 `{"status":"error","error_code":"unavailable","message_key":"googleAuth.unavailable"}` when `configured()` is false (`GoogleAuthController.php:35-37`) or when Socialite/start logic throws (`60-63`).
- **Backend callback cause:** callback configuration failure throws `unavailable` (`73-75`) and redirects to the frontend with that stable error. An unsafe callback destination can also return JSON 503 (`493-495`).
- **Frontend masking:** `register.vue:586-588` substitutes `unavailable` for every initial request exception without `response.data.error_code`. Thus this message can mask networking, CORS, reverse-proxy, non-JSON 5xx, malformed URL, or origin-check failures.
- **Confirmed here:** missing local client ID and secret make `configured()` false. The live origin remains unverified.

`configured()` additionally requires an exact backend path `/api/v1/users/google-callback`, a safe frontend URL, and a safe mobile app link (`GoogleAuthController.php:448-460`). Therefore a missing or malformed mobile app link can disable web Google login too. HTTPS is required in production; local HTTP is accepted only for loopback hosts (`463-475`).

### “Link your Google account.”

- **Literal text:** `resources/js/i18n/en.json:1378`, key `googleAuth.linkAccountTitle`.
- **Trigger:** callback query `error=linking_required`; `GoggleCallback.vue:152-157` shows the form.
- **Current backend origin:** matching verified email, no matching subject, and a usable real password (`GoogleAccountService.php:84-95`), returned as conflict status 409 before browser redirection.
- **Historical regression:** commits before `31ee81e` classified every matching email without `google_id` as `linking_required`, including placeholder-password Google users. In `b662aa0`, this unconditional branch is `GoogleAccountService.php:84-88` in that revision.

### “The provided password does not match the existing account.”

- **Literal backend text:** `GoogleAuthController.php:189-193`.
- **HTTP response:** 422 JSON with `errors.password[0]`.
- **Trigger:** the account is missing/ineligible for a usable real password, or `password_verify()` fails (`GoogleAccountService.php:52-71`).
- **Frontend display:** `GoggleCallback.vue:98-107` shows `errors.password[0]` first, then backend `message`, then localized `googleAuth.invalidCredentials`.
- **Legacy consequence:** a known placeholder is explicitly not ownership proof, so historical Google-only users cannot pass this form. Current HEAD sends those eligible rows to recovery instead of the password form.

## 11. Exception handling and log evidence

The current start and callback handlers log only a fixed event description and exception class (`GoogleAuthController.php:60-63,147-150`). Stable public error codes are returned through `jsonError()` (`483-487`). Provider tokens, authorization codes, state values, credentials, and provider response bodies are not included by these log calls.

Read-only inspection of `storage/logs/laravel.log` found 154 occurrences of `Google OAuth callback failed.`, all recording `GuzzleHttp\\Exception\\ClientException`; the latest timestamp was 2026-10-24 07:16:12. The same log contains test execution evidence and mocked failure paths, so those records cannot be attributed to live users. If one came from a real request, it would locate the failure at Socialite's provider token/user-info HTTP call, commonly due to an invalid/reused code, client mismatch, secret mismatch, redirect mismatch, or provider rejection. The sanitized log deliberately omits the response detail needed to distinguish those cases. This is a hypothesis, not a confirmed live root cause.

No raw secret, token, OAuth code, recovery code, or personal email was copied into this report.

## 12. Git history comparison

Repository history is available, but no deployment record or test artifact proves that a particular historical commit was the last operational production version. `132bb49` is the last inspected pre-refactor implementation and matches the reported seamless behavior; it is therefore a reference implementation, not a verified “last known good” deployment.

| Commit | Author date | Relevant change |
|---|---|---|
| `132bb49` | 2026-10-04 12:17:21 +03:00 | Pre-refactor controller: stateless Socialite, email `firstOrCreate`, shared placeholder password, direct Sanctum token in frontend query string, no stored provider subject |
| `4fc90c3` | 2026-10-22 12:47:59 +03:00 | Added `google_id` migration, `GoogleAccountService`, verified subject/email checks, state/PKCE/session-bound delivery exchange, stable errors, and refusal to auto-link email collisions; also initially required profile data for new users |
| `e071d00` | 2026-10-22 16:10:29 +03:00 | Added `google-link-confirm`, password proof controller/UI, and tests; this is the account-linking screen implementation |
| `b662aa0` | 2026-10-22 16:49:00 +03:00 | Removed normal Google profile-completion requirements and recognized legacy placeholder hashes as unusable, while still classifying every email match as `linking_required`; historical users remained stuck |
| `098146a` | 2026-10-24 09:53:02 +03:00 | Package-lock-only change; no Google runtime logic changed |
| `31ee81e` | 2026-10-24 10:17:12 +03:00 | Added explicit account classifications and the one-time legacy recovery route/UI; current audited HEAD |

### Original versus current behavior

The pre-refactor web callback (`132bb49:app/Http/Controllers/api/auth/GoogleAuthController.php:71-163` in that revision) was:

`Google callback → stateless user retrieval → firstOrCreateByEmail → force account active/verified → create Sanctum token → redirect with bearer token in URL`.

It was seamless because possession of a Google account reporting the same email was treated as sufficient to own any matching Scemory account. It also had material weaknesses: no web state correlation, no stable provider binding, email-only account takeover risk, reactivation of existing accounts, bearer token exposure in URL/history/referrers/logs, and raw exception text in the frontend error query.

The current flow is:

`Google callback → state/PKCE validation → verified subject/email → google_id-first resolution → explicit proof for ambiguous email collision → session-bound one-time exchange code → locked re-resolution/create → Sanctum token in JSON body`.

The breaking user-experience change was the refusal to trust email alone without a migration/backfill path for historical Google-only users. The first repair offered only a password form, even though those rows held a shared placeholder. Current HEAD adds a narrowly scoped recovery path and preserves direct login for rows already carrying the verified subject.

## 13. Comparison with AI Pro's reported flow

Both products start with a Vue request, use Laravel Socialite, create or resolve a user, issue a Sanctum token, and automatically redirect on an unambiguous success.

AI Pro's reported direct experience comes from email `firstOrCreate`. Scemory adds screens only when a verified Google identity does not match a stored provider subject but its email collides with an existing account. This is the point where silent email linking would create account-takeover risk.

Scemory's current implementation improves on the reported AI Pro architecture by maintaining server-correlated web state, using PKCE, binding accounts to Google's stable subject, enforcing status restrictions, moving bearer tokens out of redirect URLs, using short-lived session-bound exchange codes, and handling duplicate races. The seamless target remains possible for already linked users and new non-conflicting users. Historical ambiguous rows require a one-time proof because the original implementation discarded the provider identifier needed for automatic safe recognition.

## 14. Security analysis

| Area | Classification | Evidence and conclusion |
|---|---|---|
| Current OAuth state validation | **No vulnerability found in inspected source** | Web stores/pulls a ten-minute attempt and compares session state (`GoogleAuthController.php:48-52,81-89`); native state is encrypted, cached, expiring, and single-use (`399-445`) |
| Provider identity verification | **No vulnerability found in inspected source** | Requires matching Socialite ID and Google `sub`, strict boolean verified email, valid email, and safe avatar (`GoogleAccountService.php:25-50`) |
| Stable provider binding | **No vulnerability found in current source** | `google_id` is unique and primary lookup key (`migration:12`; service `73-82`) |
| Historical email-only linking | **Confirmed historical vulnerability/design flaw; removed from current source** | Old flow claimed rows solely by email and could reactivate them (`132bb49` controller lines 71-90) |
| Current account linking | **No vulnerability found in inspected source** | Real password, authenticated profile, or narrow legacy recovery proof is required; email alone does not link |
| Legacy recovery assurance | **Potential risk** | Mailbox possession plus a fresh verified Google callback is password-reset-equivalent proof, not historical cryptographic provider binding. Reassigned/recycled mailbox scenarios remain possible. A stronger support review may be needed for high-risk cases |
| Placeholder/historical token proof | **No vulnerability found in current source** | Known placeholders are explicitly rejected; old token names are not consulted as ownership evidence |
| Exchange-code expiry/one-time use | **No vulnerability found in inspected source** | Five-minute cache TTL, random 64-character code, session binding, lock, and deletion after success (`controller:132-140,324-370`) |
| CSRF | **No vulnerability found in inspected web path** | Same-origin CSRF-cookie request and `web` middleware on exchange/link/recovery POST routes |
| Token in callback URL | **Confirmed historical vulnerability/design flaw; removed from current source** | Old controller put bearer token in query; current callback URL contains only an exchange code/error |
| Browser token storage | **Potential risk** | Web stores bearer token in localStorage (`authTokenStorage.js:44-59`), exposing it if an XSS exists; no exploitable XSS was established in this audit |
| Sanctum token lifetime | **Potential risk / configuration-dependent** | Global expiration is null (`config/sanctum.php:50`); effective revocation practices were outside this flow audit |
| Duplicate-account prevention | **No vulnerability found in inspected source** | Unique `google_id`, subject/email locks, transaction, and unique-violation re-resolution (`GoogleAccountService.php:117-163`) |
| Suspended/deleted accounts | **No vulnerability found in current resolver** | Checked on subject match, email candidate, linking, and recovery. Historical code reactivated rows, but current source does not |
| Error/log data exposure | **No vulnerability found in current Google handlers** | Stable codes and exception-class-only logging; historical raw exception redirect was removed |
| Session continuity behind proxy/load balancer | **Configuration-dependent** | Database session configured locally; live cookie domain/secure flag, shared DB, proxy headers, and stickiness were not observable |
| Cache continuity and locks | **Configuration-dependent** | Local `CACHE_STORE=database` and cache-table migration exist; live shared cache and lock behavior were not observable |
| Recovery mail confidentiality | **Configuration-dependent** | Service refuses logging transports, but live mailer/delivery status was not observable |

No confirmed current account-takeover path was found in the inspected Google source. This statement does not substitute for dynamic penetration testing or review of the entire application for XSS, session fixation, mail compromise, and infrastructure errors.

## 15. Deployment and configuration findings

### Required variable names

`config/services.php:23-28` consumes:

- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI`
- `GOOGLE_MOBILE_APP_LINK`

The surrounding flow also depends on `FRONTEND_URL`, `APP_ENV`, `APP_URL`, `APP_KEY`, session settings, cache settings, mail settings for legacy recovery, and optional Sanctum stateful-domain settings. No secret values were read into or reproduced in this report.

### Callback URLs

- Backend callback path enforced by source: `/api/v1/users/google-callback` (`GoogleAuthController.php:455-460`).
- Expected development callback if that environment is configured for the supplied host: `https://dev.scemory.com/api/v1/users/google-callback`.
- Frontend callback route: `/{lang}/auth/google-callback` (`router/index.js:342-345`), for example `https://dev.scemory.com/en/auth/google-callback`.

The configured Google console redirect URI must exactly match the backend callback, including scheme, host, path, and any port. The frontend route is not the provider redirect URI.

### Local configuration evidence

The inspected `.env` is a local Docker configuration:

- `APP_ENV=local`
- `APP_URL=http://localhost:8000`
- `FRONTEND_URL=http://localhost:8000`
- `GOOGLE_REDIRECT_URI=http://localhost:8000/api/v1/users/google-callback`
- `GOOGLE_MOBILE_APP_LINK` is present and uses HTTPS
- `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` are absent
- `APP_KEY` is set (value not inspected/reported)
- `SESSION_DRIVER=database`, `SESSION_DOMAIN=null`, `SESSION_SECURE_COOKIE=false`, `SESSION_SAME_SITE=lax`
- `CACHE_STORE=database`
- `MAIL_MAILER=log`
- `SANCTUM_STATEFUL_DOMAINS` is absent, so framework defaults apply

`.env.example` also lacks client ID/secret placeholders. It documents local callback/frontend/mobile URLs but cannot by itself produce a configured Google provider. No `bootstrap/cache/config.php` or route cache artifact was present. Production may intentionally inject variables outside the repository; this cannot be inferred from local files.

### Sessions, cache, proxies, HTTPS, and middleware

- Session configuration is database-backed locally with 120-minute lifetime, HTTP-only cookie, and lax SameSite (`config/session.php:21-37,159-202`). A Google top-level GET callback is compatible with SameSite=Lax. The repository creates the `sessions` table in `database/migrations/0001_01_01_000000_create_users_table.php:40-48`; whether that migration exists in the deployed database is unverified.
- Cache defaults to `CACHE_STORE` and supports database locking (`config/cache.php:12,26-37`). The repository includes a cache-table migration. Multi-instance deployment requires every instance to share the same session and cache backends.
- `bootstrap/app.php:11-23` registers API routes and global API middleware. It contains no explicit `trustProxies()` configuration.
- The repository Nginx file listens on HTTP, uses `server_name localhost`, and does not add forwarded scheme/host headers (`docker/nginx/default.conf:1-18`). An external TLS proxy may supply those in deployment, but that infrastructure is unavailable here. Incorrect trusted-proxy/HTTPS handling can affect secure cookies and generated URLs; Socialite's explicit redirect setting reduces but does not eliminate session-cookie dependence.
- `docker-compose.yml:1-94` is a local HTTP development topology. It is not evidence of the development server's production-style TLS termination.
- Vite compiles `resources/js/app.js` plus CSS/Sass entries and uses port 5174 in dev (`vite.config.js:5-36`).
- Locked relevant packages are Laravel Framework `v12.70.0`, Sanctum `v4.3.1`, and Socialite `v5.26.0`.

### Development deployment evidence

Read-only static inspection of `https://dev.scemory.com` found the deployed Vite entry and these relevant chunks at audit time:

- `GoggleCallback-CduEe5Ne.js`
- `AuthService-Bjv_O-aq.js`
- `googleAuth-Dl0Bzknw.js`
- `register-Bc1AQT9Y.js`

Those assets contained `google-recovery-start`, `google-recovery-confirm`, `google-link-confirm`, `recovery_required`, and `linking_required`; they did not contain `profile_required`. This supports that the frontend bundle includes the current legacy-recovery experience. Asset hashes do not prove the Laravel backend revision, loaded `.env`, cached configuration, route availability, database schema/data, session/cache health, or Google console settings.

## 16. Confirmed root causes and unverified hypotheses

### Confirmed

1. **Local `unavailable`:** required Google client configuration is absent, so `configured()` fails before Socialite redirects.
2. **Historical password prompt regression:** secure subject-first resolution encountered old rows with no `google_id`; the initial resolver mapped all email collisions to `linking_required`.
3. **Historical password rejection:** old Google users received a shared placeholder hash, and current password proof deliberately rejects placeholder hashes. Asking them for a Scemory password could never succeed.
4. **Generic frontend masking:** initial login failures without a structured backend error are displayed as `unavailable`, so the visible text alone cannot identify the live layer that failed.
5. **Current source repair state:** HEAD differentiates real-password, Facebook-bound, linked Google, new, disabled/conflicting, and historical passwordless accounts and provides recovery for the last category.

### Unverified for `dev.scemory.com`

- Whether the backend runs commit `31ee81e` and has both recovery routes.
- Whether Google client ID/secret are present in the effective cached runtime configuration.
- Whether the registered Google redirect exactly equals `https://dev.scemory.com/api/v1/users/google-callback`.
- Whether `FRONTEND_URL`, mobile app link, session cookie settings, and proxy/TLS handling satisfy `configured()` and preserve the session.
- Whether the database contains the migrations/tables and historical rows expected by this code.
- Whether session and cache stores are shared and writable across instances.
- Whether the non-logging mailer can deliver legacy recovery messages.
- Whether any observed provider `ClientException` came from a real request rather than a test, and if real, the provider's sanitized error category.

## 17. Recommended minimal repair strategy (documentation only)

The current source already contains the appropriate minimal architecture. Do not restore email-only `firstOrCreate`, stateless web OAuth, URL bearer tokens, placeholder-password proof, or automatic account reactivation.

The smallest safe operational repair is:

1. Establish the exact deployed backend revision and ensure it includes `31ee81e`'s account classifications and recovery routes.
2. Validate, without printing values, that the effective Laravel configuration has non-empty Google client ID/secret, the exact HTTPS backend callback, the correct HTTPS frontend URL, and a valid HTTPS mobile app link. Rebuild config/route caches through the normal deployment procedure only when deployment is authorized.
3. Confirm the same browser session survives start → Google → callback → frontend exchange. Verify secure cookie/domain/SameSite/proxy settings and shared database session storage.
4. Confirm the database cache and locks are shared, writable, and persistent for at least the ten-minute attempt/recovery windows.
5. Configure a real non-logging mail transport for the one-time historical recovery path.
6. Exercise controlled non-production fixtures for: already linked subject, new user, real-password email collision, historical placeholder/null-password row, Facebook-bound collision, disabled row, and conflicting subject. Verify the historical row retains its original user ID and becomes direct-login after one recovery.

This retains the desired direct experience for linked and new users while requiring one narrowly scoped proof only where the old implementation discarded the provider identifier.

## 18. Specific changes required for seamless Google login safely

No source change is required for the normal direct path if the deployed backend is current and configured correctly. If deployment inspection shows older code, the required code-level delta is the current HEAD behavior:

- classify known placeholder/null passwords as unusable rather than displaying a password form;
- distinguish real-password, other-provider, conflicting-subject, disabled, and historical passwordless rows;
- recover only the historical passwordless/no-provider category with a short-lived, rate-limited, hashed one-time code sent through a non-logging mailer;
- persist the verified Google subject on the original row after proof;
- preserve state, PKCE, session-bound exchange codes, account locks, status checks, and token-in-body behavior;
- keep direct account creation for a verified, non-conflicting Google identity without demanding profile completion.

Any future attempt to make ambiguous historical rows completely one-click would require an authoritative mapping from each old row to Google's stable subject. The repository does not contain such a mapping. A historical token name or placeholder hash cannot supply it safely.

The one optional source hardening identified by this audit is to stop mapping every unstructured initial frontend failure to `unavailable`; a distinct connectivity/server-response message and internal correlation ID would make diagnosis clearer. That is an observability improvement, not the underlying account-resolution repair, and no change was made here.

## 19. Evidence needed to confirm live behavior

The following sanitized evidence would close the remaining gaps without exposing secrets:

1. Deployed Git SHA or immutable release identifier for both PHP and Vite artifacts.
2. A server-side boolean/config diagnostic showing presence (not values) of the four Google variables, plus the exact non-secret redirect/frontend/mobile URLs from `config(...)` after cache loading.
3. `route:list` output for the Google routes on the deployed release.
4. Sanitized session cookie attributes and confirmation that the same session ID hash is observed at start, callback, and exchange; do not record the raw session ID.
5. Cache/session driver names, shared-backend health, and table/lock availability.
6. Mailer transport type and delivery result for a controlled test mailbox; never use or expose a real user's recovery code.
7. A correlation ID joining one controlled failed browser request to the sanitized Laravel log event and Google provider error category, excluding authorization code, access token, state, email, and secret.
8. Database aggregate counts only: active rows with `google_id`, email-only rows with null/placeholder/real password, and provider conflicts. No emails, subjects, hashes, tokens, or user-level data are needed.

No interactive OAuth flow, database read/write, migration, cache mutation, deployment, test-user creation, or credential change was performed for this audit.

