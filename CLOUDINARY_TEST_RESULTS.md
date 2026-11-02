# Cloudinary media test results

Run on 2026-11-01 in WSL development with SQLite in-memory DB and mock Cloudinary responses. No live Cloudinary calls were made.

| Check | Result |
| --- | --- |
| `UnifiedMediaStorageTest` | 7 passed, 34 assertions: upload metadata and URL presets, video chunk option/poster, quota fallback, flag-off local delivery, durable monthly budget, paid-video preview vs original authorization, moderation promotion, replacement cleanup |
| `MediaProtectionTest` | 13 passed, 63 assertions |
| `ProcessEventVideoJobTest` | 1 passed, 8 assertions |
| `DownloadEntitlementsTest` | 4 passed, 63 assertions; existing download response shape preserved |
| `CommentImageApiTest` | 7 passed, 41 assertions; legacy comment image behavior preserved |
| `UploadProcessingTest` | 2 passed, 15 assertions when run alone |
| `EventRequestModerationTest` | 10 passed, 57 assertions |
| `MediaThumbnailTest` | 8 passed, 46 assertions when run alone |
| Combined media/security/job/moderation/download/comment test command | 52 passed, 327 assertions in one sequential PHP process after dual local fallback changes |
| `npm test` | 36 files, 282 tests passed |
| `npm run build` | Passed; existing Sass, font resolution and bundle-size warnings |
| `git diff --check` | Passed |
| `composer audit` | One medium advisory in `symfony/mime` (CVE-2026-45070), outside the Cloudinary SDK |
| Full `APP_DEBUG=false php artisan test --compact` | 539 passed, 15 skipped, 1 failed: `GoogleWebAuthTest` expects `recovery_unavailable` but receives `unavailable`. The same failure reproduces on an isolated untouched HEAD worktree. |
| Full run with local `.env` (`APP_DEBUG=true`) | Earlier run showed PayPal 5xx response-shape failures and readiness-page secret-redaction failure; those depend on the development debug setting. One download response compatibility failure was found and fixed. No clean full-suite pass is claimed. |

Live image/video upload, Cloudinary CDN delivery, real quota response, real deletion and network interruption have **not** been exercised because no credentials and explicit live-test authorization were supplied. Real upload success is not claimed.
