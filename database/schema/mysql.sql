CREATE TABLE IF NOT EXISTS `migrations`
(
    `id`
    int
    unsigned
    NOT
    NULL
    AUTO_INCREMENT,
    `migration`
    varchar
(
    255
) COLLATE utf8mb4_unicode_ci NOT NULL,
    `batch` int NOT NULL,
    PRIMARY KEY
(
    `id`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `market_items`
(
    `id`
    bigint
    unsigned
    NOT
    NULL
    AUTO_INCREMENT,
    `source`
    varchar
(
    40
) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'estjt',
    `category` varchar
(
    30
) COLLATE utf8mb4_unicode_ci NOT NULL,
    `name` varchar
(
    255
) COLLATE utf8mb4_unicode_ci NOT NULL,
    `normalized_name` varchar
(
    255
) COLLATE utf8mb4_unicode_ci NOT NULL,
    `currency` varchar
(
    20
) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `is_active` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `meta` json DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY
(
    `id`
),
    UNIQUE KEY `market_items_source_normalized_name_unique`
(
    `source`,
    `normalized_name`
),
    KEY `market_items_category_index`
(
    `category`
),
    KEY `market_items_normalized_name_index`
(
    `normalized_name`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `price_points`
(
    `id`
    bigint
    unsigned
    NOT
    NULL
    AUTO_INCREMENT,
    `market_item_id`
    bigint
    unsigned
    NOT
    NULL,
    `current_value`
    decimal
(
    18,
    4
) DEFAULT NULL,
    `high_value` decimal
(
    18,
    4
) DEFAULT NULL,
    `low_value` decimal
(
    18,
    4
) DEFAULT NULL,
    `yesterday_avg_value` decimal
(
    18,
    4
) DEFAULT NULL,
    `change_value` decimal
(
    18,
    4
) DEFAULT NULL,
    `change_percent` decimal
(
    10,
    4
) DEFAULT NULL,
    `direction` enum
(
    'asc',
    'desc',
    'none'
) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
    `raw_payload` json NOT NULL,
    `fetched_at` timestamp NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY
(
    `id`
),
    UNIQUE KEY `price_points_market_item_id_fetched_at_unique`
(
    `market_item_id`,
    `fetched_at`
),
    KEY `price_points_fetched_at_index`
(
    `fetched_at`
),
    KEY `price_points_market_item_id_fetched_at_index`
(
    `market_item_id`,
    `fetched_at`
),
    CONSTRAINT `price_points_market_item_id_foreign` FOREIGN KEY
(
    `market_item_id`
) REFERENCES `market_items`
(
    `id`
) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fetch_logs`
(
    `id`
    bigint
    unsigned
    NOT
    NULL
    AUTO_INCREMENT,
    `source`
    varchar
(
    40
) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'estjt',
    `status` varchar
(
    20
) COLLATE utf8mb4_unicode_ci NOT NULL,
    `http_status` smallint unsigned DEFAULT NULL,
    `items_count` int unsigned NOT NULL DEFAULT '0',
    `message` text COLLATE utf8mb4_unicode_ci,
    `reference_id` varchar
(
    80
) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `started_at` timestamp NULL DEFAULT NULL,
    `finished_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY
(
    `id`
),
    KEY `fetch_logs_status_index`
(
    `status`
),
    KEY `fetch_logs_status_started_at_index`
(
    `status`,
    `started_at`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_unicode_ci;

INSERT
IGNORE INTO `migrations` (`migration`, `batch`) VALUES
('2026_05_08_000001_create_market_items_table', 1),
('2026_05_08_000002_create_price_points_table', 1),
('2026_05_08_000003_create_fetch_logs_table', 1),
('2026_05_19_000001_add_started_at_index_to_fetch_logs_table', 2);
