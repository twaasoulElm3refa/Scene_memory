# Cloudinary authenticated-image rollback

Development backups made before the 2026-11-02 deployment:

- `/home/scemory/tmp/scemory-code-before-auth-20261102.tar.gz` — affected existing application files.
- `/home/scemory/tmp/scemory-bootstrap-before-auth-20261102.php` — Laravel bootstrap.
- `/home/scemory/tmp/scemory-vendor-before-auth-20261102.tar.gz` — Composer vendor tree.
- `/home/scemory/tmp/scemory-schema-before-auth-20261102.sql` — prior definitions of the two altered tables.

These files are private to the Scemory account. They are development rollback aids, not independent off-server media backups.

1. Keep `MEDIA_TEMP_ORIGINAL_CLEANUP_ENABLED=false`. Do not delete Cloudinary assets, S3 backups, retained local originals, previews, entitlements, or queued source files during rollback.
2. Set `CLOUDINARY_IMAGES_ENABLED=false` for new image uploads if Cloudinary is failing. Existing authenticated Cloudinary originals still require the current `DownloadController` and `CLOUDINARY_URL`; do **not** blindly restore the old download controller or remove the Cloudinary SDK. The feature flag does not change existing purchased-file references.
3. If a new code release itself is defective, restore selected application files from the code archive only after checking for authenticated-original records created since deployment. Keep the new download/asset interpretation in place for any such records. Re-deploy a corrected compatible version rather than dropping the new columns.
4. Keep the additive migration applied while any `events_images.original_disk=cloudinary` row or `cloudinary_media_assets.delivery_type=authenticated` row exists. The schema snapshot helps review an eventual reverse migration; it is not a data restore.
5. Recheck queue ownership and pending jobs before changing `ProcessEventImageJob` payload handling. Let a compatible worker finish or preserve queued jobs for a compatible code version. Clear only this application's config/route cache and restart only its worker if needed.
6. Verify a creator or entitled buyer can still download an existing authenticated original via Laravel, a legacy buyer can download a local original, a published local preview remains viewable, and unauthorized access remains denied. Use the retained private original as the outage fallback only while it still exists.
7. Reconcile any authenticated Cloudinary records whose media row was explicitly deleted. Do this per record after reviewing purchase, backup, retention, and legal requirements; automatic 72-hour cleanup never acts on orphaned rows.

No global service restart, unrelated site change, historical bulk migration, or production operation is part of this rollback.
