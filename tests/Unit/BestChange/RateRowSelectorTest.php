<?php
declare(strict_types=1);

namespace Tests\Unit\BestChange;

use App\Models\BestChangeDirection;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;
use iEXPackages\BestChange\DTO\SelectedRateRow;
use iEXPackages\BestChange\Services\PositionResolver;
use iEXPackages\BestChange\Services\RateRowSelector;
use PHPUnit\Framework\TestCase;

final class RateRowSelectorTest extends TestCase
{
    public function test_position_five_with_three_offers_is_insufficient_depth_not_silent_clamp(): void
    {
        $selector = new RateRowSelector(new PositionResolver());
        $direction = new BestChangeDirection(['position_num' => '5']);
        $policy = new RateSelectionPolicy('rate', 'api', 'position', 5, 600);
        $rows = [
            ['changer' => 1, 'rate' => '0.010'],
            ['changer' => 2, 'rate' => '0.011'],
            ['changer' => 3, 'rate' => '0.012'],
        ];
        $selected = $selector->select($direction, $rows, $policy, 3);
        $this->assertInstanceOf(SelectedRateRow::class, $selected);
        $this->assertSame('position_insufficient_depth', $selected->method);
        $this->assertSame(5, $selected->position);
        $this->assertSame([], $selected->row);
    }

    public function test_position_two_selects_second_offer(): void
    {
        $selector = new RateRowSelector(new PositionResolver());
        $direction = new BestChangeDirection(['position_num' => '2']);
        $policy = new RateSelectionPolicy('rate', 'api', 'position', 5, 600);
        $rows = [
            ['changer' => 11, 'rate' => '0.010'],
            ['changer' => 22, 'rate' => '0.011'],
            ['changer' => 33, 'rate' => '0.012'],
        ];
        $selected = $selector->select($direction, $rows, $policy, 3);
        $this->assertSame('position', $selected->method);
        $this->assertSame(2, $selected->position);
        $this->assertSame(22, $selected->row['changer']);
    }
}
