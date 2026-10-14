<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use App\Services\Rates\BestChangeCompilerGenerationContext;
use App\Services\Rates\BestChangeL1Shadow;
use App\Services\Rates\BestChangeTopNBookHelper;
use iEXPackages\BestChange\Services\DirectionExchangeRecalculateService;
use PHPUnit\Framework\TestCase;

final class BestChangeL1ShadowParityTest extends TestCase
{
    public function test_normalize_for_topn_is_deterministic_and_sorted_asc(): void
    {
        $raw = [
            ['changer' => 3, 'rate' => '0.9995', 'reserve' => '100', 'noise' => 'x'],
            ['changer' => 1, 'rate' => '0.9980', 'reserve' => '50'],
            ['changer' => 2, 'rate' => '0.9990'],
            ['changer' => 0, 'rate' => '0.997'], // dropped
            ['changer' => 9, 'rate' => '0'], // dropped
        ];
        $a = BestChangeTopNBookHelper::normalizeForTopN($raw);
        $b = BestChangeTopNBookHelper::normalizeForTopN(array_reverse($raw));
        $this->assertSame(
            BestChangeTopNBookHelper::fingerprint($raw),
            BestChangeTopNBookHelper::fingerprint(array_reverse($raw))
        );
        $this->assertCount(3, $a);
        $this->assertSame(1, $a[0]['changer']);
        $this->assertSame(2, $a[1]['changer']);
        $this->assertSame(3, $a[2]['changer']);
        $this->assertTrue(BestChangeTopNBookHelper::topNRelevantEqual($a, $b));
    }

    public function test_batch_book_and_fresh_book_same_rows_are_equivalent(): void
    {
        $batch = [
            ['changer' => 10, 'rate' => '1.01', 'rankrate' => '1.02'],
            ['changer' => 11, 'rate' => '1.00'],
        ];
        // marketForDirection rawRows shape — extra keys ignored for Top-N.
        $fresh = [
            ['changer' => 11, 'rate' => '1.00', 'reserve' => '999'],
            ['changer' => 10, 'rate' => '1.01', 'presence' => 'ignored'],
        ];
        $this->assertTrue(BestChangeTopNBookHelper::topNRelevantEqual($batch, $fresh));
    }

    public function test_generation_context_round_trip(): void
    {
        $ctx = new BestChangeCompilerGenerationContext();
        $ctx->beginGeneration();
        $ctx->putBatchBooks(
            ['42-93' => [['changer' => 1, 'rate' => '0.99']]],
            [100 => '42-93'],
            [100 => 500]
        );
        $this->assertTrue($ctx->isActive());
        $this->assertSame('42-93', $ctx->pairKeyForDirectionExchange(500));
        $rows = $ctx->rawRowsForDirectionExchange(500);
        $this->assertIsArray($rows);
        $this->assertSame(1, (int) $rows[0]['changer']);
        $ctx->endGeneration();
        $this->assertFalse($ctx->isActive());
        $this->assertNull($ctx->rawRowsForDirectionExchange(500));
    }

    public function test_de_semantic_identical_skips_heartbeat_only(): void
    {
        $ex = new DirectionExchange();
        $ex->course_value = '1.2345';
        $ex->exchange_rate = '1.2345 BTC';
        $ex->is_error_rate = 0;
        $ex->error_rate_text = null;
        $ex->parser_source_name = 'BestChange';

        $same = [
            'course_value' => '1.2345',
            'exchange_rate' => '1.2345 BTC',
            'is_error_rate' => 0,
            'error_rate_text' => null,
            'parser_source_name' => 'BestChange',
            'updated_at' => '2099-01-01 00:00:00',
        ];
        $this->assertTrue(DirectionExchangeRecalculateService::deSemanticFieldsIdentical($ex, $same));

        $changed = $same;
        $changed['course_value'] = '1.2346';
        $this->assertFalse(DirectionExchangeRecalculateService::deSemanticFieldsIdentical($ex, $changed));
    }

    public function test_shadow_flags_default_off(): void
    {
        // env may be unset in unit context
        putenv('BESTCHANGE_L1_SHADOW_ENABLED');
        putenv('BESTCHANGE_L1_SKIP_IDENTICAL_DE_UPSERT');
        $_ENV['BESTCHANGE_L1_SHADOW_ENABLED'] = false;
        $_ENV['BESTCHANGE_L1_SKIP_IDENTICAL_DE_UPSERT'] = false;
        $this->assertFalse(BestChangeL1Shadow::shadowEnabled());
        $this->assertFalse(BestChangeL1Shadow::skipIdenticalDeUpsertEnabled());
    }

    public function test_classify_timing_race_when_books_differ(): void
    {
        $c = BestChangeL1Shadow::classifyTopNMismatch(
            false,
            '1.0',
            '1.1',
            ['reason' => 'a', 'applied' => false],
            ['reason' => 'b', 'applied' => false],
        );
        $this->assertFalse($c['match']);
        $this->assertSame('TIMING_RACE', $c['class']);
    }

    public function test_classify_ok_when_books_and_course_match(): void
    {
        $c = BestChangeL1Shadow::classifyTopNMismatch(
            true,
            '1.001',
            '1.001',
            ['reason' => 'already_at_or_better_than_target', 'applied' => false],
            ['reason' => 'already_at_or_better_than_target', 'applied' => false],
        );
        $this->assertTrue($c['match']);
        $this->assertSame('OK', $c['class']);
    }
}
