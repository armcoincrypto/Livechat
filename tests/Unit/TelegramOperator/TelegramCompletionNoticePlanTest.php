<?php

declare(strict_types=1);

namespace Tests\Unit\TelegramOperator;

use App\Services\TelegramOperator\TelegramCompletionNoticePlan;
use Tests\TestCase;

final class TelegramCompletionNoticePlanTest extends TestCase
{
    public function test_original_and_confirm_are_not_both_edited(): void
    {
        $ops = TelegramCompletionNoticePlan::decide(100, 11, 100, 22, 100);
        $edits = array_values(array_filter($ops, fn ($op) => $op['op'] === 'edit'));
        $deletes = array_values(array_filter($ops, fn ($op) => $op['op'] === 'delete'));
        $sends = array_values(array_filter($ops, fn ($op) => $op['op'] === 'send'));
        $this->assertCount(1, $edits);
        $this->assertSame(11, $edits[0]['message']);
        $this->assertCount(1, $deletes);
        $this->assertSame(22, $deletes[0]['message']);
        $this->assertCount(0, $sends);
    }

    public function test_same_message_is_edited_once(): void
    {
        $ops = TelegramCompletionNoticePlan::decide(100, 11, 100, 11, 100);
        $this->assertSame([['op' => 'edit', 'chat' => 100, 'message' => 11]], $ops);
    }

    public function test_missing_original_edits_confirm_or_sends_once(): void
    {
        $ops = TelegramCompletionNoticePlan::decide(null, null, 9, 4, 100);
        $this->assertSame([['op' => 'edit', 'chat' => 9, 'message' => 4]], $ops);
        $ops = TelegramCompletionNoticePlan::decide(null, null, null, null, 77);
        $this->assertSame([['op' => 'send', 'chat' => 77]], $ops);
    }
}
