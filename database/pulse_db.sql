-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Апр 15 2025 г., 08:36
-- Версия сервера: 8.0.42
-- Версия PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `pulse_db`
--

-- --------------------------------------------------------

--
-- Структура таблицы `pulse_aggregates`
--

CREATE TABLE `pulse_aggregates` (
  `id` bigint UNSIGNED NOT NULL,
  `bucket` int UNSIGNED NOT NULL,
  `period` mediumint UNSIGNED NOT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `aggregate` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(20,2) NOT NULL,
  `count` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `pulse_entries`
--

CREATE TABLE `pulse_entries` (
  `id` bigint UNSIGNED NOT NULL,
  `timestamp` int UNSIGNED NOT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `value` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `pulse_values`
--

CREATE TABLE `pulse_values` (
  `id` bigint UNSIGNED NOT NULL,
  `timestamp` int UNSIGNED NOT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `pulse_aggregates`
--
ALTER TABLE `pulse_aggregates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pulse_aggregates_bucket_period_type_aggregate_key_hash_unique` (`bucket`,`period`,`type`,`aggregate`,`key_hash`),
  ADD KEY `pulse_aggregates_period_bucket_index` (`period`,`bucket`),
  ADD KEY `pulse_aggregates_type_index` (`type`),
  ADD KEY `pulse_aggregates_period_type_aggregate_bucket_index` (`period`,`type`,`aggregate`,`bucket`);

--
-- Индексы таблицы `pulse_entries`
--
ALTER TABLE `pulse_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pulse_entries_timestamp_index` (`timestamp`),
  ADD KEY `pulse_entries_type_index` (`type`),
  ADD KEY `pulse_entries_key_hash_index` (`key_hash`),
  ADD KEY `pulse_entries_timestamp_type_key_hash_value_index` (`timestamp`,`type`,`key_hash`,`value`);

--
-- Индексы таблицы `pulse_values`
--
ALTER TABLE `pulse_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pulse_values_type_key_hash_unique` (`type`,`key_hash`),
  ADD KEY `pulse_values_timestamp_index` (`timestamp`),
  ADD KEY `pulse_values_type_index` (`type`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `pulse_aggregates`
--
ALTER TABLE `pulse_aggregates`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `pulse_entries`
--
ALTER TABLE `pulse_entries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `pulse_values`
--
ALTER TABLE `pulse_values`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
