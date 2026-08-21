<?php

declare(strict_types=1);

namespace Tests\Unit\TelegramOperator;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TelegramNewOrder;
use App\Services\TelegramOperator\TelegramOperatorAuthService;
use App\Services\TelegramOperator\TelegramOrderOperatorWorkflowService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use NotificationChannels\Telegram\TelegramMessage;
use Tests\TestCase;

/**
 * Wave 3: canonical Laravel notification for normal (non-spam) orders,
 * authorized operator DMs, shared keyboard, Python order-DM guard.
 */
final class TelegramOperatorNotificationUnificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        Config::set('telegram_operator.actions_enabled', true);
        Config::set('telegram_operator.dry_run', true);
        Config::set('telegram_operator.notify_operator_dms', true);
        Config::set('telegram_operator.operator_links', '');
        Config::set('app.url', 'https://app.exswaping.com');
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_normal_non_spam_order_keyboard_has_accept_complete_admin(): void
    {
        $task = $this->fakeTask(status: 2, spam: false);
        $this->assertSame(0, (int) $task->is_spam);
        $this->assertSame(2, (int) $task->status);

        $rows = TelegramOrderOperatorWorkflowService::notificationKeyboard((int) $task->id);
        $labels = [];
        $callbacks = [];
        foreach ($rows as $row) {
            foreach ($row as $btn) {
                $labels[] = $btn['text'] ?? '';
                if (isset($btn['callback_data'])) {
                    $callbacks[] = $btn['callback_data'];
                }
            }
        }
        $this->assertContains('👤 Принять заявку', $labels);
        $this->assertContains('✅ Выполнить', $labels);
        $this->assertNotEmpty(array_filter(
            $labels,
            fn ($t) => str_contains(mb_strtolower((string) $t), 'админ')
        ));
        $this->assertNotEmpty($callbacks);
    }

    public function test_telegram_new_order_attaches_same_keyboard_for_channel_and_dm(): void
    {
        $task = $this->fakeTask(status: 2, spam: false);
        $tokens = $this->fakeTokens('-1002351759013');
        $dm = '6332166155';

        $channel = $this->buildMessageWithSharedKeyboard($tokens, $task, null);
        $dmMsg = $this->buildMessageWithSharedKeyboard($tokens, $task, $dm);

        $this->assertSame('-1002351759013', (string) ($channel->toArray()['chat_id'] ?? ''));
        $this->assertSame($dm, (string) ($dmMsg->toArray()['chat_id'] ?? ''));
        $this->assertSame(
            $channel->toArray()['reply_markup'] ?? null,
            $dmMsg->toArray()['reply_markup'] ?? null
        );

        $notification = new TelegramNewOrder($tokens, $task, $dm);
        $this->assertSame($dm, $notification->resolvedChatId());
        $this->assertSame('-1002351759013', (new TelegramNewOrder($tokens, $task))->resolvedChatId());
    }

    public function test_authorized_dm_targets_fail_closed_for_unlinked(): void
    {
        Config::set('telegram_operator.operator_links', '');
        $auth = new TelegramOperatorAuthService();
        $this->assertSame([], $auth->authorizedActionTelegramUserIds());
    }

    public function test_unlinked_telegram_user_not_in_dm_targets(): void
    {
        $this->ensureOperatorUser(92001);
        Config::set('telegram_operator.operator_links', '555001:92001');
        $auth = new TelegramOperatorAuthService();
        $ids = $auth->authorizedActionTelegramUserIds('admin_orders_execute');
        $this->assertNotContains(999999001, $ids);
    }

    public function test_python_order_dm_guard_contract_in_source(): void
    {
        $main = '/root/exswaping_notify_bot/main.py';
        $cfg = '/root/exswaping_notify_bot/config.py';
        $this->assertFileExists($main);
        $this->assertFileExists($cfg);
        $mainSrc = (string) file_get_contents($main);
        $cfgSrc = (string) file_get_contents($cfg);
        $this->assertStringContainsString('ORDER_NOTIFICATIONS_ENABLED', $cfgSrc);
        $this->assertStringContainsString('order_notifications_enabled', $mainSrc);
        $this->assertStringContainsString('ORDER_NOTIFICATIONS_ENABLED=false', $mainSrc);
        $this->assertStringContainsString('default=False', $cfgSrc);
        $exnode = (string) file_get_contents('/root/exswaping_notify_bot/exnode_watcher.py');
        $this->assertStringContainsString('send_order_notification', $exnode);
    }

    public function test_job_source_sends_operator_dm_without_completion_logic(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3).'/app/Jobs/Telegram/TelegramOrderJob.php'
        );
        $this->assertStringContainsString('operator_dm', $src);
        $this->assertStringContainsString('authorizedActionTelegramUserIds', $src);
        $this->assertStringContainsString('notify_operator_dms', $src);
        $this->assertStringNotContainsString('ManualCompletionGuard', $src);
        $this->assertStringNotContainsString('TransactionFacade', $src);
    }

    /**
     * Mirror TelegramNewOrder keyboard attachment without rendering production blade
     * (blade needs full direction/user relations).
     */
    private function buildMessageWithSharedKeyboard(object $tokens, Task $task, ?string $chatOverride): TelegramMessage
    {
        $chat = $chatOverride ?: (string) $tokens->id_channel;
        $telegram = TelegramMessage::create()
            ->token((string) $tokens->token_access)
            ->to($chat)
            ->content('📋 Заявка №TEST USDTTRC20 → BTC');

        foreach (TelegramOrderOperatorWorkflowService::notificationKeyboard((int) $task->id) as $row) {
            foreach ($row as $button) {
                if (isset($button['callback_data'])) {
                    $telegram->buttonWithCallback($button['text'], $button['callback_data'], 1);
                } elseif (isset($button['url'])) {
                    $telegram->button($button['text'], $button['url'], 1);
                }
            }
        }

        return $telegram->options(['parse_mode' => 'HTML']);
    }

    private function fakeTask(int $status, bool $spam): Task
    {
        $task = new Task();
        $task->id = 48040;
        $task->public_id = '1787303343316-test';
        $task->status = $status;
        $task->is_spam = $spam ? 1 : 0;
        $task->is_bot = 0;
        $task->give_price = '400';
        $task->receiving_price = '0.00504071';
        $task->id_direction_exchange = 35;
        $task->exists = true;

        return $task;
    }

    private function fakeTokens(string $channel): object
    {
        return (object) [
            'id' => 4,
            'token_access' => '0000000000:TEST_TOKEN_NOT_USED',
            'id_channel' => $channel,
        ];
    }

    private function ensureOperatorUser(int $id): User
    {
        $existing = User::query()->find($id);
        if ($existing) {
            return $existing;
        }

        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Operator '.$id,
            'email' => 'tg-op-u-'.$id.'@example.invalid',
            'password' => bcrypt('not-used'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }
}
