<?php

declare(strict_types=1);

namespace Tests\Unit\TelegramOperator;

use App\Models\Task;
use App\Services\TelegramOperator\TelegramOrderCardRegistry;
use App\Services\TelegramOperator\TelegramOrderLifecyclePublisher;
use App\Services\TelegramOperator\TelegramOrderMessagePresenter;
use App\Services\TelegramOperator\TelegramOperatorBotClient;
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

    public function test_paid_edits_existing_card_and_sends_short_alert_when_unclaimed(): void
    {
        $task = $this->fakeTask(3);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'new');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->once()
            ->withArgs(function ($chat, $msgId, $text) {
                return (string) $chat === '777001'
                    && (int) $msgId === 101
                    && is_string($text)
                    && str_contains($text, 'Заявка')
                    && str_contains($text, 'Клиент отметил оплату');
            })
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')
            ->once()
            ->withArgs(function ($chat, $text, $kb = null, $replyTo = null) {
                return (string) $chat === '777001'
                    && (int) $replyTo === 101
                    && str_contains((string) $text, '💰 Клиент отметил оплату')
                    && str_contains((string) $text, '🔴 Требуется взять заявку и проверить оплату')
                    && ! str_contains((string) $text, '⚠️💰');
            })
            ->andReturn(['ok' => true, 'message_id' => 701]);

        $stats = $this->publisher($bot, destinations: ['777001'])->publishPaid($task);
        $this->assertSame(1, $stats['edited']);
        $this->assertSame(0, $stats['sent']);
        $this->assertSame(1, $stats['attention_alert']);
        $this->assertSame('NORMAL_PAID', $stats['attention_alert_reason']);
        $this->assertTrue(app(TelegramOrderCardRegistry::class)->paidAttentionAlertAlreadySent((int) $task->id));
    }

    public function test_paid_repeat_still_edits_same_message_and_alert_is_exactly_once(): void
    {
        $task = $this->fakeTask(3);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'paid');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->twice()
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')
            ->once()
            ->andReturn(['ok' => true, 'message_id' => 702]);

        $publisher = $this->publisher($bot, destinations: ['777001']);
        $first = $publisher->publishPaid($task);
        $second = $publisher->publishPaid($task);
        $this->assertSame(1, $first['attention_alert']);
        $this->assertSame(0, $second['attention_alert']);
        $cards = app(TelegramOrderCardRegistry::class)->cards((int) $task->id);
        $this->assertSame(101, $cards[0]['message_id']);
    }

    public function test_paid_after_claim_edits_card_and_sends_short_alert(): void
    {
        $task = $this->fakeTask(3);
        $task->setRelation('edit_data_manager', (object) ['name' => 'admin', 'email' => null]);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'claimed');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->once()
            ->withArgs(function ($chat, $msgId, $text) {
                return (int) $msgId === 101
                    && str_contains((string) $text, 'Клиент отметил оплату')
                    && str_contains((string) $text, 'Требуется проверить оплату')
                    && str_contains((string) $text, 'В работе у:');
            })
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')
            ->once()
            ->withArgs(fn ($c, $t) => str_contains((string) $t, 'Клиент отметил оплату')
                && str_contains((string) $t, 'Проверьте поступление'))
            ->andReturn(['ok' => true, 'message_id' => 703]);

        $stats = $this->publisher($bot, destinations: ['777001'])->publishPaid($task);
        $this->assertSame(1, $stats['edited']);
        $this->assertSame(0, $stats['skipped_stale']);
        $this->assertSame(1, $stats['attention_alert']);
        $this->assertSame('NORMAL_PAID', $stats['attention_alert_reason']);
    }

    public function test_paid_after_claim_retry_alert_exactly_once(): void
    {
        $task = $this->fakeTask(3);
        $task->setRelation('edit_data_manager', (object) ['name' => 'admin', 'email' => null]);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'claimed');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->twice()
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')
            ->once()
            ->andReturn(['ok' => true, 'message_id' => 704]);

        $publisher = $this->publisher($bot, destinations: ['777001']);
        $first = $publisher->publishPaid($task);
        $second = $publisher->publishPaid($task);
        $this->assertSame(1, $first['attention_alert']);
        $this->assertSame(0, $second['attention_alert']);
        $this->assertTrue(app(TelegramOrderCardRegistry::class)->paidAttentionAlertAlreadySent((int) $task->id));
    }

    public function test_frozen_unclaimed_paid_sends_high_severity_alert(): void
    {
        $task = $this->fakeTask(3);
        $task->is_frozen = 1;
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'new');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->once()
            ->withArgs(fn ($c, $m, $t) => str_contains((string) $t, '⚠️ Риск: заморожено'))
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')
            ->once()
            ->withArgs(fn ($c, $t) => str_contains((string) $t, '⚠️💰 Клиент отметил оплату')
                && str_contains((string) $t, '🚫 Выплата заблокирована до ручной проверки'))
            ->andReturn(['ok' => true, 'message_id' => 701]);

        $stats = $this->publisher($bot, destinations: ['777001'])->publishPaid($task);
        $this->assertSame(1, $stats['edited']);
        $this->assertSame(1, $stats['attention_alert']);
        $this->assertSame('FROZEN_HIGH_RISK', $stats['attention_alert_reason']);
    }

    public function test_paid_attention_alert_decision_policy(): void
    {
        $presenter = new TelegramOrderMessagePresenter();
        $unclaimed = $this->fakeTask(3);
        $this->assertSame(
            ['send' => true, 'reason' => 'NORMAL_PAID'],
            $presenter->paidAttentionAlertDecision($unclaimed)
        );

        $claimed = $this->fakeTask(3);
        $claimed->setRelation('edit_data_manager', (object) ['name' => 'ops', 'email' => null]);
        $this->assertSame(
            ['send' => true, 'reason' => 'NORMAL_PAID'],
            $presenter->paidAttentionAlertDecision($claimed)
        );

        $frozen = $this->fakeTask(3);
        $frozen->is_frozen = true;
        $this->assertSame(
            ['send' => true, 'reason' => 'FROZEN_HIGH_RISK'],
            $presenter->paidAttentionAlertDecision($frozen)
        );
    }

    public function test_paid_attention_alert_unclaimed_asks_to_claim(): void
    {
        $presenter = new TelegramOrderMessagePresenter();
        $text = $presenter->renderPaidAttentionAlert([
            'public_id' => '99',
            'give_amount' => '400 USDT',
            'receive_amount' => '100 RUB',
            'give_ps' => 'USDTTRC20',
            'receive_ps' => 'SBER',
            'operator' => null,
        ]);
        $this->assertStringContainsString('💰 Клиент отметил оплату', $text);
        $this->assertStringContainsString('🔴 Требуется взять заявку и проверить оплату', $text);
        $this->assertStringNotContainsString('🟡 Оператор:', $text);
    }

    public function test_claim_after_paid_keeps_payment_visible_without_second_alert(): void
    {
        $task = $this->fakeTask(3);
        $task->setRelation('edit_data_manager', (object) ['name' => 'admin', 'email' => null]);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'paid');
        app(TelegramOrderCardRegistry::class)->markPaidAttentionAlertSent((int) $task->id);
        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->once()
            ->withArgs(function ($chat, $msgId, $text) {
                return (int) $msgId === 101
                    && str_contains((string) $text, 'Клиент отметил оплату')
                    && str_contains((string) $text, 'В работе у: admin');
            })
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')->never();

        $stats = $this->publisher($bot, destinations: ['777001'])->publishClaimed($task);
        $this->assertSame(1, $stats['edited']);
        $this->assertSame(0, $stats['skipped_stale']);
    }

    public function test_missing_message_recovers_card_and_still_sends_one_alert(): void
    {
        $task = $this->fakeTask(3);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'new');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->once()
            ->andReturn(['ok' => false, 'message_missing' => true]);
        $bot->shouldReceive('sendMessageResult')
            ->twice()
            ->andReturn(
                ['ok' => true, 'message_id' => 202],
                ['ok' => true, 'message_id' => 203],
            );

        $stats = $this->publisher($bot, destinations: ['777001'])->publishPaid($task);
        $this->assertSame(1, $stats['recovered']);
        $this->assertSame(1, $stats['attention_alert']);
        $cards = app(TelegramOrderCardRegistry::class)->cards((int) $task->id);
        $this->assertSame(202, $cards[0]['message_id']);
    }

    public function test_network_edit_failure_does_not_duplicate_full_card_but_may_send_short_alert(): void
    {
        $task = $this->fakeTask(3);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'new');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->once()
            ->andReturn(['ok' => false, 'message_missing' => false]);
        // Short alert only (reply to existing id) — not a second full operational card.
        $bot->shouldReceive('sendMessageResult')
            ->once()
            ->withArgs(fn ($c, $t, $kb = null, $replyTo = null) => (int) $replyTo === 101
                && str_contains((string) $t, 'Клиент отметил оплату'))
            ->andReturn(['ok' => true, 'message_id' => 705]);

        $stats = $this->publisher($bot, destinations: ['777001'])->publishPaid($task);
        $this->assertSame(0, $stats['sent']);
        $this->assertSame(0, $stats['recovered']);
        $this->assertSame(1, $stats['attention_alert']);
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
        $this->assertStringNotContainsString('Курс к выплате', $text);
        $this->assertStringNotContainsString('Адрес для депозита', $text);
        $this->assertStringContainsString('Ожидается оплата', $text);
    }

    public function test_full_rate_lines_label_direction_quote_not_settlement(): void
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
            'customer_marked_paid' => true,
            'status_label' => 'Ожидает проверки оплаты',
        ]);
        $this->assertStringContainsString('Курс при создании:', $text);
        $this->assertStringContainsString('Курс направления сейчас:', $text);
        $this->assertStringNotContainsString('Курс к выплате:', $text);
        $this->assertStringNotContainsString('Курс заявки:', $text);
        $this->assertStringContainsString('Клиент отметил оплату', $text);
    }

    public function test_claimed_state_line_preserves_paid_payment_state(): void
    {
        $presenter = new TelegramOrderMessagePresenter();
        $text = $presenter->renderText([
            'public_id' => '1789938124757626',
            'give_ps' => 'USDT TRC20',
            'receive_ps' => 'SBER RUB',
            'give_amount' => '400 USDT',
            'receive_amount' => '36 048.43 RUB',
            'order_rate' => '1 USDT = 90.1211 RUB',
            'current_rate' => null,
            'rate_mode' => 'floating',
            'give_fields' => [],
            'receive_fields' => [],
            'lifecycle' => 'claimed',
            'customer_marked_paid' => true,
            'operator' => 'admin',
            'claimed_at' => '02:07',
        ]);
        $this->assertStringContainsString('Клиент отметил оплату', $text);
        $this->assertStringContainsString('В работе у: admin · 02:07', $text);
        $this->assertStringNotContainsString('👤 admin · 🕐 02:07 · 🟡 В работе', $text);
    }

    public function test_paid_before_new_keeps_single_advanced_card(): void
    {
        $task = $this->fakeTask(3);
        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        // Paid creates the main card + one short attention alert.
        $bot->shouldReceive('sendMessageResult')
            ->twice()
            ->andReturn(
                ['ok' => true, 'message_id' => 303],
                ['ok' => true, 'message_id' => 304],
            );
        $bot->shouldReceive('editMessageResult')->never();

        $publisher = $this->publisher($bot, destinations: ['777001']);
        $paid = $publisher->publishPaid($task);
        $this->assertSame(1, $paid['sent']);
        $this->assertSame(1, $paid['attention_alert']);
        $this->assertSame('NORMAL_PAID', $paid['attention_alert_reason']);

        $lateNew = $publisher->publishNew($task);
        $this->assertSame(0, $lateNew['sent']);
        $this->assertSame(0, $lateNew['edited']);
        $cards = app(TelegramOrderCardRegistry::class)->cards((int) $task->id);
        $this->assertCount(1, $cards);
        $this->assertSame(303, $cards[0]['message_id']);
        $this->assertSame('paid', $cards[0]['lifecycle']);
    }

    public function test_frozen_claimed_paid_sends_high_severity_alert_exactly_once(): void
    {
        $task = $this->fakeTask(3);
        $task->is_frozen = true;
        $task->setRelation('edit_data_manager', (object) ['name' => 'admin', 'email' => null]);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'claimed');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->twice()
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')
            ->once()
            ->withArgs(fn ($c, $t) => str_contains((string) $t, '⚠️💰')
                && str_contains((string) $t, '🚫 Выплата заблокирована'))
            ->andReturn(['ok' => true, 'message_id' => 801]);

        $publisher = $this->publisher($bot, destinations: ['777001']);
        $first = $publisher->publishPaid($task);
        $task->is_frozen = true; // freeze re-evaluation after PAID must not create a second alert
        $second = $publisher->publishPaid($task);
        $this->assertSame(1, $first['attention_alert']);
        $this->assertSame('FROZEN_HIGH_RISK', $first['attention_alert_reason']);
        $this->assertSame(0, $second['attention_alert']);
    }

    public function test_cancelled_edits_same_card_and_repeat_is_idempotent(): void
    {
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
        $rejected = $this->fakeTask(5, 9135207);
        app(TelegramOrderCardRegistry::class)->remember((int) $rejected->id, '777001', 111, 'paid');
        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')
            ->once()
            ->withArgs(fn ($c, $m, $t) => str_contains((string) $t, '🔴 Заявка отклонена'))
            ->andReturn(['ok' => true, 'message_missing' => false]);
        $bot->shouldReceive('sendMessageResult')->never();
        $this->assertSame(1, $this->publisher($bot, destinations: ['777001'])->publishRejected($rejected)['edited']);

        $expired = $this->fakeTask(1, 9135208);
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
        $task = $this->fakeTask(6);
        app(TelegramOrderCardRegistry::class)->remember((int) $task->id, '777001', 101, 'cancelled');

        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('editMessageResult')->never();
        $bot->shouldReceive('sendMessageResult')->never();

        $stats = $this->publisher($bot, destinations: ['777001'])->publishPaid($task);
        $this->assertSame(0, $stats['edited']);
        $this->assertSame(0, $stats['sent']);
        $this->assertGreaterThan(0, $stats['skipped_stale']);
        $this->assertSame(0, $stats['attention_alert']);
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
        Config::set('telegram_operator.notify_operator_dms', true);
        Config::set('telegram_operator.actions_enabled', false);
        $registry = app(TelegramOrderCardRegistry::class);
        $presenter = new TelegramOrderMessagePresenter();
        $selector = new \App\Services\Orders\TelegramNotificationSelector();
        $auth = new \App\Services\TelegramOperator\TelegramOperatorAuthService();
        Config::set('telegram_operator.test_force_dm_ids', array_map('strval', $destinations));

        return new TelegramOrderLifecyclePublisher($bot, $registry, $presenter, $selector, $auth);
    }

    private function fakeTask(int $status, ?int $id = null): Task
    {
        $task = Mockery::mock(Task::class)->makePartial();
        $task->id = $id ?? (9900000 + $status * 1000 + random_int(1, 899));
        $task->public_id = '1789'.(string) $task->id;
        $task->status = $status;
        $task->opt_params = [];
        $task->shouldReceive('fresh')->andReturn($task);
        $task->shouldReceive('loadMissing')->andReturn($task);
        $task->shouldReceive('save')->andReturn(true);

        return $task;
    }
}
