<?php

declare(strict_types=1);

namespace Tests\Unit\TelegramOperator;

use App\Models\Task;
use App\Services\TelegramOperator\TelegramOrderCardRegistry;
use App\Services\TelegramOperator\TelegramOrderLifecyclePublisher;
use App\Services\TelegramOperator\TelegramOrderMessagePresenter;
use App\Services\TelegramOperator\TelegramOperatorBotClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

final class TelegramOrderLifecyclePublisherTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_new_sends_compact_and_stores_message_id(): void
    {
        Cache::flush();
        $task = $this->fakeTask(2);
        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('sendMessageResult')
            ->once()
            ->andReturn(['ok' => true, 'message_id' => 101]);
        $bot->shouldReceive('editMessageResult')->never();

        $publisher = $this->publisher($bot, destinations: ['777001']);
        $stats = $publisher->publishNew($task);

        $this->assertSame(1, $stats['sent']);
        $cards = app(TelegramOrderCardRegistry::class)->cards((int) $task->id);
        $this->assertCount(1, $cards);
        $this->assertSame(101, $cards[0]['message_id']);
        $this->assertSame('new', $cards[0]['lifecycle']);
    }

    public function test_paid_edits_existing_card_without_second_send(): void
    {
        Cache::flush();
        $task = $this->fakeTask(3);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'new');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->once()
            ->withArgs(function ($chat, $msgId, $text) {
                return (string) $chat === '777001'
                    && (int) $msgId === 101
                    && is_string($text)
                    && str_contains($text, 'Заявка');
            })
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')->never();

        $stats = $this->publisher($bot, destinations: ['777001'])->publishPaid($task);
        $this->assertSame(1, $stats['edited']);
        $this->assertSame(0, $stats['sent']);
    }

    public function test_paid_repeat_still_edits_same_message(): void
    {
        Cache::flush();
        $task = $this->fakeTask(3);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'paid');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->twice()
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')->never();

        $publisher = $this->publisher($bot, destinations: ['777001']);
        $publisher->publishPaid($task);
        $publisher->publishPaid($task);
        $cards = app(TelegramOrderCardRegistry::class)->cards((int) $task->id);
        $this->assertSame(101, $cards[0]['message_id']);
    }

    public function test_missing_message_recovers_with_one_replacement(): void
    {
        Cache::flush();
        $task = $this->fakeTask(3);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'new');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->once()
            ->andReturn(['ok' => false, 'message_missing' => true]);
        $bot->shouldReceive('sendMessageResult')
            ->once()
            ->andReturn(['ok' => true, 'message_id' => 202]);

        $stats = $this->publisher($bot, destinations: ['777001'])->publishPaid($task);
        $this->assertSame(1, $stats['recovered']);
        $cards = app(TelegramOrderCardRegistry::class)->cards((int) $task->id);
        $this->assertSame(202, $cards[0]['message_id']);
    }

    public function test_network_edit_failure_does_not_duplicate(): void
    {
        Cache::flush();
        $task = $this->fakeTask(3);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'new');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->once()
            ->andReturn(['ok' => false, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')->never();

        $stats = $this->publisher($bot, destinations: ['777001'])->publishPaid($task);
        $this->assertSame(0, $stats['sent']);
        $this->assertSame(0, $stats['recovered']);
        $this->assertSame(101, app(TelegramOrderCardRegistry::class)->cards((int) $task->id)[0]['message_id']);
    }

    public function test_compact_new_omits_deposit_dump_and_uses_single_rate(): void
    {
        $presenter = new TelegramOrderMessagePresenter();
        $text = $presenter->renderCompactNew([
            'public_id' => '1789834712258163',
            'give_ps' => 'USDT TRC20',
            'receive_ps' => 'FREEDOM KZT',
            'give_amount' => '2,000 USDT',
            'receive_amount' => '894,433.27 KZT',
            'order_rate' => '1 USDT = 447.2166 KZT',
            'current_rate' => '1 USDT = 447.66429 KZT',
            'rate_mode' => 'fixed',
            'email' => 'vapote***@example.com',
            'lifecycle' => 'new',
        ]);
        $this->assertStringContainsString('🆕 Новая заявка', $text);
        $this->assertStringContainsString('Курс:', $text);
        $this->assertStringNotContainsString('Курс заявки', $text);
        $this->assertStringNotContainsString('Текущий курс', $text);
        $this->assertStringNotContainsString('Адрес для депозита', $text);
        $this->assertStringContainsString('Ожидается оплата', $text);
    }

    public function test_full_rate_lines_dedupe_vague_labels(): void
    {
        $presenter = new TelegramOrderMessagePresenter();
        $text = $presenter->renderText([
            'public_id' => '1',
            'give_ps' => 'A',
            'receive_ps' => 'B',
            'give_amount' => '1',
            'receive_amount' => '2',
            'order_rate' => '1 = 10',
            'current_rate' => '1 = 11',
            'rate_mode' => 'floating',
            'give_fields' => [],
            'receive_fields' => [],
            'lifecycle' => 'paid',
            'status_label' => 'Ожидает проверки оплаты',
        ]);
        $this->assertStringContainsString('Курс при создании:', $text);
        $this->assertStringContainsString('Курс к выплате:', $text);
        $this->assertStringNotContainsString('Курс заявки:', $text);
        $this->assertStringNotContainsString('Текущий курс:', $text);
    }

    public function test_paid_before_new_keeps_single_advanced_card(): void
    {
        Cache::flush();
        $task = $this->fakeTask(3);
        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('sendMessageResult')
            ->once()
            ->andReturn(['ok' => true, 'message_id' => 303]);
        $bot->shouldReceive('editMessageResult')->never();

        $publisher = $this->publisher($bot, destinations: ['777001']);
        $paid = $publisher->publishPaid($task);
        $this->assertSame(1, $paid['sent']);

        $lateNew = $publisher->publishNew($task);
        $this->assertSame(0, $lateNew['sent']);
        $this->assertSame(0, $lateNew['edited']);
        $cards = app(TelegramOrderCardRegistry::class)->cards((int) $task->id);
        $this->assertCount(1, $cards);
        $this->assertSame(303, $cards[0]['message_id']);
        $this->assertSame('paid', $cards[0]['lifecycle']);
    }

    public function test_cancelled_edits_same_card_and_repeat_is_idempotent(): void
    {
        Cache::flush();
        $task = $this->fakeTask(6);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'paid');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->twice()
            ->withArgs(fn ($chat, $msgId, $text) => (int) $msgId === 101 && str_contains((string) $text, '❌ Заявка отменена'))
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')->never();

        $publisher = $this->publisher($bot, destinations: ['777001']);
        $this->assertSame(1, $publisher->publishCancelled($task)['edited']);
        $this->assertSame(1, $publisher->publishCancelled($task)['edited']);
        $this->assertSame(101, app(TelegramOrderCardRegistry::class)->cards((int) $task->id)[0]['message_id']);
        $this->assertSame('cancelled', app(TelegramOrderCardRegistry::class)->cards((int) $task->id)[0]['lifecycle']);
    }

    public function test_rejected_and_expired_edit_same_card(): void
    {
        Cache::flush();
        $rejected = $this->fakeTask(5);
        $rejected->id = 9135207;
        app(TelegramOrderCardRegistry::class)->remember((int) $rejected->id, '777001', 111, 'paid');
        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->once()
            ->withArgs(fn ($c, $m, $t) => str_contains((string) $t, '🔴 Заявка отклонена'))
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')->never();
        $this->assertSame(1, $this->publisher($bot, destinations: ['777001'])->publishRejected($rejected)['edited']);

        Cache::flush();
        $expired = $this->fakeTask(1);
        $expired->id = 9135208;
        app(TelegramOrderCardRegistry::class)->remember((int) $expired->id, '777001', 222, 'new');
        $bot2 = Mockery::mock(TelegramOperatorBotClient::class);
        $bot2->shouldReceive('editMessageResult')
            ->once()
            ->withArgs(fn ($c, $m, $t) => str_contains((string) $t, '⚪ Заявка истекла'))
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot2->shouldReceive('sendMessageResult')->never();
        $this->assertSame(1, $this->publisher($bot2, destinations: ['777001'])->publishExpired($expired)['edited']);
    }

    public function test_delayed_paid_cannot_regress_cancelled_card(): void
    {
        Cache::flush();
        $task = $this->fakeTask(6);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'cancelled');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')->never();
        $bot->shouldReceive('sendMessageResult')->never();

        $stats = $this->publisher($bot, destinations: ['777001'])->publishPaid($task);
        $this->assertSame(0, $stats['edited']);
        $this->assertSame(0, $stats['sent']);
        $this->assertGreaterThan(0, $stats['skipped_stale']);
        $this->assertSame('cancelled', app(TelegramOrderCardRegistry::class)->cards((int) $task->id)[0]['lifecycle']);
    }

    public function test_order_card_destinations_are_operator_dms_not_channels(): void
    {
        $src = (string) file_get_contents(base_path('app/Services/TelegramOperator/TelegramOrderLifecyclePublisher.php'));
        $this->assertStringContainsString('order-card channel path is retired', $src);
        $this->assertStringContainsString('authorizedActionTelegramUserIds', $src);
        $this->assertStringNotContainsString("enabledForEvent('process_created_order')", $src);
    }

    public function test_terminal_buttons_are_non_actionable(): void
    {
        Config::set('app.url', 'https://exswaping.com');
        Config::set('telegram_operator.actions_enabled', true);
        $kb = \App\Services\TelegramOperator\TelegramOrderOperatorWorkflowService::completedKeyboard(42);
        $flat = json_encode($kb, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Принять', $flat);
        $this->assertStringNotContainsString('Выполнить', $flat);
        $this->assertStringNotContainsString('callback_data', $flat);
    }

    public function test_job_routes_created_paid_and_terminal_to_lifecycle(): void
    {
        $src = (string) file_get_contents(base_path('app/Jobs/Telegram/TelegramOrderJob.php'));
        $this->assertStringContainsString('TelegramOrderLifecyclePublisher', $src);
        $this->assertStringContainsString('publishNew', $src);
        $this->assertStringContainsString('publishPaid', $src);
        $this->assertStringContainsString('publishCancelled', $src);
        $this->assertStringContainsString('lifecycle_expired', $src);
        $obs = (string) file_get_contents(base_path('app/Observers/Task/TaskObserver.php'));
        $this->assertStringContainsString('lifecycle_cancelled', $obs);
        $this->assertStringContainsString('lifecycle_rejected', $obs);
        $this->assertStringContainsString('lifecycle_expired', $obs);
    }

    /**
     * @param  list<string>  $destinations
     */
    private function publisher(TelegramOperatorBotClient $bot, array $destinations): TelegramOrderLifecyclePublisher
    {
        $registry = app(TelegramOrderCardRegistry::class);
        $presenter = new TelegramOrderMessagePresenter();
        $selector = Mockery::mock(\App\Services\Orders\TelegramNotificationSelector::class);
        $selector->shouldReceive('enabledForEvent')->andReturn(collect([]));
        $auth = Mockery::mock(\App\Services\TelegramOperator\TelegramOperatorAuthService::class);
        $auth->shouldReceive('authorizedActionTelegramUserIds')->andReturn(
            array_map('intval', $destinations)
        );

        return new TelegramOrderLifecyclePublisher($bot, $registry, $presenter, $selector, $auth);
    }

    private function fakeTask(int $status): Task
    {
        $task = new Task();
        $task->id = 9135206;
        $task->public_id = '1789834712258163';
        $task->status = $status;
        $task->opt_params = [];

        return $task;
    }
}
