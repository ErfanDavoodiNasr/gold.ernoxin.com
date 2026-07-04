-- Schema for the gold/silver price platform.
--
-- price_points is an append-only time-series table keyed by (item_key,
-- fetched_at). The UNIQUE constraint drives Eloquent's updateOrCreate, while
-- the AUTO_INCREMENT id is kept only because Eloquent's save() method cannot
-- express a composite WHERE — it always scopes updates to $primaryKey, so
-- removing the surrogate id would corrupt data on upserts.
--
-- An additional covering index on (item_key, current_value, fetched_at)
-- accelerates the latest-price-by-item query used by MarketCatalog and the
-- chart range scans in PriceHistoryQuery.

CREATE TABLE IF NOT EXISTS `price_points`
(
    `id`
    BIGINT
    UNSIGNED
    NOT
    NULL
    AUTO_INCREMENT,
    `item_key`
    VARCHAR
(
    255
) NOT NULL,
    `current_value` DECIMAL
(
    18,
    4
) DEFAULT NULL,
    `high_value` DECIMAL
(
    18,
    4
) DEFAULT NULL,
    `low_value` DECIMAL
(
    18,
    4
) DEFAULT NULL,
    `yesterday_avg_value` DECIMAL
(
    18,
    4
) DEFAULT NULL,
    `change_value` DECIMAL
(
    18,
    4
) DEFAULT NULL,
    `change_percent` DECIMAL
(
    10,
    4
) DEFAULT NULL,
    `direction` ENUM
(
    'asc',
    'desc',
    'none'
) NOT NULL DEFAULT 'none',
    `raw_payload` JSON NOT NULL,
    `fetched_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY
(
    `id`
),
    UNIQUE KEY `price_points_item_key_fetched_at_unique`
(
    `item_key`,
    `fetched_at`
),
    KEY `price_points_fetched_at_index`
(
    `fetched_at`
),
    KEY `price_points_item_key_current_fetched_at_index`
(
    `item_key`,
    `current_value`,
    `fetched_at`
)
    ) ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
