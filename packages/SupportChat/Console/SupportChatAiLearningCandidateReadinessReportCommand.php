<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use App\Models\SupportAiLearningCandidate;
use iEXPackages\SupportChat\Services\SupportAiCandidateSafetyFilterService;
use iEXPackages\SupportChat\Services\SupportAiLearningPromotionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class SupportChatAiLearningCandidateReadinessReportCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-candidate-readiness-report
                            {--days=30 : Lookback window in days}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Report candidate readiness for promotion (safety + accepted usage threshold).';

    public function handle(
        SupportAiCandidateSafetyFilterService $safetyFilter,
        SupportAiLearningPromotionService $promotion,
    ): int {
        $days = max(1, min(365, (int) $this->option('days')));
        $since = Carbon::now()->subDays($days);

        $candidates = SupportAiLearningCandidate::query()
            ->where('created_at', '>=', $since)
            ->orderBy('id')
            ->get();

        $eligibleBySafety = 0;
        $quarantined = 0;
        $passingThreshold = 0;
        $failingThreshold = 0;
        $ready = 0;
        $notReady = 0;
        $byIntent = [];
        $byType = [];

        foreach ($candidates as $candidate) {
            $isQuarantined = $safetyFilter->isQuarantined($candidate);
            if ($isQuarantined) {
                $quarantined++;
            } else {
                $eligibleBySafety++;
            }

            $threshold = $promotion->passesAcceptedUsageThreshold($candidate);
            if ($threshold['passes']) {
                $passingThreshold++;
            } else {
                $failingThreshold++;
            }

            $promotable = $promotion->isPromotable($candidate);
            if ($promotable) {
                $ready++;
            } else {
                $notReady++;
            }

            $intent = trim((string) ($candidate->intent ?? 'unknown_context'));
            $type = (string) $candidate->candidate_type;
            $byIntent[$intent] = ($byIntent[$intent] ?? 0) + 1;
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        ksort($byIntent);
        ksort($byType);

        $report = [
            'period_days' => $days,
            'period_since' => $since->toIso8601String(),
            'total_candidates' => $candidates->count(),
            'eligible_by_safety' => $eligibleBySafety,
            'quarantined' => $quarantined,
            'passing_accepted_threshold' => $passingThreshold,
            'failing_accepted_threshold' => $failingThreshold,
            'ready_for_promotion' => $ready,
            'not_ready' => $notReady,
            'required_min_accepted_samples' => max(1, (int) config('support_chat.ai.promotion_thresholds.min_accepted_samples', 3)),
            'promotion_threshold_enabled' => $promotion->isPromotionThresholdEnabled(),
            'by_intent' => $byIntent,
            'by_type' => $byType,
            'message' => $ready === 0
                ? 'No candidate is ready for promotion yet because there are not enough accepted operator examples.'
                : null,
            'status' => 'PASS',
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('AI Learning Candidate Readiness Report');
        $this->line('');
        $this->line('Period: '.$days.' days (since '.$since->toDateTimeString().')');
        $this->line('Total candidates: '.$report['total_candidates']);
        $this->line('Eligible by safety: '.$eligibleBySafety);
        $this->line('Quarantined: '.$quarantined);
        $this->line('Passing accepted threshold: '.$passingThreshold);
        $this->line('Failing accepted threshold: '.$failingThreshold);
        $this->line('Ready for promotion: '.$ready);
        $this->line('Not ready: '.$notReady);
        $this->line('');
        if ($ready === 0) {
            $this->line('Note: No candidate is ready for promotion yet because there are not enough accepted operator examples.');
            $this->line('');
        }
        $this->line('By intent:');
        if ($byIntent === []) {
            $this->line('- (none)');
        } else {
            foreach ($byIntent as $intent => $count) {
                $this->line('- '.$intent.': '.$count);
            }
        }
        $this->line('');
        $this->line('By type:');
        if ($byType === []) {
            $this->line('- (none)');
        } else {
            foreach ($byType as $type => $count) {
                $this->line('- '.$type.': '.$count);
            }
        }
        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }
}
