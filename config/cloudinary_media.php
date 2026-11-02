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
];
