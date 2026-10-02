<?php

declare(strict_types=1);

namespace Tests\Unit\BestChange;

use App\Models\BestChangeDirection;
use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;
use iEXPackages\BestChange\Services\AntiFakeScoreService;
use iEXPackages\BestChange\Services\AutoBlacklistService;
use iEXPackages\BestChange\Services\ExchangerPoolService;
use iEXPackages\BestChange\Services\PositionResolver;
use iEXPackages\BestChange\Services\RateRowFilter;
use iEXPackages\BestChange\Services\RateRowSelector;
use iEXPackages\BestChange\Services\RateRowSorter;
use PHPUnit\Framework\TestCase;

/**
 * Own BestChange listing must never be a market price source.
 */
final class OwnExchangerSelfExclusionTest extends TestCase
{
    private const OWN_ID = 1522;

    public function test_own_exchanger_at_pos1_excluded_next_becomes_pos1(): void
    {
        [$filter, $sorter, $selector, $settings] = $this->pipeline(ownId: self::OWN_ID);
        $dir = $this->direction(position: '1');
        $policy = $this->policy();

        $rows = [
            $this->row(self::OWN_ID, '1.00', 1000),
            $this->row(1001, '0.99', 900),
            $this->row(1002, '0.98', 800),
        ];

        $reject = [];
        $filtered = $filter->filter($rows, $dir, $policy, [], $reject);
        $this->assertSame(2, count($filtered));
        $this->assertArrayHasKey('own_exchanger', $reject);

        $sorted = $sorter->sort($filtered, $policy);
        $selected = $selector->select($dir, $sorted, $policy, 1);
        $this->assertNotNull($selected);
        // sortOrder api→asc: lowest rate first
        $this->assertSame(1002, (int) ($selected->row['changer'] ?? 0));
        $this->assertSame(1, $selected->position);
    }

    public function test_own_exchanger_at_pos5_excluded_before_position(): void
    {
        [$filter, $sorter, $selector] = $this->pipeline(ownId: self::OWN_ID);
        $dir = $this->direction(position: '5');
        $policy = $this->policy();

        // Asc order by rate; own sits at natural rank 3 before exclusion.
        $rows = [
            $this->row(1001, '1.05', 1000),
            $this->row(1002, '1.04', 900),
            $this->row(1003, '1.03', 800),
            $this->row(1004, '1.02', 700),
            $this->row(self::OWN_ID, '1.01', 600),
            $this->row(1006, '1.00', 500),
            $this->row(1007, '0.99', 400),
        ];

        $filtered = $filter->filter($rows, $dir, $policy, []);
        $sorted = $sorter->sort($filtered, $policy);
        $this->assertCount(6, $sorted);
        $selected = $selector->select($dir, $sorted, $policy, 1);
        $this->assertNotNull($selected);
        // Without self: 0.99,1.00,1.02,1.03,1.04,1.05 → pos5 = 1002
        $this->assertSame(1002, (int) ($selected->row['changer'] ?? 0));
        $this->assertSame(5, $selected->position);
    }

    public function test_multiple_self_rows_all_removed(): void
    {
        [$filter] = $this->pipeline(ownId: self::OWN_ID);
        $dir = $this->direction(position: '1');
        $policy = $this->policy();
        $rows = [
            $this->row(self::OWN_ID, '1.00', 1000),
            $this->row(self::OWN_ID, '0.99', 900),
            $this->row(1001, '0.98', 800),
        ];
        $reject = [];
        $filtered = $filter->filter($rows, $dir, $policy, [], $reject);
        $this->assertCount(1, $filtered);
        $this->assertSame(2, $reject['own_exchanger'] ?? 0);
        $this->assertSame(1001, (int) $filtered[0]['changer']);
    }

    public function test_no_self_rows_behavior_unchanged(): void
    {
        [$filter, $sorter, $selector] = $this->pipeline(ownId: self::OWN_ID);
        $dir = $this->direction(position: '2');
        $policy = $this->policy();
        $rows = [
            $this->row(1001, '1.00', 1000),
            $this->row(1002, '0.99', 900),
            $this->row(1003, '0.98', 800),
        ];
        $filtered = $filter->filter($rows, $dir, $policy, []);
        $sorted = $sorter->sort($filtered, $policy);
        $selected = $selector->select($dir, $sorted, $policy, 1);
        $this->assertSame(1002, (int) ($selected->row['changer'] ?? 0));
    }

    public function test_insufficient_depth_after_self_removal_returns_null_selection(): void
    {
        [$filter, $sorter, $selector] = $this->pipeline(ownId: self::OWN_ID);
        $dir = $this->direction(position: '3');
        $policy = $this->policy();
        // Only self + one peer → after exclusion depth=1 < requested 3 → fail-closed insufficient depth
        $rows = [
            $this->row(self::OWN_ID, '1.00', 1000),
            $this->row(1001, '0.99', 900),
        ];
        $filtered = $filter->filter($rows, $dir, $policy, []);
        $sorted = $sorter->sort($filtered, $policy);
        $this->assertCount(1, $sorted);
        $selected = $selector->select($dir, $sorted, $policy, 1);
        $this->assertNotNull($selected);
        $this->assertSame('position_insufficient_depth', $selected->method);
        $this->assertSame(3, $selected->position);
        $this->assertSame([], $selected->row);
    }

    public function test_own_exchanger_id_zero_disables_invariant_for_tests(): void
    {
        [$filter] = $this->pipeline(ownId: 0);
        $dir = $this->direction(position: '1');
        $policy = $this->policy();
        $rows = [$this->row(self::OWN_ID, '1.00', 1000)];
        $filtered = $filter->filter($rows, $dir, $policy, []);
        $this->assertCount(1, $filtered);
    }

    /**
     * @return array{0:RateRowFilter,1:RateRowSorter,2:RateRowSelector,3:BestChangeConfig}
     */
    private function pipeline(int $ownId): array
    {
        $settings = $this->createMock(BestChangeConfig::class);
        $settings->method('ownExchangerId')->willReturn($ownId);
        $settings->method('antiFakeEnabled')->willReturn(false);
        $settings->method('antiFakeMinScore')->willReturn(0);

        $pool = $this->createMock(ExchangerPoolService::class);
        $pool->method('resolve')->willReturn([
            'mode' => 'off',
            'preferred' => [],
            'excluded' => [],
        ]);

        $auto = $this->createMock(AutoBlacklistService::class);
        $auto->method('isBlocked')->willReturn(false);

        $anti = $this->createMock(AntiFakeScoreService::class);

        $filter = new RateRowFilter($anti, $settings, $pool, $auto);
        $sorter = new RateRowSorter();
        $selector = new RateRowSelector(new PositionResolver());

        return [$filter, $sorter, $selector, $settings];
    }

    private function direction(string $position): BestChangeDirection
    {
        $d = new BestChangeDirection();
        $d->id = 1;
        $d->position_num = $position;
        $d->rate_mode = 'position';
        $d->top_n = 5;
        $d->blacklist_ids = null;
        $d->whitelist_ids = null;
        $d->min_reserve = '0';
        $d->max_reserve = '0';

        return $d;
    }

    private function policy(): RateSelectionPolicy
    {
        return new RateSelectionPolicy(
            typeField: 'rate',
            sortOrder: 'api',
            mode: 'position',
            topN: 5,
            positionWindowSeconds: 600,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function row(int $changer, string $rate, float $reserve): array
    {
        return [
            'changer' => $changer,
            'rate' => $rate,
            'rankrate' => $rate,
            'reserve' => $reserve,
        ];
    }
}
