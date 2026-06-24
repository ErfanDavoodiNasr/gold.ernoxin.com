CREATE TABLE IF NOT EXISTS `price_points`
(
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `item_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `current_value` decimal(18, 4) DEFAULT NULL,
    `high_value` decimal(18, 4) DEFAULT NULL,
    `low_value` decimal(18, 4) DEFAULT NULL,
    `yesterday_avg_value` decimal(18, 4) DEFAULT NULL,
    `change_value` decimal(18, 4) DEFAULT NULL,
    `change_percent` decimal(10, 4) DEFAULT NULL,
    `direction` enum('asc', 'desc', 'none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
    `raw_payload` json NOT NULL,
    `fetched_at` timestamp NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `price_points_item_key_fetched_at_unique` (`item_key`, `fetched_at`),
    KEY `price_points_fetched_at_index` (`fetched_at`),
    KEY `price_points_item_key_fetched_at_index` (`item_key`, `fetched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
