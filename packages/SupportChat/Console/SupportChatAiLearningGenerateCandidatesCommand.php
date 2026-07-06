<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use App\Models\SupportAiLearningCandidate;
use iEXPackages\SupportChat\Services\SupportAiLearningCandidateService;
use Illuminate\Console\Command;

final class SupportChatAiLearningGenerateCandidatesCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-generate-candidates
                            {--days=14 : Lookback window in days}
                            {--dry-run : Analyze only; do not persist candidates}';

    protected $description = 'Generate pending AI learning candidates from recent operator behavior (never auto-applies).';

    public function handle(SupportAiLearningCandidateService $candidates): int
    {
        if (! $candidates->isAvailable()) {
            $this->error('Learning candidate table is not available. Run migrations first.');

            return self::FAILURE;
        }

        $days = max(1, min(365, (int) $this->option('days')));
        $dryRun = (bool) $this->option('dry-run');

        $result = $candidates->generateAllCandidates($days, $dryRun);

        $this->line('AI Learning Candidate Generation');
        $this->line('');
        $this->line('Days: '.$days);
        $this->line('Dry run: '.($dryRun ? 'yes' : 'no'));
        $this->line('Created: '.$result['created']);
        $this->line('Skipped (duplicate/existing): '.$result['skipped']);

        if (! $dryRun && $result['candidates'] !== []) {
            $this->line('');
            $this->line('Recent pending candidates:');
            $rows = SupportAiLearningCandidate::query()
                ->where('status', SupportAiLearningCandidate::STATUS_PENDING)
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($rows as $row) {
                $this->line('- #'.$row->id.' ['.$row->candidate_type.'] intent='.($row->intent ?? 'n/a')
                    .' risk='.($row->risk_level ?? 'n/a'));
                if ($row->proposed_example) {
                    $sample = mb_substr((string) $row->proposed_example, 0, 120, 'UTF-8');
                    $this->line('  example: '.$sample.(mb_strlen((string) $row->proposed_example, 'UTF-8') > 120 ? '…' : ''));
                }
            }
        }

        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }
}
