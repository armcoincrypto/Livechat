/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `admin_auth_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_auth_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `ip_address` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_ip_address` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_successful` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_desktop_gadgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_desktop_gadgets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_desktop` int NOT NULL DEFAULT '0',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alias` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `column_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_user` int NOT NULL DEFAULT '0',
  `hash_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_widget` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_desktops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_desktops` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `columns` int NOT NULL DEFAULT '2',
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `flex_num1` int NOT NULL DEFAULT '0',
  `flex_num2` int NOT NULL DEFAULT '0',
  `flex_num3` int NOT NULL DEFAULT '0',
  `flex_nums` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_filter_header`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_filter_header` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_common` int NOT NULL DEFAULT '0',
  `type_filter` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_filters_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_filters_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_filter_header` int NOT NULL DEFAULT '0',
  `type_filter` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_user` int NOT NULL DEFAULT '0',
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `section` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `advantage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `advantage` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `icon` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_target` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `affiliate_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `affiliate_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `aml_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aml_services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `id_manager` int NOT NULL DEFAULT '0',
  `aml_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aml_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `aml_services_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aml_services_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `aml_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_aml_service` int NOT NULL DEFAULT '0',
  `aml_service_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_task` int NOT NULL DEFAULT '0',
  `tx_hash` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iso_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `riskscore` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `json_result` json DEFAULT NULL,
  `id_currency` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `applications_steps_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applications_steps_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_step` int NOT NULL DEFAULT '0',
  `id_manager` int NOT NULL DEFAULT '0',
  `id_task` int NOT NULL DEFAULT '0',
  `id_order_status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `archive_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `archive_reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `hash_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `event` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_id` bigint unsigned NOT NULL,
  `old_values` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_values` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tags` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audits_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audits_user_id_user_type_index` (`user_id`,`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `autosender_payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `autosender_payment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_order` int NOT NULL DEFAULT '0',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `backgrounds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `backgrounds` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `background` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banned`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banned` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `expired_at` datetime DEFAULT NULL,
  `filter_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banned_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banned_user` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `id_task` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` text COLLATE utf8mb4_unicode_ci,
  `text` longtext COLLATE utf8mb4_unicode_ci,
  `images` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `color_title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_text` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `images_banner` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banners_buttons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banners_buttons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8mb4_unicode_ci,
  `link` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `color_text_button` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_bg_button` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banners_has_banners_buttons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banners_has_banners_buttons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` bigint unsigned NOT NULL,
  `model_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `banners_button_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `banners_has_banners_buttons_banners_button_id_foreign` (`banners_button_id`),
  KEY `banners_has_banners_buttons_model_id_foreign` (`model_id`),
  CONSTRAINT `banners_has_banners_buttons_banners_button_id_foreign` FOREIGN KEY (`banners_button_id`) REFERENCES `banners_buttons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `banners_has_banners_buttons_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `banners` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `bannable_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bannable_id` bigint unsigned NOT NULL,
  `created_by_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` bigint unsigned DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `expired_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bans_bannable_type_bannable_id_index` (`bannable_type`,`bannable_id`),
  KEY `bans_created_by_type_created_by_id_index` (`created_by_type`,`created_by_id`),
  KEY `bans_expired_at_index` (`expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bestchange_bl_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bestchange_bl_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bs_id` bigint NOT NULL DEFAULT '0',
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacts` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `desc` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bestchange_currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bestchange_currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bestchange_id` int NOT NULL DEFAULT '0',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bestchange_data_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bestchange_data_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type_log` int NOT NULL DEFAULT '0',
  `where_from` int NOT NULL DEFAULT '0',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bestchange_parser_error`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bestchange_parser_error` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_bestchange` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_direction_exchange` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bestchange_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bestchange_rates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchanger_id1` int NOT NULL,
  `exchanger_id2` int NOT NULL,
  `value` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summa` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL,
  `number_format` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_not_pair` int NOT NULL DEFAULT '0',
  `source_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bestchange_rates_name_index` (`name`),
  KEY `bestchange_rates_summa_index` (`summa`),
  KEY `bestchange_rates_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bestchange_rates_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bestchange_rates_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_bestchange_rates` int NOT NULL DEFAULT '0',
  `in_price` double NOT NULL DEFAULT '0',
  `out_price` double NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blacklist_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blacklist_order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` int NOT NULL DEFAULT '0',
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_bestchange` int NOT NULL DEFAULT '0',
  `is_iex` int NOT NULL DEFAULT '0',
  `hash_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  UNIQUE KEY `cache_key_unique` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cashback_error_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cashback_error_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `id_user` int NOT NULL DEFAULT '0',
  `in_code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `out_code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `host_id` int unsigned NOT NULL,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `last_run_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_run_output` json DEFAULT NULL,
  `last_ran_at` timestamp NULL DEFAULT NULL,
  `next_run_in_minutes` int DEFAULT NULL,
  `started_throttling_failing_notifications_at` timestamp NULL DEFAULT NULL,
  `custom_properties` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `checks_host_id_foreign` (`host_id`),
  CONSTRAINT `checks_host_id_foreign` FOREIGN KEY (`host_id`) REFERENCES `hosts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `designation_xml` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `created_user_id` int NOT NULL DEFAULT '0',
  `updated_user_id` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `code_currency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `code_currency` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `balance` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `sign` varchar(100) NOT NULL,
  `id_parser_exchange` int NOT NULL DEFAULT '0',
  `commission` double NOT NULL DEFAULT '0',
  `is_trashed` int NOT NULL DEFAULT '0',
  `internal_rate` varchar(191) NOT NULL DEFAULT '0',
  `add_to_course` varchar(191) NOT NULL DEFAULT '0',
  `id_parser_formula` int NOT NULL DEFAULT '0',
  `add_to_course_formula` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `collaboration_pr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `collaboration_pr` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int DEFAULT NULL,
  `status` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_button` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `competitor_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `competitor_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `competitor_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `competitor_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_competitor` int NOT NULL DEFAULT '0',
  `value` int NOT NULL DEFAULT '0',
  `summa` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `type` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `number_format` int NOT NULL DEFAULT '0',
  `exchange_in` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_out` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `competitor_rates_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `competitor_rates_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_competitors` int NOT NULL DEFAULT '0',
  `in_price` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `out_price` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `block_size` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `is_home` int NOT NULL DEFAULT '0',
  `is_button` int NOT NULL DEFAULT '0',
  `icon` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_color` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_manager` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_limit_user` int NOT NULL DEFAULT '0',
  `is_manual_bank` int NOT NULL DEFAULT '0',
  `bank_base` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `bank` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `id_code_currency` int NOT NULL DEFAULT '0',
  `code_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_sign` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_position` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `percent` double(8,2) NOT NULL DEFAULT '0.00',
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `subtitle` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `button_name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `subtitle_color` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_color` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon_url_home` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon_url_account` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `info_title` text COLLATE utf8mb4_unicode_ci,
  `info_text` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contests_conditions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contests_conditions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_manager` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contests_faq`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contests_faq` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_id` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contests_has_contests_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contests_has_contests_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` bigint unsigned NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contests_user_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `contests_has_contests_users_contests_user_id_foreign` (`contests_user_id`),
  KEY `contests_has_contests_users_model_id_foreign` (`model_id`),
  CONSTRAINT `contests_has_contests_users_contests_user_id_foreign` FOREIGN KEY (`contests_user_id`) REFERENCES `contests_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contests_has_contests_users_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `contests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contests_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contests_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `id_monitoring` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bonus` double(8,2) NOT NULL DEFAULT '0.00',
  `id_contest` int NOT NULL DEFAULT '0',
  `code_sign_bonus` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_name_bonus` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `course_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_direction_exchange` int NOT NULL DEFAULT '0',
  `id_user` int NOT NULL DEFAULT '0',
  `old_course` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_course` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `who` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `course_update_time_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_update_time_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `time` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_rate` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cron`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cron` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_group` int NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `interval` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cron_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cron_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pos` int NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plus` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_payment` int NOT NULL DEFAULT '0' COMMENT 'ID Платежной системы',
  `id_code_currency` int NOT NULL DEFAULT '0' COMMENT 'ID Кода валют',
  `id_filter_currency` int NOT NULL DEFAULT '0',
  `designation_xml` varchar(191) DEFAULT NULL COMMENT 'Обозначение для XML',
  `convert_by` double NOT NULL DEFAULT '1' COMMENT 'Конвертировать по',
  `number_format` int NOT NULL DEFAULT '4' COMMENT 'Знаков, после запятой',
  `day_limit_give` double NOT NULL DEFAULT '0' COMMENT 'Дневной лимит для Отдаю',
  `day_limit_receive` double NOT NULL DEFAULT '0' COMMENT 'Дневной лимит для Получаю',
  `min_char` int NOT NULL DEFAULT '0' COMMENT 'Мин. кол-во символов',
  `max_char` int NOT NULL DEFAULT '0' COMMENT 'Мак. кол-во символов',
  `field_name_from` text COMMENT 'Первые символы',
  `placeholder` varchar(255) NOT NULL,
  `allowed_char` int NOT NULL DEFAULT '0' COMMENT 'Разрешенные символы',
  `status` int NOT NULL DEFAULT '0' COMMENT 'Статус',
  `visible_give` int NOT NULL DEFAULT '0',
  `visible_receiving` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `visible` int NOT NULL DEFAULT '0',
  `visible_code_currency` int NOT NULL DEFAULT '0',
  `text_color` varchar(191) DEFAULT NULL,
  `background_color` varchar(191) DEFAULT NULL,
  `char_default` varchar(191) DEFAULT NULL,
  `mask_input` varchar(191) DEFAULT NULL,
  `sorting_1` int NOT NULL DEFAULT '0',
  `id_pay` int NOT NULL DEFAULT '0' COMMENT 'Выплаты',
  `is_valid_account` varchar(100) DEFAULT NULL,
  `is_qrcode` int DEFAULT '0',
  `prefix_qrcode` varchar(191) DEFAULT NULL,
  `is_payment_default` int NOT NULL DEFAULT '0',
  `is_payment_unique` int NOT NULL DEFAULT '0',
  `month_limit_in` double(8,2) NOT NULL DEFAULT '0.00',
  `month_limit_out` double(8,2) NOT NULL DEFAULT '0.00',
  `account_number_field` text,
  `notice_in` text,
  `transfer_percent_reserve` double NOT NULL DEFAULT '0',
  `transfer_amount_reserve` double NOT NULL DEFAULT '0',
  `remove_spaces_requisite` tinyint(1) NOT NULL DEFAULT '0',
  `payout_commission` double NOT NULL DEFAULT '0',
  `is_archive` int NOT NULL DEFAULT '0',
  `id_group` int NOT NULL DEFAULT '0',
  `is_hold` int NOT NULL DEFAULT '0',
  `is_qrcode_amount` int NOT NULL DEFAULT '0',
  `is_enabled_verification` int NOT NULL DEFAULT '0',
  `min_amount_verification` float NOT NULL DEFAULT '0',
  `hold_in_hours` int NOT NULL DEFAULT '0',
  `is_user_verification` int NOT NULL DEFAULT '0',
  `commission_merchant_percent` double NOT NULL DEFAULT '0',
  `fee_no_verified_merchant` double NOT NULL DEFAULT '0',
  `hold_delay` int NOT NULL DEFAULT '0',
  `payout_commission_amount` double NOT NULL DEFAULT '0',
  `id_auto_reserve` int NOT NULL DEFAULT '0',
  `sorting_tariffs` int NOT NULL DEFAULT '0',
  `min_out_amount_verification` varchar(191) NOT NULL DEFAULT '0',
  `is_out_enabled_verification` int NOT NULL DEFAULT '0',
  `max_limit_in_reserve` varchar(191) DEFAULT NULL,
  `hour_limit_order_pending` int NOT NULL DEFAULT '0',
  `hour_limit_order_process` int NOT NULL DEFAULT '0',
  `profit_percent_reserve` double NOT NULL DEFAULT '0',
  `is_card_detail` int NOT NULL DEFAULT '0',
  `max_display_reserve` varchar(191) DEFAULT NULL,
  `is_email_verification_modal` int NOT NULL DEFAULT '0',
  `commission_merchant_currency` double NOT NULL DEFAULT '0',
  `is_income_banner` int NOT NULL DEFAULT '0',
  `is_auto_check_modal` int NOT NULL DEFAULT '0',
  `commission_payment_currency` double(8,2) NOT NULL DEFAULT '0.00',
  `recount_percent` int NOT NULL DEFAULT '0',
  `is_recount_default` int NOT NULL DEFAULT '0',
  `notice_out` longtext,
  `is_unique_recount_order` int NOT NULL DEFAULT '0',
  `is_enable_auto_recount_order` int NOT NULL DEFAULT '0',
  `unique_recount_percent` double(8,2) NOT NULL DEFAULT '0.00',
  `recount_time_minutes` int NOT NULL DEFAULT '0',
  `desc_exchange` text,
  `out_pay_min_amount` varchar(191) NOT NULL DEFAULT '0',
  `out_pay_max_amount` varchar(191) NOT NULL DEFAULT '0',
  `out_pay_day_limit` varchar(191) NOT NULL DEFAULT '0',
  `out_pay_month_limit` varchar(191) NOT NULL DEFAULT '0',
  `is_allow_amount_space` int NOT NULL DEFAULT '0',
  `created_user_id` int NOT NULL DEFAULT '0',
  `updated_user_id` int NOT NULL DEFAULT '0',
  `is_fire` int NOT NULL DEFAULT '0',
  `field_name_to` text,
  `field_comment_from` text,
  `field_comment_to` text,
  `tech_currency_name` text,
  `button_create_order` text,
  `button_create_order_text` longtext,
  `kyc_enabled` int NOT NULL DEFAULT '0',
  `formalization_text` longtext,
  `is_aml_check_cost_reserve` int NOT NULL DEFAULT '0',
  `aml_check_cost` double NOT NULL DEFAULT '0',
  `network_code` varchar(191) DEFAULT NULL,
  `aml_text_in` longtext,
  `aml_text_out` longtext,
  `aml_analyses_count` int NOT NULL DEFAULT '0',
  `aml_analyses_price` double(8,2) NOT NULL DEFAULT '0.00',
  `aml_day_limit_count` int NOT NULL DEFAULT '0',
  `instruction_exchange` longtext,
  `blockchain_network_congestion` int NOT NULL DEFAULT '0',
  `is_kyc_checkbox` int NOT NULL DEFAULT '0',
  `tech_name` varchar(191) DEFAULT NULL,
  `other_docs_in` longtext,
  `other_docs_out` longtext,
  `is_allow_order` int NOT NULL DEFAULT '0',
  `sorting_admin` int NOT NULL DEFAULT '0',
  `is_verified_cabinet` int NOT NULL DEFAULT '0',
  `first_value` varchar(191) DEFAULT NULL,
  `verification_info` longtext,
  `verification_text` text,
  `recount_course_text` text,
  `type_output_requisites` int NOT NULL DEFAULT '0',
  `auto_pay_order_pay` int NOT NULL DEFAULT '0',
  `is_allow_file` int NOT NULL DEFAULT '0',
  `is_enabled_step_order` int NOT NULL DEFAULT '0',
  `network_code_out` varchar(191) DEFAULT NULL,
  `valid_account_error` text,
  `min_max_error_message` text,
  `account_number_field_text` text,
  `number_format_xml` int NOT NULL DEFAULT '0',
  `aml_service` varchar(191) DEFAULT NULL,
  `is_aml_check_wallet` int NOT NULL DEFAULT '0',
  `is_aml_check_tx` int NOT NULL DEFAULT '0',
  `aml_tx_from_amount` varchar(191) DEFAULT NULL,
  `id_label` int NOT NULL DEFAULT '0',
  `method_request_payment` int NOT NULL DEFAULT '0',
  `aml_wallet_from_amount` varchar(191) NOT NULL DEFAULT '0',
  `recount_statusses` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `currencies_id_payment_index` (`id_payment`),
  KEY `currencies_id_code_currency_index` (`id_code_currency`),
  KEY `currencies_designation_xml_index` (`designation_xml`),
  KEY `currencies_status_index` (`status`),
  KEY `currencies_id_pay_index` (`id_pay`),
  KEY `currencies_id_group_index` (`id_group`),
  KEY `currencies_is_user_verification_index` (`is_user_verification`),
  KEY `currencies_network_code_index` (`network_code`),
  KEY `currencies_id_filter_currency_index` (`id_filter_currency`),
  KEY `currencies_is_in_aml_check_tx_index` (`is_aml_check_tx`),
  KEY `currencies_aml_service_index` (`aml_service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies_analytics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `in_amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `out_amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `in_count_exchange` int NOT NULL DEFAULT '0',
  `out_count_exchange` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_currency` int NOT NULL DEFAULT '0',
  `in_order_count` int NOT NULL DEFAULT '0',
  `out_order_count` int NOT NULL DEFAULT '0',
  `in_amount_usd` double(8,2) NOT NULL DEFAULT '0.00',
  `out_amount_usd` double(8,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies_commands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies_commands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` double NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_currency` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_user` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies_info` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_currency` int NOT NULL DEFAULT '0',
  `aml_analyses_type` int NOT NULL DEFAULT '0',
  `aml_analyses_count` int NOT NULL DEFAULT '0',
  `aml_analyses_price` double(8,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies_labels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies_labels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` text COLLATE utf8mb4_unicode_ci,
  `text_color` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bg_color` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status` int NOT NULL DEFAULT '0',
  `id_currency` int NOT NULL DEFAULT '0',
  `id_user` int NOT NULL DEFAULT '0',
  `from_where` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies_networks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies_networks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies_notification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies_notification` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_currency` int NOT NULL DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `css_class` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_type` int NOT NULL DEFAULT '0',
  `text` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type_view_info` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currency_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key_id` varchar(100) DEFAULT NULL,
  `name` text NOT NULL,
  `id_currency` int NOT NULL,
  `when_print` int NOT NULL,
  `min_char` int NOT NULL,
  `max_char` int NOT NULL,
  `first_char` text,
  `obligatory_field` int NOT NULL,
  `checkbox_title` varchar(200) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` int NOT NULL,
  `field_type` varchar(191) DEFAULT NULL,
  `language_field` int NOT NULL DEFAULT '0',
  `description` text,
  `remove_spaces` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `sorting_out` int NOT NULL DEFAULT '0',
  `type_field` int NOT NULL DEFAULT '0',
  `list_text` longtext,
  `start_with` varchar(191) DEFAULT NULL,
  `end_with` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currency_in_has_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency_in_has_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `currency_in_has_fields_field_id_foreign` (`field_id`),
  KEY `currency_in_has_fields_model_id_foreign` (`model_id`),
  CONSTRAINT `currency_in_has_fields_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `currency_fields` (`id`) ON DELETE CASCADE,
  CONSTRAINT `currency_in_has_fields_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currency_merchants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency_merchants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_merchant_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `currency_merchants_gateway_merchant_id_foreign` (`gateway_merchant_id`),
  KEY `currency_merchants_model_id_foreign` (`model_id`),
  CONSTRAINT `currency_merchants_gateway_merchant_id_foreign` FOREIGN KEY (`gateway_merchant_id`) REFERENCES `gateways_merchants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `currency_merchants_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currency_out_has_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency_out_has_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `currency_out_has_fields_field_id_foreign` (`field_id`),
  KEY `currency_out_has_fields_model_id_foreign` (`model_id`),
  CONSTRAINT `currency_out_has_fields_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `currency_fields` (`id`) ON DELETE CASCADE,
  CONSTRAINT `currency_out_has_fields_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currency_requisites_has_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency_requisites_has_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `currency_requisites_has_fields_field_id_foreign` (`field_id`),
  KEY `currency_requisites_has_fields_model_id_foreign` (`model_id`),
  CONSTRAINT `currency_requisites_has_fields_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `requisites_fields` (`id`) ON DELETE CASCADE,
  CONSTRAINT `currency_requisites_has_fields_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `debtors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `debtors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_bestchange` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direction_day`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direction_day` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_direction_exchange` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direction_exchange`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direction_exchange` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_currency1` int NOT NULL COMMENT 'Направление 1',
  `id_currency2` int NOT NULL COMMENT 'Направление 2',
  `exchange_rate1` varchar(250) NOT NULL DEFAULT '0' COMMENT 'Курс обмена 1',
  `exchange_rate2` varchar(250) NOT NULL DEFAULT '0' COMMENT 'Курс обмена 2',
  `id_crypto_parser` int NOT NULL DEFAULT '0' COMMENT 'Автокорректировка курса',
  `id_your_exchange` int NOT NULL DEFAULT '0',
  `your_add_course1` double NOT NULL DEFAULT '0',
  `your_add_course2` double NOT NULL DEFAULT '0',
  `id_merchant` int NOT NULL DEFAULT '0',
  `add_course1` double NOT NULL DEFAULT '0' COMMENT 'Прибавление к курсу 1',
  `add_course2` double NOT NULL DEFAULT '0' COMMENT 'Прибавление к курсу 2',
  `status` int NOT NULL DEFAULT '0' COMMENT 'Статус',
  `seo_title` varchar(255) DEFAULT NULL COMMENT 'SEO Заголовок',
  `seo_description` text COMMENT 'SEO Описание',
  `seo_keywords` text COMMENT 'SEO Ключевые слова',
  `deadline` text COMMENT 'Срок выполнения обмена',
  `instructions` longtext COMMENT 'Инструкция по оплате',
  `profit` double NOT NULL DEFAULT '0' COMMENT 'Прибыль',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `auto_conversion` int NOT NULL,
  `min_price1` double NOT NULL DEFAULT '0',
  `min_price2` double NOT NULL DEFAULT '0',
  `max_price1` double NOT NULL DEFAULT '0',
  `max_price2` double NOT NULL DEFAULT '0',
  `commission1` double NOT NULL DEFAULT '0',
  `commission2` double NOT NULL DEFAULT '0',
  `sign` int NOT NULL DEFAULT '0',
  `enable_bestchange` int NOT NULL DEFAULT '0' COMMENT 'Статус парсинга "Bestchange" 0 - Отключен 1 - Включена	',
  `id_bestchange_rates` int NOT NULL DEFAULT '0',
  `bestchange_position` int NOT NULL DEFAULT '0',
  `id_group_commission` int NOT NULL DEFAULT '0',
  `sorting_1` int DEFAULT '0',
  `sorting_2` int NOT NULL DEFAULT '0',
  `parent_url` varchar(191) DEFAULT NULL,
  `is_not_bonus` int NOT NULL DEFAULT '0',
  `is_not_partner` int NOT NULL DEFAULT '0',
  `individual_percentage` float NOT NULL DEFAULT '0',
  `fixed_payout` float NOT NULL DEFAULT '0',
  `export_label_param` varchar(191) DEFAULT NULL,
  `export_label_minamount` int NOT NULL DEFAULT '0',
  `export_label_maxamount` int NOT NULL DEFAULT '0',
  `minimum_payout` float NOT NULL DEFAULT '0',
  `maximum_payout` float NOT NULL DEFAULT '0',
  `allow_export` int NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `commission_s1` double(8,2) NOT NULL DEFAULT '0.00',
  `commission_s2` double(8,2) NOT NULL DEFAULT '0.00',
  `allow_export_from` varchar(191) DEFAULT NULL,
  `allow_export_to` varchar(191) DEFAULT NULL,
  `profit_s` double NOT NULL DEFAULT '0',
  `id_group_direction` int NOT NULL DEFAULT '0',
  `is_restrict_editing` int NOT NULL DEFAULT '0',
  `is_enabled_exchange` int NOT NULL DEFAULT '0',
  `from_on_time` varchar(191) DEFAULT NULL,
  `to_on_time` varchar(191) DEFAULT NULL,
  `hidden_export_label_param` int NOT NULL DEFAULT '0',
  `is_manual_min_price2` int NOT NULL DEFAULT '0',
  `is_manual_max_price2` int NOT NULL DEFAULT '0',
  `is_manual_min_price1` int NOT NULL DEFAULT '0',
  `is_manual_max_price1` int NOT NULL DEFAULT '0',
  `in_who_pay_commission` int NOT NULL DEFAULT '0',
  `out_who_pay_commission` int NOT NULL DEFAULT '0',
  `id_competitor` int NOT NULL DEFAULT '0',
  `course_in` varchar(191) DEFAULT NULL,
  `course_out` varchar(191) DEFAULT NULL,
  `bc_min_sum` varchar(191) DEFAULT NULL,
  `bc_max_sum` varchar(191) DEFAULT NULL,
  `bc_id_new_rate` int NOT NULL DEFAULT '0',
  `bc_add_course` int NOT NULL DEFAULT '0',
  `max_order_one_ip` int NOT NULL DEFAULT '0' COMMENT 'Макс. кол-во заявок на обмен с одного IP',
  `max_order_one_account1` int NOT NULL DEFAULT '0' COMMENT 'Макс. кол-во заявок на обмен с одного счета Отдаю',
  `max_order_one_account2` int NOT NULL DEFAULT '0' COMMENT 'Макс. кол-во заявок на обмен с одного счета Получаю',
  `max_order_one_user` int NOT NULL DEFAULT '0' COMMENT 'Макс. кол-во заявок на обмен от одного пользователя',
  `max_order_one_email` int NOT NULL DEFAULT '0' COMMENT 'Макс. кол-во заявок на обмен с одного e-mail',
  `not_ip` text,
  `cr_min_sum` varchar(191) DEFAULT NULL,
  `cr_max_sum` varchar(191) DEFAULT NULL,
  `cr_id_new_rate` int NOT NULL DEFAULT '0',
  `cr_add_course` int NOT NULL DEFAULT '0',
  `max_percent_partner` double(8,2) NOT NULL DEFAULT '0.00',
  `xml_juridical` int NOT NULL DEFAULT '0',
  `languages` varchar(191) DEFAULT NULL,
  `is_hidden_not_locale` int NOT NULL DEFAULT '0',
  `sorting_tariffs` int NOT NULL DEFAULT '0',
  `device` varchar(191) DEFAULT NULL,
  `is_hidden_not_device` int NOT NULL DEFAULT '0',
  `bc_new_commission` int NOT NULL DEFAULT '0',
  `bestchange_bl` varchar(191) DEFAULT NULL,
  `bestchange_wl` varchar(191) DEFAULT NULL,
  `bestchange_step` varchar(191) NOT NULL DEFAULT '0',
  `bestchange_min_reserve` varchar(191) DEFAULT NULL,
  `bc_enable_your_course` int NOT NULL DEFAULT '0',
  `bc_id_your_exchange` int NOT NULL DEFAULT '0',
  `bc_your_add_course` double(8,2) NOT NULL DEFAULT '0.00',
  `rl_min2_course` varchar(191) DEFAULT NULL,
  `rl_max2_course` varchar(191) DEFAULT NULL,
  `rl_id_parser_exchange` int NOT NULL DEFAULT '0',
  `rl_add_course` varchar(191) NOT NULL DEFAULT '0',
  `is_change_bestchange_range` int NOT NULL DEFAULT '0',
  `bestchange_range` varchar(191) DEFAULT NULL,
  `oth_comm_percent` double DEFAULT '0',
  `oth_comm_currency` double DEFAULT '0',
  `oth_min_comm` varchar(191) DEFAULT NULL,
  `auto_del_order_status` varchar(191) DEFAULT NULL,
  `bestchange_max_reserve` varchar(191) NOT NULL DEFAULT '0',
  `is_hidden_tariffs` int NOT NULL DEFAULT '0',
  `is_holding_direction` int NOT NULL DEFAULT '0',
  `bestchange_range_from` varchar(191) DEFAULT NULL,
  `bestchange_range_to` varchar(191) DEFAULT NULL,
  `rl_id_your_exchange` int NOT NULL DEFAULT '0',
  `rl_your_add_course` varchar(191) DEFAULT NULL,
  `reserve_max_limit` varchar(191) DEFAULT NULL,
  `reserve_limit_day` varchar(191) DEFAULT NULL,
  `reserve_limit_month` varchar(191) DEFAULT NULL,
  `is_num_transaction` varchar(191) DEFAULT NULL,
  `num_transaction_label` varchar(191) DEFAULT NULL,
  `max_order_one_ip_day` int NOT NULL DEFAULT '0',
  `max_order_one_user_day` int NOT NULL DEFAULT '0',
  `max_order_one_email_day` int NOT NULL DEFAULT '0',
  `max_order_one_account1_day` int NOT NULL DEFAULT '0',
  `max_order_one_account2_day` int NOT NULL DEFAULT '0',
  `enable_file_parser_rate` int NOT NULL DEFAULT '0',
  `id_file_parser_rate` int NOT NULL DEFAULT '0',
  `id_bs_alt_parser` int NOT NULL DEFAULT '0',
  `bs_alt_parser_course` varchar(191) NOT NULL DEFAULT '0',
  `is_enable_alt_bs_parser` int NOT NULL DEFAULT '0',
  `is_disable_bs_error` int NOT NULL DEFAULT '0',
  `profit_partner` double(8,2) NOT NULL DEFAULT '0.00',
  `is_note_tx` int NOT NULL DEFAULT '0',
  `note_tx_label` varchar(191) DEFAULT NULL,
  `x19_mode` int NOT NULL DEFAULT '0',
  `is_email_verification_modal` int NOT NULL DEFAULT '0',
  `max_amount_newbie` double NOT NULL DEFAULT '0',
  `course_value` varchar(191) NOT NULL DEFAULT '0',
  `exchange_rate` varchar(191) DEFAULT NULL COMMENT 'Курс обмена',
  `is_error_rate` int NOT NULL DEFAULT '0',
  `exchange_rate_str` varchar(191) DEFAULT NULL,
  `error_rate_text` varchar(191) DEFAULT NULL,
  `tech_name` varchar(191) DEFAULT NULL,
  `last_order_at` datetime DEFAULT NULL,
  `last_order_id` bigint NOT NULL DEFAULT '0',
  `first_order_id` bigint NOT NULL DEFAULT '0',
  `desc_exchange` text,
  `id_partner_parser_rate` int NOT NULL DEFAULT '0',
  `id_parser_formula_rate` int NOT NULL DEFAULT '0',
  `type_output_requisites` int NOT NULL DEFAULT '0',
  `formalization_text` longtext,
  `min_count_exchanges_client` int NOT NULL DEFAULT '0',
  `order_button_i_pay` text,
  `order_button_i_pay_text` text,
  `is_allow_telegram_bot` int NOT NULL DEFAULT '0',
  `manual_rate_value` double NOT NULL DEFAULT '0',
  `parser_source_name` varchar(191) DEFAULT NULL,
  `other_docs` longtext,
  `is_notify_exchange_amount` int NOT NULL DEFAULT '0',
  `add_course1_s` varchar(191) NOT NULL DEFAULT '0',
  `your_add_course1_s` varchar(191) NOT NULL DEFAULT '0',
  `is_disable_auto_reg` int NOT NULL DEFAULT '0',
  `label_floating` varchar(191) DEFAULT NULL,
  `label_delay` varchar(191) DEFAULT NULL,
  `oth_comm2_percent` double NOT NULL DEFAULT '0',
  `oth_comm2_currency` double NOT NULL DEFAULT '0',
  `oth_min2_comm` double NOT NULL DEFAULT '0',
  `pay_comm_percent` varchar(191) NOT NULL DEFAULT '0',
  `pay_comm_currency` varchar(191) NOT NULL DEFAULT '0',
  `pay_comm2_percent` varchar(191) NOT NULL DEFAULT '0',
  `pay_comm2_currency` varchar(191) NOT NULL DEFAULT '0',
  `pay_min_comm` varchar(191) NOT NULL DEFAULT '0',
  `pay_min2_comm` varchar(191) NOT NULL DEFAULT '0',
  `auto_del_order_day` int NOT NULL DEFAULT '0',
  `auto_del_order_hour` int NOT NULL DEFAULT '0',
  `auto_del_order_minute` int NOT NULL DEFAULT '0',
  `is_enable_user_discount` int NOT NULL DEFAULT '0',
  `desc_exchange_dop` longtext,
  `type_profit_field` int NOT NULL DEFAULT '0',
  `text_order_success` longtext,
  `text_order_failed` longtext,
  `text_order_handler` longtext,
  `is_email_instructions` int NOT NULL DEFAULT '0',
  `is_email_desc_exchange_dop` int NOT NULL DEFAULT '0',
  `is_email_other_docs` int NOT NULL DEFAULT '0',
  `is_verified_account` int NOT NULL DEFAULT '0',
  `text_order_confirm` longtext,
  `order_button_i_confirm` text,
  `notice_process_desc` longtext,
  `bestchange_city` int NOT NULL DEFAULT '0',
  `multiplicity_type` int NOT NULL DEFAULT '0',
  `multiplicity_amount` int NOT NULL DEFAULT '0',
  `multiplicity_comment` longtext,
  PRIMARY KEY (`id`),
  KEY `direction_exchange_id_currency1_index` (`id_currency1`),
  KEY `direction_exchange_id_currency2_index` (`id_currency2`),
  KEY `direction_exchange_id_group_direction_index` (`id_group_direction`),
  KEY `direction_exchange_status_index` (`status`),
  KEY `direction_exchange_enable_bestchange_index` (`enable_bestchange`),
  KEY `direction_exchange_is_holding_direction_index` (`is_holding_direction`),
  KEY `direction_exchange_is_not_bonus_index` (`is_not_bonus`),
  KEY `direction_exchange_is_not_partner_index` (`is_not_partner`),
  KEY `direction_exchange_allow_export_index` (`allow_export`),
  KEY `direction_exchange_profit_index` (`profit`),
  KEY `direction_exchange_profit_s_index` (`profit_s`),
  KEY `direction_exchange_is_main_index` (`is_main`),
  KEY `direction_exchange_bestchange_position_index` (`bestchange_position`),
  KEY `direction_exchange_id_bestchange_rates_index` (`id_bestchange_rates`),
  KEY `direction_exchange_id_crypto_parser_index` (`id_crypto_parser`),
  KEY `direction_exchange_id_group_commission_index` (`id_group_commission`),
  KEY `direction_exchange_is_error_rate_index` (`is_error_rate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direction_exchange_cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direction_exchange_cities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_direction_exchange` int NOT NULL DEFAULT '0',
  `city_id` int NOT NULL DEFAULT '0',
  `add_comm` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `param` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `min_price` double NOT NULL DEFAULT '0',
  `max_price` double NOT NULL DEFAULT '0',
  `id_country` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direction_exchange_error_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direction_exchange_error_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_direction_exchange` int NOT NULL DEFAULT '0',
  `direction_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `where_error` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `level_risk` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direction_exchange_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direction_exchange_group` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_user` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direction_exchange_merchants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direction_exchange_merchants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_merchant_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `direction_exchange_merchants_model_id_foreign` (`model_id`),
  KEY `direction_exchange_merchants_gateway_merchant_id_foreign` (`gateway_merchant_id`),
  CONSTRAINT `direction_exchange_merchants_gateway_merchant_id_foreign` FOREIGN KEY (`gateway_merchant_id`) REFERENCES `gateways_merchants` (`id`),
  CONSTRAINT `direction_exchange_merchants_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direction_exchange_min_price_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direction_exchange_min_price_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_direction_exchange` int NOT NULL DEFAULT '0',
  `direction_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `exchange_rate` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direction_exchange_modes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direction_exchange_modes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direction_exchange_percent_amount`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direction_exchange_percent_amount` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_direction_exchange` int NOT NULL DEFAULT '0',
  `percentage` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `amount` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direction_notification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direction_notification` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_direction_exchange` int NOT NULL DEFAULT '0',
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sorting` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_enabled_schedule` int NOT NULL DEFAULT '0',
  `from_time` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_time` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_order_detail` int NOT NULL DEFAULT '0',
  `text_color` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bg_color` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direction_requisites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direction_requisites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `view` int NOT NULL DEFAULT '0',
  `limit_day` double(8,2) NOT NULL DEFAULT '0.00',
  `limit_month` double(8,2) NOT NULL DEFAULT '0.00',
  `limit_views` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direction_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direction_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_type` int NOT NULL DEFAULT '0',
  `text` longtext COLLATE utf8mb4_unicode_ci,
  `type_view_info` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `directions_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `directions_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `min_char` int NOT NULL DEFAULT '0',
  `max_char` int NOT NULL DEFAULT '0',
  `obligatory_field` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `key_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `remove_spaces` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `language_field` int NOT NULL DEFAULT '0',
  `start_with` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_with` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `directions_has_allowed_countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `directions_has_allowed_countries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `geo_country_list_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `directions_has_allowed_countries_geo_country_list_id_foreign` (`geo_country_list_id`),
  KEY `directions_has_allowed_countries_model_id_foreign` (`model_id`),
  CONSTRAINT `directions_has_allowed_countries_geo_country_list_id_foreign` FOREIGN KEY (`geo_country_list_id`) REFERENCES `geo_country_list` (`id`) ON DELETE CASCADE,
  CONSTRAINT `directions_has_allowed_countries_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `directions_has_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `directions_has_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction_field_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `directions_has_fields_direction_field_id_foreign` (`direction_field_id`),
  KEY `directions_has_fields_model_id_foreign` (`model_id`),
  CONSTRAINT `directions_has_fields_direction_field_id_foreign` FOREIGN KEY (`direction_field_id`) REFERENCES `directions_fields` (`id`) ON DELETE CASCADE,
  CONSTRAINT `directions_has_fields_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `directions_has_forbidden_countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `directions_has_forbidden_countries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `geo_country_list_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `directions_has_forbidden_countries_geo_country_list_id_foreign` (`geo_country_list_id`),
  KEY `directions_has_forbidden_countries_model_id_foreign` (`model_id`),
  CONSTRAINT `directions_has_forbidden_countries_geo_country_list_id_foreign` FOREIGN KEY (`geo_country_list_id`) REFERENCES `geo_country_list` (`id`) ON DELETE CASCADE,
  CONSTRAINT `directions_has_forbidden_countries_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `directions_has_modes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `directions_has_modes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction_exchange_mode_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `directions_has_modes_direction_exchange_mode_id_foreign` (`direction_exchange_mode_id`),
  KEY `directions_has_modes_model_id_foreign` (`model_id`),
  CONSTRAINT `directions_has_modes_direction_exchange_mode_id_foreign` FOREIGN KEY (`direction_exchange_mode_id`) REFERENCES `direction_exchange_modes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `directions_has_modes_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `directions_has_networks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `directions_has_networks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `network_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `directions_has_networks_network_id_foreign` (`network_id`),
  KEY `directions_has_networks_model_id_foreign` (`model_id`),
  CONSTRAINT `directions_has_networks_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`),
  CONSTRAINT `directions_has_networks_network_id_foreign` FOREIGN KEY (`network_id`) REFERENCES `currencies_networks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `directions_has_requisites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `directions_has_requisites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction_requisite_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `directions_has_requisites_direction_requisite_id_foreign` (`direction_requisite_id`),
  KEY `directions_has_requisites_model_id_foreign` (`model_id`),
  CONSTRAINT `directions_has_requisites_direction_requisite_id_foreign` FOREIGN KEY (`direction_requisite_id`) REFERENCES `direction_requisites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `directions_has_requisites_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `discount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `docs_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `docs_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_type` int NOT NULL,
  `parent_id` int NOT NULL,
  `parent_url` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `docs_category_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `docs_category_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `docs_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `docs_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `type_id` int NOT NULL,
  `parent_url` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `e_voucher_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e_voucher_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `account` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_num` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voucher_num` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voucher_code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voucher_amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_templates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mailable` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `html_template` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `text_template` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_event_type` int NOT NULL DEFAULT '0',
  `email_to` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `template` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `started_work` timestamp NULL DEFAULT NULL,
  `job_from` int NOT NULL DEFAULT '0',
  `job_to` int NOT NULL DEFAULT '0',
  `salary` float NOT NULL DEFAULT '0',
  `fines` int NOT NULL DEFAULT '0',
  `total` float DEFAULT '0',
  `percent` int NOT NULL DEFAULT '0',
  `bonus_rub` float NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_employees` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_employees` int NOT NULL,
  `id_task` int NOT NULL,
  `type` int NOT NULL,
  `amount` double(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_reserve`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_reserve` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `id_currency` int NOT NULL DEFAULT '0',
  `id_reserve` int NOT NULL DEFAULT '0',
  `type` int NOT NULL DEFAULT '0',
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `value_after` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT 'Значение после',
  `value_before` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT 'Значение до',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `comment` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `export_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `export_data` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_cron` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `is_filter` int NOT NULL DEFAULT '0',
  `is_allow_filter` int NOT NULL DEFAULT '0',
  `format_export` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count` int NOT NULL DEFAULT '0',
  `export_value` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faq`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faq` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_group` int NOT NULL,
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faq_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faq_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_direction_exchange` int NOT NULL,
  `id_currency1` int NOT NULL DEFAULT '0',
  `id_currency2` int NOT NULL DEFAULT '0',
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `favorites_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorites_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_user` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `file_parser_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `file_parser_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `link` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `file_parser_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `file_parser_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_in` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_out` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_group` int NOT NULL DEFAULT '0',
  `value` int NOT NULL DEFAULT '0',
  `summa` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `number_format` int NOT NULL DEFAULT '0',
  `type` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `file_storages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `file_storages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `id_manager` int NOT NULL DEFAULT '0',
  `disk` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disk_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `filter_currency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `filter_currency` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8mb4_unicode_ci,
  `sorting` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fine_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fine_employees` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_employees` int NOT NULL DEFAULT '0',
  `amount` int NOT NULL DEFAULT '0',
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `number` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `firewall`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `firewall` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(39) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `whitelisted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `firewall_ip_address_unique` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fund`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fund` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gateways` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `class_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_merchant` int NOT NULL DEFAULT '0' COMMENT 'Доступен прием?',
  `is_pay` int NOT NULL DEFAULT '0' COMMENT 'Доступны выплаты?',
  `is_rpc` int NOT NULL DEFAULT '0' COMMENT 'Использует RPC соединение',
  `is_check_pay` int NOT NULL DEFAULT '0' COMMENT 'Проверка поступления средств',
  `version` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `security_options` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gateways_merchants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gateways_merchants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_gateways` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `instruction_payment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `allow_ip_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `security_hash` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_check_from_shot` int NOT NULL DEFAULT '0',
  `is_disable_code_currency` int NOT NULL DEFAULT '0',
  `is_merchant_log` int NOT NULL DEFAULT '0',
  `is_check_api` int NOT NULL DEFAULT '0',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_deny_ip_address` int NOT NULL DEFAULT '0',
  `is_pay_commission` int NOT NULL DEFAULT '0',
  `day_limit_merchant` int NOT NULL DEFAULT '0',
  `max_limit_amount_order` double NOT NULL DEFAULT '0',
  `amount_fault` double NOT NULL DEFAULT '0',
  `day_limit_amount_merchant` double NOT NULL DEFAULT '0',
  `is_enable_merchant_button` int NOT NULL DEFAULT '0',
  `method_pay` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `fixed_fee` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_confirm` int NOT NULL DEFAULT '0',
  `max_register_blockchain` int NOT NULL DEFAULT '0',
  `max_first_confirm_blockchain` int NOT NULL DEFAULT '0',
  `total_usd` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_order_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_config_done` int NOT NULL DEFAULT '0',
  `is_card_found` int NOT NULL DEFAULT '0',
  `exchange_fee` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_amount` int NOT NULL DEFAULT '0',
  `credit_amount` int NOT NULL DEFAULT '0',
  `order_num` int NOT NULL DEFAULT '0',
  `bank_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `type_pay` int NOT NULL DEFAULT '0',
  `site_account` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_currency` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `process_method` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gateways_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gateways_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_gateways` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `max_register_blockchain` int NOT NULL DEFAULT '0',
  `max_first_confirm_blockchain` int NOT NULL DEFAULT '0',
  `min_confirm` int NOT NULL DEFAULT '0',
  `id_proxy` int NOT NULL DEFAULT '0',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `manual_pay_order` int NOT NULL DEFAULT '0',
  `method_pay` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `country_code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_request` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority_fee` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_buy` int NOT NULL DEFAULT '0',
  `exchange_fee` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `exchange_buy_type` int NOT NULL DEFAULT '0',
  `time_in_force` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_buy_curr` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_auto_take_fee` int NOT NULL DEFAULT '0',
  `volume_to_usd` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_order_id` int NOT NULL DEFAULT '0',
  `order_count` int NOT NULL DEFAULT '0',
  `is_mass_payouts` int NOT NULL DEFAULT '0' COMMENT 'Статус массовых выплат',
  `mass_coins` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT 'Валюты, используемые для массовых выплат',
  `hide_check_balance` int NOT NULL DEFAULT '0',
  `is_subtract` int NOT NULL DEFAULT '0' COMMENT 'Кто платит комиссию?',
  `currency_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_amount_type` int NOT NULL DEFAULT '0',
  `direction` int NOT NULL DEFAULT '0',
  `site_account` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_currency` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `geo_country_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `geo_country_list` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `getblock_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `getblock_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_order` int NOT NULL DEFAULT '0',
  `url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `headers` text COLLATE utf8mb4_unicode_ci,
  `response` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `options` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_commission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_commission` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `give` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `receiving` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_parser_exchange`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_parser_exchange` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `type_amount` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `alias` varchar(191) DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `priority` int NOT NULL DEFAULT '0',
  `provider_url` varchar(191) DEFAULT NULL,
  `provider_id` varchar(191) DEFAULT NULL,
  `last_updated_at` timestamp NULL DEFAULT NULL,
  `proxy_id` int NOT NULL DEFAULT '0',
  `last_imported_at` timestamp NULL DEFAULT NULL,
  `is_import_rates` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `health_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `health_checks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `resource_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_slug` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_slug` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_display` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `healthy` tinyint(1) NOT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `runtime` double(8,2) NOT NULL,
  `value` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value_human` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `health_checks_resource_slug_index` (`resource_slug`),
  KEY `health_checks_target_slug_index` (`target_slug`),
  KEY `health_checks_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `histories_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `histories_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_pay` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_excode`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_excode` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL,
  `task_id` int NOT NULL,
  `amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Тип получения данных',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `provider_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_fields` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `id_currency1` int NOT NULL DEFAULT '0',
  `id_currency2` int NOT NULL DEFAULT '0',
  `filed_give` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filed_receiving` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_internal_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_internal_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_internal_account` int NOT NULL DEFAULT '0',
  `id_user` int NOT NULL DEFAULT '0',
  `id_task` int NOT NULL DEFAULT '0',
  `type_history` int NOT NULL DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_payment_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_payment_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int DEFAULT NULL,
  `payment` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transfer` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_recalculation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_recalculation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL,
  `amount` float NOT NULL,
  `old_amount` float NOT NULL,
  `type` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `course` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hosts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hosts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ssh_user` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `port` int DEFAULT NULL,
  `ip` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_properties` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `iex_script_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iex_script_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `iex_script_config_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `iex_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iex_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `iex_settings_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `info_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `info_statistics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_automatic` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `account_number` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `internal_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `internal_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_code_currency` int NOT NULL DEFAULT '0',
  `id_user` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `balance` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `from_time` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_time` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_days` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `language_contents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `language_contents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `description_contact` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `interface_regauth_text1` longtext COLLATE utf8mb4_unicode_ci,
  `interface_regauth_text2` longtext COLLATE utf8mb4_unicode_ci,
  `telegram_bot_name_button` longtext COLLATE utf8mb4_unicode_ci,
  `tech_breach_title` longtext COLLATE utf8mb4_unicode_ci,
  `tech_breach_text` longtext COLLATE utf8mb4_unicode_ci,
  `input_footer_title` longtext COLLATE utf8mb4_unicode_ci,
  `description_footer_text` longtext COLLATE utf8mb4_unicode_ci,
  `welcome_title` longtext COLLATE utf8mb4_unicode_ci,
  `welcome_description` longtext COLLATE utf8mb4_unicode_ci,
  `telegram_block_title` longtext COLLATE utf8mb4_unicode_ci,
  `telegram_block_description` longtext COLLATE utf8mb4_unicode_ci,
  `telegram_block_button` longtext COLLATE utf8mb4_unicode_ci,
  `description_verification_card` longtext COLLATE utf8mb4_unicode_ci,
  `error_verification_card` longtext COLLATE utf8mb4_unicode_ci,
  `sitename` text COLLATE utf8mb4_unicode_ci,
  `sitename_desc` longtext COLLATE utf8mb4_unicode_ci,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `keywords` longtext COLLATE utf8mb4_unicode_ci,
  `username_new_user` text COLLATE utf8mb4_unicode_ci,
  `main_title_header` text COLLATE utf8mb4_unicode_ci,
  `main_value_header` text COLLATE utf8mb4_unicode_ci,
  `chat_app_id` text COLLATE utf8mb4_unicode_ci,
  `working_online_text` text COLLATE utf8mb4_unicode_ci,
  `working_offline_text` text COLLATE utf8mb4_unicode_ci,
  `referral_system_text` longtext COLLATE utf8mb4_unicode_ci,
  `referral_system_text_footer` longtext COLLATE utf8mb4_unicode_ci,
  `cashback_text` longtext COLLATE utf8mb4_unicode_ci,
  `cashback_footer` longtext COLLATE utf8mb4_unicode_ci,
  `monitoring_text` longtext COLLATE utf8mb4_unicode_ci,
  `description_pr` text COLLATE utf8mb4_unicode_ci,
  `description_review` text COLLATE utf8mb4_unicode_ci,
  `working_offline_notify` text COLLATE utf8mb4_unicode_ci,
  `s_order_notify_text` longtext COLLATE utf8mb4_unicode_ci,
  `jivosite_text_message` text COLLATE utf8mb4_unicode_ci,
  `title_rules_page` text COLLATE utf8mb4_unicode_ci,
  `description_rules_page` text COLLATE utf8mb4_unicode_ci,
  `description_request_payment` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `links_footer_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `links_footer_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sorting` int NOT NULL DEFAULT '0',
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `links_footers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `links_footers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `id_group` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_blank` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `links_review_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `links_review_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `links_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `links_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `icon` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_review` tinyint(1) NOT NULL DEFAULT '0',
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `id_group` int NOT NULL DEFAULT '0',
  `is_bot` int NOT NULL DEFAULT '0',
  `count_review` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `live_notification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `live_notification` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `text_alt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `color` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timeout` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_autopayments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_autopayments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `event_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_check_pay`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_check_pay` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `id_user` int NOT NULL DEFAULT '0',
  `event_type` int NOT NULL DEFAULT '0',
  `event_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_drain`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_drain` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `id_requisites` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `from_value` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_task` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_error_merchants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_error_merchants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `provider_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `event_type` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_merchants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_merchants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `event_type` int NOT NULL DEFAULT '0',
  `event_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_merchants_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_merchants_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_order` int NOT NULL DEFAULT '0',
  `ip_address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `headers` json DEFAULT NULL,
  `content` json DEFAULT NULL,
  `response` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `logs_autopayment_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs_autopayment_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_order` int NOT NULL DEFAULT '0',
  `ip_address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `headers` json DEFAULT NULL,
  `content` json DEFAULT NULL,
  `response` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `logs_autopayment_orders_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs_autopayment_orders_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_order` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `headers` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `response` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ltm_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ltm_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status` int NOT NULL DEFAULT '0',
  `locale` varchar(191) COLLATE utf8mb4_bin NOT NULL,
  `group` varchar(191) COLLATE utf8mb4_bin NOT NULL,
  `key` text COLLATE utf8mb4_bin NOT NULL,
  `value` text COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sorting` int NOT NULL,
  `slug` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name_alt` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_color` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `menu_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `merchant_account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_account` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `account` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `merchant_transaction_hash`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_transaction_hash` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `id_currency` int NOT NULL DEFAULT '0',
  `transaction_hash` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `merchant_transaction_hash_transaction_hash_index` (`transaction_hash`),
  KEY `merchant_transaction_hash_id_task_index` (`id_task`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `merchant_transaction_ids`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_transaction_ids` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `transaction_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` int unsigned NOT NULL,
  `model_id` int unsigned NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` int unsigned NOT NULL,
  `model_id` int unsigned NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `monitors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `monitors` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uptime_check_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `look_for_string` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `uptime_check_interval_in_minutes` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '5',
  `uptime_status` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not yet checked',
  `uptime_check_failure_reason` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `uptime_check_times_failed_in_a_row` int NOT NULL DEFAULT '0',
  `uptime_status_last_change_date` timestamp NULL DEFAULT NULL,
  `uptime_last_check_date` timestamp NULL DEFAULT NULL,
  `uptime_check_failed_event_fired_on_date` timestamp NULL DEFAULT NULL,
  `uptime_check_method` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'get',
  `certificate_check_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `certificate_status` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not yet checked',
  `certificate_expiration_date` timestamp NULL DEFAULT NULL,
  `certificate_issuer` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_check_failure_reason` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monitors_url_unique` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cr_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image` varchar(191) DEFAULT NULL,
  `parent_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `views` int NOT NULL DEFAULT '0',
  `slug_name` varchar(191) DEFAULT NULL,
  `is_local_image` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notices_exchange`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notices_exchange` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `color` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `link` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `is_enabled_schedule` int NOT NULL DEFAULT '0',
  `from_time` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_time` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_blank` int NOT NULL DEFAULT '0',
  `icon_notice` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_color` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bg_color` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_size` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` int unsigned NOT NULL,
  `notifiable_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_id_notifiable_type_index` (`notifiable_id`,`notifiable_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_access_tokens` (
  `id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `client_id` int NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_access_tokens_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_auth_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_auth_codes` (
  `id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `client_id` int NOT NULL,
  `scopes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_clients` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `website` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `secret` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `redirect` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_clients_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_personal_access_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_personal_access_clients` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_personal_access_clients_client_id_index` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_refresh_tokens` (
  `id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_refresh_tokens_access_token_id_index` (`access_token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operation_level_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operation_level_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `from_limit` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_limit` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operation_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operation_levels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_level_group` int NOT NULL DEFAULT '0',
  `id_operator` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_steps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8mb4_unicode_ci,
  `id_manager` int DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `icon` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `page_id` int unsigned NOT NULL AUTO_INCREMENT,
  `page_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `page_slug` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`page_id`),
  UNIQUE KEY `pages_page_slug_unique` (`page_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pages_static`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages_static` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title_about` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_about` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `about_visible` int NOT NULL DEFAULT '0',
  `text_contact` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_faq` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_faq` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_exchange_rules` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parser_api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parser_api_keys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_type` int NOT NULL DEFAULT '0',
  `api_key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `view_count` int NOT NULL DEFAULT '0',
  `provider_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parser_exchange`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parser_exchange` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `id_group` int DEFAULT NULL,
  `value` int NOT NULL DEFAULT '0',
  `summa` varchar(250) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `status` int NOT NULL DEFAULT '0',
  `id_type_price` int NOT NULL DEFAULT '0',
  `number_format` int NOT NULL DEFAULT '0',
  `type` int NOT NULL DEFAULT '0',
  `value_default` int NOT NULL DEFAULT '1',
  `summa_default` varchar(250) NOT NULL,
  `code_in` varchar(191) DEFAULT NULL,
  `code_out` varchar(191) DEFAULT NULL,
  `is_not_update` int NOT NULL DEFAULT '0',
  `code` varchar(191) DEFAULT NULL,
  `type_price` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parser_exchange_name_index` (`name`),
  KEY `parser_exchange_id_group_index` (`id_group`),
  KEY `parser_exchange_summa_index` (`summa`),
  KEY `parser_exchange_status_index` (`status`),
  KEY `parser_exchange_summa_default_index` (`summa_default`),
  KEY `parser_exchange_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parser_exchange_error_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parser_exchange_error_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_group` int NOT NULL DEFAULT '0',
  `source_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_parsing` int NOT NULL DEFAULT '0',
  `pair_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parser_exchange_http_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parser_exchange_http_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data` longtext COLLATE utf8mb4_unicode_ci,
  `url` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parser_exchange_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parser_exchange_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_parser_exchange` int NOT NULL DEFAULT '0',
  `in_price` double NOT NULL DEFAULT '0',
  `out_price` double NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parser_exchange_log_id_parser_exchange_foreign` (`id_parser_exchange`),
  CONSTRAINT `parser_exchange_log_id_parser_exchange_foreign` FOREIGN KEY (`id_parser_exchange`) REFERENCES `parser_exchange` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parser_formula_coefficient`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parser_formula_coefficient` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `summa` double(8,2) NOT NULL DEFAULT '0.00',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alias` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parser_formula_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parser_formula_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_parser_formula` int NOT NULL DEFAULT '0',
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `formula` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parser_formula_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parser_formula_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `exchange_in` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_out` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formula` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` int NOT NULL DEFAULT '0',
  `summa` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `number_format` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_coefficient` int NOT NULL DEFAULT '0',
  `coefficient_formula` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coefficient_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parser_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parser_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partner_parser_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partner_parser_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partner_parser_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partner_parser_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_in` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_out` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_group` int NOT NULL DEFAULT '0',
  `rate` double NOT NULL DEFAULT '0',
  `partner_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `partner_id` int NOT NULL DEFAULT '0',
  `group_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_format` int NOT NULL DEFAULT '0',
  `last_updated_at` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partners` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `logo` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `password` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `password_histories_user_id_foreign` (`user_id`),
  CONSTRAINT `password_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pay_transaction_hash`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_transaction_hash` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `api_transfer_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `id_currency` int NOT NULL DEFAULT '0',
  `transaction_hash` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pay_transaction_hash_transaction_hash_index` (`transaction_hash`),
  KEY `pay_transaction_hash_id_task_index` (`id_task`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_explorer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_explorer` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_payment` int NOT NULL DEFAULT '0',
  `link` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `logo` varchar(191) DEFAULT NULL,
  `enable_svg` int NOT NULL DEFAULT '0',
  `svg_name` varchar(191) DEFAULT NULL,
  `svg_class` varchar(191) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `is_delete` int NOT NULL DEFAULT '0',
  `blockchain_url` varchar(191) DEFAULT NULL,
  `is_import` int NOT NULL DEFAULT '0',
  `logo_svg` varchar(191) DEFAULT NULL,
  `is_local_image` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payout_address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payout_address` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `address` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pending_order_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pending_order_status` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_not_delete` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_permissions_group` int NOT NULL DEFAULT '0',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_user` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions_group` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `token_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `promo_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promo_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_manager` int NOT NULL DEFAULT '0',
  `count_uses` int NOT NULL DEFAULT '0' COMMENT 'Количество использований',
  `used` int NOT NULL DEFAULT '0',
  `discount_percent` double(8,2) NOT NULL DEFAULT '0.00',
  `code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permitted_directions` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `forbidden_directions` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proxies_payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proxies_payment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `address` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_user` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_agent` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proxies_qiwi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proxies_qiwi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `address` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_user` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pulse_aggregates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pulse_aggregates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bucket` int unsigned NOT NULL,
  `period` mediumint unsigned NOT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `aggregate` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(20,2) NOT NULL,
  `count` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_aggregates_bucket_period_type_aggregate_key_hash_unique` (`bucket`,`period`,`type`,`aggregate`,`key_hash`),
  KEY `pulse_aggregates_period_bucket_index` (`period`,`bucket`),
  KEY `pulse_aggregates_type_index` (`type`),
  KEY `pulse_aggregates_period_type_aggregate_bucket_index` (`period`,`type`,`aggregate`,`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pulse_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pulse_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` int unsigned NOT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `value` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pulse_entries_timestamp_index` (`timestamp`),
  KEY `pulse_entries_type_index` (`type`),
  KEY `pulse_entries_key_hash_index` (`key_hash`),
  KEY `pulse_entries_timestamp_type_key_hash_value_index` (`timestamp`,`type`,`key_hash`,`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pulse_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pulse_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` int unsigned NOT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_values_type_key_hash_unique` (`type`,`key_hash`),
  KEY `pulse_values_timestamp_index` (`timestamp`),
  KEY `pulse_values_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qiwi_currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qiwi_currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_currency` int NOT NULL DEFAULT '0',
  `selected` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `referral_program_id` int unsigned NOT NULL,
  `code` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `referral_links_referral_program_id_user_id_unique` (`referral_program_id`,`user_id`),
  KEY `referral_links_code_index` (`code`),
  CONSTRAINT `referral_links_referral_program_id_foreign` FOREIGN KEY (`referral_program_id`) REFERENCES `referral_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_referral_link` int NOT NULL,
  `id_referral` int NOT NULL,
  `id_user` int NOT NULL,
  `id_task` int NOT NULL DEFAULT '0',
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bonus` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bonus_number` float NOT NULL DEFAULT '0',
  `fixed_bonus` float NOT NULL DEFAULT '0',
  `current_percent` float NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_programs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `percent` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` int NOT NULL DEFAULT '1',
  `count` int NOT NULL DEFAULT '0',
  `is_reg` int NOT NULL DEFAULT '0',
  `lifetime_minutes` int NOT NULL DEFAULT '10080',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `style_width` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `referral_programs_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_relationships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_relationships` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `referral_link_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referral_relationships_referral_link_id_foreign` (`referral_link_id`),
  CONSTRAINT `referral_relationships_referral_link_id_foreign` FOREIGN KEY (`referral_link_id`) REFERENCES `referral_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_statistics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ref_hash` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_user` int NOT NULL DEFAULT '0',
  `is_archive` int NOT NULL DEFAULT '0',
  `user_agent` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cur_from` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cur_to` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_and_to` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referrals_info_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referrals_info_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_referral` int NOT NULL DEFAULT '0',
  `id_user` int NOT NULL DEFAULT '0',
  `type` int NOT NULL DEFAULT '0',
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `requirements_verification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requirements_verification` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `requisites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requisites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_currency` int NOT NULL DEFAULT '0',
  `id_user` int NOT NULL DEFAULT '0',
  `account_number` varchar(255) DEFAULT NULL,
  `view` int NOT NULL DEFAULT '0',
  `limit_day` int DEFAULT NULL,
  `limit_month` int DEFAULT NULL,
  `random` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `pick_certain` int NOT NULL DEFAULT '0' COMMENT 'Подобрать реквизит после определенного условия',
  `change_amount` varchar(191) DEFAULT NULL COMMENT 'Сменить реквизит если сумма превышает:',
  `is_history` int NOT NULL DEFAULT '0' COMMENT 'История П.С	',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `id_group` int NOT NULL DEFAULT '0',
  `account_number_field` varchar(191) DEFAULT NULL,
  `history_at` timestamp NULL DEFAULT NULL,
  `limit_views` int NOT NULL DEFAULT '0',
  `is_enabled_merchant` int NOT NULL DEFAULT '0',
  `id_proxy` int NOT NULL DEFAULT '0',
  `is_drain` int NOT NULL DEFAULT '0',
  `max_wallet_limit` int NOT NULL DEFAULT '0',
  `drain_comment` varchar(191) DEFAULT NULL,
  `drain_room` varchar(191) DEFAULT NULL,
  `is_unique_shot` int NOT NULL DEFAULT '0',
  `photo_status` int NOT NULL DEFAULT '0',
  `photo_name` varchar(191) DEFAULT NULL,
  `text_request_payment` longtext,
  PRIMARY KEY (`id`),
  KEY `requisites_id_currency_index` (`id_currency`),
  KEY `requisites_account_number_index` (`account_number`),
  KEY `requisites_status_index` (`status`),
  KEY `requisites_id_group_index` (`id_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `requisites_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requisites_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `value` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `id_currency` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `prefix` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `requisites_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requisites_group` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `requisites_has_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requisites_has_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `requisites_has_fields_field_id_foreign` (`field_id`),
  KEY `requisites_has_fields_model_id_foreign` (`model_id`),
  CONSTRAINT `requisites_has_fields_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `requisites_fields` (`id`) ON DELETE CASCADE,
  CONSTRAINT `requisites_has_fields_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `requisites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `requisites_has_info_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requisites_has_info_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `requisites_has_info_fields_field_id_foreign` (`field_id`),
  KEY `requisites_has_info_fields_model_id_foreign` (`model_id`),
  CONSTRAINT `requisites_has_info_fields_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `requisites_info_fields` (`id`) ON DELETE CASCADE,
  CONSTRAINT `requisites_has_info_fields_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `requisites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `requisites_info_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requisites_info_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key_name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `value_name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` int NOT NULL DEFAULT '0',
  `user_id` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `requisites_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requisites_list` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_requisites` int NOT NULL,
  `address` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reserve_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reserve_group` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_user` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reserve_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reserve_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_reserve` int NOT NULL,
  `id_task` int NOT NULL DEFAULT '0',
  `id_currency` int NOT NULL,
  `summa` float DEFAULT NULL,
  `commission` float DEFAULT NULL,
  `history` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `summa_not_fee` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summa_with_fee` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reserve_log_profit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reserve_log_profit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `id_currency` int NOT NULL DEFAULT '0',
  `percent` double(8,2) NOT NULL DEFAULT '0.00',
  `amount` double NOT NULL DEFAULT '0',
  `comment` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `amount_usd` double(8,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reserve_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reserve_request` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_direction_exchange` int NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `price` varchar(255) DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_delete` int NOT NULL DEFAULT '0',
  `id_currency` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reserves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reserves` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_currency` int NOT NULL,
  `summa` double NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_code_currency` int NOT NULL,
  `is_non_standard` int DEFAULT '0',
  `black_amount` float DEFAULT '0',
  `id_user` int NOT NULL DEFAULT '0',
  `id_main` int NOT NULL DEFAULT '0' COMMENT 'ID базового резерва',
  `id_group` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `is_fixed_reserve` int NOT NULL DEFAULT '0',
  `is_star` int NOT NULL DEFAULT '0',
  `id_file_reserve` int NOT NULL DEFAULT '0',
  `id_server_reserve` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reserves_id_currency_index` (`id_currency`),
  KEY `reserves_summa_index` (`summa`),
  KEY `reserves_status_index` (`status`),
  KEY `reserves_id_main_index` (`id_main`),
  KEY `reserves_is_fixed_reserve_index` (`is_fixed_reserve`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reserves_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reserves_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_reserve` int NOT NULL,
  `is_telegram` int NOT NULL DEFAULT '0',
  `is_email` int NOT NULL DEFAULT '0',
  `threshold` double NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `count_alert` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `reserves_alerts_id_reserve_foreign` (`id_reserve`),
  CONSTRAINT `reserves_alerts_id_reserve_foreign` FOREIGN KEY (`id_reserve`) REFERENCES `reserves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reserves_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reserves_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_group` int NOT NULL DEFAULT '0',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` double NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `number_format` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reserves_files_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reserves_files_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `link` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_agent` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_admin` int NOT NULL DEFAULT '0',
  `rate_speed` int NOT NULL DEFAULT '0',
  `id_task` int NOT NULL DEFAULT '0',
  `id_direction_exchange` int NOT NULL DEFAULT '0',
  `version` int NOT NULL DEFAULT '0',
  `id_user` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reward`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reward` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `reward_program_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reward_reward_program_id_user_id_unique` (`reward_program_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reward_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reward_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_reward_link` int NOT NULL,
  `id_task` int NOT NULL,
  `id_user` int NOT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bonus` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bonus_string` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `percent` double DEFAULT '0',
  `is_cashback` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reward_programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reward_programs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `percent` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sign` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_reg` int NOT NULL DEFAULT '0',
  `amount` int NOT NULL,
  `title` text COLLATE utf8mb4_unicode_ci,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `style_width` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `reward_programs_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` int unsigned NOT NULL,
  `role_id` int unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_export` int NOT NULL DEFAULT '0',
  `guard_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rules_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rules_pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` text COLLATE utf8mb4_unicode_ci,
  `id_page` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  UNIQUE KEY `sessions_id_unique` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sitename` varchar(255) DEFAULT NULL,
  `sitename_desc` varchar(191) DEFAULT NULL,
  `url` varchar(191) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `job` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `description` text,
  `description_home` text,
  `allow_reserve` int NOT NULL,
  `allow_partner` int NOT NULL DEFAULT '0',
  `block_visible_reserve` int NOT NULL DEFAULT '0',
  `notify_change_status` int NOT NULL,
  `auto_register` int NOT NULL,
  `max_time_task` int NOT NULL,
  `service_break` int NOT NULL DEFAULT '0' COMMENT 'Технический перерыв',
  `service_break_time` int NOT NULL DEFAULT '0',
  `service_break_interval` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `update_parser` timestamp NULL DEFAULT NULL,
  `language` int NOT NULL DEFAULT '0',
  `multi_language` int NOT NULL DEFAULT '0',
  `visible_description` int NOT NULL DEFAULT '0',
  `visible_last_exchange` int NOT NULL DEFAULT '0',
  `count_last_exchange` int NOT NULL DEFAULT '2',
  `fund` varchar(11) NOT NULL DEFAULT '0',
  `email_support` varchar(191) DEFAULT NULL,
  `lead_time_task` int NOT NULL DEFAULT '0',
  `network_bitcoin` int NOT NULL DEFAULT '0' COMMENT 'Загруженность сети bitcoin	',
  `telegram_notify` int NOT NULL DEFAULT '0',
  `telegram_chat_id` varchar(191) NOT NULL DEFAULT '0',
  `tg_stat_chat_id` varchar(191) NOT NULL,
  `show_task_page` int NOT NULL DEFAULT '0' COMMENT 'Количество, отображаемых заявок на странице',
  `active_direction` text,
  `generate_min_price` int DEFAULT '0',
  `position_bestchange` int NOT NULL DEFAULT '0',
  `unlimited_reserve` int NOT NULL DEFAULT '0',
  `analytics_email` varchar(191) DEFAULT NULL,
  `analytics_status` int NOT NULL DEFAULT '0',
  `visible_my_order` int NOT NULL DEFAULT '0',
  `disable_frame` int NOT NULL DEFAULT '0',
  `reg_multi_ip` int NOT NULL DEFAULT '0',
  `hide_exchange_guest` int NOT NULL DEFAULT '0',
  `enable_cache` int NOT NULL DEFAULT '0',
  `admin_allowed_ip` text,
  `offline_reason` text,
  `allow_filter_currency` int NOT NULL DEFAULT '0',
  `contact_job` varchar(191) DEFAULT NULL,
  `contact_email` varchar(191) DEFAULT NULL,
  `contact_telegram` varchar(191) DEFAULT NULL,
  `contact_phone` varchar(191) DEFAULT NULL,
  `is_contact_job` int NOT NULL DEFAULT '0',
  `is_contact_email` int NOT NULL DEFAULT '0',
  `is_contact_telegram` int NOT NULL DEFAULT '0',
  `is_contact_phone` int NOT NULL DEFAULT '0',
  `monitoring_description` text,
  `captcha_restoring_password` int NOT NULL DEFAULT '0',
  `order_priority` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings_referrals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings_referrals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_text` text,
  `partners_text` text,
  `block_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings_reward`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings_reward` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_text` text,
  `partners_text` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `block_text` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings_theme`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings_theme` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `visible_fon` int NOT NULL,
  `background_svg` int NOT NULL DEFAULT '0',
  `start_sharing` int DEFAULT '0' COMMENT 'Стиль кнопки "Начать обмен"',
  `fon` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_position_1` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_position_2` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_repeat` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_size` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_background` int NOT NULL DEFAULT '0',
  `favicon` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logotype` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `textlogotype` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `select_logotype` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `social_auth_system`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_auth_system` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alias` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_secret` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `social_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `link` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `id_user` int NOT NULL,
  `text` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_log_confirmation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_log_confirmation` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `confirmation` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_log_confirmation_id_task_foreign` (`id_task`),
  CONSTRAINT `task_log_confirmation_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_private_hash`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_private_hash` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `hash` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL,
  `profit` double NOT NULL DEFAULT '0',
  `add_course` double NOT NULL DEFAULT '0',
  `your_add_course` double NOT NULL DEFAULT '0',
  `commission` double NOT NULL DEFAULT '0',
  `commission_s` double NOT NULL DEFAULT '0',
  `is_group_commission` int NOT NULL DEFAULT '0',
  `group_commission_value` double NOT NULL DEFAULT '0',
  `sign` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `profit_usd` double NOT NULL DEFAULT '0',
  `profit_s` double NOT NULL DEFAULT '0',
  `profit_rub` double NOT NULL DEFAULT '0',
  `is_bestchange` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_reports_id_task_unique` (`id_task`),
  CONSTRAINT `task_reports_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_single_log_confirm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_single_log_confirm` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `needed_confirm` int NOT NULL DEFAULT '0',
  `received_confirm` int NOT NULL DEFAULT '0',
  `transaction_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `big_id` varchar(255) NOT NULL DEFAULT '0',
  `random_id` varchar(255) NOT NULL DEFAULT '0',
  `type` varchar(191) NOT NULL DEFAULT 'order',
  `id_user` int NOT NULL DEFAULT '0',
  `id_direction_exchange` int NOT NULL DEFAULT '0',
  `id_payment_requisites` int NOT NULL DEFAULT '0',
  `id_wallets_addresses` int DEFAULT NULL,
  `give_price` varchar(255) DEFAULT NULL,
  `receiving_price` varchar(255) DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip` varchar(255) NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '2',
  `from_shot` varchar(255) DEFAULT NULL,
  `to_shot` varchar(255) DEFAULT NULL,
  `passed` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `description_receiving` text,
  `description_give` text,
  `discount1` double NOT NULL,
  `discount2` double NOT NULL,
  `markup1` double NOT NULL DEFAULT '0',
  `markup2` double NOT NULL DEFAULT '0',
  `email` varchar(255) DEFAULT NULL,
  `scans` int NOT NULL DEFAULT '0' COMMENT 'Кто просматривает заявку',
  `id_payment_list` int NOT NULL DEFAULT '0',
  `payment_address` varchar(191) DEFAULT NULL COMMENT 'Генерируемые номера счетов (Bitcoin, Eth ...)	',
  `rejection_reason` varchar(191) DEFAULT NULL COMMENT 'Причина отклонения заявки	',
  `start` int NOT NULL DEFAULT '0' COMMENT 'Запускаем выполнение заявки (0 -> Не запущен процесс) (1 -> Запущен)	',
  `lead_time` timestamp NULL DEFAULT NULL COMMENT 'Время выполнения заявки со статусов "Ожидается выполнение"	',
  `message_success` text COMMENT 'Текст при успешном завершении заявки',
  `check_status` int NOT NULL DEFAULT '0' COMMENT 'Статус отправки чека клиенту	',
  `id_manager` int NOT NULL DEFAULT '0' COMMENT 'ID менеджера, который выполнил заявку.',
  `course_display` varchar(191) DEFAULT NULL,
  `merchant_status` int NOT NULL DEFAULT '0' COMMENT 'Был ли переход на мерчант',
  `income_code` text,
  `check_income_code` int NOT NULL DEFAULT '0',
  `sender_fullname` varchar(191) DEFAULT NULL,
  `recipient_fullname` varchar(191) DEFAULT NULL,
  `is_frozen` int NOT NULL DEFAULT '0' COMMENT 'Замороженные средства',
  `is_reserve` int NOT NULL DEFAULT '0' COMMENT 'Каким образом будут сниматься резервы.	',
  `skip_reserve` int NOT NULL DEFAULT '0' COMMENT 'Пропустить пополнение резерва	',
  `course_float` varchar(191) DEFAULT '0',
  `payment_field` varchar(191) DEFAULT NULL,
  `category_reject` int NOT NULL DEFAULT '0',
  `outcome_unk` varchar(191) DEFAULT NULL,
  `is_archive` int NOT NULL DEFAULT '0',
  `operator_started_at` timestamp NULL DEFAULT NULL,
  `id_rejection_status` int NOT NULL DEFAULT '0',
  `id_pending_status` int NOT NULL DEFAULT '0',
  `is_favorites` int NOT NULL DEFAULT '0',
  `is_spam` int NOT NULL DEFAULT '0',
  `is_bot` int NOT NULL DEFAULT '0',
  `register_tx` int NOT NULL DEFAULT '0',
  `merchant_incomplete_payment` int NOT NULL DEFAULT '0',
  `merchant_overpayment` int NOT NULL DEFAULT '0',
  `is_hold` int NOT NULL DEFAULT '0',
  `in_flow_funds` int NOT NULL DEFAULT '0',
  `next_checkout_at` timestamp NULL DEFAULT NULL,
  `double_withdrawal` int NOT NULL DEFAULT '0',
  `phone` varchar(191) DEFAULT NULL,
  `is_drain_merchant` int NOT NULL DEFAULT '0',
  `pay_num` int NOT NULL DEFAULT '0',
  `is_autopay_off` int NOT NULL DEFAULT '0',
  `merchant_provider` varchar(191) DEFAULT NULL,
  `id_referral_link` int NOT NULL DEFAULT '0',
  `is_new_user` int NOT NULL DEFAULT '0',
  `type_finished_order` int NOT NULL DEFAULT '0',
  `unique_security_code` varchar(191) DEFAULT NULL,
  `kunacode` varchar(191) DEFAULT NULL,
  `is_autopay_modal` int NOT NULL DEFAULT '0',
  `is_autopay_limit` int NOT NULL DEFAULT '0',
  `id_edit_data_manager` int NOT NULL DEFAULT '0',
  `telegram_id` varchar(191) DEFAULT NULL,
  `in_price_fee` varchar(191) NOT NULL DEFAULT '0',
  `out_price_fee` varchar(191) NOT NULL DEFAULT '0',
  `is_ban_order_data` int NOT NULL DEFAULT '0',
  `id_main_operator` int NOT NULL DEFAULT '0',
  `requisites_receive` varchar(191) DEFAULT NULL COMMENT 'Реквизиты, куда принимаются средства',
  `id_merchant` int NOT NULL DEFAULT '0',
  `id_pay` int NOT NULL DEFAULT '0',
  `is_auto_check_pay` int NOT NULL DEFAULT '0',
  `queue_status` int NOT NULL DEFAULT '0' COMMENT 'В очереди на выплату',
  `id_payment_gateway` bigint NOT NULL DEFAULT '0',
  `id_promo_code` int NOT NULL DEFAULT '0',
  `promo_discount` double(8,2) NOT NULL DEFAULT '0.00',
  `id_direction_requisites` int NOT NULL DEFAULT '0',
  `int_status_verification_card` int NOT NULL DEFAULT '0',
  `in_amount_merchant` varchar(191) DEFAULT NULL,
  `out_amount_pay` varchar(191) DEFAULT NULL,
  `public_id` bigint NOT NULL DEFAULT '0',
  `transfer_to_account` varchar(191) DEFAULT NULL COMMENT 'Записанный адрес куда клиенты отправляют средства',
  `scan_name` varchar(191) DEFAULT NULL,
  `is_status_pay_callback` int NOT NULL DEFAULT '0',
  `status_pay_api` int NOT NULL DEFAULT '0',
  `is_status_pay_email` int NOT NULL DEFAULT '0',
  `is_attempts_pay` int NOT NULL DEFAULT '0',
  `referral_hash` varchar(191) DEFAULT NULL,
  `give_price_default` varchar(191) NOT NULL DEFAULT '0',
  `give_price_with_comm` varchar(191) NOT NULL DEFAULT '0',
  `give_price_with_comm_pay` varchar(191) NOT NULL DEFAULT '0',
  `give_price_fee_comm` varchar(191) NOT NULL DEFAULT '0',
  `give_price_fee_pay` varchar(191) NOT NULL DEFAULT '0',
  `give_price_reserve` varchar(191) NOT NULL DEFAULT '0',
  `receiving_price_default` varchar(191) NOT NULL DEFAULT '0',
  `receiving_price_with_comm` varchar(191) NOT NULL DEFAULT '0',
  `receiving_price_with_comm_pay` varchar(191) NOT NULL DEFAULT '0',
  `receiving_price_fee_comm` varchar(191) NOT NULL DEFAULT '0',
  `receiving_price_fee_pay` varchar(191) NOT NULL DEFAULT '0',
  `receiving_price_reserve` varchar(191) NOT NULL DEFAULT '0',
  `receiving_price_discount` varchar(191) NOT NULL DEFAULT '0',
  `receiving_price_discount_fee` varchar(191) NOT NULL DEFAULT '0',
  `int_error_type` int NOT NULL DEFAULT '0',
  `error_type_text` text,
  `is_send_mail_create` int NOT NULL DEFAULT '0',
  `id_order_step` int NOT NULL DEFAULT '0',
  `is_request_payment_type` int NOT NULL DEFAULT '0',
  `is_wallet_issued` int NOT NULL DEFAULT '0',
  `requisites_description` text,
  `is_file_check` int NOT NULL DEFAULT '0',
  `is_wait_callback` int NOT NULL DEFAULT '0',
  `receiving_price_with_promocode` varchar(191) NOT NULL DEFAULT '0',
  `promo_code_value` varchar(191) DEFAULT NULL,
  `promo_code_discount` varchar(191) DEFAULT NULL,
  `method_request_payment` int NOT NULL DEFAULT '0',
  `is_run_process` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `tasks_status_index` (`status`),
  KEY `tasks_id_user_index` (`id_user`),
  KEY `tasks_ip_index` (`ip`),
  KEY `tasks_give_price_index` (`give_price`),
  KEY `tasks_receiving_price_index` (`receiving_price`),
  KEY `tasks_id_direction_exchange_index` (`id_direction_exchange`),
  KEY `tasks_from_shot_index` (`from_shot`),
  KEY `tasks_to_shot_index` (`to_shot`),
  KEY `tasks_public_id_index` (`public_id`),
  KEY `tasks_transfer_to_account_index` (`transfer_to_account`),
  KEY `tasks_telegram_id_index` (`telegram_id`),
  KEY `tasks_email_index` (`email`),
  KEY `tasks_is_archive_index` (`is_archive`),
  KEY `tasks_id_payment_requisites_index` (`id_payment_requisites`),
  KEY `tasks_id_wallets_addresses_index` (`id_wallets_addresses`),
  KEY `tasks_is_frozen_index` (`is_frozen`),
  KEY `tasks_register_tx_index` (`register_tx`),
  KEY `tasks_phone_index` (`phone`),
  KEY `tasks_is_spam_index` (`is_spam`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_card_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_card_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `card_number` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_system` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_card` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand_card` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_currency` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_url` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bank_phone` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_chat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_chat` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `id_task` int NOT NULL DEFAULT '0',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_client` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_check_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_check_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_order` int NOT NULL DEFAULT '0',
  `image` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `id_manager` int NOT NULL DEFAULT '0',
  `message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `class_style` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_comments_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_comments_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_manager` int NOT NULL DEFAULT '0',
  `id_task` int NOT NULL DEFAULT '0',
  `message` text COLLATE utf8mb4_unicode_ci,
  `class_style` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_convert_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_convert_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL,
  `to_usd` float DEFAULT NULL,
  `to_rub` float DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `id_field` int NOT NULL DEFAULT '0',
  `field_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_value` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type_field` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alias` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `id_manager` int NOT NULL DEFAULT '0',
  `text` text COLLATE utf8mb4_unicode_ci,
  `file` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_history_operators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_history_operators` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` int NOT NULL DEFAULT '0',
  `id_task` int NOT NULL DEFAULT '0',
  `id_manager` int NOT NULL DEFAULT '0',
  `id_status` int NOT NULL DEFAULT '0',
  `in_amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `out_amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `course` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_from_manager` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_info` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0' COMMENT 'ID заявки',
  `code_country` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Код страны',
  `country` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Страна',
  `city` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP адрес',
  `device` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'С какого устройства была создана заявка',
  `newbie` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Определяем, новичек ли создал заявку',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_scam` int NOT NULL DEFAULT '0',
  `is_local_scam` int NOT NULL DEFAULT '0',
  `language` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ru',
  `is_not_bonus` int NOT NULL DEFAULT '0',
  `is_not_partner` int NOT NULL DEFAULT '0',
  `is_bestchange_parser` int NOT NULL DEFAULT '0',
  `bestchange_position` int NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_switch_not_cashback` int NOT NULL DEFAULT '0' COMMENT 'В процессе закрытия заявки',
  `is_switch_not_partner` int NOT NULL DEFAULT '0' COMMENT 'В процессе закрытия заявки',
  `in_min_amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `in_max_amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_freeze_scam` tinyint(1) NOT NULL DEFAULT '0',
  `num_transaction` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_sign_payout` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_name_payout` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_position_payout` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notify_statuses` int NOT NULL DEFAULT '0',
  `is_freeze_local` int NOT NULL DEFAULT '0',
  `note_tx` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `count_change_operator` int NOT NULL DEFAULT '0',
  `amlbot_risk_in` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amlbot_risk_out` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_pending` int NOT NULL DEFAULT '0',
  `blockchain_confirm` int NOT NULL DEFAULT '0',
  `blockchain_hash` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_transaction_merchant` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_transaction_pay` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `getblockbot_risk_in` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `getblockbot_risk_out` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_id` int NOT NULL DEFAULT '0',
  `city_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `network_id` int NOT NULL DEFAULT '0',
  `network_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_aml_analysis` int NOT NULL DEFAULT '0',
  `aml_riskscore` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_wait_hash_pay` int NOT NULL DEFAULT '0' COMMENT 'Включаем когда не выдается hash оплаты сразу',
  `recalculated_at` timestamp NULL DEFAULT NULL,
  `country_id` int NOT NULL DEFAULT '0',
  `country_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_blocked_chat` int NOT NULL DEFAULT '0',
  `dot_not_remember_data` int NOT NULL DEFAULT '0',
  `aml_address_risk_in` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aml_address_risk_out` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_message_order` text COLLATE utf8mb4_unicode_ci,
  `aml_result_value` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_aml_high_risk` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `tasks_info_aml_riskscore_index` (`aml_riskscore`),
  KEY `tasks_info_city_name_index` (`city_name`),
  KEY `tasks_info_country_name_index` (`country_name`),
  KEY `tasks_info_is_aml_analysis_index` (`is_aml_analysis`),
  KEY `tasks_info_newbie_index` (`newbie`),
  KEY `tasks_info_is_local_scam_index` (`is_local_scam`),
  KEY `tasks_info_recalculated_at_index` (`recalculated_at`),
  KEY `tasks_info_country_id_index` (`country_id`),
  KEY `tasks_info_city_id_index` (`city_id`),
  KEY `tasks_info_device_index` (`device`),
  KEY `tasks_info_language_index` (`language`),
  KEY `tasks_info_bestchange_position_index` (`bestchange_position`),
  KEY `tasks_info_id_task_index` (`id_task`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_managers_styles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_managers_styles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL DEFAULT '0',
  `count_block_home` int NOT NULL DEFAULT '0',
  `is_hide_block_client` int NOT NULL DEFAULT '0',
  `is_hide_block_receive_service` int NOT NULL DEFAULT '0',
  `is_hide_block_give_service` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` bigint NOT NULL DEFAULT '0',
  `user_id` int NOT NULL DEFAULT '0',
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type_user` int NOT NULL DEFAULT '0',
  `is_view` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_profits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_profits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `profit_percent` double NOT NULL DEFAULT '0',
  `profit_currency` double NOT NULL DEFAULT '0',
  `profit_usd` double(8,2) NOT NULL DEFAULT '0.00',
  `profit_rub` double(8,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_rates_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_rates_data` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL,
  `exchange_course` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `market_course` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_rejection_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_rejection_status` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_not_delete` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_requisites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_requisites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_task` int NOT NULL DEFAULT '0',
  `id_direction_exchange` int NOT NULL DEFAULT '0',
  `id_currency` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_shots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_shots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int DEFAULT NULL,
  `account` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `color` varchar(191) DEFAULT NULL,
  `class` varchar(191) DEFAULT NULL,
  `is_export` int NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT NULL,
  `allow_delete` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_status_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_status_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL DEFAULT '0',
  `id_task` int NOT NULL DEFAULT '0',
  `old_status` int NOT NULL DEFAULT '0',
  `new_status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `in_price` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `out_price` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `place_change` int DEFAULT NULL,
  `course_display` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks_user` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL,
  `id_user` int NOT NULL,
  `convert_to_usd` float NOT NULL,
  `convert_to_rub` float NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_user_id_task_index` (`id_task`),
  KEY `tasks_user_id_user_index` (`id_user`),
  KEY `tasks_user_convert_to_usd_index` (`convert_to_usd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries` (
  `sequence` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `family_hash` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `should_display_on_index` tinyint(1) NOT NULL DEFAULT '1',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`sequence`),
  UNIQUE KEY `telescope_entries_uuid_unique` (`uuid`),
  KEY `telescope_entries_batch_id_index` (`batch_id`),
  KEY `telescope_entries_type_should_display_on_index_index` (`type`,`should_display_on_index`),
  KEY `telescope_entries_family_hash_index` (`family_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries_tags` (
  `entry_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tag` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `telescope_entries_tags_entry_uuid_tag_index` (`entry_uuid`,`tag`),
  KEY `telescope_entries_tags_tag_index` (`tag`),
  CONSTRAINT `telescope_entries_tags_entry_uuid_foreign` FOREIGN KEY (`entry_uuid`) REFERENCES `telescope_entries` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_monitoring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_monitoring` (
  `tag` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_type_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `template_type_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` int NOT NULL DEFAULT '0',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `id_user` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL,
  `transaction` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transit_requisites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transit_requisites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_currency` int NOT NULL DEFAULT '0',
  `account` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `view` int NOT NULL DEFAULT '0',
  `received` double NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `typed_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `typed_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `payload` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `typed_settings_group_name_unique` (`group`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unpaid_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unpaid_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `auto_delete` int NOT NULL DEFAULT '0',
  `time_day` int NOT NULL DEFAULT '0',
  `time_minute` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `order_status` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_hour` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `update_systems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `update_systems` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `update_site_proxy_addr` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_site_proxy_port` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_site_proxy_user` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_site_proxy_pass` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stable_versions_only` int NOT NULL DEFAULT '1',
  `update_autocheck` int NOT NULL DEFAULT '0',
  `update_stop_autocheck` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_auth`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_auth` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `source` int NOT NULL DEFAULT '0',
  `browser` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `os` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `country` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iso_code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_balance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_balance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL,
  `balance` float DEFAULT '0',
  `balance_reward` float DEFAULT '0',
  `id_code_currency` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `referral_total_profit` double NOT NULL DEFAULT '0',
  `referral_total_withdrawal` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_balance_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_balance_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `type` int NOT NULL DEFAULT '0',
  `id_manager` int NOT NULL DEFAULT '0',
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `from_balance` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_balance` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `route_type` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_verification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_verification` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL DEFAULT '0',
  `file_one` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_two` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fio_user` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `hash_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_wallet_stories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_wallet_stories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `wallet` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `id_currency` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_admin` int NOT NULL DEFAULT '0',
  `is_root` int NOT NULL DEFAULT '0',
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT 'Последний вход',
  `last_logout_at` timestamp NULL DEFAULT NULL COMMENT 'Последний выход',
  `last_activity_at` timestamp NULL DEFAULT NULL COMMENT 'Последняя активность',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `referral_program_id` int unsigned DEFAULT NULL,
  `google2fa_secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `session_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `banned_at` timestamp NULL DEFAULT NULL,
  `language` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `safe_input` int NOT NULL DEFAULT '0',
  `username` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deactivation` int NOT NULL DEFAULT '0',
  `is_order` int NOT NULL DEFAULT '0',
  `auto_withdrawal` int NOT NULL DEFAULT '0',
  `min_withdrawal` float NOT NULL DEFAULT '0',
  `current_page` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_unique_user` int NOT NULL DEFAULT '0',
  `order_num` int NOT NULL DEFAULT '0',
  `ip_changed` tinyint(1) DEFAULT NULL,
  `role_expired_at` timestamp NULL DEFAULT NULL,
  `is_follow_referral` tinyint(1) NOT NULL DEFAULT '0',
  `is_notify_email` int NOT NULL DEFAULT '0',
  `is_password_reset` int NOT NULL DEFAULT '0',
  `user_agent` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_pay_referral` int NOT NULL DEFAULT '0',
  `is_pay_cashback` int NOT NULL DEFAULT '0',
  `is_verification` int NOT NULL DEFAULT '0',
  `num_auth` int NOT NULL DEFAULT '0',
  `telegram` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_download_codes` int NOT NULL DEFAULT '0',
  `backup_code_secret` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_phone` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_hash` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_report_referral_data` int NOT NULL DEFAULT '0',
  `user_browser` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_device` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_style` int NOT NULL DEFAULT '0',
  `is_enabled_restapi` int NOT NULL DEFAULT '0',
  `restapi_key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_guest` int NOT NULL DEFAULT '0',
  `personal_discount` double NOT NULL DEFAULT '0',
  `personal_ref_discount` double(8,2) NOT NULL DEFAULT '0.00',
  `max_ref_discount` double(8,2) NOT NULL DEFAULT '0.00',
  `security_order_page_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_enable_order_paginate` int NOT NULL DEFAULT '0',
  `is_verify_account` int NOT NULL DEFAULT '0',
  `is_hidden_ip_address` int NOT NULL DEFAULT '0',
  `is_checkbox_rules` int NOT NULL DEFAULT '0',
  `is_checkbox_aml` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_referral_program_id_foreign` (`referral_program_id`),
  KEY `users_email_index` (`email`),
  KEY `users_auto_withdrawal_index` (`auto_withdrawal`),
  KEY `users_provider_index` (`provider`),
  KEY `users_last_activity_at_index` (`last_activity_at`),
  KEY `users_is_unique_user_index` (`is_unique_user`),
  KEY `users_email_verified_at_index` (`email_verified_at`),
  KEY `users_deactivation_index` (`deactivation`),
  KEY `users_is_order_index` (`is_order`),
  KEY `users_name_index` (`name`),
  KEY `users_ip_address_index` (`ip_address`),
  KEY `users_created_at_index` (`created_at`),
  KEY `users_last_login_at_index` (`last_login_at`),
  KEY `users_last_logout_at_index` (`last_logout_at`),
  KEY `users_order_num_index` (`order_num`),
  CONSTRAINT `users_referral_program_id_foreign` FOREIGN KEY (`referral_program_id`) REFERENCES `referral_programs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verification_card`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_card` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL,
  `id_order` int NOT NULL DEFAULT '0',
  `card_number` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_verified` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `hash_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_currency` int NOT NULL DEFAULT '0',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `ip_address` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_number_string` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_local_image` int NOT NULL DEFAULT '0',
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_message` text COLLATE utf8mb4_unicode_ci,
  `id_manager` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `verification_card_id_user_index` (`id_user`),
  KEY `verification_card_id_order_index` (`id_order`),
  KEY `verification_card_card_number_index` (`card_number`),
  KEY `verification_card_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verification_card_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_card_category` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verification_card_instructions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_card_instructions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_category` int NOT NULL DEFAULT '0',
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notice_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` int NOT NULL DEFAULT '0',
  `sorting` int NOT NULL DEFAULT '0',
  `image` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallet_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0',
  `amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `txid` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmations` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallets_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallets_addresses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_requisites` int NOT NULL DEFAULT '0' COMMENT 'ID платежной системы',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Адрес кошелька',
  `account` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Метки',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `private_key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_failed_send` int NOT NULL DEFAULT '0',
  `is_fee_sent` int NOT NULL DEFAULT '0',
  `provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `memo_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallets_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallets_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_task` int NOT NULL DEFAULT '0' COMMENT 'ID заявки',
  `txid` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'ID транзакции',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whitebit_withdraw`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whitebit_withdraw` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `method_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_task` int NOT NULL DEFAULT '0',
  `address` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticker` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `transaction_hash` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unique_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whitelist_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whitelist_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` int NOT NULL DEFAULT '0',
  `text` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `withdrawal_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdrawal_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL,
  `id_manager` int NOT NULL,
  `id_currency` int NOT NULL,
  `referral` int DEFAULT '0',
  `reward` int NOT NULL DEFAULT '0',
  `status` int NOT NULL,
  `score` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance_referral` float DEFAULT '0',
  `balance_reward` float NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `ip` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_referral` float DEFAULT '0',
  `base_reward` float NOT NULL DEFAULT '0',
  `verified_at` timestamp NULL DEFAULT NULL,
  `tx_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `big_id` bigint NOT NULL DEFAULT '0',
  `is_black_list` int NOT NULL DEFAULT '0',
  `black_list_text` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `view_balance_referral` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `view_balance_reward` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `withdrawal_request_tx_id_unique` (`tx_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `withdrawal_request_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdrawal_request_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_withdrawal_request` int NOT NULL,
  `id_manager` int NOT NULL DEFAULT '0',
  `text` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remainder` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `withdrawal_wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdrawal_wallets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL DEFAULT '0',
  `id_currency` int NOT NULL DEFAULT '0',
  `wallet` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `yandex_currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `yandex_currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_currency` int NOT NULL DEFAULT '0',
  `selected` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `your_exchange`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `your_exchange` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `exchange_rate` double NOT NULL,
  `number_format` int NOT NULL DEFAULT '4',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2017_09_16_161337_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2017_09_16_174415_create_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2017_09_16_174538_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2017_09_16_174559_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2017_10_03_232930_create_payout_address_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2017_10_04_233720_create_requisites_list_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2017_10_05_101321_create_reserve_log_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2017_10_05_113501_create_settings_theme_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2017_10_05_154154_create_partners_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2017_10_05_171612_create_backgrounds_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2017_10_06_054328_create_discounts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2017_10_06_063139_create_unpaid_items_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2017_10_09_172644_create_cron_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2017_10_09_193412_create_cron_category_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2017_10_10_084322_create_transaction_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2017_10_10_165846_create_faq_category_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2017_10_10_165915_create_faq_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2016_06_01_000001_create_oauth_auth_codes_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2016_06_01_000002_create_oauth_access_tokens_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2016_06_01_000003_create_oauth_refresh_tokens_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2016_06_01_000004_create_oauth_clients_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2016_06_01_000005_create_oauth_personal_access_clients_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2017_10_12_123532_create_generator_currency_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2017_10_20_073637_create_filter_currency_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2017_10_21_002533_create_monitors_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2015_03_07_311070_create_tracker_paths_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2015_03_07_311071_create_tracker_queries_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2015_03_07_311072_create_tracker_queries_arguments_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2015_03_07_311073_create_tracker_routes_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2015_03_07_311074_create_tracker_routes_paths_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2015_03_07_311075_create_tracker_route_path_parameters_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2015_03_07_311076_create_tracker_agents_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2015_03_07_311077_create_tracker_cookies_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2015_03_07_311078_create_tracker_devices_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2015_03_07_311079_create_tracker_domains_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2015_03_07_311080_create_tracker_referers_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2015_03_07_311081_create_tracker_geoip_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2015_03_07_311082_create_tracker_sessions_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2015_03_07_311083_create_tracker_errors_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2015_03_07_311084_create_tracker_system_classes_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2015_03_07_311085_create_tracker_log_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2015_03_07_311086_create_tracker_events_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2015_03_07_311087_create_tracker_events_log_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2015_03_07_311088_create_tracker_sql_queries_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2015_03_07_311089_create_tracker_sql_query_bindings_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2015_03_07_311090_create_tracker_sql_query_bindings_parameters_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2015_03_07_311091_create_tracker_sql_queries_log_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2015_03_07_311092_create_tracker_connections_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2015_03_07_311093_create_tracker_tables_relations',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2015_03_13_311094_create_tracker_referer_search_term_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2015_03_13_311095_add_tracker_referer_columns',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2015_11_23_311096_add_tracker_referer_column_to_log',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2015_11_23_311097_create_tracker_languages_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2015_11_23_311098_add_language_id_column_to_sessions',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2015_11_23_311099_add_tracker_language_foreign_key_to_sessions',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2015_11_23_311100_add_nullable_to_tracker_error',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2017_01_31_311101_fix_agent_name',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2017_06_20_311102_add_agent_name_hash',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2014_02_01_311070_create_firewall_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2017_10_21_235416_create_sessions_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2017_10_22_124906_create_task_log_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2017_10_22_201100_create_referral_programs_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2017_10_22_201101_create_referral_links_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2017_10_22_201102_create_referral_relationships_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2017_10_22_201103_add_allowed_ref_program_to_users',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2017_10_23_175416_create_user_balance_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2017_10_23_204855_create_referral_log_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2017_10_24_085006_create_reward_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2017_10_24_085144_create_reward_programs_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2017_10_24_103816_create_reward_log_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2017_10_24_233013_create_pages_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2017_10_25_083556_create_menu_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2017_10_25_190125_create_parser_type_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2017_10_25_211438_create_user_settings_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2017_10_25_223218_create_fund_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2017_03_04_000000_create_bans_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2018_06_28_000847_add_banned_at_column_to_users_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2018_06_29_101540_create_docs_category_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2018_06_29_102056_create_docs_category_type_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2018_06_29_102139_create_docs_items_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2018_07_05_184029_create_push_subscriptions_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2018_07_05_194923_add_uuid_column_to_users',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2018_09_04_064813_create_favorites_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2018_09_06_203049_create_banned_user_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2018_09_07_063952_create_tasks_rates_data_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2019_02_04_141815_create_tasks_status_log_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2019_02_06_165323_create_notices_exchange_table',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2019_02_06_191328_add_username_to_users',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2018_09_09_000001_create_table_health_checks',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2019_02_14_132323_create_banned_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2018_08_08_100000_create_telescope_entries_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2019_03_05_181556_create_audits_table',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2019_03_10_115333_create_export_lite_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2019_03_10_122508_create_export_data_table',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2017_08_24_000000_create_settings_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2019_03_13_185017_create_admin_log_operation_table',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2019_03_14_094533_create_blacklist_order_table',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2019_03_24_111951_create_advantage_table',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2019_03_27_140256_create_failed_jobs_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2019_03_29_093039_add_is_delete_to_reserve_request',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2019_03_29_095945_create_requisites_group_table',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2019_03_29_102747_add_id_group_to_requisites',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2019_03_30_064705_add_is_delete_to_payments',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2019_03_31_063131_add_column_to_requisites',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2019_03_31_091521_add_column_id_check_pay_to_currencies',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2019_03_31_141711_add_column_field_type_currency_fields',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2019_03_31_180948_add_column_month_limit_to_currency',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2019_04_01_183150_add_column_limit_week_to_requisites',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2019_04_03_214916_add_column_ip_changed_to_users',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2019_04_04_203154_add_column_role_expired_at_to_users',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2019_04_05_074739_create_password_histories_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2019_04_07_061554_rename_column_random_to_requisites',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2019_04_07_070407_add_column_history_at_to_requisites',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (241,'2019_04_07_131424_add_columns_to_generator_currency',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (242,'2019_04_07_192046_delete_column_account_number_field_to_requisites',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (243,'2019_04_07_192134_add_column_account_number_field_to_currencies',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (245,'2019_04_08_082718_add_columns_week_limit_to_currencies',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (246,'2019_04_08_090710_add_column_limit_views_to_requisites',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (247,'2019_04_16_205714_add_column_is_main_to_direction_exchange',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (248,'2019_04_18_144745_add_column_notice_in_to_currencies',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (249,'2019_04_28_195032_add_column_transfer_percent_reserve_to_currencies',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (250,'2019_04_28_195530_add_column_transfer_amount_reserve_to_currencies',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (251,'2019_04_30_161321_add_columns_commission_s_to_direction_exchange',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (252,'2019_05_02_194739_add_column_is_follow_referral_to_users',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (254,'2019_05_03_061553_add_column_allows_exports_to_direction_exchange',76);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (255,'2019_05_09_110548_add_comment_to_event_reserve',77);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (256,'2019_05_10_062257_add_column_remove_spaces_requisite_to_currencies',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (257,'2019_05_10_064441_add_column_payout_commission_to_currencies',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (258,'2019_05_10_070414_add_column_name_alt_to_payments',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (259,'2019_05_10_074607_add_column_sorting_reserve_to_currencies',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (261,'2019_05_11_074239_add_column_old_ip_address_to_user_auth',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (262,'2019_05_11_101834_add_column_number_format_to_generator_currency',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (263,'2019_05_11_102914_add_column_type_number_format_to_generator_currency',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (264,'2019_05_11_110733_add_column_operator_started_at_to_tasks',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (265,'2019_05_12_152911_add_column_blockchain_url_to_payments',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (266,'2019_05_12_190658_create_reserve_group_table',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (267,'2019_05_12_192008_add_column_id_group_to_reserves',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (268,'2019_05_13_131939_add_column_sorting_to_reserves',90);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (269,'2019_05_14_054521_add_column_deleted_at_to_tasks_info',91);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (270,'2019_05_14_054543_add_column_deleted_at_to_tasks_rates_data',91);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (271,'2019_05_14_054556_add_column_deleted_at_to_tasks_status_log',91);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (272,'2019_05_14_054723_add_column_deleted_at_to_transactions',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (273,'2019_05_14_054923_add_column_deleted_at_to_history_recalculation',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (274,'2019_05_14_054947_add_column_deleted_at_to_wallets_history',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (275,'2019_05_14_055011_add_column_deleted_at_to_tasks_convert_log',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (276,'2019_05_14_055048_add_column_deleted_at_to_history_payment_transactions',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (277,'2019_05_14_055138_add_column_deleted_at_to_history_excode',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (278,'2019_05_14_055205_add_column_deleted_at_to_reward_log',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (279,'2019_05_14_055227_add_column_deleted_at_to_wallet_transactions',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (280,'2019_05_14_085623_add_column_is_server_tx_merchant_to_currencies',97);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (282,'2019_05_14_091823_create_payment_explorer_table',98);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (283,'2019_05_14_144610_create_requisites_blacklist_table',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (284,'2019_05_14_145001_add_column_text_to_requisites_blacklist',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (285,'2019_05_14_165043_add_column_first_char_alt_to_currencies',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (286,'2019_05_14_180651_add_column_task_cancel_to_unpaid_items',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (287,'2019_05_14_181335_add_column_allow_delete_to_tasks_status',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (289,'2019_05_14_181724_custom_data_to_unpaid_items',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (290,'2019_05_14_204856_create_tasks_chat_table',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (291,'2019_05_14_213844_create_tasks_rejection_status_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (292,'2019_05_14_215838_add_column_rejection_status_to_tasks',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (293,'2019_05_14_222437_add_column_id_rejection_status_to_tasks',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (294,'2019_05_15_062052_create_pending_order_status',109);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (295,'2019_05_15_062212_add_column_id_pending_status_to_tasks',110);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (296,'2019_05_15_213011_add_column_is_not_delete_to_tasks_rejection_status',111);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (297,'2019_05_15_220025_add_column_is_not_delete_to_pending_order_status',112);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (298,'2019_05_16_112155_add_column_name_alt_to_currency_fields',113);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (299,'2019_05_11_073216_add_columns_in_out_price_to_task_status_log',114);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (300,'2019_05_17_082400_add_column_link_to_notices_exchange',115);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (302,'2019_05_17_150206_create_task_reports_table',116);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (303,'2019_05_17_205144_add_column_profit_usd_to_task_reports',117);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (304,'2019_05_17_210852_add_column_profit_s_to_direction_exchange',118);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (305,'2019_05_17_215300_add_column_profit_s_to_task_reports',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (306,'2019_05_18_092848_add_column_profit_rub_to_task_reports',120);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (307,'2019_05_18_095741_add_column_is_bestchange_to_task_reports',121);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (308,'2019_05_18_170351_create_task_log_confirmation_table',122);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (309,'2019_05_18_183933_add_column_sorting_to_notices_exchange',123);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (310,'2019_05_18_191752_add_column_sorting_to_advantage',124);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (311,'2019_05_18_192822_create_parser_exchange_log_table',125);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (312,'2019_05_18_203740_add_column_sorting_to_partners',126);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (313,'2019_05_18_204823_create_referral_statistics_table',127);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (314,'2019_05_18_220157_add_column_ip_to_referral_statistics',128);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (315,'2019_05_18_220222_add_column_id_user_to_referral_statistics',128);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (316,'2019_05_19_083508_add_column_percent_to_reward_log',129);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (317,'2019_05_19_084304_add_column_is_cashback_to_reward_log',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (318,'2019_05_19_175500_add_column_is_favorites_to_tasks',131);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (319,'2019_05_19_191417_add_column_is_spam_to_tasks',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (320,'2019_05_20_075350_create_main_event_logs_table',133);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (321,'2019_05_20_082651_add_column_ip_to_main_event_logs',134);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (322,'2019_05_20_083346_add_column_id_admin_to_main_event_logs',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (323,'2019_05_21_055132_add_column_name_alt_to_menu',136);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (324,'2019_05_22_200043_create_links_reviews_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (325,'2019_05_23_072005_add_column_hash_id_to_archive_reports',138);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (326,'2019_05_23_081635_create_bestchange_rates_log',139);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (327,'2019_05_23_090604_create_admin_auth_log_table',140);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (328,'2019_05_23_090942_add_column_is_successful_to_admin_auth_log',141);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (329,'2019_05_23_152149_add_column_place_change_to_tasks_status_log',142);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (330,'2019_05_23_174344_add_columns_to_code_currencies',143);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (331,'2019_05_24_060006_create_direction_exchange_group_table',144);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (332,'2019_05_24_061343_add_column_id_group_direction_to_direction_exchange',145);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (333,'2019_05_24_063629_add_column_is_restrict_editing_to_direction_exchange',146);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (334,'2019_05_24_072029_create_reserve_alerts_table',147);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (336,'2019_05_24_072512_add_column_count_alert_to_reserves_alerts',148);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (337,'2019_05_27_091858_add_column_language_field_to_currency_fields',149);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (338,'2019_05_27_101040_add_3_columns_to_direction_exchange',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (339,'2019_05_27_111922_create_whitelist_order_table',151);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (340,'2019_05_28_162141_add_3_columns_to_notices_exchange',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (341,'2019_05_28_164142_add_column_text_alt_to_notices_exchange',153);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (342,'2019_05_28_164951_add_column_comment_to_requisites',154);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (343,'2019_05_28_193114_create_users_history_profiles_table',155);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (344,'2019_05_28_195845_add_columns_to_history_profiles',156);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (345,'2019_05_29_075545_add_column_verified_at_to_withdrawal_request',157);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (346,'2019_05_29_081630_add_column_tx_id_to_withdrawal_request',158);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (350,'2019_05_29_110938_create_log_merchants_table',159);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (351,'2019_05_29_193757_add_column_status_to_log_merchants',159);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (352,'2019_05_29_200326_add_column_event_to_log_merchants',159);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (353,'2019_05_29_213216_add_column_provider_to_log_merchants',160);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (354,'2019_05_30_193419_add_column_is_archive_to_currencies',161);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (355,'2019_05_31_060815_create_currencies_groups_table',162);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (356,'2019_05_31_060909_add_column_id_group_to_currencies',163);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (358,'2019_06_03_113703_create_log_autopayments_table',165);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (359,'2019_06_02_134815_create_currencies_autopayment_table',166);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (360,'2019_06_05_172833_add_column_is_check_pay_cron_to_currencies',167);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (361,'2019_06_05_202326_add_column_is_bot_to_tasks',168);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (362,'2019_06_06_102107_create_log_check_pay_table',169);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (363,'2019_06_06_102412_add_column_provider_to_log_check_pay',170);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (364,'2019_06_06_155312_add_column_register_tx_to_tasks',171);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (365,'2019_06_11_065537_add_column_hidden_export_label_param_to_direction_exchange',172);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (366,'2019_06_13_185842_add_column_custom_field_comment_to_requisites',173);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (367,'2019_06_16_074529_add_column_alias_to_group_parser_exchange',174);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (368,'2019_06_16_144006_add_column_to_group_parser_exchange',175);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (369,'2019_06_16_153942_add_column_provider_to_log_autopayments',176);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (370,'2019_06_18_065040_add_column_merchant_incomplete_payment_to_tasks',177);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (371,'2019_06_18_174451_add_column_is_paid_to_currencies_autopayment',178);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (372,'2019_06_18_194027_add_column_max_amount_day_to_currencies_autopayment',179);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (373,'2019_06_19_070539_add_column_merchant_overpayment_to_tasks',180);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (374,'2019_06_21_190023_change_type_export_label_param_to_direction_exchange',181);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (375,'2019_06_22_071432_add_column_is_notify_login_to_users',182);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (376,'2019_06_22_072058_add_column_is_password_reset_to_users',183);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (377,'2019_06_22_072826_add_column_user_agent_to_users',183);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (378,'2019_06_22_150801_add_column_is_pay_referral_to_users',184);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (379,'2019_06_22_152844_add_column_is_pay_cashback_to_users',185);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (380,'2019_06_22_185817_add_column_is_archive_to_referral_statistics',186);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (381,'2019_06_22_212623_add_column_user_agent_to_referral_statistics',187);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (386,'2019_06_23_122500_create_direction_notification_table',188);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (387,'2019_06_25_090925_add_columns_to_direction_notification',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (388,'2019_06_26_055618_add_column_sort_by_to_bestchange_rates',190);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (389,'2019_06_30_055117_add_columns_to_tasks_info',191);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (390,'2019_06_30_061024_add_column_to_referral_statistics',192);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (391,'2019_06_30_065309_add_column_to_favorites',193);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (392,'2019_07_04_113722_add_columns_to_direction_exchange',194);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (393,'2019_07_04_114003_add_columns_to_direction_exchange',195);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (394,'2019_07_04_220500_add_column_to_currencies',196);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (395,'2019_07_04_221526_add_column_to_tasks',196);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (396,'2019_07_05_195018_add_column_to_direction_exchange',197);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (397,'2019_07_07_121431_add_column_to_wallets_addresses',198);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (398,'2019_07_07_202917_add_column_in_flow_funds_to_tasks',199);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (399,'2019_07_08_061505_add_column_is_qrcode_amount_to_currencies',200);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (400,'2019_07_08_082205_add_column_in_min_amount_to_tasks_info',201);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (401,'2019_07_08_082424_add_columns_in_max_amount_to_tasks_info',202);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (402,'2019_07_08_094656_add_column_is_failed_send_to_wallets_addresses',203);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (403,'2019_07_08_192231_create_verification_card__table',204);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (404,'2019_07_08_193753_add_column_to_currencies',205);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (405,'2019_07_08_211204_add_column_hash_id_to_verification_card',206);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (406,'2019_07_08_211758_add_column_id_currency_to_verification_card',207);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (407,'2019_07_08_213531_add_column_name_to_verification_card',208);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (408,'2019_07_08_213810_add_column_status_to_verification_card',209);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (409,'2019_07_09_084510_add_column_to_verification_card',210);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (410,'2019_07_09_120829_add_column_to_currencies',211);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (411,'2019_07_11_101130_change_type_notice_in_to_currencies',212);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (412,'2019_07_12_054646_add_column_card_number_string_to_verification_card',213);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (413,'2019_07_12_083524_add_column_hold_in_hours_to_currencies',214);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (414,'2019_07_16_120455_add_column_is_verification_to_users',215);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (415,'2019_07_16_160402_add_column_is_user_verification_to_currencies',216);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (416,'2019_07_19_080537_add_columns_who_pays_commission_to_direction_exchange',217);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (417,'2019_07_19_085052_add_column_commission_merchant_to_currencies',218);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (418,'2019_07_19_091318_change_type_commission_merchant_percent_to_currencies',219);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (419,'2019_07_21_143130_add_column_is_allow_split_to_code_currency',220);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (420,'2019_07_23_084152_add_column_token_value_to_code_currency',221);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (421,'2019_07_23_210927_add_column_is_send_fee_network_to_currencies',222);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (422,'2019_07_23_223251_add_column_fee_sent_to_wallets_addresses',223);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (423,'2019_07_25_080443_add_column_fee_no_verified_merchant_to_currencies',224);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (424,'2019_07_25_134305_add_column_class_name_to_merchants',225);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (425,'2019_07_25_205538_add_column_summa_not_fee_to_reserve_log',226);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (426,'2019_07_25_205735_add_column_summa_with_fee_to_reserve_log',226);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (427,'2019_07_25_213852_add_column_next_checkout_at_to_tasks',226);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (428,'2019_07_28_091432_add_column_hold_delay_to_currencies',227);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (429,'2019_08_28_113735_add_column_income_outcome_to_tasks',228);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (430,'2019_08_31_063128_add_column_provider_to_wallets_addresses',229);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (431,'2019_08_31_073249_add_column_custom_field_prefix_to_requisites',230);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (432,'2019_08_31_083246_create_requirements_table',231);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (433,'2019_09_01_191859_create_logs_404_table',232);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (434,'2019_09_01_192106_add_column_message_to_logs_404',233);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (435,'2019_09_02_105516_create_merchant_account_table',234);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (436,'2019_09_04_101305_add_column_provider_to_merchant_account',235);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (437,'2019_09_05_162508_create_qiwi_currencies_table',236);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (438,'2019_09_06_224531_add_column_is_enabled_merchant_to_requisites',237);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (439,'2019_09_07_111925_create_yandex_currencies_table',238);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (440,'2019_09_11_133856_add_column_custom_field_comment_alt_to_requisites',239);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (441,'2019_09_13_085214_add_column_is_marquee_to_notices_exchange',240);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (442,'2019_09_14_193809_add_column_num_auth_to_users',241);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (443,'2019_09_16_221532_create_autosender_payment_table',242);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (444,'2019_09_17_092625_add_column_double_withdrawal_to_tasks',243);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (445,'2019_10_02_083733_add_column_telegram_to_users',244);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (446,'2019_10_03_073341_add_column_id_currency_to_reserve_request',245);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (447,'2019_10_05_075529_create_currencies_commands',246);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (448,'2019_10_05_081343_add_column_id_currency_to_currencies_commands',247);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (449,'2019_10_05_213258_add_column_description_to_currency_fields',248);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (450,'2019_10_05_232438_add_column_phone_to_tasks',249);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (451,'2019_10_06_105509_add_column_payout_commission_amount_to_currencies',250);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (452,'2019_10_06_194016_add_columns_is_review_icon_to_links_reviews',251);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (453,'2019_10_06_194959_add_column_description_to_links_reviews',252);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (454,'2019_10_08_195259_create_links_review_groups_table',253);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (455,'2019_10_08_200227_add_column_id_group_to_links_reviews',254);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (456,'2019_10_08_230139_add_column_id_auto_reserve_to_currencies',255);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (457,'2019_10_10_195911_create_proxies_qiwi_table',256);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (458,'2019_10_10_203828_add_column_id_proxy_to_requisites',257);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (459,'2019_10_10_220151_create_proxies_payment_table',258);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (460,'2019_10_11_113204_add_column_drains_to_requisites',259);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (461,'2019_10_11_124513_create_log_drain_table',260);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (462,'2019_10_11_124800_add_column_from_value_to_log_drain',261);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (463,'2019_10_11_125018_add_column_id_task_to_log_drain',262);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (464,'2019_10_11_184842_add_column_freeze_scam_to_tasks_info',263);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (465,'2019_10_12_214638_create_live_notification_table',264);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (466,'2019_10_13_075207_add_column_id_client_to_tasks_chat',265);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (467,'2019_10_13_094256_add_column_is_drain_mertchant_to_tasks',266);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (468,'2019_10_13_210128_add_column_big_id_to_withdrawal_request',266);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (469,'2019_10_17_130759_add_column_user_agent_to_proxies_payment',267);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (470,'2019_10_18_193613_create_competitor_links_table',268);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (471,'2019_10_18_193712_create_competitor_rates_table',269);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (472,'2019_10_18_220118_add_column_number_format_to_competitor_rates',270);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (473,'2019_10_18_222659_add_column_id_competitor_to_direction_exchange',271);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (474,'2019_10_18_223449_add_column_enable_competitors_to_direction_exchange',272);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (475,'2019_10_19_071753_create_competitor_rates_log_table',273);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (476,'2019_10_19_084447_add_column_exchange_in_out_to_competitor_rates',274);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (477,'2019_10_19_155051_add_column_sorting_to_faq_category',275);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (478,'2019_10_19_165008_add_column_status_to_faq',276);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (479,'2019_10_19_165139_add_column_sorting_to_faq',277);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (480,'2019_10_19_203855_create_reviews_table',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (481,'2019_10_20_063552_add_column_user_agent_to_reviews',279);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (482,'2019_10_20_063639_add_column_id_admin_to_reviews',280);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (483,'2019_10_20_100535_create_selected_courses_table',281);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (484,'2019_10_20_133254_add_column_number_format_to_selected_courses',282);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (485,'2019_10_20_183151_add_column_provider_to_users',283);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (486,'2019_10_21_070312_create_social_auth_system_table',284);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (487,'2019_10_21_190046_change_type_to_blacklist_order',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (488,'2019_10_21_195444_add_column_is_bestchange_to_blacklist_order',286);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (489,'2019_10_21_211845_create_contacts_table',287);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (490,'2019_10_21_212111_add_column_sorting_to_contacts',288);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (491,'2019_10_21_212247_add_column_is_home_to_contacts',289);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (492,'2019_10_21_214327_change_type_to_contacts',290);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (493,'2019_10_23_195411_create_backup_codes_table',291);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (494,'2019_10_23_202738_add_column_num_to_backup_codes',292);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (495,'2019_10_23_214323_add_column_is_download_codes_to_users',293);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (496,'2019_10_24_075039_add_column_backup_code_secret_to_users',294);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (497,'2019_10_24_111024_create_social_reviews_table',295);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (498,'2019_10_24_124356_change_type_type_to_social_reviews',296);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (499,'2019_10_25_062143_create_collaboration_pr_table',297);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (500,'2019_10_25_065708_add_column_is_button_to_collaboration_pr',298);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (501,'2019_10_25_065724_add_column_is_button_to_contacts',298);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (502,'2019_10_26_074248_create_info_statistics_table',299);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (503,'2019_10_26_081610_change_type_to_info_statistics_table',300);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (504,'2019_10_26_185348_add_column_accoutn_number_to_info_statistics',301);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (505,'2019_10_26_204735_add_columns_to_users',302);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (506,'2019_10_27_070853_add_column_link_to_info_statistics',303);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (507,'2019_10_28_215528_add_columns_courses_in_out_to_direction_exchange',304);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (508,'2019_10_28_220117_create_course_logs_table',305);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (509,'2019_10_29_064341_add_column_bc_min_max_to_direction_exchange',306);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (511,'2019_10_29_085617_add_columns_bc_id_new_rate_to_direction_exchange',307);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (512,'2019_10_29_113749_add_column_bc_add_course_direction_exchange',308);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (513,'2019_10_29_181144_add_column_napsip_to_direction_exchange',309);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (514,'2019_10_29_195839_add_column_not_ip_to_direction_exchange',310);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (515,'2019_10_30_124856_add_column_cr_max_min_to_direction_exchange',311);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (516,'2019_10_30_142730_add_column_cr_fields_to_direction_exchange',312);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (517,'2019_10_30_150046_add_column_max_percent_partner_to_direction_exchange',313);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (518,'2019_10_30_190314_add_column_xml_juridical_to_direction_exchange',314);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (519,'2019_10_30_195538_add_column_languages_to_direction_exchange',315);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (520,'2019_10_30_205437_add_column_is_hidden_noauth_user_to_direction_exchange',316);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (521,'2019_10_30_220421_add_column_is_hidden_not_locale_to_direction_exchange',317);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (522,'2019_10_30_222414_add_column_sorting_tariffs_to_direction_exchange',318);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (523,'2019_10_30_223658_add_column_sorting_tariffs_to_currencies',319);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (524,'2019_10_31_162940_add_column_pay_num_to_tasks',320);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (525,'2019_10_31_203032_add_column_type_to_reviews',321);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (526,'2019_10_31_212033_add_column_device_to_direction_exchange',322);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (527,'2019_10_31_213532_add_column_is_hidden_not_device_to_direction_exchange',323);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (528,'2019_11_01_221959_add_column_is_autopay_off_tasks',324);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (529,'2019_11_03_165336_create_tasks_shots_table',325);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (530,'2019_11_05_062706_add_column_bc_new_commission_to_direction_exchange',326);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (531,'2019_11_06_202434_create_currencies_notification_table',327);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (532,'2019_11_10_081729_add_column_is_other_service_to_merchants',328);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (533,'2019_11_10_082410_add_column_service_name_to_wallets_addresses',329);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (534,'2019_11_10_111305_create_task_private_hash_table',330);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (535,'2019_11_10_203944_add_column_min_out_amount_verification_to_currencies',331);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (536,'2019_11_10_224006_create_logs_email_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (537,'2019_11_11_105817_add_column_is_out_enabled_verification_to_currencies',333);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (538,'2019_11_12_070443_add_column_bestchange_bl_to_direction_exchange',334);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (539,'2019_11_12_121035_add_column_bestchange_step_to_direction_exchange',335);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (540,'2019_11_12_131907_add_column_bc_enable_your_to_direction_exchange',336);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (541,'2019_11_12_180452_add_column_limit_min_course_to_direction_exchange',337);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (542,'2019_11_12_192745_add_column_bestchange_range_to_direction_exchange',338);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (543,'2019_11_12_201931_add_column_other_comm_amount_to_direction_exchange',339);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (544,'2019_11_13_064424_add_column_max_amount_month_to_currencies_autopayment',340);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (545,'2019_11_13_075352_add_column_delay_to_currencies_autopayment',341);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (546,'2019_11_14_073553_add_column_autodel_status_taks_to_direction_exchange',342);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (547,'2019_11_14_120030_add_column_is_vip_client_to_users',343);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (548,'2019_11_16_082626_add_column_bestchange_max_reserve_to_direction_exchange',344);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (549,'2019_11_16_181746_create_your_exchange_group_table',345);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (550,'2019_11_16_182002_add_column_id_group_to_your_exchange',346);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (551,'2019_11_17_082413_create_bestchange_data_log_table',347);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (552,'2019_11_17_184248_create_e_voucher_codes_table',348);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (553,'2019_11_17_213529_create_currencies_log_table',349);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (554,'2019_11_19_000627_add_column_is_hidden_tariffs_to_direction_exchange',350);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (555,'2019_11_19_093737_add_column_is_holding_direction_to_direction_exchange',351);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (556,'2019_11_19_105458_add_column_is_automatic_to_bestchange_rates',352);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (557,'2019_11_20_081620_add_column_bestchange_range_to_to_direction_exchange',353);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (558,'2019_11_20_085624_add_column_rl_id_your_exchange_to_direction_exchange',354);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (559,'2019_11_24_103259_add_column_reserve_limit_to_direction_exchange',355);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (560,'2019_11_24_115016_add_column_is_email_verification_to_direction_exchange',356);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (561,'2019_11_24_121027_add_column_number_transaction_to_direction_exchange',357);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (562,'2019_11_24_121742_add_column_num_transaction_to_tasks_info',358);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (563,'2019_11_24_163045_add_column_other_limit_to_direction_exchange',359);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (564,'2019_11_24_191521_add_column_auto_del_order_day_to_direction_exchange',360);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (565,'2019_11_24_213620_create_referrals_info_logs',361);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (566,'2019_12_01_170534_add_column_currency_sign_payout_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (567,'2019_12_05_171739_add_column_text_to_payment_explorer',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (568,'2019_12_06_072806_add_column_max_limit_in_reserve_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (569,'2019_12_06_091255_change_type_bestchange_step_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (570,'2019_12_06_142239_add_column_code_base_to_code_currency',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (571,'2019_12_07_092033_add_column_user_agent_to_user_auth',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (572,'2019_12_07_234636_add_column_token_decimal_to_code_currency',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (573,'2019_12_07_234901_change_type_token_decimal_to_code_currency',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (574,'2019_12_12_073739_add_column_merchant_provider_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (575,'2019_12_14_000001_create_personal_access_tokens_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (576,'2019_12_16_220833_add_column_income_outcome_5_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (577,'2020_01_02_120627_create_parser_api_keys_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (578,'2020_01_02_170107_create_gateways_merchants_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (579,'2020_01_02_231912_create_gateways_payments_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (580,'2020_01_03_170911_add_column_to_security_hash_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (581,'2020_01_04_003546_add_column_is_check_from_shot_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (582,'2020_01_04_113745_add_column_comment_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (583,'2020_01_04_161024_add_column_is_deny_ip_address_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (584,'2020_01_04_171400_create_file_parser_groups_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (585,'2020_01_04_172628_create_file_parser_rates_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (586,'2020_01_04_183340_add_column_id_file_parser_rate_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (587,'2020_01_04_211928_add_column_hour_limit_order_tocurrencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (588,'2020_01_04_232646_add_column_referral_link_id_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (589,'2020_01_05_081557_create_bestchange_currencies_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (590,'2020_01_05_095359_create_requisites_fields_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (591,'2020_01_06_221954_add_column_profit_percent_reserve_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (592,'2020_01_06_222820_create_reserve_log_profit_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (593,'2020_01_07_122646_create_operation_level_groups_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (594,'2020_01_07_155257_create_operation_levels_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (595,'2020_01_09_160914_add_column_is_not_pair_to_bestchange_rates',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (596,'2020_01_09_173024_add_column_id_bs_alt_parser_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (597,'2020_01_09_192018_add_column_bs_alt_parser_course_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (598,'2020_01_09_193144_is_enable_alt_bs_parser_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (599,'2020_01_09_205731_create_bestchange_parser_error_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (600,'2020_01_09_210039_add_column_id_direction_exchange_to_bestchange_parser_error',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (601,'2020_01_10_183324_add_column_is_disable_bs_error_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (602,'2020_01_11_083922_change_type_amount_2_to_reserve_log_profit',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (603,'2020_01_11_092711_add_column_amount_usd_to_reserve_log_profit',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (604,'2020_01_13_183703_create_tasks_fields_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (605,'2020_01_13_230315_add_column_type_field_to_tasks_fields',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (606,'2020_01_13_235114_create_currency_fields_relationships_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (607,'2020_01_14_000438_add_column_type_field_to_currency_fields_relationships',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (608,'2020_01_14_100900_remove_column_outcome_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (609,'2020_01_14_102253_add_column_view_to_parser_api_keys',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (610,'2020_01_14_112109_create_direction_exchange_error_log_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (611,'2020_01_14_114116_add_column_level_risk_to_direction_exchanger_error_log',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (612,'2020_01_15_092957_create_user_balance_log_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (613,'2020_01_15_093404_add_column_route_type_to_user_balance_log',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (614,'2020_01_17_073911_change_type_name_to_menu',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (615,'2020_01_18_102305_add_column_is_new_user_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (616,'2020_01_27_212657_create_task_card_details_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (617,'2020_01_27_225607_add_column_phone_and_url_to_task_card_details',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (618,'2020_01_27_234335_add_column_is_card_detail_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (619,'2020_01_29_001109_add_column_max_register_blockchain_to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (620,'2020_01_31_134240_add_column_max_display_reserve_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (621,'2020_01_31_222803_add_columns_is_black_list_to_withdrawal_request',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (622,'2020_02_03_211632_add_column_notify_statusss_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (623,'2020_02_05_091258_change_type_page_title_to_pages',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (624,'2020_02_05_223942_create_affiliate_settings_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (625,'2020_02_05_232146_add_column_profit_partner_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (626,'2020_02_06_223239_add_column_is_backup_to_users',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (627,'2020_02_15_002103_add_column_min_confirm_to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (628,'2020_02_17_222606_create_update_systems_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (629,'2020_02_18_230710_change_type_column_page_content_to_pages',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (630,'2020_02_23_210640_add_column_id_proxy_to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (631,'2020_02_24_095954_create_debtors_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (632,'2020_02_24_101821_add_column_is_freeze_local_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (633,'2020_02_26_120552_create_admin_desktops_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (634,'2020_02_26_124451_create_admin_desktop_gadgets_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (635,'2020_02_27_011033_add_column_column_id_to_admin_desktop_gadgets',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (636,'2020_02_27_074123_add_column_id_user_to_admin_desktop_gadgets',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (637,'2020_02_27_080153_add_column_hash_id_to_admin_desktop_gadgets',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (638,'2020_02_27_114938_add_columns_flex_nums_to_admin_gadgets',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (639,'2020_03_01_192450_add_column_notes_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (640,'2020_03_01_194627_add_column_note_tx_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (641,'2020_03_03_180055_create_merchant_transaction_ids_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (642,'2020_03_07_172020_create_favorites_links_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (643,'2020_03_07_202634_add_column_id_user_to_favorites_links',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (644,'2020_03_08_095558_create_internal_accounts_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (645,'2020_03_08_095858_add_column_balance_to_internal_accounts',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (646,'2020_03_08_154439_create_history_internal_accounts_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (647,'2020_03_11_182041_add_column_x19_mode_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (648,'2020_03_11_194019_create_directions_fields_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (649,'2020_03_11_200306_create_direction_fields_relationships_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (650,'2020_03_12_104211_create_tasks_direction_fields_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (651,'2020_03_12_210045_add_column_is_email_verification_modal_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (652,'2020_03_12_214910_add_column_is_merchant_fround_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (653,'2020_03_14_092804_add_column_is_email_verification_modal_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (654,'2020_03_14_102453_add_column_type_finished_order_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (655,'2020_03_21_232437_add_column_commission_merchant_currency_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (656,'2020_03_21_234926_add_column_is_pay_commission_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (657,'2020_03_22_081433_add_column_unique_security_code_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (658,'2020_03_28_094420_add_column_comment_to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (659,'2020_03_31_001848_add_column_is_in_banner_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (660,'2020_04_07_110002_create_currencies_analytics_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (661,'2020_04_07_110248_add_column_id_currency_to_currencies_analytics',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (662,'2020_04_07_122326_add_column_in_out_orders_to_currencies_analytics',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (663,'2020_04_07_173238_add_column_is_blank_to_notices_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (664,'2020_04_07_221109_add_column_views_to_news',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (665,'2020_04_08_213847_add_column_kunacode_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (666,'2020_04_08_214158_add_column_provider_id_to_history_excode',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (667,'2020_04_10_002908_change_type_logo_to_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (668,'2020_04_18_001701_add_column_source_name_to_bestchange_rates',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (669,'2020_04_26_182637_add_column_flex_num3_to_admin_desktops',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (670,'2020_04_26_183048_add_column_flex_nums_to_admin_desktops',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (671,'2020_05_04_161332_create_log_error_merchants_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (672,'2020_05_05_162328_add_column_is_auto_check_modal_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (673,'2020_05_05_162415_add_column_is_autopay_modal_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (674,'2020_05_05_232250_change_type_custom_field_comment_to_requisites',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (675,'2020_05_08_163228_add_column_is_autopay_limit_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (676,'2020_05_30_214628_add_column_id_edit_data_manager_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (677,'2020_06_02_164512_add_column_is_report_referral_data_to_users',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (678,'2020_06_08_2227091_create_hosts_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (679,'2020_06_08_2227092_create_checks_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (680,'2020_06_13_174912_create_cashback_error_log_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (681,'2020_07_02_115432_add_column_commission_payment_currency_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (682,'2020_07_05_213001_add_column_is_fixed_reserve_to_reserves',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (683,'2020_08_14_194403_change_type_add_course1_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (684,'2020_08_30_123059_add_column_text_to_cashback_error_log',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (685,'2020_09_04_232155_add_column_telegram_id_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (686,'2020_09_05_101950_add_column_telegram_id_to_reviews',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (687,'2020_09_07_222402_add_columns_recount_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (688,'2020_09_07_224945_add_column_max_amount_newbie_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (689,'2020_09_08_102627_add_column_is_star_to_reserves',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (690,'2020_09_15_084624_add_column_notice_out_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (691,'2020_09_15_085436_add_column_out_price_fee_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (692,'2020_09_16_220221_add_column_is_ban_order_data_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (693,'2020_09_18_100221_create_admin_filters_user_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (694,'2020_09_18_130244_create_admin_filter_header_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (695,'2020_09_19_075222_add_column_is_common_to_admin_filter_header',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (696,'2020_09_21_002100_create_iex_config_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (697,'2020_09_22_102116_add_column_memo_id_to_wallets_addresses',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (698,'2020_09_25_090107_create_email_templates_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (699,'2020_09_25_172606_create_template_type_events_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (700,'2020_09_26_000528_add_column_id_event_type_to_email_templates',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (701,'2020_09_26_141435_add_column_email_to_to_email_templates',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (702,'2020_09_26_142438_add_column_template_to_email_templates',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (703,'2020_10_02_142238_create_tasks_history_operators_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (704,'2020_10_02_142842_add_column_id_from_manager_to_tasks_history_operators',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (705,'2020_10_02_145453_add_column_id_main_operator_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (706,'2020_10_02_150923_add_column_count_change_operator_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (707,'2020_10_16_102546_add_column_uuid_to_failed_jobs',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (708,'2020_10_31_184026_create_tasks_comments_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (709,'2020_11_06_004142_add_column_day_limit_merchant_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (710,'2020_11_06_010242_add_column_amount_fault_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (711,'2020_11_06_092038_add_column_day_limit_amount_merchant_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (712,'2020_11_06_100036_add_column_manual_pay_order_to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (713,'2020_11_08_080511_add_column_is_random_view_shot_to_currencies_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (714,'2020_11_09_104143_add_column_is_enable_merchant_button_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (715,'2020_11_28_105312_add_columns_recount_unique_tome_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (716,'2020_11_29_101150_create_tasks_profits_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (717,'2020_12_05_105459_add_column_desc_exchange_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (718,'2021_01_14_083737_add_column_provider_to_oauth_clients',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (719,'2021_02_09_091620_add_columns_in_out_amount_usd_to_currencies_analytics',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (720,'2021_02_16_122100_add_column_rate_speed_to_reviews',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (721,'2021_02_17_095346_add_column_id_widget_to_admin_desktop_gadgets',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (722,'2021_03_02_084714_add_column_slug_name_to_news',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (723,'2021_03_02_210603_add_column_pay_adapter_code_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (724,'2021_03_08_142215_create_user_wallet_historicals_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (725,'2021_04_25_171050_add_columns_aml_in_out_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (726,'2021_04_25_191120_create_amlbot_histories',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (727,'2021_04_25_200701_add_columns_is_amlbot_in_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (728,'2021_04_28_124333_add_column_view_balance_reward_to_withdrawal_request',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (729,'2021_06_01_100957_create_contests_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (730,'2021_06_01_125405_create_contests_conditions_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (731,'2021_06_01_181730_create_contests_users_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (732,'2021_06_01_211943_add_percent_to_contests_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (733,'2021_06_03_122006_add_column_priority_to_group_parser_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (734,'2021_06_07_103159_create_gateways_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (735,'2021_06_27_105740_add_column_method_pay_to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (736,'2021_06_28_002227_add_column_method_pay_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (737,'2021_07_03_192024_add_column_country_code_to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (738,'2021_07_04_000006_add_column_num_request_to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (739,'2021_07_04_101725_add_column_priority_fee_to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (740,'2021_07_07_104320_drop_column_generate_address_to_requisites',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (741,'2021_07_10_082548_add_column_fixed_fee_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (742,'2021_07_11_001308_add_column_auto_del_order_time_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (743,'2021_07_12_070857_add_column_course_value_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (744,'2021_07_13_200129_add_column_requisites_receive_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (745,'2021_07_13_213800_delete_columns_to_code_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (746,'2021_07_13_214314_add_column_is_trash_to_code_currency',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (747,'2021_07_13_223722_delete_status_to_filter_currency',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (748,'2021_07_13_230255_add_column_exchange_rate_status_to_your_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (749,'2021_07_14_004909_delete_columns_to_requisites',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (750,'2021_07_14_071602_add_columns_in_custom_fields_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (751,'2021_07_14_233658_add_columns_out_pay_min_amount_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (752,'2021_07_14_234017_delete_columns_many_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (753,'2021_07_15_150744_delete_id_group_menu_to_menu',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (754,'2021_07_15_175140_add_column_is_allow_amount_space_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (755,'2021_07_15_181426_delete_columns_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (756,'2021_07_15_185128_create_histories_codes_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (757,'2021_07_15_213223_change_column_excode_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (758,'2021_07_17_174209_add_column_type_order_to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (759,'2021_07_18_000752_add_column_is_auto_take_fee__to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (760,'2021_07_24_220048_add_column_ids_merchant_to_currency',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (761,'2021_07_25_005902_add_columns_id_merchant_id_pay_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (762,'2021_07_25_084440_add_column_ids_direction_fields_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (763,'2021_07_25_123106_add_column_comment_to_requisites_fields',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (764,'2021_07_25_132656_delete_columns_custom_fields_to_requisites',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (765,'2021_07_25_163327_change_type_field_to_tasks_fields',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (766,'2021_07_28_190209_add_column_is_pending_blockchain_hash_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (767,'2021_07_29_090054_add_column_volume_to_usd_to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (768,'2021_07_29_184435_change_column_is_disable_to_gateway_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (769,'2021_07_31_012559_delete_column_pos_to_bestchange_rates',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (770,'2021_08_04_011528_add_column_class_style_to_tasks_comments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (771,'2021_08_13_071224_drop_column_uri_to_referral_programs',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (772,'2021_08_22_130433_add_column_min_confirm_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (773,'2021_09_04_130132_create_merchants_has_currencies_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (774,'2021_09_04_145759_drop_column_ids_merchant_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (775,'2021_09_04_171559_add_column_total_usd_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (776,'2021_09_05_085104_add_column_is_config_done_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (777,'2021_09_06_075300_add_column_is_auto_check_pay_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (778,'2021_09_07_085521_add_column_internal_rate_to_code_currency',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (779,'2021_09_07_130058_add_column_add_to_course_to_code_currency',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (780,'2021_09_07_155558_add_column_is_import_to_code_currency',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (781,'2021_09_08_090411_create_directions_has_fields_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (782,'2021_09_08_091454_drop_column_ids_custom_fields_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (783,'2021_09_11_105749_change_column_id_to_currency_fields',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (784,'2021_09_11_105831_create_currency_in_has_fields_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (785,'2021_09_11_114232_create_currency_out_has_fields_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (786,'2021_09_11_115208_drop_column_in_custom_fields_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (787,'2021_09_11_222346_add_column_is_import_to_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (788,'2021_09_12_172714_add_column_security_options_to_gateways',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (789,'2021_09_13_070846_add_column_remove_spaces_to_currency_fields',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (790,'2021_09_13_084813_drop_column_name_alt_to_currency_fields',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (791,'2021_09_13_160819_drop_column_name_alt_to_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (792,'2021_09_15_071723_add_column_code_in_to_parser_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (793,'2021_09_15_133756_create_currency_requisites_has_fields_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (794,'2021_09_15_140354_change_type_name_to_requisites_fields',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (795,'2021_09_15_161724_add_column_description_to_directions_fields',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (796,'2021_10_01_143016_add_column_provider_url_to_group_parser_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (797,'2021_10_02_114558_add_column_is_not_update_to_parser_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (798,'2021_10_14_093357_add_column_button_name_to_contests',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (799,'2021_10_18_100350_change_type_columns_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (800,'2021_10_18_215714_add_column_is_merchant_fround_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (801,'2021_10_21_101818_add_column_has_status_to_code_currency',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (802,'2021_11_08_140312_add_column_is_bot_to_links_reviews',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (803,'2022_03_11_111846_create_requisites_has_fields_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (804,'2022_03_11_153017_create_getblockbot_histories_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (805,'2022_03_11_153055_add_columns_is_getblockbot_in_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (806,'2022_03_11_172809_add_columns_is_getblockbot_in_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (807,'2022_05_17_092959_create_cities_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (808,'2022_05_18_084918_create_directions_has_cities_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (809,'2022_05_18_121716_add_column_city_id_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (810,'2022_05_18_183458_add_column_provider_id_to_group_parser_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (811,'2022_05_18_183908_add_column_provider_id_to_parser_api_keys',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (812,'2022_05_29_114045_add_column_user_browser_to_users',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (813,'2022_06_06_095242_add_column_tech_name_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (814,'2022_06_11_145948_add_column_last_order_at_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (815,'2022_06_11_172644_add_column_user_id_to_pages',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (816,'2022_06_12_010900_add_column_order_queue_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (817,'2022_06_12_011104_add_column_is_mass_payouts_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (818,'2022_06_12_080157_add_column_id_payment_gateway_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (819,'2022_07_02_230733_add_column_desc_exchange_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (820,'2022_07_04_114312_create_requisites_info_fields_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (821,'2022_07_04_114546_create_requisites_has_info_fields_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (822,'2022_07_10_205445_create_contests_has_contests_users_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (823,'2022_07_11_064619_create_contests_faq_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (824,'2022_07_19_162323_create_partner_parser_groups',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (825,'2022_07_19_163645_create_partner_parser_rates_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (826,'2022_07_19_173233_add_column_partner_id_to_partner_parser_rates',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (827,'2022_07_19_210756_add_column_id_partner_parser_rate_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (828,'2022_07_19_212312_add_column_number_format_to_partner_parser_rates',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (829,'2022_07_20_084400_add_column_last_updated_at_to_partner_parser_rates',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (830,'2022_07_24_124615_add_column_created_user_id_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (831,'2022_07_25_095946_add_column_last_updated_at_to_group_parser_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (832,'2022_07_25_160635_add_column_proxy_id_to_group_parser_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (833,'2022_07_25_164949_add_column_last_imported_at_to_group_parser_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (834,'2022_07_26_151929_change_default_status_to_parser_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (835,'2022_07_28_111422_add_indexs_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (836,'2022_07_28_111429_add_indexs_to_user',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (837,'2022_07_28_111636_add_indexs_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (838,'2022_07_31_191308_add_column_section_to_admin_filters_user',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (839,'2022_07_31_191651_add_column_type_filter_to_admin_filter_header',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (840,'2022_08_10_083841_create_bs_bestchange_histories_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (841,'2022_08_14_150313_create_promo_codes_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (842,'2022_08_14_171451_add_column_id_promo_code_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (843,'2022_08_15_101132_add_column_is_fire_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (844,'2022_08_18_174915_change_typ_name_to_partners',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (845,'2022_08_18_225950_add_column_colors_to_contests',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (846,'2022_08_24_090001_change_column_title_to_faq',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (847,'2022_09_04_121407_change_type_name_value_to_contacts',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (848,'2022_09_13_131053_change_type_desc_link_reviews',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (849,'2022_09_14_151220_add_column_is_fixed_to_notices_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (850,'2022_09_16_170627_change_type_title_to_advantage',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (851,'2022_09_16_193955_create_direction_exchange_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (852,'2022_09_28_092836_add_column_name_to_promo_codes',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (853,'2022_10_02_142352_add_column_code_to_parser_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (854,'2022_10_02_144508_create_parser_formula_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (855,'2022_10_02_225346_add_column_id_parser_formula_rate_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (856,'2022_10_03_020154_create_parser_formula_coefficient_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (857,'2022_10_03_021536_add_column_is_coefficient_to_parser_formula_rates',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (858,'2022_10_03_090947_add_column_title_to_parser_formula_rates',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (859,'2022_10_06_113128_change_type_instructions_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (860,'2022_10_08_121119_create_currencies_networks_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (861,'2022_10_08_185825_create_directions_has_networks_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (862,'2022_10_10_102840_add_column_user_style_to_users',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (863,'2022_10_12_094811_add_columns_direction_to_promo_codes',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (864,'2022_10_13_112501_add_column_network_name_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (865,'2022_10_17_104249_change_type_first_char_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (866,'2022_10_18_111851_drop_column_first_char_alt_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (867,'2022_10_18_112916_rename_column_first_char_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (868,'2022_10_18_123205_add_column_field_comments_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (869,'2022_10_20_172612_add_columns_id_order_detail_to_direction_notification',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (870,'2022_12_10_180543_add_column_api_key_to_users',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (871,'2022_12_11_165318_add_column_tech_currency_name_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (872,'2022_12_20_192501_change_type_account_number_field_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (873,'2022_12_20_212121_add_column_button_create_order_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (874,'2022_12_21_073924_add_column_button_create_order_text_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (875,'2023_01_19_104847_add_column_tx_hash_to_getblockbot_histories',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (876,'2023_01_19_120855_create_aml_analysis_logs_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (877,'2023_01_19_124037_add_column_aml_riskscore_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (878,'2023_01_19_124738_add_column_is_getblockbot_tx_in_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (879,'2023_01_24_151221_add_column_is_iex_to_blacklist_order',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (880,'2023_01_24_151925_add_column_hash_id_to_blacklist_order',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (881,'2023_01_27_112429_add_column_colors_to_notices_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (882,'2023_01_30_104340_add_column_kyc_enabled_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (883,'2023_01_31_110119_create_direction_requisites_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (884,'2023_01_31_182820_create_directions_has_requisites_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (885,'2023_01_31_184401_add_column_id_direction_requisites_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (886,'2023_01_31_201337_create_tasks_requisites_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (887,'2023_01_31_202617_add_column_type_output_requisites_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (888,'2023_02_01_011419_add_column_formalization_text_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (889,'2023_02_01_014140_add_column_formalization_text_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (890,'2023_02_01_024210_add_column_exchange_fee_to_gateways_merchants',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (891,'2023_02_04_141904_create_task_single_log_confirm_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (892,'2023_02_05_095356_add_columns_is_aml_check_cost_reserve_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (893,'2023_02_05_101347_add_column_int_status_verification_card_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (894,'2023_02_05_130611_create_currencies_info_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (895,'2023_02_05_222203_create_geo_country_list_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (896,'2023_02_05_225526_create_direction_has_forbidden_countries_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (897,'2023_02_06_000934_create_direction_has_allowed_countries_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (898,'2023_02_07_093706_add_column_network_code_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (899,'2023_02_09_132920_create_verification_card_category_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (900,'2023_02_09_132929_create_verification_card_instructions_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (901,'2023_02_11_021346_create_merchant_transaction_hash_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (902,'2023_02_11_083240_add_column_is_wait_hash_pay_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (903,'2023_02_11_083822_create_pay_transaction_hash_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (904,'2023_02_12_094143_create_direction_exchange_modes_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (905,'2023_02_12_094225_create_directions_has_modes_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (906,'2023_02_13_011426_create_links_footer_groups_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (907,'2023_02_13_011633_create_links_footers_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (908,'2023_02_13_152210_add_column_give_price_merhant_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (909,'2023_02_14_080835_add_column_order_id_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (910,'2023_02_14_161436_add_column_aml_text_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (911,'2023_02_14_173111_add_column_aml_analyses_count_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (912,'2023_02_14_174112_add_column_price_to_aml_analysis_logs',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (913,'2023_02_14_180900_add_column_aml_day_limit_count_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (914,'2023_02_17_001304_add_column_min_count_exchanges_client_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (915,'2023_02_17_072833_add_column_getblockbot_tx_in_amount_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (916,'2023_02_17_204125_change_column_type_page_title_to_pages',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (917,'2023_02_17_213000_add_index_public_id_to_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (918,'2023_02_18_102751_create_whitebit_logs_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (919,'2023_02_18_180633_add_column_amlbot_tx_in_amount_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (920,'2023_02_18_183553_add_column_id_currency_to_aml_analysis_logs',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (921,'2023_02_18_192345_add_column_aml_service_name_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (922,'2023_03_17_191608_add_column_instruction_exchange_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (923,'2023_03_18_003311_add_column_order_button_i_pay_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (924,'2023_03_18_102800_add_column_blockchain_network_congestion_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (925,'2023_03_31_195258_add_column_hide_check_balance_to_gateways_payments',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (926,'2023_04_05_104512_add_column_is_allow_telegram_bot_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (927,'2023_04_06_133413_add_column_recalculated_at_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (928,'2023_04_06_190726_create_reserves_files_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (929,'2023_04_06_191017_create_reserves_files_groups_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (930,'2023_04_06_200016_add_column_id_file_reserve_to_reserves',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (931,'2023_04_06_223518_add_column_id_server_reserve_to_reserves',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (932,'2023_04_07_013252_add_column_is_kyc_checkbox_to_currencies',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (933,'2023_04_07_120748_add_column_is_unique_shot_to_requisites',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (934,'2023_04_07_125130_add_column_transfer_to_account_tasks',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (935,'2023_04_07_142935_create_direction_exchange_cities_table',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (936,'2023_04_07_170937_add_column_min_price_max_price_to_direction_exchange_cities',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (937,'2023_04_07_195102_add_column_id_country_to_direction_exchange_cities',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (938,'2023_04_07_215432_add_column_country_id_name_to_tasks_info',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (939,'2023_04_08_125936_add_column_manual_rate_value_to_direction_exchange',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (940,'2014_04_02_193005_create_translations_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (941,'2022_12_14_083707_create_settings_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (942,'2023_04_09_191116_create_tasks_messages_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (943,'2023_04_09_215747_add_column_type_user_to_tasks_messages',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (944,'2023_04_10_020458_create_tasks_managers_styles_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (945,'2023_04_10_031006_add_column_is_view_to_tasks_messages',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (946,'2023_04_10_093222_add_column_is_blocked_chat_to_tasks_info',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (947,'2023_04_13_232033_add_column_parser_source_name_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (948,'2023_04_14_010948_add_columns_index2_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (949,'2023_04_15_000902_drop_directions_has_cities_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (950,'2023_04_15_103904_add_column_name_to_currencies_log',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (951,'2023_04_16_131147_drop_column_is_telescope_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (952,'2023_04_16_210500_add_column_scan_name_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (953,'2023_04_19_220750_add_index_many_column_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (954,'2023_04_19_221323_add_index_many_column_to_tasks_info',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (955,'2023_04_19_221904_add_indexes_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (956,'2023_04_19_222437_add_indexes_many_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (957,'2023_04_19_222824_add_indexes_to_pay_transaction_hash',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (958,'2023_04_19_222941_add_indexes_many_to_merchant_transaction_hash',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (959,'2023_04_19_223112_add_indexes_to_requisites',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (960,'2023_04_19_223218_add_indexes_many_to_parser_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (961,'2023_04_19_223315_add_indexes_to_bestchange_rates',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (962,'2023_04_19_223434_add_indexes_many_to_reserves',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (963,'2023_04_19_223651_add_indexes_to_verification_card',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (964,'2023_04_21_182221_add_column_token_code_to_personal_access_tokens',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (965,'2023_04_22_123929_add_column_tech_name_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (966,'2023_04_22_141544_change_type_columns_to_news',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (967,'2023_04_23_185126_add_index_id_task_to_tasks_info',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (968,'2023_04_23_185218_add_index_is_spam_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (969,'2023_04_23_185636_add_index_tasks_user_to_tasks_user',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (970,'2023_04_26_000210_add_column_is_status_pay_to_tasks_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (971,'2023_04_26_134107_add_column_other_docs_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (972,'2023_04_26_141300_add_column_other_docs_in_out_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (973,'2023_04_26_183057_add_column_is_subtract_to_gateways_payments',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (974,'2023_04_26_184424_add_column_currency_code_to_gateways_payments',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (975,'2023_05_04_230922_change_type_body_to_logs_email',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (976,'2023_05_04_232555_create_iex_script_config_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (977,'2023_05_06_194608_add_column_is_allow_order_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (978,'2023_05_16_152742_change_type_add_comm_to_direction_exchange_cities',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (979,'2023_05_16_164156_delete_column_oth_deduct_comm_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (980,'2023_05_16_183331_create_direction_exchange_exchange_amount_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (981,'2023_05_17_010122_add_column_is_notify_exchange_amount_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (982,'2023_05_17_015832_add_column_add_course1_s_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (983,'2023_05_17_091812_add_column_logo_svg_to_payments',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (984,'2023_05_18_132515_add_column_is_guest_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (985,'2023_05_18_162113_add_column_is_disable_auto_reg_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (986,'2023_05_18_164959_add_column_referral_hash_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (987,'2023_05_19_011411_add_column_label_floating_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (988,'2023_05_19_012028_drop_column_export_label_city_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (989,'2023_05_19_165853_add_columns_oth_comm2_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (990,'2023_05_19_191302_change_type_oth_comm_percent_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (991,'2023_05_19_205053_add_column_oth_min2_comm_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (992,'2023_05_19_223132_add_column_pay_comm_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (993,'2023_05_20_025451_add_columns_commpay_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (994,'2023_05_21_121413_add_column_pay_amount_to_gateways_merchants',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (995,'2023_05_21_205951_add_column_auto_del_order_hour_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (996,'2023_05_21_212722_add_column_sorting_admin_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (997,'2023_05_21_215437_add_column_sorting_to_directions_fields',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (998,'2023_05_22_000233_add_column_sorting_admin_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (999,'2023_05_22_002842_add_column_is_verified_cabinet_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1000,'2023_05_22_004246_add_column_sorting_to_currency_fields',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1001,'2023_05_22_005502_add_column_sorting_out_to_currency_fields',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1002,'2023_05_22_090735_add_column_personal_discount_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1003,'2023_05_22_105420_receiving_price_discount_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1004,'2023_05_22_151449_add_column_is_enable_user_discount_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1005,'2023_05_22_184645_create_language_contents_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1006,'2023_05_22_211107_add_column_welcome_description_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1007,'2023_05_22_214838_add_column_telegram_block_title_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1008,'2023_05_22_221014_add_column_description_verification_card_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1009,'2023_05_22_222018_change_type_name_to_tasks_status',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1010,'2023_05_23_014347_change_type_tech_currency_name_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1011,'2023_05_23_094309_create_log_merchants_events_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1012,'2023_05_23_124808_create_logs_autopayment_events_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1013,'2023_05_23_131537_change_type_value_to_geo_country_list',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1014,'2023_05_23_143235_add_column_sitename_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1015,'2023_05_23_233818_add_column_main_value_header_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1016,'2023_05_24_005420_add_column_personal_ref_discount_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1017,'2023_05_24_010234_add_column_chat_app_id_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1018,'2023_05_24_164552_add_column_pay_amount_to_gateways_payments',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1019,'2023_05_24_204649_create_getblock_requests_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1020,'2023_05_24_210901_add_column_options_to_getblock_requests',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1021,'2023_05_25_101421_create_currencies_templates_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1022,'2023_05_25_122417_add_column_type_view_info_to_currencies_templates',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1023,'2023_05_25_172313_add_column_desc_exchange_dop_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1024,'2023_05_25_223532_add_column_first_value_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1025,'2023_05_26_015154_add_column_verification_info_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1026,'2023_05_26_092859_add_column_max_ref_discount_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1027,'2023_05_28_002025_add_column_day_names_to_job_settings',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1028,'2023_05_28_092423_create_job_schedules_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1029,'2023_05_28_150255_add_column_working_online_text_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1030,'2023_05_28_195645_add_column_referral_info_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1031,'2023_05_28_203936_add_column_description_pr_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1032,'2023_05_28_204136_add_column_description_review_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1033,'2023_05_30_144203_add_column_icon_to_contacts',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1034,'2023_05_30_180526_add_column_working_offline_notify_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1035,'2023_06_03_080158_change_type_title_to_referral_programs',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1036,'2023_06_07_193059_change_type_title_to_reward_programs',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1037,'2023_06_09_002806_add_column_type_profit_field_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1038,'2023_06_10_211707_create_banners_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1039,'2023_06_10_215722_create_banners_buttons_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1040,'2023_06_10_220501_create_banners_has_buttons_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1041,'2023_06_11_133246_change_type_name_to_pending_order_status',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1042,'2023_06_11_133311_change_type_name_to_tasks_rejection_status',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1043,'2023_06_12_014441_add_column_icons_to_contests',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1044,'2023_06_12_020249_add_column_info_to_contests',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1045,'2023_06_13_103252_add_column_is_blank_to_links_footers',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1046,'2023_06_19_202228_add_column_order_num_to_gateways_merchants',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1047,'2023_06_22_002142_add_column_verification_text_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1048,'2023_06_24_123337_add_column_recount_course_text_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1049,'2023_06_25_014422_create_direction_templates_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1050,'2023_06_27_093439_add_column_int_error_type_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1051,'2023_06_29_203646_add_column_bank_name_to_gateways_merchants',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1052,'2023_06_30_110318_add_column_direction_to_gateways_payments',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1053,'2023_07_02_073640_add_column_s_order_notify_text_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1054,'2023_07_04_173449_add_column_type_pay_to_gateways_merchants',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1055,'2023_07_16_201738_add_column_dot_not_remember_data_to_tasks_info',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1056,'2023_07_21_015337_create_tasks_comments_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1057,'2023_07_21_022308_add_column_type_output_requisites_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1058,'2023_07_26_033538_add_column_text_order_success_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1059,'2023_08_08_083642_change_type_description_to_contests_conditions',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1060,'2023_08_17_093719_add_columns_is_email_other_docs_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1061,'2023_08_18_100259_change_type_name_to_filter_currency',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1062,'2023_08_18_144315_add_column_colors_to_banners_buttons',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1063,'2023_08_18_173328_add_column_colors_to_banners',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1064,'2023_08_18_174921_add_column_images_banner_to_banners',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1065,'2023_08_20_103139_add_column_site_account_to_gateways_merchants',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1066,'2023_08_22_204015_add_column_security_order_page_code_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1067,'2023_08_22_223315_add_column_is_enable_order_paginate_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1068,'2023_08_23_072059_add_column_language_field_to_directions_fields',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1069,'2023_08_23_105003_add_column_site_account_to_gateways_payments',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1070,'2023_08_23_185608_add_column_auto_pay_order_pay_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1071,'2023_08_24_102258_add_column_course_display_to_tasks_status_log',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1072,'2023_08_27_102200_add_column_is_allow_file_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1073,'2023_08_28_105838_add_column_is_send_mail_create_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1074,'2023_08_28_224737_add_column_text_color_to_contacts',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1075,'2023_08_29_092652_add_column_text_color_to_menu',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1076,'2023_08_29_143015_create_user_verification_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1077,'2023_08_29_194840_add_column_ip_adddress_to_user_verification',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1078,'2023_08_30_084911_add_column_is_verify_account_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1079,'2023_08_30_140736_add_column_is_verified_account_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1080,'2023_08_30_202024_add_column_code_to_competitor_rates',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1081,'2023_08_30_202834_add_column_code_to_file_parser_rates',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1082,'2023_09_01_114947_create_course_update_time_logs_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1083,'2023_09_03_165245_add_column_is_hidden_ip_address_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1084,'2023_09_08_152227_add_column_bank_name_to_gateways_payments',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1085,'2023_09_14_052753_add_column_jivosite_text_message_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1086,'2023_09_14_115010_create_tasks_files_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1087,'2023_09_14_211129_add_column_photos_to_requisites',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1088,'2023_09_16_102012_create_parser_exchange_http_logs_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1089,'2023_09_18_054654_add_column_is_enabled_step_order_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1090,'2023_09_18_060728_create_order_steps_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1091,'2023_09_18_064112_add_column_id_order_step_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1092,'2023_09_22_062356_add_column_code_currency_to_gateways_merchants',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1093,'2023_09_22_062403_add_column_code_currency_to_gateways_payments',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1094,'2023_09_22_080458_add_column_network_code_out_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1095,'2023_09_27_162844_create_rules_pages_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1096,'2023_09_27_204151_add_column_title_rules_page_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1097,'2023_09_29_131537_add_column_sorting_to_banners',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1098,'2023_09_30_053347_add_column_id_parser_formula_to_code_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1099,'2023_09_30_194847_add_column_icon_to_order_steps',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1100,'2023_09_30_211151_create_applications_steps_logs_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1101,'2023_10_01_062322_create_transit_requisites_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1102,'2023_10_01_084533_add_column_is_request_payment_type_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1103,'2023_10_01_090256_add_column_description_request_payment_to_language_contents',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1104,'2023_10_01_203105_add_column_text_order_confirm_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1105,'2023_10_01_211239_add_column_order_button_i_confirm_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1106,'2023_10_02_052740_add_column_color_to_direction_notification',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1107,'2023_10_02_081632_add_column_notice_process_desc_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1108,'2023_10_02_143035_add_column_style_width_to_referral_programs',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1109,'2023_10_02_202200_create_parser_formula_logs_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1110,'2023_10_03_191948_add_column_is_wallet_issued_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1111,'2023_10_04_070015_create_logs_autopayment_orders_events_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1112,'2023_10_04_190737_add_column_requisites_description_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1113,'2023_10_05_193640_add_column_valid_account_error_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1114,'2023_10_05_203840_add_column_min_max_error_message_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1115,'2023_10_05_220218_add_column_account_number_field_text_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1116,'2023_10_06_081559_add_column_aml_address_risk_to_tasks_info',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1117,'2023_10_06_125054_add_column_text_message_order_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1118,'2023_10_06_212706_add_column_type_price_to_parser_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1119,'2023_10_07_101602_add_column_status_to_gateways',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1120,'2023_10_07_172354_create_file_storages_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1121,'2023_10_07_202251_is_local_image_to_payments',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1122,'2023_10_07_212126_is_local_image_to_verification_card',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1123,'2023_10_07_212620_is_local_image_to_news',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1124,'2023_10_11_075855_add_column_number_format_xml_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1125,'2023_10_26_200624_create_aml_services_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1126,'2023_10_26_210126_delete_columns_aml_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1127,'2023_10_26_211102_add_column_aml_new_column_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1128,'2023_10_28_061937_add_column_aml_column_to_tasks_info',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1129,'2023_12_07_103814_add_column_referral_profit_to_user_balance',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1130,'2023_12_09_180431_add_column_id_task_to_reviews',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1131,'2023_12_09_183644_delete_columns2_to_reviews',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1132,'2023_12_10_110429_add_column_email_to_verification_card',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1133,'2023_12_14_070417_create_withdrawal_wallets_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1134,'2023_12_14_114340_delete_column_is_fixed_to_notice_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1135,'2023_12_14_161308_create_tasks_check_images_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1136,'2023_12_14_165845_add_column_is_file_check_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1137,'2023_12_21_124943_add_column_style_width_to_reward_programs',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1138,'2023_12_23_090341_add_column_is_not_callback_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1139,'2023_12_24_161136_create_currencies_labels_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1140,'2023_12_24_161405_add_column_id_label_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1141,'2024_01_02_120301_add_column_version_to_reviews',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1142,'2024_01_03_220700_add_columns_is_checkbox_rules_and_aml_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1143,'2024_01_10_095453_change_type_string_bank_name',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1144,'2024_01_11_074214_add_column_bestchange_city_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1145,'2024_01_12_195928_add_column_count_review_to_links_reviews',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1146,'2024_01_15_081318_add_column_text_message_to_verification_card',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1147,'2024_01_15_203747_add_column_text_request_payment_to_requisites',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1148,'2024_01_16_090627_add_column_id_user_to_reviews',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1149,'2024_01_21_080719_add_columns_type_field_to_currency_fields',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1150,'2024_01_21_171408_add_column_multiplicity_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1151,'2024_01_25_135548_change_type_method_pay_string_to_gateways_merchants',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1152,'2024_02_02_201010_add_column_process_method_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1153,'2024_02_03_192606_change_table_unpaid_items_to_unpaid_items',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1154,'2024_02_03_192935_drop_column_rules_cron_to_unpaid_items',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1155,'2024_02_07_114708_change_type_api_transfer_id_to_pay_transaction_hash',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1156,'2024_02_08_135029_create_parser_exchange_error_rates_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1157,'2024_02_08_182848_create_direction_exchange_min_price_logs_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1158,'2024_02_16_104803_add_column_receiving_price_with_promocode_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1159,'2024_02_16_114137_add_column_promo_code_value_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1160,'2024_02_16_114415_add_column_promo_code_discount_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1161,'2024_02_18_182436_delete_column_restriction_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1162,'2024_02_19_073749_drop_column_unlimited_reserve_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1163,'2024_02_23_121824_change_type_course_float_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1164,'2024_02_23_143425_drop_table_is_backup_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1165,'2024_02_26_184551_change_type_method_pay_to_gateways_payments',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1166,'2024_03_10_232215_drop_column_is_vip_client_to_users',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1167,'2024_03_12_230002_add_column_id_user_to_permissions',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1168,'2024_03_15_170328_add_column_start_with_to_direction_fields',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1169,'2024_03_15_172215_add_column_end_with_to_direction_fields',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1170,'2024_03_15_172703_add_column_start_end_with_to_currency_fields',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1171,'2024_03_15_233043_add_column_id_manager_to_verification_card',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1172,'2024_03_16_114556_add_column_method_request_payment_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1173,'2024_03_16_123918_add_column_method_request_payment_to_tasks',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1174,'2024_03_16_215825_drop_column_sorting_reserve_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1175,'2024_03_17_061747_drop_column_sorting_admin_to_direction_exchange',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1176,'2024_03_17_085002_change_type_is_out_aml_check_wallet_to_currencies',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1177,'2024_03_17_150532_create_aml_services_address_log_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1178,'2024_03_20_085534_drop_column_is_import_to_code_currencies',364);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1179,'2024_03_21_172238_change_recount_time_hours_to_currencies',365);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1180,'2024_03_23_070532_create_pulse_tables',365);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1181,'2024_03_23_092716_drop_column_is_horizon_to_users',366);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1182,'2024_03_25_130357_add_column_is_run_process_to_tasks',367);
