<?php

$p = env('BACKUP_PATH');

return [
    'path' => $p ? (str_starts_with($p, '/') ? $p : base_path($p)) : storage_path('backups'),
    'key' => env('BACKUP_ENCRYPTION_KEY'),
    'retention_daily' => (int) env('BACKUP_RETENTION_DAILY', 14),
    'retention_weekly' => (int) env('BACKUP_RETENTION_WEEKLY', 8),
    'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 26),
    'offsite_path' => env('BACKUP_OFFSITE_PATH'),
    'pg_dump' => env('PG_DUMP_BINARY', 'pg_dump'),
    'pg_restore' => env('PG_RESTORE_BINARY', 'pg_restore'),
];
