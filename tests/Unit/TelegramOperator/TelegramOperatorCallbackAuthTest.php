<?php

declare(strict_types=1);

namespace Tests\Unit\TelegramOperator;

use App\Models\User;
use App\Services\TelegramOperator\TelegramOperatorAuthService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Callback authorization: Telegram identity → mapped operator → Spatie permission.
 * Least privilege: never bypass can(); heal stale permission cache once.
 */
final class TelegramOperatorCallbackAuthTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_authorized_mapped_operator_is_allowed(): void
    {
        Config::set('telegram_operator.operator_links', '555001:91001');
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 91001;
        $user->shouldReceive('fresh')->andReturnSelf();
        $user->shouldReceive('can')->with('admin_orders_execute')->andReturn(true);

        $auth = $this->authResolving(555001, $user);
        $this->assertSame($user, $auth->authorize(555001, 'admin_orders_execute'));
    }

    public function test_unauthorized_mapped_user_is_denied(): void
    {
        Config::set('telegram_operator.operator_links', '555002:91002');
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 91002;
        $user->shouldReceive('fresh')->andReturnSelf();
        $user->shouldReceive('can')->with('admin_orders_execute')->andReturn(false);

        Log::spy();
        $auth = $this->authResolving(555002, $user);
        $this->assertNull($auth->authorize(555002, 'admin_orders_execute'));
        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => $msg === 'telegram_operator_auth_forbidden')
            ->once();
    }

    public function test_unmapped_telegram_user_is_denied(): void
    {
        Config::set('telegram_operator.operator_links', '555003:91003');
        $auth = new TelegramOperatorAuthService();
        $this->assertNull($auth->authorize(999999, 'admin_orders_execute'));
    }

    public function test_wrong_telegram_identity_cannot_use_another_operators_mapping(): void
    {
        Config::set('telegram_operator.operator_links', '555004:91004');
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 91004;
        $user->shouldReceive('can')->never();

        $auth = $this->authResolving(555004, $user);
        $this->assertNull($auth->authorize(555999, 'admin_orders_execute'));
    }

    public function test_stale_permission_cache_heals_once_then_allows(): void
    {
        Config::set('telegram_operator.operator_links', '555005:91005');
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 91005;
        $user->shouldReceive('fresh')->andReturnSelf();
        $user->shouldReceive('can')->with('admin_orders_execute')->andReturn(false, true);

        Log::spy();
        $auth = $this->authResolving(555005, $user);
        $this->assertSame($user, $auth->authorize(555005, 'admin_orders_execute'));
        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => $msg === 'telegram_operator_auth_cache_healed')
            ->once();
        Log::shouldNotHaveReceived('warning', ['telegram_operator_auth_forbidden']);
    }

    public function test_permission_lost_after_heal_still_denied(): void
    {
        Config::set('telegram_operator.operator_links', '555006:91006');
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 91006;
        $user->shouldReceive('fresh')->andReturnSelf();
        $user->shouldReceive('can')->with('admin_orders_execute')->andReturn(false, false);

        $auth = $this->authResolving(555006, $user);
        $this->assertNull($auth->authorize(555006, 'admin_orders_execute'));
    }

    public function test_claim_and_complete_permissions_both_gate_on_admin_orders_execute_by_default(): void
    {
        $cfg = (string) file_get_contents(dirname(__DIR__, 3).'/config/telegram_operator.php');
        $this->assertStringContainsString("'claim_permission' => 'admin_orders_execute'", $cfg);
        $this->assertStringContainsString("'complete_permission' => 'admin_orders_execute'", $cfg);

        $workflow = (string) file_get_contents(
            dirname(__DIR__, 3).'/app/Services/TelegramOperator/TelegramOrderOperatorWorkflowService.php'
        );
        $this->assertStringContainsString('claim_permission', $workflow);
        $this->assertStringContainsString('complete_permission', $workflow);
        $this->assertStringContainsString("reason' => 'unauthorized'", $workflow);
    }

    /**
     * @return TelegramOperatorAuthService
     */
    private function authResolving(int $telegramUserId, User $user): TelegramOperatorAuthService
    {
        return new class($telegramUserId, $user) extends TelegramOperatorAuthService
        {
            public function __construct(private int $tg, private User $user) {}

            public function resolveOperator(int $telegramUserId): ?User
            {
                return $telegramUserId === $this->tg ? $this->user : null;
            }
        };
    }
}
