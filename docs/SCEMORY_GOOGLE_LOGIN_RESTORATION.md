# Scemory Google Login restoration

## Reference and historical behavior

The AI Pro reference starts Google OAuth from Vue, resolves the returned Socialite identity on Laravel, creates or finds a user, issues a Sanctum token, and returns the user to the authenticated application. Its smooth user experience came from resolving users by email with `firstOrCreate`, using stateless OAuth, and placing bearer credentials in the redirect URL. Those implementation details were not copied because they weaken account-linking, OAuth state, and token confidentiality.

Scemory historically followed a similar email-based lookup. The old `GoogleAuthController` used `Socialite::driver('google')->stateless()->user()`, called `firstOrCreateByEmail`, and assigned `Hash::make('password')` to newly created Google users. It did not store Google's stable subject identifier. The later `google_id` migration preserved existing rows but had no authoritative subject data with which to backfill them.

The secure Google rewrite stopped trusting an email match and required ownership proof before linking. This correctly prevented email-based account takeover, but every historical Google row without `google_id` was classified like an ordinary password account. The callback displayed “Link your Google account,” while the historical row contained a shared placeholder hash rather than a password the user knew. Password confirmation therefore could never succeed. Historical token names also cannot prove that the current Google identity owns an old account.

## Restored account resolution

Scemory now handles each account state explicitly:

- A verified Google subject matching `users.google_id` signs in immediately. The original user ID, role, wallet, plans, credits, purchases, events, profile, and relationships remain attached to the same row.
- A verified Google identity with no account conflict creates a basic account immediately. The stable Google subject and safe provider profile fields are stored. No country, date of birth, profile-completion form, OTP, or application password is required. The schema permits a null password, so no synthetic password is created.
- A matching account with a real password receives the existing one-time password-confirmation link flow. Email matching alone never links it.
- A matching passwordless account with another provider identity must sign in through its existing method and connect Google from the authenticated profile. It is never shown an impossible password form.
- A passwordless historical candidate with no stored Google or Facebook identity receives a Google-specific, one-time recovery code at the address already registered on the account. This path is available only after a valid, state-checked Google callback and is bound to that browser session. The code is stored only as a hash, expires after 10 minutes, permits at most five attempts, and is send-rate-limited. Mail transports that can log message bodies are rejected. Successful proof persists the verified Google subject on the original row; later Google sign-ins are immediate.

Native clients cannot safely share the browser's Laravel recovery session. An unresolved historical native login is directed to complete the one-time recovery on the Scemory website. Once linked, native Google login is direct as well.

The callback and delivery exchange continue to enforce verified Google email and subject data, server-side Socialite code exchange, OAuth state, web PKCE, native encrypted single-use state and delivery PKCE, session-bound single-use web delivery codes, disabled-account checks, concurrent uniqueness checks, Sanctum token issuance in response bodies, and no bearer tokens in URLs.

## Frontend behavior

Recognized linked users and new eligible users follow:

`Continue with Google → Google OAuth → Scemory authenticated destination`

The callback writes the returned Sanctum credential through the existing token storage, updates the existing local authentication state, dispatches the navbar login event, honors only safe local post-authentication redirects, and removes callback parameters from browser history before exchanging credentials.

The callback displays a form only for a genuine collision requiring proof. A real password account gets the password-link form. An unresolved passwordless historical account automatically sends and requests its one-time recovery code. Provider-owned conflicts receive an accurate instruction instead of a generic “unavailable” error. All Google-specific messages are present in the existing 13 locale dictionaries.

## Facebook isolation

No Facebook controller, Socialite configuration, credential, account service, callback/exchange implementation, component, button, style, or translation was modified. The shared route file contains two added Google recovery routes only; existing Facebook route declarations are unchanged. The complete Facebook backend and frontend regression suites pass.

## Changed files

Backend:

- `app/Http/Controllers/api/auth/GoogleAuthController.php`
- `app/Services/GoogleAccountService.php`
- `app/Services/GoogleLegacyRecoveryService.php`
- `app/Mail/GoogleRecoveryCodeMail.php`
- `resources/views/mail/google-recovery-code.blade.php`
- `routes/api.php`

Frontend:

- `resources/js/views/auth/GoggleCallback.vue`
- `resources/js/services/AuthService/AuthService.js`
- `resources/js/services/googleAuth.js`
- `resources/js/i18n/{ar,de,en,es,fa,fr,hi,it,ja,ru,tr,ur,zh}.json`

Tests:

- `tests/Feature/Api/Auth/GoogleWebAuthTest.php`
- `tests/Frontend/googleAuth.test.js`
- `tests/Frontend/googleAuthService.test.js`

Documentation:

- `docs/SCEMORY_GOOGLE_LOGIN_RESTORATION.md`

This implementation adds no migration, dependency, production credential, or environment-file change. A separate working-tree modification to `.env.example` was detected and deliberately left untouched; it is not part of this implementation.

## Validation results

- PHP formatting: `vendor/bin/pint --test ...` — 5 files passed.
- Focused Google and Facebook backend suites — 56 tests passed, 614 assertions.
- Complete backend suite: `php artisan test --compact` — 366 passed, 9 skipped, 2,772 assertions. The nine skips are the repository's opt-in MySQL concurrency tests.
- Focused Google frontend suites — 35 tests passed.
- Complete frontend suite: `npm test` — 182 tests passed across 20 files. The existing jsdom run emitted a non-failing localhost connection-refused console message from another suite.
- Production frontend build: `npm run build` — passed. Existing Sass deprecation and large-chunk warnings remain.

The OAuth tests use Socialite with mocked Google token and user-info HTTP responses. They verify the real Laravel session, state, PKCE, callback, recovery, Sanctum, protected-route, and Vue authentication behavior without contacting Google or exposing credentials.

Before this implementation, a read-only inspection of `https://dev.scemory.com` and its deployed Vite assets confirmed that the deployed callback still contained the password-link flow and did not contain the recovery client. No interactive live Google authentication was performed, and this change has not been deployed. The live site therefore has not been claimed as fixed.

## Remaining limitations

- A historical account without an authoritative `google_id` must complete one proof step; silently joining by email would reintroduce account takeover risk.
- Recovery requires access to the mailbox already registered on the account and a non-logging production mail transport. A user without that access requires support-assisted identity review.
- A collision with a real password or another provider remains intentionally separate until the user proves control through that existing authentication method.
- Historical native users complete the one-time recovery on the web because the native OAuth callback cannot inherit a Laravel browser session. Future native sign-ins are seamless after linkage.

## Manual deployment to dev.scemory.com

1. Review and deploy the changed source files through the normal release process. No database migration is required for this change.
2. Keep the existing Google client ID, client secret, redirect URI, frontend URL, and mobile app-link settings unchanged.
3. Confirm the configured production mailer sends messages and is neither the `log` transport nor a failover/round-robin chain containing `log`.
4. Install the locked PHP and Node dependencies using the project's normal deployment commands, then run `npm run build`.
5. Clear and rebuild Laravel configuration and route caches through the normal deployment process so the two recovery routes are available.
6. Run the focused Google, Facebook, and email/password regression tests in the deployment environment.
7. Smoke-test one already linked Google account, one new test Google identity, and one controlled passwordless legacy fixture. Confirm the legacy fixture retains the same user ID and data and that its second Google sign-in is direct.
8. Verify that callback URLs contain only the short-lived delivery code or stable error key and never contain a Sanctum token, provider token, email address, or recovery code.
