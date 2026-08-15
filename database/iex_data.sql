-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Янв 18 2026 г., 10:28
-- Версия сервера: 11.8.5-MariaDB-deb12
-- Версия PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `app_test_iex`
--

-- --------------------------------------------------------

--
-- Структура таблицы `admin_desktops`
--

CREATE TABLE `admin_desktops` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `name` varchar(191) DEFAULT NULL,
  `columns` int(11) NOT NULL DEFAULT 2,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `flex_num1` int(11) NOT NULL DEFAULT 0,
  `flex_num2` int(11) NOT NULL DEFAULT 0,
  `flex_num3` int(11) NOT NULL DEFAULT 0,
  `flex_nums` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`flex_nums`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `admin_desktops`
--

INSERT INTO `admin_desktops` (`id`, `id_user`, `name`, `columns`, `sorting`, `created_at`, `updated_at`, `flex_num1`, `flex_num2`, `flex_num3`, `flex_nums`) VALUES
(1, 1, 'Рабочий стол 1', 2, 0, '2023-04-09 11:06:42', '2023-04-09 11:06:42', 50, 50, 0, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `admin_desktop_gadgets`
--

CREATE TABLE `admin_desktop_gadgets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_desktop` int(11) NOT NULL DEFAULT 0,
  `name` varchar(191) DEFAULT NULL,
  `alias` varchar(191) DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `column_id` varchar(191) DEFAULT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `hash_id` varchar(191) DEFAULT NULL,
  `id_widget` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `admin_filter_header`
--

CREATE TABLE `admin_filter_header` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `name` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_common` int(11) NOT NULL DEFAULT 0,
  `type_filter` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `advantage`
--

CREATE TABLE `advantage` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `title` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `icon` varchar(191) DEFAULT NULL,
  `link` varchar(191) DEFAULT NULL,
  `is_target` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `rowspan` varchar(191) NOT NULL DEFAULT '0',
  `colspan` varchar(191) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `aml_response_data`
--

CREATE TABLE `aml_response_data` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) NOT NULL DEFAULT 0,
  `id_aml_service` int(11) NOT NULL DEFAULT 0,
  `alias` varchar(191) DEFAULT NULL,
  `ext_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ext_params`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `method` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `aml_services`
--

CREATE TABLE `aml_services` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `alias` varchar(191) DEFAULT NULL,
  `aml_name` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `filename` varchar(191) DEFAULT NULL,
  `ext_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ext_options`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `api_logs`
--

CREATE TABLE `api_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `api_token` varchar(191) DEFAULT NULL,
  `token_id` bigint(20) UNSIGNED DEFAULT NULL,
  `api_action` varchar(191) DEFAULT NULL,
  `ip_address` varchar(191) DEFAULT NULL,
  `headers` longtext DEFAULT NULL,
  `post_data` longtext DEFAULT NULL,
  `status_code` smallint(5) UNSIGNED DEFAULT NULL,
  `response_data` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `applications_steps_logs`
--

CREATE TABLE `applications_steps_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_step` int(11) NOT NULL DEFAULT 0,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `id_order_status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `auth_audit_events`
--

CREATE TABLE `auth_audit_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `guard` varchar(32) DEFAULT NULL,
  `channel` varchar(32) DEFAULT NULL,
  `event` varchar(64) NOT NULL,
  `result` varchar(32) NOT NULL,
  `reason_code` varchar(64) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `ip_prev` varchar(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_id` varchar(80) DEFAULT NULL,
  `is_new_device` tinyint(1) NOT NULL DEFAULT 0,
  `browser` varchar(64) DEFAULT NULL,
  `os` varchar(64) DEFAULT NULL,
  `device` varchar(64) DEFAULT NULL,
  `country` varchar(128) DEFAULT NULL,
  `city` varchar(128) DEFAULT NULL,
  `iso_code` varchar(8) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `session_id` varchar(191) DEFAULT NULL,
  `session_prev_id` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `autosender_payment`
--

CREATE TABLE `autosender_payment` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_order` int(11) NOT NULL DEFAULT 0,
  `comment` text DEFAULT NULL,
  `amount` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `banned`
--

CREATE TABLE `banned` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` varchar(16) DEFAULT NULL,
  `filter_key` varchar(160) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `ip_from` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_to` bigint(20) UNSIGNED DEFAULT NULL,
  `filter_name` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `banned_user`
--

CREATE TABLE `banned_user` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `banners`
--

CREATE TABLE `banners` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` text DEFAULT NULL,
  `text` longtext DEFAULT NULL,
  `images` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `color_title` varchar(191) DEFAULT NULL,
  `color_text` varchar(191) DEFAULT NULL,
  `images_banner` varchar(191) DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `banners_buttons`
--

CREATE TABLE `banners_buttons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text DEFAULT NULL,
  `link` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `color_text_button` varchar(191) DEFAULT NULL,
  `color_bg_button` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `banners_has_banners_buttons`
--

CREATE TABLE `banners_has_banners_buttons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `banners_button_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `bans`
--

CREATE TABLE `bans` (
  `id` int(10) UNSIGNED NOT NULL,
  `bannable_type` varchar(191) NOT NULL,
  `bannable_id` bigint(20) UNSIGNED NOT NULL,
  `created_by_type` varchar(191) DEFAULT NULL,
  `created_by_id` bigint(20) UNSIGNED DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `bestchange_directions`
--

CREATE TABLE `bestchange_directions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_direction_exchange` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(191) DEFAULT NULL,
  `id_currency_in` int(11) NOT NULL DEFAULT 0,
  `id_currency_out` int(11) NOT NULL DEFAULT 0,
  `position_num` varchar(191) NOT NULL DEFAULT '0',
  `min_reserve` varchar(191) NOT NULL DEFAULT '0',
  `max_reserve` varchar(191) NOT NULL DEFAULT '0',
  `step` varchar(191) NOT NULL DEFAULT '0',
  `reset_course` int(11) NOT NULL DEFAULT 0,
  `standard_course` varchar(191) NOT NULL DEFAULT '0',
  `min_sum` varchar(191) NOT NULL DEFAULT '0',
  `max_sum` varchar(191) NOT NULL DEFAULT '0',
  `minmax_sum_fee` varchar(191) NOT NULL DEFAULT '0',
  `is_parser_formula` int(11) NOT NULL DEFAULT 0,
  `min_sum_new_default_parser` bigint(20) NOT NULL DEFAULT 0,
  `min_sum_new_default_parser_fee` varchar(191) NOT NULL DEFAULT '0',
  `min_sum_new_formula_parser` bigint(20) NOT NULL DEFAULT 0,
  `min_sum_new_formula_parser_fee` varchar(191) NOT NULL DEFAULT '0',
  `whitelist_ids` longtext DEFAULT NULL,
  `blacklist_ids` longtext DEFAULT NULL,
  `city_id` bigint(20) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `is_favorite` tinyint(1) NOT NULL DEFAULT 0,
  `is_error_parser` int(11) NOT NULL DEFAULT 0,
  `rate_value` varchar(191) NOT NULL DEFAULT '0',
  `source_name` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `exchange_in` varchar(191) DEFAULT NULL,
  `exchange_out` varchar(191) DEFAULT NULL,
  `formula_value` text DEFAULT NULL,
  `code` varchar(191) DEFAULT NULL,
  `rate_value_without_step` varchar(191) NOT NULL DEFAULT '0',
  `rate_mode` varchar(30) DEFAULT NULL COMMENT 'position|median_top_n|weighted_avg_top_n',
  `top_n` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'N for median/weighted (e.g. 5)',
  `explain_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Explain / audit данных расчёта курса BestChange' CHECK (json_valid(`explain_payload`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `bestchange_exchanger_cooldowns`
--

CREATE TABLE `bestchange_exchanger_cooldowns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `changer_id` int(10) UNSIGNED NOT NULL,
  `blocked_until` timestamp NOT NULL,
  `reason` varchar(191) DEFAULT NULL,
  `minutes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `bestchange_exchanger_stats`
--

CREATE TABLE `bestchange_exchanger_stats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `changer_id` int(10) UNSIGNED NOT NULL,
  `seen_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `selected_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `rejected_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `error_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `quality_score_sum` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `last_selected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `bestchange_market_reports`
--

CREATE TABLE `bestchange_market_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `day` date NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `bestchange_parser_error`
--

CREATE TABLE `bestchange_parser_error` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_bestchange` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_direction_exchange` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `blacklist_order`
--

CREATE TABLE `blacklist_order` (
  `id` int(10) UNSIGNED NOT NULL,
  `value` text DEFAULT NULL,
  `type` int(11) NOT NULL DEFAULT 0,
  `text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_bestchange` int(11) NOT NULL DEFAULT 0,
  `is_iex` int(11) NOT NULL DEFAULT 0,
  `hash_id` varchar(191) DEFAULT NULL,
  `id_task` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `cache`
--

CREATE TABLE `cache` (
  `key` varchar(191) NOT NULL,
  `value` longtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `checkbox_agreements`
--

CREATE TABLE `checkbox_agreements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `label` text NOT NULL,
  `description` longtext DEFAULT NULL,
  `link` varchar(191) DEFAULT NULL,
  `page_type` varchar(191) DEFAULT NULL,
  `page_id` bigint(20) UNSIGNED DEFAULT NULL,
  `checked` tinyint(1) NOT NULL DEFAULT 0,
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `is_protected` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sorting` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `key_id` varchar(191) DEFAULT NULL,
  `apply_mode` varchar(20) NOT NULL DEFAULT 'all_except',
  `text_error` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `checkbox_agreement_direction_allowed`
--

CREATE TABLE `checkbox_agreement_direction_allowed` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `checkbox_agreement_id` bigint(20) UNSIGNED NOT NULL,
  `direction_exchange_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `checkbox_agreement_direction_exchange`
--

CREATE TABLE `checkbox_agreement_direction_exchange` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `checkbox_agreement_id` bigint(20) UNSIGNED NOT NULL,
  `direction_exchange_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `checks`
--

CREATE TABLE `checks` (
  `id` int(10) UNSIGNED NOT NULL,
  `host_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(191) NOT NULL,
  `status` varchar(191) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_run_message` text DEFAULT NULL,
  `last_run_output` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`last_run_output`)),
  `last_ran_at` timestamp NULL DEFAULT NULL,
  `next_run_in_minutes` int(11) DEFAULT NULL,
  `started_throttling_failing_notifications_at` timestamp NULL DEFAULT NULL,
  `custom_properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_properties`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `cities`
--

CREATE TABLE `cities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text DEFAULT NULL,
  `designation_xml` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_user_id` int(11) NOT NULL DEFAULT 0,
  `updated_user_id` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `country_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `code_currency`
--

CREATE TABLE `code_currency` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `balance` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sign` varchar(100) NOT NULL,
  `id_parser_exchange` int(11) NOT NULL DEFAULT 0,
  `commission` double NOT NULL DEFAULT 0,
  `is_trashed` int(11) NOT NULL DEFAULT 0,
  `internal_rate` varchar(191) NOT NULL DEFAULT '0',
  `add_to_course` varchar(191) NOT NULL DEFAULT '0',
  `id_parser_formula` int(11) NOT NULL DEFAULT 0,
  `add_to_course_formula` varchar(191) DEFAULT NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `competitor_links`
--

CREATE TABLE `competitor_links` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `link` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `competitor_rates`
--

CREATE TABLE `competitor_rates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `id_competitor` int(11) NOT NULL DEFAULT 0,
  `value` int(11) NOT NULL DEFAULT 0,
  `summa` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `type` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `number_format` int(11) NOT NULL DEFAULT 0,
  `exchange_in` varchar(191) DEFAULT NULL,
  `exchange_out` varchar(191) DEFAULT NULL,
  `code` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `contacts`
--

CREATE TABLE `contacts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text DEFAULT NULL,
  `value` text DEFAULT NULL,
  `url` text DEFAULT NULL,
  `block_size` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '0 - disabled, 1 - enabled',
  `is_home` int(11) NOT NULL DEFAULT 0,
  `icon` varchar(191) DEFAULT NULL,
  `text_color` varchar(191) DEFAULT NULL,
  `id_group` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `contacts_groups`
--

CREATE TABLE `contacts_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '0 - disabled, 1 - enabled',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `contacts_groups`
--

INSERT INTO `contacts_groups` (`id`, `name`, `sorting`, `status`, `created_at`, `updated_at`) VALUES
(1, '{\"ru\":\"Техническая поддержка\"}', 0, 1, '2024-07-29 06:24:27', '2024-07-29 06:24:27');

-- --------------------------------------------------------

--
-- Структура таблицы `contests`
--

CREATE TABLE `contests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `name` varchar(191) DEFAULT NULL,
  `duration` varchar(191) DEFAULT NULL,
  `max_limit_user` int(11) NOT NULL DEFAULT 0,
  `is_manual_bank` int(11) NOT NULL DEFAULT 0,
  `bank_base` varchar(191) NOT NULL DEFAULT '0',
  `bank` varchar(191) NOT NULL DEFAULT '0',
  `id_code_currency` int(11) NOT NULL DEFAULT 0,
  `code_name` varchar(191) DEFAULT NULL,
  `code_sign` varchar(191) DEFAULT NULL,
  `code_position` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `percent` double(8,2) NOT NULL DEFAULT 0.00,
  `title` text DEFAULT NULL,
  `subtitle` text DEFAULT NULL,
  `button_name` text DEFAULT NULL,
  `subtitle_color` varchar(191) DEFAULT NULL,
  `title_color` varchar(191) DEFAULT NULL,
  `icon_url_home` varchar(191) DEFAULT NULL,
  `icon_url_account` varchar(191) DEFAULT NULL,
  `info_title` text DEFAULT NULL,
  `info_text` longtext DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `contests_conditions`
--

CREATE TABLE `contests_conditions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `description` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `contests_faq`
--

CREATE TABLE `contests_faq` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` text DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `contests_has_contests_users`
--

CREATE TABLE `contests_has_contests_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `contests_user_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `contests_users`
--

CREATE TABLE `contests_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_monitoring` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `name` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `link` varchar(191) DEFAULT NULL,
  `bonus` double(8,2) NOT NULL DEFAULT 0.00,
  `id_contest` int(11) NOT NULL DEFAULT 0,
  `code_sign_bonus` varchar(191) DEFAULT NULL,
  `code_name_bonus` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `course_update_time_logs`
--

CREATE TABLE `course_update_time_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `time` varchar(191) DEFAULT NULL,
  `type_rate` varchar(191) DEFAULT NULL,
  `source` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currencies`
--

CREATE TABLE `currencies` (
  `id` int(11) NOT NULL,
  `id_payment` int(11) NOT NULL DEFAULT 0 COMMENT 'ID Платежной системы',
  `id_code_currency` int(11) NOT NULL DEFAULT 0 COMMENT 'ID Кода валют',
  `id_filter_currency` int(11) NOT NULL DEFAULT 0,
  `designation_xml` varchar(191) DEFAULT NULL COMMENT 'Обозначение для XML',
  `convert_by` double NOT NULL DEFAULT 1 COMMENT 'Конвертировать по',
  `number_format` int(11) NOT NULL DEFAULT 4 COMMENT 'Знаков, после запятой',
  `day_limit_give` double NOT NULL DEFAULT 0 COMMENT 'Дневной лимит для Отдаю',
  `day_limit_receive` double NOT NULL DEFAULT 0 COMMENT 'Дневной лимит для Получаю',
  `min_char` int(11) NOT NULL DEFAULT 0 COMMENT 'Мин. кол-во символов',
  `max_char` int(11) NOT NULL DEFAULT 0 COMMENT 'Мак. кол-во символов',
  `field_name_from` text DEFAULT NULL COMMENT 'Первые символы',
  `placeholder` varchar(255) NOT NULL,
  `allowed_char` int(11) NOT NULL DEFAULT 0 COMMENT 'Разрешенные символы',
  `status` int(11) NOT NULL DEFAULT 0 COMMENT 'Статус',
  `visible_give` int(11) NOT NULL DEFAULT 0,
  `visible_receiving` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `visible` int(11) NOT NULL DEFAULT 0,
  `visible_code_currency` int(11) NOT NULL DEFAULT 0,
  `text_color` varchar(191) DEFAULT NULL,
  `background_color` varchar(191) DEFAULT NULL,
  `char_default` varchar(191) DEFAULT NULL,
  `mask_account_from` varchar(191) DEFAULT NULL,
  `mask_placeholder_char_from` varchar(10) DEFAULT NULL,
  `sorting_1` int(11) NOT NULL DEFAULT 0,
  `id_pay` int(11) NOT NULL DEFAULT 0 COMMENT 'Выплаты',
  `validation_account_from` varchar(100) DEFAULT NULL,
  `is_qrcode` int(11) DEFAULT 0,
  `prefix_qrcode` varchar(191) DEFAULT NULL,
  `is_payment_default` int(11) NOT NULL DEFAULT 0,
  `is_payment_unique` int(11) NOT NULL DEFAULT 0,
  `month_limit_in` double(8,2) NOT NULL DEFAULT 0.00,
  `month_limit_out` double(8,2) NOT NULL DEFAULT 0.00,
  `account_number_field` text DEFAULT NULL,
  `notice_in` text DEFAULT NULL,
  `transfer_percent_reserve` double NOT NULL DEFAULT 0,
  `transfer_amount_reserve` double NOT NULL DEFAULT 0,
  `remove_spaces_requisite` tinyint(1) NOT NULL DEFAULT 0,
  `payout_commission` double NOT NULL DEFAULT 0,
  `is_archive` int(11) NOT NULL DEFAULT 0,
  `id_group` int(11) NOT NULL DEFAULT 0,
  `is_qrcode_amount` int(11) NOT NULL DEFAULT 0,
  `is_enabled_verification` int(11) NOT NULL DEFAULT 0,
  `min_amount_verification` float NOT NULL DEFAULT 0,
  `is_user_verification` int(11) NOT NULL DEFAULT 0,
  `payout_commission_amount` double NOT NULL DEFAULT 0,
  `id_auto_reserve` int(11) NOT NULL DEFAULT 0,
  `sorting_tariffs` int(11) NOT NULL DEFAULT 0,
  `max_limit_in_reserve` varchar(191) DEFAULT NULL,
  `hour_limit_order_pending` int(11) NOT NULL DEFAULT 0,
  `hour_limit_order_process` int(11) NOT NULL DEFAULT 0,
  `profit_percent_reserve` double NOT NULL DEFAULT 0,
  `is_card_detail` int(11) NOT NULL DEFAULT 0,
  `max_display_reserve` varchar(191) DEFAULT NULL,
  `is_email_verification_modal` int(11) NOT NULL DEFAULT 0,
  `is_auto_check_modal` int(11) NOT NULL DEFAULT 0,
  `recount_percent` int(11) NOT NULL DEFAULT 0,
  `is_recount_default` int(11) NOT NULL DEFAULT 0,
  `notice_out` longtext DEFAULT NULL,
  `is_unique_recount_order` int(11) NOT NULL DEFAULT 0,
  `is_enable_auto_recount_order` int(11) NOT NULL DEFAULT 0,
  `unique_recount_percent` double(8,2) NOT NULL DEFAULT 0.00,
  `recount_time_minutes` int(11) NOT NULL DEFAULT 0,
  `desc_exchange` text DEFAULT NULL,
  `created_user_id` int(11) NOT NULL DEFAULT 0,
  `updated_user_id` int(11) NOT NULL DEFAULT 0,
  `is_fire` int(11) NOT NULL DEFAULT 0,
  `field_name_to` text DEFAULT NULL,
  `field_comment_from` text DEFAULT NULL,
  `field_comment_to` text DEFAULT NULL,
  `tech_currency_name` text NOT NULL,
  `button_create_order` text DEFAULT NULL,
  `button_create_order_text` longtext DEFAULT NULL,
  `formalization_text` longtext DEFAULT NULL,
  `network_code` varchar(191) DEFAULT NULL,
  `aml_text_in` longtext DEFAULT NULL,
  `aml_text_out` longtext DEFAULT NULL,
  `aml_analyses_count` int(11) NOT NULL DEFAULT 0,
  `aml_analyses_price` double(8,2) NOT NULL DEFAULT 0.00,
  `instruction_exchange` longtext DEFAULT NULL,
  `instruction_source_mode` varchar(20) NOT NULL DEFAULT 'auto',
  `desc_source_mode` varchar(20) NOT NULL DEFAULT 'auto',
  `tech_name` varchar(191) DEFAULT NULL,
  `other_docs_in` longtext DEFAULT NULL,
  `other_docs_out` longtext DEFAULT NULL,
  `is_allow_order` int(11) NOT NULL DEFAULT 0,
  `is_verified_cabinet` int(11) NOT NULL DEFAULT 0,
  `first_value` varchar(191) DEFAULT NULL,
  `verification_info` longtext DEFAULT NULL,
  `verification_text` text DEFAULT NULL,
  `recount_course_text` text DEFAULT NULL,
  `type_output_requisites` int(11) NOT NULL DEFAULT 0,
  `allow_autopay` int(11) NOT NULL DEFAULT 0,
  `is_allow_file` int(11) NOT NULL DEFAULT 0,
  `is_enabled_step_order` int(11) NOT NULL DEFAULT 0,
  `network_code_out` varchar(191) DEFAULT NULL,
  `valid_account_error_from` text DEFAULT NULL,
  `min_max_error_message` text DEFAULT NULL,
  `account_number_field_text` text DEFAULT NULL,
  `id_aml_service` varchar(191) DEFAULT NULL,
  `is_aml_check_wallet` int(11) NOT NULL DEFAULT 0,
  `is_aml_check_tx` int(11) NOT NULL DEFAULT 0,
  `aml_tx_from_amount` varchar(191) DEFAULT NULL,
  `method_request_payment` int(11) NOT NULL DEFAULT 0,
  `aml_wallet_from_amount` varchar(191) NOT NULL DEFAULT '0',
  `recount_statusses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recount_statusses`)),
  `request_payment_text` longtext DEFAULT NULL,
  `merchant_day_limit_amount` varchar(191) NOT NULL DEFAULT '0',
  `merchant_month_limit_amount` varchar(191) NOT NULL DEFAULT '0',
  `merchant_min_amount_for_order` varchar(191) NOT NULL DEFAULT '0',
  `merchant_max_amount_for_order` varchar(191) NOT NULL DEFAULT '0',
  `merchant_day_limit` varchar(191) NOT NULL DEFAULT '0',
  `merchant_month_limit` varchar(191) NOT NULL DEFAULT '0',
  `pay_day_limit_amount` varchar(191) NOT NULL DEFAULT '0',
  `pay_month_limit_amount` varchar(191) NOT NULL DEFAULT '0',
  `pay_min_amount_for_order` varchar(191) NOT NULL DEFAULT '0',
  `pay_max_amount_for_order` varchar(191) NOT NULL DEFAULT '0',
  `pay_day_limit` int(11) NOT NULL DEFAULT 0,
  `pay_month_limit` int(11) NOT NULL DEFAULT 0,
  `error_for_aml_check_wallet` int(11) NOT NULL DEFAULT 0,
  `error_for_aml_check_tx` int(11) NOT NULL DEFAULT 0,
  `file_allow_title` longtext DEFAULT NULL,
  `file_allow_description` longtext DEFAULT NULL,
  `ext_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ext_params`)),
  `id_group_network` int(11) NOT NULL,
  `small_code` varchar(191) DEFAULT NULL,
  `is_popular` int(11) NOT NULL DEFAULT 0,
  `tags` varchar(191) DEFAULT NULL,
  `validation_account_to` varchar(191) DEFAULT NULL,
  `valid_account_error_to` text DEFAULT NULL,
  `mask_account_to` varchar(191) DEFAULT NULL,
  `mask_placeholder_char_to` varchar(10) DEFAULT NULL,
  `display_scan_qr_from` tinyint(1) NOT NULL DEFAULT 0,
  `display_scan_qr_to` tinyint(1) NOT NULL DEFAULT 0,
  `identity_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Мультиязычное описание блока идентификации' CHECK (json_valid(`identity_text`)),
  `identity_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Мультиязычная инструкция для идентификации личности' CHECK (json_valid(`identity_info`)),
  `identity_verification_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Настройки верификации личности (mode, min_amount и др.)' CHECK (json_valid(`identity_verification_rules`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currencies_analytics`
--

CREATE TABLE `currencies_analytics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `in_amount` varchar(191) DEFAULT NULL,
  `out_amount` varchar(191) DEFAULT NULL,
  `in_count_exchange` int(11) NOT NULL DEFAULT 0,
  `out_count_exchange` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `in_order_count` int(11) NOT NULL DEFAULT 0,
  `out_order_count` int(11) NOT NULL DEFAULT 0,
  `in_amount_usd` decimal(24,8) NOT NULL DEFAULT 0.00000000,
  `out_amount_usd` decimal(24,8) NOT NULL DEFAULT 0.00000000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currencies_analytics_daily`
--

CREATE TABLE `currencies_analytics_daily` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `id_currency` bigint(20) UNSIGNED NOT NULL,
  `in_amount` varchar(191) DEFAULT NULL,
  `out_amount` varchar(191) DEFAULT NULL,
  `in_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `out_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `in_amount_usd` decimal(24,8) NOT NULL DEFAULT 0.00000000,
  `out_amount_usd` decimal(24,8) NOT NULL DEFAULT 0.00000000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currencies_commands`
--

CREATE TABLE `currencies_commands` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `amount` double NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_currency` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currencies_groups`
--

CREATE TABLE `currencies_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `currencies_groups`
--

INSERT INTO `currencies_groups` (`id`, `name`, `id_user`, `sorting`, `created_at`, `updated_at`) VALUES
(2, 'Общее', 1, 0, '2019-05-31 03:23:51', '2019-05-31 03:23:51');

-- --------------------------------------------------------

--
-- Структура таблицы `currencies_groups_networks`
--

CREATE TABLE `currencies_groups_networks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` text DEFAULT NULL,
  `icon` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `display_type` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0 - стандартная группировка, 1 - компактная',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currencies_info`
--

CREATE TABLE `currencies_info` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `aml_analyses_type` int(11) NOT NULL DEFAULT 0,
  `aml_analyses_count` int(11) NOT NULL DEFAULT 0,
  `aml_analyses_price` double(8,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currencies_labels`
--

CREATE TABLE `currencies_labels` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` text DEFAULT NULL,
  `text_color` varchar(191) DEFAULT NULL,
  `bg_color` varchar(191) DEFAULT NULL,
  `image` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currencies_notification`
--

CREATE TABLE `currencies_notification` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `css_class` varchar(191) DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currencies_templates`
--

CREATE TABLE `currencies_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_type` int(11) NOT NULL DEFAULT 0,
  `text` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type_view_info` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currency_bin_bank_rules`
--

CREATE TABLE `currency_bin_bank_rules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `currency_id` int(11) NOT NULL,
  `direction` varchar(3) NOT NULL,
  `mode` varchar(6) NOT NULL,
  `bank_name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currency_fields`
--

CREATE TABLE `currency_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key_id` varchar(100) DEFAULT NULL,
  `name` text NOT NULL,
  `id_currency` int(11) NOT NULL,
  `when_print` int(11) NOT NULL,
  `min_char` int(11) NOT NULL,
  `max_char` int(11) NOT NULL,
  `obligatory_field` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` int(11) NOT NULL,
  `field_type` varchar(191) DEFAULT NULL,
  `language_field` int(11) NOT NULL DEFAULT 0,
  `remove_spaces` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `sorting_out` int(11) NOT NULL DEFAULT 0,
  `type_field` int(11) NOT NULL DEFAULT 0,
  `list_text` longtext DEFAULT NULL,
  `start_with` varchar(191) DEFAULT NULL,
  `end_with` varchar(191) DEFAULT NULL,
  `description_field` longtext DEFAULT NULL,
  `example` text DEFAULT NULL,
  `validator_type` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currency_filter`
--

CREATE TABLE `currency_filter` (
  `currency_id` int(11) NOT NULL,
  `filter_currency_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currency_in_has_fields`
--

CREATE TABLE `currency_in_has_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `field_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currency_label_currency`
--

CREATE TABLE `currency_label_currency` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `currency_id` int(11) NOT NULL,
  `label_id` bigint(20) UNSIGNED NOT NULL,
  `side` enum('give','receive') NOT NULL DEFAULT 'give',
  `priority` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currency_merchants`
--

CREATE TABLE `currency_merchants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `gateway_merchant_id` bigint(20) UNSIGNED NOT NULL,
  `network_code` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currency_out_has_fields`
--

CREATE TABLE `currency_out_has_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `field_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currency_payments`
--

CREATE TABLE `currency_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `gateway_payment_id` bigint(20) UNSIGNED NOT NULL,
  `network_code` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `currency_requisites_has_fields`
--

CREATE TABLE `currency_requisites_has_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `field_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `daily_profit_stats`
--

CREATE TABLE `daily_profit_stats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stat_date` date NOT NULL,
  `direction_id` bigint(20) UNSIGNED NOT NULL,
  `direction_name` varchar(191) DEFAULT NULL,
  `currency_from` varchar(191) DEFAULT NULL,
  `currency_to` varchar(191) DEFAULT NULL,
  `total_orders` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `total_profit_usd` decimal(30,18) NOT NULL DEFAULT 0.000000000000000000,
  `avg_profit_usd` decimal(30,18) NOT NULL DEFAULT 0.000000000000000000,
  `min_profit_usd` decimal(30,18) NOT NULL DEFAULT 0.000000000000000000,
  `max_profit_usd` decimal(30,18) NOT NULL DEFAULT 0.000000000000000000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `dashboard_user_widgets`
--

CREATE TABLE `dashboard_user_widgets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `row` int(11) NOT NULL DEFAULT 0,
  `col` int(11) NOT NULL DEFAULT 0,
  `alias` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `directions_city_profile_pivot`
--

CREATE TABLE `directions_city_profile_pivot` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_direction_exchange_city` bigint(20) UNSIGNED NOT NULL,
  `direction_city_profile_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `directions_fields`
--

CREATE TABLE `directions_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text DEFAULT NULL,
  `min_char` int(11) NOT NULL DEFAULT 0,
  `max_char` int(11) NOT NULL DEFAULT 0,
  `obligatory_field` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `key_id` varchar(191) DEFAULT NULL,
  `field_type` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `description` text DEFAULT NULL,
  `remove_spaces` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `language_field` int(11) NOT NULL DEFAULT 0,
  `start_with` varchar(191) DEFAULT NULL,
  `end_with` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `directions_has_allowed_countries`
--

CREATE TABLE `directions_has_allowed_countries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `geo_country_list_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `directions_has_fields`
--

CREATE TABLE `directions_has_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `direction_field_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `directions_has_forbidden_countries`
--

CREATE TABLE `directions_has_forbidden_countries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `geo_country_list_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `directions_has_modes`
--

CREATE TABLE `directions_has_modes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `direction_exchange_mode_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `directions_has_requisites`
--

CREATE TABLE `directions_has_requisites` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `direction_requisite_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_city_profiles`
--

CREATE TABLE `direction_city_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `code` varchar(191) DEFAULT NULL,
  `profit` varchar(191) DEFAULT NULL,
  `profit_s` varchar(191) DEFAULT NULL,
  `add_comm` varchar(191) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_day`
--

CREATE TABLE `direction_day` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_direction_exchange` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_exchange`
--

CREATE TABLE `direction_exchange` (
  `id` int(11) NOT NULL,
  `limit_profile_id` bigint(20) UNSIGNED DEFAULT NULL,
  `id_currency1` int(11) NOT NULL COMMENT 'Направление 1',
  `id_currency2` int(11) NOT NULL COMMENT 'Направление 2',
  `exchange_rate1` varchar(250) NOT NULL DEFAULT '0' COMMENT 'Курс обмена 1',
  `exchange_rate2` varchar(250) NOT NULL DEFAULT '0' COMMENT 'Курс обмена 2',
  `id_crypto_parser` int(11) NOT NULL DEFAULT 0 COMMENT 'Автокорректировка курса',
  `your_add_course1` double NOT NULL DEFAULT 0,
  `your_add_course2` double NOT NULL DEFAULT 0,
  `id_merchant` int(11) NOT NULL DEFAULT 0,
  `add_course1` double NOT NULL DEFAULT 0 COMMENT 'Прибавление к курсу 1',
  `add_course2` double NOT NULL DEFAULT 0 COMMENT 'Прибавление к курсу 2',
  `status` int(11) NOT NULL DEFAULT 0 COMMENT 'Статус',
  `seo_title` text NOT NULL,
  `seo_description` longtext NOT NULL,
  `seo_keywords` longtext NOT NULL,
  `deadline` longtext NOT NULL,
  `instructions` longtext DEFAULT NULL COMMENT 'Инструкция по оплате',
  `instruction_source_mode` varchar(20) NOT NULL DEFAULT 'auto',
  `desc_source_mode` varchar(20) NOT NULL DEFAULT 'auto',
  `profit` double NOT NULL DEFAULT 0 COMMENT 'Прибыль',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `auto_conversion` int(11) NOT NULL,
  `min_price1` double NOT NULL DEFAULT 0,
  `min_price2` double NOT NULL DEFAULT 0,
  `max_price1` double NOT NULL DEFAULT 0,
  `max_price2` double NOT NULL DEFAULT 0,
  `commission1` double NOT NULL DEFAULT 0,
  `commission2` double NOT NULL DEFAULT 0,
  `sign` int(11) NOT NULL DEFAULT 0,
  `id_group_commission` int(11) NOT NULL DEFAULT 0,
  `sorting_1` int(11) DEFAULT 0,
  `sorting_2` int(11) NOT NULL DEFAULT 0,
  `parent_url` varchar(191) DEFAULT NULL,
  `is_not_partner` int(11) NOT NULL DEFAULT 0,
  `individual_percentage` float NOT NULL DEFAULT 0,
  `fixed_payout` float NOT NULL DEFAULT 0,
  `export_label_param` varchar(191) DEFAULT NULL,
  `minimum_payout` float NOT NULL DEFAULT 0,
  `maximum_payout` float NOT NULL DEFAULT 0,
  `allow_export` int(11) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT 0,
  `commission_s1` double(8,2) NOT NULL DEFAULT 0.00,
  `commission_s2` double(8,2) NOT NULL DEFAULT 0.00,
  `allow_export_from` varchar(191) DEFAULT NULL,
  `allow_export_to` varchar(191) DEFAULT NULL,
  `profit_s` double NOT NULL DEFAULT 0,
  `profit_profile_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_restrict_editing` int(11) NOT NULL DEFAULT 0,
  `is_enabled_exchange` int(11) NOT NULL DEFAULT 0,
  `from_on_time` varchar(191) DEFAULT NULL,
  `to_on_time` varchar(191) DEFAULT NULL,
  `hidden_export_label_param` int(11) NOT NULL DEFAULT 0,
  `is_manual_min_price2` int(11) NOT NULL DEFAULT 0,
  `is_manual_max_price2` int(11) NOT NULL DEFAULT 0,
  `is_manual_min_price1` int(11) NOT NULL DEFAULT 0,
  `is_manual_max_price1` int(11) NOT NULL DEFAULT 0,
  `in_who_pay_commission` int(11) NOT NULL DEFAULT 0,
  `out_who_pay_commission` int(11) NOT NULL DEFAULT 0,
  `id_competitor` int(11) NOT NULL DEFAULT 0,
  `course_in` varchar(191) DEFAULT NULL,
  `course_out` varchar(191) DEFAULT NULL,
  `max_order_one_ip` int(11) NOT NULL DEFAULT 0 COMMENT 'Макс. кол-во заявок на обмен с одного IP',
  `max_order_one_account1` int(11) NOT NULL DEFAULT 0 COMMENT 'Макс. кол-во заявок на обмен с одного счета Отдаю',
  `max_order_one_account2` int(11) NOT NULL DEFAULT 0 COMMENT 'Макс. кол-во заявок на обмен с одного счета Получаю',
  `max_order_one_user` int(11) NOT NULL DEFAULT 0 COMMENT 'Макс. кол-во заявок на обмен от одного пользователя',
  `max_order_one_email` int(11) NOT NULL DEFAULT 0 COMMENT 'Макс. кол-во заявок на обмен с одного e-mail',
  `not_ip` text DEFAULT NULL,
  `cr_min_sum` varchar(191) DEFAULT NULL,
  `cr_max_sum` varchar(191) DEFAULT NULL,
  `cr_id_new_rate` int(11) NOT NULL DEFAULT 0,
  `cr_add_course` int(11) NOT NULL DEFAULT 0,
  `max_percent_partner` double(8,2) NOT NULL DEFAULT 0.00,
  `languages` varchar(191) DEFAULT NULL,
  `is_hidden_not_locale` int(11) NOT NULL DEFAULT 0,
  `sorting_tariffs` int(11) NOT NULL DEFAULT 0,
  `device` varchar(191) DEFAULT NULL,
  `is_hidden_not_device` int(11) NOT NULL DEFAULT 0,
  `bc_enable_your_course` int(11) NOT NULL DEFAULT 0,
  `bc_id_your_exchange` int(11) NOT NULL DEFAULT 0,
  `bc_your_add_course` double(8,2) NOT NULL DEFAULT 0.00,
  `rl_min2_course` varchar(191) DEFAULT NULL,
  `rl_max2_course` varchar(191) DEFAULT NULL,
  `rl_id_parser_exchange` int(11) NOT NULL DEFAULT 0,
  `rl_add_course` varchar(191) NOT NULL DEFAULT '0',
  `oth_comm_percent` double NOT NULL DEFAULT 0,
  `oth_comm_currency` double NOT NULL DEFAULT 0,
  `oth_min_comm` double NOT NULL DEFAULT 0,
  `auto_del_order_status` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`auto_del_order_status`)),
  `is_hidden_tariffs` int(11) NOT NULL DEFAULT 0,
  `is_holding_direction` int(11) NOT NULL DEFAULT 0,
  `reserve_max_limit` varchar(191) DEFAULT NULL,
  `reserve_limit_day` varchar(191) DEFAULT NULL,
  `reserve_limit_month` varchar(191) DEFAULT NULL,
  `is_num_transaction` varchar(191) DEFAULT NULL,
  `num_transaction_label` varchar(191) DEFAULT NULL,
  `max_order_one_ip_day` int(11) NOT NULL DEFAULT 0,
  `max_order_one_user_day` int(11) NOT NULL DEFAULT 0,
  `max_order_one_email_day` int(11) NOT NULL DEFAULT 0,
  `max_order_one_account1_day` int(11) NOT NULL DEFAULT 0,
  `max_order_one_account2_day` int(11) NOT NULL DEFAULT 0,
  `enable_file_parser_rate` int(11) NOT NULL DEFAULT 0,
  `id_file_parser_rate` int(11) NOT NULL DEFAULT 0,
  `is_enable_alt_bs_parser` int(11) NOT NULL DEFAULT 0,
  `is_disable_bs_error` int(11) NOT NULL DEFAULT 0,
  `profit_partner` double(8,2) NOT NULL DEFAULT 0.00,
  `is_note_tx` int(11) NOT NULL DEFAULT 0,
  `note_tx_label` varchar(191) DEFAULT NULL,
  `x19_mode` int(11) NOT NULL DEFAULT 0,
  `is_email_verification_modal` int(11) NOT NULL DEFAULT 0,
  `max_amount_newbie` double NOT NULL DEFAULT 0,
  `course_value` varchar(191) NOT NULL DEFAULT '0',
  `exchange_rate` varchar(191) DEFAULT NULL COMMENT 'Курс обмена',
  `is_error_rate` int(11) NOT NULL DEFAULT 0,
  `exchange_rate_str` varchar(191) DEFAULT NULL,
  `error_rate_text` varchar(191) DEFAULT NULL,
  `tech_name` varchar(191) DEFAULT NULL,
  `last_order_at` datetime DEFAULT NULL,
  `last_order_id` bigint(20) NOT NULL DEFAULT 0,
  `first_order_id` bigint(20) NOT NULL DEFAULT 0,
  `desc_exchange` text DEFAULT NULL,
  `id_partner_parser_rate` int(11) NOT NULL DEFAULT 0,
  `id_parser_formula_rate` int(11) NOT NULL DEFAULT 0,
  `type_output_requisites` int(11) NOT NULL DEFAULT 0,
  `formalization_text` longtext DEFAULT NULL,
  `min_count_exchanges_client` int(11) NOT NULL DEFAULT 0,
  `order_button_i_pay` text DEFAULT NULL,
  `order_button_i_pay_text` text DEFAULT NULL,
  `is_allow_telegram_bot` int(11) NOT NULL DEFAULT 0,
  `manual_rate_value` double NOT NULL DEFAULT 0,
  `parser_source_name` varchar(191) DEFAULT NULL,
  `other_docs` longtext DEFAULT NULL,
  `is_notify_exchange_amount` int(11) NOT NULL DEFAULT 0,
  `add_course1_s` varchar(191) NOT NULL DEFAULT '0',
  `your_add_course1_s` varchar(191) NOT NULL DEFAULT '0',
  `is_disable_auto_reg` int(11) NOT NULL DEFAULT 0,
  `label_floating` varchar(191) DEFAULT NULL,
  `label_delay` varchar(191) DEFAULT NULL,
  `oth_comm2_percent` double NOT NULL DEFAULT 0,
  `oth_comm2_currency` double NOT NULL DEFAULT 0,
  `oth_min2_comm` double NOT NULL DEFAULT 0,
  `pay_comm_percent` varchar(191) NOT NULL DEFAULT '0',
  `pay_comm_currency` varchar(191) NOT NULL DEFAULT '0',
  `pay_comm2_percent` varchar(191) NOT NULL DEFAULT '0',
  `pay_comm2_currency` varchar(191) NOT NULL DEFAULT '0',
  `pay_min_comm` varchar(191) NOT NULL DEFAULT '0',
  `pay_min2_comm` varchar(191) NOT NULL DEFAULT '0',
  `auto_del_order_day` int(11) NOT NULL DEFAULT 0,
  `auto_del_order_hour` int(11) NOT NULL DEFAULT 0,
  `auto_del_order_minute` int(11) NOT NULL DEFAULT 0,
  `is_enable_user_discount` int(11) NOT NULL DEFAULT 0,
  `desc_exchange_dop` longtext DEFAULT NULL,
  `type_profit_field` int(11) NOT NULL DEFAULT 0,
  `text_order_success` longtext DEFAULT NULL,
  `text_order_failed` longtext DEFAULT NULL,
  `text_order_handler` longtext DEFAULT NULL,
  `is_verified_account` int(11) NOT NULL DEFAULT 0,
  `text_order_confirm` longtext DEFAULT NULL,
  `order_button_i_confirm` text DEFAULT NULL,
  `notice_process_desc` longtext DEFAULT NULL,
  `multiplicity_type` int(11) NOT NULL DEFAULT 0,
  `multiplicity_amount` int(11) NOT NULL DEFAULT 0,
  `multiplicity_comment` longtext DEFAULT NULL,
  `label_floating_percent` varchar(191) NOT NULL DEFAULT '0',
  `type_reserve` int(11) NOT NULL DEFAULT 0,
  `direction_reserve` varchar(191) NOT NULL DEFAULT '0',
  `network_code` varchar(191) DEFAULT NULL,
  `merchant_day_limit_amount` varchar(191) NOT NULL DEFAULT '0',
  `merchant_month_limit_amount` varchar(191) NOT NULL DEFAULT '0',
  `merchant_min_amount_for_order` varchar(191) NOT NULL DEFAULT '0',
  `merchant_max_amount_for_order` varchar(191) NOT NULL DEFAULT '0',
  `merchant_day_limit` varchar(191) NOT NULL DEFAULT '0',
  `merchant_month_limit` varchar(191) NOT NULL DEFAULT '0',
  `network_code_out` varchar(191) DEFAULT NULL,
  `pay_day_limit_amount` varchar(191) NOT NULL DEFAULT '0',
  `pay_month_limit_amount` varchar(191) NOT NULL DEFAULT '0',
  `pay_min_amount_for_order` varchar(191) NOT NULL DEFAULT '0',
  `pay_max_amount_for_order` varchar(191) NOT NULL DEFAULT '0',
  `pay_day_limit` varchar(191) NOT NULL DEFAULT '0',
  `pay_month_limit` varchar(191) NOT NULL DEFAULT '0',
  `allow_autopay` int(11) NOT NULL DEFAULT 0,
  `fix_fee` varchar(191) NOT NULL DEFAULT '0',
  `floating_fee` varchar(191) NOT NULL DEFAULT '0',
  `fix_fee_time` int(11) NOT NULL DEFAULT 0,
  `floating_fee_time` int(11) NOT NULL DEFAULT 0,
  `fix_fee_statuses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fix_fee_statuses`)),
  `floating_fee_statuses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`floating_fee_statuses`)),
  `is_type_rate` int(11) NOT NULL DEFAULT 0,
  `floating_threshold_recount_down` double NOT NULL DEFAULT 0,
  `file_rate_source` varchar(20) NOT NULL DEFAULT 'fix',
  `floating_threshold_recount_up` double NOT NULL DEFAULT 0,
  `fix_recount_percent` double NOT NULL DEFAULT 0,
  `fix_is_enable_recount` int(11) NOT NULL DEFAULT 0,
  `fix_fee_display` int(11) NOT NULL DEFAULT 0,
  `floating_fee_display` int(11) NOT NULL DEFAULT 0,
  `type_rate_description` longtext DEFAULT NULL,
  `text_order_created_email` longtext DEFAULT NULL,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `interval_confirm_order` int(11) NOT NULL DEFAULT 0,
  `profit_partner_s` varchar(191) NOT NULL DEFAULT '0',
  `is_unique_amount_from` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Запрет на создание заявок с одинаковой суммой отдачи',
  `is_hidden_order_pay` int(11) NOT NULL DEFAULT 0,
  `title_selector_fee` text DEFAULT NULL,
  `text_selector_fee` longtext DEFAULT NULL,
  `card_verification_type` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0 - по умолчанию, 1 - индивидуально',
  `card_verification_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Настройки верификации карт (no/with)' CHECK (json_valid(`card_verification_rules`)),
  `identity_verification_type` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Тип верификации личности: 0 — по умолчанию (из валюты), 1 — от направления',
  `identity_verification_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON-настройки верификации личности (режим, пороги и др.)' CHECK (json_valid(`identity_verification_rules`)),
  `identity_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Краткое описание проверки личности (мультиязычно)' CHECK (json_valid(`identity_text`)),
  `identity_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Подробная инструкция по верификации личности (мультиязычно)' CHECK (json_valid(`identity_info`)),
  `direction_verification_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Мультиязычный текст верификации карты на уровне направления' CHECK (json_valid(`direction_verification_text`)),
  `direction_verification_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Мультиязычная подробная информация о верификации карты в направлении' CHECK (json_valid(`direction_verification_info`)),
  `no_verification_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`no_verification_description`)),
  `verification_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`verification_description`)),
  `method_request_payment` int(11) NOT NULL DEFAULT 0,
  `request_payment_text` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_exchange_cities`
--

CREATE TABLE `direction_exchange_cities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_direction_exchange` int(11) NOT NULL DEFAULT 0,
  `city_id` int(11) NOT NULL DEFAULT 0,
  `add_comm` varchar(191) NOT NULL,
  `param` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `min_price` double NOT NULL DEFAULT 0,
  `max_price` double NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `profit` varchar(191) NOT NULL DEFAULT '0',
  `instruction` longtext DEFAULT NULL,
  `information` longtext DEFAULT NULL,
  `profit_partner` varchar(191) NOT NULL DEFAULT '0',
  `profit_partner_s` varchar(191) NOT NULL DEFAULT '0',
  `profit_s` varchar(191) NOT NULL DEFAULT '0',
  `bid_value` varchar(191) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `exchange_text` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_exchange_error_log`
--

CREATE TABLE `direction_exchange_error_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_direction_exchange` int(11) NOT NULL DEFAULT 0,
  `direction_name` varchar(191) DEFAULT NULL,
  `where_error` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `level_risk` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_exchange_groups`
--

CREATE TABLE `direction_exchange_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_exchange_group_pivot`
--

CREATE TABLE `direction_exchange_group_pivot` (
  `group_id` bigint(20) UNSIGNED NOT NULL,
  `direction_exchange_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_exchange_merchants`
--

CREATE TABLE `direction_exchange_merchants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `gateway_merchant_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_exchange_min_price_logs`
--

CREATE TABLE `direction_exchange_min_price_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_direction_exchange` int(11) NOT NULL DEFAULT 0,
  `direction_name` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `exchange_rate` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_exchange_modes`
--

CREATE TABLE `direction_exchange_modes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_exchange_pay`
--

CREATE TABLE `direction_exchange_pay` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `gateway_pay_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_exchange_percent_amount`
--

CREATE TABLE `direction_exchange_percent_amount` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_direction_exchange` int(11) NOT NULL DEFAULT 0,
  `percentage` varchar(191) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `from_amount` varchar(191) NOT NULL DEFAULT '0',
  `to_amount` varchar(191) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_exchange_selector_fee`
--

CREATE TABLE `direction_exchange_selector_fee` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_direction_exchange` bigint(20) UNSIGNED NOT NULL,
  `fee` varchar(191) NOT NULL DEFAULT '0',
  `name` text DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`description`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_exchange_stats_daily`
--

CREATE TABLE `direction_exchange_stats_daily` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stat_date` date NOT NULL,
  `direction_exchange_id` bigint(20) UNSIGNED NOT NULL,
  `total_orders` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `completed_orders` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rejected_orders` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `cancelled_orders` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `processing_orders` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_amount_from_usd` decimal(30,8) NOT NULL DEFAULT 0.00000000,
  `total_amount_to_usd` decimal(30,8) NOT NULL DEFAULT 0.00000000,
  `total_profit_usd` decimal(30,8) NOT NULL DEFAULT 0.00000000,
  `unique_users` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `new_users` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_notification`
--

CREATE TABLE `direction_notification` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_direction_exchange` int(11) NOT NULL DEFAULT 0,
  `description` longtext DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_enabled_schedule` int(11) NOT NULL DEFAULT 0,
  `from_time` varchar(191) DEFAULT NULL,
  `to_time` varchar(191) DEFAULT NULL,
  `title` text DEFAULT NULL,
  `is_order_detail` int(11) NOT NULL DEFAULT 0,
  `text_color` varchar(191) DEFAULT NULL,
  `bg_color` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_requisites`
--

CREATE TABLE `direction_requisites` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `account_number` varchar(191) DEFAULT NULL,
  `view` int(11) NOT NULL DEFAULT 0,
  `limit_day` double(8,2) NOT NULL DEFAULT 0.00,
  `limit_month` double(8,2) NOT NULL DEFAULT 0.00,
  `limit_views` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `direction_templates`
--

CREATE TABLE `direction_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_type` int(11) NOT NULL DEFAULT 0,
  `text` longtext DEFAULT NULL,
  `type_view_info` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `dynamic_config_locks`
--

CREATE TABLE `dynamic_config_locks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `scope_type` varchar(50) NOT NULL,
  `scope_id` bigint(20) UNSIGNED DEFAULT NULL,
  `key` varchar(191) NOT NULL,
  `locked_until` datetime DEFAULT NULL,
  `locked_permanent` tinyint(1) NOT NULL DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `dynamic_config_migrations`
--

CREATE TABLE `dynamic_config_migrations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `migration` varchar(191) NOT NULL,
  `scope_type` varchar(50) NOT NULL DEFAULT 'global',
  `scope_id` bigint(20) UNSIGNED DEFAULT NULL,
  `batch` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `dynamic_config_migrations`
--

INSERT INTO `dynamic_config_migrations` (`id`, `migration`, `scope_type`, `scope_id`, `batch`, `created_at`, `updated_at`) VALUES
(1, '2025_12_18_012842_referral_fallback_code_currencies', 'global', NULL, 1, '2026-01-18 12:34:33', '2026-01-18 12:34:33');

-- --------------------------------------------------------

--
-- Структура таблицы `dynamic_config_settings`
--

CREATE TABLE `dynamic_config_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `scope_type` varchar(50) NOT NULL,
  `scope_id` bigint(20) UNSIGNED DEFAULT NULL,
  `key` varchar(191) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`value`)),
  `expires_at` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `dynamic_config_settings`
--

INSERT INTO `dynamic_config_settings` (`id`, `scope_type`, `scope_id`, `key`, `value`, `expires_at`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 'global', NULL, 'banners.hide_nav', '0', NULL, NULL, '2026-01-18 12:21:04', '2026-01-18 12:21:04'),
(2, 'global', NULL, 'banners.is_autoplay', 'false', NULL, NULL, '2026-01-18 12:21:04', '2026-01-18 12:21:04'),
(3, 'global', NULL, 'banners.timeout', '0', NULL, NULL, '2026-01-18 12:21:04', '2026-01-18 12:21:04'),
(4, 'global', NULL, 'advantage.advantage_col', '3', NULL, NULL, '2026-01-18 12:21:04', '2026-01-18 12:21:04'),
(5, 'global', NULL, 'advantage.advantage_gutter_size', '\"10px\"', NULL, NULL, '2026-01-18 12:21:04', '2026-01-18 12:21:04'),
(6, 'global', NULL, 'advantage.advantage_row_height', '\"2:1\"', NULL, NULL, '2026-01-18 12:21:04', '2026-01-18 12:21:04'),
(7, 'global', NULL, 'advantage.is_advantage_style', 'false', NULL, NULL, '2026-01-18 12:21:04', '2026-01-18 12:21:04'),
(8, 'plugins', NULL, 'bin_inspector.api_key', 'null', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(9, 'plugins', NULL, 'bin_inspector.driver', 'null', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(10, 'plugins', NULL, 'bin_inspector.ids_currencies', 'null', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(11, 'plugins', NULL, 'bin_inspector.is_api', '0', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(12, 'plugins', NULL, 'bin_inspector.save_data', '0', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(13, 'plugins', NULL, 'bestchange_blacklist.api_id', '\"\"', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(14, 'plugins', NULL, 'bestchange_blacklist.api_key', '\"\"', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(15, 'plugins', NULL, 'bestchange_blacklist.categories', '[]', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(16, 'plugins', NULL, 'bestchange_blacklist.columns', '\"\"', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(17, 'plugins', NULL, 'bestchange_blacklist.is_status', '0', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(18, 'plugins', NULL, 'bestchange_blacklist.method', '0', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(19, 'plugins', NULL, 'bestchange_blacklist.type', '\"\"', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(20, 'plugins', NULL, 'bestchange.api_key', '\"\"', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(21, 'plugins', NULL, 'bestchange.blacklist', '[]', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(22, 'plugins', NULL, 'bestchange.cities', '[]', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(23, 'plugins', NULL, 'bestchange.currencies', '[]', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(24, 'plugins', NULL, 'bestchange.interval', '0', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(25, 'plugins', NULL, 'bestchange.is_enable', 'false', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(26, 'plugins', NULL, 'bestchange.is_log_error', 'false', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(27, 'plugins', NULL, 'bestchange.position', '0', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(28, 'plugins', NULL, 'bestchange.site_version', '\"\"', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(29, 'plugins', NULL, 'bestchange.timeout', '0', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(30, 'plugins', NULL, 'bestchange.type_position', '\"\"', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(31, 'plugins', NULL, 'bestchange.whitelist', '[]', NULL, NULL, '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(32, 'global', NULL, 'default_referral_program_id', '1', NULL, NULL, '2026-01-18 12:21:04', '2026-01-18 12:21:04'),
(33, 'global', NULL, 'type_working_mode', '2', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(34, 'global', NULL, 'admin_currencies_column_hidden_columns', '\"pc,code,xml,reserve,receiving,sending,icon\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(35, 'global', NULL, 'admin_competitor_parser_hidden_columns', '\"name,id_competitor,course,type,created_at,last_updated,status,direction_exchange\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(36, 'global', NULL, 'admin_mass_direction_editor_hidden_columns', '\"direction_exchange,text,status\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(37, 'global', NULL, 'admin_requisites_info_hidden_columns', '\"name,value,attached_requisites,created_at,updated_at,status\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(38, 'global', NULL, 'admin_codes_hidden_columns', '\"name,symbol,currencies,exchange_rate\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(39, 'global', NULL, 'admin_directions_hidden_columns', '\"direction,exchangeRate,profit,status,minAmount,maxAmount,otherFeeIn,pay,merchant,otherFeeOut\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:52:23'),
(40, 'global', NULL, 'admin_user_hidden_columns', '\"name,email,count_exchange,balance,ip_address_browser,created_at\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(41, 'global', NULL, 'admin_merchant_hidden_columns', '\"name,alias,security,count_order,status,summary_usd,created_at,updated_at\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(42, 'global', NULL, 'admin_parser_formula_hidden_columns', '\"title,course,created_at,updated_at,status\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(43, 'global', NULL, 'admin_requisites_hidden_columns', '\"currency,account_number,views,exchange_today,exchange_month,status\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(44, 'global', NULL, 'admin_payment_systems_hidden_columns', '\"logo,name,updated_at\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(45, 'global', NULL, 'admin_crypto_parser_hidden_columns', '\"name,course,type,attached_direction,last_updated,status\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(46, 'global', NULL, 'admin_reserves_hidden_columns', '\"currency,amount,created_at,last_updated,events,group\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(47, 'global', NULL, 'admin_autopayment_hidden_columns', '\"name,alias,attached_currencies,total_orders,total_to_usd,created_at,last_updated,status\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(48, 'global', NULL, 'admin_bestchange_parser_hidden_columns', '\"direction,course,information,position,created_at,last_updated,status\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(49, 'global', NULL, 'admin_telegram_notification_hidden_columns', '\"name,channel,status,last_updated\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(50, 'global', NULL, 'admin_bonuses_discount_hidden_columns', '\"amount,percent,created_at,last_updated,title\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(51, 'global', NULL, 'admin_verifications_card_hidden_columns', '\"currency,ip_address,account_number,photo,user,created_at,status,id_order\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(52, 'global', NULL, 'admin_order_statuses_log_hidden_columns', '\"number,user,old_status,new_status,give_price,receiving_price,exchange_rate\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(53, 'global', NULL, 'admin_file_parser_hidden_columns', '\"name,id_group,course,created_at,last_updated,status,direction_exchange\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(54, 'global', NULL, 'max_number_format_reserve', '10', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(55, 'global', NULL, 'env_app_locale', '\"ru\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(56, 'global', NULL, 'app_multilanguage_locale', '\"ru,en\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(57, 'global', NULL, 'interval_rates', '15', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(58, 'global', NULL, 'displayed_statuses', '\"3,2,4,5,7,9,12\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:38:54'),
(59, 'global', NULL, 'admin_reserves_column_hidden_columns', '\"currency,summa,last_updated\"', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(60, 'global', NULL, 'login_max_attempts', '5', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(61, 'global', NULL, 'login_decay_minutes', '5', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(62, 'global', NULL, 'max_decimal_places', '18', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(63, 'global', NULL, 'is_enable_page_content_accordion', '1', NULL, NULL, '2026-01-18 12:32:16', '2026-01-18 12:32:16'),
(64, 'global', NULL, 'global.referral_fallback_code_currencies', '\"\"', NULL, NULL, '2026-01-18 12:34:33', '2026-01-18 12:34:33'),
(65, 'global', NULL, 'is_discount_disabled', '1', NULL, NULL, '2026-01-18 12:38:16', '2026-01-18 12:38:16'),
(66, 'global', NULL, 'merchant_error_handling_strategy', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(67, 'global', NULL, 'client_id_type_for_order', '1', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(68, 'global', NULL, 'type_recalculation_order', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(69, 'global', NULL, 'is_save_order_data_to_file', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(70, 'global', NULL, 'is_disabled_email_field_optional', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(71, 'global', NULL, 'max_num_autopay_button', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(72, 'global', NULL, 'auto_ban_for_scam_order', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(73, 'global', NULL, 'scam_ban_mode', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(74, 'global', NULL, 'scam_ban_step1_minutes', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(75, 'global', NULL, 'scam_ban_step2_hours', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(76, 'global', NULL, 'scam_ban_step3_days', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(77, 'global', NULL, 'is_blocked_spam_order', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(78, 'global', NULL, 'allow_order_blacklist', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(79, 'global', NULL, 'disable_check_display', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(80, 'global', NULL, 'is_recount_to_merchant', '1', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(81, 'global', NULL, 'is_enabled_log_merchant', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(82, 'global', NULL, 'type_instruction_merchant', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(83, 'global', NULL, 'is_disabled_order_merchant', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(84, 'global', NULL, 'is_auto_archived', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(85, 'global', NULL, 'archived_days', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(86, 'global', NULL, 'archived_statuses', '\"\"', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(87, 'global', NULL, 'max_time_task', '1800', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(88, 'global', NULL, 'max_num_order_user', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(89, 'global', NULL, 'is_display_exchange_rate', '0', NULL, NULL, '2026-01-18 12:38:54', '2026-01-18 12:38:54'),
(90, 'global', NULL, 'auto_register', '0', NULL, NULL, '2026-01-18 12:39:10', '2026-01-18 12:39:10'),
(91, 'global', NULL, 'enable_email_domain_protection', '0', NULL, NULL, '2026-01-18 12:39:10', '2026-01-18 12:39:10'),
(92, 'global', NULL, 'is_saved_user_stories', '0', NULL, NULL, '2026-01-18 12:39:10', '2026-01-18 12:39:10'),
(93, 'global', NULL, 'is_dot_not_remember_data_order', '0', NULL, NULL, '2026-01-18 12:39:10', '2026-01-18 12:39:10'),
(94, 'global', NULL, 'is_geo_ip', '0', NULL, NULL, '2026-01-18 12:39:10', '2026-01-18 12:39:10'),
(95, 'global', NULL, 'auto_disabled_user_account', '0', NULL, NULL, '2026-01-18 12:39:10', '2026-01-18 12:39:10'),
(96, 'global', NULL, 'new_user_registration_cleanup_days', '0', NULL, NULL, '2026-01-18 12:39:10', '2026-01-18 12:39:10'),
(97, 'global', NULL, 'is_verified_referral', '0', NULL, NULL, '2026-01-18 12:39:10', '2026-01-18 12:39:10'),
(98, 'global', NULL, 'is_remember_login', '0', NULL, NULL, '2026-01-18 12:39:10', '2026-01-18 12:39:10'),
(99, 'global', NULL, 'is_verified_payouts_bonus', '0', NULL, NULL, '2026-01-18 12:39:10', '2026-01-18 12:39:10'),
(100, 'global', NULL, 'ids_currencies_account_my_wallets', '\"\"', NULL, NULL, '2026-01-18 12:39:10', '2026-01-18 12:39:10'),
(101, 'language', 1, 'language.username_new_user', '{\"ru\":\":randomUser:\"}', NULL, NULL, '2026-01-18 12:39:10', '2026-01-18 12:39:10'),
(102, 'global', NULL, 'iex_interface_dynamic_colors', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(103, 'global', NULL, 'interface_exchange', '1', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(104, 'global', NULL, 'block_visible_reserve', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(105, 'global', NULL, 'template_block_reserve', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(106, 'global', NULL, 'count_template_block_reserve_2', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(107, 'global', NULL, 'allow_filter_currency', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(108, 'global', NULL, 'display_currency_iso_codes', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(109, 'global', NULL, 'is_enabled_out_course_reserve', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(110, 'global', NULL, 'currency_display_type_dynamic', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(111, 'global', NULL, 'visible_partners', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(112, 'global', NULL, 'visible_last_exchange', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(113, 'global', NULL, 'visible_news', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(114, 'global', NULL, 'visible_reviews', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(115, 'global', NULL, 'visible_advantage', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(116, 'global', NULL, 'visible_popular_exchange', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(117, 'global', NULL, 'visible_statistics', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(118, 'global', NULL, 'is_enable_footer', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(119, 'global', NULL, 'iex_interface_header_style', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(120, 'global', NULL, 'count_last_exchange', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(121, 'global', NULL, 'count_reviews_exchange', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(122, 'global', NULL, 'count_news_exchange', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(123, 'global', NULL, 'count_popular_exchange', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(124, 'global', NULL, 'iex_interface_text_entry', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(125, 'global', NULL, 'is_style_agreement_rules', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(126, 'global', NULL, 'is_style_agreement_checkbox', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(127, 'global', NULL, 'is_exchange_rules_remember_checkbox', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(128, 'global', NULL, 'type_view_field_exchange_amount', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(129, 'global', NULL, 'is_visible_policy_cookie', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(130, 'global', NULL, 'visible_banner', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(131, 'global', NULL, 'visible_banner_mobile', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(132, 'global', NULL, 'is_hide_currency_reserve', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(133, 'global', NULL, 'is_reserves_rounding', '0', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(134, 'global', NULL, 'ids_currencies_reserves_home_view', '\"\"', NULL, NULL, '2026-01-18 12:39:47', '2026-01-18 12:39:47'),
(135, 'global', NULL, 'num_direction_paginate', '20', NULL, NULL, '2026-01-18 12:52:23', '2026-01-18 12:52:23'),
(136, 'global', NULL, 'iex_order_live_auto_update_page', '1', NULL, NULL, '2026-01-18 12:52:52', '2026-01-18 12:52:52'),
(137, 'global', NULL, 'iex_order_live_auto_update_timeout', '10', NULL, NULL, '2026-01-18 12:52:52', '2026-01-18 12:52:52'),
(138, 'global', NULL, 'iex_order_live_sound_notification', '0', NULL, NULL, '2026-01-18 12:52:52', '2026-01-18 12:52:52'),
(139, 'global', NULL, 'iex_order_live_statuses', '\"2,7\"', NULL, NULL, '2026-01-18 12:52:52', '2026-01-18 12:52:55'),
(140, 'global', NULL, 'iex_order_is_hidden_order_opened', '0', NULL, NULL, '2026-01-18 12:52:52', '2026-01-18 12:52:52'),
(141, 'global', NULL, 'iex_order_live_is_request_payment', '0', NULL, NULL, '2026-01-18 12:52:52', '2026-01-18 12:52:52');

-- --------------------------------------------------------

--
-- Структура таблицы `dynamic_config_snapshots`
--

CREATE TABLE `dynamic_config_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `scope_type` varchar(50) NOT NULL,
  `scope_id` bigint(20) UNSIGNED DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `dynamic_config_versions`
--

CREATE TABLE `dynamic_config_versions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `scope_type` varchar(50) NOT NULL,
  `scope_id` bigint(20) UNSIGNED DEFAULT NULL,
  `key` varchar(191) NOT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `employees`
--

CREATE TABLE `employees` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `started_work` timestamp NULL DEFAULT NULL,
  `job_from` int(11) NOT NULL DEFAULT 0,
  `job_to` int(11) NOT NULL DEFAULT 0,
  `salary` float NOT NULL DEFAULT 0,
  `fines` int(11) NOT NULL DEFAULT 0,
  `total` float DEFAULT 0,
  `percent` int(11) NOT NULL DEFAULT 0,
  `bonus_rub` float NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `event_employees`
--

CREATE TABLE `event_employees` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_employees` int(11) NOT NULL,
  `id_task` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `amount` double(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `export_data`
--

CREATE TABLE `export_data` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `is_cron` varchar(191) NOT NULL DEFAULT '0',
  `is_filter` int(11) NOT NULL DEFAULT 0,
  `is_allow_filter` int(11) NOT NULL DEFAULT 0,
  `format_export` varchar(191) DEFAULT NULL,
  `count` int(11) NOT NULL DEFAULT 0,
  `export_value` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `export_data`
--

INSERT INTO `export_data` (`id`, `name`, `is_cron`, `is_filter`, `is_allow_filter`, `format_export`, `count`, `export_value`, `created_at`, `updated_at`) VALUES
(1, 'Пользователи', '1', 1, 0, 'XLSX', 67, 'UsersExport', NULL, '2019-12-11 23:30:05'),
(2, 'Заявки', '0', 0, 0, 'XLSX', 7, 'OrdersExport', NULL, '2019-03-10 13:05:02'),
(3, 'Валюты', '0', 0, 1, 'XLSX', 1, 'CurrencyExport', NULL, '2019-03-10 12:50:54'),
(4, 'Направлении обменов', '0', 1, 0, 'XLSX', 1, 'DirectionExport', NULL, '2019-03-10 12:57:03'),
(5, 'Реквизиты', '0', 1, 0, 'XLSX', 4, 'RequisitesExport', NULL, '2019-03-14 17:59:03');

-- --------------------------------------------------------

--
-- Структура таблицы `export_rates_files`
--

CREATE TABLE `export_rates_files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `filename` varchar(191) DEFAULT NULL,
  `type_file` int(11) NOT NULL DEFAULT 0,
  `number_format` int(11) NOT NULL DEFAULT 10,
  `type_number_format` int(11) NOT NULL DEFAULT 0,
  `in_type_tofee` int(11) NOT NULL DEFAULT 0,
  `in_type_fromfee` int(11) NOT NULL DEFAULT 0,
  `cron_update` int(11) NOT NULL DEFAULT 0,
  `is_offline_operator` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `ids_excluded_directions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ids_excluded_directions`)),
  `is_view` int(11) NOT NULL DEFAULT 0,
  `description` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `export_rates_files`
--

INSERT INTO `export_rates_files` (`id`, `filename`, `type_file`, `number_format`, `type_number_format`, `in_type_tofee`, `in_type_fromfee`, `cron_update`, `is_offline_operator`, `status`, `created_at`, `updated_at`, `ids_excluded_directions`, `is_view`, `description`) VALUES
(1, 'valuta', 0, 10, 0, 0, 0, 0, 0, 1, '2026-01-18 12:39:31', '2026-01-18 12:39:41', '[]', 0, '{\"ru\":\"Для мониторингов\"}');

-- --------------------------------------------------------

--
-- Структура таблицы `extra_out_profiles`
--

CREATE TABLE `extra_out_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `field_label` longtext DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `min_payout_amount` varchar(191) NOT NULL DEFAULT '0',
  `min_trigger_amount` varchar(191) NOT NULL DEFAULT '0',
  `max_fields` smallint(5) UNSIGNED NOT NULL DEFAULT 20,
  `description` longtext DEFAULT NULL,
  `button_name` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `extra_out_profile_currencies`
--

CREATE TABLE `extra_out_profile_currencies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `profile_id` bigint(20) UNSIGNED NOT NULL,
  `currency_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `e_voucher_codes`
--

CREATE TABLE `e_voucher_codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `account` varchar(191) DEFAULT NULL,
  `amount` varchar(191) DEFAULT NULL,
  `batch_num` varchar(191) DEFAULT NULL,
  `voucher_num` varchar(191) DEFAULT NULL,
  `voucher_code` varchar(191) DEFAULT NULL,
  `voucher_amount` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(191) DEFAULT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `faq`
--

CREATE TABLE `faq` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_group` int(11) NOT NULL,
  `title` text DEFAULT NULL,
  `text` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `faq_category`
--

CREATE TABLE `faq_category` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `favorites_links`
--

CREATE TABLE `favorites_links` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `link` varchar(191) DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `file_parser_groups`
--

CREATE TABLE `file_parser_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `link` varchar(191) NOT NULL DEFAULT '0',
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `file_parser_rates`
--

CREATE TABLE `file_parser_rates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `exchange_in` varchar(191) DEFAULT NULL,
  `exchange_out` varchar(191) DEFAULT NULL,
  `id_group` int(11) NOT NULL DEFAULT 0,
  `value` int(11) NOT NULL DEFAULT 0,
  `summa` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `number_format` int(11) NOT NULL DEFAULT 0,
  `type` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `code` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `filter_currency`
--

CREATE TABLE `filter_currency` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `sorting` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `icon` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `filter_currency`
--

INSERT INTO `filter_currency` (`id`, `name`, `sorting`, `created_at`, `updated_at`, `icon`) VALUES
(1, '{\"ru\":\"Фиат\",\"en\":null}', 0, '2017-10-20 04:49:18', '2026-01-18 12:52:02', ''),
(3, '{\"ru\":\"Crypto\",\"en\":null}', 2, '2017-10-20 04:49:38', '2026-01-18 12:52:08', '');

-- --------------------------------------------------------

--
-- Структура таблицы `fine_employees`
--

CREATE TABLE `fine_employees` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_employees` int(11) NOT NULL DEFAULT 0,
  `amount` int(11) NOT NULL DEFAULT 0,
  `text` text DEFAULT NULL,
  `number` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `firewall`
--

CREATE TABLE `firewall` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(39) NOT NULL,
  `whitelisted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `gateways_merchants`
--

CREATE TABLE `gateways_merchants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `instruction_payment` text DEFAULT NULL,
  `allow_ip_address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `security_hash` varchar(191) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `is_deny_ip_address` int(11) NOT NULL DEFAULT 0,
  `day_limit_merchant` int(11) NOT NULL DEFAULT 0,
  `max_limit_amount_order` double NOT NULL DEFAULT 0,
  `amount_fault` double NOT NULL DEFAULT 0,
  `day_limit_amount_merchant` double NOT NULL DEFAULT 0,
  `is_enable_merchant_button` int(11) NOT NULL DEFAULT 0,
  `total_usd` varchar(191) DEFAULT NULL,
  `last_order_id` varchar(191) DEFAULT NULL,
  `is_config_done` int(11) NOT NULL DEFAULT 0,
  `pay_amount` int(11) NOT NULL DEFAULT 0,
  `credit_amount` int(11) NOT NULL DEFAULT 0,
  `order_num` int(11) NOT NULL DEFAULT 0,
  `ext_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ext_options`)),
  `alias` varchar(191) DEFAULT NULL,
  `required_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_params`)),
  `filename` varchar(191) DEFAULT NULL,
  `month_limit_amount_merchant` varchar(191) NOT NULL DEFAULT '0',
  `min_amount_for_per_order` varchar(191) NOT NULL DEFAULT '0',
  `max_amount_for_per_order` varchar(191) NOT NULL DEFAULT '0',
  `month_limit_merchant` varchar(191) NOT NULL DEFAULT '0',
  `priority` int(11) NOT NULL DEFAULT 0,
  `status_invalid_min_amount` int(11) NOT NULL DEFAULT 0,
  `status_invalid_max_amount` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `gateways_payments`
--

CREATE TABLE `gateways_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_proxy` int(11) NOT NULL DEFAULT 0,
  `comment` text DEFAULT NULL,
  `manual_pay_order` int(11) NOT NULL DEFAULT 0,
  `volume_to_usd` varchar(191) DEFAULT NULL,
  `last_order_id` int(11) NOT NULL DEFAULT 0,
  `order_count` int(11) NOT NULL DEFAULT 0,
  `mass_coins` varchar(191) NOT NULL DEFAULT '0' COMMENT 'Валюты, используемые для массовых выплат',
  `pay_amount_type` int(11) NOT NULL DEFAULT 0,
  `direction` int(11) NOT NULL DEFAULT 0,
  `alias` varchar(191) DEFAULT NULL,
  `ext_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ext_options`)),
  `filename` varchar(191) DEFAULT NULL,
  `day_limit_amount_pay` varchar(191) NOT NULL DEFAULT '0',
  `month_limit_amount_pay` varchar(191) NOT NULL DEFAULT '0',
  `min_amount_for_per_order` varchar(191) NOT NULL DEFAULT '0',
  `max_amount_for_per_order` varchar(191) NOT NULL DEFAULT '0',
  `day_limit_pay` int(11) NOT NULL DEFAULT 0,
  `month_limit_pay` int(11) NOT NULL DEFAULT 0,
  `allow_autopay` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `gateway_health_statuses`
--

CREATE TABLE `gateway_health_statuses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(16) NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `gateway_alias` varchar(64) NOT NULL,
  `status` varchar(16) NOT NULL,
  `http_status` smallint(6) DEFAULT NULL,
  `latency_ms` int(11) DEFAULT NULL,
  `message` varchar(191) DEFAULT NULL,
  `fail_streak` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_ok_at` timestamp NULL DEFAULT NULL,
  `checked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `gateway_replays`
--

CREATE TABLE `gateway_replays` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `replay_key` varchar(64) NOT NULL,
  `gateway` varchar(64) NOT NULL,
  `operation` varchar(64) NOT NULL,
  `direction` varchar(32) NOT NULL,
  `http_method` varchar(16) NOT NULL,
  `url` text DEFAULT NULL,
  `request_headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_headers`)),
  `request_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_body`)),
  `response_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_json`)),
  `http_status` smallint(5) UNSIGNED NOT NULL DEFAULT 200,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `gateway_secret_access_logs`
--

CREATE TABLE `gateway_secret_access_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `scope` varchar(32) NOT NULL,
  `action` varchar(16) NOT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `geo_country_list`
--

CREATE TABLE `geo_country_list` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(191) DEFAULT NULL,
  `value` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `geo_country_list`
--

INSERT INTO `geo_country_list` (`id`, `code`, `value`, `created_at`, `updated_at`) VALUES
(1, 'AU', '{\"ru\":\"Австралия\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(2, 'AT', '{\"ru\":\"Австрия\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(3, 'AZ', '{\"ru\":\"Азербайджан\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(4, 'AX', '{\"ru\":\"Аландские о-ва\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(5, 'AL', '{\"ru\":\"Албания\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(6, 'DZ', '{\"ru\":\"Алжир\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(7, 'AS', '{\"ru\":\"Американское Самоа\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(8, 'AI', '{\"ru\":\"Ангилья\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(9, 'AO', '{\"ru\":\"Ангола\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(10, 'AD', '{\"ru\":\"Андорра\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(11, 'AQ', '{\"ru\":\"Антарктида\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(12, 'AG', '{\"ru\":\"Антигуа и Барбуда\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(13, 'AR', '{\"ru\":\"Аргентина\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(14, 'AM', '{\"ru\":\"Армения\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(15, 'AW', '{\"ru\":\"Аруба\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(16, 'AF', '{\"ru\":\"Афганистан\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(17, 'BS', '{\"ru\":\"Багамы\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(18, 'BD', '{\"ru\":\"Бангладеш\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(19, 'BB', '{\"ru\":\"Барбадос\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(20, 'BH', '{\"ru\":\"Бахрейн\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(21, 'BY', '{\"ru\":\"Беларусь\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(22, 'BZ', '{\"ru\":\"Белиз\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(23, 'BE', '{\"ru\":\"Бельгия\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(24, 'BJ', '{\"ru\":\"Бенин\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(25, 'BM', '{\"ru\":\"Бермудские о-ва\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(26, 'BG', '{\"ru\":\"Болгария\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(27, 'BO', '{\"ru\":\"Боливия\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(28, 'BQ', '{\"ru\":\"Бонэйр, Синт-Эстатиус и Саба\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(29, 'BA', '{\"ru\":\"Босния и Герцеговина\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(30, 'BW', '{\"ru\":\"Ботсвана\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(31, 'BR', '{\"ru\":\"Бразилия\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(32, 'IO', '{\"ru\":\"Британская территория в Индийском океане\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(33, 'BN', '{\"ru\":\"Бруней-Даруссалам\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(34, 'BF', '{\"ru\":\"Буркина-Фасо\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(35, 'BI', '{\"ru\":\"Бурунди\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(36, 'BT', '{\"ru\":\"Бутан\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(37, 'VU', '{\"ru\":\"Вануату\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(38, 'VA', '{\"ru\":\"Ватикан\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(39, 'GB', '{\"ru\":\"Великобритания\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(40, 'HU', '{\"ru\":\"Венгрия\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(41, 'VE', '{\"ru\":\"Венесуэла\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(42, 'VG', '{\"ru\":\"Виргинские о-ва (Великобритания)\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(43, 'VI', '{\"ru\":\"Виргинские о-ва (США)\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(44, 'UM', '{\"ru\":\"Внешние малые о-ва (США)\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(45, 'TL', '{\"ru\":\"Восточный Тимор\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(46, 'VN', '{\"ru\":\"Вьетнам\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(47, 'GA', '{\"ru\":\"Габон\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(48, 'HT', '{\"ru\":\"Гаити\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(49, 'GY', '{\"ru\":\"Гайана\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(50, 'GM', '{\"ru\":\"Гамбия\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(51, 'GH', '{\"ru\":\"Гана\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(52, 'GP', '{\"ru\":\"Гваделупа\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(53, 'GT', '{\"ru\":\"Гватемала\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(54, 'GN', '{\"ru\":\"Гвинея\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(55, 'GW', '{\"ru\":\"Гвинея-Бисау\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(56, 'DE', '{\"ru\":\"Германия\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(57, 'GG', '{\"ru\":\"Гернси\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(58, 'GI', '{\"ru\":\"Гибралтар\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(59, 'HN', '{\"ru\":\"Гондурас\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(60, 'HK', '{\"ru\":\"Гонконг (САР)\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(61, 'GD', '{\"ru\":\"Гренада\"}', '2023-04-09 10:47:37', '2024-07-29 06:24:25'),
(62, 'GL', '{\"ru\":\"Гренландия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(63, 'GR', '{\"ru\":\"Греция\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(64, 'GE', '{\"ru\":\"Грузия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(65, 'GU', '{\"ru\":\"Гуам\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(66, 'DK', '{\"ru\":\"Дания\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(67, 'JE', '{\"ru\":\"Джерси\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(68, 'DJ', '{\"ru\":\"Джибути\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(69, 'DM', '{\"ru\":\"Доминика\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(70, 'DO', '{\"ru\":\"Доминиканская Республика\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(71, 'EG', '{\"ru\":\"Египет\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(72, 'ZM', '{\"ru\":\"Замбия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(73, 'EH', '{\"ru\":\"Западная Сахара\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(74, 'ZW', '{\"ru\":\"Зимбабве\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(75, 'IL', '{\"ru\":\"Израиль\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(76, 'IN', '{\"ru\":\"Индия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(77, 'ID', '{\"ru\":\"Индонезия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(78, 'JO', '{\"ru\":\"Иордания\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(79, 'IQ', '{\"ru\":\"Ирак\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(80, 'IR', '{\"ru\":\"Иран\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(81, 'IE', '{\"ru\":\"Ирландия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(82, 'IS', '{\"ru\":\"Исландия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(83, 'ES', '{\"ru\":\"Испания\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(84, 'IT', '{\"ru\":\"Италия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(85, 'YE', '{\"ru\":\"Йемен\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:25'),
(86, 'CV', '{\"ru\":\"Кабо-Верде\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(87, 'KZ', '{\"ru\":\"Казахстан\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(88, 'KH', '{\"ru\":\"Камбоджа\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(89, 'CM', '{\"ru\":\"Камерун\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(90, 'CA', '{\"ru\":\"Канада\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(91, 'QA', '{\"ru\":\"Катар\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(92, 'KE', '{\"ru\":\"Кения\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(93, 'CY', '{\"ru\":\"Кипр\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(94, 'KG', '{\"ru\":\"Киргизия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(95, 'KI', '{\"ru\":\"Кирибати\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(96, 'CN', '{\"ru\":\"Китай\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(97, 'KP', '{\"ru\":\"КНДР\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(98, 'CC', '{\"ru\":\"Кокосовые о-ва\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(99, 'CO', '{\"ru\":\"Колумбия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(100, 'KM', '{\"ru\":\"Коморы\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(101, 'CG', '{\"ru\":\"Конго - Браззавиль\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(102, 'CD', '{\"ru\":\"Конго - Киншаса\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(103, 'CR', '{\"ru\":\"Коста-Рика\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(104, 'CI', '{\"ru\":\"Кот-д’Ивуар\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(105, 'CU', '{\"ru\":\"Куба\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(106, 'KW', '{\"ru\":\"Кувейт\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(107, 'CW', '{\"ru\":\"Кюрасао\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(108, 'LA', '{\"ru\":\"Лаос\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(109, 'LV', '{\"ru\":\"Латвия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(110, 'LS', '{\"ru\":\"Лесото\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(111, 'LR', '{\"ru\":\"Либерия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(112, 'LB', '{\"ru\":\"Ливан\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(113, 'LY', '{\"ru\":\"Ливия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(114, 'LT', '{\"ru\":\"Литва\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(115, 'LI', '{\"ru\":\"Лихтенштейн\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(116, 'LU', '{\"ru\":\"Люксембург\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(117, 'MU', '{\"ru\":\"Маврикий\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(118, 'MR', '{\"ru\":\"Мавритания\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(119, 'MG', '{\"ru\":\"Мадагаскар\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(120, 'YT', '{\"ru\":\"Майотта\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(121, 'MO', '{\"ru\":\"Макао (САР)\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(122, 'MW', '{\"ru\":\"Малави\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(123, 'MY', '{\"ru\":\"Малайзия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(124, 'ML', '{\"ru\":\"Мали\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(125, 'MV', '{\"ru\":\"Мальдивы\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(126, 'MT', '{\"ru\":\"Мальта\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(127, 'MA', '{\"ru\":\"Марокко\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(128, 'MQ', '{\"ru\":\"Мартиника\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(129, 'MH', '{\"ru\":\"Маршалловы Острова\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(130, 'MX', '{\"ru\":\"Мексика\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(131, 'MZ', '{\"ru\":\"Мозамбик\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(132, 'MD', '{\"ru\":\"Молдова\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(133, 'MC', '{\"ru\":\"Монако\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(134, 'MN', '{\"ru\":\"Монголия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(135, 'MS', '{\"ru\":\"Монтсеррат\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(136, 'MM', '{\"ru\":\"Мьянма (Бирма)\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(137, 'NA', '{\"ru\":\"Намибия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(138, 'NR', '{\"ru\":\"Науру\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(139, 'NP', '{\"ru\":\"Непал\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(140, 'NE', '{\"ru\":\"Нигер\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(141, 'NG', '{\"ru\":\"Нигерия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(142, 'NL', '{\"ru\":\"Нидерланды\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(143, 'NI', '{\"ru\":\"Никарагуа\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(144, 'NU', '{\"ru\":\"Ниуэ\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(145, 'NZ', '{\"ru\":\"Новая Зеландия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(146, 'NC', '{\"ru\":\"Новая Каледония\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(147, 'NO', '{\"ru\":\"Норвегия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(148, 'BV', '{\"ru\":\"о-в Буве\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(149, 'IM', '{\"ru\":\"о-в Мэн\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(150, 'NF', '{\"ru\":\"о-в Норфолк\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(151, 'CX', '{\"ru\":\"о-в Рождества\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(152, 'SH', '{\"ru\":\"о-в Св. Елены\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(153, 'PN', '{\"ru\":\"о-ва Питкэрн\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(154, 'TC', '{\"ru\":\"о-ва Тёркс и Кайкос\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(155, 'HM', '{\"ru\":\"о-ва Херд и Макдональд\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(156, 'AE', '{\"ru\":\"ОАЭ\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(157, 'OM', '{\"ru\":\"Оман\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(158, 'KY', '{\"ru\":\"Острова Кайман\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(159, 'CK', '{\"ru\":\"Острова Кука\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(160, 'PK', '{\"ru\":\"Пакистан\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(161, 'PW', '{\"ru\":\"Палау\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(162, 'PS', '{\"ru\":\"Палестинские территории\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(163, 'PA', '{\"ru\":\"Панама\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(164, 'PG', '{\"ru\":\"Папуа — Новая Гвинея\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(165, 'PY', '{\"ru\":\"Парагвай\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(166, 'PE', '{\"ru\":\"Перу\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(167, 'PL', '{\"ru\":\"Польша\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(168, 'PT', '{\"ru\":\"Португалия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(169, 'PR', '{\"ru\":\"Пуэрто-Рико\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(170, 'KR', '{\"ru\":\"Республика Корея\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(171, 'RE', '{\"ru\":\"Реюньон\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(172, 'RU', '{\"ru\":\"Россия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(173, 'RW', '{\"ru\":\"Руанда\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(174, 'RO', '{\"ru\":\"Румыния\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(175, 'SV', '{\"ru\":\"Сальвадор\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(176, 'WS', '{\"ru\":\"Самоа\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(177, 'SM', '{\"ru\":\"Сан-Марино\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(178, 'ST', '{\"ru\":\"Сан-Томе и Принсипи\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(179, 'SA', '{\"ru\":\"Саудовская Аравия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(180, 'MK', '{\"ru\":\"Северная Македония\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(181, 'MP', '{\"ru\":\"Северные Марианские о-ва\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(182, 'SC', '{\"ru\":\"Сейшельские Острова\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(183, 'BL', '{\"ru\":\"Сен-Бартелеми\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(184, 'MF', '{\"ru\":\"Сен-Мартен\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(185, 'PM', '{\"ru\":\"Сен-Пьер и Микелон\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(186, 'SN', '{\"ru\":\"Сенегал\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(187, 'VC', '{\"ru\":\"Сент-Винсент и Гренадины\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(188, 'KN', '{\"ru\":\"Сент-Китс и Невис\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(189, 'LC', '{\"ru\":\"Сент-Люсия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(190, 'RS', '{\"ru\":\"Сербия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(191, 'SG', '{\"ru\":\"Сингапур\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(192, 'SX', '{\"ru\":\"Синт-Мартен\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(193, 'SY', '{\"ru\":\"Сирия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(194, 'SK', '{\"ru\":\"Словакия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(195, 'SI', '{\"ru\":\"Словения\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(196, 'US', '{\"ru\":\"Соединенные Штаты\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(197, 'SB', '{\"ru\":\"Соломоновы Острова\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(198, 'SO', '{\"ru\":\"Сомали\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(199, 'SD', '{\"ru\":\"Судан\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(200, 'SR', '{\"ru\":\"Суринам\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(201, 'SL', '{\"ru\":\"Сьерра-Леоне\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(202, 'TJ', '{\"ru\":\"Таджикистан\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(203, 'TH', '{\"ru\":\"Таиланд\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(204, 'TW', '{\"ru\":\"Тайвань\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(205, 'TZ', '{\"ru\":\"Танзания\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(206, 'TG', '{\"ru\":\"Того\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(207, 'TK', '{\"ru\":\"Токелау\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(208, 'TO', '{\"ru\":\"Тонга\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(209, 'TT', '{\"ru\":\"Тринидад и Тобаго\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(210, 'TV', '{\"ru\":\"Тувалу\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(211, 'TN', '{\"ru\":\"Тунис\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(212, 'TM', '{\"ru\":\"Туркменистан\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(213, 'TR', '{\"ru\":\"Турция\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(214, 'UG', '{\"ru\":\"Уганда\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(215, 'UZ', '{\"ru\":\"Узбекистан\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(216, 'UA', '{\"ru\":\"Украина\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(217, 'WF', '{\"ru\":\"Уоллис и Футуна\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(218, 'UY', '{\"ru\":\"Уругвай\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(219, 'FO', '{\"ru\":\"Фарерские о-ва\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(220, 'FM', '{\"ru\":\"Федеративные Штаты Микронезии\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(221, 'FJ', '{\"ru\":\"Фиджи\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(222, 'PH', '{\"ru\":\"Филиппины\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(223, 'FI', '{\"ru\":\"Финляндия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(224, 'FK', '{\"ru\":\"Фолклендские о-ва\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(225, 'FR', '{\"ru\":\"Франция\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(226, 'GF', '{\"ru\":\"Французская Гвиана\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(227, 'PF', '{\"ru\":\"Французская Полинезия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(228, 'TF', '{\"ru\":\"Французские Южные территории\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(229, 'HR', '{\"ru\":\"Хорватия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(230, 'CF', '{\"ru\":\"Центрально-Африканская Республика\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(231, 'TD', '{\"ru\":\"Чад\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(232, 'ME', '{\"ru\":\"Черногория\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(233, 'CZ', '{\"ru\":\"Чехия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(234, 'CL', '{\"ru\":\"Чили\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(235, 'CH', '{\"ru\":\"Швейцария\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(236, 'SE', '{\"ru\":\"Швеция\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(237, 'SJ', '{\"ru\":\"Шпицберген и Ян-Майен\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(238, 'LK', '{\"ru\":\"Шри-Ланка\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(239, 'EC', '{\"ru\":\"Эквадор\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(240, 'GQ', '{\"ru\":\"Экваториальная Гвинея\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(241, 'ER', '{\"ru\":\"Эритрея\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(242, 'SZ', '{\"ru\":\"Эсватини\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(243, 'EE', '{\"ru\":\"Эстония\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(244, 'ET', '{\"ru\":\"Эфиопия\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(245, 'GS', '{\"ru\":\"Южная Георгия и Южные Сандвичевы о-ва\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(246, 'ZA', '{\"ru\":\"Южно-Африканская Республика\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(247, 'SS', '{\"ru\":\"Южный Судан\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(248, 'JM', '{\"ru\":\"Ямайка\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26'),
(249, 'JP', '{\"ru\":\"Япония\"}', '2023-04-09 10:47:38', '2024-07-29 06:24:26');

-- --------------------------------------------------------

--
-- Структура таблицы `getblock_requests`
--

CREATE TABLE `getblock_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_order` int(11) NOT NULL DEFAULT 0,
  `url` varchar(191) DEFAULT NULL,
  `headers` text DEFAULT NULL,
  `response` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `options` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `group_commission`
--

CREATE TABLE `group_commission` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `give` varchar(191) NOT NULL,
  `receiving` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `group_commission_direction_exchange`
--

CREATE TABLE `group_commission_direction_exchange` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `group_commission_id` bigint(20) UNSIGNED NOT NULL,
  `direction_exchange_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `group_parser_exchange`
--

CREATE TABLE `group_parser_exchange` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `alias` varchar(191) DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `priority` int(11) NOT NULL DEFAULT 0,
  `provider_id` varchar(191) DEFAULT NULL,
  `last_updated_at` timestamp NULL DEFAULT NULL,
  `proxy_id` int(11) NOT NULL DEFAULT 0,
  `last_imported_at` timestamp NULL DEFAULT NULL,
  `is_import_rates` int(11) NOT NULL DEFAULT 0,
  `is_delete` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `group_parser_exchange`
--

INSERT INTO `group_parser_exchange` (`id`, `name`, `status`, `created_at`, `updated_at`, `alias`, `sorting`, `priority`, `provider_id`, `last_updated_at`, `proxy_id`, `last_imported_at`, `is_import_rates`, `is_delete`) VALUES
(1, 'Центральный Банк РФ', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'russiancentralbank', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(2, 'Exmo', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'exmo', 13, 0, NULL, NULL, 0, NULL, 0, 0),
(3, 'CoinMarketCap', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'coinmarketcap', 0, 0, 'coinmarketcap', NULL, 0, NULL, 0, 0),
(4, 'Европейский центральный банк', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'europeancentralbank', 17, 0, NULL, NULL, 0, NULL, 0, 0),
(5, 'Национальный банк Румынии', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'nationalbankofromania', 19, 0, NULL, NULL, 0, NULL, 0, 0),
(8, 'Национальный банк Казахстана', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'nationalbankofkazakhstan', 22, 0, NULL, NULL, 0, NULL, 0, 0),
(9, 'Национальный банк Молдовы', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'nationalbankofmoldova', 23, 0, NULL, NULL, 0, NULL, 0, 0),
(10, 'Binance', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'binance', 1, 0, NULL, NULL, 0, NULL, 0, 0),
(11, 'Blockchain', 0, '2023-04-09 10:48:58', '2023-04-09 10:48:58', 'blockchain', 1, 0, NULL, NULL, 0, NULL, 0, 0),
(13, 'Bitfinex', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'bitfinex', 3, 0, NULL, NULL, 0, NULL, 0, 0),
(14, 'HitBtc', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'hitbtc', 11, 0, NULL, NULL, 0, NULL, 0, 0),
(15, 'KuCoin', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'kucoin', 2, 0, NULL, NULL, 0, NULL, 0, 0),
(18, 'BitPay', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'bitpay', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(20, 'Bitmart', 0, '2023-04-09 10:48:58', '2023-04-09 10:48:58', 'bitmart', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(21, 'FloatRates', 0, '2023-04-09 10:48:58', '2023-04-09 10:48:58', 'floatrates', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(25, 'Узбекистанский центральный банк', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'uzbekistancentralbank', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(26, 'Израильский центральный банк', 0, '2023-04-09 10:48:58', '2023-04-09 10:48:58', 'nationalbankofisrael', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(27, 'WhiteBit', 0, '2023-04-09 10:48:58', '2023-04-09 10:48:58', 'whitebit', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(28, 'WMExchanger', 0, '2023-04-09 10:48:58', '2023-04-09 10:48:58', 'wmexchanger', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(38, 'Gate.io', 0, '2023-04-09 10:48:58', '2023-04-09 10:48:58', 'gateio', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(39, 'Coinbase API', 0, '2023-04-09 10:48:58', '2023-04-09 10:48:58', 'coinbase', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(41, 'Mexc.Exchange', 0, '2023-04-09 10:48:58', '2023-04-09 10:48:58', 'mexcexchange', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(43, 'Московская биржа', 0, '2023-04-09 10:48:58', '2023-04-09 10:48:58', 'moex', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(46, 'ByBit', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'bybit', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(47, 'Rapira', 0, '2025-04-15 08:19:49', '2025-04-15 11:19:49', 'rapira', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(52, 'Heleket', 0, '2025-04-15 11:19:49', '2025-04-15 11:19:49', 'heleket', 0, 0, NULL, NULL, 0, NULL, 0, 0),
(53, 'CryptoCash', 0, '2026-01-18 12:21:01', '2026-01-18 12:21:01', 'cryptocash', 0, 0, NULL, NULL, 0, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `health_checks`
--

CREATE TABLE `health_checks` (
  `id` int(10) UNSIGNED NOT NULL,
  `resource_name` varchar(191) NOT NULL,
  `resource_slug` varchar(191) NOT NULL,
  `target_name` varchar(191) NOT NULL,
  `target_slug` varchar(191) NOT NULL,
  `target_display` varchar(191) NOT NULL,
  `healthy` tinyint(1) NOT NULL,
  `error_message` text DEFAULT NULL,
  `runtime` double(8,2) NOT NULL,
  `value` varchar(191) DEFAULT NULL,
  `value_human` varchar(191) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `histories_codes`
--

CREATE TABLE `histories_codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `amount` varchar(191) DEFAULT NULL,
  `currency` varchar(191) DEFAULT NULL,
  `code` varchar(191) DEFAULT NULL,
  `provider_id` varchar(191) DEFAULT NULL,
  `type` varchar(191) DEFAULT NULL,
  `id_pay` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `histories_updated_data`
--

CREATE TABLE `histories_updated_data` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type_update` varchar(191) DEFAULT NULL,
  `count_num` int(11) NOT NULL DEFAULT 0,
  `total_num` int(11) NOT NULL DEFAULT 0,
  `time` varchar(191) DEFAULT NULL,
  `old_time` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `histories_updated_data`
--

INSERT INTO `histories_updated_data` (`id`, `type_update`, `count_num`, `total_num`, `time`, `old_time`, `created_at`, `updated_at`) VALUES
(1, 'courses', 0, 0, '0.0626', '0', '2025-04-21 10:51:03', '2025-04-21 10:51:03'),
(2, 'export_files', 0, 0, '0.0108', '0', '2025-04-21 10:51:03', '2025-04-21 10:51:03'),
(3, 'export_exchange', 0, 0, '0.0311', '0', '2025-04-21 10:51:03', '2025-04-21 10:51:03'),
(4, 'courses', 0, 0, '0.0528', '0.0626', '2025-04-21 10:52:03', '2025-04-21 10:52:03'),
(5, 'export_files', 0, 0, '0.0037', '0.0108', '2025-04-21 10:52:04', '2025-04-21 10:52:04'),
(6, 'export_files', 0, 0, '0.0113', '0.0108', '2025-04-21 10:52:04', '2025-04-21 10:52:04'),
(7, 'export_exchange', 0, 0, '0.049', '0.0311', '2025-04-21 10:52:04', '2025-04-21 10:52:04'),
(8, 'export_exchange', 0, 0, '0.0458', '0.0311', '2025-04-21 10:52:04', '2025-04-21 10:52:04'),
(9, 'courses', 0, 0, '0.0577', '0.0528', '2025-04-21 10:53:02', '2025-04-21 10:53:02'),
(10, 'export_files', 0, 0, '0.0109', '0.0113', '2025-04-21 10:53:03', '2025-04-21 10:53:03'),
(11, 'export_exchange', 0, 0, '0.0281', '0.0458', '2025-04-21 10:53:03', '2025-04-21 10:53:03'),
(12, 'export_files', 0, 0, '0.0039', '0.0109', '2026-01-18 12:21:05', '2026-01-18 12:21:05'),
(13, 'export_exchange', 0, 0, '0.0101', '0.0281', '2026-01-18 12:21:05', '2026-01-18 12:21:05');

-- --------------------------------------------------------

--
-- Структура таблицы `history_excode`
--

CREATE TABLE `history_excode` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `task_id` int(11) NOT NULL,
  `amount` varchar(191) NOT NULL,
  `currency` varchar(191) NOT NULL,
  `code` text DEFAULT NULL,
  `type` varchar(191) DEFAULT NULL COMMENT 'Тип получения данных',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `provider_id` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `history_fields`
--

CREATE TABLE `history_fields` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_currency1` int(11) NOT NULL DEFAULT 0,
  `id_currency2` int(11) NOT NULL DEFAULT 0,
  `filed_give` varchar(191) DEFAULT NULL,
  `filed_receiving` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `history_internal_accounts`
--

CREATE TABLE `history_internal_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_internal_account` int(11) NOT NULL DEFAULT 0,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `type_history` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `amount` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `history_payment_transactions`
--

CREATE TABLE `history_payment_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `payment` varchar(191) NOT NULL,
  `transfer` varchar(191) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `history_recalculation`
--

CREATE TABLE `history_recalculation` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `amount` float NOT NULL,
  `old_amount` float NOT NULL,
  `type` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `course` varchar(191) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `course_value` varchar(191) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `hosts`
--

CREATE TABLE `hosts` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `ssh_user` varchar(191) DEFAULT NULL,
  `port` int(11) DEFAULT NULL,
  `ip` varchar(191) DEFAULT NULL,
  `custom_properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_properties`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `info_statistics`
--

CREATE TABLE `info_statistics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `value` text NOT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `link` varchar(191) DEFAULT NULL,
  `image` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `internal_accounts`
--

CREATE TABLE `internal_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_code_currency` int(11) NOT NULL DEFAULT 0,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `balance` varchar(191) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(191) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `job_schedules`
--

CREATE TABLE `job_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `name` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `all_day` tinyint(1) NOT NULL DEFAULT 0,
  `outside_policy` varchar(32) NOT NULL DEFAULT 'inverse',
  `priority` smallint(6) NOT NULL DEFAULT 0,
  `from_time` varchar(191) DEFAULT NULL,
  `to_time` varchar(191) DEFAULT NULL,
  `timezone` varchar(64) DEFAULT NULL,
  `active_from` date DEFAULT NULL,
  `active_to` date DEFAULT NULL,
  `work_days` varchar(191) DEFAULT NULL,
  `include_dates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`include_dates`)),
  `exclude_dates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`exclude_dates`)),
  `date_ranges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`date_ranges`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `kyc_logs`
--

CREATE TABLE `kyc_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(64) NOT NULL,
  `event` varchar(64) NOT NULL,
  `status` varchar(32) DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `subject_external_id` varchar(191) DEFAULT NULL,
  `stage` varchar(32) DEFAULT NULL,
  `outcome` varchar(32) DEFAULT NULL,
  `outcome_reason` text DEFAULT NULL,
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `occurred_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `language_contents`
--

CREATE TABLE `language_contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `description_contact` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `telegram_bot_name_button` longtext DEFAULT NULL,
  `tech_breach_title` longtext DEFAULT NULL,
  `tech_breach_text` longtext DEFAULT NULL,
  `input_footer_title` longtext DEFAULT NULL,
  `description_footer_text` longtext DEFAULT NULL,
  `welcome_title` longtext DEFAULT NULL,
  `welcome_description` longtext DEFAULT NULL,
  `telegram_block_title` longtext DEFAULT NULL,
  `telegram_block_description` longtext DEFAULT NULL,
  `telegram_block_button` longtext DEFAULT NULL,
  `description_verification_card` longtext DEFAULT NULL,
  `error_verification_card` longtext DEFAULT NULL,
  `sitename` text DEFAULT NULL,
  `sitename_desc` longtext DEFAULT NULL,
  `username_new_user` text DEFAULT NULL,
  `main_title_header` text DEFAULT NULL,
  `main_value_header` text DEFAULT NULL,
  `chat_app_id` text DEFAULT NULL,
  `working_online_text` text DEFAULT NULL,
  `working_offline_text` text DEFAULT NULL,
  `referral_system_text` longtext DEFAULT NULL,
  `referral_system_text_footer` longtext DEFAULT NULL,
  `cashback_text` longtext DEFAULT NULL,
  `cashback_footer` longtext DEFAULT NULL,
  `monitoring_text` longtext DEFAULT NULL,
  `description_pr` text DEFAULT NULL,
  `description_review` text DEFAULT NULL,
  `working_offline_notify` text DEFAULT NULL,
  `s_order_notify_text` longtext DEFAULT NULL,
  `jivosite_text_message` text DEFAULT NULL,
  `title_rules_page` text DEFAULT NULL,
  `description_rules_page` text DEFAULT NULL,
  `description_request_payment` longtext DEFAULT NULL,
  `reviews_block_title` text DEFAULT NULL,
  `reviews_block_description` longtext DEFAULT NULL,
  `seo_description` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `links_footers`
--

CREATE TABLE `links_footers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text DEFAULT NULL,
  `url` text DEFAULT NULL,
  `id_group` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_blank` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `links_footer_groups`
--

CREATE TABLE `links_footer_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `name` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `links_reviews`
--

CREATE TABLE `links_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text DEFAULT NULL,
  `url` text DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `icon` varchar(191) DEFAULT NULL,
  `is_review` tinyint(1) NOT NULL DEFAULT 0,
  `type` varchar(191) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `id_group` int(11) NOT NULL DEFAULT 0,
  `is_bot` int(11) NOT NULL DEFAULT 0,
  `count_review` int(11) NOT NULL DEFAULT 0,
  `is_show` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `links_review_groups`
--

CREATE TABLE `links_review_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `live_notification`
--

CREATE TABLE `live_notification` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `text` text DEFAULT NULL,
  `text_alt` text DEFAULT NULL,
  `color` varchar(191) DEFAULT NULL,
  `timeout` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `log_autopayments`
--

CREATE TABLE `log_autopayments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `event_value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `provider` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `log_error_merchants`
--

CREATE TABLE `log_error_merchants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `provider_name` varchar(191) DEFAULT NULL,
  `event_value` text DEFAULT NULL,
  `event_type` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `log_parser_sources_errors`
--

CREATE TABLE `log_parser_sources_errors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_group` int(11) NOT NULL DEFAULT 0,
  `source_name` varchar(191) DEFAULT NULL,
  `type_parsing` int(11) NOT NULL DEFAULT 0,
  `pair_name` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `ltm_translations`
--

CREATE TABLE `ltm_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `locale` varchar(191) NOT NULL,
  `group` varchar(191) NOT NULL,
  `key` text NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Структура таблицы `menu`
--

CREATE TABLE `menu` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` longtext DEFAULT NULL,
  `sorting` int(11) NOT NULL,
  `slug` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name_alt` varchar(191) DEFAULT NULL,
  `text_color` varchar(191) DEFAULT NULL,
  `parent_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `menu`
--

INSERT INTO `menu` (`id`, `name`, `sorting`, `slug`, `status`, `created_at`, `updated_at`, `name_alt`, `text_color`, `parent_id`) VALUES
(1, '{\"ru\":\"Новости\"}', 1, '/blog', 1, '2017-10-25 05:45:46', '2026-01-18 12:51:13', 'News', NULL, 0),
(2, '{\"ru\":\"Правила обмена\"}', 1, '/pages/rules', 1, '2017-10-25 05:46:17', '2026-01-18 12:51:21', 'Exchange rules', NULL, 0),
(3, '{\"ru\":\"\\u041f\\u0430\\u0440\\u0442\\u043d\\u0435\\u0440\\u0430\\u043c\",\"en\":null}', 0, '/partners', 1, '2017-10-25 05:46:34', '2023-04-09 11:07:27', 'Partners', NULL, 0),
(5, '{\"ru\":\"FAQ\",\"en\":null}', 2, '/faq', 1, '2017-10-25 05:46:59', '2023-04-09 11:08:52', 'Questions and answers', NULL, 0),
(8, '{\"ru\":\"\\u041a\\u043e\\u043d\\u0442\\u0430\\u043a\\u0442\\u044b\",\"en\":null}', 3, '/contacts', 1, '2017-11-01 13:21:35', '2023-04-09 11:09:03', 'Contacts', NULL, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `merchants_transaction_data`
--

CREATE TABLE `merchants_transaction_data` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) NOT NULL DEFAULT 0,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `id_from_merchant` varchar(191) DEFAULT NULL,
  `service_name` varchar(191) DEFAULT NULL,
  `ext_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ext_data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_merchant` bigint(20) NOT NULL DEFAULT 0,
  `is_checkout_url` int(11) NOT NULL DEFAULT 0,
  `id_direction_exchange` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `merchant_account`
--

CREATE TABLE `merchant_account` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `account` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `provider` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `merchant_flow_events`
--

CREATE TABLE `merchant_flow_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED DEFAULT NULL,
  `merchant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `gateway_alias` varchar(64) DEFAULT NULL,
  `flow` varchar(16) NOT NULL DEFAULT 'merchant',
  `stage` varchar(32) DEFAULT NULL,
  `event` varchar(64) NOT NULL,
  `level` varchar(16) NOT NULL DEFAULT 'info',
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `message` varchar(512) DEFAULT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `checkout_id` varchar(191) DEFAULT NULL,
  `external_id` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `merchant_transaction_hash`
--

CREATE TABLE `merchant_transaction_hash` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `transaction_hash` varchar(191) DEFAULT NULL,
  `provider` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `merchant_transaction_ids`
--

CREATE TABLE `merchant_transaction_ids` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` varchar(191) DEFAULT NULL,
  `provider` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `merchant_transaction_webhooks`
--

CREATE TABLE `merchant_transaction_webhooks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) NOT NULL,
  `merchant_service_id` varchar(191) DEFAULT NULL,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `id_merchant` int(11) NOT NULL DEFAULT 0,
  `provider` varchar(191) DEFAULT NULL,
  `json_callbacks` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `amount` varchar(191) NOT NULL DEFAULT '0',
  `ext_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ext_params`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_resets_table', 1),
(3, '2017_09_16_161337_create_permission_tables', 1),
(4, '2017_09_16_174415_create_sessions_table', 1),
(5, '2017_09_16_174538_create_notifications_table', 1),
(6, '2017_09_16_174559_create_cache_table', 1),
(9, '2017_10_03_232930_create_payout_address_table', 4),
(11, '2017_10_04_233720_create_requisites_list_table', 5),
(13, '2017_10_05_101321_create_reserve_log_table', 6),
(14, '2017_10_05_113501_create_settings_theme_table', 7),
(15, '2017_10_05_154154_create_partners_table', 8),
(16, '2017_10_05_171612_create_backgrounds_table', 9),
(18, '2017_10_06_054328_create_discounts_table', 10),
(19, '2017_10_06_063139_create_unpaid_items_table', 11),
(21, '2017_10_09_172644_create_cron_table', 12),
(22, '2017_10_09_193412_create_cron_category_table', 12),
(23, '2017_10_10_084322_create_transaction_table', 13),
(24, '2017_10_10_165846_create_faq_category_table', 14),
(25, '2017_10_10_165915_create_faq_table', 14),
(26, '2016_06_01_000001_create_oauth_auth_codes_table', 15),
(27, '2016_06_01_000002_create_oauth_access_tokens_table', 15),
(28, '2016_06_01_000003_create_oauth_refresh_tokens_table', 15),
(29, '2016_06_01_000004_create_oauth_clients_table', 15),
(30, '2016_06_01_000005_create_oauth_personal_access_clients_table', 15),
(31, '2017_10_12_123532_create_generator_currency_table', 15),
(32, '2017_10_20_073637_create_filter_currency_table', 16),
(131, '2017_10_21_002533_create_monitors_table', 17),
(132, '2015_03_07_311070_create_tracker_paths_table', 18),
(133, '2015_03_07_311071_create_tracker_queries_table', 18),
(134, '2015_03_07_311072_create_tracker_queries_arguments_table', 18),
(135, '2015_03_07_311073_create_tracker_routes_table', 18),
(136, '2015_03_07_311074_create_tracker_routes_paths_table', 18),
(137, '2015_03_07_311075_create_tracker_route_path_parameters_table', 18),
(138, '2015_03_07_311076_create_tracker_agents_table', 18),
(139, '2015_03_07_311077_create_tracker_cookies_table', 18),
(140, '2015_03_07_311078_create_tracker_devices_table', 18),
(141, '2015_03_07_311079_create_tracker_domains_table', 18),
(142, '2015_03_07_311080_create_tracker_referers_table', 18),
(143, '2015_03_07_311081_create_tracker_geoip_table', 18),
(144, '2015_03_07_311082_create_tracker_sessions_table', 18),
(145, '2015_03_07_311083_create_tracker_errors_table', 18),
(146, '2015_03_07_311084_create_tracker_system_classes_table', 18),
(147, '2015_03_07_311085_create_tracker_log_table', 18),
(148, '2015_03_07_311086_create_tracker_events_table', 18),
(149, '2015_03_07_311087_create_tracker_events_log_table', 18),
(150, '2015_03_07_311088_create_tracker_sql_queries_table', 18),
(151, '2015_03_07_311089_create_tracker_sql_query_bindings_table', 18),
(152, '2015_03_07_311090_create_tracker_sql_query_bindings_parameters_table', 18),
(153, '2015_03_07_311091_create_tracker_sql_queries_log_table', 18),
(154, '2015_03_07_311092_create_tracker_connections_table', 18),
(155, '2015_03_07_311093_create_tracker_tables_relations', 18),
(156, '2015_03_13_311094_create_tracker_referer_search_term_table', 18),
(157, '2015_03_13_311095_add_tracker_referer_columns', 18),
(158, '2015_11_23_311096_add_tracker_referer_column_to_log', 18),
(159, '2015_11_23_311097_create_tracker_languages_table', 18),
(160, '2015_11_23_311098_add_language_id_column_to_sessions', 18),
(161, '2015_11_23_311099_add_tracker_language_foreign_key_to_sessions', 18),
(162, '2015_11_23_311100_add_nullable_to_tracker_error', 18),
(163, '2017_01_31_311101_fix_agent_name', 18),
(164, '2017_06_20_311102_add_agent_name_hash', 18),
(165, '2014_02_01_311070_create_firewall_table', 19),
(166, '2017_10_21_235416_create_sessions_table', 20),
(167, '2017_10_22_124906_create_task_log_table', 20),
(182, '2017_10_22_201100_create_referral_programs_table', 21),
(183, '2017_10_22_201101_create_referral_links_table', 21),
(184, '2017_10_22_201102_create_referral_relationships_table', 21),
(185, '2017_10_22_201103_add_allowed_ref_program_to_users', 21),
(187, '2017_10_23_175416_create_user_balance_table', 22),
(188, '2017_10_23_204855_create_referral_log_table', 23),
(189, '2017_10_24_085006_create_reward_table', 24),
(190, '2017_10_24_085144_create_reward_programs_table', 24),
(191, '2017_10_24_103816_create_reward_log_table', 25),
(192, '2017_10_24_233013_create_pages_table', 26),
(193, '2017_10_25_083556_create_menu_table', 27),
(194, '2017_10_25_190125_create_parser_type_table', 28),
(195, '2017_10_25_211438_create_user_settings_table', 29),
(196, '2017_10_25_223218_create_fund_table', 29),
(197, '2017_03_04_000000_create_bans_table', 30),
(198, '2018_06_28_000847_add_banned_at_column_to_users_table', 31),
(199, '2018_06_29_101540_create_docs_category_table', 32),
(200, '2018_06_29_102056_create_docs_category_type_table', 32),
(201, '2018_06_29_102139_create_docs_items_table', 32),
(202, '2018_07_05_184029_create_push_subscriptions_table', 33),
(203, '2018_07_05_194923_add_uuid_column_to_users', 34),
(204, '2018_09_04_064813_create_favorites_table', 35),
(205, '2018_09_06_203049_create_banned_user_table', 36),
(206, '2018_09_07_063952_create_tasks_rates_data_table', 37),
(207, '2019_02_04_141815_create_tasks_status_log_table', 38),
(208, '2019_02_06_165323_create_notices_exchange_table', 39),
(209, '2019_02_06_191328_add_username_to_users', 40),
(210, '2018_09_09_000001_create_table_health_checks', 41),
(211, '2019_02_14_132323_create_banned_table', 42),
(212, '2018_08_08_100000_create_telescope_entries_table', 43),
(213, '2019_03_05_181556_create_audits_table', 44),
(214, '2019_03_10_115333_create_export_lite_table', 45),
(215, '2019_03_10_122508_create_export_data_table', 46),
(216, '2017_08_24_000000_create_settings_table', 47),
(218, '2019_03_13_185017_create_admin_log_operation_table', 48),
(219, '2019_03_14_094533_create_blacklist_order_table', 49),
(222, '2019_03_24_111951_create_advantage_table', 50),
(223, '2019_03_27_140256_create_failed_jobs_table', 51),
(225, '2019_03_29_093039_add_is_delete_to_reserve_request', 52),
(226, '2019_03_29_095945_create_requisites_group_table', 53),
(227, '2019_03_29_102747_add_id_group_to_requisites', 54),
(228, '2019_03_30_064705_add_is_delete_to_payments', 55),
(230, '2019_03_31_063131_add_column_to_requisites', 56),
(231, '2019_03_31_091521_add_column_id_check_pay_to_currencies', 57),
(232, '2019_03_31_141711_add_column_field_type_currency_fields', 58),
(233, '2019_03_31_180948_add_column_month_limit_to_currency', 59),
(234, '2019_04_01_183150_add_column_limit_week_to_requisites', 60),
(235, '2019_04_03_214916_add_column_ip_changed_to_users', 61),
(236, '2019_04_04_203154_add_column_role_expired_at_to_users', 62),
(238, '2019_04_05_074739_create_password_histories_table', 63),
(239, '2019_04_07_061554_rename_column_random_to_requisites', 64),
(240, '2019_04_07_070407_add_column_history_at_to_requisites', 65),
(241, '2019_04_07_131424_add_columns_to_generator_currency', 66),
(242, '2019_04_07_192046_delete_column_account_number_field_to_requisites', 67),
(243, '2019_04_07_192134_add_column_account_number_field_to_currencies', 67),
(245, '2019_04_08_082718_add_columns_week_limit_to_currencies', 68),
(246, '2019_04_08_090710_add_column_limit_views_to_requisites', 69),
(247, '2019_04_16_205714_add_column_is_main_to_direction_exchange', 70),
(248, '2019_04_18_144745_add_column_notice_in_to_currencies', 71),
(249, '2019_04_28_195032_add_column_transfer_percent_reserve_to_currencies', 72),
(250, '2019_04_28_195530_add_column_transfer_amount_reserve_to_currencies', 73),
(251, '2019_04_30_161321_add_columns_commission_s_to_direction_exchange', 74),
(252, '2019_05_02_194739_add_column_is_follow_referral_to_users', 75),
(254, '2019_05_03_061553_add_column_allows_exports_to_direction_exchange', 76),
(255, '2019_05_09_110548_add_comment_to_event_reserve', 77),
(256, '2019_05_10_062257_add_column_remove_spaces_requisite_to_currencies', 78),
(257, '2019_05_10_064441_add_column_payout_commission_to_currencies', 79),
(258, '2019_05_10_070414_add_column_name_alt_to_payments', 80),
(259, '2019_05_10_074607_add_column_sorting_reserve_to_currencies', 81),
(261, '2019_05_11_074239_add_column_old_ip_address_to_user_auth', 83),
(262, '2019_05_11_101834_add_column_number_format_to_generator_currency', 84),
(263, '2019_05_11_102914_add_column_type_number_format_to_generator_currency', 85),
(264, '2019_05_11_110733_add_column_operator_started_at_to_tasks', 86),
(265, '2019_05_12_152911_add_column_blockchain_url_to_payments', 87),
(266, '2019_05_12_190658_create_reserve_group_table', 88),
(267, '2019_05_12_192008_add_column_id_group_to_reserves', 89),
(268, '2019_05_13_131939_add_column_sorting_to_reserves', 90),
(269, '2019_05_14_054521_add_column_deleted_at_to_tasks_info', 91),
(270, '2019_05_14_054543_add_column_deleted_at_to_tasks_rates_data', 91),
(271, '2019_05_14_054556_add_column_deleted_at_to_tasks_status_log', 91),
(272, '2019_05_14_054723_add_column_deleted_at_to_transactions', 92),
(273, '2019_05_14_054923_add_column_deleted_at_to_history_recalculation', 93),
(274, '2019_05_14_054947_add_column_deleted_at_to_wallets_history', 93),
(275, '2019_05_14_055011_add_column_deleted_at_to_tasks_convert_log', 93),
(276, '2019_05_14_055048_add_column_deleted_at_to_history_payment_transactions', 94),
(277, '2019_05_14_055138_add_column_deleted_at_to_history_excode', 95),
(278, '2019_05_14_055205_add_column_deleted_at_to_reward_log', 96),
(279, '2019_05_14_055227_add_column_deleted_at_to_wallet_transactions', 96),
(280, '2019_05_14_085623_add_column_is_server_tx_merchant_to_currencies', 97),
(282, '2019_05_14_091823_create_payment_explorer_table', 98),
(283, '2019_05_14_144610_create_requisites_blacklist_table', 99),
(284, '2019_05_14_145001_add_column_text_to_requisites_blacklist', 100),
(285, '2019_05_14_165043_add_column_first_char_alt_to_currencies', 101),
(286, '2019_05_14_180651_add_column_task_cancel_to_unpaid_items', 102),
(287, '2019_05_14_181335_add_column_allow_delete_to_tasks_status', 103),
(289, '2019_05_14_181724_custom_data_to_unpaid_items', 104),
(290, '2019_05_14_204856_create_tasks_chat_table', 105),
(291, '2019_05_14_213844_create_tasks_rejection_status_table', 106),
(292, '2019_05_14_215838_add_column_rejection_status_to_tasks', 107),
(293, '2019_05_14_222437_add_column_id_rejection_status_to_tasks', 108),
(294, '2019_05_15_062052_create_pending_order_status', 109),
(295, '2019_05_15_062212_add_column_id_pending_status_to_tasks', 110),
(296, '2019_05_15_213011_add_column_is_not_delete_to_tasks_rejection_status', 111),
(297, '2019_05_15_220025_add_column_is_not_delete_to_pending_order_status', 112),
(298, '2019_05_16_112155_add_column_name_alt_to_currency_fields', 113),
(299, '2019_05_11_073216_add_columns_in_out_price_to_task_status_log', 114),
(300, '2019_05_17_082400_add_column_link_to_notices_exchange', 115),
(302, '2019_05_17_150206_create_task_reports_table', 116),
(303, '2019_05_17_205144_add_column_profit_usd_to_task_reports', 117),
(304, '2019_05_17_210852_add_column_profit_s_to_direction_exchange', 118),
(305, '2019_05_17_215300_add_column_profit_s_to_task_reports', 119),
(306, '2019_05_18_092848_add_column_profit_rub_to_task_reports', 120),
(307, '2019_05_18_095741_add_column_is_bestchange_to_task_reports', 121),
(308, '2019_05_18_170351_create_task_log_confirmation_table', 122),
(309, '2019_05_18_183933_add_column_sorting_to_notices_exchange', 123),
(310, '2019_05_18_191752_add_column_sorting_to_advantage', 124),
(311, '2019_05_18_192822_create_parser_exchange_log_table', 125),
(312, '2019_05_18_203740_add_column_sorting_to_partners', 126),
(313, '2019_05_18_204823_create_referral_statistics_table', 127),
(314, '2019_05_18_220157_add_column_ip_to_referral_statistics', 128),
(315, '2019_05_18_220222_add_column_id_user_to_referral_statistics', 128),
(316, '2019_05_19_083508_add_column_percent_to_reward_log', 129),
(317, '2019_05_19_084304_add_column_is_cashback_to_reward_log', 130),
(318, '2019_05_19_175500_add_column_is_favorites_to_tasks', 131),
(319, '2019_05_19_191417_add_column_is_spam_to_tasks', 132),
(320, '2019_05_20_075350_create_main_event_logs_table', 133),
(321, '2019_05_20_082651_add_column_ip_to_main_event_logs', 134),
(322, '2019_05_20_083346_add_column_id_admin_to_main_event_logs', 135),
(323, '2019_05_21_055132_add_column_name_alt_to_menu', 136),
(324, '2019_05_22_200043_create_links_reviews_table', 137),
(325, '2019_05_23_072005_add_column_hash_id_to_archive_reports', 138),
(326, '2019_05_23_081635_create_bestchange_rates_log', 139),
(327, '2019_05_23_090604_create_admin_auth_log_table', 140),
(328, '2019_05_23_090942_add_column_is_successful_to_admin_auth_log', 141),
(329, '2019_05_23_152149_add_column_place_change_to_tasks_status_log', 142),
(330, '2019_05_23_174344_add_columns_to_code_currencies', 143),
(331, '2019_05_24_060006_create_direction_exchange_group_table', 144),
(332, '2019_05_24_061343_add_column_id_group_direction_to_direction_exchange', 145),
(333, '2019_05_24_063629_add_column_is_restrict_editing_to_direction_exchange', 146),
(334, '2019_05_24_072029_create_reserve_alerts_table', 147),
(336, '2019_05_24_072512_add_column_count_alert_to_reserves_alerts', 148),
(337, '2019_05_27_091858_add_column_language_field_to_currency_fields', 149),
(338, '2019_05_27_101040_add_3_columns_to_direction_exchange', 150),
(339, '2019_05_27_111922_create_whitelist_order_table', 151),
(340, '2019_05_28_162141_add_3_columns_to_notices_exchange', 152),
(341, '2019_05_28_164142_add_column_text_alt_to_notices_exchange', 153),
(342, '2019_05_28_164951_add_column_comment_to_requisites', 154),
(343, '2019_05_28_193114_create_users_history_profiles_table', 155),
(344, '2019_05_28_195845_add_columns_to_history_profiles', 156),
(345, '2019_05_29_075545_add_column_verified_at_to_withdrawal_request', 157),
(346, '2019_05_29_081630_add_column_tx_id_to_withdrawal_request', 158),
(350, '2019_05_29_110938_create_log_merchants_table', 159),
(351, '2019_05_29_193757_add_column_status_to_log_merchants', 159),
(352, '2019_05_29_200326_add_column_event_to_log_merchants', 159),
(353, '2019_05_29_213216_add_column_provider_to_log_merchants', 160),
(354, '2019_05_30_193419_add_column_is_archive_to_currencies', 161),
(355, '2019_05_31_060815_create_currencies_groups_table', 162),
(356, '2019_05_31_060909_add_column_id_group_to_currencies', 163),
(358, '2019_06_03_113703_create_log_autopayments_table', 165),
(359, '2019_06_02_134815_create_currencies_autopayment_table', 166),
(360, '2019_06_05_172833_add_column_is_check_pay_cron_to_currencies', 167),
(361, '2019_06_05_202326_add_column_is_bot_to_tasks', 168),
(362, '2019_06_06_102107_create_log_check_pay_table', 169),
(363, '2019_06_06_102412_add_column_provider_to_log_check_pay', 170),
(364, '2019_06_06_155312_add_column_register_tx_to_tasks', 171),
(365, '2019_06_11_065537_add_column_hidden_export_label_param_to_direction_exchange', 172),
(366, '2019_06_13_185842_add_column_custom_field_comment_to_requisites', 173),
(367, '2019_06_16_074529_add_column_alias_to_group_parser_exchange', 174),
(368, '2019_06_16_144006_add_column_to_group_parser_exchange', 175),
(369, '2019_06_16_153942_add_column_provider_to_log_autopayments', 176),
(370, '2019_06_18_065040_add_column_merchant_incomplete_payment_to_tasks', 177),
(371, '2019_06_18_174451_add_column_is_paid_to_currencies_autopayment', 178),
(372, '2019_06_18_194027_add_column_max_amount_day_to_currencies_autopayment', 179),
(373, '2019_06_19_070539_add_column_merchant_overpayment_to_tasks', 180),
(374, '2019_06_21_190023_change_type_export_label_param_to_direction_exchange', 181),
(375, '2019_06_22_071432_add_column_is_notify_login_to_users', 182),
(376, '2019_06_22_072058_add_column_is_password_reset_to_users', 183),
(377, '2019_06_22_072826_add_column_user_agent_to_users', 183),
(378, '2019_06_22_150801_add_column_is_pay_referral_to_users', 184),
(379, '2019_06_22_152844_add_column_is_pay_cashback_to_users', 185),
(380, '2019_06_22_185817_add_column_is_archive_to_referral_statistics', 186),
(381, '2019_06_22_212623_add_column_user_agent_to_referral_statistics', 187),
(386, '2019_06_23_122500_create_direction_notification_table', 188),
(387, '2019_06_25_090925_add_columns_to_direction_notification', 189),
(388, '2019_06_26_055618_add_column_sort_by_to_bestchange_rates', 190),
(389, '2019_06_30_055117_add_columns_to_tasks_info', 191),
(390, '2019_06_30_061024_add_column_to_referral_statistics', 192),
(391, '2019_06_30_065309_add_column_to_favorites', 193),
(392, '2019_07_04_113722_add_columns_to_direction_exchange', 194),
(393, '2019_07_04_114003_add_columns_to_direction_exchange', 195),
(394, '2019_07_04_220500_add_column_to_currencies', 196),
(395, '2019_07_04_221526_add_column_to_tasks', 196),
(396, '2019_07_05_195018_add_column_to_direction_exchange', 197),
(397, '2019_07_07_121431_add_column_to_wallets_addresses', 198),
(398, '2019_07_07_202917_add_column_in_flow_funds_to_tasks', 199),
(399, '2019_07_08_061505_add_column_is_qrcode_amount_to_currencies', 200),
(400, '2019_07_08_082205_add_column_in_min_amount_to_tasks_info', 201),
(401, '2019_07_08_082424_add_columns_in_max_amount_to_tasks_info', 202),
(402, '2019_07_08_094656_add_column_is_failed_send_to_wallets_addresses', 203),
(403, '2019_07_08_192231_create_verification_card__table', 204),
(404, '2019_07_08_193753_add_column_to_currencies', 205),
(405, '2019_07_08_211204_add_column_hash_id_to_verification_card', 206),
(406, '2019_07_08_211758_add_column_id_currency_to_verification_card', 207),
(407, '2019_07_08_213531_add_column_name_to_verification_card', 208),
(408, '2019_07_08_213810_add_column_status_to_verification_card', 209),
(409, '2019_07_09_084510_add_column_to_verification_card', 210),
(410, '2019_07_09_120829_add_column_to_currencies', 211),
(411, '2019_07_11_101130_change_type_notice_in_to_currencies', 212),
(412, '2019_07_12_054646_add_column_card_number_string_to_verification_card', 213),
(413, '2019_07_12_083524_add_column_hold_in_hours_to_currencies', 214),
(414, '2019_07_16_120455_add_column_is_verification_to_users', 215),
(415, '2019_07_16_160402_add_column_is_user_verification_to_currencies', 216),
(416, '2019_07_19_080537_add_columns_who_pays_commission_to_direction_exchange', 217),
(417, '2019_07_19_085052_add_column_commission_merchant_to_currencies', 218),
(418, '2019_07_19_091318_change_type_commission_merchant_percent_to_currencies', 219),
(419, '2019_07_21_143130_add_column_is_allow_split_to_code_currency', 220),
(420, '2019_07_23_084152_add_column_token_value_to_code_currency', 221),
(421, '2019_07_23_210927_add_column_is_send_fee_network_to_currencies', 222),
(422, '2019_07_23_223251_add_column_fee_sent_to_wallets_addresses', 223),
(423, '2019_07_25_080443_add_column_fee_no_verified_merchant_to_currencies', 224),
(424, '2019_07_25_134305_add_column_class_name_to_merchants', 225),
(425, '2019_07_25_205538_add_column_summa_not_fee_to_reserve_log', 226),
(426, '2019_07_25_205735_add_column_summa_with_fee_to_reserve_log', 226),
(427, '2019_07_25_213852_add_column_next_checkout_at_to_tasks', 226),
(428, '2019_07_28_091432_add_column_hold_delay_to_currencies', 227),
(429, '2019_08_28_113735_add_column_income_outcome_to_tasks', 228),
(430, '2019_08_31_063128_add_column_provider_to_wallets_addresses', 229),
(431, '2019_08_31_073249_add_column_custom_field_prefix_to_requisites', 230),
(432, '2019_08_31_083246_create_requirements_table', 231),
(433, '2019_09_01_191859_create_logs_404_table', 232),
(434, '2019_09_01_192106_add_column_message_to_logs_404', 233),
(435, '2019_09_02_105516_create_merchant_account_table', 234),
(436, '2019_09_04_101305_add_column_provider_to_merchant_account', 235),
(437, '2019_09_05_162508_create_qiwi_currencies_table', 236),
(438, '2019_09_06_224531_add_column_is_enabled_merchant_to_requisites', 237),
(439, '2019_09_07_111925_create_yandex_currencies_table', 238),
(440, '2019_09_11_133856_add_column_custom_field_comment_alt_to_requisites', 239),
(441, '2019_09_13_085214_add_column_is_marquee_to_notices_exchange', 240),
(442, '2019_09_14_193809_add_column_num_auth_to_users', 241),
(443, '2019_09_16_221532_create_autosender_payment_table', 242),
(444, '2019_09_17_092625_add_column_double_withdrawal_to_tasks', 243),
(445, '2019_10_02_083733_add_column_telegram_to_users', 244),
(446, '2019_10_03_073341_add_column_id_currency_to_reserve_request', 245),
(447, '2019_10_05_075529_create_currencies_commands', 246),
(448, '2019_10_05_081343_add_column_id_currency_to_currencies_commands', 247),
(449, '2019_10_05_213258_add_column_description_to_currency_fields', 248),
(450, '2019_10_05_232438_add_column_phone_to_tasks', 249),
(451, '2019_10_06_105509_add_column_payout_commission_amount_to_currencies', 250),
(452, '2019_10_06_194016_add_columns_is_review_icon_to_links_reviews', 251),
(453, '2019_10_06_194959_add_column_description_to_links_reviews', 252),
(454, '2019_10_08_195259_create_links_review_groups_table', 253),
(455, '2019_10_08_200227_add_column_id_group_to_links_reviews', 254),
(456, '2019_10_08_230139_add_column_id_auto_reserve_to_currencies', 255),
(457, '2019_10_10_195911_create_proxies_qiwi_table', 256),
(458, '2019_10_10_203828_add_column_id_proxy_to_requisites', 257),
(459, '2019_10_10_220151_create_proxies_payment_table', 258),
(460, '2019_10_11_113204_add_column_drains_to_requisites', 259),
(461, '2019_10_11_124513_create_log_drain_table', 260),
(462, '2019_10_11_124800_add_column_from_value_to_log_drain', 261),
(463, '2019_10_11_125018_add_column_id_task_to_log_drain', 262),
(464, '2019_10_11_184842_add_column_freeze_scam_to_tasks_info', 263),
(465, '2019_10_12_214638_create_live_notification_table', 264),
(466, '2019_10_13_075207_add_column_id_client_to_tasks_chat', 265),
(467, '2019_10_13_094256_add_column_is_drain_mertchant_to_tasks', 266),
(468, '2019_10_13_210128_add_column_big_id_to_withdrawal_request', 266),
(469, '2019_10_17_130759_add_column_user_agent_to_proxies_payment', 267),
(470, '2019_10_18_193613_create_competitor_links_table', 268),
(471, '2019_10_18_193712_create_competitor_rates_table', 269),
(472, '2019_10_18_220118_add_column_number_format_to_competitor_rates', 270),
(473, '2019_10_18_222659_add_column_id_competitor_to_direction_exchange', 271),
(474, '2019_10_18_223449_add_column_enable_competitors_to_direction_exchange', 272),
(475, '2019_10_19_071753_create_competitor_rates_log_table', 273),
(476, '2019_10_19_084447_add_column_exchange_in_out_to_competitor_rates', 274),
(477, '2019_10_19_155051_add_column_sorting_to_faq_category', 275),
(478, '2019_10_19_165008_add_column_status_to_faq', 276),
(479, '2019_10_19_165139_add_column_sorting_to_faq', 277),
(480, '2019_10_19_203855_create_reviews_table', 278),
(481, '2019_10_20_063552_add_column_user_agent_to_reviews', 279),
(482, '2019_10_20_063639_add_column_id_admin_to_reviews', 280),
(483, '2019_10_20_100535_create_selected_courses_table', 281),
(484, '2019_10_20_133254_add_column_number_format_to_selected_courses', 282),
(485, '2019_10_20_183151_add_column_provider_to_users', 283),
(486, '2019_10_21_070312_create_social_auth_system_table', 284),
(487, '2019_10_21_190046_change_type_to_blacklist_order', 285),
(488, '2019_10_21_195444_add_column_is_bestchange_to_blacklist_order', 286),
(489, '2019_10_21_211845_create_contacts_table', 287),
(490, '2019_10_21_212111_add_column_sorting_to_contacts', 288),
(491, '2019_10_21_212247_add_column_is_home_to_contacts', 289),
(492, '2019_10_21_214327_change_type_to_contacts', 290),
(493, '2019_10_23_195411_create_backup_codes_table', 291),
(494, '2019_10_23_202738_add_column_num_to_backup_codes', 292),
(495, '2019_10_23_214323_add_column_is_download_codes_to_users', 293),
(496, '2019_10_24_075039_add_column_backup_code_secret_to_users', 294),
(497, '2019_10_24_111024_create_social_reviews_table', 295),
(498, '2019_10_24_124356_change_type_type_to_social_reviews', 296),
(499, '2019_10_25_062143_create_collaboration_pr_table', 297),
(500, '2019_10_25_065708_add_column_is_button_to_collaboration_pr', 298),
(501, '2019_10_25_065724_add_column_is_button_to_contacts', 298),
(502, '2019_10_26_074248_create_info_statistics_table', 299),
(503, '2019_10_26_081610_change_type_to_info_statistics_table', 300),
(504, '2019_10_26_185348_add_column_accoutn_number_to_info_statistics', 301),
(505, '2019_10_26_204735_add_columns_to_users', 302),
(506, '2019_10_27_070853_add_column_link_to_info_statistics', 303),
(507, '2019_10_28_215528_add_columns_courses_in_out_to_direction_exchange', 304),
(508, '2019_10_28_220117_create_course_logs_table', 305),
(509, '2019_10_29_064341_add_column_bc_min_max_to_direction_exchange', 306),
(511, '2019_10_29_085617_add_columns_bc_id_new_rate_to_direction_exchange', 307),
(512, '2019_10_29_113749_add_column_bc_add_course_direction_exchange', 308),
(513, '2019_10_29_181144_add_column_napsip_to_direction_exchange', 309),
(514, '2019_10_29_195839_add_column_not_ip_to_direction_exchange', 310),
(515, '2019_10_30_124856_add_column_cr_max_min_to_direction_exchange', 311),
(516, '2019_10_30_142730_add_column_cr_fields_to_direction_exchange', 312),
(517, '2019_10_30_150046_add_column_max_percent_partner_to_direction_exchange', 313),
(518, '2019_10_30_190314_add_column_xml_juridical_to_direction_exchange', 314),
(519, '2019_10_30_195538_add_column_languages_to_direction_exchange', 315),
(520, '2019_10_30_205437_add_column_is_hidden_noauth_user_to_direction_exchange', 316),
(521, '2019_10_30_220421_add_column_is_hidden_not_locale_to_direction_exchange', 317),
(522, '2019_10_30_222414_add_column_sorting_tariffs_to_direction_exchange', 318),
(523, '2019_10_30_223658_add_column_sorting_tariffs_to_currencies', 319),
(524, '2019_10_31_162940_add_column_pay_num_to_tasks', 320),
(525, '2019_10_31_203032_add_column_type_to_reviews', 321),
(526, '2019_10_31_212033_add_column_device_to_direction_exchange', 322),
(527, '2019_10_31_213532_add_column_is_hidden_not_device_to_direction_exchange', 323),
(528, '2019_11_01_221959_add_column_is_autopay_off_tasks', 324),
(529, '2019_11_03_165336_create_tasks_shots_table', 325),
(530, '2019_11_05_062706_add_column_bc_new_commission_to_direction_exchange', 326),
(531, '2019_11_06_202434_create_currencies_notification_table', 327),
(532, '2019_11_10_081729_add_column_is_other_service_to_merchants', 328),
(533, '2019_11_10_082410_add_column_service_name_to_wallets_addresses', 329),
(534, '2019_11_10_111305_create_task_private_hash_table', 330),
(535, '2019_11_10_203944_add_column_min_out_amount_verification_to_currencies', 331),
(536, '2019_11_10_224006_create_logs_email_table', 332),
(537, '2019_11_11_105817_add_column_is_out_enabled_verification_to_currencies', 333),
(538, '2019_11_12_070443_add_column_bestchange_bl_to_direction_exchange', 334),
(539, '2019_11_12_121035_add_column_bestchange_step_to_direction_exchange', 335),
(540, '2019_11_12_131907_add_column_bc_enable_your_to_direction_exchange', 336),
(541, '2019_11_12_180452_add_column_limit_min_course_to_direction_exchange', 337),
(542, '2019_11_12_192745_add_column_bestchange_range_to_direction_exchange', 338),
(543, '2019_11_12_201931_add_column_other_comm_amount_to_direction_exchange', 339),
(544, '2019_11_13_064424_add_column_max_amount_month_to_currencies_autopayment', 340),
(545, '2019_11_13_075352_add_column_delay_to_currencies_autopayment', 341),
(546, '2019_11_14_073553_add_column_autodel_status_taks_to_direction_exchange', 342),
(547, '2019_11_14_120030_add_column_is_vip_client_to_users', 343),
(548, '2019_11_16_082626_add_column_bestchange_max_reserve_to_direction_exchange', 344),
(549, '2019_11_16_181746_create_your_exchange_group_table', 345),
(550, '2019_11_16_182002_add_column_id_group_to_your_exchange', 346),
(551, '2019_11_17_082413_create_bestchange_data_log_table', 347),
(552, '2019_11_17_184248_create_e_voucher_codes_table', 348),
(553, '2019_11_17_213529_create_currencies_log_table', 349),
(554, '2019_11_19_000627_add_column_is_hidden_tariffs_to_direction_exchange', 350),
(555, '2019_11_19_093737_add_column_is_holding_direction_to_direction_exchange', 351),
(556, '2019_11_19_105458_add_column_is_automatic_to_bestchange_rates', 352),
(557, '2019_11_20_081620_add_column_bestchange_range_to_to_direction_exchange', 353),
(558, '2019_11_20_085624_add_column_rl_id_your_exchange_to_direction_exchange', 354),
(559, '2019_11_24_103259_add_column_reserve_limit_to_direction_exchange', 355),
(560, '2019_11_24_115016_add_column_is_email_verification_to_direction_exchange', 356),
(561, '2019_11_24_121027_add_column_number_transaction_to_direction_exchange', 357),
(562, '2019_11_24_121742_add_column_num_transaction_to_tasks_info', 358),
(563, '2019_11_24_163045_add_column_other_limit_to_direction_exchange', 359),
(564, '2019_11_24_191521_add_column_auto_del_order_day_to_direction_exchange', 360),
(565, '2019_11_24_213620_create_referrals_info_logs', 361),
(566, '2019_12_01_170534_add_column_currency_sign_payout_to_tasks_info', 362),
(567, '2019_12_05_171739_add_column_text_to_payment_explorer', 362),
(568, '2019_12_06_072806_add_column_max_limit_in_reserve_to_currencies', 362),
(569, '2019_12_06_091255_change_type_bestchange_step_to_direction_exchange', 362),
(570, '2019_12_06_142239_add_column_code_base_to_code_currency', 362),
(571, '2019_12_07_092033_add_column_user_agent_to_user_auth', 362),
(572, '2019_12_07_234636_add_column_token_decimal_to_code_currency', 362),
(573, '2019_12_07_234901_change_type_token_decimal_to_code_currency', 362),
(574, '2019_12_12_073739_add_column_merchant_provider_to_tasks', 362),
(575, '2019_12_14_000001_create_personal_access_tokens_table', 362),
(576, '2019_12_16_220833_add_column_income_outcome_5_to_tasks', 362),
(577, '2020_01_02_120627_create_parser_api_keys_table', 362),
(578, '2020_01_02_170107_create_gateways_merchants_table', 362),
(579, '2020_01_02_231912_create_gateways_payments_table', 362),
(580, '2020_01_03_170911_add_column_to_security_hash_to_gateways_merchants', 362),
(581, '2020_01_04_003546_add_column_is_check_from_shot_to_gateways_merchants', 362),
(582, '2020_01_04_113745_add_column_comment_to_gateways_merchants', 362),
(583, '2020_01_04_161024_add_column_is_deny_ip_address_to_gateways_merchants', 362),
(584, '2020_01_04_171400_create_file_parser_groups_table', 362),
(585, '2020_01_04_172628_create_file_parser_rates_table', 362),
(586, '2020_01_04_183340_add_column_id_file_parser_rate_to_direction_exchange', 362),
(587, '2020_01_04_211928_add_column_hour_limit_order_tocurrencies', 362),
(588, '2020_01_04_232646_add_column_referral_link_id_to_tasks', 362),
(589, '2020_01_05_081557_create_bestchange_currencies_table', 362),
(590, '2020_01_05_095359_create_requisites_fields_table', 362),
(591, '2020_01_06_221954_add_column_profit_percent_reserve_to_currencies', 362),
(592, '2020_01_06_222820_create_reserve_log_profit_table', 362),
(593, '2020_01_07_122646_create_operation_level_groups_table', 362),
(594, '2020_01_07_155257_create_operation_levels_table', 362),
(595, '2020_01_09_160914_add_column_is_not_pair_to_bestchange_rates', 362),
(596, '2020_01_09_173024_add_column_id_bs_alt_parser_to_direction_exchange', 362),
(597, '2020_01_09_192018_add_column_bs_alt_parser_course_to_direction_exchange', 362),
(598, '2020_01_09_193144_is_enable_alt_bs_parser_to_direction_exchange', 362),
(599, '2020_01_09_205731_create_bestchange_parser_error_table', 362),
(600, '2020_01_09_210039_add_column_id_direction_exchange_to_bestchange_parser_error', 362),
(601, '2020_01_10_183324_add_column_is_disable_bs_error_to_direction_exchange', 362),
(602, '2020_01_11_083922_change_type_amount_2_to_reserve_log_profit', 362),
(603, '2020_01_11_092711_add_column_amount_usd_to_reserve_log_profit', 362),
(604, '2020_01_13_183703_create_tasks_fields_table', 362),
(605, '2020_01_13_230315_add_column_type_field_to_tasks_fields', 362),
(606, '2020_01_13_235114_create_currency_fields_relationships_table', 362),
(607, '2020_01_14_000438_add_column_type_field_to_currency_fields_relationships', 362),
(608, '2020_01_14_100900_remove_column_outcome_to_tasks', 362),
(609, '2020_01_14_102253_add_column_view_to_parser_api_keys', 362),
(610, '2020_01_14_112109_create_direction_exchange_error_log_table', 362),
(611, '2020_01_14_114116_add_column_level_risk_to_direction_exchanger_error_log', 362),
(612, '2020_01_15_092957_create_user_balance_log_table', 362),
(613, '2020_01_15_093404_add_column_route_type_to_user_balance_log', 362),
(614, '2020_01_17_073911_change_type_name_to_menu', 362),
(615, '2020_01_18_102305_add_column_is_new_user_to_tasks', 362),
(616, '2020_01_27_212657_create_task_card_details_table', 362),
(617, '2020_01_27_225607_add_column_phone_and_url_to_task_card_details', 362),
(618, '2020_01_27_234335_add_column_is_card_detail_to_currencies', 362),
(619, '2020_01_29_001109_add_column_max_register_blockchain_to_gateways_payments', 362),
(620, '2020_01_31_134240_add_column_max_display_reserve_to_currencies', 362),
(621, '2020_01_31_222803_add_columns_is_black_list_to_withdrawal_request', 362),
(622, '2020_02_03_211632_add_column_notify_statusss_to_tasks_info', 362),
(623, '2020_02_05_091258_change_type_page_title_to_pages', 362),
(624, '2020_02_05_223942_create_affiliate_settings_table', 362),
(625, '2020_02_05_232146_add_column_profit_partner_to_direction_exchange', 362),
(626, '2020_02_06_223239_add_column_is_backup_to_users', 362),
(627, '2020_02_15_002103_add_column_min_confirm_to_gateways_payments', 362),
(628, '2020_02_17_222606_create_update_systems_table', 362),
(629, '2020_02_18_230710_change_type_column_page_content_to_pages', 362),
(630, '2020_02_23_210640_add_column_id_proxy_to_gateways_payments', 362),
(631, '2020_02_24_095954_create_debtors_table', 362),
(632, '2020_02_24_101821_add_column_is_freeze_local_to_tasks_info', 362),
(633, '2020_02_26_120552_create_admin_desktops_table', 362),
(634, '2020_02_26_124451_create_admin_desktop_gadgets_table', 362),
(635, '2020_02_27_011033_add_column_column_id_to_admin_desktop_gadgets', 362),
(636, '2020_02_27_074123_add_column_id_user_to_admin_desktop_gadgets', 362),
(637, '2020_02_27_080153_add_column_hash_id_to_admin_desktop_gadgets', 362),
(638, '2020_02_27_114938_add_columns_flex_nums_to_admin_gadgets', 362),
(639, '2020_03_01_192450_add_column_notes_to_direction_exchange', 362),
(640, '2020_03_01_194627_add_column_note_tx_to_tasks_info', 362),
(641, '2020_03_03_180055_create_merchant_transaction_ids_table', 362),
(642, '2020_03_07_172020_create_favorites_links_table', 362),
(643, '2020_03_07_202634_add_column_id_user_to_favorites_links', 362),
(644, '2020_03_08_095558_create_internal_accounts_table', 362),
(645, '2020_03_08_095858_add_column_balance_to_internal_accounts', 362),
(646, '2020_03_08_154439_create_history_internal_accounts_table', 362),
(647, '2020_03_11_182041_add_column_x19_mode_to_direction_exchange', 362),
(648, '2020_03_11_194019_create_directions_fields_table', 362),
(649, '2020_03_11_200306_create_direction_fields_relationships_table', 362),
(650, '2020_03_12_104211_create_tasks_direction_fields_table', 362),
(651, '2020_03_12_210045_add_column_is_email_verification_modal_to_direction_exchange', 362),
(652, '2020_03_12_214910_add_column_is_merchant_fround_to_currencies', 362),
(653, '2020_03_14_092804_add_column_is_email_verification_modal_to_currencies', 362),
(654, '2020_03_14_102453_add_column_type_finished_order_to_tasks', 362),
(655, '2020_03_21_232437_add_column_commission_merchant_currency_to_currencies', 362),
(656, '2020_03_21_234926_add_column_is_pay_commission_to_gateways_merchants', 362),
(657, '2020_03_22_081433_add_column_unique_security_code_to_tasks', 362),
(658, '2020_03_28_094420_add_column_comment_to_gateways_payments', 362),
(659, '2020_03_31_001848_add_column_is_in_banner_to_currencies', 362),
(660, '2020_04_07_110002_create_currencies_analytics_table', 362),
(661, '2020_04_07_110248_add_column_id_currency_to_currencies_analytics', 362),
(662, '2020_04_07_122326_add_column_in_out_orders_to_currencies_analytics', 362),
(663, '2020_04_07_173238_add_column_is_blank_to_notices_exchange', 362),
(664, '2020_04_07_221109_add_column_views_to_news', 362),
(665, '2020_04_08_213847_add_column_kunacode_to_tasks', 362),
(666, '2020_04_08_214158_add_column_provider_id_to_history_excode', 362),
(667, '2020_04_10_002908_change_type_logo_to_payments', 362),
(668, '2020_04_18_001701_add_column_source_name_to_bestchange_rates', 362),
(669, '2020_04_26_182637_add_column_flex_num3_to_admin_desktops', 362),
(670, '2020_04_26_183048_add_column_flex_nums_to_admin_desktops', 362),
(671, '2020_05_04_161332_create_log_error_merchants_table', 362),
(672, '2020_05_05_162328_add_column_is_auto_check_modal_to_currencies', 362),
(673, '2020_05_05_162415_add_column_is_autopay_modal_to_tasks', 362),
(674, '2020_05_05_232250_change_type_custom_field_comment_to_requisites', 362),
(675, '2020_05_08_163228_add_column_is_autopay_limit_to_tasks', 362),
(676, '2020_05_30_214628_add_column_id_edit_data_manager_to_tasks', 362),
(677, '2020_06_02_164512_add_column_is_report_referral_data_to_users', 362),
(678, '2020_06_08_2227091_create_hosts_table', 362),
(679, '2020_06_08_2227092_create_checks_table', 362),
(680, '2020_06_13_174912_create_cashback_error_log_table', 362),
(681, '2020_07_02_115432_add_column_commission_payment_currency_to_currencies', 362),
(682, '2020_07_05_213001_add_column_is_fixed_reserve_to_reserves', 362),
(683, '2020_08_14_194403_change_type_add_course1_to_direction_exchange', 362),
(684, '2020_08_30_123059_add_column_text_to_cashback_error_log', 362),
(685, '2020_09_04_232155_add_column_telegram_id_to_tasks', 362),
(686, '2020_09_05_101950_add_column_telegram_id_to_reviews', 362),
(687, '2020_09_07_222402_add_columns_recount_to_currencies', 362),
(688, '2020_09_07_224945_add_column_max_amount_newbie_direction_exchange', 362),
(689, '2020_09_08_102627_add_column_is_star_to_reserves', 362),
(690, '2020_09_15_084624_add_column_notice_out_to_currencies', 362),
(691, '2020_09_15_085436_add_column_out_price_fee_to_tasks', 362),
(692, '2020_09_16_220221_add_column_is_ban_order_data_to_tasks', 362),
(693, '2020_09_18_100221_create_admin_filters_user_table', 362),
(694, '2020_09_18_130244_create_admin_filter_header_table', 362),
(695, '2020_09_19_075222_add_column_is_common_to_admin_filter_header', 362),
(696, '2020_09_21_002100_create_iex_config_table', 362),
(697, '2020_09_22_102116_add_column_memo_id_to_wallets_addresses', 362),
(698, '2020_09_25_090107_create_email_templates_table', 362),
(699, '2020_09_25_172606_create_template_type_events_table', 362),
(700, '2020_09_26_000528_add_column_id_event_type_to_email_templates', 362),
(701, '2020_09_26_141435_add_column_email_to_to_email_templates', 362),
(702, '2020_09_26_142438_add_column_template_to_email_templates', 362),
(703, '2020_10_02_142238_create_tasks_history_operators_table', 362),
(704, '2020_10_02_142842_add_column_id_from_manager_to_tasks_history_operators', 362),
(705, '2020_10_02_145453_add_column_id_main_operator_to_tasks', 362),
(706, '2020_10_02_150923_add_column_count_change_operator_to_tasks_info', 362),
(707, '2020_10_16_102546_add_column_uuid_to_failed_jobs', 362),
(708, '2020_10_31_184026_create_tasks_comments_table', 362),
(709, '2020_11_06_004142_add_column_day_limit_merchant_to_gateways_merchants', 362),
(710, '2020_11_06_010242_add_column_amount_fault_to_gateways_merchants', 362),
(711, '2020_11_06_092038_add_column_day_limit_amount_merchant_to_gateways_merchants', 362),
(712, '2020_11_06_100036_add_column_manual_pay_order_to_gateways_payments', 362),
(713, '2020_11_08_080511_add_column_is_random_view_shot_to_currencies_table', 362),
(714, '2020_11_09_104143_add_column_is_enable_merchant_button_to_gateways_merchants', 362),
(715, '2020_11_28_105312_add_columns_recount_unique_tome_to_currencies', 362),
(716, '2020_11_29_101150_create_tasks_profits_table', 362),
(717, '2020_12_05_105459_add_column_desc_exchange_to_currencies', 362),
(718, '2021_01_14_083737_add_column_provider_to_oauth_clients', 362),
(719, '2021_02_09_091620_add_columns_in_out_amount_usd_to_currencies_analytics', 362),
(720, '2021_02_16_122100_add_column_rate_speed_to_reviews', 362),
(721, '2021_02_17_095346_add_column_id_widget_to_admin_desktop_gadgets', 362),
(722, '2021_03_02_084714_add_column_slug_name_to_news', 362),
(723, '2021_03_02_210603_add_column_pay_adapter_code_to_currencies', 362),
(724, '2021_03_08_142215_create_user_wallet_historicals_table', 362),
(725, '2021_04_25_171050_add_columns_aml_in_out_to_currencies', 362),
(726, '2021_04_25_191120_create_amlbot_histories', 362),
(727, '2021_04_25_200701_add_columns_is_amlbot_in_to_tasks_info', 362),
(728, '2021_04_28_124333_add_column_view_balance_reward_to_withdrawal_request', 362),
(729, '2021_06_01_100957_create_contests_table', 362),
(730, '2021_06_01_125405_create_contests_conditions_table', 362),
(731, '2021_06_01_181730_create_contests_users_table', 362),
(732, '2021_06_01_211943_add_percent_to_contests_table', 362),
(733, '2021_06_03_122006_add_column_priority_to_group_parser_exchange', 362),
(734, '2021_06_07_103159_create_gateways_table', 362),
(735, '2021_06_27_105740_add_column_method_pay_to_gateways_payments', 362),
(736, '2021_06_28_002227_add_column_method_pay_to_gateways_merchants', 362),
(737, '2021_07_03_192024_add_column_country_code_to_gateways_payments', 362),
(738, '2021_07_04_000006_add_column_num_request_to_gateways_payments', 362),
(739, '2021_07_04_101725_add_column_priority_fee_to_gateways_payments', 362),
(740, '2021_07_07_104320_drop_column_generate_address_to_requisites', 362),
(741, '2021_07_10_082548_add_column_fixed_fee_to_gateways_merchants', 362),
(742, '2021_07_11_001308_add_column_auto_del_order_time_to_direction_exchange', 362),
(743, '2021_07_12_070857_add_column_course_value_to_direction_exchange', 362),
(744, '2021_07_13_200129_add_column_requisites_receive_to_tasks', 362),
(745, '2021_07_13_213800_delete_columns_to_code_currencies', 362),
(746, '2021_07_13_214314_add_column_is_trash_to_code_currency', 362),
(747, '2021_07_13_223722_delete_status_to_filter_currency', 362),
(748, '2021_07_13_230255_add_column_exchange_rate_status_to_your_exchange', 362),
(749, '2021_07_14_004909_delete_columns_to_requisites', 362),
(750, '2021_07_14_071602_add_columns_in_custom_fields_to_currencies', 362),
(751, '2021_07_14_233658_add_columns_out_pay_min_amount_to_currencies', 362),
(752, '2021_07_14_234017_delete_columns_many_to_currencies', 362),
(753, '2021_07_15_150744_delete_id_group_menu_to_menu', 362),
(754, '2021_07_15_175140_add_column_is_allow_amount_space_to_currencies', 362),
(755, '2021_07_15_181426_delete_columns_to_direction_exchange', 362),
(756, '2021_07_15_185128_create_histories_codes_table', 362),
(757, '2021_07_15_213223_change_column_excode_to_tasks', 362),
(758, '2021_07_17_174209_add_column_type_order_to_gateways_payments', 362),
(759, '2021_07_18_000752_add_column_is_auto_take_fee__to_gateways_payments', 362),
(760, '2021_07_24_220048_add_column_ids_merchant_to_currency', 362),
(761, '2021_07_25_005902_add_columns_id_merchant_id_pay_to_tasks', 362),
(762, '2021_07_25_084440_add_column_ids_direction_fields_to_direction_exchange', 362),
(763, '2021_07_25_123106_add_column_comment_to_requisites_fields', 362),
(764, '2021_07_25_132656_delete_columns_custom_fields_to_requisites', 362),
(765, '2021_07_25_163327_change_type_field_to_tasks_fields', 362),
(766, '2021_07_28_190209_add_column_is_pending_blockchain_hash_to_tasks_info', 362),
(767, '2021_07_29_090054_add_column_volume_to_usd_to_gateways_payments', 362),
(768, '2021_07_29_184435_change_column_is_disable_to_gateway_merchants', 362),
(769, '2021_07_31_012559_delete_column_pos_to_bestchange_rates', 362),
(770, '2021_08_04_011528_add_column_class_style_to_tasks_comments', 362),
(771, '2021_08_13_071224_drop_column_uri_to_referral_programs', 362),
(772, '2021_08_22_130433_add_column_min_confirm_to_gateways_merchants', 362),
(773, '2021_09_04_130132_create_merchants_has_currencies_table', 362),
(774, '2021_09_04_145759_drop_column_ids_merchant_to_currencies', 362),
(775, '2021_09_04_171559_add_column_total_usd_to_gateways_merchants', 362),
(776, '2021_09_05_085104_add_column_is_config_done_to_gateways_merchants', 362),
(777, '2021_09_06_075300_add_column_is_auto_check_pay_to_tasks', 362),
(778, '2021_09_07_085521_add_column_internal_rate_to_code_currency', 362),
(779, '2021_09_07_130058_add_column_add_to_course_to_code_currency', 362),
(780, '2021_09_07_155558_add_column_is_import_to_code_currency', 362),
(781, '2021_09_08_090411_create_directions_has_fields_table', 362),
(782, '2021_09_08_091454_drop_column_ids_custom_fields_to_direction_exchange', 362),
(783, '2021_09_11_105749_change_column_id_to_currency_fields', 362),
(784, '2021_09_11_105831_create_currency_in_has_fields_table', 362),
(785, '2021_09_11_114232_create_currency_out_has_fields_table', 362),
(786, '2021_09_11_115208_drop_column_in_custom_fields_to_currencies', 362),
(787, '2021_09_11_222346_add_column_is_import_to_payments', 362),
(788, '2021_09_12_172714_add_column_security_options_to_gateways', 362),
(789, '2021_09_13_070846_add_column_remove_spaces_to_currency_fields', 362),
(790, '2021_09_13_084813_drop_column_name_alt_to_currency_fields', 362),
(791, '2021_09_13_160819_drop_column_name_alt_to_payments', 362),
(792, '2021_09_15_071723_add_column_code_in_to_parser_exchange', 362),
(793, '2021_09_15_133756_create_currency_requisites_has_fields_table', 362),
(794, '2021_09_15_140354_change_type_name_to_requisites_fields', 362),
(795, '2021_09_15_161724_add_column_description_to_directions_fields', 362),
(796, '2021_10_01_143016_add_column_provider_url_to_group_parser_exchange', 362),
(797, '2021_10_02_114558_add_column_is_not_update_to_parser_exchange', 362),
(798, '2021_10_14_093357_add_column_button_name_to_contests', 362),
(799, '2021_10_18_100350_change_type_columns_to_currencies', 362),
(800, '2021_10_18_215714_add_column_is_merchant_fround_to_gateways_merchants', 362),
(801, '2021_10_21_101818_add_column_has_status_to_code_currency', 362),
(802, '2021_11_08_140312_add_column_is_bot_to_links_reviews', 362),
(803, '2022_03_11_111846_create_requisites_has_fields_table', 362),
(804, '2022_03_11_153017_create_getblockbot_histories_table', 362),
(805, '2022_03_11_153055_add_columns_is_getblockbot_in_to_tasks_info', 362),
(806, '2022_03_11_172809_add_columns_is_getblockbot_in_to_currencies', 362),
(807, '2022_05_17_092959_create_cities_table', 362),
(808, '2022_05_18_084918_create_directions_has_cities_table', 362),
(809, '2022_05_18_121716_add_column_city_id_to_tasks_info', 362),
(810, '2022_05_18_183458_add_column_provider_id_to_group_parser_exchange', 362),
(811, '2022_05_18_183908_add_column_provider_id_to_parser_api_keys', 362),
(812, '2022_05_29_114045_add_column_user_browser_to_users', 362),
(813, '2022_06_06_095242_add_column_tech_name_to_direction_exchange', 362),
(814, '2022_06_11_145948_add_column_last_order_at_to_direction_exchange', 362),
(815, '2022_06_11_172644_add_column_user_id_to_pages', 362),
(816, '2022_06_12_010900_add_column_order_queue_to_tasks', 362),
(817, '2022_06_12_011104_add_column_is_mass_payouts_gateways_payments', 362),
(818, '2022_06_12_080157_add_column_id_payment_gateway_to_tasks', 362),
(819, '2022_07_02_230733_add_column_desc_exchange_to_direction_exchange', 362),
(820, '2022_07_04_114312_create_requisites_info_fields_table', 362),
(821, '2022_07_04_114546_create_requisites_has_info_fields_table', 362),
(822, '2022_07_10_205445_create_contests_has_contests_users_table', 362),
(823, '2022_07_11_064619_create_contests_faq_table', 362),
(824, '2022_07_19_162323_create_partner_parser_groups', 362),
(825, '2022_07_19_163645_create_partner_parser_rates_table', 362),
(826, '2022_07_19_173233_add_column_partner_id_to_partner_parser_rates', 362),
(827, '2022_07_19_210756_add_column_id_partner_parser_rate_to_direction_exchange', 362),
(828, '2022_07_19_212312_add_column_number_format_to_partner_parser_rates', 362),
(829, '2022_07_20_084400_add_column_last_updated_at_to_partner_parser_rates', 362),
(830, '2022_07_24_124615_add_column_created_user_id_to_currencies', 362),
(831, '2022_07_25_095946_add_column_last_updated_at_to_group_parser_exchange', 362),
(832, '2022_07_25_160635_add_column_proxy_id_to_group_parser_exchange', 362),
(833, '2022_07_25_164949_add_column_last_imported_at_to_group_parser_exchange', 362),
(834, '2022_07_26_151929_change_default_status_to_parser_exchange', 362),
(835, '2022_07_28_111422_add_indexs_to_tasks', 362),
(836, '2022_07_28_111429_add_indexs_to_user', 362),
(837, '2022_07_28_111636_add_indexs_to_direction_exchange', 362),
(838, '2022_07_31_191308_add_column_section_to_admin_filters_user', 362),
(839, '2022_07_31_191651_add_column_type_filter_to_admin_filter_header', 362),
(840, '2022_08_10_083841_create_bs_bestchange_histories_table', 362),
(841, '2022_08_14_150313_create_promo_codes_table', 362),
(842, '2022_08_14_171451_add_column_id_promo_code_to_tasks', 362),
(843, '2022_08_15_101132_add_column_is_fire_to_currencies', 362),
(844, '2022_08_18_174915_change_typ_name_to_partners', 362),
(845, '2022_08_18_225950_add_column_colors_to_contests', 362),
(846, '2022_08_24_090001_change_column_title_to_faq', 362),
(847, '2022_09_04_121407_change_type_name_value_to_contacts', 362),
(848, '2022_09_13_131053_change_type_desc_link_reviews', 362),
(849, '2022_09_14_151220_add_column_is_fixed_to_notices_exchange', 362),
(850, '2022_09_16_170627_change_type_title_to_advantage', 362),
(851, '2022_09_16_193955_create_direction_exchange_merchants', 362),
(852, '2022_09_28_092836_add_column_name_to_promo_codes', 362),
(853, '2022_10_02_142352_add_column_code_to_parser_exchange', 362),
(854, '2022_10_02_144508_create_parser_formula_table', 362),
(855, '2022_10_02_225346_add_column_id_parser_formula_rate_to_direction_exchange', 362),
(856, '2022_10_03_020154_create_parser_formula_coefficient_table', 362),
(857, '2022_10_03_021536_add_column_is_coefficient_to_parser_formula_rates', 362),
(858, '2022_10_03_090947_add_column_title_to_parser_formula_rates', 362),
(859, '2022_10_06_113128_change_type_instructions_to_direction_exchange', 362),
(860, '2022_10_08_121119_create_currencies_networks_table', 362),
(861, '2022_10_08_185825_create_directions_has_networks_table', 362),
(862, '2022_10_10_102840_add_column_user_style_to_users', 362),
(863, '2022_10_12_094811_add_columns_direction_to_promo_codes', 362),
(864, '2022_10_13_112501_add_column_network_name_to_tasks_info', 362),
(865, '2022_10_17_104249_change_type_first_char_to_currencies', 362),
(866, '2022_10_18_111851_drop_column_first_char_alt_to_currencies', 362),
(867, '2022_10_18_112916_rename_column_first_char_to_currencies', 362),
(868, '2022_10_18_123205_add_column_field_comments_to_currencies', 362),
(869, '2022_10_20_172612_add_columns_id_order_detail_to_direction_notification', 362),
(870, '2022_12_10_180543_add_column_api_key_to_users', 362),
(871, '2022_12_11_165318_add_column_tech_currency_name_to_currencies', 362),
(872, '2022_12_20_192501_change_type_account_number_field_to_currencies', 362),
(873, '2022_12_20_212121_add_column_button_create_order_to_currencies', 362),
(874, '2022_12_21_073924_add_column_button_create_order_text_to_currencies', 362),
(875, '2023_01_19_104847_add_column_tx_hash_to_getblockbot_histories', 362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(876, '2023_01_19_120855_create_aml_analysis_logs_table', 362),
(877, '2023_01_19_124037_add_column_aml_riskscore_to_tasks_info', 362),
(878, '2023_01_19_124738_add_column_is_getblockbot_tx_in_to_currencies', 362),
(879, '2023_01_24_151221_add_column_is_iex_to_blacklist_order', 362),
(880, '2023_01_24_151925_add_column_hash_id_to_blacklist_order', 362),
(881, '2023_01_27_112429_add_column_colors_to_notices_exchange', 362),
(882, '2023_01_30_104340_add_column_kyc_enabled_to_currencies', 362),
(883, '2023_01_31_110119_create_direction_requisites_table', 362),
(884, '2023_01_31_182820_create_directions_has_requisites_table', 362),
(885, '2023_01_31_184401_add_column_id_direction_requisites_to_tasks', 362),
(886, '2023_01_31_201337_create_tasks_requisites_table', 362),
(887, '2023_01_31_202617_add_column_type_output_requisites_to_direction_exchange', 362),
(888, '2023_02_01_011419_add_column_formalization_text_to_direction_exchange', 362),
(889, '2023_02_01_014140_add_column_formalization_text_to_currencies', 362),
(890, '2023_02_01_024210_add_column_exchange_fee_to_gateways_merchants', 362),
(891, '2023_02_04_141904_create_task_single_log_confirm_table', 362),
(892, '2023_02_05_095356_add_columns_is_aml_check_cost_reserve_to_currencies', 362),
(893, '2023_02_05_101347_add_column_int_status_verification_card_to_tasks', 362),
(894, '2023_02_05_130611_create_currencies_info_table', 362),
(895, '2023_02_05_222203_create_geo_country_list_table', 362),
(896, '2023_02_05_225526_create_direction_has_forbidden_countries_table', 362),
(897, '2023_02_06_000934_create_direction_has_allowed_countries_table', 362),
(898, '2023_02_07_093706_add_column_network_code_to_currencies', 362),
(899, '2023_02_09_132920_create_verification_card_category_table', 362),
(900, '2023_02_09_132929_create_verification_card_instructions_table', 362),
(901, '2023_02_11_021346_create_merchant_transaction_hash_table', 362),
(902, '2023_02_11_083240_add_column_is_wait_hash_pay_to_tasks_info', 362),
(903, '2023_02_11_083822_create_pay_transaction_hash_table', 362),
(904, '2023_02_12_094143_create_direction_exchange_modes_table', 362),
(905, '2023_02_12_094225_create_directions_has_modes_table', 362),
(906, '2023_02_13_011426_create_links_footer_groups_table', 362),
(907, '2023_02_13_011633_create_links_footers_table', 362),
(908, '2023_02_13_152210_add_column_give_price_merhant_to_tasks', 362),
(909, '2023_02_14_080835_add_column_order_id_to_tasks', 362),
(910, '2023_02_14_161436_add_column_aml_text_to_currencies', 362),
(911, '2023_02_14_173111_add_column_aml_analyses_count_to_currencies', 362),
(912, '2023_02_14_174112_add_column_price_to_aml_analysis_logs', 362),
(913, '2023_02_14_180900_add_column_aml_day_limit_count_to_currencies', 362),
(914, '2023_02_17_001304_add_column_min_count_exchanges_client_to_direction_exchange', 362),
(915, '2023_02_17_072833_add_column_getblockbot_tx_in_amount_to_currencies', 362),
(916, '2023_02_17_204125_change_column_type_page_title_to_pages', 362),
(917, '2023_02_17_213000_add_index_public_id_to_tasks', 362),
(918, '2023_02_18_102751_create_whitebit_logs_table', 362),
(919, '2023_02_18_180633_add_column_amlbot_tx_in_amount_to_currencies', 362),
(920, '2023_02_18_183553_add_column_id_currency_to_aml_analysis_logs', 362),
(921, '2023_02_18_192345_add_column_aml_service_name_to_currencies', 362),
(922, '2023_03_17_191608_add_column_instruction_exchange_to_currencies', 362),
(923, '2023_03_18_003311_add_column_order_button_i_pay_to_direction_exchange', 362),
(924, '2023_03_18_102800_add_column_blockchain_network_congestion_to_currencies', 362),
(925, '2023_03_31_195258_add_column_hide_check_balance_to_gateways_payments', 362),
(926, '2023_04_05_104512_add_column_is_allow_telegram_bot_to_direction_exchange', 362),
(927, '2023_04_06_133413_add_column_recalculated_at_to_tasks_info', 362),
(928, '2023_04_06_190726_create_reserves_files_table', 362),
(929, '2023_04_06_191017_create_reserves_files_groups_table', 362),
(930, '2023_04_06_200016_add_column_id_file_reserve_to_reserves', 362),
(931, '2023_04_06_223518_add_column_id_server_reserve_to_reserves', 362),
(932, '2023_04_07_013252_add_column_is_kyc_checkbox_to_currencies', 362),
(933, '2023_04_07_120748_add_column_is_unique_shot_to_requisites', 362),
(934, '2023_04_07_125130_add_column_transfer_to_account_tasks', 362),
(935, '2023_04_07_142935_create_direction_exchange_cities_table', 362),
(936, '2023_04_07_170937_add_column_min_price_max_price_to_direction_exchange_cities', 362),
(937, '2023_04_07_195102_add_column_id_country_to_direction_exchange_cities', 362),
(938, '2023_04_07_215432_add_column_country_id_name_to_tasks_info', 362),
(939, '2023_04_08_125936_add_column_manual_rate_value_to_direction_exchange', 362),
(940, '2014_04_02_193005_create_translations_table', 363),
(941, '2014_10_12_100000_create_password_reset_tokens_table', 363),
(942, '2023_04_09_191116_create_tasks_messages_table', 363),
(943, '2023_04_09_215747_add_column_type_user_to_tasks_messages', 363),
(944, '2023_04_10_020458_create_tasks_managers_styles_table', 363),
(945, '2023_04_10_031006_add_column_is_view_to_tasks_messages', 363),
(946, '2023_04_10_093222_add_column_is_blocked_chat_to_tasks_info', 363),
(947, '2023_04_13_232033_add_column_parser_source_name_to_direction_exchange', 363),
(948, '2023_04_14_010948_add_columns_index2_to_tasks', 363),
(949, '2023_04_15_000902_drop_directions_has_cities_table', 363),
(950, '2023_04_15_103904_add_column_name_to_currencies_log', 363),
(951, '2023_04_16_131147_drop_column_is_telescope_to_users', 363),
(952, '2023_04_16_210500_add_column_scan_name_to_tasks', 363),
(953, '2023_04_19_220750_add_index_many_column_to_tasks', 363),
(954, '2023_04_19_221323_add_index_many_column_to_tasks_info', 363),
(955, '2023_04_19_221904_add_indexes_to_currencies', 363),
(956, '2023_04_19_222437_add_indexes_many_to_direction_exchange', 363),
(957, '2023_04_19_222824_add_indexes_to_pay_transaction_hash', 363),
(958, '2023_04_19_222941_add_indexes_many_to_merchant_transaction_hash', 363),
(959, '2023_04_19_223112_add_indexes_to_requisites', 363),
(960, '2023_04_19_223218_add_indexes_many_to_parser_exchange', 363),
(961, '2023_04_19_223315_add_indexes_to_bestchange_rates', 363),
(962, '2023_04_19_223434_add_indexes_many_to_reserves', 363),
(963, '2023_04_19_223651_add_indexes_to_verification_card', 363),
(964, '2023_04_21_182221_add_column_token_code_to_personal_access_tokens', 363),
(965, '2023_04_22_123929_add_column_tech_name_to_currencies', 363),
(966, '2023_04_22_141544_change_type_columns_to_news', 363),
(967, '2023_04_23_185126_add_index_id_task_to_tasks_info', 363),
(968, '2023_04_23_185218_add_index_is_spam_to_tasks', 363),
(969, '2023_04_23_185636_add_index_tasks_user_to_tasks_user', 363),
(970, '2023_04_26_000210_add_column_is_status_pay_to_tasks_table', 363),
(971, '2023_04_26_134107_add_column_other_docs_to_direction_exchange', 363),
(972, '2023_04_26_141300_add_column_other_docs_in_out_to_currencies', 363),
(973, '2023_04_26_183057_add_column_is_subtract_to_gateways_payments', 363),
(974, '2023_04_26_184424_add_column_currency_code_to_gateways_payments', 363),
(975, '2023_05_04_230922_change_type_body_to_logs_email', 363),
(976, '2023_05_04_232555_create_iex_script_config_table', 363),
(977, '2023_05_06_194608_add_column_is_allow_order_to_currencies', 363),
(978, '2023_05_16_152742_change_type_add_comm_to_direction_exchange_cities', 363),
(979, '2023_05_16_164156_delete_column_oth_deduct_comm_to_direction_exchange', 363),
(980, '2023_05_16_183331_create_direction_exchange_exchange_amount_table', 363),
(981, '2023_05_17_010122_add_column_is_notify_exchange_amount_to_direction_exchange', 363),
(982, '2023_05_17_015832_add_column_add_course1_s_to_direction_exchange', 363),
(983, '2023_05_17_091812_add_column_logo_svg_to_payments', 363),
(984, '2023_05_18_132515_add_column_is_guest_to_users', 363),
(985, '2023_05_18_162113_add_column_is_disable_auto_reg_to_direction_exchange', 363),
(986, '2023_05_18_164959_add_column_referral_hash_to_tasks', 363),
(987, '2023_05_19_011411_add_column_label_floating_to_direction_exchange', 363),
(988, '2023_05_19_012028_drop_column_export_label_city_to_direction_exchange', 363),
(989, '2023_05_19_165853_add_columns_oth_comm2_to_direction_exchange', 363),
(990, '2023_05_19_191302_change_type_oth_comm_percent_to_direction_exchange', 363),
(991, '2023_05_19_205053_add_column_oth_min2_comm_to_direction_exchange', 363),
(992, '2023_05_19_223132_add_column_pay_comm_to_direction_exchange', 363),
(993, '2023_05_20_025451_add_columns_commpay_to_tasks', 363),
(994, '2023_05_21_121413_add_column_pay_amount_to_gateways_merchants', 363),
(995, '2023_05_21_205951_add_column_auto_del_order_hour_to_direction_exchange', 363),
(996, '2023_05_21_212722_add_column_sorting_admin_to_direction_exchange', 363),
(997, '2023_05_21_215437_add_column_sorting_to_directions_fields', 363),
(998, '2023_05_22_000233_add_column_sorting_admin_to_currencies', 363),
(999, '2023_05_22_002842_add_column_is_verified_cabinet_to_currencies', 363),
(1000, '2023_05_22_004246_add_column_sorting_to_currency_fields', 363),
(1001, '2023_05_22_005502_add_column_sorting_out_to_currency_fields', 363),
(1002, '2023_05_22_090735_add_column_personal_discount_to_users', 363),
(1003, '2023_05_22_105420_receiving_price_discount_to_tasks', 363),
(1004, '2023_05_22_151449_add_column_is_enable_user_discount_to_direction_exchange', 363),
(1005, '2023_05_22_184645_create_language_contents_table', 363),
(1006, '2023_05_22_211107_add_column_welcome_description_to_language_contents', 363),
(1007, '2023_05_22_214838_add_column_telegram_block_title_to_language_contents', 363),
(1008, '2023_05_22_221014_add_column_description_verification_card_to_language_contents', 363),
(1009, '2023_05_22_222018_change_type_name_to_tasks_status', 363),
(1010, '2023_05_23_014347_change_type_tech_currency_name_to_currencies', 363),
(1011, '2023_05_23_094309_create_log_merchants_events_table', 363),
(1012, '2023_05_23_124808_create_logs_autopayment_events_table', 363),
(1013, '2023_05_23_131537_change_type_value_to_geo_country_list', 363),
(1014, '2023_05_23_143235_add_column_sitename_to_language_contents', 363),
(1015, '2023_05_23_233818_add_column_main_value_header_to_language_contents', 363),
(1016, '2023_05_24_005420_add_column_personal_ref_discount_to_users', 363),
(1017, '2023_05_24_010234_add_column_chat_app_id_to_language_contents', 363),
(1018, '2023_05_24_164552_add_column_pay_amount_to_gateways_payments', 363),
(1019, '2023_05_24_204649_create_getblock_requests_table', 363),
(1020, '2023_05_24_210901_add_column_options_to_getblock_requests', 363),
(1021, '2023_05_25_101421_create_currencies_templates_table', 363),
(1022, '2023_05_25_122417_add_column_type_view_info_to_currencies_templates', 363),
(1023, '2023_05_25_172313_add_column_desc_exchange_dop_to_direction_exchange', 363),
(1024, '2023_05_25_223532_add_column_first_value_to_currencies', 363),
(1025, '2023_05_26_015154_add_column_verification_info_to_currencies', 363),
(1026, '2023_05_26_092859_add_column_max_ref_discount_to_users', 363),
(1027, '2023_05_28_002025_add_column_day_names_to_job_settings', 363),
(1028, '2023_05_28_092423_create_job_schedules_table', 363),
(1029, '2023_05_28_150255_add_column_working_online_text_to_language_contents', 363),
(1030, '2023_05_28_195645_add_column_referral_info_to_language_contents', 363),
(1031, '2023_05_28_203936_add_column_description_pr_to_language_contents', 363),
(1032, '2023_05_28_204136_add_column_description_review_to_language_contents', 363),
(1033, '2023_05_30_144203_add_column_icon_to_contacts', 363),
(1034, '2023_05_30_180526_add_column_working_offline_notify_to_language_contents', 363),
(1035, '2023_06_03_080158_change_type_title_to_referral_programs', 363),
(1036, '2023_06_07_193059_change_type_title_to_reward_programs', 363),
(1037, '2023_06_09_002806_add_column_type_profit_field_to_direction_exchange', 363),
(1038, '2023_06_10_211707_create_banners_table', 363),
(1039, '2023_06_10_215722_create_banners_buttons_table', 363),
(1040, '2023_06_10_220501_create_banners_has_buttons_table', 363),
(1041, '2023_06_11_133246_change_type_name_to_pending_order_status', 363),
(1042, '2023_06_11_133311_change_type_name_to_tasks_rejection_status', 363),
(1043, '2023_06_12_014441_add_column_icons_to_contests', 363),
(1044, '2023_06_12_020249_add_column_info_to_contests', 363),
(1045, '2023_06_13_103252_add_column_is_blank_to_links_footers', 363),
(1046, '2023_06_19_202228_add_column_order_num_to_gateways_merchants', 363),
(1047, '2023_06_22_002142_add_column_verification_text_to_currencies', 363),
(1048, '2023_06_24_123337_add_column_recount_course_text_to_currencies', 363),
(1049, '2023_06_25_014422_create_direction_templates_table', 363),
(1050, '2023_06_27_093439_add_column_int_error_type_to_tasks', 363),
(1051, '2023_06_29_203646_add_column_bank_name_to_gateways_merchants', 363),
(1052, '2023_06_30_110318_add_column_direction_to_gateways_payments', 363),
(1053, '2023_07_02_073640_add_column_s_order_notify_text_to_language_contents', 363),
(1054, '2023_07_04_173449_add_column_type_pay_to_gateways_merchants', 363),
(1055, '2023_07_16_201738_add_column_dot_not_remember_data_to_tasks_info', 363),
(1056, '2023_07_21_015337_create_tasks_comments_users', 363),
(1057, '2023_07_21_022308_add_column_type_output_requisites_to_currencies', 363),
(1058, '2023_07_26_033538_add_column_text_order_success_to_direction_exchange', 363),
(1059, '2023_08_08_083642_change_type_description_to_contests_conditions', 363),
(1060, '2023_08_17_093719_add_columns_is_email_other_docs_to_direction_exchange', 363),
(1061, '2023_08_18_100259_change_type_name_to_filter_currency', 363),
(1062, '2023_08_18_144315_add_column_colors_to_banners_buttons', 363),
(1063, '2023_08_18_173328_add_column_colors_to_banners', 363),
(1064, '2023_08_18_174921_add_column_images_banner_to_banners', 363),
(1065, '2023_08_20_103139_add_column_site_account_to_gateways_merchants', 363),
(1066, '2023_08_22_204015_add_column_security_order_page_code_to_users', 363),
(1067, '2023_08_22_223315_add_column_is_enable_order_paginate_to_users', 363),
(1068, '2023_08_23_072059_add_column_language_field_to_directions_fields', 363),
(1069, '2023_08_23_105003_add_column_site_account_to_gateways_payments', 363),
(1070, '2023_08_23_185608_add_column_auto_pay_order_pay_to_currencies', 363),
(1071, '2023_08_24_102258_add_column_course_display_to_tasks_status_log', 363),
(1072, '2023_08_27_102200_add_column_is_allow_file_to_currencies', 363),
(1073, '2023_08_28_105838_add_column_is_send_mail_create_to_tasks', 363),
(1074, '2023_08_28_224737_add_column_text_color_to_contacts', 363),
(1075, '2023_08_29_092652_add_column_text_color_to_menu', 363),
(1076, '2023_08_29_143015_create_user_verification_table', 363),
(1077, '2023_08_29_194840_add_column_ip_adddress_to_user_verification', 363),
(1078, '2023_08_30_084911_add_column_is_verify_account_to_users', 363),
(1079, '2023_08_30_140736_add_column_is_verified_account_to_direction_exchange', 363),
(1080, '2023_08_30_202024_add_column_code_to_competitor_rates', 363),
(1081, '2023_08_30_202834_add_column_code_to_file_parser_rates', 363),
(1082, '2023_09_01_114947_create_course_update_time_logs_table', 363),
(1083, '2023_09_03_165245_add_column_is_hidden_ip_address_to_users', 363),
(1084, '2023_09_08_152227_add_column_bank_name_to_gateways_payments', 363),
(1085, '2023_09_14_052753_add_column_jivosite_text_message_to_language_contents', 363),
(1086, '2023_09_14_115010_create_tasks_files_table', 363),
(1087, '2023_09_14_211129_add_column_photos_to_requisites', 363),
(1088, '2023_09_16_102012_create_parser_exchange_http_logs_table', 363),
(1089, '2023_09_18_054654_add_column_is_enabled_step_order_to_currencies', 363),
(1090, '2023_09_18_060728_create_order_steps_table', 363),
(1091, '2023_09_18_064112_add_column_id_order_step_to_tasks', 363),
(1092, '2023_09_22_062356_add_column_code_currency_to_gateways_merchants', 363),
(1093, '2023_09_22_062403_add_column_code_currency_to_gateways_payments', 363),
(1094, '2023_09_22_080458_add_column_network_code_out_to_currencies', 363),
(1095, '2023_09_27_162844_create_rules_pages_table', 363),
(1096, '2023_09_27_204151_add_column_title_rules_page_to_language_contents', 363),
(1097, '2023_09_29_131537_add_column_sorting_to_banners', 363),
(1098, '2023_09_30_053347_add_column_id_parser_formula_to_code_currencies', 363),
(1099, '2023_09_30_194847_add_column_icon_to_order_steps', 363),
(1100, '2023_09_30_211151_create_applications_steps_logs_table', 363),
(1101, '2023_10_01_062322_create_transit_requisites_table', 363),
(1102, '2023_10_01_084533_add_column_is_request_payment_type_to_tasks', 363),
(1103, '2023_10_01_090256_add_column_description_request_payment_to_language_contents', 363),
(1104, '2023_10_01_203105_add_column_text_order_confirm_to_direction_exchange', 363),
(1105, '2023_10_01_211239_add_column_order_button_i_confirm_to_direction_exchange', 363),
(1106, '2023_10_02_052740_add_column_color_to_direction_notification', 363),
(1107, '2023_10_02_081632_add_column_notice_process_desc_to_direction_exchange', 363),
(1108, '2023_10_02_143035_add_column_style_width_to_referral_programs', 363),
(1109, '2023_10_02_202200_create_parser_formula_logs_table', 363),
(1110, '2023_10_03_191948_add_column_is_wallet_issued_to_tasks', 363),
(1111, '2023_10_04_070015_create_logs_autopayment_orders_events_table', 363),
(1112, '2023_10_04_190737_add_column_requisites_description_to_tasks', 363),
(1113, '2023_10_05_193640_add_column_valid_account_error_to_currencies', 363),
(1114, '2023_10_05_203840_add_column_min_max_error_message_to_currencies', 363),
(1115, '2023_10_05_220218_add_column_account_number_field_text_to_currencies', 363),
(1116, '2023_10_06_081559_add_column_aml_address_risk_to_tasks_info', 363),
(1117, '2023_10_06_125054_add_column_text_message_order_to_tasks', 363),
(1118, '2023_10_06_212706_add_column_type_price_to_parser_exchange', 363),
(1119, '2023_10_07_101602_add_column_status_to_gateways', 363),
(1120, '2023_10_07_172354_create_file_storages_table', 363),
(1121, '2023_10_07_202251_is_local_image_to_payments', 363),
(1122, '2023_10_07_212126_is_local_image_to_verification_card', 363),
(1123, '2023_10_07_212620_is_local_image_to_news', 363),
(1124, '2023_10_11_075855_add_column_number_format_xml_to_currencies', 363),
(1125, '2023_10_26_200624_create_aml_services_table', 363),
(1126, '2023_10_26_210126_delete_columns_aml_to_currencies', 363),
(1127, '2023_10_26_211102_add_column_aml_new_column_to_currencies', 363),
(1128, '2023_10_28_061937_add_column_aml_column_to_tasks_info', 363),
(1129, '2023_12_07_103814_add_column_referral_profit_to_user_balance', 363),
(1130, '2023_12_09_180431_add_column_id_task_to_reviews', 363),
(1131, '2023_12_09_183644_delete_columns2_to_reviews', 363),
(1132, '2023_12_10_110429_add_column_email_to_verification_card', 363),
(1133, '2023_12_14_070417_create_withdrawal_wallets_table', 363),
(1134, '2023_12_14_114340_delete_column_is_fixed_to_notice_exchange', 363),
(1135, '2023_12_14_161308_create_tasks_check_images_table', 363),
(1136, '2023_12_14_165845_add_column_is_file_check_to_tasks', 363),
(1137, '2023_12_21_124943_add_column_style_width_to_reward_programs', 363),
(1138, '2023_12_23_090341_add_column_is_not_callback_to_tasks', 363),
(1139, '2023_12_24_161136_create_currencies_labels_table', 363),
(1140, '2023_12_24_161405_add_column_id_label_to_currencies', 363),
(1141, '2024_01_02_120301_add_column_version_to_reviews', 363),
(1142, '2024_01_03_220700_add_columns_is_checkbox_rules_and_aml_to_users', 363),
(1143, '2024_01_10_095453_change_type_string_bank_name', 363),
(1144, '2024_01_11_074214_add_column_bestchange_city_to_direction_exchange', 363),
(1145, '2024_01_12_195928_add_column_count_review_to_links_reviews', 363),
(1146, '2024_01_15_081318_add_column_text_message_to_verification_card', 363),
(1147, '2024_01_15_203747_add_column_text_request_payment_to_requisites', 363),
(1148, '2024_01_16_090627_add_column_id_user_to_reviews', 363),
(1149, '2024_01_21_080719_add_columns_type_field_to_currency_fields', 363),
(1150, '2024_01_21_171408_add_column_multiplicity_to_direction_exchange', 363),
(1151, '2024_01_25_135548_change_type_method_pay_string_to_gateways_merchants', 363),
(1152, '2024_02_02_201010_add_column_process_method_table', 363),
(1153, '2024_02_03_192606_change_table_unpaid_items_to_unpaid_items', 363),
(1154, '2024_02_03_192935_drop_column_rules_cron_to_unpaid_items', 363),
(1155, '2024_02_07_114708_change_type_api_transfer_id_to_pay_transaction_hash', 363),
(1156, '2024_02_08_135029_create_parser_exchange_error_rates_table', 363),
(1157, '2024_02_08_182848_create_direction_exchange_min_price_logs_table', 363),
(1158, '2024_02_16_104803_add_column_receiving_price_with_promocode_to_tasks', 363),
(1159, '2024_02_16_114137_add_column_promo_code_value_to_tasks', 363),
(1160, '2024_02_16_114415_add_column_promo_code_discount_to_tasks', 363),
(1161, '2024_02_18_182436_delete_column_restriction_to_users', 363),
(1162, '2024_02_19_073749_drop_column_unlimited_reserve_to_currencies', 363),
(1163, '2024_02_23_121824_change_type_course_float_to_tasks', 363),
(1164, '2024_02_23_143425_drop_table_is_backup_to_users', 363),
(1165, '2024_02_26_184551_change_type_method_pay_to_gateways_payments', 363),
(1166, '2024_03_10_232215_drop_column_is_vip_client_to_users', 363),
(1167, '2024_03_12_230002_add_column_id_user_to_permissions', 363),
(1168, '2024_03_15_170328_add_column_start_with_to_direction_fields', 363),
(1169, '2024_03_15_172215_add_column_end_with_to_direction_fields', 363),
(1170, '2024_03_15_172703_add_column_start_end_with_to_currency_fields', 363),
(1171, '2024_03_15_233043_add_column_id_manager_to_verification_card', 363),
(1172, '2024_03_16_114556_add_column_method_request_payment_to_currencies', 363),
(1173, '2024_03_16_123918_add_column_method_request_payment_to_tasks', 363),
(1174, '2024_03_16_215825_drop_column_sorting_reserve_to_currencies', 363),
(1175, '2024_03_17_061747_drop_column_sorting_admin_to_direction_exchange', 363),
(1176, '2024_03_17_085002_change_type_is_out_aml_check_wallet_to_currencies', 363),
(1177, '2024_03_17_150532_create_aml_services_address_log_table', 363),
(1178, '2024_03_20_085534_drop_column_is_import_to_code_currencies', 363),
(1179, '2024_03_21_172238_change_recount_time_hours_to_currencies', 363),
(1180, '2024_03_23_092716_drop_column_is_horizon_to_users', 363),
(1181, '2024_03_25_130357_add_column_is_run_process_to_tasks', 363),
(1182, '2024_03_25_225402_add_column_sorting_to_direction_exchange_cities', 363),
(1183, '2024_03_27_210448_create_tasks_operators_logs_table', 363),
(1184, '2024_03_27_221901_add_column_id_task_to_tasks_operators_logs', 363),
(1185, '2024_03_29_082842_add_column_is_success_to_aml_services_logs', 363),
(1186, '2024_03_29_141247_drop_columns_aml_check_cost_to_currencies', 363),
(1187, '2024_03_29_174035_drop_columns_currency_position_payout_to_tasks_info', 363),
(1188, '2024_03_29_225346_add_column_request_payment_text_to_currencies', 363),
(1189, '2024_03_29_225655_drop_column_text_request_payment_to_payment_requisites', 363),
(1190, '2024_03_31_072001_add_column_language_to_verification_card', 363),
(1191, '2024_03_31_210928_add_column_is_from_verification_card_to_tasks', 363),
(1192, '2024_04_01_090900_drop_column_int_status_verification_card_to_tasks', 363),
(1193, '2024_04_01_194156_drop_column_is_email_other_docs_to_direction_exchange', 363),
(1194, '2024_04_01_233458_create_contacts_groups_table', 363),
(1195, '2024_04_01_233547_add_column_id_group_to_contacts', 363),
(1196, '2024_04_04_131301_add_column_receiving_price_with_discount_to_tasks', 363),
(1197, '2024_04_04_140734_add_column_user_discount_to_tasks', 363),
(1198, '2024_04_04_145234_add_column_id_reward_to_users', 363),
(1199, '2024_04_04_152832_add_column_order_total_exchanges_to_users', 363),
(1200, '2024_04_05_121336_drop_column_cashback_tasks_info', 363),
(1201, '2024_04_05_122116_drop_column_is_not_bonus_to_direction_exchange', 363),
(1202, '2024_04_05_122636_drop_column_balance_reward_to_user_balance', 363),
(1203, '2024_04_05_123122_drop_column_reward_to_withdrawal_request', 363),
(1204, '2024_04_06_091544_add_column_id_manager_to_tasks_messages', 363),
(1205, '2024_04_06_110549_add_column_public_id_to_tasks_messages', 363),
(1206, '2024_04_08_110330_drop_column_export_label_minamount_to_direction_exchange', 363),
(1207, '2024_04_08_110728_drop_column_number_format_xml_to_currencies', 363),
(1208, '2024_04_08_143741_drop_column_is_pay_cashback_to_users', 363),
(1209, '2024_04_09_214330_drop_column_is_income_banner_to_currencies', 363),
(1210, '2024_04_10_064553_drop_column_min_out_amount_verification_to_currencies', 363),
(1211, '2024_04_12_152650_add_column_is_active_role_to_users', 363),
(1212, '2024_04_15_074744_drop_column_is_allow_amount_space_to_currencies', 363),
(1213, '2024_04_17_204143_add_column_sorting_to_tasks_status', 363),
(1214, '2024_04_18_004124_add_column_is_pay_referral_bonus_to_tasks', 363),
(1215, '2024_04_22_065827_create_bestchange_currency_codes_table', 363),
(1216, '2024_04_22_074729_create_bestchange_cities_table', 363),
(1217, '2024_04_24_153642_add_column_course_display_fixed_to_tasks', 363),
(1218, '2024_04_27_203442_create_api_logs_table', 363),
(1219, '2024_04_28_181254_add_index_to_tasks', 363),
(1220, '2024_05_01_205018_change_type_role_expired_at_to_users', 363),
(1221, '2024_05_03_100254_add_column_currency_code_value_to_bestchange_currency_codes', 363),
(1222, '2024_05_03_104231_create_tasks_operators_table', 363),
(1223, '2024_05_03_131912_drop_column_scans_to_tasks', 363),
(1224, '2024_05_03_140549_drop_column_id_main_operator_to_tasks', 363),
(1225, '2024_05_03_172011_change_type_value_to_cache', 363),
(1226, '2024_05_09_193914_drop_column_provider_url_to_group_parser_exchange', 363),
(1227, '2024_05_10_081729_change_type_is_delete_to_group_parser_exchange', 363),
(1228, '2024_05_16_073741_add_column_archived_at_to_tasks', 363),
(1229, '2024_05_16_223434_create_project_settings_table', 363),
(1230, '2024_05_16_224521_create_security_settings', 363),
(1231, '2024_05_16_233950_create_requisites_attach_logs', 363),
(1232, '2024_05_16_234746_add_column_id_task_to_requisites_attach_logs', 363),
(1233, '2024_05_18_222436_drop_column_fee_to_currencies', 363),
(1234, '2024_05_19_083104_add_column_transfer_to_account_type_to_tasks', 363),
(1235, '2024_05_19_083634_create_requisites_logs_table', 363),
(1236, '2024_05_19_094801_add_column_title_to_oles', 363),
(1237, '2024_05_20_230838_add_column_logged_ip_address_to_users', 363),
(1238, '2024_05_21_185644_add_column_is_frontend_to_users', 363),
(1239, '2024_06_06_163952_add_column_reviews_block_title_to_language_contents', 364),
(1240, '2024_06_08_115901_create_bestchange_directions_table', 364),
(1241, '2024_06_08_153105_drop_columns_bestchange_to_direction_exchange', 364),
(1242, '2024_06_09_171350_drop_column_xml_juridical_to_direction_exchange', 364),
(1243, '2024_06_09_173011_add_column_label_floating_percent_to_direction_exchange', 364),
(1244, '2024_06_11_224816_drop_columns_to_tasks_info', 364),
(1245, '2024_06_11_224901_add_column_type_freeze_scam_to_tasks_info', 364),
(1246, '2024_06_12_081215_add_column_id_task_to_blacklist_order', 364),
(1247, '2024_06_12_090525_drop_columns_is_bestchange_parser_to_tasks_info', 364),
(1248, '2024_06_14_114057_change_type_deadline_to_direction_exchange', 364),
(1249, '2024_06_14_122436_add_columns_reserve_field_to_direction_exchange', 364),
(1250, '2024_06_15_131334_add_column_service_id_to_wallets_addresses', 364),
(1251, '2024_06_15_225110_add_column_ext_options_to_gateways_merchants', 364),
(1252, '2024_06_16_103109_add_column_gateway_alias_to_gateways_merchants', 364),
(1253, '2024_06_16_110534_add_column_filename_to_gateways_merchants', 364),
(1254, '2024_06_16_113135_drop_columns_to_gateways_merchants', 364),
(1255, '2024_06_16_212422_add_column_new_limits_to_gateways_merchants', 364),
(1256, '2024_06_16_224201_add_merchant_columns_to_currencies', 364),
(1257, '2024_06_16_231428_add_column_priority_to_gateways_merchants', 364),
(1258, '2024_06_17_075036_add_columns_id_task_to_wallets_addresses', 364),
(1259, '2024_06_17_090702_drop_columns_to_wallets_addresses', 364),
(1260, '2024_06_17_093726_create_merchants_transaction_data_table', 364),
(1261, '2024_06_17_095720_add_column_id_merchant_to_merchants_transaction_data', 364),
(1262, '2024_06_17_173925_create_tasks_requisites_data', 364),
(1263, '2024_06_17_191804_add_column_is_checkout_to_merchants_transaction_data', 364),
(1264, '2024_06_18_092621_drop_columns_big_id_to_tasks', 364),
(1265, '2024_06_18_112818_create_tasks_requisites_attached', 364),
(1266, '2024_06_18_161355_create_currency_pay_table', 364),
(1267, '2024_06_18_170306_add_columns_to_gateways_payments', 364),
(1268, '2024_06_18_172828_drop_columns_to_gateways_payments', 364),
(1269, '2024_06_18_192702_add_columns_to_gateways_payments', 364),
(1270, '2024_06_18_193422_add_columns_to_currencies', 364),
(1271, '2024_06_18_224451_add_column_is_waiting_hash_to_pay_transaction_hash', 364),
(1272, '2024_06_18_230045_add_column_id_pay_to_pay_transaction_hash', 364),
(1273, '2024_06_19_203153_create_merchant_transaction_webhooks', 364),
(1274, '2024_06_19_221317_add_column_amount_to_merchant_transaction_webhooks', 364),
(1275, '2024_06_19_235307_add_columns_to_gateways_merchants', 364),
(1276, '2024_06_20_111429_create_gateway_payments_logs_table', 364),
(1277, '2024_06_21_130725_create_pay_transaction_data_table', 364),
(1278, '2024_06_22_103922_add_column_aml_check_wallet_error_to_currencies', 364),
(1279, '2024_06_22_123955_create_aml_response_data_table', 364),
(1280, '2024_06_22_153235_add_columns_to_aml_services', 364),
(1281, '2024_06_22_154855_change_column_to_currencies', 364),
(1282, '2024_06_22_200213_add_column_partner_method_to_user', 364),
(1283, '2024_06_23_182157_add_column_ext_options_to_aml_services', 364),
(1284, '2024_06_23_215715_drop_columns_to_tasks_info', 364),
(1285, '2024_06_23_234423_add_columns_to_aml_response_data', 364),
(1286, '2024_06_25_233117_add_column_error_for_aml_check_tx_to_currencies', 364),
(1287, '2024_06_26_064448_create_telegram_notifications_table', 364),
(1288, '2024_06_26_114753_add_column_to_telegram_notifications', 364),
(1289, '2024_06_27_000540_add_column_id_direction_exchange_to_merchants_transaction_data', 364),
(1290, '2024_06_27_000620_add_column_id_direction_exchange_to_pays_transaction_data', 364),
(1291, '2024_06_27_160635_drop_column_to_direction_exchange', 364),
(1292, '2024_06_27_193021_add_columns_to_direction_exchange', 364),
(1293, '2024_06_27_221448_create_direction_exchange_pay_table', 364),
(1294, '2024_06_27_222201_add_columns_to_direction_exchange', 364),
(1295, '2024_06_28_000212_add_column_allow_autopay_to_gateways_payments', 364),
(1296, '2024_06_28_004025_drop_columns_to_tasks', 364),
(1297, '2024_06_28_005245_drop_columns_to_currencies', 364),
(1298, '2024_06_28_111443_drop_column_to_tasks', 364),
(1299, '2024_06_29_151656_add_column_name_to_requisites', 364),
(1300, '2024_06_30_173919_drop_column_id_group_direction_to_direction_exchange', 364),
(1301, '2024_07_02_113907_add_column_from_amount_to_parser_formula_logs', 364),
(1302, '2024_07_03_062324_drop_column_skip_reserve_to_tasks', 364),
(1303, '2024_07_03_072217_drop_column_is_reserve_to_tasks', 364),
(1304, '2024_07_03_190527_add_columns_to_tasks', 364),
(1305, '2024_07_04_141333_create_reserves_manual_events_table', 364),
(1306, '2024_07_04_184713_create_reserves_logs_table', 364),
(1307, '2024_07_05_104830_drop_column_sorting_admin_to_currencies', 364),
(1308, '2024_07_16_130439_add_column_opt_params_to_tasks', 364),
(1309, '2024_07_16_154356_drop_column_text_message_order_to_tasks', 364),
(1310, '2024_07_16_195953_drop_column_is_report_referral_data_to_users', 364),
(1311, '2024_07_18_221407_drop_columns_to_currency_fields', 364),
(1312, '2024_07_19_004528_add_column_desc_to_currency_fields', 364),
(1313, '2024_07_19_005802_drop_column_to_currency_fields', 364),
(1314, '2024_07_19_075106_add_column_sorting_to_requisites_info_fields', 364),
(1315, '2024_07_19_153520_drop_column_type_to_contacts', 364),
(1316, '2024_07_23_230101_drop_columns_to_currencies', 364),
(1317, '2024_07_23_231521_add_column_title_file_name_to_currencies', 364),
(1319, '2024_08_09_113706_add_column_parent_id_to_menu', 365),
(1320, '2024_08_10_003333_add_column_ext_params_to_currencies', 365),
(1321, '2024_08_10_091112_add_column_type_column_to_tasks_card_details', 365),
(1322, '2024_08_22_093015_add_column_rowspan_to_advantage', 365),
(1323, '2024_08_23_120404_change_method_to_info_statistics', 365),
(1324, '2024_08_23_122650_add_column_image_to_info_statistics', 365),
(1325, '2024_08_25_201746_add_column_icon_to_filter_currency', 365),
(1326, '2024_08_27_214650_create_currencies_groups_networks_table', 365),
(1327, '2024_08_27_220154_add_column_id_group_network_to_currencies', 365),
(1328, '2024_08_28_000459_add_column_field_key_to_tasks_fields', 365),
(1329, '2024_08_28_062925_drop_column_sender_to_tasks', 365),
(1330, '2024_08_28_114940_drop_column_tasks_info', 365),
(1331, '2024_08_30_083705_small_code_to_currencies', 365),
(1332, '2024_08_30_171207_create_histories_updated_data_table', 365),
(1333, '2024_08_31_140339_add_column_is_hidden_list_to_currencies_groups_networks', 365),
(1334, '2024_09_01_194552_add_column_example_to_currency_fields', 365),
(1335, '2024_09_02_174122_add_column_type_rate_to_tasks', 365),
(1336, '2024_09_02_174516_add_column_fees_types_to_direction_exchange', 365),
(1337, '2024_09_02_174846_add_column_is_type_rate_to_direction_exchange', 365),
(1338, '2024_09_02_181254_add_column_is_type_rate_to_tasks', 365),
(1339, '2024_09_02_205944_add_column_floating_fee_to_direction_exchange', 365),
(1340, '2024_09_02_225645_change_type_floating_fee_statuses_to_direction_exchange', 365),
(1341, '2024_09_02_232812_add_column_floating_stop_recount_pay_to_direction_exchange', 365),
(1342, '2024_09_02_233006_add_column_floating_rate_stop_to_tasks', 365),
(1343, '2024_09_02_233930_add_columns_fix_to_direction_exchange', 365),
(1344, '2024_09_02_234828_add_column_fix_recount_stop_to_tasks', 365),
(1345, '2024_09_02_235244_add_column_is_enable_fix_recount_to_direction_exchange', 365),
(1346, '2024_09_03_070958_add_column_display_fee_to_direction_exchange', 365),
(1347, '2024_09_03_074619_add_column_type_rate_description_to_direction_exchange', 365),
(1348, '2024_09_14_220456_add_column_to_payments', 365),
(1349, '2024_09_18_204029_add_column_status_to_links_footers', 365),
(1350, '2024_10_18_131502_add_column_course_value_to_history_recalculation', 365),
(1351, '2024_10_21_181731_add_column_id_who_completed_to_tasks', 365),
(1352, '2024_10_25_080525_create_dashboard_user_widgets_table', 365),
(1353, '2024_10_27_174339_create_notification_events_table', 365),
(1354, '2024_10_27_181105_add_column_id_value_to_notification_events', 365),
(1355, '2024_10_27_183134_change_type_notification_events', 365),
(1356, '2024_10_31_092346_add_column_is_read_to_tasks_messages', 365),
(1357, '2024_10_31_180950_add_index_to_tasks_messages', 365),
(1358, '2024_11_01_105152_add_column_text_order_created_email_to_direction_exchange', 365),
(1359, '2024_11_04_160128_add_column_started_at_to_promo_codes', 365),
(1360, '2024_11_04_190311_add_column_started_at_to_contests', 365),
(1361, '2024_12_07_192935_remove_unique_slug_to_menu', 365),
(1362, '2024_12_12_094043_add_column_is_check_payment_merchant_to_tasks', 365),
(1363, '2024_12_22_211148_add_column_id_country_to_cities', 365),
(1364, '2024_12_22_214715_drop_id_country_to_direction_exchange_cities', 365),
(1365, '2025_01_04_221836_drop_column_convert_to_rub_to_tasks_user', 365),
(1366, '2025_01_04_221938_drop_column_profit_rub_to_task_reports', 365),
(1367, '2025_01_04_222019_drop_column_profit_rub_to_tasks_profits', 365),
(1368, '2025_01_11_095118_add_column_is_error_update_to_parser_formula_rates', 365),
(1369, '2025_01_12_005602_change_type_oth_min_comm_to_direction_exchange', 365),
(1370, '2025_01_13_090946_create_export_rates_files_table', 365),
(1371, '2025_01_13_120212_add_column_ids_excluded_directions_to_export_rates-files', 365),
(1372, '2025_01_13_194017_add_column_is_view_to_export_rates_files', 365),
(1373, '2025_01_14_093016_create_direction_exchange_groups_table', 365),
(1374, '2025_01_14_093031_add_column_id_group_to_direction_exchange', 365),
(1375, '2025_01_14_102121_add_column_sorting_to_direction_exchange_groups', 365),
(1376, '2025_01_14_125358_change_type_auto_del_order_status_to_direction_exchange', 365),
(1377, '2025_01_14_163240_add_column_profit_and_instruction_to_direction_exchange_cities', 365),
(1378, '2025_01_14_173828_add_column_id_city_to_tasks_info', 365),
(1379, '2025_01_15_223637_add_column_image_preview_to_verification_card', 365),
(1380, '2025_01_15_232528_add_column_preview_to_user_verification', 365),
(1381, '2025_02_16_144745_add_column_interval_confirm_order_to_direction_exchange', 366),
(1382, '2025_03_04_121735_drop_columns_seo_to_language_contents', 366),
(1383, '2025_03_05_134455_drop_columns_to_users', 366),
(1384, '2025_03_07_083516_add_column_mimetype_to_tasks_check_images', 366),
(1385, '2025_03_16_195336_add_column_type_index_to_parser_formula_coefficient', 366),
(1386, '2025_03_17_103730_add_column_profit_partner_s_to_direction_exchange', 366),
(1387, '2025_03_17_193958_add_column_profit_partner_to_direction_exchange_cities', 366),
(1388, '2025_03_17_203702_add_column_profit_to_direction_exchange_cities', 366),
(1389, '2025_03_18_103524_add_column_exchange_to_bestchange_directions', 366),
(1390, '2025_03_20_093304_update_id_and_id_task_to_bigint', 366),
(1391, '2025_03_20_160617_add_column_type_requisite_to_tasks', 366),
(1392, '2025_03_23_084751_add_column_seo_description_to_language_contents', 366),
(1393, '2025_03_23_124415_drop_column_icon_to_order_steps', 366),
(1394, '2025_03_24_102651_add_column_is_already_used_to_requisities', 366),
(1395, '2025_03_25_103652_create_tasks_meta_table', 366),
(1396, '2025_03_25_114522_create_task_payment_status_logs_table', 366),
(1397, '2024_08_10_075226_create_card_info_settings', 367),
(1398, '2024_08_22_191226_advantage_settings', 367),
(1399, '2024_08_22_194135_advantage_settings', 367),
(1400, '2024_09_21_090616_banners', 367),
(1401, '2024_10_05_105045_create_bestchange_settings', 367),
(1402, '2024_10_13_182836_add_unpaid_item', 367),
(1403, '2024_11_21_231540_create_bestchange_blacklist_settings', 367),
(1404, '2025_03_17_081206_type_profit_to_direction_exchange', 367),
(1405, '2025_04_04_202334_add_column_is_popular_to_currencies', 367),
(1406, '2025_04_05_135642_add_column_page_headline_to_pages', 367),
(1407, '2025_04_07_130346_add_column_values_to_direction_exchange_cities', 367),
(1408, '2024_05_23_070532_create_pulse_tables', 368),
(1409, '2025_04_15_173036_add_column_is_show_to_links_reviews', 369),
(1410, '2025_04_28_082744_create_log_parser_sources_errors_table', 370),
(1411, '2025_05_02_143030_create_rates_history_logs_table', 371),
(1412, '2025_05_06_134111_drop_column_to_language_contents', 372),
(1413, '2025_05_08_151921_add_column_formula_value_to_bestchange_directions', 372),
(1414, '2025_05_12_101009_create_news_categories_table', 372),
(1415, '2025_05_12_110025_add_category_id_to_news', 372),
(1416, '2025_05_12_194536_add_colum_description_to_export_rates_files', 372),
(1417, '2025_05_21_080008_add_column_telegram_to_tasks_meta', 373),
(1418, '2025_05_28_154428_create_sumsub_ids_table', 374),
(1419, '2025_06_10_201835_create_proxies_table', 375),
(1420, '2025_06_20_093025_add_column_from_to_amount_to_direction_exchange_percent_amount', 376),
(1421, '2025_06_21_083058_add_column_validator_type_currency_fields', 376),
(1422, '2025_06_21_150417_add_column_code_to_bestchange_directions', 376),
(1423, '2025_06_21_185804_add_column_file_path_to_tasks_messages', 376),
(1424, '2025_06_23_091518_change_type_seo_title_to_direction_exchange', 376),
(1425, '2025_07_06_114947_add_and_change_is_valid_account_to_currencies', 377),
(1426, '2025_07_06_121229_change_column_mask_input_to_currencies', 377),
(1427, '2025_07_12_192429_add_column_is_unique_amount_from_to_direction_exchange', 378),
(1428, '2025_07_14_213522_add_column_is_hidden_order_pay_to_direction_exchange', 378),
(1429, '2025_07_15_215316_add_column_usage_count_to_user_wallet_stories', 378),
(1430, '2025_07_16_211728_add_colums_to_currencies', 378),
(1431, '2025_07_17_091518_drop_column_lead_time_to_tasks', 378),
(1432, '2025_07_18_111844_create_direction_exchange_selector_fee_table', 378),
(1433, '2025_07_18_122510_add_selected_fee_id_to_tasks_meta_table', 378),
(1434, '2025_07_18_164124_add_column_title_selector_to_direction_exchange', 378),
(1435, '2025_07_20_082720_make_referral_links_code_case_sensitive', 378),
(1436, '2025_07_20_105727_fix_news_timestamps', 378),
(1437, '2025_07_26_101850_create_checkbox_agreements_table', 379),
(1438, '2025_07_26_115851_create_checkbox_agreement_direction_exchange_table', 379),
(1439, '2025_07_26_135151_add_column_key_id_to_checkbox_agreements', 379),
(1440, '2025_07_26_150953_add_column_text_error_to_checkbox_agreements', 379),
(1441, '2025_07_26_180612_drop_column_is_kyc_checkbox_to_currencies', 379),
(1442, '2025_07_26_183010_add_checkbox_agreements_to_tasks_meta', 379),
(1443, '2025_07_26_223559_add_column_rate_value_without_step_to_bestchange_directions', 379),
(1444, '2025_08_01_100458_add_is_favorite_to_bestchange_directions_table', 380),
(1445, '2025_08_02_170357_add_column_types_to_direction_exchange', 381),
(1446, '2025_08_02_213316_add_verification_required_to_tasks_meta_table', 381),
(1447, '2025_08_03_094220_add_type_verification_to_tasks_meta_table', 381),
(1448, '2025_08_05_220318_create_selector_fees_table', 381),
(1449, '2025_08_05_220353_create_selector_fee_directions_table', 381),
(1450, '2025_08_05_224736_add_sorting_and_status_to_selector_fees_table', 381),
(1451, '2025_08_06_080455_add_column_selected_fee_type_to_tasks_meta', 381),
(1452, '2025_08_06_090320_change_selected_fee_id_foreign_in_tasks_meta', 381),
(1453, '2025_08_06_090459_add_direction_selected_fee_id_to_tasks_meta', 381),
(1454, '2025_08_06_135925_create_group_commission_direction_exchange_table', 381),
(1455, '2025_08_06_231223_add_status_to_direction_exchange_cities_table', 381),
(1456, '2025_08_08_121038_add_indexes_for_rates_sources', 381),
(1457, '2025_08_13_071004_create_currency_label_currency_table', 382),
(1458, '2025_08_13_094535_drop_id_label_from_currencies', 382),
(1459, '2025_08_14_201934_create_task_extra_outs_table', 383),
(1460, '2025_08_14_210340_change_type_amount_to_task_extra_outs', 383),
(1461, '2025_08_15_075641_extra_out_profiles', 383),
(1462, '2025_08_15_075834_extra_out_profile_currencies', 383),
(1463, '2025_08_15_180051_create_currency_filter_pivot', 383),
(1464, '2025_08_16_091107_add_description_to_selector_fees', 383),
(1465, '2025_08_16_095520_add_description_to_direction_exchange_selector_fee', 383),
(1466, '2025_08_21_171442_add_fee_type_to_selector_fees_table', 383),
(1467, '2025_09_02_180830_fix_mtd_unique_and_indexes', 383),
(1468, '2025_09_10_100455_add_unique_to_referral_logs', 384),
(1469, '2025_10_05_180431_add_network_code_to_currency_merchants_table', 385),
(1470, '2025_10_05_180431_add_network_code_to_currency_payments_table', 385),
(1471, '2025_10_05_213756_add_column_merchant_network_code_to_tasks_meta', 385),
(1472, '2025_10_22_140128_create_kyc_logs_table', 386),
(1473, '2025_10_22_140654_add_column_is_completed_sumsub_ids', 386),
(1474, '2025_10_23_083101_create_system_logs_table', 386),
(1475, '2025_10_23_084059_add_source_to_system_logs', 386),
(1476, '2025_10_23_084631_add_name_to_system_logs', 386),
(1477, '2025_10_23_094609_alter_system_logs_add_state_indexes', 386),
(1478, '2025_10_23_111625_alter_system_logs_add_episode_columns', 386),
(1479, '2025_10_23_115841_add_active_flag_to_system_logs', 386),
(1480, '2025_10_23_121812_alter_system_logs_active_unique_key', 386),
(1481, '2025_10_24_164139_add_file_rate_source_to_direction_exchange_table', 386),
(1482, '2025_10_24_224043_create_direction_exchange_group_pivot', 386),
(1483, '2025_10_25_214144_add_indexes_selector_fees', 386),
(1484, '2025_10_25_214211_add_indexes_direction_exchange_selector_fee', 386),
(1485, '2025_10_26_203903_add_column_user_agent_to_tasks_meta', 386),
(1486, '2025_10_29_225245_add_column_user_flag_to_tasks_meta', 387),
(1487, '2025_10_30_093608_add_columns_to_job_schedules', 387),
(1488, '2025_10_30_112521_add_column_to_job_schedules', 387),
(1489, '2025_10_30_184503_add_selected_fees_to_tasks_meta', 387),
(1490, '2025_10_31_102028_drop_column_selected_fees_common', 387),
(1491, '2025_11_06_092819_create_visit_counters_hour', 388),
(1492, '2025_11_06_092846_create_user_request_daily_table', 388),
(1493, '2025_11_16_181120_create_checkbox_agreement_direction_allowed_table', 388),
(1494, '2025_11_16_183222_add_column_apply_mode_to_checkbox_agreements', 388),
(1495, '2025_11_17_092942_add_instruction_source_mode_to_directions_and_currencies', 388),
(1496, '2025_11_17_181313_add_desc_source_mode_to_directions_and_currencies', 388),
(1497, '2025_11_17_232843_tag_processor_custom_tags', 388),
(1498, '2025_11_17_233212_tag_processor_entity_custom_tags', 388),
(1499, '2025_11_17_234256_add_label_and_description_to_tag_processor_entity_custom_tags', 388),
(1500, '2025_11_18_210235_direction_city_profiles', 388),
(1501, '2025_11_18_210252_direction_exchange_city_profile_pivot', 388),
(1502, '2025_11_19_001328_add_comm_direction_city_profiles', 388),
(1503, '2025_11_19_134430_add_column_method_request_payment_to_direction_exchange', 388),
(1504, '2025_11_19_175532_add_column_view_expires_at_to_tasks', 388),
(1505, '2025_11_19_201733_create_settings_limit_profiles_table', 388),
(1506, '2025_11_19_220621_add_speed_and_first_orders_limits_to_settings_limit_profiles', 388),
(1507, '2025_11_19_234043_add_mask_placeholder_chars_to_currencies_table', 388),
(1508, '2025_11_20_001519_drop_column_id_manager_to_tasks', 388),
(1509, '2025_11_20_182210_create_order_exports_table', 388),
(1510, '2025_11_20_222251_order_profit_results', 388),
(1511, '2025_11_21_082021_daily_profit_stats', 388),
(1512, '2025_11_21_203142_add_completed_at_to_tasks_table', 388),
(1513, '2025_11_21_235039_reserve_total_snapshots', 388),
(1514, '2025_11_22_093242_create_order_exchange_stats_daily_table', 388),
(1515, '2025_11_22_114746_add_device_type_to_tasks_meta_table', 388),
(1516, '2025_11_22_141847_direction_exchange_stats_daily', 388),
(1517, '2025_11_22_165255_rename_tasks_convert_log', 388),
(1518, '2025_11_22_170703_rename_fk_on_order_exchange_totals', 388),
(1519, '2025_11_22_203448_update_currencies_analytics_table', 388),
(1520, '2025_11_22_210337_currencies_analytics_daily', 388),
(1521, '2025_11_22_223638_update_referral_statistics_table', 388),
(1522, '2025_11_23_114810_referral_log', 388),
(1523, '2025_11_24_092337_change_type_order_exchange_totals', 388),
(1524, '2025_11_24_095014_tasks_user', 388),
(1525, '2025_11_24_095640_2025_11_24_100000_update_users_money_and_dates', 388),
(1526, '2025_11_24_100124_2025_11_24_120000_drop_task_reports_table', 388),
(1527, '2025_11_24_100314_drop_admin_filters_user_table', 388),
(1528, '2025_11_24_100500_drop_course_logs_table', 388),
(1529, '2025_11_24_105656_drop_id_user_from_permissions', 388),
(1530, '2025_11_24_105719_drop_reserve_group_table', 388),
(1531, '2025_11_24_115841_create_user_global_stats_daily_table', 388),
(1532, '2025_11_24_210532_task_info', 388),
(1533, '2025_11_24_230206_is_hidden_list', 388),
(1534, '2025_11_24_230412_display_type', 388),
(1535, '2025_11_25_103740_add_response_data_to_api_logs_table', 388),
(1536, '2025_11_25_120040_add_token_id_to_api_logs_table', 388),
(1537, '2025_11_25_131918_add_value_to_permissions_group', 388),
(1538, '2025_11_25_194917_add_identity_verification_fields_to_currencies_table', 388),
(1539, '2025_11_25_223021_rename_type_verification_to_card_verification_type', 388),
(1540, '2025_11_25_223405_add_card_verification_rules_to_direction_exchange', 388),
(1541, '2025_11_25_231915_add_direction_verification_json_fields_to_direction_exchange', 388),
(1542, '2025_11_26_070420_update_tasks_meta_for_card_verification', 388),
(1543, '2025_11_26_085827_add_identity_verification_fields_to_direction_exchange', 388),
(1544, '2025_11_26_113812_drop_identity_verification_columns_from_currencies', 388),
(1545, '2025_11_26_114032_add_identity_verification_rules_to_currencies', 388),
(1546, '2025_11_26_120851_add_identity_verification_columns_to_tasks_meta', 388),
(1547, '2025_11_26_130637_add_is_from_identity_verification_to_tasks', 388),
(1548, '2025_11_26_143054_profit_profiles', 388),
(1549, '2025_11_26_143238_add_column_to_direction_exchanges', 388),
(1550, '2025_12_07_110419_currency_bin_bank_rules', 389),
(1551, '2025_12_08_005414_2025_12_08_000000_drop_old_settings_tables', 389),
(1552, '2025_12_08_010452_create_dynamic_config_settings_table', 389),
(1553, '2025_12_08_081007_сreate_dynamic_config_migrations_table', 389),
(1554, '2025_12_08_094620_add_expires_at_to_dynamic_config_settings', 389),
(1555, '2025_12_08_101848_dynamic_config_versions', 389),
(1556, '2025_12_08_102057_dynamic_config_snapshots', 389),
(1557, '2025_12_08_103902_dynamic_config_locks', 389),
(1558, '2025_12_08_103925_deleted_at', 389),
(1559, '2025_12_08_114659_migrate_language_contents', 389),
(1560, '2025_12_08_143615_migrate_banners_project_settings_to_dynamic_config', 389),
(1561, '2025_12_08_175329_migrate_advantage_project_settings_to_dynamic_config', 389),
(1562, '2025_12_08_200457_migrate_project_settings_card_info_to_bin_inspector', 389),
(1563, '2025_12_08_201057_migrate_project_settings_bestchange_blacklist_to_dynamic_config', 389),
(1564, '2025_12_08_211221_migrate_project_settings_bestchange_to_dynamic_config', 389),
(1565, '2025_12_14_180657_payment_gateway_logs', 389),
(1566, '2025_12_16_115508_create_gateway_replays', 389),
(1567, '2025_12_16_115537_create_gateway_secret_access_logs', 389),
(1568, '2025_12_16_125337_payment_gateway_logs', 389),
(1569, '2025_12_16_164050_gateway_health_statuses', 389),
(1570, '2025_12_18_113000_delete_many_tables', 389),
(1571, '2025_12_19_182143_create_online_sessions_table', 389),
(1572, '2025_12_19_182223_create_online_hourly_stats_table', 389),
(1573, '2025_12_19_182247_create_online_daily_stats_table', 389),
(1574, '2025_12_20_211212_create_merchant_flow_events_table', 389),
(1575, '2025_12_21_182410_create_referral_audit_logs', 389),
(1576, '2025_12_21_190234_referral_log_hold_reversal', 389),
(1577, '2025_12_21_190507_user_balance_add_hold_balance', 389),
(1578, '2025_12_21_212050_referral_log_increase_precision', 389),
(1579, '2025_12_23_221912_order_recount_policies', 389),
(1580, '2025_12_23_221930_order_recount_states', 389),
(1581, '2025_12_23_221943_order_recount_audit', 389),
(1582, '2025_12_24_084813_alter_order_recount_states_split_state', 389),
(1583, '2025_12_24_092744_order_recount_states', 389),
(1584, '2025_12_24_154057_create_order_recount_daily_stats_table', 389),
(1585, '2025_12_24_154229_create_order_recount_aggregate_state_table', 389);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1586, '2025_12_25_180154_drop_stop_recount_columns_from_direction_exchange_table', 389),
(1587, '2025_12_25_194623_parser_exchange', 389),
(1588, '2025_12_25_203703_referral_programs', 389),
(1589, '2025_12_25_211729_drop_type_and_count_from_referral_programs_table', 389),
(1590, '2025_12_26_121756_add_rate_mode_top_n_to_bestchange_directions', 389),
(1591, '2025_12_26_174759_add_explain_payload_to_bestchange_directions', 389),
(1592, '2025_12_26_181859_create_bestchange_exchanger_stats', 389),
(1593, '2025_12_26_185204_create_bestchange_exchanger_cooldowns', 389),
(1594, '2025_12_26_185621_create_bestchange_market_reports', 389),
(1595, '2025_12_28_210928_add_geo_data_to_tasks_meta_table', 389),
(1596, '2025_12_28_210943_drop_geo_columns_from_tasks_info_table', 389),
(1597, '2025_12_29_123734_upgrade_tasks_status_log_table', 389),
(1598, '2025_12_29_125425_id_direction_exchange', 389),
(1599, '2025_12_29_221317_add_freeze_scam_to_tasks_meta', 389),
(1600, '2025_12_30_222551_create_proxy_health_logs_table', 389),
(1601, '2025_12_31_095514_add_auto_disabled_until_to_proxies', 389),
(1602, '2025_12_31_100525_alter_proxies_password_to_text', 389),
(1603, '2026_01_01_144829_create_auth_audit_events_table', 389),
(1604, '2026_01_01_201023_add_session_fields_to_auth_audit_events', 389),
(1605, '2026_01_01_213000_drop_legacy_auth_tables', 389),
(1606, '2026_01_01_231432_create_referral_settings_code_audits_table', 389),
(1607, '2026_01_02_145535_task_log_confirmation', 389),
(1608, '2026_01_02_153339_log_merchants', 389),
(1609, '2026_01_03_121324_create_page_groups_table', 389),
(1610, '2026_01_03_121400_add_group_fields_to_pages_table', 389),
(1611, '2026_01_03_204729_rules_pages', 389),
(1612, '2026_01_04_000528_upgrade_promo_codes_and_scopes', 389),
(1613, '2026_01_04_103125_add_promo_snapshot_fields_to_tasks', 389),
(1614, '2026_01_04_103151_drop_legacy_promo_discount_from_tasks', 389),
(1615, '2026_01_04_113801_drop_promo_code_discount_from_tasks_table', 389),
(1616, '2026_01_04_230907_upgrade_tasks_requisites_attached', 389),
(1617, '2026_01_05_093744_add_status_to_faq_category_table', 389),
(1618, '2026_01_05_164430_add_status_to_contacts_groups_table', 389),
(1619, '2026_01_05_165549_add_status_to_contacts_table', 389),
(1620, '2026_01_05_211521_banned', 389);

-- --------------------------------------------------------

--
-- Структура таблицы `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` int(10) UNSIGNED NOT NULL,
  `model_id` int(10) UNSIGNED NOT NULL,
  `model_type` varchar(191) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `model_id` int(10) UNSIGNED NOT NULL,
  `model_type` varchar(191) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_id`, `model_type`) VALUES
(1, 1, 'App\\Models\\User'),
(2, 1, 'App\\Models\\User');

-- --------------------------------------------------------

--
-- Структура таблицы `monitors`
--

CREATE TABLE `monitors` (
  `id` int(10) UNSIGNED NOT NULL,
  `url` varchar(191) NOT NULL,
  `uptime_check_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `look_for_string` varchar(191) NOT NULL DEFAULT '',
  `uptime_check_interval_in_minutes` varchar(191) NOT NULL DEFAULT '5',
  `uptime_status` varchar(191) NOT NULL DEFAULT 'not yet checked',
  `uptime_check_failure_reason` varchar(191) NOT NULL DEFAULT '',
  `uptime_check_times_failed_in_a_row` int(11) NOT NULL DEFAULT 0,
  `uptime_status_last_change_date` timestamp NULL DEFAULT NULL,
  `uptime_last_check_date` timestamp NULL DEFAULT NULL,
  `uptime_check_failed_event_fired_on_date` timestamp NULL DEFAULT NULL,
  `uptime_check_method` varchar(191) NOT NULL DEFAULT 'get',
  `certificate_check_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `certificate_status` varchar(191) NOT NULL DEFAULT 'not yet checked',
  `certificate_expiration_date` timestamp NULL DEFAULT NULL,
  `certificate_issuer` varchar(191) DEFAULT NULL,
  `certificate_check_failure_reason` varchar(191) NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `name` text NOT NULL,
  `cr_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` longtext NOT NULL,
  `image` varchar(191) DEFAULT NULL,
  `parent_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `views` int(11) NOT NULL DEFAULT 0,
  `slug_name` varchar(191) DEFAULT NULL,
  `is_local_image` int(11) NOT NULL DEFAULT 0,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `news_categories`
--

CREATE TABLE `news_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `color` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `notices_exchange`
--

CREATE TABLE `notices_exchange` (
  `id` int(10) UNSIGNED NOT NULL,
  `text` longtext NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `color` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `link` varchar(191) DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `is_enabled_schedule` int(11) NOT NULL DEFAULT 0,
  `from_time` varchar(191) DEFAULT NULL,
  `to_time` varchar(191) DEFAULT NULL,
  `is_blank` int(11) NOT NULL DEFAULT 0,
  `icon_notice` varchar(191) DEFAULT NULL,
  `text_color` varchar(191) DEFAULT NULL,
  `bg_color` varchar(191) DEFAULT NULL,
  `text_size` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(191) NOT NULL,
  `notifiable_id` int(10) UNSIGNED NOT NULL,
  `notifiable_type` varchar(191) NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `notification_events`
--

CREATE TABLE `notification_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `is_read` int(11) NOT NULL DEFAULT 0,
  `type_event` int(11) NOT NULL DEFAULT 0,
  `title` varchar(191) DEFAULT NULL,
  `message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`message`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_value` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `oauth_access_tokens`
--

CREATE TABLE `oauth_access_tokens` (
  `id` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `scopes` text DEFAULT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `oauth_auth_codes`
--

CREATE TABLE `oauth_auth_codes` (
  `id` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `scopes` text DEFAULT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `oauth_clients`
--

CREATE TABLE `oauth_clients` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `website` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `secret` varchar(100) NOT NULL,
  `redirect` text NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `provider` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `oauth_personal_access_clients`
--

CREATE TABLE `oauth_personal_access_clients` (
  `id` int(10) UNSIGNED NOT NULL,
  `client_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `oauth_refresh_tokens`
--

CREATE TABLE `oauth_refresh_tokens` (
  `id` varchar(100) NOT NULL,
  `access_token_id` varchar(100) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `online_daily_stats`
--

CREATE TABLE `online_daily_stats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `day` date NOT NULL,
  `auth_unique` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `guest_unique` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `auth_hits` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `guest_hits` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `peak_concurrent` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `online_hourly_stats`
--

CREATE TABLE `online_hourly_stats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `hour` datetime NOT NULL,
  `auth_hits` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `guest_hits` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `concurrent_last` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `concurrent_max` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `online_sessions`
--

CREATE TABLE `online_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(10) NOT NULL,
  `identity` varchar(80) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `gid` varchar(64) DEFAULT NULL,
  `user_name` varchar(191) DEFAULT NULL,
  `user_email` varchar(191) DEFAULT NULL,
  `first_seen` timestamp NOT NULL,
  `last_seen` timestamp NOT NULL,
  `hits` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ip` varchar(45) DEFAULT NULL,
  `ua_hash` blob DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `online_sessions`
--

INSERT INTO `online_sessions` (`id`, `type`, `identity`, `user_id`, `gid`, `user_name`, `user_email`, `first_seen`, `last_seen`, `hits`, `ip`, `ua_hash`, `created_at`, `updated_at`) VALUES
(1, 'user', 'user:1', 1, NULL, 'admin', 'user@iexexchanger.com', '2026-01-18 13:28:44', '2026-01-18 13:28:44', 1, NULL, 0x684fac3d8e595845640e507a9122bd55, '2026-01-18 13:28:44', '2026-01-18 13:28:44');

-- --------------------------------------------------------

--
-- Структура таблицы `operation_levels`
--

CREATE TABLE `operation_levels` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_level_group` int(11) NOT NULL DEFAULT 0,
  `id_operator` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `operation_level_groups`
--

CREATE TABLE `operation_level_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `from_limit` varchar(191) DEFAULT NULL,
  `to_limit` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_exchange_stats_daily`
--

CREATE TABLE `order_exchange_stats_daily` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `total_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `completed_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `rejected_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `processing_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_exchange_totals`
--

CREATE TABLE `order_exchange_totals` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `exchange_usd` decimal(24,8) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_exports`
--

CREATE TABLE `order_exports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `format` varchar(10) NOT NULL DEFAULT 'xlsx',
  `file_name` varchar(191) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `rows_count` int(10) UNSIGNED DEFAULT NULL,
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `error_message` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_profit_results`
--

CREATE TABLE `order_profit_results` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `profit_amount` decimal(30,18) NOT NULL DEFAULT 0.000000000000000000,
  `profit_currency_code` varchar(16) NOT NULL,
  `profit_amount_usd` decimal(30,18) NOT NULL DEFAULT 0.000000000000000000,
  `base_currency_code` varchar(16) NOT NULL DEFAULT 'USD',
  `profit_percent_effective` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `rates_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rates_snapshot`)),
  `breakdown_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`breakdown_json`)),
  `calculated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_recount_aggregate_state`
--

CREATE TABLE `order_recount_aggregate_state` (
  `key` varchar(64) NOT NULL,
  `last_audit_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `order_recount_aggregate_state`
--

INSERT INTO `order_recount_aggregate_state` (`key`, `last_audit_id`, `updated_at`) VALUES
('daily', 0, '2026-01-18 12:21:04');

-- --------------------------------------------------------

--
-- Структура таблицы `order_recount_audit`
--

CREATE TABLE `order_recount_audit` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `policy_id` bigint(20) UNSIGNED DEFAULT NULL,
  `decision` varchar(16) NOT NULL,
  `reason_code` varchar(64) NOT NULL DEFAULT 'ok',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `old_rate` varchar(64) DEFAULT NULL,
  `new_rate` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_recount_daily_stats`
--

CREATE TABLE `order_recount_daily_stats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `day` date NOT NULL,
  `trigger` varchar(32) NOT NULL,
  `decision` varchar(16) NOT NULL,
  `reason_code` varchar(64) NOT NULL DEFAULT 'ok',
  `scope_type` varchar(16) NOT NULL DEFAULT 'global',
  `scope_id` bigint(20) UNSIGNED DEFAULT NULL,
  `events_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `perf_ms_sum` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `calc_ms_sum` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `recount_ms_sum` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `perf_ms_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `calc_ms_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `recount_ms_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_recount_policies`
--

CREATE TABLE `order_recount_policies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `priority` int(11) NOT NULL DEFAULT 100,
  `scope_type` varchar(32) NOT NULL DEFAULT 'global',
  `scope_id` bigint(20) UNSIGNED DEFAULT NULL,
  `trigger_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`trigger_types`)),
  `conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`conditions`)),
  `actions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`actions`)),
  `stop_further` tinyint(1) NOT NULL DEFAULT 0,
  `title` varchar(190) NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_recount_states`
--

CREATE TABLE `order_recount_states` (
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `last_recalculated_at_global` timestamp NULL DEFAULT NULL,
  `last_rate_value_global` varchar(64) DEFAULT NULL,
  `status_last_global` int(10) UNSIGNED DEFAULT NULL,
  `status_entered_at_global` timestamp NULL DEFAULT NULL,
  `recount_in_status_count_global` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_recalculated_at_floating` timestamp NULL DEFAULT NULL,
  `last_rate_value_floating` varchar(64) DEFAULT NULL,
  `status_last_floating` int(10) UNSIGNED DEFAULT NULL,
  `status_entered_at_floating` timestamp NULL DEFAULT NULL,
  `recount_in_status_count_floating` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_checked_at` timestamp NULL DEFAULT NULL,
  `fail_streak` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_steps`
--

CREATE TABLE `order_steps` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text DEFAULT NULL,
  `id_manager` int(11) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `pages`
--

CREATE TABLE `pages` (
  `page_id` int(10) UNSIGNED NOT NULL,
  `group_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `page_title` text NOT NULL,
  `page_content` longtext DEFAULT NULL,
  `page_slug` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) NOT NULL DEFAULT 0,
  `page_headline` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `pages`
--

INSERT INTO `pages` (`page_id`, `group_id`, `sort_order`, `is_active`, `page_title`, `page_content`, `page_slug`, `created_at`, `updated_at`, `deleted_at`, `user_id`, `page_headline`) VALUES
(1, NULL, 0, 1, '{\"ru\":\"Правила обмена\"}', '{\"ru\":\"<p>Текст правила....</p>\"}', 'rules', '2017-10-24 20:54:45', '2026-01-18 12:51:06', NULL, 1, '{\"ru\":null}'),
(2, NULL, 0, 1, '{\"ru\":\"AML\"}', '{\"ru\":\"<p>AML</p>\"}', 'aml', '2026-01-18 12:40:39', '2026-01-18 12:40:39', NULL, 1, '{\"ru\":\"aml\"}'),
(3, NULL, 0, 1, '{\"ru\":\"Политика\"}', '{\"ru\":\"<p>...</p>\"}', 'policy-cookie', '2026-01-18 12:41:00', '2026-01-18 12:51:00', NULL, 1, '{\"ru\":\"политика\"}');

-- --------------------------------------------------------

--
-- Структура таблицы `pages_static`
--

CREATE TABLE `pages_static` (
  `id` int(11) NOT NULL,
  `title_about` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_about` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `about_visible` int(11) NOT NULL DEFAULT 0,
  `text_contact` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_faq` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_faq` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_exchange_rules` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `pages_static`
--

INSERT INTO `pages_static` (`id`, `title_about`, `text_about`, `about_visible`, `text_contact`, `title_faq`, `text_faq`, `text_exchange_rules`, `created_at`, `updated_at`) VALUES
(1, 'Добро пожаловать', '<p>Компания &mdash; сервис обмена электронных валют, созданный специально для профессиональных игроков &mdash; трейдеров бирж крипто-валют. Данный сервис предназначен для тех, кто предпочитает не ограничиваться только заработками от торговли на одной конкретной бирже, получая дополнительную прибыль от межбиржевого арбитража.</p>\n\n<p>Нас не интересует как Вы зарабатываете ту или иную валюту. Наша основная задача &mdash; оперативно и без лишних хлопот предоставить Вам выгодный обмен.&nbsp;</p>\n\n<p>&nbsp; &nbsp; &nbsp; &nbsp; &laquo;Сайт&raquo; делает основную ставку на анонимность операций, а еще наши менеджеры крайне вежливы и приятны в общении, убедитесь в этом сами!</p>\n\n<p>&nbsp; &nbsp; &nbsp; &nbsp; Все данные и операции проходят по защищенному протоколу &laquo;Comodo Secure&raquo;, что обеспечивает Вам гарантию, что они не достанутся третьим лицам. Мы тщательно следим за качеством сервиса, поэтому настоятельно просим оставлять отзывы после каждого обмена.</p>\n\n<p>&nbsp; &nbsp; &nbsp; &nbsp; Также, обращаем ваше внимание, что все обмены осуществляются строго через контактные телефоны или форму общения с оператором у нас на сайте. Не используйте мессенджеры без подтверждения через форму, дабы избежать действий злоумышленников.&nbsp;</p>\n\n<p>&nbsp;</p>\n\n<p><b>Работаем круглосуточно</b><br />\nНаши операторы обрабатывают заявки в ручном режиме.<br />\nВремя обработки заявок от 5 до 20 минут.</p>\n\n<p><b>У нас Вы можете обменять:</b>&nbsp;QIWI, Яндекс. Деньги, Perfect Money, Payeer, Bitcoin, BTC-e USD, BTC-e RUR, Сбербанк, Альфа Банк, Тинькофф Банк, ВТБ24 Банк.</p>\n\n<p>&nbsp;</p>', 0, '<h2>Техническая поддержка</h2>\r\n\r\n<p>Если у Вас возникли вопросы технического или финансового плана, напишите нам и мы поможем Вам в решении вашего вопроса. Мы отвечаем на вопросы в течение 15-60 минут, в зависимости от загрузки сервиса.</p>\r\n\r\n<p>Время работы: <strong>Круглосуточно</strong></p>', '', '<h3>1. Как быстро происходит обмен?</h3>\r\n\r\n<ul>\r\n	<li>Обмен между Bitcoin, Ethereum, Litecoin, Monero, Dash, Advanced Cash, WEX, Bitcoin.co.id, EXMO, Perfect Money и Яндекс Деньгами происходит в автоматическом режиме, то есть мгновенно. Однако случаются ситуации, когда Perfect Money или OkPay могут заморозить вашу операцию. В этом случае для проведения заявки потребуется несколько часов.</li>\r\n	<li>Переводы с Яндекс Денег на счета, на которые вы ранее не переводили средств через наш сервис, удерживаются в течение 48 часов и проходят автоматически по истечении данного срока. Последующие переводы на этот счет проходят мгновенно.</li>\r\n</ul>\r\n\r\n<h3>2. Могу ли я отказаться от обмена?</h3>\r\n\r\n<p>В том случае, если операция обмена не завершена &mdash; можете. Вам будут возвращены ваши деньги за вычетом комиссии платежной системы, в которой производилась оплата, то есть возврат будет произведен за ваш счет.</p>\r\n\r\n<h3>3. Что делать, если я оформил и оплатил заявку, а деньги все еще не пришли?</h3>\r\n\r\n<ol>\r\n	<li>Ознакомьтесь с ответом на вопрос #1 текущего раздела.</li>\r\n	<li>Внимательно прочитайте письмо, присланное вам на e-mail сразу после оформления заявки.</li>\r\n	<li>Воспользовавшись любым удобным для вас способом, сообщите оператору номер своей заявки, и он в кратчайшие сроки проверит ее.</li>\r\n</ol>\r\n\r\n<h3>4. Что делать, если я оплатил заявку, но её статус остался &laquo;ожидает оплаты клиентом&raquo;.</h3>\r\n\r\n<p>Данная ситуация возникает в том случае, когда платежная система передает информацию о платеже с задержкой. Вам необходимо подождать около 5 минут, после чего обновить страницу. Если изменений не произошло, свяжитесь со службой поддержки.</p>', '<p><strong>1. Общие положения</strong></p>\r\n\r\n<p>Настоящее соглашение (далее по тексту Соглашение) описывает правила и условия, на основании которых предоставляются услуги обменного сервиса OwlPay и является официальной письменной публичной офертой адресованной физическим и юридическим лицам (далее по тексту - Пользователь), заключить Соглашение о предоставлении услуг сервисом OwlPay на изложенных ниже условиях.<br />\r\nПеред тем как воспользоваться услугами сервиса OwlPay , Пользователь обязан ознакомиться в полном объеме с условиями &laquo;Соглашения о предоставлении услуг сервисом OwlPay&raquo;.<br />\r\nИспользование услуг сервиса OwlPay возможно только при условии, что Пользователь принимает все условия Соглашения.<br />\r\nДействующая версия Соглашения расположена для публичного доступа на интернет-сайте сервиса OwlPay.</p>\r\n\r\n<p><strong>2. Термины и определения, используемые в Соглашении</strong></p>\r\n\r\n<p><em>Сервис&nbsp;</em>OwlPay&nbsp;<em>(Сервис)</em>&nbsp;&ndash; система предоставления интернет-услуг по обмену, продаже и покупке электронных валют.<br />\r\n<em>Интернет-сайт Сервиса</em>&nbsp;&ndash; www.owlpay.cc<br />\r\n<em>Пользователь</em>&nbsp;&ndash;&nbsp; любое физическое или юридическое лицо, использующее услуги Сервиса OwlPay и осуществившее акцепт Соглашения в соответствии с его условиями.</p>\r\n\r\n<p><em>Партнер</em>&nbsp;&ndash; лицо, оказывающее Сервису услуги по привлечению Пользователей, условия оказания которых описаны в настоящем Соглашении.<br />\r\n<em>Платежная система</em>&nbsp;&ndash;&nbsp; программный продукт, созданный третьей стороной, представляющий собой механизм реализации учета денежных (электронные деньги) и/или иных обязательств, оплату товаров и услуг в сети Интернет, а также организацию взаиморасчетов между пользователями.</p>\r\n\r\n<p>Основными платежными системами в рамках настоящего Соглашения являются:<br />\r\nPerfectMoney, e-Voucher, Bitcoin, AdvCash, OkPay, Payeer, QIWI, Yandex.Money, BTC-E, Exmo и другие. Точный список Платежных систем указан на Интернет-сайте Сервиса. &nbsp;</p>\r\n\r\n<p><em>Клиент платежной системы</em>&nbsp;&ndash; лицо, заключившее соглашение с соответствующей платежной системой на приобретение имущественных прав требования к ней, измеряемых в условных единицах, принятых в соответствующей платежной системе.<br />\r\n<em>Электронная валюта</em>&nbsp;&ndash; денежное и/или иное обязательство между разработчиком данной валюты и ее пользователем, выраженное цифровым способом.</p>\r\n\r\n<p><em>Платеж/Операция</em>&nbsp;&ndash;&nbsp; перевод электронной и/или иной валюты от плательщика к получателю.<br />\r\n<em>Заявка</em>&nbsp;&ndash; выражение намерения Пользователя воспользоваться одной из услуг, предлагаемых Сервисом OwlPay , путем заполнения электронной формы через Интернет-сайт Сервиса, на условиях, описанных в Соглашении и указанных в параметрах этой Заявки.<br />\r\n<em>Верификация карты</em>&nbsp;- это проверка принадлежности карты (или счета) её владельцу. Условия проверки принадлежности устанавливает Сервис, производиться единовременно для каждого нового счета (карты) клиента.<br />\r\n<em>Исходная валюта</em>&nbsp;&ndash; электронная валюта, которую Пользователь желает продать или обменять.<br />\r\n<em>Исходный счет</em>&nbsp;&ndash; номер кошелька или любое другое обозначения счета Пользователя в Платежной системе, с которого была отправлена Исходная валюта.</p>\r\n\r\n<p><em>Полученная валюта</em>&nbsp;&ndash; электронная валюта, которую Пользователь получает в результате продажи или обмена Исходной валюты.<br />\r\n<em>Счет получателя</em>&nbsp;&ndash; номер кошелька или любое другое обозначения счета Пользователя в Платежной системе, на который будет отправлена Полученная валюта.</p>\r\n\r\n<p><em>Резерв валюты</em>&nbsp;&ndash; имеющийся в распоряжении Сервиса OwlPay , на момент создания Заявки, объем определенной Электронной валюты.</p>\r\n\r\n<p><em>Обмен валюты</em>&nbsp;&ndash;&nbsp; обмен электронной валюты одной платежной системы на электронную валюту другой платежной системы.</p>\r\n\r\n<p><em>Курс</em>&nbsp;&mdash; стоимостное соотношение двух электронных валют при их обмене.</p>\r\n\r\n<p><em>Резервы электронных валют</em>&nbsp;&ndash; суммы имеющихся в наличии у Сервиса Электронных валют или денежных средств для совершения услуг. Суммы резервов указаны на Интернет-сайте Сервиса на главной странице.</p>\r\n\r\n<p><strong>3. Предмет Соглашения и порядок вступления его в силу</strong></p>\r\n\r\n<ul>\r\n	<li>3.1. Предметом настоящего Соглашения является предоставление Пользователю Сервисом OwlPay следующих услуг:</li>\r\n	<li>3.1.1. обмен электронной валюты;</li>\r\n	<li>3.1.2. продажа Пользователю электронной валюты;</li>\r\n	<li>3.1.3. покупка у Пользователя электронной валюты.</li>\r\n	<li>3.2. Акцепт (принятие условий) настоящего Соглашения (Оферты) производится Пользователем в случае:</li>\r\n	<li>3.2.1. Направления Заявки на совершение одной из услуг, предлагаемых Сервисом;</li>\r\n	<li>3.2.2. Регистрации на Интернет-сайте Сервиса.</li>\r\n	<li>3.3. При направлении Заявки акцептом публичной оферты признается совершение Пользователем действий по завершению формирования Заявки, подтверждающих его намерение совершить сделку с Сервисом на условиях, предложенных Сервисом. Дата и время акцепта, а также параметры условий заявки фиксируются Сервисом автоматически в момент завершения формирования заявки. Настоящее Соглашение вступает в силу в момент поступления от Пользователя на реквизиты Сервиса электронной валюты или денежных средств в сумме, предусмотренной параметрами Заявки.</li>\r\n	<li>3.4. В случае регистрации на Интернет-сайте Сервиса настоящее Соглашение вступает в действие в момент проставления галочки перед словами &laquo;Я согласен с условиями оферты&raquo; и нажатия кнопки &laquo;Зарегистрироваться&raquo;.</li>\r\n	<li>3.5. Срок действия договора устанавливается бессрочно до момента расторжения договора по инициативе одной из сторон на условиях указанных ниже.</li>\r\n</ul>\r\n\r\n<p><strong>4. Услуги и порядок их оказания</strong></p>\r\n\r\n<ul>\r\n	<li>4.1. Общий&nbsp;порядок оказания услуг Сервисом</li>\r\n	<li>4.1.1. Заказ услуг Сервисом OwlPay осуществляется Пользователем путем направления Заявки через Интернет-сайт Сервиса.</li>\r\n	<li>4.1.2. Управление процессом обмена или получение информации о ходе выполнения услуги Пользователем производятся при помощи соответствующего пользовательского интерфейса, расположенного на Интернет-сайте Сервиса.</li>\r\n	<li>4.1.3. Сервис OwlPay осуществляет исполнение Заявок на безотзывной основе в соответствии с условиями работы соответствующих Платежных систем. Особые условия работы с некоторыми Платежными системами указаны ниже.</li>\r\n	<li>4.1.4. Сервис OwlPay не несет ответственности за действия Платежной системы перед ее Клиентом. Права и обязанности платежной системы и ее Клиента регулируются условиями предоставления услуг соответствующих Платежных систем.</li>\r\n	<li>4.1.5. Воспользовавшись услугами Сервиса OwlPay , Пользователь подтверждает, что законно владеет и распоряжается денежными средствами и электронной валютой, участвующими в соответствующем Платеже.</li>\r\n	<li>4.1.6. Пользователь обязуется самостоятельно исчислять и уплачивать все налоги, требуемые в соответствии с налоговым законодательством места нахождения Пользователя.</li>\r\n	<li>4.2. Услуга по Обмену Электронной валюты</li>\r\n	<li>4.2.1. Путем оформления Заявки Пользователь поручает, а Сервис OwlPay от своего имени и за счет Пользователя, совершает действия по обмену Электронной валюты одной Платежной системы (Исходная валюта) на Электронную валюту другой Платежной системы (Полученная валюта) выбранной Пользователем.</li>\r\n	<li>4.2.2. Пользователь обязуется перечислить (передать) Исходную валюту, в размере указанном в Заявке, а Сервис OwlPay , после получения соответствующей Электронной валюты, обязуется перечислить (передать) Пользователю Полученную валюту, рассчитанную по Курсу и в соответствии с тарифами Сервиса.</li>\r\n	<li>4.2.3. Размер вознаграждения Сервиса OwlPay отражается в Заявке и подтверждается Пользователем на одной из страниц пользовательского интерфейса при оформлении Заявки.</li>\r\n	<li>4.2.4. Обязанность Сервиса OwlPay по перечислению (передаче) Электронной валюты Пользователю считается исполненной в момент списания Электронной валюты в соответствующей Платежной системе со счета Сервиса OwlPay , что регистрируется в истории операций соответствующей Платежной системы.</li>\r\n	<li>4.3. Услуга по продаже Пользователю электронной валюты</li>\r\n	<li>4.3.1. Путем оформления Заявки Пользователь поручает, а Сервис OwlPay от своего имени и за счет Пользователя, совершает действия по приобретению и передаче Электронной валюты Пользователю.</li>\r\n	<li>4.3.2. Размер вознаграждения Сервиса OwlPay за указанные действия отражается в Заявке и подтверждается Пользователем на одной из страниц пользовательского интерфейса.</li>\r\n	<li>4.3.3. В течение 24 часов с момента получения денежных средств от Пользователя, в размере указанном в соответствующей Заявке, Сервис OwlPay обязан перечислить (передать) Полученную Электронную валюту на реквизиты и в размере, указанном Пользователем в Заявке.</li>\r\n	<li>4.3.4. Сервис OwlPay вправе отменить созданную Пользователем заявку на покупку Электронной валюты, если оплата по такой заявке не поступила на расчетный счет сервиса по истечению 24 часов с момента создания такой заявки.</li>\r\n	<li>4.3.5. Обязанность Сервиса OwlPay по перечислению (передаче) Полученной валюты Пользователю считается исполненной в момент списания Электронной валюты в соответствующей Платежной системе со счета Сервиса OwlPay , что регистрируется в истории операций соответствующей Платежной системы.</li>\r\n	<li>4.4. Услуга по покупке у Пользователя электронной валюты</li>\r\n	<li>4.4.1. Путем оформления Заявки Пользователь поручает, а Сервис OwlPay от своего имени и за счет Пользователя, покупает электронную валюту у Пользователя, а также совершает действия по передаче денежного эквивалента Пользователю в сумме, указанной в Заявке.</li>\r\n	<li>4.4.2. В течение 24 (Двадцати четырех) часов с момента получения Исходной валюты от Пользователя, в размере указанном в соответствующей Заявке, Сервис OwlPay обязан передать Пользователю денежный эквивалент перечисленной Электронной валюты, способом выбранным Пользователем при подаче Заявки.</li>\r\n	<li>4.4.3. Размер вознаграждения Сервиса OwlPay за указанные действия отражается в Заявке и подтверждается Пользователем на одной из страниц пользовательского интерфейса.</li>\r\n	<li>4.3.4. Обязанность Сервиса OwlPay по перечислению денежного эквивалента переданной Электронной валюты считается исполненной в момент списания соответствующей суммы со счета Сервиса OwlPay .</li>\r\n</ul>\r\n\r\n<p><strong>5. Дополнительные условия оказания услуг</strong></p>\r\n\r\n<ul>\r\n	<li>5.1. В случае непоступления от Пользователя к Сервису OwlPay Электронной валюты или денежных средств в течение 24 часов с момента совершения Заявки, Сервис имеет право аннулировать такую Заявку при ручной обработке поступивших платежей, и в течение 2 часов при автоматической обработке. Электронная валюта или денежные средства, поступившие после указанного выше срока, подлежат возврату на реквизиты плательщика. При осуществлении возврата, все комиссионные расходы на перевод производятся из поступивших средств за счет Пользователя.</li>\r\n	<li>5.2. В случае поступления от Пользователя к Сервису OwlPay Электронной валюты или денежных средств в сумме, отличающейся от указанной в Заявке, Сервис имеет право рассматривать это как распоряжение Пользователя сделать перерасчет по заявке согласно фактически поступившей сумме Электронной валюты. В случае, если количество поступившей Электронной валюты или денежных средств отличается от заявленного Пользователем более чем на 10%, Сервис может в одностороннем порядке аннулировать Заявку и возвратить поступившие средства на реквизиты плательщика. При осуществлении возврата, все комиссионные расходы на перевод средств производятся из поступивших средств за счет Пользователя.</li>\r\n	<li>5.3. В случае не исполнения Сервисом OwlPay условия отправки Электронной валюты или денежных средств по Заявке на реквизиты, указанные Пользователем, в течение 24 часов с момента отправки Заявки, Пользователь имеет права требовать возврата Электронной валюты или денежных средств в полном объеме, кроме случаев, указанных в настоящем Соглашении. Требование о возврате Электронной валюты или денежных средств может быть исполнено Сервисом только в том случае, если на момент получения такого требования денежный эквивалент не был отправлен на реквизиты, указанные Пользователем. Увеличение срока перечисления Электронной валюты или денежных средств может быть вызвано условиями обработки заявок отдельных Платежных систем, в указанном случае Сервис ответственности не несет и возврат не осуществляется.</li>\r\n	<li>5.4. Курс Электронной валюты фиксируется Системой не более, чем на 18 минут с момента совершения Заявки. В случае, если Пользователь произвел оплату по прошествии 18 минут, Система автоматически обновляет курс и блокирует возможность создания заявки. В случае блокировки создания заявки необходимо создать новую заявку по актуальному курсу, пропустив пункт с оплатой, а также обратиться в техническую поддержку Сервиса OwlPay .<br />\r\n	В случае с Банковскими платежами, курс обновляется всегда.</li>\r\n	<li>5.5. Особые условия некоторых Платежных систем:<br />\r\n	- Банковские платежи обрабатываются Сервисом в течение 24 часов, при необходимости Сервис может потребовать Верификацию карты(счета) клиента;<br />\r\n	- PerfectMoney может задерживать переводы более, чем на 24 часа;<br />\r\n	- Если Заявка на Bitcoin завершена, возврат денежных средств невозможен;<br />\r\n	- Если присланная сумма Bitcoin меньше 0,0010 ВТС, деньги назад не возвращаются<br />\r\n	- BTC-e: Если полученная сумма не вписывается в лимиты/резервы Сервиса, новый купон будет создан за вычетом минимальной комиссии Сервиса;<br />\r\n	- YandexMoney: На первый платеж от Незарегистрированного/Неверифицированного Пользователя установлена задержка на 48 часов.<br />\r\n	- Банковские переводы в направлении Visa/Mastercard RUB в большинстве случаев зачисляются мгновенно, но в некоторых случаях могут занимать до 5 банковских дней.</li>\r\n</ul>\r\n\r\n<p><strong>6. Стоимость услуг</strong></p>\r\n\r\n<ul>\r\n	<li>6.1.&nbsp; Cтоимость услуг Сервиса OwlPay устанавливается руководством Сервиса и публикуется на Интернет-сайте Сервиса.</li>\r\n	<li>6.2. Сервис OwlPay вправе самостоятельно изменять курсы обмена Электронных валют и взимаемых комиссионных в любое время в одностороннем порядке, о чем уведомляет Пользователей Сервиса предварительным размещением информации об этих изменениях на Интернет-сайте Сервиса.</li>\r\n	<li>6.3. В Заявке, создаваемой Пользователем на Интернет-сайте Сервиса, указывается Курс, размер комиссии, взимаемый соответствующей Платежной системой, за проведение Операции, размер вознаграждения Сервиса, способ Обмена, а также общая сумма перечисляемых денежных средств или электронной валюты.</li>\r\n	<li>6.4. Сервис OwlPay взимает стоимость своего вознаграждения в момент проведения соответствующей Операции.</li>\r\n	<li>6.5. Сервис предлагает следующую систему вознаграждений (только для зарегистрированных Пользователей). Узнайте статус своей скидки, и повысьте процент вознаграждения при обращении в техническую поддержку! Подробнее об условиях получения скидки на обмен:<br />\r\n	5% - за обмен от 0 до 1000 $<br />\r\n	10% - от 1000 до 10 000 $<br />\r\n	15% - от 10 000 и выше</li>\r\n</ul>\r\n\r\n<p>Процент вознаграждения рассчитывается от прибыли Сервиса по каждому доступному направлению обмена, по некоторым направлениям обмена комиссия сервиса может быть 0%, может быть меньше 0% (то есть комиссия отсутствует) или Сервис не получает выгоду от обмена, значит Сервис с этого обмена не имеет прибыли, в этих ситуациях вознаграждение не начисляется.</p>\r\n\r\n<p>&nbsp;</p>', '2023-04-09 11:12:02', '2017-10-10 17:51:13');

-- --------------------------------------------------------

--
-- Структура таблицы `page_groups`
--

CREATE TABLE `page_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(191) NOT NULL,
  `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`title`)),
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`description`)),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `parser_api_keys`
--

CREATE TABLE `parser_api_keys` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_type` int(11) NOT NULL DEFAULT 0,
  `api_key` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `provider_id` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `parser_exchange`
--

CREATE TABLE `parser_exchange` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `id_group` int(10) UNSIGNED DEFAULT NULL,
  `value` int(11) NOT NULL DEFAULT 0,
  `summa` varchar(250) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `status` int(11) NOT NULL DEFAULT 0,
  `id_type_price` int(11) NOT NULL DEFAULT 0,
  `number_format` int(11) NOT NULL DEFAULT 0,
  `type` int(11) NOT NULL DEFAULT 0,
  `value_default` int(11) NOT NULL DEFAULT 1,
  `summa_default` varchar(250) NOT NULL,
  `code_in` varchar(191) DEFAULT NULL,
  `code_out` varchar(191) DEFAULT NULL,
  `is_not_update` int(11) NOT NULL DEFAULT 0,
  `code` varchar(191) DEFAULT NULL,
  `type_price` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `parser_formula_coefficient`
--

CREATE TABLE `parser_formula_coefficient` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `summa` double(8,2) NOT NULL DEFAULT 0.00,
  `name` varchar(191) DEFAULT NULL,
  `alias` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type_index` int(11) NOT NULL DEFAULT 0,
  `template` text DEFAULT NULL,
  `comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `parser_formula_rates`
--

CREATE TABLE `parser_formula_rates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text DEFAULT NULL,
  `exchange_in` varchar(191) DEFAULT NULL,
  `exchange_out` varchar(191) DEFAULT NULL,
  `formula` varchar(191) DEFAULT NULL,
  `value` int(11) NOT NULL DEFAULT 0,
  `summa` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `number_format` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_coefficient` int(11) NOT NULL DEFAULT 0,
  `coefficient_formula` varchar(191) DEFAULT NULL,
  `coefficient_name` varchar(191) DEFAULT NULL,
  `title` varchar(191) DEFAULT NULL,
  `is_error_update` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `parser_type`
--

CREATE TABLE `parser_type` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `value` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `parser_type`
--

INSERT INTO `parser_type` (`id`, `name`, `value`, `created_at`, `updated_at`) VALUES
(1, 'Максимальная цена сделки за 24 часа', 'high', '2017-10-24 21:00:00', '2017-10-24 21:00:00'),
(2, 'Минимальная цена сделки за 24 часа', 'low', '2017-10-24 21:00:00', '2017-10-24 21:00:00'),
(3, 'Средняя цена сделки за 24 часа', 'avg', '2017-10-24 21:00:00', '2017-10-24 21:00:00'),
(4, 'Объем всех сделок за 24 часа', 'vol', '2017-10-24 21:00:00', '2017-10-24 21:00:00'),
(5, 'Сумма всех сделок за 24 часа', 'vol_curr', '2017-10-24 21:00:00', '2017-10-24 21:00:00'),
(6, 'Цена последней сделки', 'last_trade', '2017-10-24 21:00:00', '2017-10-24 21:00:00'),
(7, 'Текущая максимальная цена покупки', 'buy_price', '2017-10-24 21:00:00', '2017-10-24 21:00:00'),
(8, 'Текущая минимальная цена продажи', 'sell_price', '2017-10-24 21:00:00', '2017-10-24 21:00:00');

-- --------------------------------------------------------

--
-- Структура таблицы `partners`
--

CREATE TABLE `partners` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` text DEFAULT NULL,
  `link` text DEFAULT NULL,
  `logo` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `partner_parser_groups`
--

CREATE TABLE `partner_parser_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `link` varchar(191) DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `partner_parser_rates`
--

CREATE TABLE `partner_parser_rates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `exchange_in` varchar(191) DEFAULT NULL,
  `exchange_out` varchar(191) DEFAULT NULL,
  `id_group` int(11) NOT NULL DEFAULT 0,
  `rate` double NOT NULL DEFAULT 0,
  `partner_type` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `partner_id` int(11) NOT NULL DEFAULT 0,
  `group_name` varchar(191) DEFAULT NULL,
  `number_format` int(11) NOT NULL DEFAULT 0,
  `last_updated_at` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `password_resets`
--

CREATE TABLE `password_resets` (
  `email` varchar(191) NOT NULL,
  `token` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(191) NOT NULL,
  `token` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `logo` varchar(191) DEFAULT NULL,
  `enable_svg` int(11) NOT NULL DEFAULT 0,
  `svg_name` varchar(191) DEFAULT NULL,
  `svg_class` varchar(191) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `is_delete` int(11) NOT NULL DEFAULT 0,
  `blockchain_url` varchar(191) DEFAULT NULL,
  `is_import` int(11) NOT NULL DEFAULT 0,
  `logo_svg` varchar(191) DEFAULT NULL,
  `is_local_image` int(11) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `payment_explorer`
--

CREATE TABLE `payment_explorer` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_payment` int(11) NOT NULL DEFAULT 0,
  `link` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `text` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `payment_gateway_logs`
--

CREATE TABLE `payment_gateway_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED DEFAULT NULL,
  `merchant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `gateway_alias` varchar(64) NOT NULL,
  `direction` varchar(16) NOT NULL,
  `operation` varchar(32) NOT NULL,
  `transaction_id` varchar(128) DEFAULT NULL,
  `external_id` varchar(128) DEFAULT NULL,
  `http_method` varchar(10) DEFAULT NULL,
  `url` varchar(512) DEFAULT NULL,
  `response_status` smallint(5) UNSIGNED DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `duration_ms` int(10) UNSIGNED DEFAULT NULL,
  `attempt` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `idempotency_key` varchar(128) DEFAULT NULL,
  `request_headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_headers`)),
  `request_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_body`)),
  `response_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_body`)),
  `error_class` varchar(191) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_sandbox` tinyint(1) NOT NULL DEFAULT 0,
  `replay_key` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `payout_address`
--

CREATE TABLE `payout_address` (
  `id` int(10) UNSIGNED NOT NULL,
  `address` varchar(191) DEFAULT NULL,
  `payment` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `pays_transaction_data`
--

CREATE TABLE `pays_transaction_data` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) NOT NULL DEFAULT 0,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `id_pay` int(11) NOT NULL DEFAULT 0,
  `service_name` varchar(191) DEFAULT NULL,
  `id_from_pay` varchar(191) DEFAULT NULL,
  `ext_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ext_data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_direction_exchange` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `pay_transaction_hash`
--

CREATE TABLE `pay_transaction_hash` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `api_transfer_id` varchar(191) NOT NULL,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `transaction_hash` varchar(191) DEFAULT NULL,
  `provider` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_waiting_hash` int(11) NOT NULL DEFAULT 0,
  `id_pay` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `pending_order_status`
--

CREATE TABLE `pending_order_status` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_not_delete` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `pending_order_status`
--

INSERT INTO `pending_order_status` (`id`, `name`, `created_at`, `updated_at`, `is_not_delete`) VALUES
(1, '{\"ru\":\"Без причины\"}', NULL, '2024-07-29 06:24:24', 0),
(2, '{\"ru\":\"Средства временно заблокированы, AML Анализ не пройден\"}', NULL, '2024-07-29 06:24:24', 0),
(3, '{\"ru\":\"Вы указали не верный адрес биткоин кошелька. Пожалуйста пришлите верный адрес биткоин кошелька на почту.\"}', NULL, '2024-07-29 06:24:24', 0),
(4, '{\"ru\":\"Вы указали не верные реквизиты получателя. Пожалуйста проверьте введённые данные и пришлите верные реквизиты на почту\"}', NULL, '2024-07-29 06:24:24', 0),
(5, '{\"ru\":\"Данные найдены в списке должников\"}', '2019-10-11 18:36:21', '2024-07-29 06:24:24', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_permissions_group` int(11) NOT NULL DEFAULT 0,
  `name` varchar(191) NOT NULL,
  `guard_name` varchar(191) NOT NULL,
  `title` varchar(191) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `permissions`
--

INSERT INTO `permissions` (`id`, `id_permissions_group`, `name`, `guard_name`, `title`, `text`, `created_at`, `updated_at`) VALUES
(1, 2, 'currencies', 'web', 'Разрешить управление валютами в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать и редактировать валюты в админпанели.', '2017-10-03 07:07:51', '2019-03-11 08:40:29'),
(2, 2, 'currency_codes', 'web', 'Разрешить управление кодами валют в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать и редактировать коды валют в админпанели.	\r\n', '2017-10-03 07:08:04', '2017-12-25 06:42:04'),
(3, 2, 'currency_filters', 'web', 'Разрешить управление фильтрами валют в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать и редактировать фильтры валют в админпанели.	\r\n', '2017-10-03 07:08:09', '2017-12-25 06:42:21'),
(5, 2, 'payment_system', 'web', 'Разрешить управление платежной системой\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать и управлять платежными системами в админпанели.	\r\n', '2017-10-03 07:08:23', '2017-12-25 06:42:54'),
(6, 2, 'direction_exchange', 'web', 'Разрешить доступ к направлениям в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять направлениями обмена в админпанели.	\r\n', '2017-10-03 07:08:31', '2017-12-25 06:43:11'),
(7, 2, 'additional_fields', 'web', 'Разрешить управление дополнительными полями в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять дополнительными полями в админпанели.	\r\n', '2017-10-03 07:08:37', '2017-12-25 06:43:27'),
(8, 2, 'partners', 'web', 'Разрешить управление партнерами в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять партнерами в админпанели.	\r\n', '2017-11-14 00:05:39', '2017-12-25 06:43:38'),
(9, 2, 'user_discounts', 'web', 'Разрешить управление скидками пользователей в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять скидками пользователей в админпанели.	\r\n', '2017-11-22 09:19:12', '2017-12-25 06:43:56'),
(10, 2, 'reserves', 'web', 'Разрешить управление резервами в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять резервами в админпанели.	\r\n', '2017-11-22 09:38:57', '2017-12-25 06:44:07'),
(11, 2, 'claims_payment', 'web', 'Разрешить управление заявками на выплату в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять заявками на выплату в админпанели.	\r\n', '2017-12-25 06:40:15', '2017-12-25 06:44:26'),
(12, 3, 'allow_admin', 'web', 'Разрешить доступ в админпанель\r\n', 'Данная опция не предоставляет полного доступа ко всем разделам админпанели, а только разрешает добавление или редактирование новостей в админпанели.	\r\n', '2017-12-25 06:41:29', '2017-12-25 06:41:29'),
(13, 3, 'admin_settings', 'web', 'Разрешить управление настройками в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять настройками в админпанели.	\r\n', '2017-12-25 06:45:13', '2017-12-25 06:45:13'),
(14, 3, 'admin_users', 'web', 'Разрешить управление пользователями в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять пользователями в админпанели.	\r\n', '2017-12-25 06:45:22', '2017-12-25 06:45:22'),
(15, 3, 'admin_tasks', 'web', 'Разрешить управление заявками в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, принимать, и управлять заявками в админпанели.	\r\n', '2017-12-25 06:45:53', '2017-12-25 06:45:53'),
(16, 3, 'admin_news', 'web', 'Разрешить управление новостями в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, редактировать, создавать новости в админпанели.	\r\n', '2017-12-25 06:46:01', '2017-12-25 06:46:01'),
(17, 3, 'admin_pages', 'web', 'Разрешить управление страницами в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать, редактировать и удалять статические страницы в админпанели.	\r\n', '2017-12-25 06:46:12', '2017-12-25 06:46:12'),
(18, 3, 'admin_parser', 'web', 'Разрешить управление парсером в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять парсерами в админпанели.	\r\n', '2017-12-25 06:46:22', '2017-12-25 06:46:22'),
(19, 3, 'admin_bonuses_program', 'web', 'Разрешить управление партнерской программой в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать и редактировать партнерскую программу в админпанели.	\r\n', '2017-12-25 06:46:43', '2024-07-29 06:24:27'),
(20, 3, 'admin_status_job', 'web', 'Разрешить управление статусом работы в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять статусом работы сервисама в админпанели.	\r\n', '2017-12-25 06:46:55', '2017-12-25 06:46:55'),
(21, 3, 'admin_faq', 'web', 'Разрешить управление FAQ в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать, редактировать вопросы и ответы в админпанели.	\r\n', '2017-12-25 06:47:04', '2017-12-25 06:47:04'),
(23, 3, 'admin_export_courses', 'web', 'Разрешить управление экспортом курсов в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять экспортом курсов в админпанели.	\r\n', '2017-12-25 06:47:34', '2017-12-25 06:47:34'),
(24, 2, 'admin_backup', 'web', 'Разрешить управление резервными копиями базы данных в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять резервными копиями в админпанели.', '2017-12-25 06:47:56', '2019-03-11 08:40:05'),
(25, 3, 'admin_statistics', 'web', 'Разрешить доступ к статистике в админпанели\r\n\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, получить полный доступ к статистике в админпанели.', '2017-12-28 17:53:11', '2017-12-28 17:53:11'),
(26, 3, 'admin_merchant', 'web', 'Разрешить доступ к мерчанту', 'Данная опция позволит пользователям, имеющим доступ в админпанель, получить полный контроль над платежными системами (история транзакций, баланс)', '2018-03-04 17:48:08', '2018-03-04 17:48:08'),
(28, 2, 'requisites_system', 'web', 'Разрешить управление реквизитами в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать и управлять резвизитами в админпанели.	\r\n', '2018-06-20 19:49:49', '2018-06-20 19:49:49'),
(30, 3, 'user_wallets', 'web', 'Разрешить доступ к счетам пользователей', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять счетами пользователей', '2019-02-08 03:22:45', '2019-02-08 03:22:45'),
(31, 3, 'admin_blockip', 'web', 'Разрешить управление фильтрами блокировки по IP или E-Mail в админпанели\r\n', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать, редактировать и удалять фильтры блокировки по IP или E-Mail.	', '2019-02-20 03:25:38', '2019-02-20 03:25:38'),
(32, 3, 'admin_cache', 'web', 'Разрешить очистку кэша', 'Данная опция позволит пользователям, имеющим доступ в админпанель, очищать кэш сайта', '2019-02-20 03:26:51', '2019-02-20 03:26:51'),
(33, 3, 'order_blacklist', 'web', 'Разрешить управление черным список в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять черными списками.', '2019-03-14 08:16:35', '2019-03-14 08:17:22'),
(34, 3, 'admin_roles', 'web', 'Разрешить управление группами прав в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять группами прав пользователей в админпанели.', '2019-04-04 14:16:54', '2019-04-04 17:09:22'),
(36, 3, 'admin_order_trashed', 'web', 'Разрешить управление удаленными заявками в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, принимать, и управлять удаленными заявками в админпанели.', '2019-05-14 13:24:57', '2019-05-14 13:24:57'),
(37, 3, 'admin_limit_editing_directions', 'web', 'Ограничить редактирование направлений', 'Данная опция позволит главным админам ограничивать доступ к редактированию некоторых направлений', '2019-05-24 03:44:48', '2019-05-24 03:44:48'),
(39, 3, 'admin_reviews', 'web', 'Разрешить управление отзывами', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять отзывами', '2019-10-20 05:01:57', '2019-10-20 05:01:57'),
(40, 2, 'admin_verification_card', 'web', 'Разрешить управление верификациями в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять верификациями в админпанели.', '2019-10-20 05:03:55', '2019-10-20 05:03:55'),
(41, 3, 'admin_selected_course', 'web', 'Разрешить управление избранными курсами', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять избранными курсами', '2019-10-20 13:10:43', '2019-10-20 13:10:43'),
(42, 3, 'admin_social_auth', 'web', 'Разрешить доступ к Системе авторизаций', 'Данная опция позволит пользователям, имеющим доступ в админпанель, получить полный контроль над ключами системы авторизаций', '2019-10-21 09:21:52', '2019-10-21 09:21:52'),
(43, 3, 'admin_contact', 'web', 'Разрешить управление контактами', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять контактами в админпанели.', '2019-10-21 19:38:06', '2019-10-21 19:38:06'),
(44, 3, 'admin_other_permissions', 'web', 'Разрешить управление дополнительными модулям', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять всеми дополнительными возможностями в админпанели.', '2019-11-11 20:07:30', '2019-11-11 20:07:30'),
(45, 3, 'admin_update_system', 'web', 'Разрешить управление системой обновления', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управления системой обновлений в админпанели.', '2023-04-09 10:47:38', '2023-04-09 10:47:38'),
(46, 3, 'admin_order_limits', 'web', 'Разрешить управление лимитами для операторов в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать и управлять лимитами обменов для операторов в админпанели.', '2023-04-09 10:47:38', '2023-04-09 10:47:38'),
(49, 3, 'admin_access_global_control', 'web', 'Разрешить контроль над всеми функциями в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать общие группы, фильтры и т.д', '2023-04-09 10:47:38', '2023-04-09 10:47:38'),
(50, 3, 'admin_api', 'web', 'Разрешить доступ к API в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, конторолировать функции API', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(51, 3, 'admin_unlimited_order_creation', 'web', 'Разрешить снятие лимитов для заявок', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создать заявки обходя установленные ограничения', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(52, 3, 'admin_laravel_links', 'web', 'Разрешить доступ к мониторингу данных', 'Данная опция позволит пользователям, имеющим доступ в админпанель, получить возможность просматривать мониторинг важных данных.', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(53, 5, 'admin_handler_any_order', 'web', 'Разрешить выполнять заявки любых менеджеров', 'Данная опция позволит пользователям, имеющим доступ в админпанель, получить возможность выполнять любые заявки других менеджеров которые уже приняли.', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(54, 3, 'admin_account_change_password', 'web', 'Разрешить сбрасывать пароли пользователям', 'Данная опция позволит пользователям, имеющим доступ в админпанель, получить возможность устанавливать новые пароли для пользователей.', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(55, 3, 'admin_access_google_authentication', 'web', 'Разрешить управление Google Authentication', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять ключами безопасности Google Authentication.', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(56, 3, 'admin_account_logs', 'web', 'Разрешить доступ к логам авторизаций', 'Данная опция позволит пользователям, имеющим доступ в админпанель, просматривать и очищать логи авторизаций.', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(57, 3, 'admin_requisites_log', 'web', 'Разрешить управление логами реквизитов', 'Данная опция позволит пользователям, имеющим доступ в админпанель, просматривать и очищать логи платежных реквизитов.', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(58, 4, 'admin_widgets', 'web', 'Разрешить управление виджетами для рабочего стола', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять виджетами и рабочим столом.', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(59, 4, 'admin_other_favorites', 'web', 'Разрешить управление избранными страницами', 'Данная опция позволит пользователям, имеющим доступ в админпанель, могут управлять избранными страницами.', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(60, 3, 'admin_verification_account', 'web', 'Разрешить управление верификациями личности в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять верификациями личности в админпанели.', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(61, 4, 'admin_orders_causes_limit', 'web', 'Разрешить настройку причин и лимитов для заявок', 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать или редактирование причин и лимитов для заявок', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(62, 4, 'admin_orders_control_status', 'web', 'Разрешить управление статусами для заявок', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять статусами для заявок', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(63, 4, 'admin_orders_logs', 'web', 'Разрешить управление логами заявок', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить просматривать и очищать логи заявок', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(64, 3, 'admin_parser_bestchange', 'web', 'Разрешить управление BestChange парсером в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять BestChange парсером в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(65, 3, 'admin_parser_competitors', 'web', 'Разрешить управление парсером конкурентов в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять парсером конкурентов в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(66, 3, 'admin_parser_formula', 'web', 'Разрешить управление формулами курсов в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять формулами курсов в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(67, 3, 'admin_parser_file', 'web', 'Разрешить управление файлами курсов в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять файлами курсов в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(68, 2, 'admin_plugin_promo_code', 'web', 'Разрешить управление промо-кодами в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять промо-кодами в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(69, 2, 'admin_plugin_contests', 'web', 'Разрешить управление конкурсами в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять конкурсами в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(70, 2, 'admin_plugin_card_info', 'web', 'Разрешить управление информациями о картах в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, настроить плагин которая собирает информацию о картах в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(71, 2, 'admin_plugin_cities', 'web', 'Разрешить управление городами в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять городами в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(72, 2, 'admin_plugin_proxy', 'web', 'Разрешить управление proxy менеджером в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять proxy менеджером в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(73, 2, 'admin_plugin_aml', 'web', 'Разрешить управление AML-сервисами в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять AML-сервисами в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(74, 4, 'admin_export_data', 'web', 'Разрешить управление экспортными данными в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять экспортными данными в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(75, 4, 'admin_other_blockchain_explorer', 'web', 'Разрешить управление blockchain explorer в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять blockchain explorer в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(76, 3, 'admin_banners', 'web', 'Разрешить управление баннерами в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять баннерами в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(77, 3, 'admin_geoip', 'web', 'Разрешить управление GEOIP в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять GEOIP в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(78, 2, 'admin_notification', 'web', 'Разрешить управление уведомлениями в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять уведомлениями в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(79, 3, 'admin_advantage', 'web', 'Разрешить управление преимуществами в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять преимуществами в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(80, 3, 'admin_links_reviews', 'web', 'Разрешить управление ссылками на отзывы в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять ссылками на отзывы в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(81, 3, 'admin_links_footer', 'web', 'Разрешить управление ссылками для footer в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять ссылками для footer в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(82, 2, 'admin_menu', 'web', 'Разрешить управление навигационным меню в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять навигационным меню в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(83, 3, 'admin_statistics_tools', 'web', 'Разрешить управление статистикой для главной страницы в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять статистикой для главной страницы в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(84, 5, 'admin_other_online_chat', 'web', 'Разрешить управление онлайн чатом для заявок в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, управление онлайн чатом для заявок админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(85, 3, 'admin_autopayment', 'web', 'Разрешить доступ к выплатам', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить доступ к автовыплатам в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(86, 3, 'admin_merchant_api_logs', 'web', 'Разрешить доступ к логам мерчантов и api', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить доступ к логам мерчантов и api в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(87, 3, 'admin_order_archive_orders', 'web', 'Разрешить доступ к настройке архивации заявок', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить доступ к настройке архивации заявок в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(88, 5, 'admin_orders_restore', 'web', 'Разрешить восстанавливать заявки', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить восстанавливать заявки', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(89, 5, 'admin_orders_reject', 'web', 'Разрешить отклонять заявки', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить отклонять заявки', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(90, 5, 'admin_orders_client_black_list', 'web', 'Разрешить вносить клиентов в черный список', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить в заявках вносить клиентов в черный список', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(91, 5, 'admin_orders_attach_photo', 'web', 'Разрешить прикреплять фото', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить прикреплять фотографии к заявкам', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(92, 5, 'admin_orders_control_comment', 'web', 'Разрешить управление комментариями', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешать управление комментариями в заявке', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(93, 5, 'admin_orders_id_editor', 'web', 'Разрешить редактировать заявку', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешать редактирование заявки', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(94, 5, 'admin_orders_id_recount', 'web', 'Разрешить пересчитать заявку', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешать пересчитывать заявку', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(95, 5, 'admin_orders_change_operator', 'web', 'Разрешить передавать заявки между менеджерами', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешать передать заявку другим менеджерам', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(96, 5, 'admin_orders_execute', 'web', 'Разрешить обработку заявки', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешать обрабатывать заявку', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(97, 5, 'admin_orders_auto_successful', 'web', 'Разрешить вывести кнопку автовыплаты', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешать отображение кнопки автовыплаты заявки', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(98, 4, 'admin_session_logs', 'web', 'Разрешить доступ к сессиям пользователей в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, получить доступ к сессиям пользователей в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(99, 4, 'admin_session_destroy', 'web', 'Разрешить удалять сессии пользователей в админпанели', 'Данная опция позволит пользователям, имеющим доступ в админпанель, удалять сессии пользователей в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(100, 3, 'admin_telegram_notification', 'web', 'Разрешить управление Telegram уведомлениями', 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить полный контроль над telegram уведомлениями в админпанели', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(101, 6, 'admin_analytics_exchanges', 'web', 'Доступ к аналитике обменов', 'Позволяет просматривать и использовать разделы аналитики по обменным операциям в админпанели.', '2026-01-18 12:21:04', '2026-01-18 12:21:04'),
(102, 6, 'admin_analytics_partners', 'web', 'Доступ к аналитике партнёрской программы', 'Позволяет просматривать отчёты и статистику по партнёрской программе и реферальным начислениям.', '2026-01-18 12:21:04', '2026-01-18 12:21:04'),
(103, 6, 'admin_analytics_users', 'web', 'Доступ к аналитике пользователей', 'Позволяет просматривать расширенную статистику по пользователям, их активности и заявкам.', '2026-01-18 12:21:04', '2026-01-18 12:21:04');

-- --------------------------------------------------------

--
-- Структура таблицы `permissions_group`
--

CREATE TABLE `permissions_group` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `permissions_group`
--

INSERT INTO `permissions_group` (`id`, `name`, `created_at`, `updated_at`) VALUES
(2, 'Базовое управление', '2017-12-24 21:00:00', '2017-12-24 21:00:00'),
(3, 'Админпанель', '2017-12-24 21:00:00', '2017-12-24 21:00:00'),
(4, 'Дополнение', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(5, 'Заявки', '2024-07-29 06:24:27', '2024-07-29 06:24:27'),
(6, 'Аналитика', '2026-01-18 12:21:03', '2026-01-18 12:21:03');

-- --------------------------------------------------------

--
-- Структура таблицы `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(191) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `token_code` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `profit_profiles`
--

CREATE TABLE `profit_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `profit` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `profit_s` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `scope` varchar(191) NOT NULL DEFAULT 'direction',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `project_settings`
--

CREATE TABLE `project_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `group` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `project_settings`
--

INSERT INTO `project_settings` (`id`, `group`, `name`, `locked`, `payload`, `created_at`, `updated_at`) VALUES
(1, 'security', 'is_google_auth', 0, '0', '2024-07-29 05:30:34', '2024-07-29 05:30:34'),
(2, 'security', 'proxiesfilter_sources_ddos', 0, 'null', '2024-07-29 05:30:34', '2024-07-29 05:30:34'),
(3, 'security', 'env_session_secure_cookie', 0, '0', '2024-07-29 05:30:34', '2024-07-29 05:30:34'),
(4, 'security', 'env_session_driver', 0, '\"file\"', '2024-07-29 05:30:34', '2024-07-29 05:30:34'),
(5, 'security', 'session_lifetime', 0, '120', '2024-07-29 05:30:34', '2024-07-29 05:30:34'),
(6, 'security', 'ip_change_control', 0, '0', '2024-07-29 05:30:34', '2024-07-29 05:30:34'),
(7, 'security', 'admin_allowed_ip', 0, 'null', '2024-07-29 05:30:34', '2024-07-29 05:30:34'),
(8, 'security', 'app_one_session_user', 0, '0', '2024-07-29 05:30:34', '2024-07-29 05:30:34'),
(9, 'card_info', 'driver', 0, 'null', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(10, 'card_info', 'is_api', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(11, 'card_info', 'save_data', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(12, 'card_info', 'api_key', 0, 'null', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(13, 'card_info', 'ids_currencies', 0, 'null', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(14, 'advantage', 'is_advantage_style', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(15, 'advantage', 'advantage_col', 0, '\"3\"', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(16, 'advantage', 'advantage_row_height', 0, '\"2:1\"', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(17, 'advantage', 'advantage_gutter_size', 0, '\"10px\"', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(18, 'banners', 'is_autoplay', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(19, 'banners', 'timeout', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(20, 'banners', 'hide_nav', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(21, 'bestchange', 'is_enable', 0, 'false', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(22, 'bestchange', 'position', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(23, 'bestchange', 'currencies', 0, '[]', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(24, 'bestchange', 'is_log_error', 0, 'false', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(25, 'bestchange', 'timeout', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(26, 'bestchange', 'cities', 0, '[]', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(27, 'bestchange', 'site_version', 0, '\"\"', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(28, 'bestchange', 'type_position', 0, '\"\"', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(29, 'bestchange', 'blacklist', 0, '[]', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(30, 'bestchange', 'whitelist', 0, '[]', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(31, 'bestchange', 'api_key', 0, '\"\"', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(32, 'bestchange', 'interval', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(33, 'direction-settings', 'unpaid_auto_delete', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(34, 'direction-settings', 'unpaid_order_status', 0, '[]', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(35, 'direction-settings', 'unpaid_time_day', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(36, 'direction-settings', 'unpaid_time_hour', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(37, 'direction-settings', 'unpaid_time_minute', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(38, 'direction-settings', 'generate_min_price', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(39, 'direction-settings', 'generate_max_price', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(40, 'bestchange-blacklist', 'is_status', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(41, 'bestchange-blacklist', 'categories', 0, '[]', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(42, 'bestchange-blacklist', 'method', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(43, 'bestchange-blacklist', 'api_id', 0, '\"\"', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(44, 'bestchange-blacklist', 'api_key', 0, '\"\"', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(45, 'bestchange-blacklist', 'columns', 0, '\"\"', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(46, 'bestchange-blacklist', 'type', 0, '\"\"', '2025-04-15 11:19:49', '2025-04-15 11:19:49'),
(47, 'direction-settings', 'profit_calculation_type', 0, '0', '2025-04-15 11:19:49', '2025-04-15 11:19:49');

-- --------------------------------------------------------

--
-- Структура таблицы `promo_codes`
--

CREATE TABLE `promo_codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `count_uses` int(11) NOT NULL DEFAULT 0 COMMENT 'Количество использований',
  `used` int(11) NOT NULL DEFAULT 0,
  `discount_type` varchar(16) NOT NULL DEFAULT 'percent',
  `discount_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `code` varchar(191) NOT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `scope_mode` varchar(16) NOT NULL DEFAULT 'all',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `promo_code_directions`
--

CREATE TABLE `promo_code_directions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `promo_code_id` bigint(20) UNSIGNED NOT NULL,
  `direction_exchange_id` int(11) NOT NULL,
  `mode` varchar(16) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `proxies`
--

CREATE TABLE `proxies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `host` varchar(191) NOT NULL,
  `port` smallint(5) UNSIGNED NOT NULL,
  `login` varchar(191) DEFAULT NULL,
  `password` text DEFAULT NULL,
  `type` enum('http','https','socks4','socks5') NOT NULL DEFAULT 'http',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `auto_disabled_until` timestamp NULL DEFAULT NULL,
  `last_checked_at` timestamp NULL DEFAULT NULL,
  `fail_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `proxy_health_logs`
--

CREATE TABLE `proxy_health_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `proxy_id` bigint(20) UNSIGNED NOT NULL,
  `context` varchar(50) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `http_status` smallint(5) UNSIGNED DEFAULT NULL,
  `latency_ms` int(10) UNSIGNED DEFAULT NULL,
  `error` varchar(1024) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `rates_history_logs`
--

CREATE TABLE `rates_history_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_source` int(11) NOT NULL DEFAULT 0,
  `source` varchar(191) NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `old_value` varchar(191) DEFAULT NULL,
  `new_value` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `referrals_info_logs`
--

CREATE TABLE `referrals_info_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_referral` int(11) NOT NULL DEFAULT 0,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `type` int(11) NOT NULL DEFAULT 0,
  `text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `referral_audit_logs`
--

CREATE TABLE `referral_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event` varchar(64) NOT NULL,
  `level` varchar(16) NOT NULL DEFAULT 'info',
  `partner_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `client_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `referral_link_id` bigint(20) UNSIGNED DEFAULT NULL,
  `referral_program_id` bigint(20) UNSIGNED DEFAULT NULL,
  `task_id` bigint(20) UNSIGNED DEFAULT NULL,
  `message` varchar(500) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `trace_id` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `referral_links`
--

CREATE TABLE `referral_links` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `referral_program_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `referral_links`
--

INSERT INTO `referral_links` (`id`, `user_id`, `referral_program_id`, `code`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Gz', '2019-11-28 22:23:48', '2019-11-29 14:16:03'),
(2, 2, 1, 'k5', '2024-07-29 06:24:27', '2024-07-29 06:24:27');

-- --------------------------------------------------------

--
-- Структура таблицы `referral_log`
--

CREATE TABLE `referral_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_referral_link` int(11) NOT NULL,
  `id_referral` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `event_key` varchar(191) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'confirmed',
  `available_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `is_reversal` tinyint(1) NOT NULL DEFAULT 0,
  `reversal_of_id` int(10) UNSIGNED DEFAULT NULL,
  `text` text NOT NULL,
  `bonus` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bonus_number` decimal(24,2) NOT NULL DEFAULT 0.00,
  `fixed_bonus` decimal(24,2) NOT NULL DEFAULT 0.00,
  `current_percent` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `reason` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `referral_programs`
--

CREATE TABLE `referral_programs` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `title` text NOT NULL,
  `description` longtext NOT NULL,
  `percent` varchar(191) NOT NULL,
  `lifetime_minutes` int(11) NOT NULL DEFAULT 10080,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `style_width` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `referral_programs`
--

INSERT INTO `referral_programs` (`id`, `name`, `title`, `description`, `percent`, `lifetime_minutes`, `created_at`, `updated_at`, `deleted_at`, `style_width`) VALUES
(1, 'level1', '{\"ru\":\"Стандартный\"}', '{\"ru\":\"Для новых пользователей\"}', '0.5', 525600, '2017-10-23 11:38:25', '2026-01-18 12:37:49', NULL, '100px'),
(5, 'Professional', '{\"ru\":\"Индивидуальный\"}', '{\"ru\":\"Для мониторингов\"}', '0.6', 1576800, '2018-03-27 13:54:08', '2026-01-18 12:38:07', NULL, '130px');

-- --------------------------------------------------------

--
-- Структура таблицы `referral_relationships`
--

CREATE TABLE `referral_relationships` (
  `id` int(10) UNSIGNED NOT NULL,
  `referral_link_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `referral_settings_code_audits`
--

CREATE TABLE `referral_settings_code_audits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor_id` int(10) UNSIGNED DEFAULT NULL,
  `actor_email` varchar(191) DEFAULT NULL,
  `before_code_currency_id` int(11) DEFAULT NULL,
  `after_code_currency_id` int(11) DEFAULT NULL,
  `before_fallback_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_fallback_codes`)),
  `after_fallback_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_fallback_codes`)),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `referral_statistics`
--

CREATE TABLE `referral_statistics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ref_hash` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(191) DEFAULT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `is_archive` int(11) NOT NULL DEFAULT 0,
  `user_agent` varchar(191) DEFAULT NULL,
  `cur_from` varchar(191) DEFAULT NULL,
  `cur_to` varchar(191) DEFAULT NULL,
  `from_and_to` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `requirements_verification`
--

CREATE TABLE `requirements_verification` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `requisites`
--

CREATE TABLE `requisites` (
  `id` int(11) NOT NULL,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `account_number` varchar(255) DEFAULT NULL,
  `view` int(11) NOT NULL DEFAULT 0,
  `limit_day` int(11) DEFAULT NULL,
  `limit_month` int(11) DEFAULT NULL,
  `random` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `pick_certain` int(11) NOT NULL DEFAULT 0 COMMENT 'Подобрать реквизит после определенного условия',
  `change_amount` varchar(191) DEFAULT NULL COMMENT 'Сменить реквизит если сумма превышает:',
  `is_history` int(11) NOT NULL DEFAULT 0 COMMENT 'История П.С	',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `id_group` int(11) NOT NULL DEFAULT 0,
  `account_number_field` varchar(191) DEFAULT NULL,
  `history_at` timestamp NULL DEFAULT NULL,
  `limit_views` int(11) NOT NULL DEFAULT 0,
  `is_enabled_merchant` int(11) NOT NULL DEFAULT 0,
  `id_proxy` int(11) NOT NULL DEFAULT 0,
  `is_drain` int(11) NOT NULL DEFAULT 0,
  `max_wallet_limit` int(11) NOT NULL DEFAULT 0,
  `drain_comment` varchar(191) DEFAULT NULL,
  `drain_room` varchar(191) DEFAULT NULL,
  `is_unique_shot` int(11) NOT NULL DEFAULT 0,
  `photo_status` int(11) NOT NULL DEFAULT 0,
  `photo_name` varchar(191) DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `is_already_used` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `requisites_fields`
--

CREATE TABLE `requisites_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text DEFAULT NULL,
  `value` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `comment` longtext DEFAULT NULL,
  `prefix` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `requisites_group`
--

CREATE TABLE `requisites_group` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `requisites_group`
--

INSERT INTO `requisites_group` (`id`, `name`, `status`, `sorting`, `created_at`, `updated_at`) VALUES
(1, 'All', 1, 1, '2019-11-29 10:31:44', '2019-11-29 10:31:44');

-- --------------------------------------------------------

--
-- Структура таблицы `requisites_has_fields`
--

CREATE TABLE `requisites_has_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `field_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `requisites_has_info_fields`
--

CREATE TABLE `requisites_has_info_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(11) NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `field_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `requisites_info_fields`
--

CREATE TABLE `requisites_info_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key_name` text DEFAULT NULL,
  `value_name` text DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `requisites_list`
--

CREATE TABLE `requisites_list` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_requisites` int(11) NOT NULL,
  `address` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `requisites_logs`
--

CREATE TABLE `requisites_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_requisite` int(11) NOT NULL DEFAULT 0,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `ip_address` varchar(191) DEFAULT NULL,
  `user_agent` varchar(191) DEFAULT NULL,
  `account` varchar(191) DEFAULT NULL,
  `old_account` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `reserves`
--

CREATE TABLE `reserves` (
  `id` int(11) NOT NULL,
  `id_currency` int(11) NOT NULL,
  `summa` double NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_code_currency` int(11) NOT NULL,
  `is_non_standard` int(11) DEFAULT 0,
  `black_amount` float DEFAULT 0,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_main` int(11) NOT NULL DEFAULT 0 COMMENT 'ID базового резерва',
  `id_group` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `is_fixed_reserve` int(11) NOT NULL DEFAULT 0,
  `is_star` int(11) NOT NULL DEFAULT 0,
  `id_file_reserve` int(11) NOT NULL DEFAULT 0,
  `id_server_reserve` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `reserves_files`
--

CREATE TABLE `reserves_files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_group` int(11) NOT NULL DEFAULT 0,
  `name` varchar(191) DEFAULT NULL,
  `amount` double NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `number_format` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `reserves_files_groups`
--

CREATE TABLE `reserves_files_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `link` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `reserves_logs`
--

CREATE TABLE `reserves_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_reserve` bigint(20) NOT NULL DEFAULT 0,
  `amount` varchar(191) NOT NULL DEFAULT '0',
  `amount_from` varchar(191) NOT NULL DEFAULT '0',
  `amount_to` varchar(191) NOT NULL DEFAULT '0',
  `type_reserve` int(11) NOT NULL DEFAULT 0,
  `id_task` bigint(20) NOT NULL DEFAULT 0,
  `id_direction_exchange` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `reserves_manual_events`
--

CREATE TABLE `reserves_manual_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_reserve` int(11) NOT NULL DEFAULT 0,
  `reserve_from` varchar(191) NOT NULL DEFAULT '0',
  `reserve_to` varchar(191) NOT NULL DEFAULT '0',
  `comment` text DEFAULT NULL,
  `type_reserve` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `reserve_total_snapshots`
--

CREATE TABLE `reserve_total_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `snapshot_at` timestamp NOT NULL,
  `total_usd` decimal(36,18) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `reviews`
--

CREATE TABLE `reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `text` text DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_agent` varchar(191) DEFAULT NULL,
  `id_admin` int(11) NOT NULL DEFAULT 0,
  `rate_speed` int(11) NOT NULL DEFAULT 0,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `id_direction_exchange` int(11) NOT NULL DEFAULT 0,
  `version` int(11) NOT NULL DEFAULT 0,
  `id_user` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `reward_programs`
--

CREATE TABLE `reward_programs` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `percent` varchar(191) NOT NULL,
  `sign` varchar(191) DEFAULT NULL,
  `is_reg` int(11) NOT NULL DEFAULT 0,
  `amount` int(11) NOT NULL,
  `title` text NOT NULL,
  `description` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `style_width` varchar(191) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `reward_programs`
--

INSERT INTO `reward_programs` (`id`, `name`, `percent`, `sign`, `is_reg`, `amount`, `title`, `description`, `created_at`, `updated_at`, `deleted_at`, `style_width`) VALUES
(1, 'reward1', '0.2', NULL, 1, 0, 'после регистрации на сайте', 'после регистрации на сайте', '2019-01-04 17:18:19', '2019-02-21 07:37:57', NULL, '0'),
(2, 'reward2', '0.5', NULL, 0, 2000, 'при обмене от 2 000$', 'при обмене от 2 000$', '2019-01-04 17:19:11', '2019-02-21 07:38:58', NULL, '0'),
(3, 'reward3', '0.9', NULL, 0, 10000, 'при обмене от 10 000$', 'при обмене от 10 000$', '2019-01-04 17:19:33', '2019-02-21 07:39:11', NULL, '0');

-- --------------------------------------------------------

--
-- Структура таблицы `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `is_export` int(11) NOT NULL DEFAULT 0,
  `guard_name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `title` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `roles`
--

INSERT INTO `roles` (`id`, `name`, `is_export`, `guard_name`, `created_at`, `updated_at`, `title`) VALUES
(1, 'super admin', 1, 'web', '2017-10-03 07:08:58', '2024-07-29 06:24:27', 'Главные администраторы'),
(2, 'administrator', 0, 'web', '2017-10-03 07:09:53', '2024-07-29 06:24:27', 'Администраторы'),
(3, 'moderator', 0, 'web', '2017-10-03 07:10:03', '2024-07-29 06:24:27', 'Модераторы'),
(4, 'support', 0, 'web', '2017-10-03 07:10:16', '2024-07-29 06:24:27', 'Служба поддержки'),
(6, 'manager', 0, 'web', '2017-10-11 05:11:52', '2024-07-29 06:24:27', 'Менеджеры');

-- --------------------------------------------------------

--
-- Структура таблицы `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(5, 1),
(6, 1),
(7, 1),
(8, 1),
(9, 1),
(10, 1),
(11, 1),
(12, 1),
(13, 1),
(14, 1),
(15, 1),
(16, 1),
(17, 1),
(18, 1),
(19, 1),
(20, 1),
(21, 1),
(23, 1),
(24, 1),
(25, 1),
(26, 1),
(28, 1),
(30, 1),
(31, 1),
(32, 1),
(33, 1),
(34, 1),
(36, 1),
(37, 1),
(39, 1),
(40, 1),
(41, 1),
(42, 1),
(43, 1),
(44, 1),
(45, 1),
(46, 1),
(49, 1),
(50, 1),
(51, 1),
(52, 1),
(53, 1),
(54, 1),
(55, 1),
(56, 1),
(57, 1),
(58, 1),
(59, 1),
(60, 1),
(61, 1),
(62, 1),
(63, 1),
(64, 1),
(65, 1),
(66, 1),
(67, 1),
(68, 1),
(69, 1),
(70, 1),
(71, 1),
(72, 1),
(73, 1),
(74, 1),
(75, 1),
(76, 1),
(77, 1),
(78, 1),
(79, 1),
(80, 1),
(81, 1),
(82, 1),
(83, 1),
(84, 1),
(85, 1),
(86, 1),
(87, 1),
(88, 1),
(89, 1),
(90, 1),
(91, 1),
(92, 1),
(93, 1),
(94, 1),
(95, 1),
(96, 1),
(97, 1),
(98, 1),
(99, 1),
(100, 1),
(101, 1),
(102, 1),
(103, 1),
(1, 2),
(5, 2),
(6, 2),
(10, 2),
(11, 2),
(12, 2),
(13, 2),
(14, 2),
(15, 2),
(20, 2),
(25, 2),
(26, 2),
(28, 2),
(2, 3),
(6, 3),
(7, 3),
(2, 4),
(10, 6),
(11, 6),
(12, 6),
(15, 6),
(20, 6),
(25, 6),
(28, 6),
(37, 6);

-- --------------------------------------------------------

--
-- Структура таблицы `selector_fees`
--

CREATE TABLE `selector_fees` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`description`)),
  `fee` varchar(191) NOT NULL,
  `fee_type` varchar(16) NOT NULL DEFAULT 'dynamic',
  `sorting` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `selector_fee_direction_exchange`
--

CREATE TABLE `selector_fee_direction_exchange` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `selector_fee_id` bigint(20) UNSIGNED NOT NULL,
  `direction_exchange_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(191) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('0Ad6aQEtfXYXQt0mwJD0vg8pIYErQ7UtxCQS5auH', NULL, '49.13.105.113', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36', 'ZXlKcGRpSTZJaTluY0RJeFlYVkpMMEV5WVhaUWRub3hOSEJhT0ZFOVBTSXNJblpoYkhWbElqb2lPRVZWZDFGSE0xbzRZalpaTWxwVlZsZzRNVTk0ZUhCamVrVTFTR280VVdRemQyWk1lWEZrTTBSdWNFVk9TVGhyWkdGS2J6QlhkM3BTWlhsR00wVmlVMWhCVnpCcVJYUnVhblpsTnpsblpVUjJhbmR4TmpNdk5pdDFORGxpVjJ0MVQwWjZhMlJqV25oUlQyZHhUM2hMU2tkaEwzQmpiM0pyZVZnM2JERXpNVWN6YlM5RmNUQTNibUZhUWt3cmVuTTBUV3BYVldOa1dYQXZZMEU1TDNKeVNISTRPVFEzVFdwYVVUbEJSRkV5YjFKVFdscEtaVGh5ZHpGRGRXeHpLMlJWWkZWMVptaFlOMlYxZUdGMk4yVlBiV2s0ZEVwc1VXaEJiMGQ2U0ROcEwxVlFiWHB1WWxOemRuRk1ZVFF5U1hSM2FHWnBlbU5MYVc5ek1USlhUMDluYlVnNVUwczBUbkJMWXl0cE1HNVVPREpqTlVNd2RXcHhUQ3RJWjIwd1JFczBVMlIxWVdSaGMwbGpaM0p1V2xnMU0xRXlOMEYxY1ZoRE1rNHlObTB5Ym13aUxDSnRZV01pT2lKaU5UQTJObUV3T1RBd01UQTNaVEJoTUdNM04yVTFaRFkzTW1VeU5qZzRNVEJsWTJJNU5EYzVNVGxoWlRJME16RXpPV0k1TjJRMVlUVTFNakExWkRRNElpd2lkR0ZuSWpvaUluMD0=', 1768730162),
('F6kg433uEfv3oEeX7Ist2QxNsVEKfcuDeazq0MVZ', NULL, '49.13.105.113', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36', 'ZXlKcGRpSTZJbEIyVEhrNVQzZHBZblF2ZVhWbVFXbHJhM2hhTkdjOVBTSXNJblpoYkhWbElqb2lZVmgzWlhoTFdtTmhRbFV6UTBKTlNrTTVaekJMWXpGQ09UbFZaalpWVERSUlZHbFVVbXRhZFVKcVdXbGlkM1IxTWtGamJXeFhRak14WVZGd09USXlWbkpQUm14UVJFOUJaazVsZW1Zd1JqTjJkV1JWV2tKeFMwTmtlWE5FWTBZMmQwWk9hMVEwUVhBNVJsSkhWM1ppVEhaSmJERkJiRXRQTHl0RE0wUm1SRTlHTW5OU0t6bHdhbVZwVEZWU1dFUmFVVXgxTWs1S2EwZ3hOekZHVEhvd1JHSmtRbGRXTmxodmFXOUxkM1pzY2tOc1RsbFhlVEl5SzFkVE9VUldjVlV2VVUxc1VqSm9UV0ZaZUdoUE9WbFNPRU5yWkVRMFFtRkhOVFJxZFhaek9IZFNibm81ZWpKVFdrMUVRMGd4V1ZwUksyYzBiREZDZVhwd01uVllUbWhXVEd4eldWWkhTRmxOV1hKVU4yNVhja2h1V2xsTWIwOVNVREV6VUROQ2R6ZERRa1psUVVWSGJIbDNURWxUYlVSRVlVODFZVzF6TjJKWWNtMUNRelY1UVhZaUxDSnRZV01pT2lJMFlqSmtaams0TURGaVpUVmtOelk1WTJObE5EQTJNamd5TmpJNU16UmhNbU14TXpabU4yRTROR001TUdWbU1ERXhNRGcyTVRJMU5UUmxPRGN4Wmpkaklpd2lkR0ZuSWpvaUluMD0=', 1768729321),
('frbHYQt0FlTdocbEf1yKqdBqACn5kqnvXSMDmZH5', 1, '87.200.44.224', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'ZXlKcGRpSTZJalkwV0c5a2JXcDJVMXAwTmtkUlRITk9ibmhyYVZFOVBTSXNJblpoYkhWbElqb2laVFJUWTJFM1VXMTVZMFpPUlN0VlpHUnJWRmhYWml0VVMyWmtUbWMwWVhwUWF6TlZSbVpJYVhRMFFWbzNXWGRwU3pOS1N6Z3ZVbkJHYUcxRVFqQmFPRlF4TlZOWVlVSkdSVE5tU0VKcU5YbE9VbHBuYmxkWk0yOHlOWGxWVDJGNFUySktORVZ0VUZwbWVXVnljM3B5YW5SUmJHbDNXazlQVUVsMlZqaEtNVWhpYVdwc1J5OTBPRFpTY1N0NVlYTmhhamszVlVoVE0xQkhWMlZKTURCb1lsY3hNM3AwVmt0SWNGTTFWRGxrZFd0d0t6RXpObU50ZUdWU2Jsb3ZZbWRoUTJORU1FNTJUbkI2YUhwT1NISnpVVEJIVFhSc1RrUlpWa05JYzJOdlpVRkZWRmRoVXpSU1ZHdGxiMDlXZURoc2NHOW1aR2RhZERCWGJrVkVSalZYTkVGaWRVcHZPVWRCUlRNM1FVOHdWa0ZJYUdWb2JWb3dTRVV6Tld0UWFXWmpWVGs1U0dWWmVXbHRNMVZWY0dKS1ZIRjVaRWxaUjFBdlptbzFSRE41Y1hwTmNWRkROa2xZY1ZWVGExSXJOelZ4VWtRemRYaHBOMk16WlRadGNtUnhSekkwVEc1RFZHZEJhMDgzWkM4eGVFMHJRWEZ1U3pkeU5qRkhjMVZ1VEcxMFZGQkpaVFl3YkdobFpTdFFMMVJoYjFsU04zZEtaVzgzVHpkS1NtRkVWbVUwZEZCbEwydE9aWHB4YkVvelpYQkVUbmd3TVVjdmNIQkhlRVoxV0dKV1JXWm9XVFZKUnpRdk5rTmljMEpUTmpWbk1WbHpNemN2YW5oMGJuZFVNMHRUVWpSS1VGVndMMGhPTlhOVWVqUnRSWFJMTm01YVJHZHVSMVZYUkRnNGFFUjBRMjFsWTIxR04zQTFiRVJqYUdoT1dtY3pTM3B1ZVdGNVQwSm1NWHBWYTJkT2FWWlFRbk5WVHpGemFsQjVUekk1YXl0VFlsaHRObmQxVUM5alpGcE5VVmg0T1M4d1JEQlZTRk5JUVVFeFdFZGpkV2hJVFhaalpWQXllR1JpZDIxSk5YcEhkbGtyVmpWT1EzcHdMek5CYUdSbFlVWjZXV1ZLWmtJclFuZDVjVzFVYlVWYWJFeFhZVlJwTWxWcVlVWlhNRFU1YUhjOVBTSXNJbTFoWXlJNklqWTFaamxtWVdWak9ETmpObU15TURWa01HSmhPV05tWVdNMlpqTXhNR1ZoTm1OaVpXUTRZVFJsTW1FeE5qa3pOVFZrWW1FeU56Z3lObVZoTmpsa01EQWlMQ0owWVdjaU9pSWlmUT09', 1768732124),
('JkewRHN5LnPERGSmNrzX35L4YJpiiyfahe2mdK80', NULL, '87.200.44.224', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'ZXlKcGRpSTZJakl6YWtodlJHTldTekUzTlVVemJtaEROR0pLV25jOVBTSXNJblpoYkhWbElqb2lOMGx1UkV3MVZDczRhMFlyZEdSMU5EaFBha280Um5Cd2VUbE9lbFExUzI5VFoyUTRVVkpXYlRGd2RGTlFNbUUyTml0NFIyWnNSbWxtYTBOd09WVlBWRTl4Ykd4Q2F6bHJiVmR1UjBjcldVUnRSVnBLVmxOVlRpdGljRkZUWTFKeFZYZDBTVEJ1YmsweWNEUjVla1JhY0RSSlpqZFZNbUp4YTJSa2RXMTVaRTlaY1djM2NGQm5hVlZ3VlRoVWRVY3JNM05TUmxSb1YweFBUVUZGYVhVMlREbHVXa1Z1UlRGbVVFSnhNMjFYY2xadVpIbDBSbVJMU1ZOelZERjRhMUUyTVVWMFRIQTRjVEZHVDJzM1JtcHViSHBQTDJ0SU1WWkZiREZ2VVRKV2VHOUdaRmMyTTFGcWQybE9RWEpCY1ZWeGNHZG9Ubk5NWVVVMldVWkZRamx5UVdKdmRERTJVWEZyUjBOblVuWlNaa0V2Y1ZGM01WZzNjVXRuWTBwVWNERkVTMjVwWVRWeWVtaDNWR1k1UkVwT09YcGxSMFZOVWpSaFIweFROVVpFZGtWTFRteGxla3BuT0ZSaU1XdzRWRlJFWVhwSGNqRnZXR0owTDFsNGRFVjBiVlF3YTIxSmVWaElVbU5aVUVGdlVWRXlWMWhwYUN0VFV6WnNSRzg1YTBoRUlpd2liV0ZqSWpvaU5EbGtaV0ppWW1JMFlUaG1ZemhtWXpObFptUTVOak00T1RJNE1ESXlaRFZtWkRCbFltRXpaR1UxT0RSbE1USm1Zek14T1dJeVkyTmlaVEZrTm1ZeE5pSXNJblJoWnlJNklpSjk=', 1768731632),
('lodvQBCLQeTLiTwwJIwsyO1u4WAxcmpp2w08c2V3', NULL, '49.13.105.113', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36', 'ZXlKcGRpSTZJaTh5TDI1RVpUQTFTRkZrTDBZeVRWVm9TRU52TmxFOVBTSXNJblpoYkhWbElqb2lOamRpU1doMmVtRkNZVlJ6TDBvMVJHVlZOWEZLWkZZM2QwUllielpQVDBGNFoyRndiM042YjB4d1ZsSlNhRW80TWpCVGNHSTFaaTlCVDNGRlRrUTBORE52VEdSUGRGSkhiRTV2WjBad1YzUm5XV05LTlVnNVpXWm9hR0Z5YzBKalIxQjBVVVZIYW1oWU9FcE5jbEJ2V214R2JGQkNTemN4TlhablVITXJRWEZUTldneFVXeFFUSE5GWkc5b1Uyd3JXVk5JYkRGVGMzbFNNQ3RYZVVsaEwybG9Na2hxUzBWMWRVbFZNakI0VFdjdlJXdFBTazFhYVZWSWIwcHBOMUJIUm1jclV6SllWMkpHYzNKb1oyNVljRlJ4VGswM1dreGFZM3BSVFRCb1JWbFlZa04zYW5GWmNuWkZPSEoxVjBKVGMwaEZkSEJhZDNOb1VFdFFUMjVRZG5CRk0ybDZRekExWVZSaGNuWnZiR0ZxUkdFeWNrOW9jWFEyUzFwV1JqUTBObkJSYldWM1dIcElWMUUyUzFkT0t5OXhSVzFuZFdkc05FTlFXR051UTNraUxDSnRZV01pT2lJNU5tSXdPR1EzTkdVMFptSXlNamM0WVdFMVlqa3dOREkwWm1VNU1qZ3dOek5qWVRWaU9HRXdOVGt6TldZMFpqaGxOV0U1TnpOaE9XSTFNR0ZoTTJVMklpd2lkR0ZuSWpvaUluMD0=', 1768730401),
('pp7NkBh7sCehpwcqZubFbYZjUlaX9PYoSANitTgM', NULL, '49.13.105.113', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36', 'ZXlKcGRpSTZJblZsUzBaU1lreHJOM0l6UW1OdE1UVjFPRWRRWjNjOVBTSXNJblpoYkhWbElqb2lhRWRtTmpjM1ZEWkVhRTlDYW1sbVdYZFlOMGx3Vkc1RGVHYzFiVkJ1VTFKbVJqTnBSV1kxVWxwWlptMURWRVZsVHpsRWNIUk5NRzFoWTJ4UE16RkhlVWxWYTNGblVFMUNRbGx2UTNGWmVUUlpVVWh6VDJsaFJTOUZha1V4Y1N0dlZqRmxjRVE0V2twRmJrUkpXSFU0YkdzeE1TdE1iV3MwYmtWRk1scFBSeXRoY0U5TU0xWkJjVnBhVVdsS1pFdzRUVVJaUkdaeE1URXpNbVVyYTFWUlFpOVNjSFJVU1ZGM1RFTkpOSFkwU0V0dmVHdDZhVkEzY0dObE0xRkRUM1ZIUjFObVdXRXJVVkJ2V0ZWeE9WaE1lVTlsYVRCaEsxWm5iUzlXYjBsQksxcFZZMUY1YTJSQ1kxaHNOMHBZVFM5NVJrTk9jRE5UUkhZME5YcHVTV2RsTms5d2VUQjZlak0wV0M5R2VEZFNaV1VyTUdKMGVuQjZjV2hLUTJvMmJYWm5Xa1JYVUdsdmVHMXVTVkk1YTNvMlVqVTFiR2xXTmtsaVJWTk5VR3BOYUhBaUxDSnRZV01pT2lJNU5HTTFZamhoTWpKaU56SXlNR1kyTUdVM1lXUTRORGc0T1RGak5HSmtaRGhoTVRjell6TTNObVZrTVRjeVpETXhNMll6T1RCbVlqRmxZVEUyWm1KaUlpd2lkR0ZuSWpvaUluMD0=', 1768731241),
('QBkrmZSVP4aqYFWpChaZSBMM8YKUKGvtDNaKIyLX', NULL, '49.13.105.113', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36', 'ZXlKcGRpSTZJbTQwZHpaS2VFRkViR3RqVjAxbEsyeHlZakF6Y1VFOVBTSXNJblpoYkhWbElqb2lSMGRFV1M4NWJGVTRkaTloUXpoRWRXZDRTbXh3TlhGNlJsTTVNa1J4TkRSV1JVaE5jazE1WjNZNVUwWkRaM2RIT0ZoTGFFVm5lamRXTW5aT1IxRXllR1JPV25aS1ZIVktSa1ZEVVdsSGNYQTNZbkpNYWtaNk4xQlZURU5FYzA1MlVrUXJOVFIzVUdodlkwdE9NSFZxVlhaTlowSmtUMFp1VVVkSVozUTRlR1pYUlVoMVlpOUhaa1JEYkc1RVpucFJNVUl3ZVZGRFFqRkhRbVZVYVVONk5HSjFiM2x4VTBRMGFGaGtiWEZtVW01RVkzaDNMMjFqWmsxTGJFRmFURkpoVHpkM1RIWk9OWEJGUVhCNFdreE5VR0VyWkZBMFNHTmxUa1UwYXprelRWRkdZeTl3ZWpSYWJFOWxOMU52YURORVdWQlRaazR5ZUZjeVEwSjFhM0pZTTJaVlVVZzFXVVZpUVdkSVZXOVhORXN5WlZaNVRIQjJPRGhHT0d4RWVIUTVWWEJTYWxwbVIwOUVibVprU0RaUGIwdHZhblpHY0RkeVlXcDBRVTQwYXpBaUxDSnRZV01pT2lJeFpXSmpNVGhpT0RrME5Ua3lNelF3TTJVd04yUXdOVEEyTW1RelpUaG1Zak0wT1RsbU16UTNNV1JsT1RjM1kySmxOems1TVdZNE9UUXlPVFJsTTJRd0lpd2lkR0ZuSWpvaUluMD0=', 1768732081),
('TnVJ6w7cRZIhgy9RuoYFVtqPJrPUeJ9DW2ACLnbg', NULL, '49.13.105.113', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36', 'ZXlKcGRpSTZJblpMZDNWSlFYRkpiemRUTTBkd1prRmxSamRKUTJjOVBTSXNJblpoYkhWbElqb2lRVTlhUVd0TVJGWmlia1F5Ykc5M01sYzJZMUptU25jM00ydDFRbWhTVUdGWWRVb3llRTVZZEhsRFVuUkZUM1puVlc5blpqWTNkMlpFWmtvMWJtb3hjVGhCTldSS00xSnFVaXQzUjB0a2JHNDFOMnBJUjNSaFVFSnhObTV4VTNwV1VrRXdUVWhSTDBSVVF6bFdMMDlXSzNnNVVIRkRZelpxTm5kRmF5dHFUalp2Y1hGVWVtaGxRVUkyYWpGU1FsVnlhMlJ3U2s5aFZHWnpSekJJZVV4amNGTnFla1pQTDFSTFFVRlpTMDlsZWs1dlRFZzNTRUV3Y25jdmRUSjRWMDFCUlcxNE1qTlFORWh4VVVsWU1FTnROblF2UzAwM1FtSnhUelZNV25sUGRVUjZiQzlCTWtNeWFGWkdha1pIUldGb1VEazRVVGd2VlN0WE5uaExORll4Ym5CTk5tSlFVMWRzVEdsR1dpOVZNak5uVFdaTmExQnRaM2t3YTJwd1dIRXZlSFZIWkVzdmMxaDNTa3RtT0ZGeE5tOXNjbFpyUlVreE1URTNTVWhxYVdjaUxDSnRZV01pT2lKaE9EQmxaak0yWVdKak1qSTBZbUU0WVdZek1ESmtPVGN6WldVMVl6VTVZakUxT1dWa01ETmtaalJpTTJSaFpEQTJNMkl6TVdNd01qSXlNR1UzWm1VMklpd2lkR0ZuSWpvaUluMD0=', 1768728481);

-- --------------------------------------------------------

--
-- Структура таблицы `settings_limit_profiles`
--

CREATE TABLE `settings_limit_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `description` varchar(191) DEFAULT NULL,
  `max_num_order_user_hour` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `max_num_order_user_day` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `order_limit_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `order_limit_minutes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `min_interval_between_orders_seconds` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `first_orders_window_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `first_orders_max_amount` decimal(24,8) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `settings_referrals`
--

CREATE TABLE `settings_referrals` (
  `id` int(11) NOT NULL,
  `account_text` text DEFAULT NULL,
  `partners_text` text DEFAULT NULL,
  `block_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `settings_referrals`
--

INSERT INTO `settings_referrals` (`id`, `account_text`, `partners_text`, `block_text`, `created_at`, `updated_at`) VALUES
(1, '<p>Обратите внимание! Реферальные начисляются от заработка сервиса с каждой вашей транзакции. В случаях, когда сервис не получает выгоду от обмена, реферальные не начисляются.</p>', '<p>Посоветуйте наш сервис друзьям и партнерам и зарабатывайте вместе с нами! Ваша реферальная ссылка находится в личном кабинете, в разделе &laquo;Реферальная программа&raquo;, скопируйте её и поделитесь с друзьями!</p>\n\n<p>Для вашего удобства в личном кабинете хранится история реферальных начислений, где отражено, сколько человек было зарегистрировано по вашей рекомендации, а так же информация о вашем кешбеке.</p>\n\n<p>Мы разработали поэтапную реферальную программу с хорошим вознаграждением:</p>', '', '2019-02-21 07:25:37', '2019-02-21 07:25:37');

-- --------------------------------------------------------

--
-- Структура таблицы `settings_theme`
--

CREATE TABLE `settings_theme` (
  `id` int(10) UNSIGNED NOT NULL,
  `visible_fon` int(11) NOT NULL,
  `background_svg` int(11) NOT NULL DEFAULT 0,
  `start_sharing` int(11) DEFAULT 0 COMMENT 'Стиль кнопки "Начать обмен"',
  `fon` varchar(191) DEFAULT NULL,
  `background_position_1` varchar(191) DEFAULT NULL,
  `background_position_2` varchar(191) DEFAULT NULL,
  `background_repeat` varchar(100) DEFAULT NULL,
  `background_size` varchar(100) DEFAULT NULL,
  `full_background` int(11) NOT NULL DEFAULT 0,
  `favicon` varchar(191) DEFAULT NULL,
  `logotype` varchar(191) DEFAULT NULL,
  `textlogotype` varchar(191) DEFAULT NULL,
  `select_logotype` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `settings_theme`
--

INSERT INTO `settings_theme` (`id`, `visible_fon`, `background_svg`, `start_sharing`, `fon`, `background_position_1`, `background_position_2`, `background_repeat`, `background_size`, `full_background`, `favicon`, `logotype`, `textlogotype`, `select_logotype`, `created_at`, `updated_at`) VALUES
(1, 1, 0, 1, '66AfW4ipllmv4v2.png', 'left', 'left', 'no-repeat', 'auto', 0, NULL, 'logo.png', 'OwlChange', 0, '2017-10-04 21:00:00', '2017-12-13 15:19:43');

-- --------------------------------------------------------

--
-- Структура таблицы `social_auth_system`
--

CREATE TABLE `social_auth_system` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `alias` varchar(191) DEFAULT NULL,
  `client_id` varchar(191) DEFAULT NULL,
  `client_secret` varchar(191) DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `social_reviews`
--

CREATE TABLE `social_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `type` varchar(191) NOT NULL DEFAULT '0',
  `link` varchar(191) DEFAULT NULL,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `sumsub_ids`
--

CREATE TABLE `sumsub_ids` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `applicant_id` varchar(191) NOT NULL,
  `status` varchar(191) DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `expire_at` timestamp NULL DEFAULT NULL,
  `sumsub_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sumsub_data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `provider` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `system_logs`
--

CREATE TABLE `system_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `module` varchar(64) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `level` varchar(20) NOT NULL,
  `state` enum('active','resolved') NOT NULL DEFAULT 'active',
  `active_flag` tinyint(4) NOT NULL DEFAULT 0 COMMENT '1=active, 0=resolved (для уникального индекса и быстрого поиска)',
  `active_unique_key` varchar(768) GENERATED ALWAYS AS (case when `active_flag` = 1 then concat_ws('#',`module`,coalesce(`name`,''),coalesce(`code`,''),coalesce(`entity_type`,''),coalesce(`entity_id`,'')) else NULL end) STORED COMMENT 'Технический ключ уникальности только для активных записей',
  `entity_type` varchar(120) DEFAULT NULL,
  `entity_id` varchar(64) DEFAULT NULL,
  `code` varchar(128) DEFAULT NULL,
  `message` varchar(191) NOT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `source` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`source`)),
  `occurred_at` timestamp NULL DEFAULT NULL,
  `first_seen_at` timestamp NULL DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `times_seen` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `last_message` text DEFAULT NULL,
  `last_context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`last_context`)),
  `dedup_hash` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tag_processor_custom_tags`
--

CREATE TABLE `tag_processor_custom_tags` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(191) NOT NULL,
  `label` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `group` varchar(191) DEFAULT NULL,
  `type` varchar(191) NOT NULL DEFAULT 'string',
  `scope` varchar(191) NOT NULL DEFAULT 'any',
  `value` longtext DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tag_processor_entity_custom_tags`
--

CREATE TABLE `tag_processor_entity_custom_tags` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_type` varchar(191) NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(191) NOT NULL,
  `label` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `group` varchar(191) DEFAULT NULL,
  `type` varchar(191) NOT NULL DEFAULT 'string',
  `value` longtext DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks`
--

CREATE TABLE `tasks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(191) NOT NULL DEFAULT 'order',
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_direction_exchange` int(11) NOT NULL DEFAULT 0,
  `id_payment_requisites` int(11) NOT NULL DEFAULT 0,
  `id_wallets_addresses` int(11) DEFAULT NULL,
  `give_price` varchar(255) DEFAULT NULL,
  `receiving_price` varchar(255) DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(255) NOT NULL DEFAULT '0',
  `status` int(11) NOT NULL DEFAULT 2,
  `from_shot` varchar(255) DEFAULT NULL,
  `to_shot` varchar(255) DEFAULT NULL,
  `passed` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `description_receiving` text DEFAULT NULL,
  `description_give` text DEFAULT NULL,
  `discount1` double NOT NULL,
  `discount2` double NOT NULL,
  `markup1` double NOT NULL DEFAULT 0,
  `markup2` double NOT NULL DEFAULT 0,
  `email` varchar(255) DEFAULT NULL,
  `payment_address` varchar(191) DEFAULT NULL COMMENT 'Генерируемые номера счетов (Bitcoin, Eth ...)	',
  `rejection_reason` varchar(191) DEFAULT NULL COMMENT 'Причина отклонения заявки	',
  `start` int(11) NOT NULL DEFAULT 0 COMMENT 'Запускаем выполнение заявки (0 -> Не запущен процесс) (1 -> Запущен)	',
  `message_success` text DEFAULT NULL COMMENT 'Текст при успешном завершении заявки',
  `check_status` int(11) NOT NULL DEFAULT 0 COMMENT 'Статус отправки чека клиенту	',
  `course_display` varchar(191) DEFAULT NULL,
  `merchant_status` int(11) NOT NULL DEFAULT 0 COMMENT 'Был ли переход на мерчант',
  `income_code` text DEFAULT NULL,
  `check_income_code` int(11) NOT NULL DEFAULT 0,
  `is_frozen` int(11) NOT NULL DEFAULT 0 COMMENT 'Замороженные средства',
  `course_float` varchar(191) NOT NULL DEFAULT '0',
  `payment_field` varchar(191) DEFAULT NULL,
  `category_reject` int(11) NOT NULL DEFAULT 0,
  `is_archive` int(11) NOT NULL DEFAULT 0,
  `id_rejection_status` int(11) NOT NULL DEFAULT 0,
  `id_pending_status` int(11) NOT NULL DEFAULT 0,
  `is_favorites` int(11) NOT NULL DEFAULT 0,
  `is_spam` int(11) NOT NULL DEFAULT 0,
  `is_bot` int(11) NOT NULL DEFAULT 0,
  `register_tx` int(11) NOT NULL DEFAULT 0,
  `merchant_incomplete_payment` int(11) NOT NULL DEFAULT 0,
  `merchant_overpayment` int(11) NOT NULL DEFAULT 0,
  `in_flow_funds` int(11) NOT NULL DEFAULT 0,
  `next_checkout_at` timestamp NULL DEFAULT NULL,
  `double_withdrawal` int(11) NOT NULL DEFAULT 0,
  `phone` varchar(191) DEFAULT NULL,
  `is_drain_merchant` int(11) NOT NULL DEFAULT 0,
  `pay_num` int(11) NOT NULL DEFAULT 0,
  `is_autopay_off` int(11) NOT NULL DEFAULT 0,
  `merchant_provider` varchar(191) DEFAULT NULL,
  `id_referral_link` int(11) NOT NULL DEFAULT 0,
  `is_new_user` int(11) NOT NULL DEFAULT 0,
  `type_finished_order` int(11) NOT NULL DEFAULT 0,
  `unique_security_code` varchar(191) DEFAULT NULL,
  `kunacode` varchar(191) DEFAULT NULL,
  `is_autopay_modal` int(11) NOT NULL DEFAULT 0,
  `is_autopay_limit` int(11) NOT NULL DEFAULT 0,
  `id_edit_data_manager` int(11) NOT NULL DEFAULT 0,
  `telegram_id` varchar(191) DEFAULT NULL,
  `in_price_fee` varchar(191) NOT NULL DEFAULT '0',
  `out_price_fee` varchar(191) NOT NULL DEFAULT '0',
  `is_ban_order_data` int(11) NOT NULL DEFAULT 0,
  `requisites_receive` varchar(191) DEFAULT NULL COMMENT 'Реквизиты, куда принимаются средства',
  `id_merchant` int(11) NOT NULL DEFAULT 0,
  `id_pay` int(11) NOT NULL DEFAULT 0,
  `is_auto_check_pay` int(11) NOT NULL DEFAULT 0,
  `queue_status` int(11) NOT NULL DEFAULT 0 COMMENT 'В очереди на выплату',
  `id_payment_gateway` bigint(20) NOT NULL DEFAULT 0,
  `id_promo_code` int(11) NOT NULL DEFAULT 0,
  `id_direction_requisites` int(11) NOT NULL DEFAULT 0,
  `in_amount_merchant` varchar(191) DEFAULT NULL,
  `out_amount_pay` varchar(191) DEFAULT NULL,
  `public_id` bigint(20) NOT NULL DEFAULT 0,
  `view_expires_at` timestamp NULL DEFAULT NULL,
  `transfer_to_account` varchar(191) DEFAULT NULL COMMENT 'Записанный адрес куда клиенты отправляют средства',
  `is_status_pay_callback` int(11) NOT NULL DEFAULT 0,
  `status_pay_api` int(11) NOT NULL DEFAULT 0,
  `is_status_pay_email` int(11) NOT NULL DEFAULT 0,
  `is_attempts_pay` int(11) NOT NULL DEFAULT 0,
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
  `is_send_mail_create` int(11) NOT NULL DEFAULT 0,
  `id_order_step` int(11) NOT NULL DEFAULT 0,
  `is_request_payment_type` int(11) NOT NULL DEFAULT 0,
  `is_wallet_issued` int(11) NOT NULL DEFAULT 0,
  `requisites_description` text DEFAULT NULL,
  `is_file_check` int(11) NOT NULL DEFAULT 0,
  `is_wait_callback` int(11) NOT NULL DEFAULT 0,
  `receiving_price_with_promocode` varchar(191) NOT NULL DEFAULT '0',
  `promo_code_value` varchar(191) DEFAULT NULL,
  `promo_code_discount_type` varchar(16) DEFAULT NULL,
  `promo_code_code` varchar(64) DEFAULT NULL,
  `method_request_payment` int(11) NOT NULL DEFAULT 0,
  `is_run_process` int(11) NOT NULL DEFAULT 0,
  `is_from_verification_card` int(11) NOT NULL DEFAULT 0,
  `is_from_identity_verification` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Заявка создана из сценария верификации личности (KYC)',
  `receiving_price_with_user_discount` varchar(191) NOT NULL DEFAULT '0',
  `receiving_price_user_discount` varchar(191) NOT NULL DEFAULT '0',
  `user_discount` varchar(191) NOT NULL DEFAULT '0',
  `is_pay_referral_bonus` int(11) NOT NULL DEFAULT 0,
  `course_float_fixed` varchar(191) DEFAULT NULL,
  `course_display_fixed` varchar(191) DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `transfer_to_account_type` varchar(191) DEFAULT NULL,
  `is_reserve_in_used` int(11) NOT NULL DEFAULT 0,
  `is_reserve_out_used` int(11) NOT NULL DEFAULT 0,
  `opt_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`opt_params`)),
  `type_rate` int(11) NOT NULL DEFAULT 0,
  `is_type_rate` int(11) NOT NULL DEFAULT 0,
  `floating_recount_stop` int(11) NOT NULL DEFAULT 0,
  `fix_recount_stop` int(11) NOT NULL DEFAULT 0,
  `id_who_completed` int(11) NOT NULL DEFAULT 0,
  `is_check_payment_merchant` int(11) NOT NULL DEFAULT 0,
  `type_requisite` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_card_details`
--

CREATE TABLE `tasks_card_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `card_number` varchar(191) DEFAULT NULL,
  `payment_system` varchar(191) DEFAULT NULL,
  `type_card` varchar(191) DEFAULT NULL,
  `brand_card` varchar(191) DEFAULT NULL,
  `country_name` varchar(191) DEFAULT NULL,
  `country_currency` varchar(191) DEFAULT NULL,
  `bank_name` varchar(191) DEFAULT NULL,
  `bank_url` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bank_phone` varchar(191) DEFAULT NULL,
  `type_column` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_chat`
--

CREATE TABLE `tasks_chat` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_client` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_check_images`
--

CREATE TABLE `tasks_check_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_order` int(11) NOT NULL DEFAULT 0,
  `image` varchar(191) DEFAULT NULL,
  `ip_address` varchar(191) DEFAULT NULL,
  `user_agent` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `mimetype` varchar(191) DEFAULT NULL,
  `size` varchar(191) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_comments`
--

CREATE TABLE `tasks_comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `message` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `class_style` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_comments_users`
--

CREATE TABLE `tasks_comments_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `message` text DEFAULT NULL,
  `class_style` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_fields`
--

CREATE TABLE `tasks_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `id_field` int(11) NOT NULL DEFAULT 0,
  `field_name` varchar(191) DEFAULT NULL,
  `field_value` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type_field` varchar(191) DEFAULT NULL,
  `alias` varchar(191) DEFAULT NULL,
  `field_key` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_files`
--

CREATE TABLE `tasks_files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `text` text DEFAULT NULL,
  `file` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_history_operators`
--

CREATE TABLE `tasks_history_operators` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` int(11) NOT NULL DEFAULT 0,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `id_status` int(11) NOT NULL DEFAULT 0,
  `in_amount` varchar(191) DEFAULT NULL,
  `out_amount` varchar(191) DEFAULT NULL,
  `course` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_from_manager` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_info`
--

CREATE TABLE `tasks_info` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `ip` varchar(191) DEFAULT NULL COMMENT 'IP адрес',
  `device` varchar(191) DEFAULT NULL COMMENT 'С какого устройства была создана заявка',
  `newbie` varchar(191) DEFAULT NULL COMMENT 'Определяем, новичек ли создал заявку',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `language` varchar(191) NOT NULL DEFAULT 'ru',
  `is_not_partner` int(11) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `in_min_amount` varchar(191) DEFAULT NULL,
  `in_max_amount` varchar(191) DEFAULT NULL,
  `is_freeze_scam` tinyint(1) NOT NULL DEFAULT 0,
  `num_transaction` varchar(191) DEFAULT NULL,
  `notify_statuses` int(11) NOT NULL DEFAULT 0,
  `note_tx` text DEFAULT NULL,
  `count_change_operator` int(11) NOT NULL DEFAULT 0,
  `is_pending` int(11) NOT NULL DEFAULT 0,
  `blockchain_confirm` int(11) NOT NULL DEFAULT 0,
  `blockchain_hash` varchar(191) DEFAULT NULL,
  `id_transaction_merchant` varchar(191) DEFAULT NULL,
  `id_transaction_pay` varchar(191) DEFAULT NULL,
  `city_name` varchar(191) DEFAULT NULL,
  `city_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_aml_analysis` int(11) NOT NULL DEFAULT 0,
  `is_wait_hash_pay` int(11) NOT NULL DEFAULT 0 COMMENT 'Включаем когда не выдается hash оплаты сразу',
  `recalculated_at` timestamp NULL DEFAULT NULL,
  `country_name` varchar(191) DEFAULT NULL,
  `is_blocked_chat` int(11) NOT NULL DEFAULT 0,
  `dot_not_remember_data` int(11) NOT NULL DEFAULT 0,
  `is_aml_high_risk` int(11) NOT NULL DEFAULT 0,
  `type_freeze_scam` varchar(191) DEFAULT NULL,
  `direction_city_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_messages`
--

CREATE TABLE `tasks_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type_user` int(11) NOT NULL DEFAULT 0,
  `is_view` int(11) NOT NULL DEFAULT 0,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `public_id` bigint(20) NOT NULL DEFAULT 0,
  `is_read` int(11) NOT NULL DEFAULT 0,
  `file_path` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_meta`
--

CREATE TABLE `tasks_meta` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `payment_status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `geo_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Гео-данные клиента (страна, регион, город и т.д.)' CHECK (json_valid(`geo_data`)),
  `telegram_id` varchar(191) DEFAULT NULL,
  `telegram_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`telegram_data`)),
  `selected_fee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `direction_selected_fee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `checkbox_agreements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checkbox_agreements`)),
  `card_verification_required` tinyint(1) NOT NULL DEFAULT 0,
  `card_verification_type` tinyint(4) NOT NULL DEFAULT 0,
  `identity_verification_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Флаг: требуется ли верификация личности в момент создания заявки',
  `identity_verification_type` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Тип верификации личности: 0=валюта, 1=направление, 2=индивидуальные правила',
  `selected_fee_type` varchar(191) DEFAULT NULL,
  `selected_fees` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`selected_fees`)),
  `merchant_network_code` varchar(191) DEFAULT NULL,
  `user_agent` varchar(191) DEFAULT NULL,
  `device_type` varchar(20) DEFAULT NULL COMMENT 'Тип устройства пользователя: mobile / desktop / tablet (снимок на момент создания заявки)',
  `user_flag` varchar(191) DEFAULT NULL,
  `freeze_scam` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Данные блокировки заявки (freeze_scam): тип, причины, дата' CHECK (json_valid(`freeze_scam`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_operators`
--

CREATE TABLE `tasks_operators` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_operators_logs`
--

CREATE TABLE `tasks_operators_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_old_operator` int(11) NOT NULL DEFAULT 0,
  `id_operator` int(11) NOT NULL DEFAULT 0,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_profits`
--

CREATE TABLE `tasks_profits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `profit_percent` double NOT NULL DEFAULT 0,
  `profit_currency` double NOT NULL DEFAULT 0,
  `profit_usd` double(8,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_rates_data`
--

CREATE TABLE `tasks_rates_data` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `exchange_course` varchar(191) NOT NULL,
  `market_course` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_rejection_status`
--

CREATE TABLE `tasks_rejection_status` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_not_delete` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tasks_rejection_status`
--

INSERT INTO `tasks_rejection_status` (`id`, `name`, `created_at`, `updated_at`, `is_not_delete`) VALUES
(1, '{\"ru\":\"Платёж не поступил\"}', NULL, '2024-07-29 06:24:24', 1),
(2, '{\"ru\":\"Возврат\"}', NULL, '2024-07-29 06:24:24', 1),
(3, '{\"ru\":\"Повторная заявка (дубликат)\"}', NULL, '2024-07-29 06:24:24', 1),
(4, '{\"ru\":\"Ваша заявка признана мошеннической\"}', NULL, '2024-07-29 06:24:24', 1),
(5, '{\"ru\":\"Мошенник из черного списка Bestchange\"}', '2019-10-11 17:14:18', '2024-07-29 06:24:24', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_requisites`
--

CREATE TABLE `tasks_requisites` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(191) DEFAULT NULL,
  `account_number` varchar(191) DEFAULT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `id_direction_exchange` int(11) NOT NULL DEFAULT 0,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_requisites_attached`
--

CREATE TABLE `tasks_requisites_attached` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) NOT NULL DEFAULT 0,
  `id_manager` bigint(20) NOT NULL DEFAULT 0,
  `wallet_number` varchar(191) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `ext_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ext_params`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_requisites_manual_data`
--

CREATE TABLE `tasks_requisites_manual_data` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_requisites` int(11) NOT NULL DEFAULT 0,
  `id_task` bigint(20) NOT NULL DEFAULT 0,
  `account_number` varchar(191) DEFAULT NULL,
  `ext_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ext_params`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_shots`
--

CREATE TABLE `tasks_shots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `account` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_status`
--

CREATE TABLE `tasks_status` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `color` varchar(191) DEFAULT NULL,
  `class` varchar(191) DEFAULT NULL,
  `is_export` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  `allow_delete` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tasks_status`
--

INSERT INTO `tasks_status` (`id`, `name`, `color`, `class`, `is_export`, `updated_at`, `allow_delete`, `sorting`) VALUES
(1, '{\"ru\":\"Время истекло\"}', '#f44336', 'st-delete', 0, '2024-06-28 08:10:13', 1, 3),
(2, '{\"ru\":\"Ожидается оплата\"}', '#7b93a3', 'st-pending-payment', 0, '2024-06-28 08:10:13', 1, 0),
(3, '{\"ru\":\"Ожидает обработки\"}', '#2196f3', 'st-handler', 0, '2024-06-28 08:10:13', 0, 1),
(4, '{\"ru\":\"Заявка исполнена\"}', '#4caf50', 'st-success', 1, '2024-06-28 08:10:13', 0, 4),
(5, '{\"ru\":\"Заявка отклонена\"}', '#f44336', 'st-delete', 0, '2024-06-28 08:10:13', 1, 5),
(6, '{\"ru\":\"Заявка отменена пользователем\"}', '#333', 'st-cancel', 0, '2024-06-28 08:10:13', 1, 6),
(7, '{\"ru\":\"Оплаченная заявка\"}', '#af4c88', 'st-merchant', 0, '2024-06-28 08:10:13', 0, 2),
(8, '{\"ru\":\"Отложенная заявка\"}', '#b2ba0f', 'st-frozen', 0, '2024-06-28 08:10:13', 0, 7),
(9, '{\"ru\":\"В процессе оплаты\"}', '#ab804b', 'st-process-payment', 0, '2024-06-28 08:10:13', 0, 8),
(10, '{\"ru\":\"Недействительна\"}', '#a70000', 'st-error', 0, '2024-06-28 08:10:13', 0, 9),
(11, '{\"ru\":\"Заявка удалена\"}', '#a70000', 'st-error', 0, '2024-06-28 08:10:13', 0, 10),
(12, '{\"ru\":\"Проверка оплаты\"}', '#fffae0', 'st-warning', 0, '2024-08-03 19:35:41', 0, 11),
(13, '{\"ru\":\"Подтверждение от мерчанта\"}', '#fffae0', 'st-merchant-waiting', 0, '2024-08-03 19:35:41', 0, 12),
(14, '{\"ru\":\"Ошибка авто-выплаты\"}', '#a70000', 'st-error-light', 0, '2024-08-03 19:35:41', 0, 13),
(15, '{\"ru\":\"Выплата в процессе\"}', '#a70000', NULL, 0, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `tasks_status_log`
--

CREATE TABLE `tasks_status_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `id_direction_exchange` int(11) DEFAULT NULL,
  `old_status` int(11) NOT NULL DEFAULT 0,
  `new_status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `in_price` varchar(191) DEFAULT NULL,
  `in_amount` decimal(30,18) DEFAULT NULL COMMENT 'Числовая сумма отдаю (snapshot)',
  `out_price` varchar(191) DEFAULT NULL,
  `out_amount` decimal(30,18) DEFAULT NULL COMMENT 'Числовая сумма получаю (snapshot)',
  `place_change` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=site, 1=admin, 2=merchant, 3=system',
  `course_display` varchar(191) DEFAULT NULL,
  `course_display_text` varchar(255) DEFAULT NULL COMMENT 'Курс строкой для UI, например "87.12 RUB = 1 USDT"',
  `course_display_rate` decimal(30,18) DEFAULT NULL COMMENT 'Курс числом (показанный пользователю)',
  `course_float_rate` decimal(30,18) DEFAULT NULL COMMENT 'Курс числом (плавающий/реальный)',
  `course_diff_percent` decimal(10,6) DEFAULT NULL COMMENT 'Процент отличия course_display_rate от course_float_rate'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `task_check_payment_status_logs`
--

CREATE TABLE `task_check_payment_status_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `old_status` tinyint(3) UNSIGNED NOT NULL,
  `new_status` tinyint(3) UNSIGNED NOT NULL,
  `description` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `task_extra_outs`
--

CREATE TABLE `task_extra_outs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `amount` varchar(191) NOT NULL DEFAULT '0',
  `position` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `task_log`
--

CREATE TABLE `task_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL,
  `text` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `task_single_log_confirm`
--

CREATE TABLE `task_single_log_confirm` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `needed_confirm` int(11) NOT NULL DEFAULT 0,
  `received_confirm` int(11) NOT NULL DEFAULT 0,
  `transaction_id` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `telegram_notifications`
--

CREATE TABLE `telegram_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `token_access` varchar(191) DEFAULT NULL,
  `id_channel` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `ext_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ext_params`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `channel_name` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `telescope_entries`
--

CREATE TABLE `telescope_entries` (
  `sequence` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL,
  `batch_id` char(36) NOT NULL,
  `family_hash` varchar(191) DEFAULT NULL,
  `should_display_on_index` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(20) NOT NULL,
  `content` longtext NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `telescope_entries_tags`
--

CREATE TABLE `telescope_entries_tags` (
  `entry_uuid` char(36) NOT NULL,
  `tag` varchar(191) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `telescope_monitoring`
--

CREATE TABLE `telescope_monitoring` (
  `tag` varchar(191) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `unpaid_items`
--

CREATE TABLE `unpaid_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `auto_delete` int(11) NOT NULL DEFAULT 0,
  `time_day` int(11) NOT NULL DEFAULT 0,
  `time_minute` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `order_status` varchar(191) DEFAULT NULL,
  `time_hour` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `unpaid_items`
--

INSERT INTO `unpaid_items` (`id`, `auto_delete`, `time_day`, `time_minute`, `created_at`, `updated_at`, `order_status`, `time_hour`) VALUES
(1, 1, 5, 0, NULL, '2019-11-14 14:20:13', '2', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `update_systems`
--

CREATE TABLE `update_systems` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `update_site_proxy_addr` varchar(191) DEFAULT NULL,
  `update_site_proxy_port` varchar(191) DEFAULT NULL,
  `update_site_proxy_user` varchar(191) DEFAULT NULL,
  `update_site_proxy_pass` varchar(191) DEFAULT NULL,
  `stable_versions_only` int(11) NOT NULL DEFAULT 1,
  `update_autocheck` int(11) NOT NULL DEFAULT 0,
  `update_stop_autocheck` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `update_versions`
--

CREATE TABLE `update_versions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `version` varchar(191) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `update_versions`
--

INSERT INTO `update_versions` (`id`, `version`, `applied_at`, `notes`) VALUES
(3, '9.2.2', '2025-04-21 10:52:21', NULL),
(4, '10.0.0', '2026-01-18 12:21:01', NULL),
(5, '10.0.1', '2026-01-18 12:21:01', NULL),
(6, '10.0.2', '2026-01-18 12:21:01', NULL),
(7, '10.0.3', '2026-01-18 12:21:01', NULL),
(8, '10.0.4', '2026-01-18 12:21:01', NULL),
(9, '10.0.4.1', '2026-01-18 12:21:01', NULL),
(10, '10.0.5', '2026-01-18 12:21:01', NULL),
(11, '10.0.6', '2026-01-18 12:21:01', NULL),
(12, '10.0.6.1', '2026-01-18 12:21:01', NULL),
(13, '10.0.7', '2026-01-18 12:21:01', NULL),
(14, '10.0.8', '2026-01-18 12:21:01', NULL),
(15, '10.0.8.1', '2026-01-18 12:21:01', NULL),
(16, '10.0.9', '2026-01-18 12:21:02', NULL),
(17, '10.0.9.1', '2026-01-18 12:21:02', NULL),
(18, '10.1.0', '2026-01-18 12:21:02', NULL),
(19, '10.1.1', '2026-01-18 12:21:02', NULL),
(20, '10.2.0', '2026-01-18 12:21:02', NULL),
(21, '10.2.1', '2026-01-18 12:21:02', NULL),
(22, '10.2.2', '2026-01-18 12:21:02', NULL),
(23, '10.3.0', '2026-01-18 12:21:04', NULL),
(24, '11.0.0', '2026-01-18 12:21:05', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `limit_profile_id` bigint(20) UNSIGNED DEFAULT NULL,
  `phone` varchar(191) DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) NOT NULL,
  `is_admin` int(11) NOT NULL DEFAULT 0,
  `is_root` int(11) NOT NULL DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT 'Последний вход',
  `last_logout_at` timestamp NULL DEFAULT NULL COMMENT 'Последний выход',
  `last_activity_at` timestamp NULL DEFAULT NULL COMMENT 'Последняя активность',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `referral_program_id` int(10) UNSIGNED DEFAULT NULL,
  `google2fa_secret` text DEFAULT NULL,
  `session_id` text DEFAULT NULL,
  `banned_at` timestamp NULL DEFAULT NULL,
  `language` varchar(191) DEFAULT NULL,
  `safe_input` int(11) NOT NULL DEFAULT 0,
  `username` varchar(191) DEFAULT NULL,
  `ip_address` varchar(191) DEFAULT NULL,
  `deactivation` int(11) NOT NULL DEFAULT 0,
  `is_order` int(11) NOT NULL DEFAULT 0,
  `auto_withdrawal` int(11) NOT NULL DEFAULT 0,
  `current_page` varchar(191) DEFAULT NULL,
  `is_unique_user` int(11) NOT NULL DEFAULT 0,
  `order_num` int(11) NOT NULL DEFAULT 0,
  `ip_changed` tinyint(1) DEFAULT NULL,
  `role_expired_at` timestamp NULL DEFAULT NULL,
  `is_follow_referral` tinyint(1) NOT NULL DEFAULT 0,
  `is_notify_email` int(11) NOT NULL DEFAULT 0,
  `is_password_reset` int(11) NOT NULL DEFAULT 0,
  `user_agent` varchar(191) DEFAULT NULL,
  `is_pay_referral` int(11) NOT NULL DEFAULT 0,
  `is_verification` int(11) NOT NULL DEFAULT 0,
  `num_auth` int(11) NOT NULL DEFAULT 0,
  `telegram` varchar(191) DEFAULT NULL,
  `provider` varchar(191) DEFAULT NULL,
  `provider_id` varchar(191) DEFAULT NULL,
  `is_download_codes` int(11) NOT NULL DEFAULT 0,
  `backup_code_secret` varchar(191) DEFAULT NULL,
  `phone_code` varchar(191) DEFAULT NULL,
  `admin_phone` varchar(191) DEFAULT NULL,
  `phone_hash` varchar(191) DEFAULT NULL,
  `user_browser` varchar(191) DEFAULT NULL,
  `user_device` varchar(191) DEFAULT NULL,
  `user_style` int(11) NOT NULL DEFAULT 0,
  `is_enabled_restapi` int(11) NOT NULL DEFAULT 0,
  `restapi_key` varchar(191) DEFAULT NULL,
  `is_guest` int(11) NOT NULL DEFAULT 0,
  `personal_discount` double NOT NULL DEFAULT 0,
  `personal_ref_discount` double NOT NULL DEFAULT 0,
  `max_ref_discount` double NOT NULL DEFAULT 0,
  `security_order_page_code` varchar(191) DEFAULT NULL,
  `is_enable_order_paginate` int(11) NOT NULL DEFAULT 0,
  `is_verify_account` int(11) NOT NULL DEFAULT 0,
  `is_hidden_ip_address` int(11) NOT NULL DEFAULT 0,
  `id_reward_program` int(11) NOT NULL DEFAULT 0,
  `order_total_exchanges` decimal(24,8) NOT NULL DEFAULT 0.00000000,
  `is_active_role` int(11) NOT NULL DEFAULT 0,
  `logged_ip_address` varchar(191) DEFAULT NULL COMMENT 'IP Адрес авторизованного пользователя',
  `allowed_ip_addresses` varchar(191) DEFAULT NULL COMMENT 'Разрешенные IP адреса',
  `is_frontend` int(11) NOT NULL DEFAULT 0,
  `partner_method` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `limit_profile_id`, `phone`, `name`, `email`, `email_verified_at`, `password`, `is_admin`, `is_root`, `last_login_at`, `last_logout_at`, `last_activity_at`, `remember_token`, `created_at`, `updated_at`, `referral_program_id`, `google2fa_secret`, `session_id`, `banned_at`, `language`, `safe_input`, `username`, `ip_address`, `deactivation`, `is_order`, `auto_withdrawal`, `current_page`, `is_unique_user`, `order_num`, `ip_changed`, `role_expired_at`, `is_follow_referral`, `is_notify_email`, `is_password_reset`, `user_agent`, `is_pay_referral`, `is_verification`, `num_auth`, `telegram`, `provider`, `provider_id`, `is_download_codes`, `backup_code_secret`, `phone_code`, `admin_phone`, `phone_hash`, `user_browser`, `user_device`, `user_style`, `is_enabled_restapi`, `restapi_key`, `is_guest`, `personal_discount`, `personal_ref_discount`, `max_ref_discount`, `security_order_page_code`, `is_enable_order_paginate`, `is_verify_account`, `is_hidden_ip_address`, `id_reward_program`, `order_total_exchanges`, `is_active_role`, `logged_ip_address`, `allowed_ip_addresses`, `is_frontend`, `partner_method`) VALUES
(1, NULL, NULL, 'admin', 'user@iexexchanger.com', NULL, '$2y$12$0C0yodDHDL2S8R5P4/rc2OR4K7XIk7DEOZUXwAFipzWflGQMlBYM6', 0, 0, '2026-01-18 12:33:56', '2023-04-09 11:26:28', '2026-01-18 13:29:00', 'w0HXvJtNF8yTvdJSvkDDm4PpzI8lRLhZhIHHJGxKkPfbfl69BdjoPLBPrSkB', '2019-11-28 22:23:46', '2026-01-18 13:29:00', NULL, '', 'LSsxqBdE9CICvabeuO9fC5BcQ1FRqmH3bRuzCpQ0', NULL, 'ru', 0, NULL, '87.200.44.224', 0, 0, 0, 'iexadmin/frontend-api/vue/getNotify', 0, 0, 0, NULL, 0, 1, 0, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 0, 0, 42, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, 'Chrome', 'desktop', 0, 0, NULL, 0, 0, 0, 0, NULL, 0, 0, 0, 1, 0.00000000, 0, '87.200.44.224', NULL, 0, 0),
(2, NULL, NULL, 'Guest', 'guest@test.com', NULL, '$2y$12$l2EzInIswkSO89/P.wCmuubgX7CiDZCahHTrVAN5NJviFUDbjnZPC', 0, 0, NULL, NULL, NULL, NULL, '2024-07-29 06:24:27', '2024-07-29 06:24:27', NULL, NULL, NULL, NULL, NULL, 0, NULL, '127.0.0.1', 0, 0, 0, NULL, 0, 0, NULL, NULL, 0, 0, 0, NULL, 0, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 1, 0, 0, 0, NULL, 0, 0, 0, 1, 0.00000000, 0, NULL, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `user_balance`
--

CREATE TABLE `user_balance` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL,
  `balance` float DEFAULT 0,
  `hold_balance` double NOT NULL DEFAULT 0,
  `id_code_currency` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `referral_total_profit` double NOT NULL DEFAULT 0,
  `referral_total_withdrawal` double NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_balance`
--

INSERT INTO `user_balance` (`id`, `id_user`, `balance`, `hold_balance`, `id_code_currency`, `created_at`, `updated_at`, `referral_total_profit`, `referral_total_withdrawal`) VALUES
(1, 1, 0, 0, 1, '2019-11-28 22:23:48', '2019-11-28 22:23:48', 0, 0),
(2, 2, 0, 0, 1, '2024-07-29 06:24:27', '2024-07-29 06:24:27', 0, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `user_balance_log`
--

CREATE TABLE `user_balance_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `type` int(11) NOT NULL DEFAULT 0,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `text` text DEFAULT NULL,
  `from_balance` varchar(191) DEFAULT NULL,
  `to_balance` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `route_type` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_global_stats_daily`
--

CREATE TABLE `user_global_stats_daily` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `total_users` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `new_users` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `active_users` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `users_with_orders` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `total_orders` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `successful_orders` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `failed_orders` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `canceled_orders` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `total_volume_usd` decimal(24,8) NOT NULL DEFAULT 0.00000000,
  `total_profit_usd` decimal(24,8) NOT NULL DEFAULT 0.00000000,
  `avg_orders_per_active_user` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `avg_volume_per_active_user` decimal(24,8) NOT NULL DEFAULT 0.00000000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_request_daily`
--

CREATE TABLE `user_request_daily` (
  `bucket_day` date NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `hits` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_seen` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_request_daily`
--

INSERT INTO `user_request_daily` (`bucket_day`, `user_id`, `hits`, `last_seen`, `created_at`, `updated_at`) VALUES
('2026-01-18', 1, 199, '2026-01-18 13:27:44', '2026-01-18 12:33:56', '2026-01-18 13:27:44');

-- --------------------------------------------------------

--
-- Структура таблицы `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_verification`
--

CREATE TABLE `user_verification` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `file_one` varchar(191) DEFAULT NULL,
  `file_two` varchar(191) DEFAULT NULL,
  `fio_user` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `hash_id` varchar(191) DEFAULT NULL,
  `ip_address` varchar(191) DEFAULT NULL,
  `user_agent` varchar(191) DEFAULT NULL,
  `file_one_preview` varchar(191) DEFAULT NULL,
  `file_two_preview` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_wallet_stories`
--

CREATE TABLE `user_wallet_stories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `wallet` longtext DEFAULT NULL,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `usage_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `verification_card`
--

CREATE TABLE `verification_card` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_order` int(11) NOT NULL DEFAULT 0,
  `card_number` varchar(191) DEFAULT NULL,
  `image` varchar(191) DEFAULT NULL,
  `is_verified` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `hash_id` varchar(191) DEFAULT NULL,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `name` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `ip_address` varchar(191) DEFAULT NULL,
  `user_agent` varchar(191) DEFAULT NULL,
  `card_number_string` varchar(191) DEFAULT NULL,
  `is_local_image` int(11) NOT NULL DEFAULT 0,
  `email` varchar(191) DEFAULT NULL,
  `text_message` text DEFAULT NULL,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `language` varchar(191) DEFAULT 'ru',
  `image_preview` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `verification_card_category`
--

CREATE TABLE `verification_card_category` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` text DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `verification_card_instructions`
--

CREATE TABLE `verification_card_instructions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_category` int(11) NOT NULL DEFAULT 0,
  `name` text DEFAULT NULL,
  `text` longtext DEFAULT NULL,
  `notice_text` longtext DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `sorting` int(11) NOT NULL DEFAULT 0,
  `image` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `visit_counters_hour`
--

CREATE TABLE `visit_counters_hour` (
  `bucket_hour` datetime NOT NULL,
  `total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `auth` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `guest` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `wallets_history`
--

CREATE TABLE `wallets_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `txid` text DEFAULT NULL COMMENT 'ID транзакции',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `amount` varchar(191) DEFAULT NULL,
  `txid` varchar(191) DEFAULT NULL,
  `address` varchar(191) DEFAULT NULL,
  `confirmations` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `whitebit_withdraw`
--

CREATE TABLE `whitebit_withdraw` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `method_type` varchar(191) DEFAULT NULL,
  `id_task` bigint(20) UNSIGNED NOT NULL,
  `address` varchar(191) DEFAULT NULL,
  `amount` varchar(191) DEFAULT NULL,
  `currency` varchar(191) DEFAULT NULL,
  `ticker` varchar(191) DEFAULT NULL,
  `fee` varchar(191) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `transaction_hash` varchar(191) DEFAULT NULL,
  `unique_id` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `withdrawal_request`
--

CREATE TABLE `withdrawal_request` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_manager` int(11) NOT NULL,
  `id_currency` int(11) NOT NULL,
  `referral` int(11) DEFAULT 0,
  `reward` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL,
  `score` varchar(191) DEFAULT NULL,
  `balance_referral` float DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `ip` varchar(191) DEFAULT NULL,
  `base_referral` float DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `tx_id` varchar(191) DEFAULT NULL,
  `big_id` bigint(20) NOT NULL DEFAULT 0,
  `is_black_list` int(11) NOT NULL DEFAULT 0,
  `black_list_text` varchar(191) DEFAULT NULL,
  `view_balance_referral` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `withdrawal_request_log`
--

CREATE TABLE `withdrawal_request_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_withdrawal_request` int(11) NOT NULL,
  `id_manager` int(11) NOT NULL DEFAULT 0,
  `text` varchar(191) NOT NULL,
  `amount` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remainder` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `withdrawal_wallets`
--

CREATE TABLE `withdrawal_wallets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_currency` int(11) NOT NULL DEFAULT 0,
  `wallet` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `admin_desktops`
--
ALTER TABLE `admin_desktops`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `admin_desktop_gadgets`
--
ALTER TABLE `admin_desktop_gadgets`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `admin_filter_header`
--
ALTER TABLE `admin_filter_header`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `advantage`
--
ALTER TABLE `advantage`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `aml_response_data`
--
ALTER TABLE `aml_response_data`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `aml_services`
--
ALTER TABLE `aml_services`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `api_logs`
--
ALTER TABLE `api_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `api_logs_api_token_index` (`api_token`),
  ADD KEY `api_logs_created_at_index` (`created_at`);

--
-- Индексы таблицы `applications_steps_logs`
--
ALTER TABLE `applications_steps_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applications_steps_logs_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `auth_audit_events`
--
ALTER TABLE `auth_audit_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_audit_user_created_idx` (`user_id`,`created_at`),
  ADD KEY `auth_audit_ip_created_idx` (`ip`,`created_at`),
  ADD KEY `auth_audit_event_created_idx` (`event`,`created_at`),
  ADD KEY `auth_audit_result_created_idx` (`result`,`created_at`),
  ADD KEY `auth_audit_events_user_id_index` (`user_id`),
  ADD KEY `auth_audit_events_email_index` (`email`),
  ADD KEY `auth_audit_events_guard_index` (`guard`),
  ADD KEY `auth_audit_events_channel_index` (`channel`),
  ADD KEY `auth_audit_events_event_index` (`event`),
  ADD KEY `auth_audit_events_result_index` (`result`),
  ADD KEY `auth_audit_events_reason_code_index` (`reason_code`),
  ADD KEY `auth_audit_events_ip_index` (`ip`),
  ADD KEY `auth_audit_events_device_id_index` (`device_id`),
  ADD KEY `auth_audit_events_is_new_device_index` (`is_new_device`),
  ADD KEY `auth_audit_events_country_index` (`country`),
  ADD KEY `auth_audit_events_city_index` (`city`),
  ADD KEY `auth_audit_events_iso_code_index` (`iso_code`),
  ADD KEY `auth_audit_events_session_id_index` (`session_id`);

--
-- Индексы таблицы `autosender_payment`
--
ALTER TABLE `autosender_payment`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `banned`
--
ALTER TABLE `banned`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `banned_filter_key_uniq` (`filter_key`),
  ADD KEY `banned_type_idx` (`type`),
  ADD KEY `banned_expired_at_idx` (`expired_at`),
  ADD KEY `banned_ip_range_idx` (`ip_from`,`ip_to`);

--
-- Индексы таблицы `banned_user`
--
ALTER TABLE `banned_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `banned_user_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `banners_buttons`
--
ALTER TABLE `banners_buttons`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `banners_has_banners_buttons`
--
ALTER TABLE `banners_has_banners_buttons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `banners_has_banners_buttons_banners_button_id_foreign` (`banners_button_id`),
  ADD KEY `banners_has_banners_buttons_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `bans`
--
ALTER TABLE `bans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bans_bannable_type_bannable_id_index` (`bannable_type`,`bannable_id`),
  ADD KEY `bans_created_by_type_created_by_id_index` (`created_by_type`,`created_by_id`),
  ADD KEY `bans_expired_at_index` (`expired_at`);

--
-- Индексы таблицы `bestchange_directions`
--
ALTER TABLE `bestchange_directions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bestchange_directions_is_favorite_index` (`is_favorite`),
  ADD KEY `idx_bestchange_directions_status_code` (`status`,`code`);

--
-- Индексы таблицы `bestchange_exchanger_cooldowns`
--
ALTER TABLE `bestchange_exchanger_cooldowns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_bc_exchanger_cooldown_changer` (`changer_id`),
  ADD KEY `bestchange_exchanger_cooldowns_changer_id_index` (`changer_id`),
  ADD KEY `bestchange_exchanger_cooldowns_blocked_until_index` (`blocked_until`);

--
-- Индексы таблицы `bestchange_exchanger_stats`
--
ALTER TABLE `bestchange_exchanger_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bestchange_exchanger_stats_changer_id_unique` (`changer_id`);

--
-- Индексы таблицы `bestchange_market_reports`
--
ALTER TABLE `bestchange_market_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_bc_market_report_day` (`day`),
  ADD KEY `bestchange_market_reports_day_index` (`day`);

--
-- Индексы таблицы `bestchange_parser_error`
--
ALTER TABLE `bestchange_parser_error`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `blacklist_order`
--
ALTER TABLE `blacklist_order`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `cache`
--
ALTER TABLE `cache`
  ADD UNIQUE KEY `cache_key_unique` (`key`);

--
-- Индексы таблицы `checkbox_agreements`
--
ALTER TABLE `checkbox_agreements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checkbox_agreements_page_type_page_id_index` (`page_type`,`page_id`);

--
-- Индексы таблицы `checkbox_agreement_direction_allowed`
--
ALTER TABLE `checkbox_agreement_direction_allowed`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `checkbox_direction_allowed_unique` (`checkbox_agreement_id`,`direction_exchange_id`),
  ADD KEY `dir_exchange_allowed_id_foreign` (`direction_exchange_id`);

--
-- Индексы таблицы `checkbox_agreement_direction_exchange`
--
ALTER TABLE `checkbox_agreement_direction_exchange`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `checkbox_direction_unique` (`checkbox_agreement_id`,`direction_exchange_id`),
  ADD KEY `dir_exchange_id_foreign` (`direction_exchange_id`);

--
-- Индексы таблицы `checks`
--
ALTER TABLE `checks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checks_host_id_foreign` (`host_id`);

--
-- Индексы таблицы `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `code_currency`
--
ALTER TABLE `code_currency`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `competitor_links`
--
ALTER TABLE `competitor_links`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `competitor_rates`
--
ALTER TABLE `competitor_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_competitor_rates_status_code` (`status`,`code`);

--
-- Индексы таблицы `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `contacts_groups`
--
ALTER TABLE `contacts_groups`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `contests`
--
ALTER TABLE `contests`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `contests_conditions`
--
ALTER TABLE `contests_conditions`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `contests_faq`
--
ALTER TABLE `contests_faq`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `contests_has_contests_users`
--
ALTER TABLE `contests_has_contests_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contests_has_contests_users_contests_user_id_foreign` (`contests_user_id`),
  ADD KEY `contests_has_contests_users_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `contests_users`
--
ALTER TABLE `contests_users`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `course_update_time_logs`
--
ALTER TABLE `course_update_time_logs`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `currencies_id_payment_index` (`id_payment`),
  ADD KEY `currencies_id_code_currency_index` (`id_code_currency`),
  ADD KEY `currencies_designation_xml_index` (`designation_xml`),
  ADD KEY `currencies_status_index` (`status`),
  ADD KEY `currencies_id_pay_index` (`id_pay`),
  ADD KEY `currencies_id_group_index` (`id_group`),
  ADD KEY `currencies_is_user_verification_index` (`is_user_verification`),
  ADD KEY `currencies_network_code_index` (`network_code`),
  ADD KEY `currencies_id_filter_currency_index` (`id_filter_currency`),
  ADD KEY `currencies_is_in_aml_check_tx_index` (`is_aml_check_tx`),
  ADD KEY `currencies_aml_service_index` (`id_aml_service`),
  ADD KEY `currencies_id_group_network_index` (`id_group_network`),
  ADD KEY `currencies_display_scan_qr_from_index` (`display_scan_qr_from`),
  ADD KEY `currencies_display_scan_qr_to_index` (`display_scan_qr_to`);

--
-- Индексы таблицы `currencies_analytics`
--
ALTER TABLE `currencies_analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `currencies_analytics_id_currency_unique` (`id_currency`);

--
-- Индексы таблицы `currencies_analytics_daily`
--
ALTER TABLE `currencies_analytics_daily`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `currencies_analytics_daily_date_currency_unique` (`date`,`id_currency`),
  ADD KEY `currencies_analytics_daily_date_index` (`date`),
  ADD KEY `currencies_analytics_daily_id_currency_index` (`id_currency`);

--
-- Индексы таблицы `currencies_commands`
--
ALTER TABLE `currencies_commands`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `currencies_groups`
--
ALTER TABLE `currencies_groups`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `currencies_groups_networks`
--
ALTER TABLE `currencies_groups_networks`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `currencies_info`
--
ALTER TABLE `currencies_info`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `currencies_labels`
--
ALTER TABLE `currencies_labels`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `currencies_notification`
--
ALTER TABLE `currencies_notification`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `currencies_templates`
--
ALTER TABLE `currencies_templates`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `currency_bin_bank_rules`
--
ALTER TABLE `currency_bin_bank_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `currency_bin_bank_rules_unique` (`currency_id`,`direction`,`mode`,`bank_name`),
  ADD KEY `currency_bin_bank_rules_idx` (`currency_id`,`direction`,`mode`);

--
-- Индексы таблицы `currency_fields`
--
ALTER TABLE `currency_fields`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `currency_filter`
--
ALTER TABLE `currency_filter`
  ADD UNIQUE KEY `currency_filter_unique` (`currency_id`,`filter_currency_id`),
  ADD KEY `currency_filter_filter_currency_id_fk` (`filter_currency_id`);

--
-- Индексы таблицы `currency_in_has_fields`
--
ALTER TABLE `currency_in_has_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `currency_in_has_fields_field_id_foreign` (`field_id`),
  ADD KEY `currency_in_has_fields_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `currency_label_currency`
--
ALTER TABLE `currency_label_currency`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_currency_label_side` (`currency_id`,`label_id`,`side`),
  ADD KEY `currency_label_currency_label_id_index` (`label_id`);

--
-- Индексы таблицы `currency_merchants`
--
ALTER TABLE `currency_merchants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `currency_merchants_gateway_merchant_id_foreign` (`gateway_merchant_id`),
  ADD KEY `currency_merchants_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `currency_out_has_fields`
--
ALTER TABLE `currency_out_has_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `currency_out_has_fields_field_id_foreign` (`field_id`),
  ADD KEY `currency_out_has_fields_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `currency_payments`
--
ALTER TABLE `currency_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `currency_payments_model_id_foreign` (`model_id`),
  ADD KEY `currency_payments_gateway_payment_id_foreign` (`gateway_payment_id`);

--
-- Индексы таблицы `currency_requisites_has_fields`
--
ALTER TABLE `currency_requisites_has_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `currency_requisites_has_fields_field_id_foreign` (`field_id`),
  ADD KEY `currency_requisites_has_fields_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `daily_profit_stats`
--
ALTER TABLE `daily_profit_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `daily_profit_stats_stat_date_direction_id_unique` (`stat_date`,`direction_id`),
  ADD KEY `daily_profit_stats_stat_date_index` (`stat_date`),
  ADD KEY `daily_profit_stats_direction_id_index` (`direction_id`);

--
-- Индексы таблицы `dashboard_user_widgets`
--
ALTER TABLE `dashboard_user_widgets`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `directions_city_profile_pivot`
--
ALTER TABLE `directions_city_profile_pivot`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `directions_city_profile_pivot_id_direction_exchange_city_unique` (`id_direction_exchange_city`),
  ADD KEY `directions_city_profile_pivot_direction_city_profile_id_index` (`direction_city_profile_id`);

--
-- Индексы таблицы `directions_fields`
--
ALTER TABLE `directions_fields`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `directions_has_allowed_countries`
--
ALTER TABLE `directions_has_allowed_countries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `directions_has_allowed_countries_geo_country_list_id_foreign` (`geo_country_list_id`),
  ADD KEY `directions_has_allowed_countries_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `directions_has_fields`
--
ALTER TABLE `directions_has_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `directions_has_fields_direction_field_id_foreign` (`direction_field_id`),
  ADD KEY `directions_has_fields_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `directions_has_forbidden_countries`
--
ALTER TABLE `directions_has_forbidden_countries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `directions_has_forbidden_countries_geo_country_list_id_foreign` (`geo_country_list_id`),
  ADD KEY `directions_has_forbidden_countries_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `directions_has_modes`
--
ALTER TABLE `directions_has_modes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `directions_has_modes_direction_exchange_mode_id_foreign` (`direction_exchange_mode_id`),
  ADD KEY `directions_has_modes_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `directions_has_requisites`
--
ALTER TABLE `directions_has_requisites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `directions_has_requisites_direction_requisite_id_foreign` (`direction_requisite_id`),
  ADD KEY `directions_has_requisites_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `direction_city_profiles`
--
ALTER TABLE `direction_city_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `direction_city_profiles_code_unique` (`code`),
  ADD KEY `direction_city_profiles_status_index` (`status`);

--
-- Индексы таблицы `direction_day`
--
ALTER TABLE `direction_day`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `direction_exchange`
--
ALTER TABLE `direction_exchange`
  ADD PRIMARY KEY (`id`),
  ADD KEY `direction_exchange_id_currency1_index` (`id_currency1`),
  ADD KEY `direction_exchange_id_currency2_index` (`id_currency2`),
  ADD KEY `direction_exchange_status_index` (`status`),
  ADD KEY `direction_exchange_is_holding_direction_index` (`is_holding_direction`),
  ADD KEY `direction_exchange_is_not_partner_index` (`is_not_partner`),
  ADD KEY `direction_exchange_allow_export_index` (`allow_export`),
  ADD KEY `direction_exchange_profit_index` (`profit`),
  ADD KEY `direction_exchange_profit_s_index` (`profit_s`),
  ADD KEY `direction_exchange_is_main_index` (`is_main`),
  ADD KEY `direction_exchange_id_crypto_parser_index` (`id_crypto_parser`),
  ADD KEY `direction_exchange_id_group_commission_index` (`id_group_commission`),
  ADD KEY `direction_exchange_is_error_rate_index` (`is_error_rate`),
  ADD KEY `direction_exchange_limit_profile_id_foreign` (`limit_profile_id`),
  ADD KEY `direction_exchange_profit_profile_id_foreign` (`profit_profile_id`);

--
-- Индексы таблицы `direction_exchange_cities`
--
ALTER TABLE `direction_exchange_cities`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `direction_exchange_error_log`
--
ALTER TABLE `direction_exchange_error_log`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `direction_exchange_groups`
--
ALTER TABLE `direction_exchange_groups`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `direction_exchange_group_pivot`
--
ALTER TABLE `direction_exchange_group_pivot`
  ADD PRIMARY KEY (`group_id`,`direction_exchange_id`),
  ADD KEY `direction_exchange_group_pivot_direction_exchange_id_foreign` (`direction_exchange_id`);

--
-- Индексы таблицы `direction_exchange_merchants`
--
ALTER TABLE `direction_exchange_merchants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `direction_exchange_merchants_model_id_foreign` (`model_id`),
  ADD KEY `direction_exchange_merchants_gateway_merchant_id_foreign` (`gateway_merchant_id`);

--
-- Индексы таблицы `direction_exchange_min_price_logs`
--
ALTER TABLE `direction_exchange_min_price_logs`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `direction_exchange_modes`
--
ALTER TABLE `direction_exchange_modes`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `direction_exchange_pay`
--
ALTER TABLE `direction_exchange_pay`
  ADD PRIMARY KEY (`id`),
  ADD KEY `direction_exchange_pay_model_id_foreign` (`model_id`),
  ADD KEY `direction_exchange_pay_gateway_pay_id_foreign` (`gateway_pay_id`);

--
-- Индексы таблицы `direction_exchange_percent_amount`
--
ALTER TABLE `direction_exchange_percent_amount`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `direction_exchange_selector_fee`
--
ALTER TABLE `direction_exchange_selector_fee`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_desf_direction` (`id_direction_exchange`),
  ADD KEY `idx_desf_direction_id` (`id_direction_exchange`,`id`);

--
-- Индексы таблицы `direction_exchange_stats_daily`
--
ALTER TABLE `direction_exchange_stats_daily`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `direction_exchange_stats_daily_date_direction_unique` (`stat_date`,`direction_exchange_id`),
  ADD KEY `direction_exchange_stats_daily_stat_date_index` (`stat_date`),
  ADD KEY `direction_exchange_stats_daily_direction_exchange_id_index` (`direction_exchange_id`);

--
-- Индексы таблицы `direction_notification`
--
ALTER TABLE `direction_notification`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `direction_requisites`
--
ALTER TABLE `direction_requisites`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `direction_templates`
--
ALTER TABLE `direction_templates`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `dynamic_config_locks`
--
ALTER TABLE `dynamic_config_locks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dyn_cfg_locks_scope_key_unique` (`scope_type`,`scope_id`,`key`),
  ADD KEY `dyn_cfg_locks_scope_index` (`scope_type`,`scope_id`);

--
-- Индексы таблицы `dynamic_config_migrations`
--
ALTER TABLE `dynamic_config_migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dyn_cfg_migrations_unique` (`migration`,`scope_type`,`scope_id`),
  ADD KEY `dyn_cfg_migrations_scope_index` (`scope_type`,`scope_id`);

--
-- Индексы таблицы `dynamic_config_settings`
--
ALTER TABLE `dynamic_config_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dyn_cfg_scope_key_unique` (`scope_type`,`scope_id`,`key`),
  ADD KEY `dyn_cfg_scope_index` (`scope_type`,`scope_id`);

--
-- Индексы таблицы `dynamic_config_snapshots`
--
ALTER TABLE `dynamic_config_snapshots`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `dynamic_config_versions`
--
ALTER TABLE `dynamic_config_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dyn_cfg_versions_scope_key_index` (`scope_type`,`scope_id`,`key`);

--
-- Индексы таблицы `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `event_employees`
--
ALTER TABLE `event_employees`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `export_data`
--
ALTER TABLE `export_data`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `export_rates_files`
--
ALTER TABLE `export_rates_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `export_rates_files_filename_index` (`filename`),
  ADD KEY `export_rates_files_is_offline_operator_index` (`is_offline_operator`);

--
-- Индексы таблицы `extra_out_profiles`
--
ALTER TABLE `extra_out_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `extra_out_profiles_is_enabled_index` (`is_enabled`),
  ADD KEY `extra_out_profiles_min_trigger_amount_index` (`min_trigger_amount`);

--
-- Индексы таблицы `extra_out_profile_currencies`
--
ALTER TABLE `extra_out_profile_currencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `extra_out_profile_currencies_profile_id_currency_id_unique` (`profile_id`,`currency_id`),
  ADD KEY `extra_out_profile_currencies_currency_id_foreign` (`currency_id`);

--
-- Индексы таблицы `e_voucher_codes`
--
ALTER TABLE `e_voucher_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `e_voucher_codes_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Индексы таблицы `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `faq_category`
--
ALTER TABLE `faq_category`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `favorites_links`
--
ALTER TABLE `favorites_links`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `file_parser_groups`
--
ALTER TABLE `file_parser_groups`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `file_parser_rates`
--
ALTER TABLE `file_parser_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_file_parser_rates_status_code` (`status`,`code`);

--
-- Индексы таблицы `filter_currency`
--
ALTER TABLE `filter_currency`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `fine_employees`
--
ALTER TABLE `fine_employees`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `firewall`
--
ALTER TABLE `firewall`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `firewall_ip_address_unique` (`ip_address`);

--
-- Индексы таблицы `gateways_merchants`
--
ALTER TABLE `gateways_merchants`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `gateways_payments`
--
ALTER TABLE `gateways_payments`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `gateway_health_statuses`
--
ALTER TABLE `gateway_health_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gateway_health_statuses_type_entity_id_unique` (`type`,`entity_id`),
  ADD KEY `gateway_health_statuses_type_index` (`type`),
  ADD KEY `gateway_health_statuses_entity_id_index` (`entity_id`),
  ADD KEY `gateway_health_statuses_gateway_alias_index` (`gateway_alias`),
  ADD KEY `gateway_health_statuses_status_index` (`status`);

--
-- Индексы таблицы `gateway_replays`
--
ALTER TABLE `gateway_replays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gateway_replays_replay_key_unique` (`replay_key`),
  ADD KEY `gateway_replays_gateway_index` (`gateway`),
  ADD KEY `gateway_replays_operation_index` (`operation`),
  ADD KEY `gateway_replays_direction_index` (`direction`);

--
-- Индексы таблицы `gateway_secret_access_logs`
--
ALTER TABLE `gateway_secret_access_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gateway_secret_access_logs_user_id_index` (`user_id`),
  ADD KEY `gateway_secret_access_logs_scope_index` (`scope`),
  ADD KEY `gateway_secret_access_logs_action_index` (`action`);

--
-- Индексы таблицы `geo_country_list`
--
ALTER TABLE `geo_country_list`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `getblock_requests`
--
ALTER TABLE `getblock_requests`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `group_commission`
--
ALTER TABLE `group_commission`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `group_commission_direction_exchange`
--
ALTER TABLE `group_commission_direction_exchange`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_direction_unique` (`group_commission_id`,`direction_exchange_id`),
  ADD KEY `group_commission_directions_id_foreign` (`direction_exchange_id`);

--
-- Индексы таблицы `group_parser_exchange`
--
ALTER TABLE `group_parser_exchange`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `health_checks`
--
ALTER TABLE `health_checks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `health_checks_resource_slug_index` (`resource_slug`),
  ADD KEY `health_checks_target_slug_index` (`target_slug`),
  ADD KEY `health_checks_created_at_index` (`created_at`);

--
-- Индексы таблицы `histories_codes`
--
ALTER TABLE `histories_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `histories_codes_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `histories_updated_data`
--
ALTER TABLE `histories_updated_data`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `history_excode`
--
ALTER TABLE `history_excode`
  ADD PRIMARY KEY (`id`),
  ADD KEY `history_excode_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `history_fields`
--
ALTER TABLE `history_fields`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `history_internal_accounts`
--
ALTER TABLE `history_internal_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `history_internal_accounts_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `history_payment_transactions`
--
ALTER TABLE `history_payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `history_payment_transactions_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `history_recalculation`
--
ALTER TABLE `history_recalculation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `history_recalculation_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `hosts`
--
ALTER TABLE `hosts`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `info_statistics`
--
ALTER TABLE `info_statistics`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `internal_accounts`
--
ALTER TABLE `internal_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Индексы таблицы `job_schedules`
--
ALTER TABLE `job_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_schedules_status_priority_index` (`status`,`priority`),
  ADD KEY `job_schedules_id_user_index` (`id_user`),
  ADD KEY `job_schedules_active_from_active_to_index` (`active_from`,`active_to`),
  ADD KEY `job_schedules_timezone_index` (`timezone`),
  ADD KEY `job_schedules_outside_policy_index` (`outside_policy`);

--
-- Индексы таблицы `kyc_logs`
--
ALTER TABLE `kyc_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kyc_logs_provider_index` (`provider`),
  ADD KEY `kyc_logs_event_index` (`event`),
  ADD KEY `kyc_logs_status_index` (`status`),
  ADD KEY `kyc_logs_user_id_index` (`user_id`),
  ADD KEY `kyc_logs_subject_external_id_index` (`subject_external_id`),
  ADD KEY `kyc_logs_stage_index` (`stage`),
  ADD KEY `kyc_logs_outcome_index` (`outcome`),
  ADD KEY `kyc_logs_occurred_at_index` (`occurred_at`);

--
-- Индексы таблицы `language_contents`
--
ALTER TABLE `language_contents`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `links_footers`
--
ALTER TABLE `links_footers`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `links_footer_groups`
--
ALTER TABLE `links_footer_groups`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `links_reviews`
--
ALTER TABLE `links_reviews`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `links_review_groups`
--
ALTER TABLE `links_review_groups`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `live_notification`
--
ALTER TABLE `live_notification`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `log_autopayments`
--
ALTER TABLE `log_autopayments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `log_autopayments_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `log_error_merchants`
--
ALTER TABLE `log_error_merchants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `log_error_merchants_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `log_parser_sources_errors`
--
ALTER TABLE `log_parser_sources_errors`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `ltm_translations`
--
ALTER TABLE `ltm_translations`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `merchants_transaction_data`
--
ALTER TABLE `merchants_transaction_data`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mtd_task` (`id_task`),
  ADD KEY `idx_mtd_merchant_time` (`id_merchant`,`created_at`),
  ADD KEY `idx_mtd_direction` (`id_direction_exchange`),
  ADD KEY `idx_mtd_service` (`service_name`);

--
-- Индексы таблицы `merchant_account`
--
ALTER TABLE `merchant_account`
  ADD PRIMARY KEY (`id`),
  ADD KEY `merchant_account_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `merchant_flow_events`
--
ALTER TABLE `merchant_flow_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `merchant_flow_events_task_id_index` (`task_id`),
  ADD KEY `merchant_flow_events_merchant_id_index` (`merchant_id`),
  ADD KEY `merchant_flow_events_gateway_alias_index` (`gateway_alias`),
  ADD KEY `merchant_flow_events_flow_index` (`flow`),
  ADD KEY `merchant_flow_events_stage_index` (`stage`),
  ADD KEY `merchant_flow_events_event_index` (`event`),
  ADD KEY `merchant_flow_events_level_index` (`level`),
  ADD KEY `merchant_flow_events_checkout_id_index` (`checkout_id`),
  ADD KEY `merchant_flow_events_external_id_index` (`external_id`);

--
-- Индексы таблицы `merchant_transaction_hash`
--
ALTER TABLE `merchant_transaction_hash`
  ADD PRIMARY KEY (`id`),
  ADD KEY `merchant_transaction_hash_transaction_hash_index` (`transaction_hash`),
  ADD KEY `merchant_transaction_hash_id_task_index` (`id_task`);

--
-- Индексы таблицы `merchant_transaction_ids`
--
ALTER TABLE `merchant_transaction_ids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `merchant_transaction_ids_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `merchant_transaction_webhooks`
--
ALTER TABLE `merchant_transaction_webhooks`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Индексы таблицы `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Индексы таблицы `monitors`
--
ALTER TABLE `monitors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `monitors_url_unique` (`url`);

--
-- Индексы таблицы `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`),
  ADD KEY `news_category_id_foreign` (`category_id`);

--
-- Индексы таблицы `news_categories`
--
ALTER TABLE `news_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `news_categories_name_unique` (`name`),
  ADD UNIQUE KEY `news_categories_slug_unique` (`slug`);

--
-- Индексы таблицы `notices_exchange`
--
ALTER TABLE `notices_exchange`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_id_notifiable_type_index` (`notifiable_id`,`notifiable_type`);

--
-- Индексы таблицы `notification_events`
--
ALTER TABLE `notification_events`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `oauth_access_tokens`
--
ALTER TABLE `oauth_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oauth_access_tokens_user_id_index` (`user_id`);

--
-- Индексы таблицы `oauth_auth_codes`
--
ALTER TABLE `oauth_auth_codes`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `oauth_clients`
--
ALTER TABLE `oauth_clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oauth_clients_user_id_index` (`user_id`);

--
-- Индексы таблицы `oauth_personal_access_clients`
--
ALTER TABLE `oauth_personal_access_clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oauth_personal_access_clients_client_id_index` (`client_id`);

--
-- Индексы таблицы `oauth_refresh_tokens`
--
ALTER TABLE `oauth_refresh_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oauth_refresh_tokens_access_token_id_index` (`access_token_id`);

--
-- Индексы таблицы `online_daily_stats`
--
ALTER TABLE `online_daily_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `online_daily_stats_day_unique` (`day`);

--
-- Индексы таблицы `online_hourly_stats`
--
ALTER TABLE `online_hourly_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `online_hourly_stats_hour_unique` (`hour`);

--
-- Индексы таблицы `online_sessions`
--
ALTER TABLE `online_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `online_sessions_identity_unique` (`identity`),
  ADD KEY `online_sessions_type_last_seen_index` (`type`,`last_seen`),
  ADD KEY `online_sessions_user_id_index` (`user_id`),
  ADD KEY `online_sessions_gid_index` (`gid`),
  ADD KEY `online_sessions_first_seen_index` (`first_seen`),
  ADD KEY `online_sessions_last_seen_index` (`last_seen`);

--
-- Индексы таблицы `operation_levels`
--
ALTER TABLE `operation_levels`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `operation_level_groups`
--
ALTER TABLE `operation_level_groups`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `order_exchange_stats_daily`
--
ALTER TABLE `order_exchange_stats_daily`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_exchange_stats_daily_date_unique` (`date`),
  ADD KEY `order_exchange_stats_daily_date_idx` (`date`);

--
-- Индексы таблицы `order_exchange_totals`
--
ALTER TABLE `order_exchange_totals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_exchange_totals_created_at_index` (`created_at`),
  ADD KEY `order_exchange_totals_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `order_exports`
--
ALTER TABLE `order_exports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_exports_user_id_foreign` (`user_id`);

--
-- Индексы таблицы `order_profit_results`
--
ALTER TABLE `order_profit_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_profit_results_task_id_unique` (`task_id`),
  ADD KEY `order_profit_results_task_id_index` (`task_id`);

--
-- Индексы таблицы `order_recount_aggregate_state`
--
ALTER TABLE `order_recount_aggregate_state`
  ADD PRIMARY KEY (`key`);

--
-- Индексы таблицы `order_recount_audit`
--
ALTER TABLE `order_recount_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ora_task_created_idx` (`task_id`,`created_at`),
  ADD KEY `order_recount_audit_task_id_index` (`task_id`),
  ADD KEY `order_recount_audit_policy_id_index` (`policy_id`);

--
-- Индексы таблицы `order_recount_daily_stats`
--
ALTER TABLE `order_recount_daily_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ord_daily_unique` (`day`,`trigger`,`decision`,`reason_code`,`scope_type`,`scope_id`),
  ADD KEY `ord_daily_day_idx` (`day`),
  ADD KEY `ord_daily_scope_idx` (`scope_type`,`scope_id`),
  ADD KEY `ord_daily_trigger_idx` (`trigger`),
  ADD KEY `ord_daily_decision_idx` (`decision`);

--
-- Индексы таблицы `order_recount_policies`
--
ALTER TABLE `order_recount_policies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orp_scope_idx` (`scope_type`,`scope_id`,`is_enabled`,`priority`);

--
-- Индексы таблицы `order_recount_states`
--
ALTER TABLE `order_recount_states`
  ADD PRIMARY KEY (`task_id`);

--
-- Индексы таблицы `order_steps`
--
ALTER TABLE `order_steps`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`page_id`),
  ADD UNIQUE KEY `pages_page_slug_unique` (`page_slug`),
  ADD UNIQUE KEY `pages_group_slug_unique` (`group_id`,`page_slug`);

--
-- Индексы таблицы `pages_static`
--
ALTER TABLE `pages_static`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `page_groups`
--
ALTER TABLE `page_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_groups_slug_unique` (`slug`);

--
-- Индексы таблицы `parser_api_keys`
--
ALTER TABLE `parser_api_keys`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `parser_exchange`
--
ALTER TABLE `parser_exchange`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parser_exchange_name_index` (`name`),
  ADD KEY `parser_exchange_id_group_index` (`id_group`),
  ADD KEY `parser_exchange_summa_index` (`summa`),
  ADD KEY `parser_exchange_status_index` (`status`),
  ADD KEY `parser_exchange_summa_default_index` (`summa_default`),
  ADD KEY `parser_exchange_type_index` (`type`),
  ADD KEY `idx_parser_exchange_status_code` (`status`,`code`);

--
-- Индексы таблицы `parser_formula_coefficient`
--
ALTER TABLE `parser_formula_coefficient`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parser_formula_coef_type_alias` (`type_index`,`alias`);

--
-- Индексы таблицы `parser_formula_rates`
--
ALTER TABLE `parser_formula_rates`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `parser_type`
--
ALTER TABLE `parser_type`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `partner_parser_groups`
--
ALTER TABLE `partner_parser_groups`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `partner_parser_rates`
--
ALTER TABLE `partner_parser_rates`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `password_resets`
--
ALTER TABLE `password_resets`
  ADD KEY `password_resets_email_index` (`email`);

--
-- Индексы таблицы `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Индексы таблицы `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `payment_explorer`
--
ALTER TABLE `payment_explorer`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `payment_gateway_logs`
--
ALTER TABLE `payment_gateway_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_gateway_logs_gateway_alias_operation_created_at_index` (`gateway_alias`,`operation`,`created_at`),
  ADD KEY `payment_gateway_logs_direction_created_at_index` (`direction`,`created_at`),
  ADD KEY `payment_gateway_logs_task_id_index` (`task_id`),
  ADD KEY `payment_gateway_logs_merchant_id_index` (`merchant_id`),
  ADD KEY `payment_gateway_logs_payment_id_index` (`payment_id`),
  ADD KEY `payment_gateway_logs_gateway_alias_index` (`gateway_alias`),
  ADD KEY `payment_gateway_logs_direction_index` (`direction`),
  ADD KEY `payment_gateway_logs_operation_index` (`operation`),
  ADD KEY `payment_gateway_logs_transaction_id_index` (`transaction_id`),
  ADD KEY `payment_gateway_logs_external_id_index` (`external_id`),
  ADD KEY `payment_gateway_logs_status_index` (`status`),
  ADD KEY `payment_gateway_logs_attempt_index` (`attempt`),
  ADD KEY `payment_gateway_logs_idempotency_key_index` (`idempotency_key`),
  ADD KEY `payment_gateway_logs_is_sandbox_index` (`is_sandbox`),
  ADD KEY `payment_gateway_logs_replay_key_index` (`replay_key`);

--
-- Индексы таблицы `payout_address`
--
ALTER TABLE `payout_address`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `pays_transaction_data`
--
ALTER TABLE `pays_transaction_data`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `pay_transaction_hash`
--
ALTER TABLE `pay_transaction_hash`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pay_transaction_hash_transaction_hash_index` (`transaction_hash`),
  ADD KEY `pay_transaction_hash_id_task_index` (`id_task`);

--
-- Индексы таблицы `pending_order_status`
--
ALTER TABLE `pending_order_status`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `permissions_group`
--
ALTER TABLE `permissions_group`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Индексы таблицы `profit_profiles`
--
ALTER TABLE `profit_profiles`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `project_settings`
--
ALTER TABLE `project_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_settings_group_name_unique` (`group`,`name`);

--
-- Индексы таблицы `promo_codes`
--
ALTER TABLE `promo_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `promo_codes_code_unique` (`code`);

--
-- Индексы таблицы `promo_code_directions`
--
ALTER TABLE `promo_code_directions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `promo_code_dir_mode_unique` (`promo_code_id`,`direction_exchange_id`,`mode`),
  ADD KEY `promo_code_directions_promo_code_id_mode_index` (`promo_code_id`,`mode`),
  ADD KEY `promo_code_directions_direction_exchange_id_mode_index` (`direction_exchange_id`,`mode`);

--
-- Индексы таблицы `proxies`
--
ALTER TABLE `proxies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proxies_status_auto_disabled_idx` (`status`,`auto_disabled_until`);

--
-- Индексы таблицы `proxy_health_logs`
--
ALTER TABLE `proxy_health_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proxy_health_logs_proxy_time_idx` (`proxy_id`,`created_at`),
  ADD KEY `proxy_health_logs_context_time_idx` (`context`,`created_at`),
  ADD KEY `proxy_health_logs_success_time_idx` (`success`,`created_at`);

--
-- Индексы таблицы `rates_history_logs`
--
ALTER TABLE `rates_history_logs`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `referrals_info_logs`
--
ALTER TABLE `referrals_info_logs`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `referral_audit_logs`
--
ALTER TABLE `referral_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `referral_audit_logs_event_created_at_index` (`event`,`created_at`),
  ADD KEY `referral_audit_logs_event_index` (`event`),
  ADD KEY `referral_audit_logs_level_index` (`level`),
  ADD KEY `referral_audit_logs_partner_user_id_index` (`partner_user_id`),
  ADD KEY `referral_audit_logs_client_user_id_index` (`client_user_id`),
  ADD KEY `referral_audit_logs_referral_link_id_index` (`referral_link_id`),
  ADD KEY `referral_audit_logs_referral_program_id_index` (`referral_program_id`),
  ADD KEY `referral_audit_logs_task_id_index` (`task_id`),
  ADD KEY `referral_audit_logs_trace_id_index` (`trace_id`);

--
-- Индексы таблицы `referral_links`
--
ALTER TABLE `referral_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referral_links_referral_program_id_user_id_unique` (`referral_program_id`,`user_id`),
  ADD KEY `referral_links_code_index` (`code`);

--
-- Индексы таблицы `referral_log`
--
ALTER TABLE `referral_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referral_log_event_key_unique` (`event_key`),
  ADD KEY `referral_log_id_task_foreign` (`id_task`),
  ADD KEY `referral_log_id_user_index` (`id_user`),
  ADD KEY `referral_log_id_referral_index` (`id_referral`),
  ADD KEY `referral_log_id_referral_link_index` (`id_referral_link`),
  ADD KEY `referral_log_created_at_index` (`created_at`),
  ADD KEY `referral_log_status_index` (`status`),
  ADD KEY `referral_log_available_at_index` (`available_at`),
  ADD KEY `referral_log_reversal_of_id_index` (`reversal_of_id`),
  ADD KEY `referral_log_id_task_index` (`id_task`),
  ADD KEY `referral_log_event_key_index` (`event_key`);

--
-- Индексы таблицы `referral_programs`
--
ALTER TABLE `referral_programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referral_programs_name_unique` (`name`);

--
-- Индексы таблицы `referral_relationships`
--
ALTER TABLE `referral_relationships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `referral_relationships_referral_link_id_foreign` (`referral_link_id`);

--
-- Индексы таблицы `referral_settings_code_audits`
--
ALTER TABLE `referral_settings_code_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `referral_settings_code_audits_actor_id_index` (`actor_id`),
  ADD KEY `referral_settings_code_audits_before_code_currency_id_index` (`before_code_currency_id`),
  ADD KEY `referral_settings_code_audits_after_code_currency_id_index` (`after_code_currency_id`);

--
-- Индексы таблицы `referral_statistics`
--
ALTER TABLE `referral_statistics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `referral_statistics_ref_hash_index` (`ref_hash`),
  ADD KEY `referral_statistics_created_at_index` (`created_at`),
  ADD KEY `referral_statistics_ref_hash_created_at_index` (`ref_hash`,`created_at`),
  ADD KEY `referral_statistics_id_user_index` (`id_user`);

--
-- Индексы таблицы `requirements_verification`
--
ALTER TABLE `requirements_verification`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `requisites`
--
ALTER TABLE `requisites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requisites_id_currency_index` (`id_currency`),
  ADD KEY `requisites_account_number_index` (`account_number`),
  ADD KEY `requisites_status_index` (`status`),
  ADD KEY `requisites_id_group_index` (`id_group`);

--
-- Индексы таблицы `requisites_fields`
--
ALTER TABLE `requisites_fields`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `requisites_group`
--
ALTER TABLE `requisites_group`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `requisites_has_fields`
--
ALTER TABLE `requisites_has_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requisites_has_fields_field_id_foreign` (`field_id`),
  ADD KEY `requisites_has_fields_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `requisites_has_info_fields`
--
ALTER TABLE `requisites_has_info_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requisites_has_info_fields_field_id_foreign` (`field_id`),
  ADD KEY `requisites_has_info_fields_model_id_foreign` (`model_id`);

--
-- Индексы таблицы `requisites_info_fields`
--
ALTER TABLE `requisites_info_fields`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `requisites_list`
--
ALTER TABLE `requisites_list`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `requisites_logs`
--
ALTER TABLE `requisites_logs`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `reserves`
--
ALTER TABLE `reserves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reserves_id_currency_index` (`id_currency`),
  ADD KEY `reserves_summa_index` (`summa`),
  ADD KEY `reserves_status_index` (`status`),
  ADD KEY `reserves_id_main_index` (`id_main`),
  ADD KEY `reserves_is_fixed_reserve_index` (`is_fixed_reserve`);

--
-- Индексы таблицы `reserves_files`
--
ALTER TABLE `reserves_files`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `reserves_files_groups`
--
ALTER TABLE `reserves_files_groups`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `reserves_logs`
--
ALTER TABLE `reserves_logs`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `reserves_manual_events`
--
ALTER TABLE `reserves_manual_events`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `reserve_total_snapshots`
--
ALTER TABLE `reserve_total_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reserve_total_snapshots_snapshot_at_index` (`snapshot_at`);

--
-- Индексы таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviews_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `reward_programs`
--
ALTER TABLE `reward_programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reward_programs_name_unique` (`name`);

--
-- Индексы таблицы `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Индексы таблицы `selector_fees`
--
ALTER TABLE `selector_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_selector_fees_status_sort` (`status`,`sorting`,`id`);

--
-- Индексы таблицы `selector_fee_direction_exchange`
--
ALTER TABLE `selector_fee_direction_exchange`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `selector_fee_direction_unique` (`selector_fee_id`,`direction_exchange_id`),
  ADD KEY `selector_fee_directions_id_foreign` (`direction_exchange_id`);

--
-- Индексы таблицы `sessions`
--
ALTER TABLE `sessions`
  ADD UNIQUE KEY `sessions_id_unique` (`id`);

--
-- Индексы таблицы `settings_limit_profiles`
--
ALTER TABLE `settings_limit_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `settings_limit_profiles_slug_unique` (`slug`);

--
-- Индексы таблицы `settings_referrals`
--
ALTER TABLE `settings_referrals`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `settings_theme`
--
ALTER TABLE `settings_theme`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `social_auth_system`
--
ALTER TABLE `social_auth_system`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `social_reviews`
--
ALTER TABLE `social_reviews`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `sumsub_ids`
--
ALTER TABLE `sumsub_ids`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sumsub_ids_applicant_id_unique` (`applicant_id`),
  ADD KEY `sumsub_ids_user_id_index` (`user_id`),
  ADD KEY `sumsub_ids_is_completed_index` (`is_completed`),
  ADD KEY `sumsub_ids_provider_index` (`provider`);

--
-- Индексы таблицы `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `syslogs_unique_active_only` (`active_unique_key`),
  ADD KEY `system_logs_entity_type_entity_id_occurred_at_index` (`entity_type`,`entity_id`,`occurred_at`),
  ADD KEY `system_logs_module_code_occurred_at_index` (`module`,`code`,`occurred_at`),
  ADD KEY `system_logs_module_index` (`module`),
  ADD KEY `system_logs_level_index` (`level`),
  ADD KEY `system_logs_entity_type_index` (`entity_type`),
  ADD KEY `system_logs_entity_id_index` (`entity_id`),
  ADD KEY `system_logs_code_index` (`code`),
  ADD KEY `system_logs_occurred_at_index` (`occurred_at`),
  ADD KEY `system_logs_dedup_hash_index` (`dedup_hash`),
  ADD KEY `system_logs_name_index` (`name`),
  ADD KEY `syslogs_key_lookup` (`module`,`name`,`code`,`entity_type`,`entity_id`,`occurred_at`,`id`),
  ADD KEY `system_logs_state_index` (`state`),
  ADD KEY `system_logs_module_name_code_entity_state_index` (`module`,`name`,`code`,`entity_type`,`entity_id`,`state`),
  ADD KEY `system_logs_first_seen_at_index` (`first_seen_at`),
  ADD KEY `system_logs_last_seen_at_index` (`last_seen_at`),
  ADD KEY `system_logs_resolved_at_index` (`resolved_at`),
  ADD KEY `system_logs_active_flag_index` (`active_flag`);

--
-- Индексы таблицы `tag_processor_custom_tags`
--
ALTER TABLE `tag_processor_custom_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag_processor_custom_tags_key_unique` (`key`),
  ADD KEY `tag_processor_custom_tags_group_index` (`group`),
  ADD KEY `tag_processor_custom_tags_type_index` (`type`),
  ADD KEY `tag_processor_custom_tags_scope_index` (`scope`),
  ADD KEY `tag_processor_custom_tags_is_active_index` (`is_active`),
  ADD KEY `tag_processor_custom_tags_created_by_index` (`created_by`),
  ADD KEY `tag_processor_custom_tags_updated_by_index` (`updated_by`);

--
-- Индексы таблицы `tag_processor_entity_custom_tags`
--
ALTER TABLE `tag_processor_entity_custom_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tp_entity_custom_tags_entity_key_unique` (`entity_type`,`entity_id`,`key`),
  ADD KEY `tp_entity_custom_tags_entity_index` (`entity_type`,`entity_id`),
  ADD KEY `tag_processor_entity_custom_tags_group_index` (`group`),
  ADD KEY `tag_processor_entity_custom_tags_type_index` (`type`),
  ADD KEY `tag_processor_entity_custom_tags_is_active_index` (`is_active`),
  ADD KEY `tag_processor_entity_custom_tags_created_by_index` (`created_by`),
  ADD KEY `tag_processor_entity_custom_tags_updated_by_index` (`updated_by`);

--
-- Индексы таблицы `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_status_index` (`status`),
  ADD KEY `tasks_id_user_index` (`id_user`),
  ADD KEY `tasks_ip_index` (`ip`),
  ADD KEY `tasks_give_price_index` (`give_price`),
  ADD KEY `tasks_receiving_price_index` (`receiving_price`),
  ADD KEY `tasks_id_direction_exchange_index` (`id_direction_exchange`),
  ADD KEY `tasks_from_shot_index` (`from_shot`),
  ADD KEY `tasks_to_shot_index` (`to_shot`),
  ADD KEY `tasks_public_id_index` (`public_id`),
  ADD KEY `tasks_transfer_to_account_index` (`transfer_to_account`),
  ADD KEY `tasks_telegram_id_index` (`telegram_id`),
  ADD KEY `tasks_email_index` (`email`),
  ADD KEY `tasks_is_archive_index` (`is_archive`),
  ADD KEY `tasks_id_payment_requisites_index` (`id_payment_requisites`),
  ADD KEY `tasks_id_wallets_addresses_index` (`id_wallets_addresses`),
  ADD KEY `tasks_is_frozen_index` (`is_frozen`),
  ADD KEY `tasks_register_tx_index` (`register_tx`),
  ADD KEY `tasks_phone_index` (`phone`),
  ADD KEY `tasks_is_spam_index` (`is_spam`),
  ADD KEY `tasks_referral_hash_index` (`referral_hash`),
  ADD KEY `tasks_requisites_receive_index` (`requisites_receive`),
  ADD KEY `tasks_id_promo_code_idx` (`id_promo_code`);

--
-- Индексы таблицы `tasks_card_details`
--
ALTER TABLE `tasks_card_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_card_details_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_chat`
--
ALTER TABLE `tasks_chat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_chat_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_check_images`
--
ALTER TABLE `tasks_check_images`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `tasks_comments`
--
ALTER TABLE `tasks_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_comments_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_comments_users`
--
ALTER TABLE `tasks_comments_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_comments_users_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_fields`
--
ALTER TABLE `tasks_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_fields_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_files`
--
ALTER TABLE `tasks_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_files_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_history_operators`
--
ALTER TABLE `tasks_history_operators`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_history_operators_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_info`
--
ALTER TABLE `tasks_info`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_info_city_name_index` (`city_name`),
  ADD KEY `tasks_info_country_name_index` (`country_name`),
  ADD KEY `tasks_info_is_aml_analysis_index` (`is_aml_analysis`),
  ADD KEY `tasks_info_newbie_index` (`newbie`),
  ADD KEY `tasks_info_recalculated_at_index` (`recalculated_at`),
  ADD KEY `tasks_info_device_index` (`device`),
  ADD KEY `tasks_info_language_index` (`language`),
  ADD KEY `tasks_info_id_task_index` (`id_task`);

--
-- Индексы таблицы `tasks_messages`
--
ALTER TABLE `tasks_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_messages_id_task_index` (`id_task`),
  ADD KEY `tasks_messages_user_id_index` (`user_id`);

--
-- Индексы таблицы `tasks_meta`
--
ALTER TABLE `tasks_meta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_meta_task_id_foreign` (`task_id`),
  ADD KEY `tasks_meta_payment_status_index` (`payment_status`),
  ADD KEY `tasks_meta_selected_fee_id_foreign` (`selected_fee_id`),
  ADD KEY `tasks_meta_direction_selected_fee_id_foreign` (`direction_selected_fee_id`);

--
-- Индексы таблицы `tasks_operators`
--
ALTER TABLE `tasks_operators`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_operators_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_operators_logs`
--
ALTER TABLE `tasks_operators_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_operators_logs_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_profits`
--
ALTER TABLE `tasks_profits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_profits_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_rates_data`
--
ALTER TABLE `tasks_rates_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_rates_data_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_rejection_status`
--
ALTER TABLE `tasks_rejection_status`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `tasks_requisites`
--
ALTER TABLE `tasks_requisites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_requisites_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_requisites_attached`
--
ALTER TABLE `tasks_requisites_attached`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task_requisites_attached_task` (`id_task`,`id`),
  ADD KEY `idx_task_requisites_attached_manager` (`id_manager`,`id`),
  ADD KEY `idx_task_requisites_attached_created_at` (`created_at`);

--
-- Индексы таблицы `tasks_requisites_manual_data`
--
ALTER TABLE `tasks_requisites_manual_data`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `tasks_shots`
--
ALTER TABLE `tasks_shots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_shots_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `tasks_status`
--
ALTER TABLE `tasks_status`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `tasks_status_log`
--
ALTER TABLE `tasks_status_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_status_log_id_task_foreign` (`id_task`),
  ADD KEY `tasks_status_log_task_created_idx` (`id_task`,`created_at`),
  ADD KEY `tasks_status_log_new_status_idx` (`new_status`),
  ADD KEY `tasks_status_log_user_id_idx` (`user_id`),
  ADD KEY `tasks_status_log_place_change_idx` (`place_change`),
  ADD KEY `tasks_status_log_direction_fk` (`id_direction_exchange`);

--
-- Индексы таблицы `task_check_payment_status_logs`
--
ALTER TABLE `task_check_payment_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_check_payment_status_logs_task_id_foreign` (`task_id`);

--
-- Индексы таблицы `task_extra_outs`
--
ALTER TABLE `task_extra_outs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_extra_outs_id_task_index` (`id_task`);

--
-- Индексы таблицы `task_log`
--
ALTER TABLE `task_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_log_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `task_single_log_confirm`
--
ALTER TABLE `task_single_log_confirm`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_single_log_confirm_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `telegram_notifications`
--
ALTER TABLE `telegram_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `telescope_entries`
--
ALTER TABLE `telescope_entries`
  ADD PRIMARY KEY (`sequence`),
  ADD UNIQUE KEY `telescope_entries_uuid_unique` (`uuid`),
  ADD KEY `telescope_entries_batch_id_index` (`batch_id`),
  ADD KEY `telescope_entries_type_should_display_on_index_index` (`type`,`should_display_on_index`),
  ADD KEY `telescope_entries_family_hash_index` (`family_hash`);

--
-- Индексы таблицы `telescope_entries_tags`
--
ALTER TABLE `telescope_entries_tags`
  ADD KEY `telescope_entries_tags_entry_uuid_tag_index` (`entry_uuid`,`tag`),
  ADD KEY `telescope_entries_tags_tag_index` (`tag`);

--
-- Индексы таблицы `unpaid_items`
--
ALTER TABLE `unpaid_items`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `update_systems`
--
ALTER TABLE `update_systems`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `update_versions`
--
ALTER TABLE `update_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `update_versions_version_unique` (`version`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_username_unique` (`username`),
  ADD KEY `users_referral_program_id_foreign` (`referral_program_id`),
  ADD KEY `users_email_index` (`email`),
  ADD KEY `users_auto_withdrawal_index` (`auto_withdrawal`),
  ADD KEY `users_provider_index` (`provider`),
  ADD KEY `users_last_activity_at_index` (`last_activity_at`),
  ADD KEY `users_is_unique_user_index` (`is_unique_user`),
  ADD KEY `users_email_verified_at_index` (`email_verified_at`),
  ADD KEY `users_deactivation_index` (`deactivation`),
  ADD KEY `users_is_order_index` (`is_order`),
  ADD KEY `users_name_index` (`name`),
  ADD KEY `users_ip_address_index` (`ip_address`),
  ADD KEY `users_created_at_index` (`created_at`),
  ADD KEY `users_last_login_at_index` (`last_login_at`),
  ADD KEY `users_last_logout_at_index` (`last_logout_at`),
  ADD KEY `users_order_num_index` (`order_num`),
  ADD KEY `users_limit_profile_id_foreign` (`limit_profile_id`);

--
-- Индексы таблицы `user_balance`
--
ALTER TABLE `user_balance`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `user_balance_log`
--
ALTER TABLE `user_balance_log`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `user_global_stats_daily`
--
ALTER TABLE `user_global_stats_daily`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_global_stats_daily_date_unique` (`date`);

--
-- Индексы таблицы `user_request_daily`
--
ALTER TABLE `user_request_daily`
  ADD PRIMARY KEY (`bucket_day`,`user_id`),
  ADD KEY `urd_day_idx` (`bucket_day`),
  ADD KEY `urd_user_idx` (`user_id`),
  ADD KEY `urd_updated_idx` (`updated_at`);

--
-- Индексы таблицы `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `user_verification`
--
ALTER TABLE `user_verification`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `user_wallet_stories`
--
ALTER TABLE `user_wallet_stories`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `verification_card`
--
ALTER TABLE `verification_card`
  ADD PRIMARY KEY (`id`),
  ADD KEY `verification_card_id_user_index` (`id_user`),
  ADD KEY `verification_card_id_order_index` (`id_order`),
  ADD KEY `verification_card_card_number_index` (`card_number`),
  ADD KEY `verification_card_status_index` (`status`);

--
-- Индексы таблицы `verification_card_category`
--
ALTER TABLE `verification_card_category`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `verification_card_instructions`
--
ALTER TABLE `verification_card_instructions`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `visit_counters_hour`
--
ALTER TABLE `visit_counters_hour`
  ADD PRIMARY KEY (`bucket_hour`),
  ADD KEY `vch_bucket_idx` (`bucket_hour`),
  ADD KEY `vch_updated_idx` (`updated_at`);

--
-- Индексы таблицы `wallets_history`
--
ALTER TABLE `wallets_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `wallets_history_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `wallet_transactions_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `whitebit_withdraw`
--
ALTER TABLE `whitebit_withdraw`
  ADD PRIMARY KEY (`id`),
  ADD KEY `whitebit_withdraw_id_task_foreign` (`id_task`);

--
-- Индексы таблицы `withdrawal_request`
--
ALTER TABLE `withdrawal_request`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `withdrawal_request_tx_id_unique` (`tx_id`);

--
-- Индексы таблицы `withdrawal_request_log`
--
ALTER TABLE `withdrawal_request_log`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `withdrawal_wallets`
--
ALTER TABLE `withdrawal_wallets`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `admin_desktops`
--
ALTER TABLE `admin_desktops`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `admin_desktop_gadgets`
--
ALTER TABLE `admin_desktop_gadgets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `admin_filter_header`
--
ALTER TABLE `admin_filter_header`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `advantage`
--
ALTER TABLE `advantage`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `aml_response_data`
--
ALTER TABLE `aml_response_data`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `aml_services`
--
ALTER TABLE `aml_services`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `api_logs`
--
ALTER TABLE `api_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `applications_steps_logs`
--
ALTER TABLE `applications_steps_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `auth_audit_events`
--
ALTER TABLE `auth_audit_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `autosender_payment`
--
ALTER TABLE `autosender_payment`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `banned`
--
ALTER TABLE `banned`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `banned_user`
--
ALTER TABLE `banned_user`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `banners`
--
ALTER TABLE `banners`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `banners_buttons`
--
ALTER TABLE `banners_buttons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `banners_has_banners_buttons`
--
ALTER TABLE `banners_has_banners_buttons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `bans`
--
ALTER TABLE `bans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `bestchange_directions`
--
ALTER TABLE `bestchange_directions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `bestchange_exchanger_cooldowns`
--
ALTER TABLE `bestchange_exchanger_cooldowns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `bestchange_exchanger_stats`
--
ALTER TABLE `bestchange_exchanger_stats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `bestchange_market_reports`
--
ALTER TABLE `bestchange_market_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `bestchange_parser_error`
--
ALTER TABLE `bestchange_parser_error`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `blacklist_order`
--
ALTER TABLE `blacklist_order`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `checkbox_agreements`
--
ALTER TABLE `checkbox_agreements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `checkbox_agreement_direction_allowed`
--
ALTER TABLE `checkbox_agreement_direction_allowed`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `checkbox_agreement_direction_exchange`
--
ALTER TABLE `checkbox_agreement_direction_exchange`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `checks`
--
ALTER TABLE `checks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `cities`
--
ALTER TABLE `cities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `code_currency`
--
ALTER TABLE `code_currency`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `competitor_links`
--
ALTER TABLE `competitor_links`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `competitor_rates`
--
ALTER TABLE `competitor_rates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `contacts_groups`
--
ALTER TABLE `contacts_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `contests`
--
ALTER TABLE `contests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `contests_conditions`
--
ALTER TABLE `contests_conditions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `contests_faq`
--
ALTER TABLE `contests_faq`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `contests_has_contests_users`
--
ALTER TABLE `contests_has_contests_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `contests_users`
--
ALTER TABLE `contests_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `course_update_time_logs`
--
ALTER TABLE `course_update_time_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currencies_analytics`
--
ALTER TABLE `currencies_analytics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currencies_analytics_daily`
--
ALTER TABLE `currencies_analytics_daily`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currencies_commands`
--
ALTER TABLE `currencies_commands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currencies_groups`
--
ALTER TABLE `currencies_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `currencies_groups_networks`
--
ALTER TABLE `currencies_groups_networks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currencies_info`
--
ALTER TABLE `currencies_info`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currencies_labels`
--
ALTER TABLE `currencies_labels`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currencies_notification`
--
ALTER TABLE `currencies_notification`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currencies_templates`
--
ALTER TABLE `currencies_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currency_bin_bank_rules`
--
ALTER TABLE `currency_bin_bank_rules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currency_fields`
--
ALTER TABLE `currency_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currency_in_has_fields`
--
ALTER TABLE `currency_in_has_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currency_label_currency`
--
ALTER TABLE `currency_label_currency`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currency_merchants`
--
ALTER TABLE `currency_merchants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currency_out_has_fields`
--
ALTER TABLE `currency_out_has_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currency_payments`
--
ALTER TABLE `currency_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `currency_requisites_has_fields`
--
ALTER TABLE `currency_requisites_has_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `daily_profit_stats`
--
ALTER TABLE `daily_profit_stats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `dashboard_user_widgets`
--
ALTER TABLE `dashboard_user_widgets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `directions_city_profile_pivot`
--
ALTER TABLE `directions_city_profile_pivot`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `directions_fields`
--
ALTER TABLE `directions_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `directions_has_allowed_countries`
--
ALTER TABLE `directions_has_allowed_countries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `directions_has_fields`
--
ALTER TABLE `directions_has_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `directions_has_forbidden_countries`
--
ALTER TABLE `directions_has_forbidden_countries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `directions_has_modes`
--
ALTER TABLE `directions_has_modes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `directions_has_requisites`
--
ALTER TABLE `directions_has_requisites`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_city_profiles`
--
ALTER TABLE `direction_city_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_day`
--
ALTER TABLE `direction_day`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_exchange`
--
ALTER TABLE `direction_exchange`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_exchange_cities`
--
ALTER TABLE `direction_exchange_cities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_exchange_error_log`
--
ALTER TABLE `direction_exchange_error_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_exchange_groups`
--
ALTER TABLE `direction_exchange_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_exchange_merchants`
--
ALTER TABLE `direction_exchange_merchants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_exchange_min_price_logs`
--
ALTER TABLE `direction_exchange_min_price_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_exchange_modes`
--
ALTER TABLE `direction_exchange_modes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_exchange_pay`
--
ALTER TABLE `direction_exchange_pay`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_exchange_percent_amount`
--
ALTER TABLE `direction_exchange_percent_amount`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_exchange_selector_fee`
--
ALTER TABLE `direction_exchange_selector_fee`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_exchange_stats_daily`
--
ALTER TABLE `direction_exchange_stats_daily`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_notification`
--
ALTER TABLE `direction_notification`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_requisites`
--
ALTER TABLE `direction_requisites`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `direction_templates`
--
ALTER TABLE `direction_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `dynamic_config_locks`
--
ALTER TABLE `dynamic_config_locks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `dynamic_config_migrations`
--
ALTER TABLE `dynamic_config_migrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `dynamic_config_settings`
--
ALTER TABLE `dynamic_config_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- AUTO_INCREMENT для таблицы `dynamic_config_snapshots`
--
ALTER TABLE `dynamic_config_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `dynamic_config_versions`
--
ALTER TABLE `dynamic_config_versions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `event_employees`
--
ALTER TABLE `event_employees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `export_data`
--
ALTER TABLE `export_data`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `export_rates_files`
--
ALTER TABLE `export_rates_files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `extra_out_profiles`
--
ALTER TABLE `extra_out_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `extra_out_profile_currencies`
--
ALTER TABLE `extra_out_profile_currencies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `e_voucher_codes`
--
ALTER TABLE `e_voucher_codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `faq`
--
ALTER TABLE `faq`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `faq_category`
--
ALTER TABLE `faq_category`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `favorites_links`
--
ALTER TABLE `favorites_links`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `file_parser_groups`
--
ALTER TABLE `file_parser_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `file_parser_rates`
--
ALTER TABLE `file_parser_rates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `filter_currency`
--
ALTER TABLE `filter_currency`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `fine_employees`
--
ALTER TABLE `fine_employees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `firewall`
--
ALTER TABLE `firewall`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `gateways_merchants`
--
ALTER TABLE `gateways_merchants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `gateways_payments`
--
ALTER TABLE `gateways_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `gateway_health_statuses`
--
ALTER TABLE `gateway_health_statuses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `gateway_replays`
--
ALTER TABLE `gateway_replays`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `gateway_secret_access_logs`
--
ALTER TABLE `gateway_secret_access_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `geo_country_list`
--
ALTER TABLE `geo_country_list`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=250;

--
-- AUTO_INCREMENT для таблицы `getblock_requests`
--
ALTER TABLE `getblock_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `group_commission`
--
ALTER TABLE `group_commission`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `group_commission_direction_exchange`
--
ALTER TABLE `group_commission_direction_exchange`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `group_parser_exchange`
--
ALTER TABLE `group_parser_exchange`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT для таблицы `health_checks`
--
ALTER TABLE `health_checks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `histories_codes`
--
ALTER TABLE `histories_codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `histories_updated_data`
--
ALTER TABLE `histories_updated_data`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT для таблицы `history_excode`
--
ALTER TABLE `history_excode`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `history_fields`
--
ALTER TABLE `history_fields`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `history_internal_accounts`
--
ALTER TABLE `history_internal_accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `history_payment_transactions`
--
ALTER TABLE `history_payment_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `history_recalculation`
--
ALTER TABLE `history_recalculation`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `hosts`
--
ALTER TABLE `hosts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `info_statistics`
--
ALTER TABLE `info_statistics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `internal_accounts`
--
ALTER TABLE `internal_accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `job_schedules`
--
ALTER TABLE `job_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `kyc_logs`
--
ALTER TABLE `kyc_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `language_contents`
--
ALTER TABLE `language_contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `links_footers`
--
ALTER TABLE `links_footers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `links_footer_groups`
--
ALTER TABLE `links_footer_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `links_reviews`
--
ALTER TABLE `links_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `links_review_groups`
--
ALTER TABLE `links_review_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `live_notification`
--
ALTER TABLE `live_notification`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `log_autopayments`
--
ALTER TABLE `log_autopayments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `log_error_merchants`
--
ALTER TABLE `log_error_merchants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `log_parser_sources_errors`
--
ALTER TABLE `log_parser_sources_errors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `ltm_translations`
--
ALTER TABLE `ltm_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `menu`
--
ALTER TABLE `menu`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `merchants_transaction_data`
--
ALTER TABLE `merchants_transaction_data`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `merchant_account`
--
ALTER TABLE `merchant_account`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `merchant_flow_events`
--
ALTER TABLE `merchant_flow_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `merchant_transaction_hash`
--
ALTER TABLE `merchant_transaction_hash`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `merchant_transaction_ids`
--
ALTER TABLE `merchant_transaction_ids`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `merchant_transaction_webhooks`
--
ALTER TABLE `merchant_transaction_webhooks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1621;

--
-- AUTO_INCREMENT для таблицы `monitors`
--
ALTER TABLE `monitors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `news_categories`
--
ALTER TABLE `news_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `notices_exchange`
--
ALTER TABLE `notices_exchange`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `notification_events`
--
ALTER TABLE `notification_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `oauth_clients`
--
ALTER TABLE `oauth_clients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `oauth_personal_access_clients`
--
ALTER TABLE `oauth_personal_access_clients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `online_daily_stats`
--
ALTER TABLE `online_daily_stats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `online_hourly_stats`
--
ALTER TABLE `online_hourly_stats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `online_sessions`
--
ALTER TABLE `online_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `operation_levels`
--
ALTER TABLE `operation_levels`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `operation_level_groups`
--
ALTER TABLE `operation_level_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_exchange_stats_daily`
--
ALTER TABLE `order_exchange_stats_daily`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_exchange_totals`
--
ALTER TABLE `order_exchange_totals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_exports`
--
ALTER TABLE `order_exports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_profit_results`
--
ALTER TABLE `order_profit_results`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_recount_audit`
--
ALTER TABLE `order_recount_audit`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_recount_daily_stats`
--
ALTER TABLE `order_recount_daily_stats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_recount_policies`
--
ALTER TABLE `order_recount_policies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_steps`
--
ALTER TABLE `order_steps`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `pages`
--
ALTER TABLE `pages`
  MODIFY `page_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `pages_static`
--
ALTER TABLE `pages_static`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `page_groups`
--
ALTER TABLE `page_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `parser_api_keys`
--
ALTER TABLE `parser_api_keys`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `parser_exchange`
--
ALTER TABLE `parser_exchange`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `parser_formula_coefficient`
--
ALTER TABLE `parser_formula_coefficient`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `parser_formula_rates`
--
ALTER TABLE `parser_formula_rates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `parser_type`
--
ALTER TABLE `parser_type`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `partners`
--
ALTER TABLE `partners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `partner_parser_groups`
--
ALTER TABLE `partner_parser_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `partner_parser_rates`
--
ALTER TABLE `partner_parser_rates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `payment_explorer`
--
ALTER TABLE `payment_explorer`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `payment_gateway_logs`
--
ALTER TABLE `payment_gateway_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `payout_address`
--
ALTER TABLE `payout_address`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `pays_transaction_data`
--
ALTER TABLE `pays_transaction_data`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `pay_transaction_hash`
--
ALTER TABLE `pay_transaction_hash`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `pending_order_status`
--
ALTER TABLE `pending_order_status`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT для таблицы `permissions_group`
--
ALTER TABLE `permissions_group`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `profit_profiles`
--
ALTER TABLE `profit_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `project_settings`
--
ALTER TABLE `project_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT для таблицы `promo_codes`
--
ALTER TABLE `promo_codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `promo_code_directions`
--
ALTER TABLE `promo_code_directions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `proxies`
--
ALTER TABLE `proxies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `proxy_health_logs`
--
ALTER TABLE `proxy_health_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `rates_history_logs`
--
ALTER TABLE `rates_history_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `referrals_info_logs`
--
ALTER TABLE `referrals_info_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `referral_audit_logs`
--
ALTER TABLE `referral_audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `referral_links`
--
ALTER TABLE `referral_links`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `referral_log`
--
ALTER TABLE `referral_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `referral_programs`
--
ALTER TABLE `referral_programs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `referral_relationships`
--
ALTER TABLE `referral_relationships`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `referral_settings_code_audits`
--
ALTER TABLE `referral_settings_code_audits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `referral_statistics`
--
ALTER TABLE `referral_statistics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `requirements_verification`
--
ALTER TABLE `requirements_verification`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `requisites`
--
ALTER TABLE `requisites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `requisites_fields`
--
ALTER TABLE `requisites_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `requisites_group`
--
ALTER TABLE `requisites_group`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `requisites_has_fields`
--
ALTER TABLE `requisites_has_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `requisites_has_info_fields`
--
ALTER TABLE `requisites_has_info_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `requisites_info_fields`
--
ALTER TABLE `requisites_info_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `requisites_list`
--
ALTER TABLE `requisites_list`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `requisites_logs`
--
ALTER TABLE `requisites_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `reserves`
--
ALTER TABLE `reserves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `reserves_files`
--
ALTER TABLE `reserves_files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `reserves_files_groups`
--
ALTER TABLE `reserves_files_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `reserves_logs`
--
ALTER TABLE `reserves_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `reserves_manual_events`
--
ALTER TABLE `reserves_manual_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `reserve_total_snapshots`
--
ALTER TABLE `reserve_total_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `reward_programs`
--
ALTER TABLE `reward_programs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `selector_fees`
--
ALTER TABLE `selector_fees`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `selector_fee_direction_exchange`
--
ALTER TABLE `selector_fee_direction_exchange`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `settings_limit_profiles`
--
ALTER TABLE `settings_limit_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `settings_referrals`
--
ALTER TABLE `settings_referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `settings_theme`
--
ALTER TABLE `settings_theme`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `social_auth_system`
--
ALTER TABLE `social_auth_system`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `social_reviews`
--
ALTER TABLE `social_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `sumsub_ids`
--
ALTER TABLE `sumsub_ids`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tag_processor_custom_tags`
--
ALTER TABLE `tag_processor_custom_tags`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tag_processor_entity_custom_tags`
--
ALTER TABLE `tag_processor_entity_custom_tags`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_card_details`
--
ALTER TABLE `tasks_card_details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_chat`
--
ALTER TABLE `tasks_chat`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_check_images`
--
ALTER TABLE `tasks_check_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_comments`
--
ALTER TABLE `tasks_comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_comments_users`
--
ALTER TABLE `tasks_comments_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_fields`
--
ALTER TABLE `tasks_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_files`
--
ALTER TABLE `tasks_files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_history_operators`
--
ALTER TABLE `tasks_history_operators`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_info`
--
ALTER TABLE `tasks_info`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_messages`
--
ALTER TABLE `tasks_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_meta`
--
ALTER TABLE `tasks_meta`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_operators`
--
ALTER TABLE `tasks_operators`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_operators_logs`
--
ALTER TABLE `tasks_operators_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_profits`
--
ALTER TABLE `tasks_profits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_rates_data`
--
ALTER TABLE `tasks_rates_data`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_rejection_status`
--
ALTER TABLE `tasks_rejection_status`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `tasks_requisites`
--
ALTER TABLE `tasks_requisites`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_requisites_attached`
--
ALTER TABLE `tasks_requisites_attached`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_requisites_manual_data`
--
ALTER TABLE `tasks_requisites_manual_data`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_shots`
--
ALTER TABLE `tasks_shots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks_status`
--
ALTER TABLE `tasks_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `tasks_status_log`
--
ALTER TABLE `tasks_status_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `task_check_payment_status_logs`
--
ALTER TABLE `task_check_payment_status_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `task_extra_outs`
--
ALTER TABLE `task_extra_outs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `task_log`
--
ALTER TABLE `task_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `task_single_log_confirm`
--
ALTER TABLE `task_single_log_confirm`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `telegram_notifications`
--
ALTER TABLE `telegram_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `telescope_entries`
--
ALTER TABLE `telescope_entries`
  MODIFY `sequence` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `unpaid_items`
--
ALTER TABLE `unpaid_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `update_systems`
--
ALTER TABLE `update_systems`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `update_versions`
--
ALTER TABLE `update_versions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `user_balance`
--
ALTER TABLE `user_balance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `user_balance_log`
--
ALTER TABLE `user_balance_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_global_stats_daily`
--
ALTER TABLE `user_global_stats_daily`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_verification`
--
ALTER TABLE `user_verification`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_wallet_stories`
--
ALTER TABLE `user_wallet_stories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `verification_card`
--
ALTER TABLE `verification_card`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `verification_card_category`
--
ALTER TABLE `verification_card_category`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `verification_card_instructions`
--
ALTER TABLE `verification_card_instructions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `wallets_history`
--
ALTER TABLE `wallets_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `whitebit_withdraw`
--
ALTER TABLE `whitebit_withdraw`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `withdrawal_request`
--
ALTER TABLE `withdrawal_request`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `withdrawal_request_log`
--
ALTER TABLE `withdrawal_request_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `withdrawal_wallets`
--
ALTER TABLE `withdrawal_wallets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `applications_steps_logs`
--
ALTER TABLE `applications_steps_logs`
  ADD CONSTRAINT `applications_steps_logs_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `banned_user`
--
ALTER TABLE `banned_user`
  ADD CONSTRAINT `banned_user_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `banners_has_banners_buttons`
--
ALTER TABLE `banners_has_banners_buttons`
  ADD CONSTRAINT `banners_has_banners_buttons_banners_button_id_foreign` FOREIGN KEY (`banners_button_id`) REFERENCES `banners_buttons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `banners_has_banners_buttons_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `banners` (`id`);

--
-- Ограничения внешнего ключа таблицы `checkbox_agreement_direction_allowed`
--
ALTER TABLE `checkbox_agreement_direction_allowed`
  ADD CONSTRAINT `chkbx_agreement_allowed_id_foreign` FOREIGN KEY (`checkbox_agreement_id`) REFERENCES `checkbox_agreements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dir_exchange_allowed_id_foreign` FOREIGN KEY (`direction_exchange_id`) REFERENCES `direction_exchange` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `checkbox_agreement_direction_exchange`
--
ALTER TABLE `checkbox_agreement_direction_exchange`
  ADD CONSTRAINT `chkbx_agreement_id_foreign` FOREIGN KEY (`checkbox_agreement_id`) REFERENCES `checkbox_agreements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dir_exchange_id_foreign` FOREIGN KEY (`direction_exchange_id`) REFERENCES `direction_exchange` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `checks`
--
ALTER TABLE `checks`
  ADD CONSTRAINT `checks_host_id_foreign` FOREIGN KEY (`host_id`) REFERENCES `hosts` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `contests_has_contests_users`
--
ALTER TABLE `contests_has_contests_users`
  ADD CONSTRAINT `contests_has_contests_users_contests_user_id_foreign` FOREIGN KEY (`contests_user_id`) REFERENCES `contests_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contests_has_contests_users_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `contests` (`id`);

--
-- Ограничения внешнего ключа таблицы `currency_bin_bank_rules`
--
ALTER TABLE `currency_bin_bank_rules`
  ADD CONSTRAINT `currency_bin_bank_rules_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `currency_filter`
--
ALTER TABLE `currency_filter`
  ADD CONSTRAINT `currency_filter_currency_id_fk` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `currency_filter_filter_currency_id_fk` FOREIGN KEY (`filter_currency_id`) REFERENCES `filter_currency` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `currency_in_has_fields`
--
ALTER TABLE `currency_in_has_fields`
  ADD CONSTRAINT `currency_in_has_fields_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `currency_fields` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `currency_in_has_fields_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `currencies` (`id`);

--
-- Ограничения внешнего ключа таблицы `currency_label_currency`
--
ALTER TABLE `currency_label_currency`
  ADD CONSTRAINT `currency_label_currency_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `currency_label_currency_label_id_foreign` FOREIGN KEY (`label_id`) REFERENCES `currencies_labels` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `currency_merchants`
--
ALTER TABLE `currency_merchants`
  ADD CONSTRAINT `currency_merchants_gateway_merchant_id_foreign` FOREIGN KEY (`gateway_merchant_id`) REFERENCES `gateways_merchants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `currency_merchants_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `currencies` (`id`);

--
-- Ограничения внешнего ключа таблицы `currency_out_has_fields`
--
ALTER TABLE `currency_out_has_fields`
  ADD CONSTRAINT `currency_out_has_fields_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `currency_fields` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `currency_out_has_fields_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `currencies` (`id`);

--
-- Ограничения внешнего ключа таблицы `currency_payments`
--
ALTER TABLE `currency_payments`
  ADD CONSTRAINT `currency_payments_gateway_payment_id_foreign` FOREIGN KEY (`gateway_payment_id`) REFERENCES `gateways_payments` (`id`),
  ADD CONSTRAINT `currency_payments_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `currencies` (`id`);

--
-- Ограничения внешнего ключа таблицы `currency_requisites_has_fields`
--
ALTER TABLE `currency_requisites_has_fields`
  ADD CONSTRAINT `currency_requisites_has_fields_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `requisites_fields` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `currency_requisites_has_fields_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `currencies` (`id`);

--
-- Ограничения внешнего ключа таблицы `directions_city_profile_pivot`
--
ALTER TABLE `directions_city_profile_pivot`
  ADD CONSTRAINT `dcp_pivot_dec_fk` FOREIGN KEY (`id_direction_exchange_city`) REFERENCES `direction_exchange_cities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `dcp_pivot_profile_fk` FOREIGN KEY (`direction_city_profile_id`) REFERENCES `direction_city_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `directions_has_allowed_countries`
--
ALTER TABLE `directions_has_allowed_countries`
  ADD CONSTRAINT `directions_has_allowed_countries_geo_country_list_id_foreign` FOREIGN KEY (`geo_country_list_id`) REFERENCES `geo_country_list` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `directions_has_allowed_countries_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`);

--
-- Ограничения внешнего ключа таблицы `directions_has_fields`
--
ALTER TABLE `directions_has_fields`
  ADD CONSTRAINT `directions_has_fields_direction_field_id_foreign` FOREIGN KEY (`direction_field_id`) REFERENCES `directions_fields` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `directions_has_fields_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`);

--
-- Ограничения внешнего ключа таблицы `directions_has_forbidden_countries`
--
ALTER TABLE `directions_has_forbidden_countries`
  ADD CONSTRAINT `directions_has_forbidden_countries_geo_country_list_id_foreign` FOREIGN KEY (`geo_country_list_id`) REFERENCES `geo_country_list` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `directions_has_forbidden_countries_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`);

--
-- Ограничения внешнего ключа таблицы `directions_has_modes`
--
ALTER TABLE `directions_has_modes`
  ADD CONSTRAINT `directions_has_modes_direction_exchange_mode_id_foreign` FOREIGN KEY (`direction_exchange_mode_id`) REFERENCES `direction_exchange_modes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `directions_has_modes_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`);

--
-- Ограничения внешнего ключа таблицы `directions_has_requisites`
--
ALTER TABLE `directions_has_requisites`
  ADD CONSTRAINT `directions_has_requisites_direction_requisite_id_foreign` FOREIGN KEY (`direction_requisite_id`) REFERENCES `direction_requisites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `directions_has_requisites_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`);

--
-- Ограничения внешнего ключа таблицы `direction_exchange`
--
ALTER TABLE `direction_exchange`
  ADD CONSTRAINT `direction_exchange_limit_profile_id_foreign` FOREIGN KEY (`limit_profile_id`) REFERENCES `settings_limit_profiles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `direction_exchange_profit_profile_id_foreign` FOREIGN KEY (`profit_profile_id`) REFERENCES `profit_profiles` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `direction_exchange_group_pivot`
--
ALTER TABLE `direction_exchange_group_pivot`
  ADD CONSTRAINT `direction_exchange_group_pivot_direction_exchange_id_foreign` FOREIGN KEY (`direction_exchange_id`) REFERENCES `direction_exchange` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `direction_exchange_group_pivot_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `direction_exchange_groups` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `direction_exchange_merchants`
--
ALTER TABLE `direction_exchange_merchants`
  ADD CONSTRAINT `direction_exchange_merchants_gateway_merchant_id_foreign` FOREIGN KEY (`gateway_merchant_id`) REFERENCES `gateways_merchants` (`id`),
  ADD CONSTRAINT `direction_exchange_merchants_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`);

--
-- Ограничения внешнего ключа таблицы `direction_exchange_pay`
--
ALTER TABLE `direction_exchange_pay`
  ADD CONSTRAINT `direction_exchange_pay_gateway_pay_id_foreign` FOREIGN KEY (`gateway_pay_id`) REFERENCES `gateways_payments` (`id`),
  ADD CONSTRAINT `direction_exchange_pay_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `direction_exchange` (`id`);

--
-- Ограничения внешнего ключа таблицы `extra_out_profile_currencies`
--
ALTER TABLE `extra_out_profile_currencies`
  ADD CONSTRAINT `extra_out_profile_currencies_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `extra_out_profile_currencies_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `extra_out_profiles` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `e_voucher_codes`
--
ALTER TABLE `e_voucher_codes`
  ADD CONSTRAINT `e_voucher_codes_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `group_commission_direction_exchange`
--
ALTER TABLE `group_commission_direction_exchange`
  ADD CONSTRAINT `group_commission_direction_exchange_group_commission_id_foreign` FOREIGN KEY (`group_commission_id`) REFERENCES `group_commission` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_commission_directions_id_foreign` FOREIGN KEY (`direction_exchange_id`) REFERENCES `direction_exchange` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `histories_codes`
--
ALTER TABLE `histories_codes`
  ADD CONSTRAINT `histories_codes_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `history_excode`
--
ALTER TABLE `history_excode`
  ADD CONSTRAINT `history_excode_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `history_internal_accounts`
--
ALTER TABLE `history_internal_accounts`
  ADD CONSTRAINT `history_internal_accounts_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `history_payment_transactions`
--
ALTER TABLE `history_payment_transactions`
  ADD CONSTRAINT `history_payment_transactions_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `history_recalculation`
--
ALTER TABLE `history_recalculation`
  ADD CONSTRAINT `history_recalculation_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `kyc_logs`
--
ALTER TABLE `kyc_logs`
  ADD CONSTRAINT `kyc_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `log_autopayments`
--
ALTER TABLE `log_autopayments`
  ADD CONSTRAINT `log_autopayments_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `log_error_merchants`
--
ALTER TABLE `log_error_merchants`
  ADD CONSTRAINT `log_error_merchants_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `merchant_account`
--
ALTER TABLE `merchant_account`
  ADD CONSTRAINT `merchant_account_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `merchant_transaction_hash`
--
ALTER TABLE `merchant_transaction_hash`
  ADD CONSTRAINT `merchant_transaction_hash_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `merchant_transaction_ids`
--
ALTER TABLE `merchant_transaction_ids`
  ADD CONSTRAINT `merchant_transaction_ids_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `news`
--
ALTER TABLE `news`
  ADD CONSTRAINT `news_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `news_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_exchange_totals`
--
ALTER TABLE `order_exchange_totals`
  ADD CONSTRAINT `order_exchange_totals_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_exports`
--
ALTER TABLE `order_exports`
  ADD CONSTRAINT `order_exports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `pages`
--
ALTER TABLE `pages`
  ADD CONSTRAINT `pages_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `page_groups` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `parser_exchange`
--
ALTER TABLE `parser_exchange`
  ADD CONSTRAINT `fk_parser_exchange_group` FOREIGN KEY (`id_group`) REFERENCES `group_parser_exchange` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `pay_transaction_hash`
--
ALTER TABLE `pay_transaction_hash`
  ADD CONSTRAINT `pay_transaction_hash_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `promo_code_directions`
--
ALTER TABLE `promo_code_directions`
  ADD CONSTRAINT `promo_code_directions_direction_exchange_id_foreign` FOREIGN KEY (`direction_exchange_id`) REFERENCES `direction_exchange` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `promo_code_directions_promo_code_id_foreign` FOREIGN KEY (`promo_code_id`) REFERENCES `promo_codes` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `proxy_health_logs`
--
ALTER TABLE `proxy_health_logs`
  ADD CONSTRAINT `proxy_health_logs_proxy_id_foreign` FOREIGN KEY (`proxy_id`) REFERENCES `proxies` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `referral_links`
--
ALTER TABLE `referral_links`
  ADD CONSTRAINT `referral_links_referral_program_id_foreign` FOREIGN KEY (`referral_program_id`) REFERENCES `referral_programs` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `referral_log`
--
ALTER TABLE `referral_log`
  ADD CONSTRAINT `referral_log_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `referral_relationships`
--
ALTER TABLE `referral_relationships`
  ADD CONSTRAINT `referral_relationships_referral_link_id_foreign` FOREIGN KEY (`referral_link_id`) REFERENCES `referral_links` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `referral_settings_code_audits`
--
ALTER TABLE `referral_settings_code_audits`
  ADD CONSTRAINT `referral_settings_code_audits_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `referral_settings_code_audits_after_code_currency_id_foreign` FOREIGN KEY (`after_code_currency_id`) REFERENCES `code_currency` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `referral_settings_code_audits_before_code_currency_id_foreign` FOREIGN KEY (`before_code_currency_id`) REFERENCES `code_currency` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `requisites_has_fields`
--
ALTER TABLE `requisites_has_fields`
  ADD CONSTRAINT `requisites_has_fields_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `requisites_fields` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `requisites_has_fields_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `requisites` (`id`);

--
-- Ограничения внешнего ключа таблицы `requisites_has_info_fields`
--
ALTER TABLE `requisites_has_info_fields`
  ADD CONSTRAINT `requisites_has_info_fields_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `requisites_info_fields` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `requisites_has_info_fields_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `requisites` (`id`);

--
-- Ограничения внешнего ключа таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `selector_fee_direction_exchange`
--
ALTER TABLE `selector_fee_direction_exchange`
  ADD CONSTRAINT `selector_fee_directions_id_foreign` FOREIGN KEY (`direction_exchange_id`) REFERENCES `direction_exchange` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `selector_fee_id_foreign` FOREIGN KEY (`selector_fee_id`) REFERENCES `selector_fees` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `sumsub_ids`
--
ALTER TABLE `sumsub_ids`
  ADD CONSTRAINT `sumsub_ids_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_card_details`
--
ALTER TABLE `tasks_card_details`
  ADD CONSTRAINT `tasks_card_details_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_chat`
--
ALTER TABLE `tasks_chat`
  ADD CONSTRAINT `tasks_chat_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_comments`
--
ALTER TABLE `tasks_comments`
  ADD CONSTRAINT `tasks_comments_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_comments_users`
--
ALTER TABLE `tasks_comments_users`
  ADD CONSTRAINT `tasks_comments_users_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_fields`
--
ALTER TABLE `tasks_fields`
  ADD CONSTRAINT `tasks_fields_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_files`
--
ALTER TABLE `tasks_files`
  ADD CONSTRAINT `tasks_files_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_history_operators`
--
ALTER TABLE `tasks_history_operators`
  ADD CONSTRAINT `tasks_history_operators_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_info`
--
ALTER TABLE `tasks_info`
  ADD CONSTRAINT `tasks_info_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_meta`
--
ALTER TABLE `tasks_meta`
  ADD CONSTRAINT `tasks_meta_direction_selected_fee_id_foreign` FOREIGN KEY (`direction_selected_fee_id`) REFERENCES `direction_exchange_selector_fee` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_meta_selected_fee_id_foreign` FOREIGN KEY (`selected_fee_id`) REFERENCES `selector_fees` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_meta_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_operators`
--
ALTER TABLE `tasks_operators`
  ADD CONSTRAINT `tasks_operators_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_operators_logs`
--
ALTER TABLE `tasks_operators_logs`
  ADD CONSTRAINT `tasks_operators_logs_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_profits`
--
ALTER TABLE `tasks_profits`
  ADD CONSTRAINT `tasks_profits_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_rates_data`
--
ALTER TABLE `tasks_rates_data`
  ADD CONSTRAINT `tasks_rates_data_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_requisites`
--
ALTER TABLE `tasks_requisites`
  ADD CONSTRAINT `tasks_requisites_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_shots`
--
ALTER TABLE `tasks_shots`
  ADD CONSTRAINT `tasks_shots_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks_status_log`
--
ALTER TABLE `tasks_status_log`
  ADD CONSTRAINT `tasks_status_log_direction_fk` FOREIGN KEY (`id_direction_exchange`) REFERENCES `direction_exchange` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_status_log_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `task_check_payment_status_logs`
--
ALTER TABLE `task_check_payment_status_logs`
  ADD CONSTRAINT `task_check_payment_status_logs_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `task_extra_outs`
--
ALTER TABLE `task_extra_outs`
  ADD CONSTRAINT `task_extra_outs_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `task_log`
--
ALTER TABLE `task_log`
  ADD CONSTRAINT `task_log_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `task_single_log_confirm`
--
ALTER TABLE `task_single_log_confirm`
  ADD CONSTRAINT `task_single_log_confirm_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `telescope_entries_tags`
--
ALTER TABLE `telescope_entries_tags`
  ADD CONSTRAINT `telescope_entries_tags_entry_uuid_foreign` FOREIGN KEY (`entry_uuid`) REFERENCES `telescope_entries` (`uuid`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_limit_profile_id_foreign` FOREIGN KEY (`limit_profile_id`) REFERENCES `settings_limit_profiles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_referral_program_id_foreign` FOREIGN KEY (`referral_program_id`) REFERENCES `referral_programs` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `wallets_history`
--
ALTER TABLE `wallets_history`
  ADD CONSTRAINT `wallets_history_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `wallet_transactions_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `whitebit_withdraw`
--
ALTER TABLE `whitebit_withdraw`
  ADD CONSTRAINT `whitebit_withdraw_id_task_foreign` FOREIGN KEY (`id_task`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
