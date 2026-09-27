<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeMarketBaseHealth;
use App\Services\Rates\RateAuthorityDriftMonitor;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class RateAuthorityDriftMonitorContractTest extends TestCase
{
    public function test_severity_buckets(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $fn = new ReflectionMethod($m, 'severityFromDiff');
        $fn->setAccessible(true);
        $this->assertSame('OK', $fn->invoke($m, 0.5));
        $this->assertSame('WATCH', $fn->invoke($m, 1.5));
        $this->assertSame('WARNING', $fn->invoke($m, 3.5));
        $this->assertSame('CRITICAL', $fn->invoke($m, 5.1));
    }

    public function test_owner_normalization(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $fn = new ReflectionMethod($m, 'normalizeOwner');
        $fn->setAccessible(true);
        $this->assertSame('BESTCHANGE', $fn->invoke($m, 'BestChange'));
        $this->assertSame('DERIVED', $fn->invoke($m, 'DERIVED_MARKET_BASELINE'));
        $this->assertSame('ZELLE', $fn->invoke($m, 'ZELLE_USDTTRC20_BENCHMARK'));
        $this->assertSame('MANUAL', $fn->invoke($m, 'Ручной курс'));
    }

    public function test_payeer_and_1513_intentional(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $fn = new ReflectionMethod($m, 'isIntentionalDerivedException');
        $fn->setAccessible(true);
        $this->assertTrue($fn->invoke($m, 1513, 'USDTTRC20', 'CASHUSD'));
        $this->assertTrue($fn->invoke($m, 9999, 'PRRUB', 'USDTTRC20'));
        $this->assertTrue($fn->invoke($m, 9999, 'BTC', 'PREUR'));
        $this->assertFalse($fn->invoke($m, 9999, 'BTC', 'USDTTRC20'));
    }

    public function test_alert_dedupe_same_key_suppressed(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $fn = new ReflectionMethod($m, 'dedupeEvents');
        $fn->setAccessible(true);
        $row = [
            'direction_id' => 410,
            'pair' => 'GRAM→BTC',
            'owner' => 'BESTCHANGE',
            'severity' => 'CRITICAL',
            'root_cause' => 'PROFIT_APPLICATION_MISMATCH',
            'first_bad_stage' => 'x',
            'current_final' => '1',
            'expected_final' => '2',
            'diff_percent' => 10.0,
            'public_impact' => true,
            'order_impact' => true,
            'xml_impact' => true,
            'action' => 'INVESTIGATE',
        ];
        $key = '410|PROFIT_APPLICATION_MISMATCH|CRITICAL';
        $first = $fn->invoke($m, [], $key, $row);
        $this->assertCount(1, $first);
        $second = $fn->invoke($m, [$key => $row], $key, $row);
        $this->assertCount(0, $second);
    }

    public function test_operator_alert_format_contains_no_auto_change(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $text = $m->formatOperatorAlert([
            'type' => 'OPEN',
            'severity' => 'CRITICAL',
            'direction_id' => 410,
            'pair' => 'GRAM→BTC',
            'owner' => 'DERIVED',
            'current_final' => '0.1',
            'expected_final' => '0.2',
            'diff_percent' => 12.9,
            'root_cause' => 'DERIVED_PROVIDER_STALE',
            'first_bad_stage' => 'TONUSDT provider leg',
            'public_impact' => true,
            'order_impact' => true,
            'xml_impact' => true,
            'action' => 'QUARANTINE_EXISTING_POLICY',
        ]);
        $this->assertStringContainsString('RATE DRIFT', $text);
        $this->assertStringContainsString('NO AUTO RATE CHANGE', $text);
        $this->assertStringContainsString('DERIVED_PROVIDER_STALE', $text);
    }

    public function test_recovery_alert_format(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $text = $m->formatOperatorAlert([
            'type' => 'RECOVERED',
            'direction_id' => 410,
            'pair' => 'GRAM→BTC',
            'owner' => 'BESTCHANGE',
            'previous_root_cause' => 'BESTCHANGE_BOOK_STALE',
            'recovered_at' => '2026-09-26T12:00:00Z',
        ]);
        $this->assertStringContainsString('RATE RECOVERED', $text);
        $this->assertStringContainsString('BESTCHANGE_BOOK_STALE', $text);
    }

    public function test_schedule_registers_authority_drift_monitor_once(): void
    {
        $src = file_get_contents(dirname(__DIR__, 3).'/app/Console/Schedule/ExchangeSchedule.php');
        $this->assertNotFalse($src);
        $this->assertSame(1, substr_count((string) $src, 'rates:authority-drift-monitor'));
        $this->assertStringContainsString('withoutOverlapping(8)', (string) $src);
        // Preserve BC reliability + scheduler hygiene from live base.
        $this->assertMatchesRegularExpression(
            '/compiler:bestchange[\s\S]{0,500}withoutOverlapping\(\s*20\s*\)/',
            (string) $src
        );
        $this->assertStringContainsString('run_schedule_run_once.sh', (string) $src);
    }

    public function test_alert_class_maps_stale_vs_price_drift(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $fn = new ReflectionMethod($m, 'alertClassForRoot');
        $fn->setAccessible(true);
        $this->assertSame('AUTHORITY_DATA_STALE', $fn->invoke($m, 'BESTCHANGE_BOOK_STALE'));
        $this->assertSame('AUTHORITY_PRICE_DRIFT', $fn->invoke($m, 'PROFIT_APPLICATION_MISMATCH'));
        $this->assertSame('OWNERSHIP_INVARIANT', $fn->invoke($m, 'REGISTRY_REENTRY'));
        $this->assertSame('PROVIDER_UNAVAILABLE', $fn->invoke($m, 'BESTCHANGE_MAPPING_MISMATCH'));
        $this->assertSame('PROVIDER_UNAVAILABLE', $fn->invoke($m, 'BESTCHANGE_POSITION_INSUFFICIENT_DEPTH'));
        $this->assertSame('PROVIDER_UNAVAILABLE', $fn->invoke($m, 'BESTCHANGE_PARSER_ERROR'));
    }

    public function test_systemic_collapse_below_threshold_passthrough(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $fn = new ReflectionMethod($m, 'collapseSystemicBestChangeStaleAlerts');
        $fn->setAccessible(true);
        $events = [[
            'type' => 'OPEN',
            'owner' => 'BESTCHANGE',
            'root_cause' => 'BESTCHANGE_BOOK_STALE',
            'direction_id' => 1,
        ]];
        $out = $fn->invoke($m, $events, ['BESTCHANGE_STALE_TOTAL' => 1, 'BESTCHANGE_BAD_TOTAL' => 1], []);
        $this->assertFalse($out['systemic']);
        $this->assertCount(1, $out['events']);
    }

    public function test_gram_wave_invariant_ids_frozen(): void
    {
        $ref = new \ReflectionClass(RateAuthorityDriftMonitor::class);
        $gram = $ref->getConstant('GRAM_MIGRATED_IDS');
        $w2a = $ref->getConstant('WAVE2A_IDS');
        $w2d = $ref->getConstant('WAVE2D_IDS');
        $keep = $ref->getConstant('KEEP_DERIVED_IDS');
        $this->assertContains(410, $gram);
        $this->assertSame([2237], $w2a);
        $this->assertEqualsCanonicalizing([2870, 1482], $w2d);
        $this->assertSame([1513], $keep);
    }

    public function test_bc_fixture_stale_heartbeat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-27 16:53:04', 'UTC'));
        $bc = (object) [
            'status' => 1,
            'is_error_parser' => 0,
            'rate_value' => '1.23',
            'position_num' => '3',
            'updated_at' => '2026-09-27 16:00:00', // 3180s old > 1800
        ];
        $h = BestChangeMarketBaseHealth::evaluateFromRow($bc, 410, 1800, false);
        $this->assertFalse($h['healthy']);
        $this->assertSame('stale', $h['reason']);
        $this->assertGreaterThan(1800, (int) $h['age_seconds']);
        Carbon::setTestNow();
    }

    public function test_bc_fixture_fresh_identical_rate_heartbeat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-27 16:53:04', 'UTC'));
        $bc = (object) [
            'status' => 1,
            'is_error_parser' => 0,
            'rate_value' => '1.23',
            'position_num' => '3',
            'updated_at' => '2026-09-27 16:50:00', // 184s
        ];
        $h = BestChangeMarketBaseHealth::evaluateFromRow($bc, 410, 1800, false);
        $this->assertTrue($h['healthy']);
        $this->assertSame('healthy', $h['status']);
        $this->assertLessThan(900, (int) $h['age_seconds']);
        $this->assertFalse($h['book_age_stale']);
        Carbon::setTestNow();
    }

    public function test_fresh_heartbeat_plus_position_insufficient_depth_not_book_stale(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-27 16:53:04', 'UTC'));
        $bc = (object) [
            'status' => 1,
            'is_error_parser' => 1,
            'rate_value' => '1.23',
            'position_num' => '6',
            'updated_at' => '2026-09-27 16:50:00', // fresh
            'explain_payload' => json_encode([
                'reason' => 'position_insufficient_depth',
                'available_offers' => 5,
                'requested_position' => 6,
            ]),
        ];
        $h = BestChangeMarketBaseHealth::evaluateFromRow($bc, 2239, 1800, false);
        $this->assertFalse($h['healthy']);
        $this->assertFalse($h['book_age_stale']); // BOOK_STALE=NO
        $this->assertTrue($h['is_error_parser']); // PARSER_ERROR=YES
        $this->assertSame('position_insufficient_depth', $h['reason']);
        $this->assertSame(5, $h['book_depth']);
        Carbon::setTestNow();
    }

    public function test_operator_alert_bodies_are_class_specific(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $stale = $m->formatOperatorAlert([
            'type' => 'OPEN',
            'severity' => 'WARNING',
            'direction_id' => 1,
            'pair' => 'BTC→USDTTRC20',
            'owner' => 'BESTCHANGE',
            'root_cause' => 'BESTCHANGE_BOOK_STALE',
            'alert_class' => 'AUTHORITY_DATA_STALE',
            'reference_age_sec' => 2000,
            'public_impact' => false,
            'order_impact' => false,
            'xml_impact' => false,
            'action' => 'INVESTIGATE',
        ]);
        $this->assertStringContainsString('AUTHORITY DATA STALE', $stale);
        $this->assertStringNotContainsString('RATE DRIFT', $stale);

        $thin = $m->formatOperatorAlert([
            'type' => 'OPEN',
            'severity' => 'WATCH',
            'direction_id' => 2239,
            'pair' => 'NEO→CARDUZS',
            'owner' => 'BESTCHANGE',
            'root_cause' => 'BESTCHANGE_POSITION_INSUFFICIENT_DEPTH',
            'position' => '6',
            'book_depth' => 5,
            'explain_reason' => 'position_insufficient_depth',
            'book_age_stale' => false,
            'first_bad_stage' => 'bestchange_position_depth',
            'public_impact' => false,
            'order_impact' => false,
            'xml_impact' => false,
            'action' => 'OBSERVE',
        ]);
        $this->assertStringContainsString('BESTCHANGE BOOK UNUSABLE', $thin);
        $this->assertStringContainsString('position_insufficient_depth', $thin);
        $this->assertStringContainsString('BOOK AGE STALE: NO', $thin);
        $this->assertStringNotContainsString('RATE DRIFT', $thin);
    }

    public function test_bc_fixture_missing_inactive_thin_parser_error(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-27 16:53:04', 'UTC'));
        $fresh = '2026-09-27 16:50:00';
        $inactive = BestChangeMarketBaseHealth::evaluateFromRow((object) [
            'status' => 0, 'is_error_parser' => 0, 'rate_value' => '1', 'position_num' => '1', 'updated_at' => $fresh,
        ], 1, 1800, false);
        $this->assertSame('link_inactive', $inactive['reason']);

        $thin = BestChangeMarketBaseHealth::evaluateFromRow((object) [
            'status' => 1, 'is_error_parser' => 0, 'rate_value' => '0', 'position_num' => '1', 'updated_at' => $fresh,
        ], 1, 1800, false);
        $this->assertSame('non_positive_rate', $thin['reason']);

        $err = BestChangeMarketBaseHealth::evaluateFromRow((object) [
            'status' => 1, 'is_error_parser' => 1, 'rate_value' => '1', 'position_num' => '1', 'updated_at' => $fresh,
        ], 1, 1800, false);
        $this->assertSame('parser_error', $err['reason']);
        Carbon::setTestNow();
    }

    public function test_systemic_collapse_above_threshold(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $fn = new ReflectionMethod($m, 'collapseSystemicBestChangeStaleAlerts');
        $fn->setAccessible(true);
        $events = [];
        for ($i = 1; $i <= 40; $i++) {
            $events[] = [
                'type' => 'OPEN',
                'owner' => 'BESTCHANGE',
                'root_cause' => 'BESTCHANGE_BOOK_STALE',
                'direction_id' => $i,
                'severity' => 'WARNING',
                'alert_class' => 'AUTHORITY_DATA_STALE',
            ];
        }
        $out = $fn->invoke($m, $events, [
            'BESTCHANGE_STALE_TOTAL' => 40,
            'BESTCHANGE_BAD_TOTAL' => 40,
            'RATE_TOTAL' => 2000,
        ], []);
        $this->assertTrue($out['systemic']);
        $this->assertNotEmpty($out['global_key'] ?? null);
        // Per-direction OPEN spam collapsed to systemic + non-stale passthrough.
        $openStale = array_filter(
            $out['events'],
            static fn ($e) => ($e['root_cause'] ?? '') === 'BESTCHANGE_BOOK_STALE' && ($e['type'] ?? '') === 'OPEN'
        );
        $this->assertLessThanOrEqual(1, count($openStale));
    }

    public function test_operator_alert_answers_what_how_who_why(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $text = $m->formatOperatorAlert([
            'type' => 'OPEN',
            'severity' => 'CRITICAL',
            'direction_id' => 1209,
            'pair' => 'USDTSOL→CARDBYN',
            'owner' => 'BESTCHANGE',
            'current_final' => '1.0',
            'expected_final' => '1.2',
            'diff_percent' => 17.6,
            'root_cause' => 'PROFIT_APPLICATION_MISMATCH',
            'first_bad_stage' => 'course_value_vs_bc_base_plus_profit',
            'public_impact' => true,
            'order_impact' => true,
            'xml_impact' => false,
            'action' => 'DEDICATED_PRICING_REMEDIATION',
            'alert_class' => 'AUTHORITY_PRICE_DRIFT',
        ]);
        $this->assertStringContainsString('USDTSOL→CARDBYN', $text);
        $this->assertStringContainsString('BESTCHANGE', $text);
        $this->assertStringContainsString('PROFIT_APPLICATION_MISMATCH', $text);
        $this->assertStringContainsString('course_value_vs_bc_base_plus_profit', $text);
        $this->assertStringNotContainsString('rate health degraded', strtolower($text));
    }
}
