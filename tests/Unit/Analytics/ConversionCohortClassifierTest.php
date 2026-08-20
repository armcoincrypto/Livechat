<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics;

use App\Services\Analytics\ConversionCohortClassifier;
use iEXPackages\Analytics\Services\Exchanges\ConversionFunnelService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConversionCohortClassifierTest extends TestCase
{
    public function test_test_canary_emails(): void
    {
        self::assertSame(
            ConversionCohortClassifier::TEST_CANARY,
            ConversionCohortClassifier::classify([
                'email' => 'browser-e2e-canary@qa.exswaping.com',
                'is_spam' => 0,
                'created_at' => '2026-08-19 12:00:00',
            ])
        );
        self::assertSame(
            ConversionCohortClassifier::TEST_CANARY,
            ConversionCohortClassifier::classify([
                'email' => 'ops@exswaping.local',
                'created_at' => '2026-08-19 12:00:00',
            ])
        );
        self::assertTrue(ConversionCohortClassifier::isTestCanaryEmail('wave2-check@example.com'));
    }

    public function test_spam_is_bot_cohort_not_is_bot_flag(): void
    {
        self::assertSame(
            ConversionCohortClassifier::BOT,
            ConversionCohortClassifier::classify([
                'email' => 'someone@gmail.com',
                'is_spam' => 1,
                'created_at' => '2026-08-19 12:00:00',
            ])
        );

        // is_bot merchant flag must NOT force BOT cohort
        self::assertSame(
            ConversionCohortClassifier::REAL_CUSTOMER,
            ConversionCohortClassifier::classify([
                'email' => 'customer@gmail.com',
                'is_spam' => 0,
                'created_at' => '2026-08-19 12:00:00',
            ])
        );
    }

    public function test_historical_and_unknown(): void
    {
        self::assertSame(
            ConversionCohortClassifier::HISTORICAL_PRE_ATTRIBUTION,
            ConversionCohortClassifier::classify([
                'email' => 'old@gmail.com',
                'is_spam' => 0,
                'created_at' => '2026-08-07 23:59:59',
            ])
        );
        self::assertSame(
            ConversionCohortClassifier::UNKNOWN,
            ConversionCohortClassifier::classify([
                'email' => '',
                'is_spam' => 0,
                'created_at' => '2026-08-19 12:00:00',
            ])
        );
    }

    public function test_internal_operator_not_guessed(): void
    {
        self::assertSame(
            ConversionCohortClassifier::REAL_CUSTOMER,
            ConversionCohortClassifier::classify([
                'email' => 'staff@exswaping.com',
                'is_spam' => 0,
                'created_at' => '2026-08-19 12:00:00',
            ])
        );
        self::assertContains(ConversionCohortClassifier::INTERNAL_OPERATOR, ConversionCohortClassifier::all());
    }

    public function test_period_whitelist(): void
    {
        $svc = new ConversionFunnelService;
        [$from, $to, $key] = $svc->resolvePeriod('7d');
        self::assertSame('7d', $key);
        self::assertTrue($from->lt($to));

        $this->expectException(InvalidArgumentException::class);
        $svc->resolvePeriod('90d');
    }

    public function test_payment_status_set_includes_paid_and_downstream(): void
    {
        self::assertContains(7, ConversionCohortClassifier::PAYMENT_RECEIVED_OR_BEYOND);
        self::assertContains(3, ConversionCohortClassifier::PAYMENT_RECEIVED_OR_BEYOND);
        self::assertContains(4, ConversionCohortClassifier::PAYMENT_RECEIVED_OR_BEYOND);
        self::assertNotContains(2, ConversionCohortClassifier::PAYMENT_RECEIVED_OR_BEYOND);
        self::assertNotContains(1, ConversionCohortClassifier::PAYMENT_RECEIVED_OR_BEYOND);
    }
}
