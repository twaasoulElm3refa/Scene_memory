# Cloudinary implementation report

Branch: `feature/cloudinary-unified-media`. Development code only; no production migration, deployment, or live Cloudinary request was made.

## 1. Image flows migrated

Existing event upload routes now create private originals and optional Cloudinary previews through `ProcessEventImageJob` or `EventMediaIngest`. Comment images, categories, subcategories, countries, event covers, footer logos and virtual gift images use `UnifiedMediaStorage::publicImage`. Models resolve Cloudinary references to URLs and old local paths as before. Moderation approval promotes only that event's eligible local previews. Fixed image display and thumbnail presets avoid an open-ended transformation API.

## 2. Video flows migrated

`ProcessEventVideoJob` retains the private original and locally generated watermarked trailer. When enabled on a published event, the trailer uploads to Cloudinary with chunking above 10 MiB. Its metadata includes bytes, format, dimensions and duration; browser preview redirects to public CDN playback and a fixed poster URL. Vue event playback has loading, error and poster states. Paid original video remains on the existing authorized Laravel endpoint.

## 3. Media still local

All historical media remains where it is. Paid originals and upload staging remain on `media` by design. Every newly uploaded public image keeps a local fallback copy, and Cloudinary event previews retain their local generated preview path. Previews for pending events remain local until approval. Disabled flags switch new and existing Cloudinary-backed records to local delivery. Static application assets and legacy user avatars are unchanged. The dormant `HomeController::create` now delegates to the shared event ingest service.

## 4. Marketplace protection

No paid full-resolution image or original video is uploaded to Cloudinary. `MediaAccessService::canDownload` checks active user, seller/admin ownership or a current entitlement. `DownloadController` serves private files with `no-store`; public CDN delivery contains only approved previews. Published preview URLs can be cached by viewers, as in the existing preview model. Historical protected originals still on the legacy public disk require the existing `media:legacy-quarantine` operator workflow before production; this branch does not silently move historical files.

## 5. Free plan limitations

Cloudinary Free offers 25 monthly credits shared across transformation, storage and bandwidth. Image limits default to 10 MiB, video to 50 MiB, and the local monthly Cloudinary upload budget to 1024 MiB. Credit warning and stop thresholds default to 70% and 90%. Video adaptive streaming and eager transcoding remain disabled to avoid extra processing. The SDK chunks large video transfers from Laravel, but browser uploads are not resumable. PHP and web-server request-size/time limits must be configured for the chosen 50 MiB maximum.

## 6. Tests

See `CLOUDINARY_TEST_RESULTS.md`. Cloudinary SDK calls were mocked for automated tests; no real credentials or live integration test was available. With `APP_DEBUG=false`, the full backend suite has one Google recovery assertion failure reproduced on untouched HEAD. The focused media suite, frontend tests and build pass.

## 7. Remaining before production

1. Review and apply the additive migration in a staging environment, configure `CLOUDINARY_URL` there, and run an explicitly authorized live upload/playback/delete smoke test with a small public image and video.
2. Set PHP/web-server upload and timeout limits; confirm the queue worker has `ffmpeg` and `ffprobe` and a shared private media disk.
3. Review Cloudinary console delivery settings, transformation restrictions and account usage; leave both flags off until the staged smoke test succeeds.
4. Audit and quarantine any legacy paid originals still reachable through `/storage` using the existing `media:legacy-quarantine` workflow, then verify entitlement downloads. Reconcile pre-existing test failures, run a clean backend suite without concurrent filesystem tests, and confirm the final production release gate.
5. Consider a durable orphan cleanup queue for DB failures after public catalog image upload. Replacement and deletion clean up Cloudinary category, country, subcategory, event cover, footer, comment and event preview assets. Old gift images are deliberately retained because historical transaction snapshots can reference them.
6. `composer audit` reports an existing medium `symfony/mime` advisory (CVE-2026-45070); update the dependency within a separately reviewed dependency change before deployment.

The code is ready for staged verification, not yet verified against a live Cloudinary account or deployed.
