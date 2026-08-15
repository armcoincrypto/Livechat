<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiLearningPromotionService;
use Illuminate\Console\Command;

final class SupportChatAiLearningEvaluateCandidatesCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-evaluate-candidates
                            {--limit=50 : Max pending candidates to evaluate}
                            {--days=30 : Lookback for operator similarity}
                            {--dry-run : Evaluate without updating DB}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Evaluate pending AI learning candidates and gate-promote to staged/approved/rejected.';

    public function handle(SupportAiLearningPromotionService $promotion): int
    {
        if (! app(\iEXPackages\SupportChat\Services\SupportAiLearningEvaluationService::class)->isAvailable()) {
            $this->error('Evaluation columns not available. Run migrations first.');

            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $days = max(7, min(365, (int) $this->option('days')));
        $dryRun = (bool) $this->option('dry-run');

        $result = $promotion->evaluateAndPromotePending($limit, $dryRun, ['days' => $days]);
        $payload = array_merge($result, ['status' => 'PASS', 'dry_run' => $dryRun]);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('AI Learning Candidate Evaluation');
        $this->line('');
        $this->line('Dry run: '.($dryRun ? 'yes' : 'no'));
        $this->line('Evaluated: '.$result['evaluated']);
        $this->line('Staged: '.$result['staged']);
        $this->line('Approved: '.$result['approved']);
        $this->line('Rejected: '.$result['rejected']);
        $this->line('');

        foreach ($result['candidates'] as $row) {
            $flags = empty($row['flags']) ? 'none' : implode(', ', $row['flags']);
            $this->line(sprintf(
                '- #%d [%s] intent=%s score=%.1f result=%s flags=%s',
                $row['id'],
                $row['type'],
                $row['intent'] ?? 'n/a',
                $row['overall_score'],
                $row['result'],
                $flags,
            ));
        }

        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }
}
