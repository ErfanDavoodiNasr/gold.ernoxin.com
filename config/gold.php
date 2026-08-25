<?php

return [
    'source_key' => env('ESTJT_SOURCE_KEY') ?: 'estjt',
    'source_name' => env('ESTJT_SOURCE_NAME') ?: 'اتحادیه صنف فروشندگان و سازندگان طلا و جواهر و نقره و سکه تهران',
    'source_url' => env('ESTJT_SOURCE_URL', 'https://www.estjt.ir/price/'),
    'fetch_interval_minutes' => (int)env('ESTJT_FETCH_INTERVAL_MINUTES', 1),
    'frontend_refresh_seconds' => (int)env('FRONTEND_REFRESH_SECONDS', 60),
    'summary_cache_seconds' => (int)env('MARKET_SUMMARY_CACHE_SECONDS', 10),
    'history_cache_seconds' => (int)env('MARKET_HISTORY_CACHE_SECONDS', 45),
    'history_cache_seconds_medium' => (int)env('MARKET_HISTORY_CACHE_SECONDS_MEDIUM', 120),
    'history_cache_seconds_long' => (int)env('MARKET_HISTORY_CACHE_SECONDS_LONG', 300),
    'latest_fetch_cache_seconds' => (int)env('LATEST_FETCH_CACHE_SECONDS', 10),
    'chart_sql_bucket_threshold_minutes' => (int)env('CHART_SQL_BUCKET_THRESHOLD_MINUTES', 360),
    'timeout_connect' => (int)env('ESTJT_TIMEOUT_CONNECT', 3),
    'timeout_read' => (int)env('ESTJT_TIMEOUT_READ', 5),
    'retry_count' => (int)env('ESTJT_RETRY_COUNT', 1),
    'retry_backoff_milliseconds' => (int)env('ESTJT_RETRY_BACKOFF_MS', 150),
    'chart_default_range' => env('CHART_DEFAULT_RANGE', '1d'),
    'chart_available_ranges' => array_values(array_filter(array_map('trim', explode(',', env('CHART_AVAILABLE_RANGES', '1h,2h,6h,12h,1d,7d,30d,90d,180d,365d'))))),
    'chart_max_points' => (int)env('CHART_MAX_POINTS', 600),
    'history_max_days' => (int)env('HISTORY_MAX_DAYS', 365),
    'outlier' => [
        'spike_min' => (float)env('OUTLIER_SPIKE_MIN', 8.0),
        'spike_max' => (float)env('OUTLIER_SPIKE_MAX', 12.0),
    ],
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
    // Fixed id/slug — never reorder-dependent. Adding items must use a new unused id.
    'known_items' => [
        'gold' => [
            ['id' => 1, 'slug' => 'ounce', 'name' => 'انس طلا'],
            ['id' => 2, 'slug' => 'mozaneh', 'name' => 'مظنه تهران'],
            ['id' => 3, 'slug' => '18k', 'name' => 'طلای ۱۸ عیار'],
            ['id' => 4, 'slug' => '24k', 'name' => 'طلای ۲۴ عیار'],
        ],
        'coin' => [
            ['id' => 5, 'slug' => 'bahar-old', 'name' => 'سکه طرح قدیم'],
            ['id' => 6, 'slug' => 'emami', 'name' => 'سکه طرح جدید'],
            ['id' => 7, 'slug' => 'half', 'name' => 'نیم سکه'],
            ['id' => 8, 'slug' => 'quarter', 'name' => 'ربع سکه'],
            ['id' => 9, 'slug' => 'gram', 'name' => 'سکه یک گرمی'],
        ],
    ],
    // intrinsic = weight_g * (purity / reference_purity) * price(reference_item)
    'coin_bubble' => [
        'reference_item' => 'طلای ۱۸ عیار',
        'reference_purity' => 0.750,
        'coins' => [
            'سکه طرح قدیم' => ['weight_g' => 8.133, 'purity' => 0.900],
            'سکه طرح جدید' => ['weight_g' => 8.133, 'purity' => 0.900],
            'نیم سکه' => ['weight_g' => 4.0665, 'purity' => 0.900],
            'ربع سکه' => ['weight_g' => 2.03325, 'purity' => 0.900],
            'سکه یک گرمی' => ['weight_g' => 1.0, 'purity' => 0.900],
        ],
    ],
    'features' => [
        'dark_mode' => true,
    ],
    'hosting' => [
        'ensure_writable_paths' => true,
    ],
    'theme_default' => env('THEME_DEFAULT') ?: 'system',
    'theme_accent' => env('THEME_ACCENT') ?: '#d9a441',
];
