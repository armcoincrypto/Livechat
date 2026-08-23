<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

/**
 * Single-owner completion notice: edit original XOR send once. Never both.
 *
 * @phpstan-type Op array{op: string, chat: int|string, message?: int}
 */
final class TelegramCompletionNoticePlan
{
    /**
     * @return list<Op>
     */
    public static function decide(
        int|string|null $originalChatId,
        ?int $originalMessageId,
        int|string|null $confirmChatId,
        ?int $confirmMessageId,
        int|string $fallbackChatId,
    ): array {
        $hasOriginal = $originalChatId !== null && $originalChatId !== '' && $originalMessageId !== null && $originalMessageId > 0;
        $hasConfirm = $confirmChatId !== null && $confirmChatId !== '' && $confirmMessageId !== null && $confirmMessageId > 0;
        $same = $hasOriginal && $hasConfirm
            && (string) $originalChatId === (string) $confirmChatId
            && $originalMessageId === $confirmMessageId;

        if ($hasOriginal) {
            $ops = [[
                'op' => 'edit',
                'chat' => $originalChatId,
                'message' => $originalMessageId,
            ]];
            if ($hasConfirm && ! $same) {
                $ops[] = [
                    'op' => 'delete',
                    'chat' => $confirmChatId,
                    'message' => $confirmMessageId,
                ];
            }

            return $ops;
        }

        if ($hasConfirm) {
            return [[
                'op' => 'edit',
                'chat' => $confirmChatId,
                'message' => $confirmMessageId,
            ]];
        }

        return [[
            'op' => 'send',
            'chat' => $fallbackChatId,
        ]];
    }
}
