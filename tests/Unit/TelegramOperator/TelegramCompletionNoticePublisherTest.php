<?php

declare(strict_types=1);

namespace Tests\Unit\TelegramOperator;

use App\Services\TelegramOperator\TelegramCompletionNoticePublisher;
use App\Services\TelegramOperator\TelegramOperatorBotClient;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class TelegramCompletionNoticePublisherTest extends TestCase
{
    public function test_second_publish_is_suppressed_by_order_id_cache(): void
    {
        Cache::flush();
        $bot = new class extends TelegramOperatorBotClient {
            public array $calls = [];

            public function editMessage(int|string $chatId, int $messageId, string $text, ?array $keyboard = null): void
            {
                $this->calls[] = ['edit', $chatId, $messageId, $text];
            }

            public function sendMessage(int|string $chatId, string $text, ?array $keyboard = null): void
            {
                $this->calls[] = ['send', $chatId, $text];
            }

            public function deleteMessage(int|string $chatId, int $messageId): bool
            {
                $this->calls[] = ['delete', $chatId, $messageId];

                return true;
            }
        };
        $publisher = new TelegramCompletionNoticePublisher($bot);
        $flow = ['chat_id' => 100, 'message_id' => 11];
        $this->assertSame('edit', $publisher->publish(501, 100, 100, 22, $flow, 'DONE', null));
        $this->assertSame('duplicate_suppressed', $publisher->publish(501, 100, 100, 22, $flow, 'DONE', null));
        $edits = array_values(array_filter($bot->calls, fn ($c) => $c[0] === 'edit'));
        $this->assertCount(1, $edits);
        $this->assertSame(11, $edits[0][2]);
        $this->assertCount(1, array_filter($bot->calls, fn ($c) => $c[0] === 'delete'));
    }

    public function test_workflow_owns_a_single_completion_notice_publisher(): void
    {
        $src = (string) file_get_contents(base_path('app/Services/TelegramOperator/TelegramOrderOperatorWorkflowService.php'));
        $this->assertStringContainsString('TelegramCompletionNoticePublisher', $src);
        $this->assertStringNotContainsString('editMessage($origChat', $src);
        $this->assertStringContainsString('telegram_complete_notice:', (string) file_get_contents(base_path('app/Services/TelegramOperator/TelegramCompletionNoticePublisher.php')));
    }

    public function test_workflow_edits_full_card_not_compact_notice(): void
    {
        $src = (string) file_get_contents(base_path('app/Services/TelegramOperator/TelegramOrderOperatorWorkflowService.php'));
        $this->assertStringContainsString('LIFECYCLE_COMPLETED', $src);
        $this->assertStringContainsString('renderText', $src);
        $this->assertStringNotContainsString('"✅ Заявка выполнена', $src);
        $this->assertStringContainsString('completedKeyboard', $src);
        $this->assertStringContainsString('LIFECYCLE_CLAIMED', $src);
    }
}
