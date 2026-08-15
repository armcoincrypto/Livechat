<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiLearningAnalyzer;
use Illuminate\Console\Command;

final class SupportChatAiLearningAnalyzeCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-analyze
                            {--days=14 : Lookback window in days}
                            {--json : Output machine-readable JSON}
                            {--backfill : Attempt to match unmatched operator replies}';

    protected $description = 'Analyze AI suggestion usage and operator reply patterns (read-only metrics).';

    public function handle(SupportAiLearningAnalyzer $analyzer): int
    {
        if (! $analyzer->isAvailable()) {
            $this->error('Learning tables are not available. Run migrations first.');

            return self::FAILURE;
        }

        $days = max(1, min(365, (int) $this->option('days')));

        if ((bool) $this->option('backfill')) {
            $backfilled = $analyzer->backfillOperatorMatches($days);
            if (! (bool) $this->option('json')) {
                $this->line('Backfilled operator matches: '.$backfilled);
                $this->line('');
            }
        }

        $analysis = $analyzer->analyzeRecentEvents($days);

        if ((bool) $this->option('json')) {
            $this->line(json_encode(array_merge($analysis, ['status' => 'PASS']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('AI Learning Analysis');
        $this->line('');
        $this->line('Events: '.$analysis['events']);
        $this->line('Operator replies matched: '.$analysis['operator_replies_matched']);
        $this->line('Suggestion usage rate: '.($analysis['usage_rate'] !== null ? $analysis['usage_rate'].'%' : 'n/a'));
        $this->line('Rewrite rate: '.($analysis['rewrite_rate'] !== null ? $analysis['rewrite_rate'].'%' : 'n/a'));
        $this->line('');
        $this->line('Weak intents:');
        if ($analysis['weak_intents'] === []) {
            $this->line('- (none detected)');
        } else {
            foreach ($analysis['weak_intents'] as $row) {
                $this->line('- '.$row['intent'].' (usage '.$row['usage_rate'].'%, n='.$row['count'].')');
            }
        }
        $this->line('');
        $this->line('Top ignored patterns:');
        if ($analysis['ignored_patterns'] === []) {
            $this->line('- (none detected)');
        } else {
            foreach ($analysis['ignored_patterns'] as $row) {
                $intent = $row['intent'] ?? 'unknown';
                $this->line('- '.$intent.' / '.$row['outcome'].' (count='.$row['count'].')');
            }
        }
        $this->line('');
        $this->line('Safety flags:');
        if ($analysis['safety_flags'] === []) {
            $this->line('- (none detected)');
        } else {
            foreach ($analysis['safety_flags'] as $row) {
                $this->line('- '.$row['flag'].' (count='.$row['count'].')');
            }
        }
        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }
}
