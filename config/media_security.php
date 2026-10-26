<?php

return [
    'max_pixels' => 40000000,
    'max_dimension' => 20000,
    'max_video_seconds' => 3600,
    'video_process_timeout' => 300,
    // Card-size derivatives of the watermarked preview (longest edge, encoder quality).
    'thumbnail_max_edge' => (int) env('MEDIA_THUMBNAIL_MAX_EDGE', 1280),
    'thumbnail_quality' => (int) env('MEDIA_THUMBNAIL_QUALITY', 82),
    // Browser/CDN cache lifetime for previews and thumbnails of published media only.
    // A takedown can stay visible to viewers who already loaded it for this long; 0 disables.
    'public_max_age' => max(0, (int) env('MEDIA_PUBLIC_MAX_AGE', 3600)),
];
