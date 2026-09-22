-- Structure-only missing tables (no FKs, no rows)
CREATE TABLE IF NOT EXISTS `checkbox_agreement_direction_allowed` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `checkbox_agreement_id` bigint unsigned NOT NULL,
  `direction_exchange_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `checkbox_direction_allowed_unique` (`checkbox_agreement_id`,`direction_exchange_id`),
  KEY `dir_exchange_allowed_id_foreign` (`direction_exchange_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `checkbox_agreement_direction_exchange` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `checkbox_agreement_id` bigint unsigned NOT NULL,
  `direction_exchange_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `checkbox_direction_unique` (`checkbox_agreement_id`,`direction_exchange_id`),
  KEY `dir_exchange_id_foreign` (`direction_exchange_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `currency_bin_bank_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `currency_id` int NOT NULL,
  `direction` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `currency_bin_bank_rules_unique` (`currency_id`,`direction`,`mode`,`bank_name`),
  KEY `currency_bin_bank_rules_idx` (`currency_id`,`direction`,`mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `currency_category_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `currency_category_id` bigint unsigned NOT NULL,
  `currency_id` int NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_currency_category_currency` (`currency_category_id`,`currency_id`),
  UNIQUE KEY `uq_currency_category_items_currency_id` (`currency_id`),
  KEY `idx_currency_active` (`currency_id`,`is_active`),
  KEY `idx_category_position` (`currency_category_id`,`position`),
  KEY `currency_category_items_position_index` (`position`),
  KEY `currency_category_items_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `currency_filter` (
  `currency_id` int NOT NULL,
  `filter_currency_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `currency_filter_unique` (`currency_id`,`filter_currency_id`),
  KEY `currency_filter_filter_currency_id_fk` (`filter_currency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `currency_label_currency` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `currency_id` int NOT NULL,
  `label_id` bigint unsigned NOT NULL,
  `side` enum('give','receive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'give',
  `priority` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_currency_label_side` (`currency_id`,`label_id`,`side`),
  KEY `currency_label_currency_label_id_index` (`label_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `currency_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_payment_id` bigint unsigned NOT NULL,
  `network_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `currency_payments_model_id_foreign` (`model_id`),
  KEY `currency_payments_gateway_payment_id_foreign` (`gateway_payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `direction_exchange_group_pivot` (
  `group_id` bigint unsigned NOT NULL,
  `direction_exchange_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`group_id`,`direction_exchange_id`),
  KEY `direction_exchange_group_pivot_direction_exchange_id_foreign` (`direction_exchange_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `direction_exchange_pay` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_pay_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `direction_exchange_pay_model_id_foreign` (`model_id`),
  KEY `direction_exchange_pay_gateway_pay_id_foreign` (`gateway_pay_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `directions_city_profile_pivot` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_direction_exchange_city` bigint unsigned NOT NULL,
  `direction_city_profile_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `directions_city_profile_pivot_id_direction_exchange_city_unique` (`id_direction_exchange_city`),
  KEY `directions_city_profile_pivot_direction_city_profile_id_index` (`direction_city_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `extra_field_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `field_id` bigint unsigned NOT NULL,
  `owner_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` bigint unsigned NOT NULL,
  `field_value` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_field_owner` (`field_id`,`owner_type`,`owner_id`),
  KEY `extra_field_values_owner_type_owner_id_index` (`owner_type`,`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `extra_out_profile_currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint unsigned NOT NULL,
  `currency_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extra_out_profile_currencies_profile_id_currency_id_unique` (`profile_id`,`currency_id`),
  KEY `extra_out_profile_currencies_currency_id_foreign` (`currency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `group_commission_direction_exchange` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_commission_id` bigint unsigned NOT NULL,
  `direction_exchange_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_direction_unique` (`group_commission_id`,`direction_exchange_id`),
  KEY `group_commission_directions_id_foreign` (`direction_exchange_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `kyc_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `subject_external_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stage` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome_reason` text COLLATE utf8mb4_unicode_ci,
  `response_data` json DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `occurred_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kyc_logs_provider_index` (`provider`),
  KEY `kyc_logs_event_index` (`event`),
  KEY `kyc_logs_status_index` (`status`),
  KEY `kyc_logs_user_id_index` (`user_id`),
  KEY `kyc_logs_subject_external_id_index` (`subject_external_id`),
  KEY `kyc_logs_stage_index` (`stage`),
  KEY `kyc_logs_outcome_index` (`outcome`),
  KEY `kyc_logs_occurred_at_index` (`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `order_exchange_totals` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_task` bigint unsigned NOT NULL,
  `exchange_usd` decimal(24,8) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_exchange_totals_created_at_index` (`created_at`),
  KEY `order_exchange_totals_id_task_foreign` (`id_task`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `order_exports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `format` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'xlsx',
  `file_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rows_count` int unsigned DEFAULT NULL,
  `filters` json DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_exports_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `promo_code_directions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `promo_code_id` bigint unsigned NOT NULL,
  `direction_exchange_id` int NOT NULL,
  `mode` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promo_code_dir_mode_unique` (`promo_code_id`,`direction_exchange_id`,`mode`),
  KEY `promo_code_directions_promo_code_id_mode_index` (`promo_code_id`,`mode`),
  KEY `promo_code_directions_direction_exchange_id_mode_index` (`direction_exchange_id`,`mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `proxy_health_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proxy_id` bigint unsigned NOT NULL,
  `context` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `http_status` smallint unsigned DEFAULT NULL,
  `latency_ms` int unsigned DEFAULT NULL,
  `error` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `proxy_health_logs_proxy_time_idx` (`proxy_id`,`created_at`),
  KEY `proxy_health_logs_context_time_idx` (`context`,`created_at`),
  KEY `proxy_health_logs_success_time_idx` (`success`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `referral_settings_code_audits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` int unsigned DEFAULT NULL,
  `actor_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `before_code_currency_id` int DEFAULT NULL,
  `after_code_currency_id` int DEFAULT NULL,
  `before_fallback_codes` json DEFAULT NULL,
  `after_fallback_codes` json DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referral_settings_code_audits_actor_id_index` (`actor_id`),
  KEY `referral_settings_code_audits_before_code_currency_id_index` (`before_code_currency_id`),
  KEY `referral_settings_code_audits_after_code_currency_id_index` (`after_code_currency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `reserve_closures` (
  `ancestor_id` int NOT NULL,
  `descendant_id` int NOT NULL,
  `depth` smallint unsigned NOT NULL,
  PRIMARY KEY (`ancestor_id`,`descendant_id`),
  KEY `idx_rc_desc_depth` (`descendant_id`,`depth`),
  KEY `idx_rc_anc_depth` (`ancestor_id`,`depth`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `reserve_links` (
  `reserve_id` int NOT NULL,
  `parent_reserve_id` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `note` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`reserve_id`),
  KEY `reserve_links_parent_reserve_id_index` (`parent_reserve_id`),
  KEY `reserve_links_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `selector_fee_direction_exchange` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `selector_fee_id` bigint unsigned NOT NULL,
  `direction_exchange_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector_fee_direction_unique` (`selector_fee_id`,`direction_exchange_id`),
  KEY `selector_fee_directions_id_foreign` (`direction_exchange_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `sumsub_ids` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `applicant_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT '0',
  `expire_at` timestamp NULL DEFAULT NULL,
  `sumsub_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `provider` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sumsub_ids_applicant_id_unique` (`applicant_id`),
  KEY `sumsub_ids_user_id_index` (`user_id`),
  KEY `sumsub_ids_is_completed_index` (`is_completed`),
  KEY `sumsub_ids_provider_index` (`provider`),
  CONSTRAINT `sumsub_ids_chk_1` CHECK (json_valid(`sumsub_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `support_ai_conversation_outcomes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `outcome` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `last_operator_message_id` bigint unsigned DEFAULT NULL,
  `last_visitor_message_id` bigint unsigned DEFAULT NULL,
  `time_to_resolution_seconds` int unsigned DEFAULT NULL,
  `source` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_ai_conversation_outcomes_conversation_id_unique` (`conversation_id`),
  KEY `support_ai_conversation_outcomes_outcome_updated_at_index` (`outcome`,`updated_at`),
  KEY `support_ai_conversation_outcomes_outcome_index` (`outcome`),
  KEY `support_ai_conversation_outcomes_last_operator_message_id_index` (`last_operator_message_id`),
  KEY `support_ai_conversation_outcomes_last_visitor_message_id_index` (`last_visitor_message_id`),
  KEY `support_ai_conversation_outcomes_source_index` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `support_ai_learning_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `message_id` bigint unsigned DEFAULT NULL,
  `ai_request_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intent` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversation_stage` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suggestions` json DEFAULT NULL,
  `suggestion_hashes` json DEFAULT NULL,
  `selected_suggestion_index` tinyint unsigned DEFAULT NULL,
  `operator_reply` text COLLATE utf8mb4_unicode_ci,
  `operator_reply_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_edited` tinyint(1) NOT NULL DEFAULT '0',
  `edit_distance_ratio` decimal(5,4) DEFAULT NULL,
  `outcome` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quality_score` decimal(5,2) DEFAULT NULL,
  `safety_flags` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ai_learning_events_conversation_id_created_at_index` (`conversation_id`,`created_at`),
  KEY `support_ai_learning_events_conversation_id_message_id_index` (`conversation_id`,`message_id`),
  KEY `support_ai_learning_events_message_id_index` (`message_id`),
  KEY `support_ai_learning_events_ai_request_id_index` (`ai_request_id`),
  KEY `support_ai_learning_events_intent_index` (`intent`),
  KEY `support_ai_learning_events_conversation_stage_index` (`conversation_stage`),
  KEY `support_ai_learning_events_language_index` (`language`),
  KEY `support_ai_learning_events_operator_reply_hash_index` (`operator_reply_hash`),
  KEY `support_ai_learning_events_outcome_index` (`outcome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `support_ai_operator_usage_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `visitor_message_id` bigint unsigned DEFAULT NULL,
  `operator_message_id` bigint unsigned DEFAULT NULL,
  `suggestion_usage_id` bigint unsigned DEFAULT NULL,
  `intent` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_lookup_used` tinyint(1) NOT NULL DEFAULT '0',
  `direction_lookup_used` tinyint(1) NOT NULL DEFAULT '0',
  `draft_generated_at` timestamp NULL DEFAULT NULL,
  `operator_replied_at` timestamp NULL DEFAULT NULL,
  `response_time_seconds` int unsigned DEFAULT NULL,
  `operator_decision` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suggestion_preview` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_reply_preview` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suggestion_text_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_text_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `similarity_score` decimal(5,4) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_ai_op_usage_conv_visitor_uq` (`conversation_id`,`visitor_message_id`),
  KEY `support_ai_op_usage_draft_decision_idx` (`draft_generated_at`,`operator_decision`),
  KEY `support_ai_operator_usage_metrics_visitor_message_id_index` (`visitor_message_id`),
  KEY `support_ai_operator_usage_metrics_operator_message_id_index` (`operator_message_id`),
  KEY `support_ai_operator_usage_metrics_suggestion_usage_id_index` (`suggestion_usage_id`),
  KEY `support_ai_operator_usage_metrics_intent_index` (`intent`),
  KEY `support_ai_operator_usage_metrics_draft_generated_at_index` (`draft_generated_at`),
  KEY `support_ai_operator_usage_metrics_operator_decision_index` (`operator_decision`),
  KEY `support_ai_operator_usage_metrics_suggestion_text_hash_index` (`suggestion_text_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `support_ai_suggestion_usages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `visitor_message_id` bigint unsigned DEFAULT NULL,
  `suggestion_id` tinyint unsigned DEFAULT NULL,
  `operator_message_id` bigint unsigned DEFAULT NULL,
  `learning_event_id` bigint unsigned DEFAULT NULL,
  `decision` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `edit_distance` int unsigned DEFAULT NULL,
  `similarity_score` decimal(5,4) DEFAULT NULL,
  `matched_by` varchar(48) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suggestion_text_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_text_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suggestion_preview` varchar(320) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_reply_preview` varchar(320) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_ai_suggestion_usages_operator_message_id_unique` (`operator_message_id`),
  KEY `support_ai_suggestion_usages_conversation_id_created_at_index` (`conversation_id`,`created_at`),
  KEY `support_ai_suggestion_usages_decision_created_at_index` (`decision`,`created_at`),
  KEY `support_ai_suggestion_usages_visitor_message_id_index` (`visitor_message_id`),
  KEY `support_ai_suggestion_usages_suggestion_id_index` (`suggestion_id`),
  KEY `support_ai_suggestion_usages_operator_message_id_index` (`operator_message_id`),
  KEY `support_ai_suggestion_usages_learning_event_id_index` (`learning_event_id`),
  KEY `support_ai_suggestion_usages_decision_index` (`decision`),
  KEY `support_ai_suggestion_usages_matched_by_index` (`matched_by`),
  KEY `support_ai_suggestion_usages_suggestion_text_hash_index` (`suggestion_text_hash`),
  KEY `support_ai_suggestion_usages_operator_text_hash_index` (`operator_text_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `support_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_conversation_id` bigint unsigned NOT NULL,
  `support_message_id` bigint unsigned DEFAULT NULL,
  `sender_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `disk` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint unsigned NOT NULL,
  `sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telegram_message_id` bigint unsigned DEFAULT NULL,
  `telegram_file_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telegram_delivery_failed_at` timestamp NULL DEFAULT NULL,
  `telegram_delivery_error` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_attachments_support_message_id_foreign` (`support_message_id`),
  KEY `support_attachments_support_conversation_id_id_index` (`support_conversation_id`,`id`),
  KEY `support_attachments_sender_type_index` (`sender_type`),
  KEY `support_attachments_sha256_index` (`sha256`),
  KEY `support_attachments_tg_delivery_failed_idx` (`telegram_delivery_failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_conversation_id` bigint unsigned NOT NULL,
  `sender_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `telegram_outbound_message_id` bigint unsigned DEFAULT NULL,
  `telegram_delivery_failed_at` timestamp NULL DEFAULT NULL,
  `telegram_delivery_error` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telegram_inbound_message_id` bigint unsigned DEFAULT NULL,
  `telegram_reply_to_message_id` bigint unsigned DEFAULT NULL,
  `seen_by_visitor_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_messages_telegram_inbound_uid` (`telegram_inbound_message_id`),
  KEY `support_messages_support_conversation_id_id_index` (`support_conversation_id`,`id`),
  KEY `support_messages_sender_type_index` (`sender_type`),
  KEY `support_messages_conv_seen_visitor_idx` (`support_conversation_id`,`seen_by_visitor_at`),
  KEY `support_messages_tg_delivery_failed_idx` (`telegram_delivery_failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `task_check_payment_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `old_status` tinyint unsigned NOT NULL,
  `new_status` tinyint unsigned NOT NULL,
  `description` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_check_payment_status_logs_task_id_foreign` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `task_extra_outs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` bigint unsigned NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `position` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_extra_outs_id_task_index` (`id_task`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `tasks_meta` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `payment_status` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `geo_data` json DEFAULT NULL COMMENT 'Гео-данные клиента (страна, регион, город и т.д.)',
  `telegram_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telegram_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `selected_fee_id` bigint unsigned DEFAULT NULL,
  `direction_selected_fee_id` bigint unsigned DEFAULT NULL,
  `checkbox_agreements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `card_verification_required` tinyint(1) NOT NULL DEFAULT '0',
  `card_verification_type` tinyint NOT NULL DEFAULT '0',
  `identity_verification_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Флаг: требуется ли верификация личности в момент создания заявки',
  `identity_verification_type` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Тип верификации личности: 0=валюта, 1=направление, 2=индивидуальные правила',
  `selected_fee_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `selected_fees` json DEFAULT NULL,
  `merchant_network_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Тип устройства пользователя: mobile / desktop / tablet (снимок на момент создания заявки)',
  `user_flag` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `freeze_scam` json DEFAULT NULL COMMENT 'Данные блокировки заявки (freeze_scam): тип, причины, дата',
  `chat_handoff_to_human` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'true — чат передан оператору, AI не отвечает',
  PRIMARY KEY (`id`),
  KEY `tasks_meta_task_id_foreign` (`task_id`),
  KEY `tasks_meta_payment_status_index` (`payment_status`),
  KEY `tasks_meta_selected_fee_id_foreign` (`selected_fee_id`),
  KEY `tasks_meta_direction_selected_fee_id_foreign` (`direction_selected_fee_id`),
  CONSTRAINT `tasks_meta_chk_1` CHECK (json_valid(`telegram_data`)),
  CONSTRAINT `tasks_meta_chk_2` CHECK (json_valid(`checkbox_agreements`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `tasks_operators` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `id_task` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_operators_id_task_foreign` (`id_task`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `tasks_operators_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_old_operator` int NOT NULL DEFAULT '0',
  `id_operator` int NOT NULL DEFAULT '0',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_task` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_operators_logs_id_task_foreign` (`id_task`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;