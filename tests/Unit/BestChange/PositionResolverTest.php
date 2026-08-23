<?php
declare(strict_types=1);

namespace Tests\Unit\BestChange;

use App\Models\BestChangeDirection;
use iEXPackages\BestChange\Services\PositionResolver;
use PHPUnit\Framework\TestCase;

final class PositionResolverTest extends TestCase
{
    public function test_positions_are_one_based(): void
    {
        $resolver = new PositionResolver();
        $this->assertSame(1, $resolver->resolve($this->dir('1'), 8, 3, 600));
        $this->assertSame(2, $resolver->resolve($this->dir('2'), 8, 3, 600));
        $this->assertSame(5, $resolver->resolve($this->dir('5'), 8, 3, 600));
    }

    public function test_legacy_resolve_clamps_when_book_is_shallower_than_position(): void
    {
        $resolver = new PositionResolver();
        $direction = $this->dir('5');
        $this->assertSame(3, $resolver->resolve($direction, 3, 3, 600));
        $this->assertSame(5, $resolver->requestedPosition($direction, 3, 600));
    }

    private function dir(string $position): BestChangeDirection
    {
        return new BestChangeDirection(['position_num' => $position]);
    }
}
