<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use App\Http\Controllers\Administrator\Vue\LayoutVueController;
use App\Http\Controllers\Administrator\Vue\OrderVueController;
use App\Http\Resources\Admin\Orders\LiveOrdersRowMapper;
use App\Http\Resources\Admin\Orders\OrdersListRowMapper;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class Wave4AdminBackendContractTest extends TestCase
{
    public function test_live_orders_soft_deleted_direction_does_not_500(): void
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
        $this->assertNotNull($id, 'need historical soft-deleted direction task');

        $task = Task::with(\App\Http\Resources\Admin\Orders\HistoricalOrderRelationConstraints::forLiveOrders())
            ->findOrFail($id);
        $row = LiveOrdersRowMapper::map($task, $admin->id);
        $this->assertSame((int) $id, (int) $row['id']);
        $this->assertIsString($row['name']);
        $this->assertIsString($row['amount']);
        foreach ($row['operators'] as $op) {
            $this->assertArrayNotHasKey('email', $op);
        }

        $ctrl = app(OrderVueController::class);
        $resp = $ctrl->liveOrders();
        $this->assertSame(200, $resp->getStatusCode());
        $json = json_decode($resp->getContent(), true);
        $this->assertIsArray($json['orders'] ?? null);
        foreach ($json['orders'] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                foreach ($item['operators'] ?? [] as $op) {
                    $this->assertArrayNotHasKey('email', $op);
                }
            }
        }
    }

    public function test_live_orders_retired_currency_null_safe(): void
    {
        $task = Task::query()->orderByDesc('id')->first();
        $this->assertNotNull($task);
        $direction = new class {
            public $currency1 = null;
            public $currency2 = null;
            public $id = 0;
            public $tech_name = null;
        };
        $task->setRelation('direction_exchange', $direction);
        $task->setRelation('task_operators', collect());
        $row = LiveOrdersRowMapper::map($task, 1);
        $this->assertSame($task->id, $row['id']);
        $this->assertNotSame('', $row['name']);
    }

    public function test_list_row_omits_customer_email(): void
    {
        $task = Task::query()->with(['user', 'meta', 'task_info', 'task_status', 'task_operators.user', 'direction_exchange.currency1.payment'])->orderByDesc('id')->first();
        $this->assertNotNull($task);
        $row = OrdersListRowMapper::map($task);
        $this->assertArrayNotHasKey('email', $row['attributes']['user']);
        $this->assertArrayHasKey('name', $row['attributes']['user']);
    }

    public function test_unauthenticated_admin_json_is_401(): void
    {
        Auth::logout();
        $request = Request::create('/iexadmin/frontend-api/vue/liveOrders', 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $response = app()->handle($request);
        $this->assertSame(401, $response->getStatusCode(), substr((string) $response->getContent(), 0, 300));
        $this->assertStringContainsString('json', strtolower((string) $response->headers->get('Content-Type')));
        $json = json_decode($response->getContent(), true);
        $this->assertSame(1, $json['status'] ?? null);
    }

    public function test_unauthorized_live_orders_is_403(): void
    {
        $base = User::permission('allow_admin')->orderBy('id')->first() ?: User::permission('admin_tasks')->orderBy('id')->first();
        $this->assertNotNull($base);
        $user = Wave4DenyTasksUser::query()->whereKey($base->id)->first();
        $this->assertFalse($user->can('admin_tasks'));
        Auth::login($user);
        $request = Request::create('/iexadmin/frontend-api/vue/liveOrders', 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);
        $response = app()->handle($request);
        $this->assertSame(403, $response->getStatusCode(), substr((string) $response->getContent(), 0, 400));
        $json = json_decode($response->getContent(), true);
        $this->assertSame(1, $json['status'] ?? null);
    }

    public function test_autopay_toggle_unauthorized_rejected_and_stays_off(): void
    {
        $before = (int) iEXSetting('is_enabled_autopay_cron', 0);
        $this->assertSame(0, $before, 'autopay must remain OFF for this test');

        $base = User::permission('admin_tasks')->orderBy('id')->first();
        $this->assertNotNull($base);
        $user = Wave4DenyAutopayUser::query()->whereKey($base->id)->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->can('admin_autopayment'));
        Auth::login($user);

        $req = Request::create('/iexadmin/frontend-api/vue/layoutSettings', 'POST', [
            'settings' => ['is_enabled_autopay_cron' => 1],
        ]);
        $req->setUserResolver(fn () => $user);
        $resp = app(LayoutVueController::class)->postLayoutSettings($req);
        $this->assertSame(403, $resp->getStatusCode());
        $this->assertSame(0, (int) iEXSetting('is_enabled_autopay_cron', 0));
    }
}

final class Wave4DenyAutopayUser extends User
{
    protected $table = 'users';

    public function can($abilities, $arguments = []): bool
    {
        if ($abilities === 'admin_autopayment') {
            return false;
        }

        return parent::can($abilities, $arguments);
    }
}

final class Wave4DenyTasksUser extends User
{
    protected $table = 'users';

    public function can($abilities, $arguments = []): bool
    {
        if ($abilities === 'admin_tasks') {
            return false;
        }

        return parent::can($abilities, $arguments);
    }
}

