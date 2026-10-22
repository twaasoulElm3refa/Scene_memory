# Google authentication implementation

Implemented and verified locally on 22 October 2026. No deployment or persistent database migration was performed.

## Existing integration findings

Scemory already used Laravel 12, Sanctum 4, Socialite 5, and a combined Vue login/registration page. Both tabs already had Google buttons. Authentication normally stores Sanctum bearer tokens through `authTokenStorage`; the native app uses secure storage. Roles and protected APIs already use the existing User model and Sanctum middleware.

The prior Google controller was partially integrated but unsafe: web authentication used `stateless()` without validating OAuth state, matched/created users by email, invented a random password, marked email verified without checking Google's verification evidence, and placed a Sanctum token in the web redirect query. It did not persist Google's stable provider identifier. The native flow already had encrypted, expiring, single-use state and a verifier-bound delivery code. These native protections were retained.

The existing integration and dependencies were improved. No second provider, Google login route, authentication guard, or translation system was introduced.

## Implemented flow

1. The shared Google button starts the existing `GET /api/v1/users/google-login` endpoint. Web requests receive the Google authorization URL as JSON and navigate to it. The client accepts only the Google Accounts HTTPS origin.
2. Web authorization uses Laravel's web session middleware, Socialite's state validation, S256 PKCE, a ten-minute attempt deadline, and session regeneration. The existing API routes previously lacked that session middleware.
3. Google returns to the existing `/api/v1/users/google-callback`. Socialite exchanges the authorization code server-side using the configured client ID, secret, exact redirect URI, and web PKCE verifier. It retrieves identity from Google's authenticated HTTPS userinfo endpoint. Only a nonempty `sub`, valid email, and boolean `email_verified: true` are accepted. The mapped provider ID must equal `sub`.
4. Account lookup uses the unique `users.google_id`. Existing linked users retain their email, display name, password, role, profile, and account relationships. Suspended and soft-deleted accounts are rejected. No existing account is reactivated.
5. Matching email addresses without the matching provider identity return `linking_required`. Neither merging nor automatic linking occurs, including for legacy Google users whose provider ID was never saved.
6. A successful callback creates a five-minute, opaque, single-use delivery code in the existing cache. Web codes are bound to the regenerated browser session; native codes are bound to the app's existing S256 verifier. The redirect contains only this code, or a stable error code. Native app links also contain the selected language. Identity and authentication tokens are absent from redirects.
7. Vue removes callback parameters from history before exchanging the delivery code. Web exchange uses the new `POST /api/v1/users/google-exchange`, first loading `/sanctum/csrf-cookie`; Laravel validates CSRF and the browser session binding. Native exchange reuses `POST /api/v1/users/google-mobile-exchange` and its verifier.
8. New users first receive `profile_required`. The callback screen collects country and birth date, retaining the registration form's required profile fields. No account/token is created until those fields pass server validation. The name and email come from verified Google identity. New accounts receive only role `user`, the existing default licence when present, a nullable password, and verified-email evidence. The available HTTPS avatar is stored separately from locally uploaded profile images. No Google access/refresh token is persisted.
9. Cache locks, database transactions, and unique constraints protect account creation and single-use delivery. A simultaneous email/password registration cannot be claimed by Google. Mail queuing failures do not invalidate successful account creation.
10. The exchange returns the ordinary Sanctum token and actual user/role in a JSON body. Vue uses existing token storage, updates profile/language state, emits the existing `login` event consumed by Navbar, and follows a validated local return path or the existing home/admin destination. Existing Sanctum logout revokes the token.

This is authorization-code OAuth, not a frontend ID-token endpoint. Client-supplied JWTs, emails, subjects, and role/query parameters cannot establish identity. Signature/audience/issuer/expiry tests for a submitted Google ID token are therefore inapplicable; tests cover Google rejecting invalid/expired authorization credentials and unauthorized userinfo responses. If an ID-token API is added later, implement all checks in [Google's server verification guidance](https://developers.google.com/identity/gsi/web/guides/verify-google-id-token).

Native `stateless()` remains deliberate: the native app cannot share its bearer session with the system browser. Its encrypted state, cache nonce consumption, and verifier-bound token delivery provide the existing binding. Web authorization never uses `stateless()`.

## UI and languages

The original branding, form fields, recovery links, tabs, and navigation remain. One shared `GoogleAuthButton` is displayed below the active login or registration form, with a localized OR divider, multicolor Google icon, white button, full available width, keyboard focus styling, loading announcement, disabled behavior, and reduced-motion support. Being outside the password forms lets Google registration proceed without submitting or validating a password.

All 23 new strings use the existing Vue i18n dictionaries in all 13 languages: **ar, en, fr, de, ru, es, it, hi, ja, fa, zh, ur, tr**. These cover continue/sign-up labels, divider, loading, cancellation, failures, expired/invalid requests, disabled/unavailable accounts, linking, profile completion/validation, and retry/navigation. Arabic, Persian, and Urdu use RTL layouts. Callback errors use allowlisted message keys, never raw provider messages.

## Database changes

`database/migrations/2026_10_22_000001_add_google_identity_to_users.php`:

- Adds nullable, unique `google_id` for Google's `sub`.
- Adds nullable `google_avatar_url`.
- Allows `password` to be null for new passwordless users. Existing password hashes and all existing rows are preserved.

No historical identity backfill is possible from email alone. Migration tests verify existing account/hash/role/status preservation. The rollback intentionally leaves password nullable; do not remove identity columns after accounts depend on them without an account-recovery plan.

## Required configuration and Google Cloud settings

No real OAuth credentials or Cloud Console access were supplied. This checkout has no application `.env`. Automated tests use synthetic configuration and a test-only Laravel key in `phpunit.xml`. Supply a normal application key and actual OAuth credentials through the deployment secret environment.

The existing variables remain:

```dotenv
# Local Laravel-served SPA (use the same hostname throughout)
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:8000
GOOGLE_CLIENT_ID=<web application OAuth client ID>
GOOGLE_CLIENT_SECRET=<server-side client secret>
GOOGLE_REDIRECT_URI=http://localhost:8000/api/v1/users/google-callback
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

For production, use the actual canonical HTTPS origin, for example:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://scemory.com
FRONTEND_URL=https://scemory.com
GOOGLE_REDIRECT_URI=https://scemory.com/api/v1/users/google-callback
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Keep `GOOGLE_CLIENT_SECRET` server-side. Neither it nor the client ID needs a `VITE_` variable for this flow. `GOOGLE_MOBILE_APP_LINK` and `VITE_GOOGLE_AUTH_ORIGIN` keep their existing native-app meanings; the latter is an origin, never a secret.

Google Cloud configuration:

1. Select the intended project in Google Cloud/Google Auth Platform. Configure branding, audience, support/developer contact details, authorized domains, and the published Scemory homepage/privacy/terms URLs. Select an audience consistent with platform eligibility.
2. Create or reuse an OAuth client of type **Web application**. Put its ID and secret in the server variables above.
3. Register the exact local redirect URI and the exact production redirect URI shown above, adjusting the origin to the real deployment. Hostname, port, scheme, and path must match. Do not register the Vue callback path as Google's redirect URI.
4. Authorized JavaScript origins are not required by this server redirect integration: it does not load Google Identity Services or issue browser-side OAuth requests. Do not add a frontend secret. If another existing client independently needs JavaScript origins, configure those origins for that client.
5. Request only the existing `openid profile email` scopes. While the consent app is in testing, add intended test accounts. Complete Google's applicable branding/domain verification and publish the consent app for public access when ready.

These settings follow [Google's web-server OAuth documentation](https://developers.google.com/identity/protocols/oauth2/web-server). The integration follows [Laravel 12 Socialite](https://laravel.com/docs/12.x/socialite), and the shared button uses the multicolor icon and continue wording described in [Google's branding guidance](https://developers.google.com/identity/branding-guidelines).

Web hosting requirements:

- Serve the Vue application and `/api` and `/sanctum` endpoints through the same public origin. `FRONTEND_URL` is that public application origin, not a standalone Vite development server. In development, visit the Laravel origin; Vite can still supply HMR assets through Laravel's existing integration. A separate frontend host needs an explicit same-origin reverse proxy for these paths.
- Use durable sessions and a shared cache with atomic locks, such as the existing database or Redis configuration. All instances need the same `APP_KEY`. Test-only array stores must not be used in production.
- Keep the session cookie available on `/`, HttpOnly, SameSite=Lax, and Secure in production. Configure the reverse proxy/Laravel HTTPS handling correctly. Cookies and state must survive the outbound Google navigation.
- Configured OAuth/callback destinations must be valid HTTPS URLs in production without credentials, query, or fragment components. HTTP is accepted only for loopback hosts outside production. Request parameters cannot override a destination.

## Local validation results

Tests ran in WSL with PHP 8.3.6 and Linux Node 24.13.0. The installed dependencies were reused; the Windows Node executable could not use Linux-native Rollup dependencies, so an isolated Linux Node binary was used from `/tmp`. No application dependency or lockfile was changed by this task.

Commands (`phpunit.xml` supplies a synthetic `APP_KEY` only to the test process):

```sh
php vendor/bin/phpunit --no-progress --do-not-cache-result --display-skipped
npm test
npm run build
php vendor/bin/pint --test <the seven new/updated Google PHP files>
```

`npm test` and `npm run build` were executed through their equivalent installed Vitest and Vite CLI entrypoints with Linux Node.

| Check | Result |
| --- | --- |
| Complete backend suite | 320 total; 311 passed, 9 skipped; 2,243 assertions; no failures |
| Google-specific backend coverage | 31 tests covering web/native security, linking, onboarding, provider failures, replay, suspension, migration preservation, real Sanctum token logout/revocation |
| Complete frontend suite | 147 tests passed in 18 files |
| New Google frontend coverage | 30 tests: actual Vue rendering/interactions, all locales/RTL, loading/errors, safe navigation, native verifier retention, profile completion, existing password forms, CSRF/POST transport |
| Production frontend build | Passed; existing Sass/Bootstrap deprecation warnings and bundles over 500 kB remain |
| PHP formatting and Git whitespace check | Passed for task changes |

The nine existing skips are seven `VirtualGiftConcurrencyTest` MySQL cases (opt in through `tests/run-gifts-mysql-concurrency.sh`) and two `WeeklyConcurrencyTest` MySQL cases (see `docs/weekly-ranking.md`). They are not hidden or counted as passes. No unrelated test failures remain in the executed suites.

UI tests mount the actual Vue components in jsdom. They verify flexible width/focus/logical spacing and RTL behavior; they are not real-device screenshots or a browser layout audit. Live Google consent, browser cookie behavior across the real deployed origin, and Android app-link behavior still need a credentialed smoke test.

## Files changed for this task

- Backend: `app/Http/Controllers/api/auth/GoogleAuthController.php`, `app/Services/GoogleAccountService.php`, `app/Exceptions/GoogleAuthException.php`, `routes/api.php`, `app/Models/User.php`.
- Compatibility: `app/Http/Controllers/api/auth/AuthController.php` guards password login for null-password accounts; existing password hashes are untouched. `app/Http/Middleware/SecurityResponseHeaders.php` retains safe localized Google error keys when redacting server errors.
- Schema/configuration: the migration above and `.env.example`.
- Vue/services: `resources/js/components/auth/GoogleAuthButton.vue`, `resources/js/views/auth/register.vue`, `resources/js/views/auth/GoggleCallback.vue`, `resources/js/services/googleAuth.js`, `resources/js/services/AuthService/AuthService.js`.
- Locales: the 13 existing `resources/js/i18n/{ar,en,fr,de,ru,es,it,hi,ja,fa,zh,ur,tr}.json` dictionaries.
- Tests: `phpunit.xml`, `tests/Feature/Api/Auth/GoogleWebAuthTest.php`, `tests/Feature/Api/GoogleMobileExchangeTest.php`, `tests/Feature/Security/GoogleAccountSecurityTest.php`, `tests/Frontend/googleAuth.test.js`, `tests/Frontend/googleAuthService.test.js`.
- Report: this file. Build output under the existing ignored `public/build` directory was regenerated locally.

The checkout already contained substantial unrelated uncommitted work, including security changes in several shared files. Those changes were preserved. Navbar, Sanctum configuration, token storage, wallet/purchase logic, and existing authorization rules were reused.

## Deployment and remaining actions

1. Supply the OAuth credentials, application key, and exact console redirect settings; configure the canonical HTTPS origin and production cookies/session/cache stores.
2. Review and back up the target database. Apply the isolated migration during your normal release process, for example `php artisan migrate --path=database/migrations/2026_10_22_000001_add_google_identity_to_users.php --force`. Test the schema upgrade on a staging copy of the production database engine first. This task ran migrations only in PHPUnit's in-memory SQLite database.
3. Build/release the updated frontend and backend together, refresh configuration caches using the normal deployment workflow, and restart long-running workers if applicable. Old browser bundles that expect query-string tokens are incompatible with the secured callback.
4. Smoke-test a new Google user, a returning provider-linked user, cancellation, disabled accounts, required country/birth-date completion, locale retention, protected APIs, Navbar state, and logout. Also confirm a matching-email legacy account is safely rejected without changes.

**Explicit account linking remains unavailable**, as permitted by the request. Existing accounts without a recorded Google subject must sign in using their existing account/recovery method. They see a localized explanation. A future linking feature must require recent authentication to the existing Scemory account plus explicit consent and a freshly verified Google flow; do not backfill provider IDs by email or assign replacement passwords. Legacy Google-only users can use the existing email-based password recovery path where available, or an authenticated support recovery process. All existing account data remains intact.

The integration is implemented and locally tested. Real Google sign-in cannot be certified end-to-end until credentials and console settings are supplied and the credentialed browser/native smoke tests are completed.
