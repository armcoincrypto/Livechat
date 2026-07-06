<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiLearningCandidate;
use App\Models\SupportAiLearningEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Generates pending learning candidates from analyzed events. Never auto-applies changes.
 */
final class SupportAiLearningCandidateService
{
    public function __construct(
        private readonly SupportAiLearningAnalyzer $analyzer,
        private readonly SupportAiLearningService $learning,
    ) {}

    public function isAvailable(): bool
    {
        return Schema::hasTable('support_ai_learning_candidates');
    }

    /**
     * @return array{created: int, skipped: int, candidates: list<int>}
     */
    public function generateAllCandidates(int $days = 14, bool $dryRun = false): array
    {
        if (! $this->isAvailable()) {
            return ['created' => 0, 'skipped' => 0, 'candidates' => []];
        }

        $this->analyzer->backfillOperatorMatches($days);
        $analysis = $this->analyzer->analyzeRecentEvents($days);

        $created = 0;
        $skipped = 0;
        $ids = [];

        foreach ($this->generatePlaybookCandidates($days, $analysis) as $payload) {
            $result = $this->storePendingCandidate($payload, $dryRun);
            if ($result === null) {
                $skipped++;
                continue;
            }
            $created++;
            $ids[] = $result;
        }

        foreach ($this->generateToneRuleCandidates($analysis) as $payload) {
            $result = $this->storePendingCandidate($payload, $dryRun);
            if ($result === null) {
                $skipped++;
                continue;
            }
            $created++;
            $ids[] = $result;
        }

        foreach ($this->generateSafetyRuleCandidates($days, $analysis) as $payload) {
            $result = $this->storePendingCandidate($payload, $dryRun);
            if ($result === null) {
                $skipped++;
                continue;
            }
            $created++;
            $ids[] = $result;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'candidates' => $ids,
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return list<array<string, mixed>>
     */
    public function generatePlaybookCandidates(int $days, array $analysis = []): array
    {
        if ($analysis === []) {
            $analysis = $this->analyzer->analyzeRecentEvents($days);
        }

        $since = Carbon::now()->subDays(max(1, min(365, $days)));
        $events = SupportAiLearningEvent::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('operator_reply')
            ->whereIn('outcome', ['rewritten', 'ignored'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $candidates = [];
        foreach ($events as $event) {
            $intent = (string) ($event->intent ?? 'unknown_context');
            $operatorReply = trim((string) $event->operator_reply);
            if ($operatorReply === '') {
                continue;
            }

            $suggestions = is_array($event->suggestions) ? $event->suggestions : [];
            $before = '';
            foreach ($suggestions as $suggestion) {
                $text = trim((string) ($suggestion['text'] ?? ''));
                if ($text !== '') {
                    $before = $text;
                    break;
                }
            }

            $problem = $this->problemSummaryForIntent($intent, (string) $event->outcome);
            $candidates[] = [
                'candidate_type' => SupportAiLearningCandidate::TYPE_PLAYBOOK_EXAMPLE,
                'status' => SupportAiLearningCandidate::STATUS_PENDING,
                'source' => 'autolearn_analyzer',
                'intent' => $intent,
                'language' => $event->language,
                'title' => 'Operator-preferred reply for '.$intent,
                'problem_summary' => $problem,
                'proposed_example' => $operatorReply,
                'before_example' => $before !== '' ? $before : null,
                'after_example' => $operatorReply,
                'evidence' => [
                    'learning_event_id' => $event->id,
                    'outcome' => $event->outcome,
                    'edit_distance_ratio' => $event->edit_distance_ratio,
                ],
                'score' => $event->quality_score,
                'risk_level' => $this->riskLevelForText($operatorReply),
            ];
        }

        return $this->dedupeCandidates($candidates);
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return list<array<string, mixed>>
     */
    public function generateToneRuleCandidates(array $analysis): array
    {
        $candidates = [];
        foreach ($analysis['weak_intents'] ?? [] as $row) {
            $intent = (string) ($row['intent'] ?? '');
            if ($intent === '') {
                continue;
            }

            $candidates[] = [
                'candidate_type' => SupportAiLearningCandidate::TYPE_TONE_RULE,
                'status' => SupportAiLearningCandidate::STATUS_PENDING,
                'source' => 'autolearn_analyzer',
                'intent' => $intent,
                'language' => null,
                'title' => 'Low AI usage for '.$intent,
                'problem_summary' => 'Operators frequently rewrite or ignore AI suggestions for intent '.$intent
                    .' (usage rate '.($row['usage_rate'] ?? '?').'%).',
                'proposed_rule' => 'Prefer concise, action-oriented replies for '.$intent.' without repeating known data.',
                'proposed_example' => null,
                'before_example' => null,
                'after_example' => null,
                'evidence' => $row,
                'score' => isset($row['usage_rate']) ? (float) $row['usage_rate'] : null,
                'risk_level' => 'low',
            ];
        }

        return $this->dedupeCandidates($candidates);
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return list<array<string, mixed>>
     */
    public function generateSafetyRuleCandidates(int $days, array $analysis = []): array
    {
        if ($analysis === []) {
            $analysis = $this->analyzer->analyzeRecentEvents($days);
        }

        $candidates = [];
        foreach ($analysis['safety_flags'] ?? [] as $row) {
            $flag = (string) ($row['flag'] ?? '');
            $count = (int) ($row['count'] ?? 0);
            if ($flag === '' || $count < 1) {
                continue;
            }

            $candidates[] = [
                'candidate_type' => SupportAiLearningCandidate::TYPE_SAFETY_RULE,
                'status' => SupportAiLearningCandidate::STATUS_PENDING,
                'source' => 'autolearn_analyzer',
                'intent' => null,
                'language' => null,
                'title' => 'Strengthen safety guard: '.$flag,
                'problem_summary' => 'Detected '.$count.' learning event(s) with safety flag '.$flag.'.',
                'proposed_rule' => 'Block or warn on AI suggestions containing pattern: '.$flag,
                'proposed_example' => null,
                'before_example' => null,
                'after_example' => null,
                'evidence' => $row,
                'score' => min(100.0, $count * 5.0),
                'risk_level' => 'high',
            ];
        }

        return $this->dedupeCandidates($candidates);
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    public function dedupeCandidates(array $candidates): array
    {
        $seen = [];
        $out = [];

        foreach ($candidates as $candidate) {
            $key = implode('|', [
                (string) ($candidate['candidate_type'] ?? ''),
                (string) ($candidate['intent'] ?? ''),
                (string) ($candidate['proposed_rule'] ?? ''),
                $this->learning->hashSuggestion((string) ($candidate['proposed_example'] ?? '')),
            ]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $candidate;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storePendingCandidate(array $payload, bool $dryRun = false): ?int
    {
        $payload['status'] = SupportAiLearningCandidate::STATUS_PENDING;

        if ($this->candidateExists($payload)) {
            return null;
        }

        if ($dryRun) {
            return 0;
        }

        $candidate = new SupportAiLearningCandidate;
        $candidate->fill([
            'candidate_type' => (string) ($payload['candidate_type'] ?? SupportAiLearningCandidate::TYPE_PLAYBOOK_EXAMPLE),
            'status' => SupportAiLearningCandidate::STATUS_PENDING,
            'source' => $payload['source'] ?? 'autolearn_analyzer',
            'intent' => $payload['intent'] ?? null,
            'language' => $payload['language'] ?? null,
            'title' => $payload['title'] ?? null,
            'problem_summary' => $payload['problem_summary'] ?? null,
            'proposed_rule' => $payload['proposed_rule'] ?? null,
            'proposed_example' => $payload['proposed_example'] ?? null,
            'before_example' => $payload['before_example'] ?? null,
            'after_example' => $payload['after_example'] ?? null,
            'evidence' => $payload['evidence'] ?? null,
            'score' => $payload['score'] ?? null,
            'risk_level' => $payload['risk_level'] ?? 'medium',
        ]);
        $candidate->save();

        return (int) $candidate->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function candidateExists(array $payload): bool
    {
        $query = SupportAiLearningCandidate::query()
            ->where('candidate_type', (string) ($payload['candidate_type'] ?? ''))
            ->where('status', SupportAiLearningCandidate::STATUS_PENDING);

        if (($payload['intent'] ?? null) !== null) {
            $query->where('intent', (string) $payload['intent']);
        }

        $example = trim((string) ($payload['proposed_example'] ?? ''));
        if ($example !== '') {
            $query->where('proposed_example', $example);
        } else {
            $query->where('proposed_rule', (string) ($payload['proposed_rule'] ?? ''));
        }

        return $query->exists();
    }

    private function problemSummaryForIntent(string $intent, string $outcome): string
    {
        $base = match ($intent) {
            'wrong_network' => 'AI suggestions may be too generic; operators explain network verification.',
            'complaint_or_angry_customer' => 'AI tone may be insufficiently empathetic for upset customers.',
            'large_otc_exchange' => 'AI may miss OTC-specific reassurance and manual review steps.',
            'payment_proof_only' => 'AI may ask for redundant details when proof was already shared.',
            'eta_request' => 'AI may avoid giving actionable status update language.',
            default => 'Operators prefer a different reply style for this scenario.',
        };

        if ($outcome === 'ignored') {
            return $base.' Operators ignored AI suggestions.';
        }

        return $base.' Operators rewrote the suggestion substantially.';
    }

    private function riskLevelForText(string $text): string
    {
        $flags = $this->learning->detectSafetyFlags($text);

        return $flags !== [] ? 'high' : 'medium';
    }
}
