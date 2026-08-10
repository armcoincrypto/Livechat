<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use App\Http\Controllers\Administrator\Orders\OrdersController;
use App\Http\Resources\Admin\Orders\OrdersListRowMapper;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class OrdersListSoftDeletedDirectionContractTest extends TestCase
{
    public function test_handler_and_all_include_soft_deleted_direction_tasks_without_500(): void
    {
        Event::fake();
        $admin = User::permission('admin_tasks')->orderBy('id')->first() ?: User::orderBy('id')->first();
        $this->assertNotNull($admin);
        Auth::login($admin);

        $id = DB::table('tasks as t')
            ->join('direction_exchange as d', 'd.id', '=', 't.id_direction_exchange')
            ->whereNotNull('d.deleted_at')
            ->orderByDesc('t.id')
            ->value('t.id');
        $this->assertNotNull($id, 'need at least one historical soft-deleted direction task');

        $ctrl = app(OrdersController::class);

        foreach (['handler', 'all'] as $tab) {
            $req = Request::create('/iexadmin/frontend-api/orders/list', 'GET', [
                'tabValue' => $tab,
                'per_page' => 20,
            ]);
            $req->setUserResolver(fn () => $admin);
            $req->setLaravelSession(app('session.store'));
            $resp = $ctrl->index($req);
            $this->assertSame(200, $resp->getStatusCode(), $tab);
            $json = json_decode($resp->getContent(), true);
            $this->assertIsArray($json['items']['data'] ?? null, $tab.' items.data');
            $this->assertNotEmpty($json['items']['data'], $tab.' should not be empty given DB truth');
            if ($tab === 'all') {
                $this->assertArrayHasKey('total', $json['items']);
                $this->assertArrayHasKey('per_page', $json['items']);
                $this->assertArrayHasKey('current_page', $json['items']);
                $this->assertGreaterThan(0, (int) $json['items']['total']);
            }
        }

        $task = Task::with([
            'meta', 'task_info', 'user', 'task_status', 'task_operators.user',
            'direction_exchange.currency1.code_currency',
            'direction_exchange.currency1.payment',
            'direction_exchange.currency2.code_currency',
            'direction_exchange.currency2.payment',
        ])->findOrFail($id);
        $row = OrdersListRowMapper::map($task);
        $this->assertSame((int) $id, (int) $row['id']);
        $this->assertNotNull($row['attributes']['direction']['id']);
    }

    public function test_order_detail_loads_soft_deleted_direction_and_currency(): void
    {
        Event::fake();
        $admin = User::permission('admin_tasks')->orderBy('id')->first() ?: User::orderBy('id')->first();
        Auth::login($admin);

        $id = DB::table('tasks as t')
            ->join('direction_exchange as d', 'd.id', '=', 't.id_direction_exchange')
            ->whereNotNull('d.deleted_at')
            ->orderByDesc('t.id')
            ->value('t.id');
        $this->assertNotNull($id);

        $ctrl = app(OrdersController::class);
        $req = Request::create('/iexadmin/frontend-api/orders/'.$id, 'GET');
        $req->setUserResolver(fn () => $admin);
        $req->setLaravelSession(app('session.store'));
        $resp = $ctrl->show((int) $id, $req);
        $content = method_exists($resp, 'getContent') ? $resp->getContent() : json_encode($resp);
        $json = json_decode($content, true);
        $attrs = $json['attributes'] ?? $json;
        $this->assertNotEmpty($attrs['direction_exchange']['tech_name'] ?? null);
        $this->assertTrue((bool) ($attrs['direction_exchange']['is_archived'] ?? false));
        $this->assertNotSame('', (string) ($attrs['in_currency']['name'] ?? ''));
    }
}
