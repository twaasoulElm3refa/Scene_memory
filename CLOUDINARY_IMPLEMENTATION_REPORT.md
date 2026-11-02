# Cloudinary implementation report — 2026-11-02

Local branch: `feature/cloudinary-unified-media`. Development checkout: `/home/scemory/Scene_memory`, deployed Git base `9698299` on `main` with scoped uncommitted application changes. No commit, push, public production deployment, historical bulk migration, or customer-media deletion was performed.

## Root cause and image architecture

The earlier Cloudinary integration sent blurred/watermarked event previews after approval; originals stayed only on the private server disk. The approved architecture now sends **new clean event-image originals** as Cloudinary `type=authenticated`, regardless of moderation status. New pixel-blurred/watermarked previews remain local. Legacy Cloudinary previews remain usable. `ProcessEventImageJob` and `EventMediaIngest` share `UnifiedMediaStorage`, while `GenerateEventAiTagsJob` uses the retained private original or a temporary authorized Cloudinary copy after cleanup.

The clean local source is retained for at least 72 hours after successful Cloudinary upload. `IndependentMediaBackup` provides S3-compatible encrypted private backup, streamed SHA-256 verification, and a restore test. Daily scheduler commands retry backups and check cleanup eligibility. **Automatic cleanup is disabled.** The development host has no S3 bucket/credentials/disk selected in its application configuration yet; user will set them server-side. Until then, backup status remains pending and clean local originals cannot be deleted by this cleanup command.

## API, purchase, and moderation contract

Internal `events_images.full_url` is an opaque Cloudinary reference for new authenticated originals; `preview_url` is the local watermarked JPEG path. API display `full_url` and `preview_url` remain Laravel preview links so existing Vue views show only publishable previews. The downloads API returns a short-lived signed Laravel `full_url` after entitlement lookup. `DownloadController` checks creator/admin ownership or a current purchase entitlement, fetches the original via a short-lived Cloudinary private-download URL server-side, and proxies it with no-store headers. It falls back only to the retained private original during an outage. Pending/rejected event previews remain unpublished. API authentication now returns JSON 401 even for browser-style requests that do not send an `Accept` header.

Purchased event media cannot be removed through the existing event/media deletion endpoints while an entitlement exists. For unpurchased record deletion, authenticated originals and retained local copies are deliberately kept for reviewed reconciliation; they are not selected by the active-record 72-hour cleanup query. No original is exposed via an unsigned Cloudinary URL. Legacy paid originals still on historical public paths require the separate reviewed `media:legacy-quarantine` procedure; none were moved by this change.

## Video and other image flows

The video workflow is unchanged: original paid videos remain private local files, and approved watermarked trailers may be delivered through public Cloudinary when the independent video flag is enabled. Server-to-Cloudinary video uploads above 10 MiB use 6 MiB chunks. No adaptive streaming, eager transcode, or browser resumable upload was added. Public catalog/category/comment/gift/cover images retain the prior unified Cloudinary plus local fallback behavior. Static images, legacy files, and user avatar reads remain unchanged.

## Free-plan limits and failure behavior

Image/video size limits, a local monthly upload-byte budget, cached Cloudinary credit usage, warning/stop thresholds, and independent flags remain. The byte budget is not equivalent to billing credits. A Cloudinary failure leaves a private clean original and local preview, records failure, and retries the queued image job; it cannot downgrade a paid original to a public URL. Backup failure leaves the original private and blocks cleanup. The cleanup command checks authenticated identity, authorized retrievability, SHA-256, backup privacy/integrity, media readiness, a processing lock, approved path scope, and the 72-hour clock. It is bounded and supports dry-run.

## Modified code

- `ProcessEventImageJob`, `EventMediaIngest`, `ImageAnalysisService` (existing preview optimization), `GenerateEventAiTagsJob`, and `PromotePublishedMediaPreviews`: create/retain clean originals, generate local previews, upload authenticated originals without approval dependency, preserve analysis and video promotion.
- `UnifiedMediaStorage`, `IndependentMediaBackup`, `CloudinaryMediaAsset`, `EventsImges`, and the additive 2026-11-02 migration: authenticated Cloudinary ledger, private S3 backup metadata, cleanup status, and secure API serialization.
- `DownloadController`, `MediaDeliveryController`, `MediaAccessService`, `bootstrap/app.php`: authorized original delivery, private fallback, preview visibility, reliable API 401.
- `BackupPendingOriginals`, `CleanupTemporaryOriginals`, `routes/console.php`, `config/cloudinary_media.php`, `.env.example`, `composer.json/lock`: scheduled backup and cleanup, S3 adapter, feature gates.
- `EventImageController`, `EventAdminController`, `UserDashboardController`: prevent deletion of purchased media.
- `ProbeCloudinaryImage`, `phpunit.xml`, and the focused media/auth tests: synthetic protected-asset probe and deterministic test isolation.
- This report, `MEDIA_UPLOAD_AUDIT.md`, `CLOUDINARY_MEDIA_ARCHITECTURE.md`, `CLOUDINARY_TEST_RESULTS.md`, and `CLOUDINARY_ROLLBACK_PLAN.md`: operational record.

The updated synthetic probe command exists locally; the development host's root-owned `app/Console/Commands` directory prevented updating that diagnostic file through the scoped `scemory` account. This does not affect upload, delivery, backup, or cleanup code.

## Verification and remaining work

See `CLOUDINARY_TEST_RESULTS.md` for counts and exact real/mocked scope. Local implementation: **yes**. Development code and additive migration: **yes**. Development page and legacy preview: **verified**. Real authenticated original on the development Cloudinary account: **verified synthetically**. Deployed new-event upload and purchase journey: **not yet exercised**. Independent S3 backup/restore: **not yet verified**. 72-hour retention and daily cleanup: **implemented and mocked**; automatic deletion: **disabled**.

Minimum next steps: configure the approved private S3-compatible bucket and credentials on the development host, set `MEDIA_BACKUP_DISK=s3`, then run a synthetic backup and `media:backup-originals --media=ID --restore-check`. Test a new regular-user event upload and purchase on development using a dedicated fixture account, verify local preview and authenticated original metadata, and keep cleanup off. After backup restoration and a reviewed retention policy succeed, obtain explicit operational approval before setting `MEDIA_TEMP_ORIGINAL_CLEANUP_ENABLED=true`. Production migration and release require separate approval.
