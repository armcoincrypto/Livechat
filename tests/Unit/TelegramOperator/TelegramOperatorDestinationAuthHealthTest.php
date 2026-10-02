<?php

declare(strict_types=1);

namespace Tests\Unit\TelegramOperator;

use App\Models\User;
use App\Services\TelegramOperator\TelegramOperatorAuthService;
use App\Services\TelegramOperator\TelegramOperatorDestinationHealth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Spatie permission-cache hardening for Telegram operator destinations.
 * Does not weaken authorization; never asserts unauthorized sends.
 */
final class TelegramOperatorDestinationAuthHealthTest extends TestCase
{
    private string $statePath;

    private string $jsonlPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statePath = storage_path('app/telegram_operator_destination_health_state.json');
        $this->jsonlPath = storage_path('logs/telegram_operator_destination_health.jsonl');
        @unlink($this->statePath);
        @unlink($this->jsonlPath);
        Config::set('telegram_operator.complete_permission', 'admin_orders_execute');
        Config::set('telegram_operator.notify_operator_dms', true);
    }

    protected function tearDown(): void
    {
        @unlink($this->statePath);
        @unlink($this->jsonlPath);
        Mockery::close();
        parent::tearDown();
    }

    public function test_classifier_distinguishes_no_configuration_authorization_and_bot_send(): void
    {
        $h = new TelegramOperatorDestinationHealth();
        $this->assertSame(
            TelegramOperatorDestinationHealth::CLASS_NO_CONFIGURATION,
            $h->classifyEmptyDestinations([], [], false)
        );
        $this->assertSame(
            TelegramOperatorDestinationHealth::CLASS_NO_CONFIGURATION,
            $h->classifyEmptyDestinations([], [], true)
        );
        $this->assertSame(
            TelegramOperatorDestinationHealth::CLASS_AUTHORIZATION_EMPTY,
            $h->classifyEmptyDestinations([6155 => 1], [], true)
        );
        $this->assertSame(
            TelegramOperatorDestinationHealth::CLASS_BOT_SEND_FAILURE,
            $h->classifyEmptyDestinations([6155 => 1], [6155], true, 'api_error')
        );
        $this->assertSame(
            TelegramOperatorDestinationHealth::CLASS_OK,
            $h->classifyEmptyDestinations([6155 => 1], [6155], true)
        );
    }

    public function test_auth_empty_event_is_deduped_until_recovery(): void
    {
        Log::spy();
        $h = new TelegramOperatorDestinationHealth();
        $ctx = [
            'permission' => 'admin_orders_execute',
            'links' => [6332166155 => 1],
            'authorized_ids' => [],
            'configured_link_count' => 1,
            'authorized_count' => 0,
        ];
        $h->recordAuthEmpty($ctx);
        $h->recordAuthEmpty($ctx);
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => $msg === TelegramOperatorDestinationHealth::EVENT_AUTH_EMPTY)
            ->once();

        $state = json_decode((string) file_get_contents($this->statePath), true);
        $this->assertSame('AUTH_EMPTY', $state['status']);
        $this->assertSame(1, $state['telegram_operator_auth_empty_total']);

        $h->recordAuthRecovered([
            'permission' => 'admin_orders_execute',
            'links' => [6332166155 => 1],
            'authorized_ids' => [6332166155],
            'configured_link_count' => 1,
            'authorized_count' => 1,
            'healed_from_cache' => true,
        ]);
        $state = json_decode((string) file_get_contents($this->statePath), true);
        $this->assertSame('AUTH_OK', $state['status']);
        $this->assertSame(1, $state['telegram_operator_auth_empty_total']);

        $h->recordAuthEmpty($ctx);
        $state = json_decode((string) file_get_contents($this->statePath), true);
        $this->assertSame(2, $state['telegram_operator_auth_empty_total']);
    }

    public function test_links_missing_classified_no_configuration_and_destinations_empty(): void
    {
        Config::set('telegram_operator.operator_links', '');
        $auth = new TelegramOperatorAuthService();
        $this->assertSame([], $auth->links());
        $this->assertSame([], $auth->authorizedActionTelegramUserIds());
        $h = new TelegramOperatorDestinationHealth();
        $this->assertSame(
            TelegramOperatorDestinationHealth::CLASS_NO_CONFIGURATION,
            $h->classifyEmptyDestinations($auth->links(), [], true)
        );
    }

    public function test_lifecycle_publisher_logs_destination_empty_class(): void
    {
        $src = (string) file_get_contents(base_path('app/Services/TelegramOperator/TelegramOrderLifecyclePublisher.php'));
        $this->assertStringContainsString('destination_empty_class', $src);
        $this->assertStringContainsString('telegram_order_lifecycle_destination_authorization_empty', $src);
        $this->assertStringContainsString('telegram_order_lifecycle_destination_no_configuration', $src);
        $this->assertStringContainsString('CLASS_BOT_SEND_FAILURE', $src);
    }

    public function test_mutation_paths_invalidate_permission_cache(): void
    {
        $userCtrl = (string) file_get_contents(base_path('app/Http/Controllers/Administrator/Account/UserController.php'));
        $roleCtrl = (string) file_get_contents(base_path('app/Http/Controllers/Administrator/Account/RoleController.php'));
        $hourly = (string) file_get_contents(base_path('app/Console/Commands/HourlyMonitoringCommand.php'));
        $update = (string) file_get_contents(base_path('app/Console/Commands/Update/UpdatingOldDataCommand.php'));

        $this->assertStringContainsString('syncRoles', $userCtrl);
        $this->assertStringContainsString('forgetCachedPermissions', $userCtrl);
        $this->assertStringContainsString('forgetCachedPermissions', $roleCtrl);
        $this->assertStringContainsString('removeRole', $roleCtrl);
        $this->assertStringContainsString('syncRoles([])', $hourly);
        $this->assertStringContainsString('forgetCachedPermissions', $hourly);
        $this->assertStringContainsString('forgetCachedPermissions', $update);
    }

    public function test_auth_service_reloads_permission_registrar_on_resolve(): void
    {
        $src = (string) file_get_contents(base_path('app/Services/TelegramOperator/TelegramOperatorAuthService.php'));
        $this->assertStringContainsString('reloadPermissionRegistrar', $src);
        $this->assertStringContainsString('forgetCachedPermissions', $src);
        $this->assertStringContainsString('clearPermissionsCollection', $src);
        $this->assertStringContainsString('recordAuthRecovered', $src);
        $this->assertStringContainsString('recordAuthEmpty', $src);
    }

    public function test_safe_reset_script_is_permission_cache_only(): void
    {
        $script = (string) file_get_contents(base_path('scripts/ops/reset_spatie_permission_cache.sh'));
        $this->assertStringContainsString('permission:cache-reset', $script);
        $this->assertStringNotContainsString('cache:flush', $script);
        $this->assertStringNotContainsString('redis-cli FLUSH', $script);
        $this->assertStringNotContainsString('session:flush', $script);
    }

    public function test_paid_policy_untouched_by_hardening_files(): void
    {
        // Hardening must not rewrite paidAttentionAlertDecision.
        $presenter = (string) file_get_contents(base_path('app/Services/TelegramOperator/TelegramOrderMessagePresenter.php'));
        $auth = (string) file_get_contents(base_path('app/Services/TelegramOperator/TelegramOperatorAuthService.php'));
        $health = (string) file_get_contents(base_path('app/Services/TelegramOperator/TelegramOperatorDestinationHealth.php'));
        $this->assertStringNotContainsString('paidAttentionAlertDecision', $auth);
        $this->assertStringNotContainsString('paidAttentionAlertDecision', $health);
        $this->assertStringContainsString('function paidAttentionAlertDecision', $presenter);
    }

    /**
     * When links configured and can() fails until Spatie cache is forgotten,
     * destination list recovers without bypassing authorization.
     */
    public function test_corrupt_cache_heals_via_supported_reset_without_bypass(): void
    {
        if (! class_exists(PermissionRegistrar::class)) {
            $this->markTestSkipped('spatie/laravel-permission not available');
        }

        Config::set('telegram_operator.operator_links', '6332166155:91077');

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 91077;
        $user->shouldReceive('can')
            ->with('admin_orders_execute')
            ->andReturn(false, true, true);

        // Avoid DB: stub resolveOperator path via partial auth subclass.
        $auth = new class($user) extends TelegramOperatorAuthService
        {
            public function __construct(private User $u) {}

            public function resolveOperator(int $telegramUserId): ?User
            {
                return $telegramUserId === 6332166155 ? $this->u : null;
            }
        };

        Log::spy();
        $ids = $auth->authorizedActionTelegramUserIds('admin_orders_execute');
        $this->assertSame([6332166155], $ids);
        // authorize() itself performs one supported cache heal before denying.
        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => $msg === 'telegram_operator_auth_cache_healed')
            ->once();
    }

    public function test_true_permission_revoke_stays_fail_closed_and_emits_auth_empty(): void
    {
        if (! class_exists(PermissionRegistrar::class)) {
            $this->markTestSkipped('spatie/laravel-permission not available');
        }

        Config::set('telegram_operator.operator_links', '6332166155:91078');
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 91078;
        $user->shouldReceive('can')->with('admin_orders_execute')->andReturn(false);

        $auth = new class($user) extends TelegramOperatorAuthService
        {
            public function __construct(private User $u) {}

            public function resolveOperator(int $telegramUserId): ?User
            {
                return $telegramUserId === 6332166155 ? $this->u : null;
            }
        };

        Log::spy();
        $ids = $auth->authorizedActionTelegramUserIds('admin_orders_execute');
        $this->assertSame([], $ids);
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => $msg === TelegramOperatorDestinationHealth::EVENT_AUTH_EMPTY)
            ->once();
        $this->assertFileExists($this->statePath);
    }
}
