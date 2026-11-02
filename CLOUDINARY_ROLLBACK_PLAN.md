# Cloudinary media rollback

1. Set `CLOUDINARY_IMAGES_ENABLED=false` and `CLOUDINARY_VIDEOS_ENABLED=false`; clear Laravel config cache and restart queue workers. New uploads use local storage. Cloudinary-backed public images use `cloudinary_media_assets.local_path`, and event previews use `events_images.fallback_preview_url`, so existing new-media delivery immediately uses retained local copies. Keep `CLOUDINARY_URL` configured while any Cloudinary references remain.
2. Keep the additive `cloudinary_media_assets` table and `events_images` metadata columns. Do not roll back the schema while referenced Cloudinary assets exist. Legacy local media is unaffected by either flag.
3. If an older Cloudinary-backed event preview lacks `fallback_preview_url`, use a reviewed operator script to verify the retained local derivative before updating `preview_url` and `preview_disk='media'`; do not expose `full_url` as a replacement for a paid preview.
4. Public catalog images uploaded before this dual-copy implementation and lacking `local_path` need a reviewed per-record copy back to local storage. Do not rewrite pointers blindly. Gift images referenced by historical transactions must stay available.
5. After traffic is back on local delivery, reconcile Cloudinary assets against `cloudinary_media_assets`, delete confirmed orphans one by one, and only then consider dropping the additive schema. Keep private originals and purchase entitlement records intact throughout.

No historical bulk migration or production data operation is part of this branch.
