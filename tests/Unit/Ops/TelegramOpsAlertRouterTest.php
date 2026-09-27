<?php

declare(strict_types=1);

namespace Tests\Unit\Ops;

use App\Services\Ops\TelegramOpsAlertRouter;
use App\Services\TelegramOperator\TelegramOperatorBotClient;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

final class TelegramOpsAlertRouterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_disabled_without_ops_chat_never_sends(): void
    {
        Config::set([
            'telegram_ops.rate_alerts_enabled' => true,
            'telegram_ops.chat_id' => '',
            'support_chat.telegram.group_id' => '-100111',
        ]);
        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('sendMessageResult')->never();
        $r = new TelegramOpsAlertRouter($bot);
        $stats = $r->deliverRateAlertEvents([
            ['type' => 'OPEN', 'severity' => 'CRITICAL', 'root_cause' => 'RATE_DRIFT', 'public_impact' => true],
        ]);
        $this->assertSame('OPS_TELEGRAM_CHAT_ID_MISSING', $stats['blocked_reason']);
        $this->assertSame(0, $stats['sent']);
        $this->assertFalse($r->rateTelegramAlertsEnabled());
    }

    public function test_refuses_support_traffic_chat_as_ops_destination(): void
    {
        Config::set([
            'telegram_ops.rate_alerts_enabled' => true,
            'telegram_ops.chat_id' => '-1003801',
            'support_chat.telegram.group_id' => '-1003801',
            'support_chat.telegram.hourly_report_chat_id' => '-1003801',
        ]);
        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('sendMessageResult')->never();
        $stats = (new TelegramOpsAlertRouter($bot))->deliverRateAlertEvents([
            ['type' => 'OPEN', 'severity' => 'CRITICAL', 'root_cause' => 'RATE_DRIFT', 'public_impact' => true],
        ]);
        $this->assertSame('REFUSED_ORDER_OR_TRAFFIC_CHAT', $stats['blocked_reason']);
    }

    public function test_watch_and_non_impact_parser_skipped(): void
    {
        Config::set([
            'telegram_ops.rate_alerts_enabled' => true,
            'telegram_ops.chat_id' => '-1009999',
            'telegram_ops.suppress_watch_telegram' => true,
            'telegram_ops.telegram_severities' => ['CRITICAL', 'WARNING'],
            'support_chat.telegram.group_id' => '-1003801',
        ]);
        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('sendMessageResult')->never();
        $r = new TelegramOpsAlertRouter($bot);
        $this->assertFalse($r->shouldDeliver([
            'type' => 'OPEN',
            'severity' => 'WATCH',
            'root_cause' => 'BESTCHANGE_POSITION_INSUFFICIENT_DEPTH',
            'public_impact' => false,
            'order_impact' => false,
        ]));
        $this->assertFalse($r->shouldDeliver([
            'type' => 'OPEN',
            'severity' => 'WARNING',
            'root_cause' => 'BESTCHANGE_POSITION_INSUFFICIENT_DEPTH',
            'public_impact' => false,
            'order_impact' => false,
        ]));
    }

    public function test_critical_rate_drift_and_recovery_go_to_ops_chat(): void
    {
        Config::set([
            'telegram_ops.rate_alerts_enabled' => true,
            'telegram_ops.chat_id' => '-1009999',
            'support_chat.telegram.group_id' => '-1003801',
        ]);
        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('sendMessageResult')
            ->twice()
            ->withArgs(fn ($chat, $text) => (string) $chat === '-1009999' && is_string($text))
            ->andReturn(['ok' => true, 'message_id' => 1]);
        $r = new TelegramOpsAlertRouter($bot);
        $stats = $r->deliverRateAlertEvents([
            [
                'type' => 'OPEN',
                'severity' => 'CRITICAL',
                'root_cause' => 'RATE_DRIFT',
                'public_impact' => true,
                'order_impact' => true,
                'direction_id' => 1,
                'pair' => 'A→B',
                'owner' => 'BESTCHANGE',
                'diff_percent' => 12.5,
                'current_final' => '1',
                'expected_final' => '2',
            ],
            [
                'type' => 'RECOVERED',
                'direction_id' => 1,
                'pair' => 'A→B',
                'owner' => 'BESTCHANGE',
                'previous_root_cause' => 'RATE_DRIFT',
                'recovered_at' => 'now',
            ],
        ]);
        $this->assertSame(2, $stats['sent']);
        $this->assertNull($stats['blocked_reason']);
        $this->assertTrue($r->rateTelegramAlertsEnabled());
    }

    public function test_master_switch_off_blocks_even_with_chat(): void
    {
        Config::set([
            'telegram_ops.rate_alerts_enabled' => false,
            'telegram_ops.chat_id' => '-1009999',
        ]);
        $bot = Mockery::mock(TelegramOperatorBotClient::class);
        $bot->shouldReceive('sendMessageResult')->never();
        $stats = (new TelegramOpsAlertRouter($bot))->deliverRateAlertEvents([
            ['type' => 'OPEN', 'severity' => 'CRITICAL', 'root_cause' => 'RATE_DRIFT', 'public_impact' => true],
        ]);
        $this->assertSame('RATE_TELEGRAM_ALERTS_DISABLED', $stats['blocked_reason']);
    }
}
