-- Disposable PHPUnit schema patch for exswaping_phpunit_* only.
-- Structures mirrored from production app_exswapin (SHOW CREATE / SHOW COLUMNS).
-- No production rows. Safe to re-run (IF NOT EXISTS / information_schema guards).

-- tasks.id_who_completed (migration 9.2.2/2024_10_21_181731)
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `tasks` ADD COLUMN `id_who_completed` INT NOT NULL DEFAULT 0',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tasks'
    AND COLUMN_NAME = 'id_who_completed'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- gateways_merchants.alias (migration 9.2.2/2024_06_16_103109)
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `gateways_merchants` ADD COLUMN `alias` VARCHAR(191) NULL DEFAULT NULL',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'gateways_merchants'
    AND COLUMN_NAME = 'alias'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- direction_exchange.floating_fee / fix_fee (migration 9.2.2/2024_09_02_174516)
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `direction_exchange` ADD COLUMN `floating_fee` VARCHAR(191) NOT NULL DEFAULT ''0''',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'direction_exchange'
    AND COLUMN_NAME = 'floating_fee'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `direction_exchange` ADD COLUMN `fix_fee` VARCHAR(191) NOT NULL DEFAULT ''0''',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'direction_exchange'
    AND COLUMN_NAME = 'fix_fee'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `bestchange_directions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_direction_exchange` bigint NOT NULL DEFAULT '0',
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_currency_in` int NOT NULL DEFAULT '0',
  `id_currency_out` int NOT NULL DEFAULT '0',
  `position_num` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `min_reserve` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `max_reserve` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `step` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `reset_course` int NOT NULL DEFAULT '0',
  `standard_course` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `min_sum` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `max_sum` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `minmax_sum_fee` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `is_parser_formula` int NOT NULL DEFAULT '0',
  `min_sum_new_default_parser` bigint NOT NULL DEFAULT '0',
  `min_sum_new_default_parser_fee` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `min_sum_new_formula_parser` bigint NOT NULL DEFAULT '0',
  `min_sum_new_formula_parser_fee` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `whitelist_ids` longtext COLLATE utf8mb4_unicode_ci,
  `blacklist_ids` longtext COLLATE utf8mb4_unicode_ci,
  `city_id` bigint NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `is_favorite` tinyint(1) NOT NULL DEFAULT '0',
  `is_error_parser` int NOT NULL DEFAULT '0',
  `rate_value` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `source_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `exchange_in` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_out` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formula_value` text COLLATE utf8mb4_unicode_ci,
  `code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rate_value_without_step` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `rate_mode` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `top_n` smallint unsigned DEFAULT NULL,
  `explain_payload` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bestchange_directions_is_favorite_index` (`is_favorite`),
  KEY `idx_bestchange_directions_status_code` (`status`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `extra_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` json NOT NULL,
  `scope` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attach_to_order` tinyint(1) NOT NULL DEFAULT '1',
  `min_char` smallint unsigned NOT NULL DEFAULT '0',
  `max_char` smallint unsigned NOT NULL DEFAULT '0',
  `obligatory_field` tinyint unsigned NOT NULL DEFAULT '1',
  `remove_spaces` tinyint unsigned NOT NULL DEFAULT '0',
  `start_with` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_with` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validator_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int unsigned NOT NULL DEFAULT '0',
  `status` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extra_fields_key_id_unique` (`key_id`),
  KEY `extra_fields_scope_index` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `merchant_transaction_webhooks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` bigint NOT NULL,
  `merchant_service_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_currency` int NOT NULL DEFAULT '0',
  `id_merchant` int NOT NULL DEFAULT '0',
  `provider` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `json_callbacks` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `amount` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `ext_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  PRIMARY KEY (`id`),
  CONSTRAINT `merchant_transaction_webhooks_chk_1` CHECK (json_valid(`ext_params`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `merchants_transaction_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` bigint NOT NULL DEFAULT '0',
  `id_currency` int NOT NULL DEFAULT '0',
  `id_from_merchant` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ext_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_merchant` bigint NOT NULL DEFAULT '0',
  `is_checkout_url` int NOT NULL DEFAULT '0',
  `account_validator_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_validator_passed` tinyint(1) DEFAULT NULL,
  `id_direction_exchange` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mtd_task` (`id_task`),
  KEY `idx_mtd_merchant_time` (`id_merchant`,`created_at`),
  KEY `idx_mtd_direction` (`id_direction_exchange`),
  KEY `idx_mtd_service` (`service_name`),
  CONSTRAINT `merchants_transaction_data_chk_1` CHECK (json_valid(`ext_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- payments.deleted_at (SoftDeletes; present in production)
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `payments` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payments'
    AND COLUMN_NAME = 'deleted_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- dynamic_config_settings (package DynamicConfig; production-owned)
CREATE TABLE IF NOT EXISTS `dynamic_config_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scope_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_id` bigint unsigned DEFAULT NULL,
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dyn_cfg_scope_key_unique` (`scope_type`,`scope_id`,`key`),
  KEY `dyn_cfg_scope_index` (`scope_type`,`scope_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
