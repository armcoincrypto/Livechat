<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiLearningEvent;
use App\Models\SupportMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregates learning events into usage metrics and weak-case signals.
 */
final class SupportAiLearningAnalyzer
{
    public function __construct(
        private readonly SupportAiLearningService $learning,
    ) {}

    public function isAvailable(): bool
    {
        return Schema::hasTable('support_ai_learning_events');
    }

    /**
     * @return array{
     *     days: int,
     *     events: int,
     *     operator_replies_matched: int,
     *     usage_rate: float|null,
     *     rewrite_rate: float|null,
     *     weak_intents: list<array{intent: string, count: int, usage_rate: float|null}>,
     *     ignored_patterns: list<array{intent: string|null, outcome: string, count: int}>,
     *     safety_flags: list<array{flag: string, count: int}>,
     *     common_operator_replies: list<array{intent: string|null, sample: string, count: int}>
     * }
     */
    public function analyzeRecentEvents(int $days = 14): array
    {
        $days = max(1, min(365, $days));
        $since = Carbon::now()->subDays($days);

        $events = SupportAiLearningEvent::query()
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->get();

        $total = $events->count();
        $matched = $events->filter(static fn (SupportAiLearningEvent $e): bool => $e->operator_reply !== null)->count();

        $usageRate = $this->calculateUsageRate($events);
        $rewriteRate = $this->calculateRewriteRate($events);

        return [
            'days' => $days,
            'events' => $total,
            'operator_replies_matched' => $matched,
            'usage_rate' => $usageRate,
            'rewrite_rate' => $rewriteRate,
            'weak_intents' => $this->detectWeakIntents($events),
            'ignored_patterns' => $this->detectIgnoredSuggestions($events),
            'safety_flags' => $this->detectSafetyFlags($events),
            'common_operator_replies' => $this->detectCommonOperatorReplies($events),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SupportAiLearningEvent>  $events
     */
    public function calculateUsageRate($events): ?float
    {
        $withReply = $events->filter(static fn (SupportAiLearningEvent $e): bool => $e->operator_reply !== null);
        if ($withReply->isEmpty()) {
            return null;
        }

        $used = $withReply->filter(static function (SupportAiLearningEvent $e): bool {
            return in_array((string) $e->outcome, ['used_exact', 'used_near'], true);
        });

        return round(($used->count() / $withReply->count()) * 100, 1);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SupportAiLearningEvent>  $events
     */
    public function calculateRewriteRate($events): ?float
    {
        $withReply = $events->filter(static fn (SupportAiLearningEvent $e): bool => $e->operator_reply !== null);
        if ($withReply->isEmpty()) {
            return null;
        }

        $rewritten = $withReply->filter(static function (SupportAiLearningEvent $e): bool {
            return (bool) $e->operator_edited
                || in_array((string) $e->outcome, ['rewritten', 'ignored'], true);
        });

        return round(($rewritten->count() / $withReply->count()) * 100, 1);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SupportAiLearningEvent>  $events
     * @return list<array{intent: string, count: int, usage_rate: float|null}>
     */
    public function detectWeakIntents($events): array
    {
        $byIntent = $events->groupBy(static fn (SupportAiLearningEvent $e): string => (string) ($e->intent ?? 'unknown_context'));

        $weak = [];
        foreach ($byIntent as $intent => $group) {
            if ($group->count() < 2) {
                continue;
            }

            $usage = $this->calculateUsageRate($group);
            if ($usage !== null && $usage < 40.0) {
                $weak[] = [
                    'intent' => $intent,
                    'count' => $group->count(),
                    'usage_rate' => $usage,
                ];
            }
        }

        usort($weak, static fn (array $a, array $b): int => ($a['usage_rate'] ?? 100) <=> ($b['usage_rate'] ?? 100));

        return array_slice($weak, 0, 10);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SupportAiLearningEvent>  $events
     * @return list<array{intent: string|null, outcome: string, count: int}>
     */
    public function detectIgnoredSuggestions($events): array
    {
        $ignored = $events->filter(static fn (SupportAiLearningEvent $e): bool => (string) $e->outcome === 'ignored');
        $grouped = [];

        foreach ($ignored as $event) {
            $key = ((string) ($event->intent ?? 'unknown')).'|ignored';
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'intent' => $event->intent,
                    'outcome' => 'ignored',
                    'count' => 0,
                ];
            }
            $grouped[$key]['count']++;
        }

        $rows = array_values($grouped);
        usort($rows, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($rows, 0, 8);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SupportAiLearningEvent>  $events
     * @return list<array{flag: string, count: int}>
     */
    public function detectSafetyFlags($events): array
    {
        $counts = [];
        foreach ($events as $event) {
            $flags = is_array($event->safety_flags) ? $event->safety_flags : [];
            foreach ($flags as $flag) {
                $flag = (string) $flag;
                $counts[$flag] = ($counts[$flag] ?? 0) + 1;
            }
        }

        arsort($counts);
        $rows = [];
        foreach ($counts as $flag => $count) {
            $rows[] = ['flag' => $flag, 'count' => $count];
        }

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SupportAiLearningEvent>  $events
     * @return list<array{intent: string|null, sample: string, count: int}>
     */
    public function detectCommonOperatorReplies($events): array
    {
        $counts = [];
        foreach ($events as $event) {
            if ($event->operator_reply === null || trim((string) $event->operator_reply) === '') {
                continue;
            }

            $hash = (string) $event->operator_reply_hash;
            if ($hash === '') {
                continue;
            }

            if (! isset($counts[$hash])) {
                $counts[$hash] = [
                    'intent' => $event->intent,
                    'sample' => mb_substr((string) $event->operator_reply, 0, 160, 'UTF-8'),
                    'count' => 0,
                ];
            }
            $counts[$hash]['count']++;
        }

        $rows = array_values($counts);
        usort($rows, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($rows, 0, 5);
    }

    /**
     * Backfill operator replies for events missing outcomes (command-time analysis).
     */
    public function backfillOperatorMatches(int $days = 14): int
    {
        if (! $this->isAvailable()) {
            return 0;
        }

        $since = Carbon::now()->subDays(max(1, min(365, $days)));
        $events = SupportAiLearningEvent::query()
            ->where('created_at', '>=', $since)
            ->whereNull('operator_reply')
            ->whereNotNull('conversation_id')
            ->whereNotNull('message_id')
            ->orderBy('id')
            ->get();

        $updated = 0;
        foreach ($events as $event) {
            $operatorMessage = SupportMessage::query()
                ->where('support_conversation_id', (int) $event->conversation_id)
                ->where('sender_type', SupportMessage::SENDER_OPERATOR)
                ->where('id', '>', (int) $event->message_id)
                ->orderBy('id')
                ->first();

            if ($operatorMessage === null) {
                continue;
            }

            $conversation = $operatorMessage->conversation;
            if ($conversation === null) {
                continue;
            }

            $visitorAnchor = SupportMessage::query()->find((int) $event->message_id);
            if ($this->learning->recordOperatorReply($conversation, $operatorMessage, $visitorAnchor) !== null) {
                $updated++;
            }
        }

        return $updated;
    }
}
