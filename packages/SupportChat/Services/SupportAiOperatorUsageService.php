<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiLearningEvent;
use App\Models\SupportAiOperatorUsageMetric;
use App\Models\SupportAiSuggestionUsage;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * LC-H: persist operator AI draft usage metrics (telemetry only — never changes AI behavior).
 */
final class SupportAiOperatorUsageService
{
    private const PREVIEW_MAX = 160;

    public function __construct(
        private readonly SupportAiLearningService $learning,
    ) {}

    public function isEnabled(): bool
    {
        if (! filter_var(config('support_chat.ai.usage_monitoring.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            return Schema::hasTable('support_ai_operator_usage_metrics');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $meta
     */
    public function recordDraftGenerated(
        SupportConversation $conversation,
        SupportMessage $triggerMessage,
        array $result,
        array $meta = [],
    ): ?SupportAiOperatorUsageMetric {
        if (! $this->isEnabled() || ! $this->shouldRecordDraft()) {
            return null;
        }

        $suggestionText = $this->extractPrimarySuggestionText($result);
        if ($suggestionText === '') {
            return null;
        }

        try {
            $intent = trim((string) ($meta['intent'] ?? ''));
            $orderLookupUsed = (bool) ($meta['order_lookup_attempted'] ?? false);
            $directionLookupUsed = (bool) ($meta['direction_lookup_attempted'] ?? false);
            $sanitized = $this->learning->sanitizeLearningText($suggestionText);

            $metric = SupportAiOperatorUsageMetric::query()->firstOrNew([
                'conversation_id' => (int) $conversation->id,
                'visitor_message_id' => (int) $triggerMessage->id,
            ]);

            $metric->intent = $intent !== '' ? $intent : null;
            $metric->order_lookup_used = $orderLookupUsed;
            $metric->direction_lookup_used = $directionLookupUsed;
            $metric->draft_generated_at = $metric->draft_generated_at ?? now();
            $metric->operator_decision = $metric->operator_decision ?? SupportAiOperatorUsageMetric::DECISION_PENDING;
            $metric->suggestion_preview = $this->redactPreview($sanitized);
            $metric->suggestion_text_hash = $this->learning->hashSuggestion($sanitized);
            $metric->metadata = array_merge(is_array($metric->metadata) ? $metric->metadata : [], [
                'option_count' => count($result['options'] ?? []),
                'confidence' => (string) ($result['confidence'] ?? ''),
                'language' => (string) ($result['language'] ?? ''),
                'has_verified_order_status' => (bool) ($meta['has_verified_order_status'] ?? false),
                'order_lookup_found' => (bool) ($meta['order_lookup_found'] ?? false),
                'direction_lookup_found' => (bool) ($meta['direction_lookup_found'] ?? false),
                'direction_status' => isset($meta['direction_status']) ? (string) $meta['direction_status'] : null,
            ]);
            $metric->save();

            SupportChatDiagnosticsLog::operatorUsageDraftRecorded([
                'support_conversation_id' => (int) $conversation->id,
                'visitor_message_id' => (int) $triggerMessage->id,
                'intent' => $metric->intent,
                'order_lookup_used' => $orderLookupUsed,
                'direction_lookup_used' => $directionLookupUsed,
                'suggestion_text_hash' => $metric->suggestion_text_hash,
            ]);

            return $metric;
        } catch (Throwable $e) {
            Log::warning('support-chat ai:usage_draft_record_failed', [
                'support_conversation_id' => $conversation->id,
                'support_message_id' => $triggerMessage->id,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return null;
        }
    }

    public function recordOperatorOutcome(
        SupportAiSuggestionUsage $usage,
        SupportConversation $conversation,
        SupportMessage $operatorMessage,
        ?SupportMessage $visitorAnchor = null,
    ): ?SupportAiOperatorUsageMetric {
        if (! $this->isEnabled()) {
            return null;
        }

        try {
            $visitorMessageId = $usage->visitor_message_id;
            if ($visitorMessageId === null || $visitorMessageId < 1) {
                $visitorMessageId = $visitorAnchor?->id;
            }

            $metric = null;
            if ($visitorMessageId !== null && $visitorMessageId > 0) {
                $metric = SupportAiOperatorUsageMetric::query()
                    ->where('conversation_id', (int) $conversation->id)
                    ->where('visitor_message_id', (int) $visitorMessageId)
                    ->first();
            }

            if ($metric === null) {
                $metric = new SupportAiOperatorUsageMetric;
                $metric->conversation_id = (int) $conversation->id;
                $metric->visitor_message_id = $visitorMessageId;
                $metric->draft_generated_at = now();
                $metric->order_lookup_used = false;
                $metric->direction_lookup_used = false;
            }

            $metadata = is_array($usage->metadata) ? $usage->metadata : [];
            if ($metric->intent === null && isset($metadata['intent'])) {
                $metric->intent = is_string($metadata['intent']) ? $metadata['intent'] : null;
            }

            $metric->operator_message_id = (int) $operatorMessage->id;
            $metric->suggestion_usage_id = (int) $usage->id;
            $metric->operator_decision = $usage->decision;
            $metric->operator_replied_at = $operatorMessage->created_at ?? now();
            $metric->similarity_score = $usage->similarity_score;
            $metric->operator_text_hash = $usage->operator_text_hash;
            $metric->operator_reply_preview = $this->redactPreview((string) ($usage->operator_reply_preview ?? ''));

            if ($metric->suggestion_preview === null && $usage->suggestion_preview !== null) {
                $metric->suggestion_preview = $this->redactPreview((string) $usage->suggestion_preview);
            }
            if ($metric->suggestion_text_hash === null && $usage->suggestion_text_hash !== null) {
                $metric->suggestion_text_hash = $usage->suggestion_text_hash;
            }

            $metric->response_time_seconds = $this->computeResponseTimeSeconds(
                $visitorMessageId,
                $operatorMessage,
            );

            $metric->metadata = array_merge(is_array($metric->metadata) ? $metric->metadata : [], [
                'matched_by' => $usage->matched_by,
                'edit_distance' => $usage->edit_distance,
            ]);
            $metric->save();

            SupportChatDiagnosticsLog::operatorUsageOutcomeRecorded([
                'support_conversation_id' => (int) $conversation->id,
                'visitor_message_id' => $visitorMessageId,
                'operator_message_id' => (int) $operatorMessage->id,
                'operator_decision' => $usage->decision,
                'response_time_seconds' => $metric->response_time_seconds,
                'similarity_score' => $usage->similarity_score,
            ]);

            return $metric;
        } catch (Throwable $e) {
            Log::warning('support-chat ai:usage_outcome_record_failed', [
                'support_conversation_id' => $conversation->id,
                'support_message_id' => $operatorMessage->id,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return null;
        }
    }

    public function backfillFromHistoricalTelemetry(Carbon $since): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $count = 0;

        $events = SupportAiLearningEvent::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('suggestions')
            ->orderBy('id')
            ->get();

        foreach ($events as $event) {
            if ($event->message_id === null || $event->conversation_id === null) {
                continue;
            }

            $suggestions = is_array($event->suggestions) ? $event->suggestions : [];
            $text = $this->extractPrimarySuggestionText(['options' => $suggestions]);
            if ($text === '') {
                continue;
            }

            $metadata = is_array($event->metadata) ? $event->metadata : [];
            $sanitized = $this->learning->sanitizeLearningText($text);

            $metric = SupportAiOperatorUsageMetric::query()->firstOrNew([
                'conversation_id' => (int) $event->conversation_id,
                'visitor_message_id' => (int) $event->message_id,
            ]);

            if ($metric->exists
                && $metric->operator_decision !== null
                && $metric->operator_decision !== SupportAiOperatorUsageMetric::DECISION_PENDING) {
                continue;
            }

            $metric->intent = $event->intent;
            $metric->order_lookup_used = (bool) ($metadata['order_lookup_attempted'] ?? $metadata['order_lookup_used'] ?? false);
            $metric->direction_lookup_used = (bool) ($metadata['direction_lookup_attempted'] ?? $metadata['direction_lookup_used'] ?? false);
            $metric->draft_generated_at = $metric->draft_generated_at ?? ($event->created_at ?? now());
            $metric->operator_decision = $metric->operator_decision ?? SupportAiOperatorUsageMetric::DECISION_PENDING;
            $metric->suggestion_preview = $metric->suggestion_preview ?? $this->redactPreview($sanitized);
            $metric->suggestion_text_hash = $metric->suggestion_text_hash ?? $this->learning->hashSuggestion($sanitized);
            $metric->metadata = array_merge(is_array($metric->metadata) ? $metric->metadata : [], [
                'backfilled_from' => 'learning_event',
                'learning_event_id' => (int) $event->id,
            ]);
            $metric->save();
            $count++;
        }

        $usages = SupportAiSuggestionUsage::query()
            ->where('created_at', '>=', $since)
            ->orderBy('id')
            ->get();

        foreach ($usages as $usage) {
            if ($usage->conversation_id === null) {
                continue;
            }

            $conversation = SupportConversation::query()->find($usage->conversation_id);
            if ($conversation === null) {
                continue;
            }

            $operatorMessage = $usage->operator_message_id !== null
                ? SupportMessage::query()->find($usage->operator_message_id)
                : null;

            if ($operatorMessage === null) {
                continue;
            }

            $this->recordOperatorOutcome($usage, $conversation, $operatorMessage);
            $count++;
        }

        return $count;
    }

    public function redactPreview(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\b\d{10,}\b/u', '[ORDER]', $text) ?? $text;
        $text = preg_replace('/\b0x[0-9a-fA-F]{8,}\b/i', '[ADDR]', $text) ?? $text;
        $text = preg_replace('/\b[13][a-km-zA-HJ-NP-Z1-9]{25,34}\b/', '[WALLET]', $text) ?? $text;
        $text = preg_replace('/\b[0-9a-fA-F]{32,64}\b/', '[TX]', $text) ?? $text;
        $text = preg_replace('/\S+@\S+\.\S+/', '[EMAIL]', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        if (mb_strlen($text, 'UTF-8') <= self::PREVIEW_MAX) {
            return $text;
        }

        return mb_substr($text, 0, self::PREVIEW_MAX - 1, 'UTF-8').'…';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractPrimarySuggestionText(array $result): string
    {
        $options = $result['options'] ?? [];
        if (is_array($options)) {
            foreach (array_values($options) as $option) {
                if (! is_array($option)) {
                    continue;
                }
                $text = trim((string) ($option['text'] ?? ''));
                if ($text !== '') {
                    return $text;
                }
            }
        }

        $draft = trim((string) ($result['draft'] ?? ''));

        return $draft;
    }

    private function shouldRecordDraft(): bool
    {
        if (! app()->runningInConsole()) {
            return true;
        }

        return filter_var(
            config('support_chat.ai.usage_monitoring.record_in_console', false),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    private function computeResponseTimeSeconds(?int $visitorMessageId, SupportMessage $operatorMessage): ?int
    {
        if ($visitorMessageId === null || $visitorMessageId < 1) {
            return null;
        }

        $visitor = SupportMessage::query()->find($visitorMessageId);
        if ($visitor === null || $visitor->created_at === null || $operatorMessage->created_at === null) {
            return null;
        }

        $seconds = $operatorMessage->created_at->diffInSeconds($visitor->created_at, absolute: true);

        return max(0, (int) $seconds);
    }
}
