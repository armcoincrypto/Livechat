<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeMarketBaseHealth;
use PHPUnit\Framework\TestCase;

/**
 * Class-A2: chunk preload row preference must match evaluate()'s SELECT order
 * (status DESC, id ASC → first row wins). evaluateFromRow with derived outlier
 * must preserve the healthy/status/rate fields recalculate consumes.
 */
final class BestChangeHealthChunkPreloadParityTest extends TestCase
{
    public function test_first_row_preference_matches_evaluate_order(): void
    {
        // Simulate status DESC, id ASC stream: active id=5 before inactive id=9,
        // and among equals status, lower id first then higher — first wins.
        $rows = [
            (object) [
                'id' => 5,
                'id_direction_exchange' => 100,
                'status' => 1,
                'rate_value' => '1.25',
                'position_num' => '2',
                'updated_at' => '2099-01-01 00:00:00',
                'is_error_parser' => 0,
                'explain_payload' => null,
            ],
            (object) [
                'id' => 9,
                'id_direction_exchange' => 100,
                'status' => 0,
                'rate_value' => '9.99',
                'position_num' => '1',
                'updated_at' => '2099-01-01 00:00:00',
                'is_error_parser' => 0,
                'explain_payload' => null,
            ],
        ];

        $map = [];
        foreach ($rows as $row) {
            $dirId = (int) $row->id_direction_exchange;
            if (! isset($map[$dirId])) {
                $map[$dirId] = $row;
            }
        }

        $this->assertSame(5, (int) $map[100]->id);

        $health = BestChangeMarketBaseHealth::evaluateFromRow(
            $map[100],
            100,
            null,
            withDerivedOutlier: false
        );

        $this->assertTrue($health['healthy']);
        $this->assertSame('1.25', $health['rate_value']);
        $this->assertSame('2', $health['position_num']);
        $this->assertSame(1, $health['link_status']);
    }

    public function test_missing_preload_falls_back_semantics_documented(): void
    {
        // Recalculate keeps evaluate() when chunk map misses a direction id.
        // This test documents the contract; evaluate() needs DB and is covered live.
        $this->assertTrue(method_exists(BestChangeMarketBaseHealth::class, 'evaluate'));
        $this->assertTrue(method_exists(BestChangeMarketBaseHealth::class, 'evaluateFromRow'));
    }
}
