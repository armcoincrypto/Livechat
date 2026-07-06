<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportConversation;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single source of truth for support conversation lifecycle field transitions and related logs.
 */
final class SupportConversationLifecycleService
{
    public function __construct(
        private readonly SupportAiConversationOutcomeService $outcomeTracking,
    ) {}

    /**
     * Operator-triggered close (admin UI or CLI). Idempotent when already closed (no log).
     *
     * @param  array<string, mixed>  $logExtra  Merged into the log context (e.g. CLI reason).
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

        $this->syncOutcome($conversation->fresh(), 'lifecycle_close_'.$context, [
            'trigger' => 'close',
        ]);
    }

    /**
     * Operator-triggered reopen (admin UI or CLI). Always applies status / closed_at like legacy CLI.
     */
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

        $this->syncOutcome($conversation->fresh(), 'lifecycle_reopen_'.$context, [
            'trigger' => 'reopen',
            'reopened_by' => 'operator',
        ]);
    }

    /**
     * Visitor message on a closed thread: reopen so the message can be stored and Telegram notify can run.
     *
     * @return bool True if the conversation was closed and is now reopened.
     */
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

        $this->syncOutcome($conversation->fresh(), 'lifecycle_reopen_visitor', [
            'trigger' => 'reopen',
            'reopened_by' => 'visitor',
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function syncOutcome(
        SupportConversation $conversation,
        string $source,
        array $context = [],
    ): void {
        try {
            $this->outcomeTracking->syncFromConversation($conversation, $source, null, $context);
        } catch (Throwable $e) {
            Log::warning('support-chat ai:outcome_record_failed', [
                'stage' => 'lifecycle_integration',
                'support_conversation_id' => $conversation->id,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);
        }
    }
}
