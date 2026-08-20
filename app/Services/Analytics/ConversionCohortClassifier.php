<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Deterministic conversion cohorts from existing task fields only.
 *
 * IMPORTANT: tasks.is_bot is a merchant/polling payment flag, NOT a traffic-bot
 * signal. Do not use it for BOT cohort classification.
 */
final class ConversionCohortClassifier
{
    public const REAL_CUSTOMER = 'REAL_CUSTOMER';

    public const BOT = 'BOT';

    public const TEST_CANARY = 'TEST_CANARY';

    public const HISTORICAL_PRE_ATTRIBUTION = 'HISTORICAL_PRE_ATTRIBUTION';

    public const INTERNAL_OPERATOR = 'INTERNAL_OPERATOR';

    public const UNKNOWN = 'UNKNOWN';

    /** Feature attribution went live (UTC calendar day). */
    public const ATTRIBUTION_EPOCH = '2026-08-08 00:00:00';

    /**
     * Statuses that mean payment was received or the order progressed past payment.
     *
     * @var list<int>
     */
    public const PAYMENT_RECEIVED_OR_BEYOND = [7, 3, 16, 4, 9, 12, 13, 15];

    /** @var list<int> */
    public const PROCESSING = [3, 16, 15];

    /**
     * Classify a task row. Prefer UNKNOWN over guessing.
     *
     * @param  array{email?: ?string, is_spam?: int|bool|null, created_at?: ?string}  $row
     */
    public static function classify(array $row): string
    {
        $email = strtolower(trim((string) ($row['email'] ?? '')));

        if (self::isTestCanaryEmail($email)) {
            return self::TEST_CANARY;
        }

        if ((int) ($row['is_spam'] ?? 0) === 1) {
            return self::BOT;
        }

        $createdAt = (string) ($row['created_at'] ?? '');
        if ($createdAt !== '' && $createdAt < self::ATTRIBUTION_EPOCH) {
            return self::HISTORICAL_PRE_ATTRIBUTION;
        }

        // No proven operator identity field; do not invent INTERNAL_OPERATOR.
        if ($email === '') {
            return self::UNKNOWN;
        }

        return self::REAL_CUSTOMER;
    }

    public static function isTestCanaryEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        if (str_contains($email, 'canary')) {
            return true;
        }

        if (str_ends_with($email, '@qa.exswaping.com') || str_ends_with($email, '@exswaping.local')) {
            return true;
        }

        if (preg_match('/(^|[^a-z0-9])test([a-z0-9._+-]*)@exswaping\.com$/', $email) === 1) {
            return true;
        }

        if (str_contains($email, 'wave2') || str_contains($email, 'attribution-canary')) {
            return true;
        }

        return false;
    }

    /**
     * SQL CASE expression for cohort (alias default `t`).
     * Mirrors classify() for aggregate queries.
     */
    public static function sqlCaseExpression(string $alias = 't'): string
    {
        $epoch = self::ATTRIBUTION_EPOCH;

        return <<<SQL
CASE
  WHEN {$alias}.email LIKE '%canary%'
    OR {$alias}.email LIKE '%@qa.exswaping.com'
    OR {$alias}.email LIKE '%@exswaping.local'
    OR {$alias}.email LIKE '%test%@exswaping.com'
    OR {$alias}.email LIKE '%wave2%'
    OR {$alias}.email LIKE '%attribution-canary%'
    THEN 'TEST_CANARY'
  WHEN {$alias}.is_spam = 1 THEN 'BOT'
  WHEN {$alias}.created_at < '{$epoch}' THEN 'HISTORICAL_PRE_ATTRIBUTION'
  WHEN {$alias}.email IS NULL OR {$alias}.email = '' THEN 'UNKNOWN'
  ELSE 'REAL_CUSTOMER'
END
SQL;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::REAL_CUSTOMER,
            self::BOT,
            self::TEST_CANARY,
            self::HISTORICAL_PRE_ATTRIBUTION,
            self::INTERNAL_OPERATOR,
            self::UNKNOWN,
        ];
    }
}
