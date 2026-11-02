<?php

return [
    'url' => env('CLOUDINARY_URL'),
    'images_enabled' => (bool) env('CLOUDINARY_IMAGES_ENABLED', false),
    'videos_enabled' => (bool) env('CLOUDINARY_VIDEOS_ENABLED', false),
    'max_image_mb' => (int) env('MEDIA_MAX_IMAGE_MB', 10),
    'max_video_mb' => (int) env('MEDIA_MAX_VIDEO_MB', 50),
    'max_monthly_upload_mb' => (int) env('CLOUDINARY_MONTHLY_UPLOAD_MB', 1024),
    'quota_warning_percent' => (int) env('CLOUDINARY_WARNING_PERCENT', 70),
    'quota_stop_percent' => (int) env('CLOUDINARY_STOP_PERCENT', 90),
    'allow_local_fallback' => (bool) env('CLOUDINARY_ALLOW_LOCAL_FALLBACK', true),
    'temp_original_retention_hours' => max(72, (int) env('MEDIA_TEMP_ORIGINAL_RETENTION_HOURS', 72)),
    'temp_original_cleanup_enabled' => (bool) env('MEDIA_TEMP_ORIGINAL_CLEANUP_ENABLED', false),
    'require_verified_independent_backup' => (bool) env('MEDIA_REQUIRE_VERIFIED_INDEPENDENT_BACKUP', true),
    'backup_disk' => env('MEDIA_BACKUP_DISK'),
    'backup_prefix' => env('MEDIA_BACKUP_PREFIX', 'scemory/originals'),
    'cleanup_batch_limit' => min(100, max(1, (int) env('MEDIA_CLEANUP_BATCH_LIMIT', 25))),
];
