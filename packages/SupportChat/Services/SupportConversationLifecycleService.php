<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportConversation;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for support conversation lifecycle field transitions and related logs.
 */
final class SupportConversationLifecycleService
{
    /**
     * @param  array<string, mixed>  $logExtra
     */
    public function closeByOperator(SupportConversation $conversation, string $context, array $logExtra = []): void
    {
        if ($conversation->isClosed()) {
            return;
        }

        $conversation->forceFill([
            'status' => SupportConversation::STATUS_CLOSED,
            'closed_at' => now(),
        ])->save();

        Log::info('support-chat lifecycle: closed_by_operator_'.$context, array_merge([
            'conversation_uuid' => $conversation->uuid,
            'public_support_id' => $conversation->public_support_id,
        ], $logExtra));
    }

    public function reopenByOperator(SupportConversation $conversation, string $waiting, string $context): void
    {
        $waiting = strtolower(trim($waiting));
        $status = match ($waiting) {
            'visitor' => SupportConversation::STATUS_WAITING_VISITOR,
            default => SupportConversation::STATUS_WAITING_OPERATOR,
        };

        $conversation->forceFill([
            'status' => $status,
            'closed_at' => null,
        ])->save();

        Log::info('support-chat lifecycle: reopened_by_operator_'.$context, [
            'conversation_uuid' => $conversation->uuid,
            'public_support_id' => $conversation->public_support_id,
            'status' => $conversation->status,
        ]);
    }

    public function reopenIfClosedForVisitor(SupportConversation $conversation): bool
    {
        if (! $conversation->isClosed()) {
            return false;
        }

        $conversation->forceFill([
            'status' => SupportConversation::STATUS_WAITING_OPERATOR,
            'closed_at' => null,
        ])->save();

        Log::info('support-chat lifecycle: reopened_by_visitor', [
            'conversation_uuid' => $conversation->uuid,
            'public_support_id' => $conversation->public_support_id,
            'status' => $conversation->status,
        ]);

        return true;
    }
}
