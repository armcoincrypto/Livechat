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
    }

    public function test_profit_position_and_unrelated_gaps_are_not_selected(): void
    {
        $this->assertFalse(BestChangeStoredCourseReassert::isStaleManualMirror(
            '45.885766874751985357',
            '44.872610842',
            '46.07009',
            '0.4',
        ));
        $this->assertFalse(BestChangeStoredCourseReassert::isStaleManualMirror(
            '1.5984',
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
        $this->assertStringContainsString("RatesXmlMonitorGate::show('scheme:files_online_post')", $src);
        $this->assertStringNotContainsString('@unlink(\App\Services\Rates\RatesXmlMonitorGate::FLAG_PATH)', $src);
    }
}

