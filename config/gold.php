<?php

return [
    'source_key' => env('ESTJT_SOURCE_KEY') ?: 'estjt',
    'source_name' => env('ESTJT_SOURCE_NAME') ?: 'اتحادیه صنف فروشندگان و سازندگان طلا و جواهر و نقره و سکه تهران',
    'source_url' => env('ESTJT_SOURCE_URL', 'https://www.estjt.ir/price/'),
    'fetch_interval_minutes' => (int)env('ESTJT_FETCH_INTERVAL_MINUTES', 5),
    'timeout_connect' => (int)env('ESTJT_TIMEOUT_CONNECT', 3),
    'timeout_read' => (int)env('ESTJT_TIMEOUT_READ', 8),
    'retry_count' => (int)env('ESTJT_RETRY_COUNT', 2),
    'retry_backoff_milliseconds' => (int)env('ESTJT_RETRY_BACKOFF_MS', 300),
    'chart_default_range_days' => (int)env('CHART_DEFAULT_RANGE_DAYS', 7),
    'chart_available_ranges' => array_filter(array_map('intval', explode(',', env('CHART_AVAILABLE_RANGES', '1,7,30,90,180,365')))),
    'chart_max_points' => (int)env('CHART_MAX_POINTS', 600),
    'history_max_days' => (int)env('HISTORY_MAX_DAYS', 365),
    'http_headers' => [
        'user_agent' => env('ESTJT_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'),
        'accept_language' => env('ESTJT_ACCEPT_LANGUAGE', 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7'),
        'referer' => env('ESTJT_REFERER', 'https://www.estjt.ir/'),
    ],
    'blocked_page_patterns' => ['captcha', 'cloudflare', 'access denied', 'security check', 'attention required'],
    'table_header_labels' => [
        'gold' => env('ESTJT_GOLD_HEADER_LABEL', 'نوع طلا'),
        'coin' => env('ESTJT_COIN_HEADER_LABEL', 'نوع سکه'),
    ],
    'known_items' => [
        'gold' => ['انس طلا', 'مظنه تهران', 'طلای ۱۸ عیار', 'طلای ۲۴ عیار'],
        'coin' => ['سکه طرح قدیم', 'سکه طرح جدید', 'نیم سکه', 'ربع سکه', 'سکه یک گرمی'],
    ],
    'features' => [
        'auto_fetch' => true,
        'dark_mode' => true,
        'manual_fetch_api' => false,
    ],
    'hosting' => [
        'auto_migrate' => true,
        'ensure_writable_paths' => true,
    ],
    'theme_default' => env('THEME_DEFAULT') ?: 'dark',
    'theme_accent' => env('THEME_ACCENT') ?: '#d9a441',
];
