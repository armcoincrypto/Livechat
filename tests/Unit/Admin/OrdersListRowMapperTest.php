<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use App\Http\Resources\Admin\Orders\OrdersListRowMapper;
use App\Http\Resources\Admin\Orders\OrdersLiveResources;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OrdersListRowMapperTest extends TestCase
{
    public function test_null_direction_does_not_throw(): void
    {
        $task = Task::query()->orderByDesc('id')->first();
        $this->assertNotNull($task);

        $task->setRelation('direction_exchange', null);
        $task->setRelation('task_operators', collect());
        $task->loadMissing(['task_status', 'user', 'meta', 'task_info']);

        $row = OrdersListRowMapper::map($task);
        $this->assertSame($task->id, $row['id']);
        $this->assertNull($row['attributes']['direction']['id']);
        $this->assertNotSame('', (string) $row['attributes']['direction']['name']);
    }

    public function test_soft_deleted_direction_serializes_in_live_collection(): void
    {
        $id = DB::table('tasks as t')
            ->join('direction_exchange as d', 'd.id', '=', 't.id_direction_exchange')
            ->whereNotNull('d.deleted_at')
            ->orderByDesc('t.id')
            ->value('t.id');

        if (! $id) {
            $this->markTestSkipped('no soft-deleted direction tasks');
        }

        $task = Task::with([
            'meta', 'task_info', 'user', 'task_status', 'task_operators.user',
            'direction_exchange' => fn ($q) => $q->withTrashed(),
            'direction_exchange.currency1.code_currency',
            'direction_exchange.currency1.payment',
            'direction_exchange.currency2.code_currency',
            'direction_exchange.currency2.payment',
        ])->findOrFail($id);

        $res = (new OrdersLiveResources(collect([$task])))->toArray(Request::create('/'));
        $this->assertCount(1, $res['data']);
        $this->assertNotNull($res['data'][0]['attributes']['direction']['id']);
    }
}
