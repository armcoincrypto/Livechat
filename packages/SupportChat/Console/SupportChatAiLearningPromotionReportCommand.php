<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use App\Models\SupportAiLearningCandidate;
use iEXPackages\SupportChat\Services\SupportAiCandidateSafetyFilterService;
use iEXPackages\SupportChat\Services\SupportAiLearningEvaluationService;
use iEXPackages\SupportChat\Services\SupportAiLearningPromotionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SupportChatAiLearningPromotionReportCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-promotion-report
                            {--days=30 : Lookback window in days}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Report staged/approved/rejected learning candidates and evaluation trends.';

    public function handle(
        SupportAiLearningEvaluationService $evaluation,
        SupportAiCandidateSafetyFilterService $safetyFilter,
        SupportAiLearningPromotionService $promotion,
    ): int {
        $days = max(1, min(365, (int) $this->option('days')));
        $since = Carbon::now()->subDays($days);

        $pending = SupportAiLearningCandidate::query()->where('status', SupportAiLearningCandidate::STATUS_PENDING)->count();
        $staged = SupportAiLearningCandidate::query()->where('status', SupportAiLearningCandidate::STATUS_STAGED)->count();
        $approved = SupportAiLearningCandidate::query()->where('status', SupportAiLearningCandidate::STATUS_APPROVED)->count();
        $rejected = SupportAiLearningCandidate::query()->where('status', SupportAiLearningCandidate::STATUS_REJECTED)->count();

        $thresholdEnabled = $promotion->isPromotionThresholdEnabled();
        $requiredMin = max(1, min(50, (int) config('support_chat.ai.promotion_thresholds.min_accepted_samples', 3)));

        $quarantined = 0;
        $promotableStaged = 0;
        $promotableApproved = 0;
        $quarantineReasons = [];
        $passingThreshold = 0;
        $failingThreshold = 0;
        $unlinkedBlocked = 0;
        $thresholdFailureReasons = [];

        foreach (SupportAiLearningCandidate::query()->whereIn('status', [
            SupportAiLearningCandidate::STATUS_PENDING,
            SupportAiLearningCandidate::STATUS_STAGED,
            SupportAiLearningCandidate::STATUS_APPROVED,
        ])->get() as $candidate) {
            $threshold = $promotion->passesAcceptedUsageThreshold($candidate);

            if ($threshold['passes']) {
                $passingThreshold++;
            } else {
                $failingThreshold++;
                $reason = (string) ($threshold['reason'] ?? 'accepted_threshold_not_met');
                $thresholdFailureReasons[$reason] = ($thresholdFailureReasons[$reason] ?? 0) + 1;
                if ($reason === 'unlinked_candidate_blocked') {
                    $unlinkedBlocked++;
                }
            }

            if ($safetyFilter->isQuarantined($candidate)) {
                $quarantined++;
                $stored = $safetyFilter->getStoredFilter($candidate);
                foreach (is_array($stored['reasons'] ?? null) ? $stored['reasons'] : [] as $reason) {
                    $quarantineReasons[(string) $reason] = ($quarantineReasons[(string) $reason] ?? 0) + 1;
                }

                continue;
            }

            if (! $promotion->isPromotable($candidate)) {
                continue;
            }

            if ($candidate->status === SupportAiLearningCandidate::STATUS_STAGED) {
                $promotableStaged++;
            } elseif ($candidate->status === SupportAiLearningCandidate::STATUS_APPROVED) {
                $promotableApproved++;
            }
        }
        arsort($quarantineReasons);
        arsort($thresholdFailureReasons);

        $avgScore = SupportAiLearningCandidate::query()
            ->whereNotNull('evaluation_score')
            ->where('evaluated_at', '>=', $since)
            ->avg('evaluation_score');

        $topStaged = SupportAiLearningCandidate::query()
            ->where('status', SupportAiLearningCandidate::STATUS_STAGED)
            ->orderByDesc('evaluation_score')
            ->limit(20)
            ->get(['id', 'intent', 'candidate_type', 'proposed_example', 'evaluation_score', 'evidence']);

        $topStaged = $topStaged->filter(
            static fn (SupportAiLearningCandidate $candidate): bool => $promotion->isPromotable($candidate)
        )->take(5)->values();

        $rejectedReasons = SupportAiLearningCandidate::query()
            ->where('status', SupportAiLearningCandidate::STATUS_REJECTED)
            ->where('evaluated_at', '>=', $since)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['evaluation_flags']);

        $flagCounts = [];
        foreach ($rejectedReasons as $row) {
            $flags = is_array($row->evaluation_flags['flags'] ?? null)
                ? $row->evaluation_flags['flags']
                : [];
            foreach ($flags as $flag) {
                $flagCounts[(string) $flag] = ($flagCounts[(string) $flag] ?? 0) + 1;
            }
        }
        arsort($flagCounts);

        $weakIntents = SupportAiLearningCandidate::query()
            ->select('intent', DB::raw('count(*) as total'))
            ->where('status', SupportAiLearningCandidate::STATUS_REJECTED)
            ->where('evaluated_at', '>=', $since)
            ->whereNotNull('intent')
            ->groupBy('intent')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(static fn ($r): array => ['intent' => $r->intent, 'count' => (int) $r->total])
            ->all();

        $stagedExamples = [];
        foreach ($topStaged as $candidate) {
            $stagedExamples[] = [
                'id' => (int) $candidate->id,
                'intent' => $candidate->intent,
                'type' => $candidate->candidate_type,
                'score' => $candidate->evaluation_score,
                'example' => $evaluation->maskSensitiveForReport((string) ($candidate->proposed_example ?? '')),
            ];
        }

        $readyForPromotion = $promotableStaged + $promotableApproved;

        $report = [
            'days' => $days,
            'pending' => $pending,
            'staged' => $staged,
            'approved' => $approved,
            'rejected' => $rejected,
            'promotion_threshold_enabled' => $thresholdEnabled,
            'required_min_accepted_samples' => $requiredMin,
            'candidates_passing_threshold' => $passingThreshold,
            'candidates_failing_threshold' => $failingThreshold,
            'unlinked_candidates_blocked' => $unlinkedBlocked,
            'quarantined' => $quarantined,
            'promotable_staged' => $promotableStaged,
            'promotable_approved' => $promotableApproved,
            'ready_for_promotion' => $readyForPromotion,
            'threshold_failure_reasons' => array_map(
                static fn (string $reason, int $count): array => ['reason' => $reason, 'count' => $count],
                array_keys($thresholdFailureReasons),
                array_values($thresholdFailureReasons),
            ),
            'quarantine_reasons' => array_map(
                static fn (string $reason, int $count): array => ['reason' => $reason, 'count' => $count],
                array_keys($quarantineReasons),
                array_values($quarantineReasons),
            ),
            'average_evaluation_score' => $avgScore !== null ? round((float) $avgScore, 2) : null,
            'weak_intents' => $weakIntents,
            'top_staged_examples' => $stagedExamples,
            'rejected_safety_reasons' => array_map(
                static fn (string $flag, int $count): array => ['flag' => $flag, 'count' => $count],
                array_keys($flagCounts),
                array_values($flagCounts),
            ),
            'status' => 'PASS',
            'message' => $readyForPromotion === 0
                ? 'No candidate is ready for promotion yet because there are not enough accepted operator examples.'
                : null,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('AI Learning Promotion Report');
        $this->line('');
        $this->line('Window: '.$days.' days');
        $this->line('Pending: '.$pending);
        $this->line('Staged: '.$staged.' (promotable: '.$promotableStaged.')');
        $this->line('Approved: '.$approved.' (promotable: '.$promotableApproved.')');
        $this->line('Rejected: '.$rejected);
        $this->line('Quarantined (not promotable): '.$quarantined);
        $this->line('Promotion threshold enabled: '.($thresholdEnabled ? 'yes' : 'no'));
        $this->line('Required accepted samples: '.$requiredMin);
        $this->line('Candidates passing threshold: '.$passingThreshold);
        $this->line('Candidates failing threshold: '.$failingThreshold);
        $this->line('Unlinked candidates blocked: '.$unlinkedBlocked);
        $this->line('Ready for promotion: '.$readyForPromotion);
        $this->line('Average evaluation score: '.($avgScore !== null ? round((float) $avgScore, 2) : 'n/a'));
        $this->line('');
        $this->line('Top threshold failure reasons:');
        if ($thresholdFailureReasons === []) {
            $this->line('- (none)');
        } else {
            foreach ($thresholdFailureReasons as $reason => $count) {
                $this->line('- '.$reason.' (count='.$count.')');
            }
        }
        $this->line('');
        $this->line('Quarantine reasons (pending/staged/approved):');
        if ($quarantineReasons === []) {
            $this->line('- (none)');
        } else {
            foreach ($quarantineReasons as $reason => $count) {
                $this->line('- '.$reason.' (count='.$count.')');
            }
        }
        $this->line('');
        if ($readyForPromotion === 0) {
            $this->line('Note: No candidate is ready for promotion yet because there are not enough accepted operator examples.');
            $this->line('');
        }
        $this->line('Top weak intents (rejected):');
        if ($weakIntents === []) {
            $this->line('- (none)');
        } else {
            foreach ($weakIntents as $row) {
                $this->line('- '.$row['intent'].' (count='.$row['count'].')');
            }
        }
        $this->line('');
        $this->line('Top staged examples (promotable only):');
        if ($stagedExamples === []) {
            $this->line('- (none)');
        } else {
            foreach ($stagedExamples as $row) {
                $this->line('- #'.$row['id'].' ['.$row['intent'].'] score='.$row['score'].' — '.$row['example']);
            }
        }
        $this->line('');
        $this->line('Rejected safety reasons:');
        if ($flagCounts === []) {
            $this->line('- (none)');
        } else {
            foreach ($flagCounts as $flag => $count) {
                $this->line('- '.$flag.' (count='.$count.')');
            }
        }
        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }
}
