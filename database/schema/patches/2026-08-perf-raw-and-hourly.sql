-- Run once on existing installs (phpMyAdmin). Safe pieces are IF NOT EXISTS;
-- drop raw_payload only if that column still exists.

CREATE TABLE IF NOT EXISTS `price_points_hourly`
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
    `bucket_at` TIMESTAMP NOT NULL,
    `current_value` DECIMAL
(
    18,
    4
) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY
(
    `id`
),
    UNIQUE KEY `price_points_hourly_item_bucket_unique`
(
    `item_key`,
    `bucket_at`
),
    KEY `price_points_hourly_item_bucket_value_index`
(
    `item_key`,
    `bucket_at`,
    `current_value`
)
    ) ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;

-- Soften legacy JSON so new rows need not store raw_payload:
ALTER TABLE `price_points` MODIFY `raw_payload` JSON NULL;

-- Skip if column already removed:
-- ALTER TABLE `price_points` DROP COLUMN `raw_payload`;
