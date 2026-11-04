<?php

return [
    'root' => env('ATTACHMENTS_ROOT') ? (str_starts_with(env('ATTACHMENTS_ROOT'), '/') ? env('ATTACHMENTS_ROOT') : base_path(env('ATTACHMENTS_ROOT'))) : storage_path('app/private/attachments'),
    'key' => env('FILES_ENCRYPTION_KEY'),
    'previous_keys' => array_filter(explode(',', (string) env('FILES_PREVIOUS_KEYS', ''))),
    'max_kb' => (int) env('ATTACHMENTS_MAX_KB', 25600),
    // الامتدادات المسموحة وتوقيعاتها المتوقعة (يُتحقق من المحتوى الفعلي عبر finfo)
    'allowed' => [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ],
    'scanner' => [
        'driver' => env('SCANNER_DRIVER', 'clamav'),
        'host' => env('CLAMAV_HOST', 'clamav'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 60),
        'max_attempts' => 5,
    ],
];
