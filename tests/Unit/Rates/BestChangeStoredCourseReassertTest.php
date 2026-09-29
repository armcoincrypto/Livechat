<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

require_once dirname(__DIR__, 3) . '/app/Services/Rates/BestChangeStoredCourseReassert.php';

use App\Services\Rates\BestChangeStoredCourseReassert;
use PHPUnit\Framework\TestCase;

final class BestChangeStoredCourseReassertTest extends TestCase
{
    public function test_zero_profit_manual_mirror_below_rate_is_stale(): void
    {
        $this->assertTrue(BestChangeStoredCourseReassert::isStaleManualMirror(
            '7083021.4256975',
            '7083021.4256975',
            '7502213.1528800996',
            '0',
        ));
        $this->assertTrue(BestChangeStoredCourseReassert::needsZeroProfitCourseReassert(
            '7083021.4256975',
            '7502213.1528800996',
            '0',
        ));
    }

    public function test_cardbyn_3187_diverged_course_is_selected(): void
    {
        // Live evidence 2026-09-29: USDTPOLYGON→CARDBYN course≃2.881 vs BC≃2.6867
        // (≈7.23%) with profit=0 and course≠manual — prior mirror predicate missed it.
        $this->assertFalse(BestChangeStoredCourseReassert::isStaleManualMirror(
            '2.881031126637655519',
            '2.915708',
            '2.686700000080708468',
            '0',
        ));
        $this->assertTrue(BestChangeStoredCourseReassert::needsZeroProfitCourseReassert(
            '2.881031126637655519',
            '2.686700000080708468',
            '0',
        ));
    }

    public function test_cardbyn_2281_sub_percent_drift_is_not_selected(): void
    {
        // TRX→CARDBYN ~0.07% — below RELATIVE_GAP; monitor severity OK.
        $this->assertFalse(BestChangeStoredCourseReassert::needsZeroProfitCourseReassert(
            '0.915262985030462011',
            '0.914617752933629582',
            '0',
        ));
    }

    public function test_adjacent_cardbyn_and_cash_usd_gaps_follow_gap_rule(): void
    {
        // Adjacent CARDBYN (USDT SOL→BYN card) course below BC by ~18%.
        $this->assertTrue(BestChangeStoredCourseReassert::needsZeroProfitCourseReassert(
            '2.18999999997041',
            '2.68999999997041',
            '0',
        ));
        // Cash USD→USDT large gap, profit 0.
        $this->assertTrue(BestChangeStoredCourseReassert::needsZeroProfitCourseReassert(
            '0.954644824393084552',
            '0.689655657550876346',
            '0',
        ));
    }

    public function test_profit_position_and_unrelated_gaps_are_not_selected(): void
    {
        $this->assertFalse(BestChangeStoredCourseReassert::isStaleManualMirror(
            '45.885766874751985357',
            '44.872610842',
            '46.07009',
            '0.4',
        ));
        $this->assertFalse(BestChangeStoredCourseReassert::needsZeroProfitCourseReassert(
            '45.885766874751985357',
            '46.07009',
            '0.4',
        ));
        // course==manual above rate: classic mirror predicate false; gap rule true.
        $this->assertFalse(BestChangeStoredCourseReassert::isStaleManualMirror(
            '1.5984',
            '1.5984',
            '1.366',
            '0',
        ));
        $this->assertTrue(BestChangeStoredCourseReassert::needsZeroProfitCourseReassert(
            '1.5984',
            '1.366',
            '0',
        ));
        $this->assertFalse(BestChangeStoredCourseReassert::isStaleManualMirror(
            '2.19',
            '0',
            '2.69',
            '0',
        ));
        $this->assertTrue(BestChangeStoredCourseReassert::needsZeroProfitCourseReassert(
            '2.19',
            '2.69',
            '0',
        ));
    }

    public function test_mirror_reassert_allows_slightly_aged_clean_rate(): void
    {
        $healthy = [
            'healthy' => true,
            'link_status' => 1,
            'rate_value' => '100.5',
            'reason' => null,
        ];
        $this->assertTrue(BestChangeStoredCourseReassert::isUsableRateForMirrorReassert($healthy));

        // evaluate(..., 7200) still healthy when age is 2000s.
        $softOk = [
            'healthy' => true,
            'link_status' => 1,
            'rate_value' => '100.5',
            'reason' => null,
            'age_seconds' => 2000,
        ];
        $this->assertTrue(BestChangeStoredCourseReassert::isUsableRateForMirrorReassert($softOk));

        $parserErr = [
            'healthy' => false,
            'link_status' => 1,
            'rate_value' => '100.5',
            'reason' => 'parser_error',
        ];
        $this->assertFalse(BestChangeStoredCourseReassert::isUsableRateForMirrorReassert($parserErr));

        $extremeStale = [
            'healthy' => false,
            'link_status' => 1,
            'rate_value' => '100.5',
            'reason' => 'stale_updated_at',
            'age_seconds' => 9000,
        ];
        $this->assertFalse(BestChangeStoredCourseReassert::isUsableRateForMirrorReassert($extremeStale));

        $inactive = [
            'healthy' => false,
            'link_status' => 0,
            'rate_value' => '100.5',
            'reason' => 'link_inactive',
        ];
        $this->assertFalse(BestChangeStoredCourseReassert::isUsableRateForMirrorReassert($inactive));
    }

    public function test_courses_command_calls_reassert_after_full_update(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/packages/Courses/Console/UpdateCoursesConsole.php'
        );
        $full = strpos($src, '$rates->fullUpdate()');
        $hook = strpos($src, 'new BestChangeStoredCourseReassert()');
        $this->assertNotFalse($full);
        $this->assertNotFalse($hook);
        $this->assertGreaterThan($full, $hook);
        $this->assertStringContainsString('reassert settle', $src);
        $this->assertStringContainsString('MIRROR_REASSERT_MAX_AGE_SECONDS', (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/Rates/BestChangeStoredCourseReassert.php'
        ));
    }

    public function test_scheme_files_online_path_uses_gate_show(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Console/Commands/StatusRatesFilesCommand.php'
        );
        $this->assertStringContainsString("RatesXmlMonitorGate::show('scheme:files_online')", $src);
        $this->assertStringContainsString("RatesXmlMonitorGate::show('scheme:files_online_finally')", $src);
        $this->assertStringContainsString('RatesXmlPublicFeedHealth::assertOnlineInvariant', $src);
        $this->assertStringNotContainsString('@unlink(\App\Services\Rates\RatesXmlMonitorGate::FLAG_PATH)', $src);
    }
}

