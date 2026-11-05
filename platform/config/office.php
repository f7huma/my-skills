<?php

return [
    // الاسم الرسمي للمكتب كما يظهر في الشعار — يُستخدم نصيًا في الواجهة والتقارير
    'name' => 'مكتب فيصل حميضان الحميضان للمحاماة والاستشارات القانونية',
    'short_name' => 'فيصل حميضان الحميضان',
    'tagline' => 'للمحاماة والاستشارات القانونية',
    'license_number' => '47529',
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Riyadh'),
    // عطلة نهاية الأسبوع في المملكة: الجمعة والسبت (ISO: 5=Fri, 6=Sat)
    'weekend_days' => [5, 6],
    'poa_expiry_warning_days' => (int) env('POA_EXPIRY_WARNING_DAYS', 30),
    'currencies' => ['SAR', 'USD', 'EUR', 'AED', 'KWD', 'BHD', 'QAR', 'OMR', 'EGP', 'GBP'],
    // قرار المكتب: إلزامية افتراضيًا في الإنتاج، ويمكن إيقافها بـ MFA_REQUIRED_FOR_LAWYER=false (تبقى اختيارية لكل مستخدم)
    'mfa_required' => (bool) env('MFA_REQUIRED_FOR_LAWYER', env('APP_ENV') === 'production'),
    'synthetic_only' => (bool) env('SYNTHETIC_DATA_ONLY', false),
    'noindex' => (bool) env('APP_NOINDEX', false),
    'preview_gate' => [
        'enabled' => (bool) env('PREVIEW_GATE_ENABLED', false),
        'user' => env('PREVIEW_GATE_USER'),
        'password_hash' => env('PREVIEW_GATE_PASSWORD_HASH'),
    ],
    'invitation_ttl_hours' => 72,
    'ops' => [
        'alert_mail' => env('OPS_ALERT_MAIL'),
        'alert_webhook' => env('OPS_ALERT_WEBHOOK'),
        'disk_min_free_percent' => (int) env('DISK_MIN_FREE_PERCENT', 15),
        'tls_check_host' => env('TLS_CHECK_HOST'),
        'tls_min_days' => (int) env('TLS_MIN_DAYS', 14),
    ],
];
