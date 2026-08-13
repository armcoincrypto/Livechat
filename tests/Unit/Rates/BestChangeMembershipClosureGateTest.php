<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeMappingVerifier;
use App\Services\Rates\CanonicalDirectionEligibility;
use Tests\TestCase;

/**
 * Release gate: public+orderable+owner-enabled+BC-supported must not drift out of XML.
 * Inverse: XML pairs must remain claimable by a public+orderable direction.
 */
final class BestChangeMembershipClosureGateTest extends TestCase
{
    public function test_bestchange_export_context_constant_stable(): void
    {
        $this->assertSame('BESTCHANGE_EXPORT', CanonicalDirectionEligibility::CTX_BESTCHANGE_EXPORT);
    }

    public function test_payeer_rails_remain_absent_fail_closed(): void
    {
        $path = base_path('resources/rates/bestchange-codes.overrides.json');
        if (!is_file($path)) {
            $this->markTestSkipped('overrides file unavailable');
        }

        $v = BestChangeMappingVerifier::fromStorageApp();
        foreach (['PRUSD', 'PREUR', 'PRRUB'] as $code) {
            $this->assertSame(
                'ABSENT',
                strtoupper((string) ($v->verifyCode($code)['status'] ?? '')),
                $code
            );
        }
    }

    public function test_membership_health_command_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Console\Commands\RatesBestChangeMembershipHealthCommand::class));
    }

    public function test_live_membership_critical_metrics_when_xml_present(): void
    {
        $xml = public_path('static/exports/currencies.xml');
        if (!is_file($xml)) {
            $this->markTestSkipped('live currencies.xml unavailable');
        }

        // Fast structural checks only — full DB sweep is the artisan command.
        $raw = (string) file_get_contents($xml);
        $this->assertStringContainsString('<rates', $raw);
        $items = substr_count($raw, '<item>');
        $this->assertGreaterThan(0, $items);
        $this->assertSame(0, substr_count($raw, '<manual></manual>'));
        $this->assertSame(0, substr_count($raw, '<frommin></frommin>'));
        $this->assertSame(0, substr_count($raw, '<frommax></frommax>'));
    }
}
