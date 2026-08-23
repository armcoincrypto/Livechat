<?php
declare(strict_types=1);

namespace Tests\Unit\BestChange;

use PHPUnit\Framework\TestCase;

final class RatesUpdateServiceSafetyContractTest extends TestCase
{
    public function test_fetch_failure_returns_without_upsert(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/packages/BestChange/Services/RatesUpdateService.php');
        $this->assertStringContainsString('fetchRatesBatch', $src);
        $this->assertStringContainsString('не затираем курсы ошибкой сети', $src);
        $this->assertMatchesRegularExpression('/catch \(\\\\Throwable\) \{[\s\S]*?return new UpdateResult\(0,/s', $src);
    }

    public function test_empty_filtered_book_zeros_parser_rate_not_course_value_columns(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/packages/BestChange/Services/RatesUpdateService.php');
        $this->assertStringContainsString('function errorUpdateRow', $src);
        $this->assertStringContainsString("'rate_value' => '0'", $src);

        $recalc = (string) file_get_contents(dirname(__DIR__, 3) . '/packages/BestChange/Services/DirectionExchangeRecalculateService.php');
        $this->assertStringContainsString("if (!\$health['healthy'])", $recalc);
        $this->assertStringContainsString("['is_error_rate', 'error_rate_text', 'parser_source_name', 'updated_at']", $recalc);
        $this->assertStringContainsString("['course_value', 'exchange_rate', 'is_error_rate', 'error_rate_text', 'parser_source_name', 'updated_at']", $recalc);
        $successNeedle = "['course_value', 'exchange_rate', 'is_error_rate', 'error_rate_text', 'parser_source_name', 'updated_at']";
        $errorNeedle = "['is_error_rate', 'error_rate_text', 'parser_source_name', 'updated_at']";
        $this->assertNotFalse(strpos($recalc, $successNeedle));
        $this->assertNotFalse(strpos($recalc, $errorNeedle));
        $this->assertTrue(strpos($recalc, $errorNeedle) > strpos($recalc, $successNeedle));
    }

    public function test_insufficient_depth_retains_last_good_rate_value(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/packages/BestChange/Services/RatesUpdateService.php');
        $this->assertStringContainsString('position_insufficient_depth', $src);
        $this->assertStringContainsString('retainLastGoodParserWarning', $src);
        $this->assertStringContainsString('bestchange.insufficient_market_depth', $src);
        $this->assertStringContainsString("(string) (\$direction->rate_value ?? '0')", $src);
        $this->assertStringContainsString("'is_error_parser' => 1", $src);
    }

    public function test_upsert_does_not_overwrite_admin_configuration_fields(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/packages/BestChange/Services/RatesUpdateService.php');
        $this->assertStringContainsString("['rate_value', 'rate_value_without_step', 'source_name', 'is_error_parser', 'explain_payload']", $src);
        $this->assertStringNotContainsString("'position_num'", $src);
        $this->assertStringNotContainsString("'rate_mode'", $src);
        $this->assertStringNotContainsString("'formula_value'", $src);
        $this->assertDoesNotMatchRegularExpression("/upsert\([\s\S]*'profit'/", $src);
    }
}
