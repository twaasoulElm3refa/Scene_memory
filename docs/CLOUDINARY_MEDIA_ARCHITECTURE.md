# Cloudinary media architecture

Updated 2026-11-02. The approved image lifecycle applies to new event images. Historical media and the current video path retain their existing delivery behavior.

## Image lifecycle

1. The Laravel upload endpoint validates media and ownership and stages the clean source on the private `media` disk, outside `public` and `storage:link`.
2. `ImageAnalysisService` reads the source without changing it. It produces a separate scaled, blurred, pixel-watermarked JPEG preview on `media`. The existing Scemory watermark design is used. A thumbnail may be generated from that preview.
3. `UnifiedMediaStorage::authenticatedOriginal` sends the clean source through the official Cloudinary PHP SDK with `resource_type=image`, `type=authenticated`, and a stable UUIDv5 public ID. Before retrying an upload, it checks the database ledger and the Cloudinary Admin API to recover an upload whose response was interrupted. Only the server holds Cloudinary credentials.
4. `events_images.full_url` stores an internal `cloudinary/image/...` reference, `original_disk=cloudinary`, `preview_url` stores the local preview path, and `preview_disk=media`. `local_original_path` holds the private retained clean copy. API serialization maps display `full_url`, `preview_url`, `url`, and `video` to the Laravel preview endpoint. The purchased downloads API supplies a five-minute signed application URL after entitlement lookup. Internal paths and the Cloudinary asset ID are not serialized as media display URLs.
5. `CloudinaryMediaAsset` records the authenticated delivery type, asset/public IDs, format, size, dimensions, SHA-256, upload timestamp, backup status/reference, and cleanup status. The upload timestamp starts the 72-hour minimum. Upload failure leaves the clean private original and preview, records `cloudinary_failed`, and rethrows for the queue retry.
6. Published preview and thumbnail endpoints enforce current event visibility before serving local pixels. Pending or rejected event previews stay private; existing signed moderation review access remains. Approval no longer uploads image previews to Cloudinary. Older Cloudinary preview references are still served through the legacy branch.
7. Original downloads check an active user and creator/admin ownership or a current entitlement. Laravel creates a 180-second Cloudinary `privateDownloadUrl`, retrieves and length-checks its bytes server-side, then returns them with private/no-store headers. It never sends that URL to the browser. On Cloudinary failure, it serves only a retained private copy. Revoked entitlements fail on the next request.

## Independent backup and cleanup

`IndependentMediaBackup` supports a separate private S3-compatible Laravel disk. It writes with AES256 server-side encryption, verifies object existence, byte count, private visibility, and streamed SHA-256, and can restore to a temporary private file for an integrity check. `media:backup-originals` retries pending/failed copies; `--media=ID --restore-check` verifies recovery. No same-VPS directory or Cloudinary internal backup counts as independent.

`media:cleanup-originals` is registered once daily, but automatic execution is disabled by default. It supports `--dry-run`, a bounded batch, and per-media cache locks. It deletes only `events/full/<fingerprint>.<format>` private clean copies for authenticated image records after at least 72 hours, when the media is ready, the Cloudinary asset can be found and retrieved with matching SHA-256, and the independent backup is still private and checksum-valid. An active AI-tagging lock also blocks it. Failures update `cleanup_status` and a sanitized block reason. Local previews, legacy originals, and videos are never selected.

The S3 bucket must have an independently reviewed retention/lifecycle policy; this application does not automatically expire backup objects. Enabling cleanup requires a successful restore test and explicit operational approval.

## Other media and Free plan

Public catalog images still use `UnifiedMediaStorage::publicImage` with local public fallback. Their fixed display and thumbnail presets are `c_limit,w_1280,q_auto,f_auto` and `c_limit,w_480,q_auto,f_auto`. Existing video originals remain private local files; approved watermarked trailers may use public Cloudinary playback and one fixed poster preset. Server-to-Cloudinary video transfer uses 6 MiB chunks for files above 10 MiB. There is no adaptive streaming, eager transcode, or browser resumable transfer.

Image and video flags are separate. Per-file limits, a monthly upload-byte budget, cached usage checks, a warning threshold, and a stop threshold limit Free-plan risk. The byte budget is not a Cloudinary billing-credit meter. Cloudinary's [billing documentation](https://cloudinary.com/documentation/billing_and_plans) describes the shared 25-credit Free plan; [access control](https://cloudinary.com/documentation/control_access_to_media) describes authenticated assets.

## Legacy and deletion

No historical bulk migration runs. Old private originals and Cloudinary previews continue through the existing disk/reference branches. Event/media deletion is denied while an entitlement exists. When an unpurchased event/media row is deleted, authenticated originals and retained local/backup copies are kept for explicit operator reconciliation; they are outside the 72-hour cleanup query once the row is gone. Existing public preview deletion remains. Do not purge those retained copies or drop the additive columns as a shortcut to rollback.
