<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiConversationOutcome;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Tracks support conversation outcomes for AI learning correlation (telemetry only).
 */
final class SupportAiConversationOutcomeService
{
    /** @var list<string> */
    private const ESCALATION_PATTERNS = [
        '/\b(?:эскала|escalat|передан|передано|админ|admin|старш|senior)\b/iu',
        '/\b(?:manual review|ручн(?:ая|ую)\s+проверк|передал(?:и)?\s+(?:админ|старш))\b/iu',
    ];

    /** @var list<string> */
    private const FAILED_STATUS_HINTS = [
        'failed',
        'error',
        'timeout',
        'delivery_failed',
    ];

    public function isEnabled(): bool
    {
        if (! filter_var(config('support_chat.ai.outcome_tracking.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            return Schema::hasTable('support_ai_conversation_outcomes');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function syncFromConversation(
        SupportConversation $conversation,
        string $source,
        ?SupportMessage $triggerMessage = null,
        array $context = [],
    ): ?SupportAiConversationOutcome {
        if (! $this->isEnabled()) {
            return null;
        }

        try {
            $conversation->refresh();

            $existing = SupportAiConversationOutcome::query()
                ->where('conversation_id', (int) $conversation->id)
                ->first();

            $messageIds = $this->resolveMessagePointers($conversation, $triggerMessage, $context);
            $outcome = $this->inferOutcome($conversation, $existing, $context, $messageIds);

            $record = $existing ?? new SupportAiConversationOutcome;
            $previousOutcome = $existing?->outcome;

            $record->conversation_id = (int) $conversation->id;
            $record->outcome = $outcome;
            $record->last_operator_message_id = $messageIds['last_operator_message_id'];
            $record->last_visitor_message_id = $messageIds['last_visitor_message_id'];
            $record->source = $this->truncateSource($source);
            $record->metadata = $this->buildMetadata($conversation, $existing, $context, $previousOutcome, $outcome);

            if ($outcome === SupportAiConversationOutcome::OUTCOME_RESOLVED) {
                $record->resolved_at = $conversation->closed_at ?? now();
                $record->time_to_resolution_seconds = $this->computeTimeToResolution($conversation, $record->resolved_at);
            } else {
                if ($outcome !== SupportAiConversationOutcome::OUTCOME_REOPENED) {
                    $record->resolved_at = null;
                    $record->time_to_resolution_seconds = null;
                }
            }

            $record->save();

            SupportChatDiagnosticsLog::aiOutcomeSynced([
                'support_conversation_id' => (int) $conversation->id,
                'public_support_id' => $conversation->public_support_id,
                'telegram_forum_topic_id' => $conversation->telegram_forum_topic_id,
                'outcome' => $outcome,
                'previous_outcome' => $previousOutcome,
                'source' => $this->truncateSource($source),
                'last_visitor_message_id' => $messageIds['last_visitor_message_id'],
                'last_operator_message_id' => $messageIds['last_operator_message_id'],
                'conversation_status' => (string) $conversation->status,
            ]);

            return $record;
        } catch (Throwable $e) {
            Log::warning('support-chat ai:outcome_record_failed', [
                'support_conversation_id' => $conversation->id,
                'source' => $source,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{last_operator_message_id: int|null, last_visitor_message_id: int|null}
     */
    private function resolveMessagePointers(
        SupportConversation $conversation,
        ?SupportMessage $triggerMessage,
        array $context,
    ): array {
        $lastOperator = isset($context['last_operator_message_id'])
            ? (int) $context['last_operator_message_id']
            : null;
        $lastVisitor = isset($context['last_visitor_message_id'])
            ? (int) $context['last_visitor_message_id']
            : null;

        if ($triggerMessage !== null) {
            if ($triggerMessage->sender_type === SupportMessage::SENDER_OPERATOR) {
                $lastOperator = (int) $triggerMessage->id;
            } elseif ($triggerMessage->sender_type === SupportMessage::SENDER_VISITOR) {
                $lastVisitor = (int) $triggerMessage->id;
            }
        }

        if ($lastOperator === null) {
            $lastOperator = SupportMessage::query()
                ->where('support_conversation_id', (int) $conversation->id)
                ->where('sender_type', SupportMessage::SENDER_OPERATOR)
                ->orderByDesc('id')
                ->value('id');
            $lastOperator = $lastOperator !== null ? (int) $lastOperator : null;
        }

        if ($lastVisitor === null) {
            $lastVisitor = SupportMessage::query()
                ->where('support_conversation_id', (int) $conversation->id)
                ->where('sender_type', SupportMessage::SENDER_VISITOR)
                ->orderByDesc('id')
                ->value('id');
            $lastVisitor = $lastVisitor !== null ? (int) $lastVisitor : null;
        }

        return [
            'last_operator_message_id' => $lastOperator,
            'last_visitor_message_id' => $lastVisitor,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array{last_operator_message_id: int|null, last_visitor_message_id: int|null}  $messageIds
     */
    private function inferOutcome(
        SupportConversation $conversation,
        ?SupportAiConversationOutcome $existing,
        array $context,
        array $messageIds,
    ): string {
        if ($this->contextIndicatesFailure($context, $conversation)) {
            return SupportAiConversationOutcome::OUTCOME_FAILED;
        }

        if ($conversation->isClosed()) {
            return SupportAiConversationOutcome::OUTCOME_RESOLVED;
        }

        if ($this->contextIndicatesReopen($context, $existing, $conversation)) {
            return SupportAiConversationOutcome::OUTCOME_REOPENED;
        }

        if ($this->conversationHasEscalationSignal($conversation, $messageIds['last_operator_message_id'])) {
            return SupportAiConversationOutcome::OUTCOME_ESCALATED;
        }

        if ($this->isActiveConversationStatus($conversation->status)) {
            return SupportAiConversationOutcome::OUTCOME_PENDING;
        }

        return SupportAiConversationOutcome::OUTCOME_UNKNOWN;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextIndicatesFailure(array $context, SupportConversation $conversation): bool
    {
        if (! empty($context['failed']) || ! empty($context['error'])) {
            return true;
        }

        $status = mb_strtolower(trim((string) ($context['status'] ?? $conversation->status)), 'UTF-8');

        foreach (self::FAILED_STATUS_HINTS as $hint) {
            if (str_contains($status, $hint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextIndicatesReopen(
        array $context,
        ?SupportAiConversationOutcome $existing,
        SupportConversation $conversation,
    ): bool {
        if (! empty($context['reopened_by'])) {
            return true;
        }

        if ($existing === null) {
            return false;
        }

        if ($existing->outcome !== SupportAiConversationOutcome::OUTCOME_RESOLVED) {
            return false;
        }

        return ! $conversation->isClosed();
    }

    private function conversationHasEscalationSignal(
        SupportConversation $conversation,
        ?int $lastOperatorMessageId,
    ): bool {
        $body = null;

        if ($lastOperatorMessageId !== null && $lastOperatorMessageId > 0) {
            $body = SupportMessage::query()->whereKey($lastOperatorMessageId)->value('body');
        }

        if (! is_string($body) || trim($body) === '') {
            $body = SupportMessage::query()
                ->where('support_conversation_id', (int) $conversation->id)
                ->where('sender_type', SupportMessage::SENDER_OPERATOR)
                ->orderByDesc('id')
                ->value('body');
        }

        if (! is_string($body) || trim($body) === '') {
            return false;
        }

        foreach (self::ESCALATION_PATTERNS as $pattern) {
            if (preg_match($pattern, $body) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isActiveConversationStatus(string $status): bool
    {
        return in_array($status, [
            SupportConversation::STATUS_WAITING_OPERATOR,
            SupportConversation::STATUS_WAITING_VISITOR,
            SupportConversation::STATUS_OPEN,
            'open',
        ], true);
    }

    private function computeTimeToResolution(
        SupportConversation $conversation,
        ?\Illuminate\Support\Carbon $resolvedAt,
    ): ?int {
        if ($resolvedAt === null || $conversation->created_at === null) {
            return null;
        }

        $seconds = $conversation->created_at->diffInSeconds($resolvedAt, false);

        return $seconds >= 0 ? (int) $seconds : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildMetadata(
        SupportConversation $conversation,
        ?SupportAiConversationOutcome $existing,
        array $context,
        ?string $previousOutcome,
        string $outcome,
    ): array {
        $metadata = is_array($existing?->metadata) ? $existing->metadata : [];

        $metadata['conversation_status'] = (string) $conversation->status;
        $metadata['waiting_on'] = $conversation->waitingOn();
        $metadata['outcome'] = $outcome;
        $metadata['sync_context'] = array_filter([
            'reopened_by' => isset($context['reopened_by']) ? (string) $context['reopened_by'] : null,
            'trigger' => isset($context['trigger']) ? (string) $context['trigger'] : null,
        ]);

        if ($previousOutcome !== null && $previousOutcome !== $outcome) {
            $metadata['previous_outcome'] = $previousOutcome;
        }

        return $metadata;
    }

    private function truncateSource(string $source): string
    {
        $source = trim($source);
        if (mb_strlen($source, 'UTF-8') <= 64) {
            return $source;
        }

        return mb_substr($source, 0, 64, 'UTF-8');
    }
}
