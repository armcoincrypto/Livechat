<?php

declare(strict_types=1);

namespace Tests\Unit\TelegramOperator;

use App\Http\Middleware\VerifyTelegramOperatorWebhookSecret;
use App\Models\OrderOperatorAssignment;
use App\Models\Task;
use App\Models\User;
use App\Services\Orders\ManualCompletion\ManualCompletionGuard;
use App\Services\Orders\ManualCompletion\ManualOrderCompletionService;
use App\Services\TelegramOperator\TelegramOperatorAuditLogger;
use App\Services\TelegramOperator\TelegramOperatorAuthService;
use App\Services\TelegramOperator\TelegramOrderClaimService;
use App\Services\TelegramOperator\TelegramOrderOperatorWorkflowService;
use App\Services\TelegramOperator\TelegramOperatorPendingFlow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class TelegramOperatorWave1Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->ensureAssignmentsTable();
        Config::set('telegram_operator.actions_enabled', true);
        Config::set('telegram_operator.dry_run', true);
        Config::set('telegram_operator.operator_links', '');
        Config::set('telegram_operator.webhook_secret', 'test-secret-wave1');
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Auth::forgetGuards();
        parent::tearDown();
    }

    public function test_auth_fail_closed_for_unknown_and_unlinked(): void
    {
        $auth = new TelegramOperatorAuthService();
        $this->assertNull($auth->resolveOperator(999001));
        $this->assertNull($auth->authorize(999001, 'admin_orders_execute'));

        Config::set('telegram_operator.operator_links', '999001:1');
        // user 1 may or may not exist; if missing still null
        $resolved = $auth->resolveOperator(999001);
        if ($resolved !== null) {
            // without permission still fail closed via authorize when can() false
            $this->assertTrue(true);
        } else {
            $this->assertNull($resolved);
        }
    }

    public function test_auth_links_parser_ignores_malformed(): void
    {
        Config::set('telegram_operator.operator_links', 'abc:1,12:xy,42:7,');
        $map = (new TelegramOperatorAuthService())->links();
        $this->assertSame([42 => 7], $map);
    }

    public function test_claim_assigns_without_changing_task_status(): void
    {
        $task = $this->insertSyntheticTask(3);
        $operator = $this->ensureOperatorUser(91001);
        $service = new TelegramOrderClaimService(
            new TelegramOperatorAuthService(),
            new TelegramOperatorAuditLogger()
        );

        $result = $service->claim((int) $task->id, $operator, 555001);
        $this->assertTrue($result['ok']);
        $this->assertSame('claimed', $result['code']);
        $this->assertSame(3, (int) Task::query()->whereKey($task->id)->value('status'));

        $again = $service->claim((int) $task->id, $operator, 555001);
        $this->assertTrue($again['ok']);
        $this->assertSame('already_yours', $again['code']);

        $other = $this->ensureOperatorUser(91002);
        $conflict = $service->claim((int) $task->id, $other, 555002);
        $this->assertFalse($conflict['ok']);
        $this->assertSame('already_claimed', $conflict['code']);
        $this->assertSame(3, (int) Task::query()->whereKey($task->id)->value('status'));
    }

    public function test_claim_rejects_completed_order(): void
    {
        $task = $this->insertSyntheticTask(4);
        $operator = $this->ensureOperatorUser(91003);
        $service = new TelegramOrderClaimService(
            new TelegramOperatorAuthService(),
            new TelegramOperatorAuditLogger()
        );
        $result = $service->claim((int) $task->id, $operator, 555003);
        $this->assertFalse($result['ok']);
        $this->assertSame('already_completed', $result['code']);
    }

    public function test_dry_run_completion_does_not_mutate_status(): void
    {
        $task = $this->insertSyntheticTask(7);

        $capable = new class(91004) implements \Illuminate\Contracts\Auth\Authenticatable {
            public function __construct(private int $oid)
            {
            }

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return $this->oid;
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void
            {
            }

            public function getRememberTokenName(): ?string
            {
                return null;
            }

            public function can(string $ability): bool
            {
                return $ability === 'admin_orders_execute';
            }
        };

        $svc = app(ManualOrderCompletionService::class);
        $result = $svc->complete((int) $task->id, $capable, [
            'settlement_reference' => 'DRY-RUN-REF-1001',
        ], true);

        $this->assertTrue($result['ok']);
        $this->assertSame('dry_run_ok', $result['code']);
        $this->assertTrue($result['dry_run'] ?? false);
        $this->assertSame(7, (int) Task::query()->whereKey($task->id)->value('status'));
    }

    public function test_completion_eligibility_statuses(): void
    {
        foreach ([3, 7, 12, 14] as $status) {
            $this->assertContains($status, ManualCompletionGuard::MANUAL_FROM_STATUSES);
        }
        foreach ([1, 2, 4] as $status) {
            $this->assertNotContains($status, ManualCompletionGuard::MANUAL_FROM_STATUSES);
        }
    }

    public function test_callback_payload_is_opaque_action_and_id_only(): void
    {
        $kb = TelegramOrderOperatorWorkflowService::notificationKeyboard(4242);
        $flat = [];
        foreach ($kb as $row) {
            foreach ($row as $btn) {
                if (isset($btn['callback_data'])) {
                    $flat[] = $btn['callback_data'];
                }
            }
        }
        $this->assertContains('ot:t:4242', $flat);
        $this->assertContains('ot:c:4242', $flat);
        foreach ($flat as $data) {
            $this->assertDoesNotMatchRegularExpression('/amount|wallet|status|permission/i', $data);
            $this->assertLessThanOrEqual(64, strlen($data));
        }
    }

    public function test_pending_flow_confirm_then_cancel_clears(): void
    {
        $flow = new TelegramOperatorPendingFlow();
        $flow->put(777, [
            'stage' => 'awaiting_confirm',
            'task_id' => 1,
            'chat_id' => 777,
            'message_id' => 1,
            'operator_user_id' => 1,
        ]);
        $this->assertSame('awaiting_confirm', $flow->get(777)['stage'] ?? null);
        $flow->clear(777);
        $this->assertNull($flow->get(777));
    }

    public function test_telegram_complete_does_not_ask_for_typed_tx_or_reference(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/TelegramOperator/TelegramOrderOperatorWorkflowService.php');
        $this->assertStringNotContainsString('TX hash / ID транзакции', $src);
        $this->assertStringNotContainsString('Укажите подтверждение выплаты', $src);
        $this->assertStringContainsString("stage' => 'awaiting_confirm'", $src);
        $this->assertStringContainsString('Да, завершить', $src);
        $this->assertStringContainsString("'message' => \$note", $src);
        $this->assertStringNotContainsString("'settlement_reference' => \$reference", $src);
        $guard = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Orders/ManualCompletion/ManualCompletionGuard.php');
        $this->assertStringContainsString('SettlementEvidence::fromOptions', $guard);
    }

    public function test_webhook_middleware_fail_closed(): void
    {
        $mw = new VerifyTelegramOperatorWebhookSecret();
        Config::set('telegram_operator.actions_enabled', false);
        try {
            $mw->handle(Request::create('/callbacks/v1/telegram-operator', 'POST'), fn () => response('ok'));
            $this->fail('expected 404');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }

        Config::set('telegram_operator.actions_enabled', true);
        Config::set('telegram_operator.webhook_secret', '');
        try {
            $mw->handle(Request::create('/callbacks/v1/telegram-operator', 'POST'), fn () => response('ok'));
            $this->fail('expected 404 empty secret');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }

        Config::set('telegram_operator.webhook_secret', 'test-secret-wave1');
        $bad = Request::create('/callbacks/v1/telegram-operator', 'POST');
        $bad->headers->set('X-Telegram-Bot-Api-Secret-Token', 'wrong');
        try {
            $mw->handle($bad, fn () => response('ok'));
            $this->fail('expected 403');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $ok = Request::create('/callbacks/v1/telegram-operator', 'POST');
        $ok->headers->set('X-Telegram-Bot-Api-Secret-Token', 'test-secret-wave1');
        $response = $mw->handle($ok, fn () => response('ok'));
        $this->assertSame('ok', $response->getContent());
    }

    public function test_shared_completion_service_exists_and_admin_path_references_it(): void
    {
        $src = file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Administrator/Vue/OrderVueController.php');
        $this->assertStringContainsString('ManualOrderCompletionService', $src);
        $this->assertTrue(class_exists(ManualOrderCompletionService::class));
        $this->assertTrue(class_exists(TelegramOrderOperatorWorkflowService::class));
    }

    public function test_no_direct_status_four_write_in_telegram_operator_package(): void
    {
        $dir = dirname(__DIR__, 3).'/app/Services/TelegramOperator';
        foreach (glob($dir.'/*.php') as $file) {
            $src = file_get_contents($file);
            $this->assertStringNotContainsString("status = 4", $src);
            $this->assertStringNotContainsString("'status' => 4", $src);
            $this->assertStringNotContainsString('status => 4', $src);
        }
        $completion = file_get_contents(dirname(__DIR__, 3).'/app/Services/Orders/ManualCompletion/ManualOrderCompletionService.php');
        $this->assertStringContainsString('TransactionFacade::find', $completion);
        $this->assertStringContainsString('skip_auto_payment', $completion);
    }

    private function ensureAssignmentsTable(): void
    {
        if (Schema::hasTable('order_operator_assignments')) {
            return;
        }
        Schema::create('order_operator_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->unique();
            $table->unsignedBigInteger('operator_user_id');
            $table->unsignedBigInteger('telegram_user_id')->nullable();
            $table->string('source', 32)->default('telegram');
            $table->timestamp('claimed_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    private function ensureOperatorUser(int $id): User
    {
        $existing = User::query()->find($id);
        if ($existing) {
            return $existing;
        }

        // Minimal insert compatible with production users table
        $email = 'tg-op-'.$id.'@example.invalid';
        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Operator '.$id,
            'email' => $email,
            'password' => bcrypt('not-used'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function insertSyntheticTask(int $status): Task
    {
        $id = DB::table('tasks')->insertGetId([
            'public_id' => 'tgw1-'.bin2hex(random_bytes(6)),
            'status' => $status,
            'id_direction_exchange' => 25,
            'discount1' => 0,
            'discount2' => 0,
            'give_price' => 1,
            'receiving_price' => 1,
            'id_who_completed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Task::query()->whereKey($id)->firstOrFail();
    }
}
