<?php

return [
    'disk' => env('UPLOAD_DISK', 'public'),
    'max_size_kb' => (int) env('UPLOAD_MAX_SIZE_KB', 5120),
    'mimetypes' => ['image/jpeg', 'image/png', 'image/webp'],
    'directories' => [
        'reports' => 'reports',
        'activities' => 'activities',
        'covers' => 'covers',
    ],
    'report_photos_limit' => (int) env('REPORT_PHOTO_LIMIT', 5),
];
