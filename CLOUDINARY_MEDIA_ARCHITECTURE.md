# Cloudinary media architecture

The new `UnifiedMediaStorage` service handles public images and public marketplace derivatives. It uses the official `cloudinary/cloudinary_php` 3.3 SDK on Laravel 12 / PHP 8.3. The API secret is read from server-side `CLOUDINARY_URL` only. The browser receives CDN URLs, never credentials or unsigned upload authority.

## Storage boundary

- Paid originals: `storage/app/media-private` (`media` disk). Laravel checks owner/admin or a current `entitlements` row before serving the original through `DownloadController`; video range requests use Symfony's binary response. These originals are never sent to Cloudinary.
- Public event previews: existing watermarked/downscaled derivative is generated locally first. If the event is published and the relevant flag is enabled, the derivative is uploaded to Cloudinary. The `preview_disk` value becomes `cloudinary`, and `fallback_preview_url` retains the private local preview path; `full_url` and `original_disk` remain private. Pending events are promoted by `PromotePublishedMediaPreviews` after moderation approval. Turning a flag off immediately serves that preview from the local fallback.
- Other public images: the same service first retains a local copy, then uploads category, subcategory, country, event cover, comment, footer and gift images. Their existing path fields store a `cloudinary/<type>/scemory/...` reference, and `cloudinary_media_assets.local_path` stores the public local fallback. Model accessors turn the reference into a Cloudinary URL or the local URL when the image flag is off. Old public paths still work.
- `cloudinary_media_assets` records `asset_id`, `public_id`, resource type, version, bytes, dimensions, duration, format and status. Deleted rows remain as ledger entries and count toward the upload budget for their calendar month. Additive `events_images` fields retain original media size, format, video duration and ready status.

## Delivery and cost

Public images use two fixed on-demand presets: display (`c_limit,w_1280,q_auto,f_auto`) and card (`c_limit,w_480,q_auto,f_auto`). Video trailers play as uploaded without eager transcoding; one 480px poster is derived on demand. Adaptive streaming is intentionally not enabled because it would create extra renditions and consume the shared Free plan credits. Large PHP SDK video uploads set a 6 MiB `chunk_size`; this chunks server-to-Cloudinary transfer, while browser-to-Laravel remains the existing upload flow.

Upload checks enforce configurable per-file image/video limits, a serialized monthly upload byte budget, and cached Admin API credit usage (six-hour cache, daily command). At 70% a warning is logged; at 90% Cloudinary uploads stop. Public images can fall back to the public disk if configured. Protected originals always stay private. The official [Free plan](https://cloudinary.com/documentation/billing_and_plans) provides 25 credits shared by transformations, storage and bandwidth; [PHP upload documentation](https://cloudinary.com/documentation/php_image_and_video_upload) supports `chunk_size` for large files. Cloudinary usage data is periodic, so the threshold is a safety limit rather than exact real-time billing control.

The service does not issue authenticated Cloudinary original URLs. This avoids relying on account features or URL obscurity for paid content. On a plan that provides verified authenticated delivery with expiration, that could be added separately after security and cost review.

## Failure behavior

Upload errors, missing credentials, incomplete API metadata, and quota/credit limits fail closed for Cloudinary. Public image fallback is configurable. Event derivatives retain the local copy. Cloudinary assets are destroyed if the DB metadata insert fails; retries of image/video jobs reuse an already completed record. SDK delete failures are logged for operator reconciliation. No automated historical bulk migration runs.
