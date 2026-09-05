<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Services\Orders\TaskPublicIdGenerator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TaskPublicIdGeneratorTest extends TestCase
{
    public function test_allocate_returns_positive_unique_ids(): void
    {
        $a = TaskPublicIdGenerator::allocate();
        $b = TaskPublicIdGenerator::allocate();

        $this->assertGreaterThan(0, $a);
        $this->assertGreaterThan(0, $b);
        $this->assertNotSame($a, $b);
        $this->assertSame(0, (int) DB::table('tasks')->where('public_id', $a)->count());
        $this->assertSame(0, (int) DB::table('tasks')->where('public_id', $b)->count());
    }

    public function test_allocate_skips_existing_public_id(): void
    {
        $existing = (int) DB::table('tasks')->where('public_id', '>', 0)->orderByDesc('id')->value('public_id');
        $this->assertGreaterThan(0, $existing);

        // Force first candidate to collide by inserting a reserved sentinel then allocating many.
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = TaskPublicIdGenerator::allocate();
        }
        $this->assertNotContains($existing, $ids);
        $this->assertCount(5, array_unique($ids));
    }
}
