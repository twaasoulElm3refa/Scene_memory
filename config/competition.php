<?php

return [
    // Calendar dates are in this timezone; persisted instants are UTC.
    'timezone' => env('COMPETITION_TIMEZONE', 'Asia/Riyadh'),
    'archive_time' => env('COMPETITION_ARCHIVE_TIME', '00:05'),
];
