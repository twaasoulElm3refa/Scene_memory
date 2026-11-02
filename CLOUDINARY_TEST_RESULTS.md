# Cloudinary media test results — 2026-11-02

## Automated local tests

| Check | Result |
| --- | --- |
| Full Laravel suite with SQLite in memory and Cloudinary image/video flags forced off for unrelated tests | **556 passed, 15 skipped, 0 failed; 6,702 assertions** |
| Focused authenticated-original lifecycle | 8 passed, 52 assertions: private backup, restore, 72-hour gate, disabled cleanup, active lock, bounded scan rotation, entitlement and revocation, private fallback, purchased-media deletion guard |
| Focused queued event image tests | 3 passed: pending event, authenticated original and local preview, retry/idempotency, upload failure, disabled flag |
| Frontend Vitest | **36 files, 282 passed, 0 failed** |
| Frontend production build | Passed; existing large-bundle warning |
| Composer manifest validation | Passed |

`phpunit.xml` now forces Cloudinary flags off and `APP_DEBUG=false` so a developer's real `.env` cannot make unrelated tests upload customer-like fixtures or change API error behavior. The Google recovery error-code assertion was updated to match the production-safe 503 response.

## Real Cloudinary synthetic test

The designated development account was tested with a generated 32×32 PNG through the official SDK, then the synthetic asset was removed. Results: authenticated upload metadata verified by the Admin API; unsigned original **401**; unsigned transformed original **401**; 120-second private-download URL returned matching bytes; already-expired private-download URL **401**; cleanup successful. No customer asset was used. This confirms the account's current authenticated delivery behavior; it does not guarantee future plan terms or quota availability.

## Development deployment checks

The scoped additive migration ran in 55 ms. `composer install --no-dev` installed the S3 adapter on PHP 8.2. The two new commands are registered through `bootstrap/app.php` because `app/Console/Commands` is root-owned on the development host. Scheduler lists daily backup and cleanup tasks. Cleanup dry-run inspected 0 new authenticated assets; actual cleanup returns `cleanup=disabled`. Backup returns `independent_backup=unconfigured` until the server-side S3 settings are supplied.

`/en/add_event` returned 200, existing Cloudinary preview for media 109 returned 200, and unauthenticated download returned 401 for both JSON and ordinary browser-style requests after the API auth redirect fix. The queue had 0 pending, delayed, or reserved jobs before deployment.

## Not yet verified

- S3 backup write, private visibility, integrity, and restoration on the development host: server-side bucket/credentials are not yet configured.
- A regular user creating a new event end to end on the deployed application; no synthetic customer account/event was created.
- A deployed purchase of a newly uploaded authenticated original; mocked entitlement tests and the real private-download probe cover the parts independently.
- 72-hour cleanup against a real eligible record. Automatic cleanup remains disabled.
- Production migration and deployment.
