<?php

return [
    'event_tags_limit' => (int) env('AI_EVENT_TAGS_LIMIT', 8),
    'image_tags_limit' => (int) env('AI_IMAGE_TAGS_LIMIT', 10),
    'images_limit' => (int) env('AI_IMAGES_LIMIT', 5),
    'language' => env('AI_TAGS_LANGUAGE', 'ar'),
    'queue' => env('AI_TAGS_QUEUE', 'default'),
    'overlap_expire_after' => (int) env('AI_TAGS_OVERLAP_EXPIRE_AFTER', 600),
];
