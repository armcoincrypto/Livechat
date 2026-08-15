<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiLearningPreviewService;
use Illuminate\Console\Command;

final class SupportChatAiLearningPreviewReportCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-preview-report
                            {--json : Output machine-readable JSON}';

    protected $description = 'Report latest AI learning preview package (read-only).';

    public function handle(SupportAiLearningPreviewService $preview): int
    {
        $package = $preview->readLatestPreview();
        if ($package === null) {
            $this->error('No preview found. Run support-chat:ai-learning-preview-generate first.');

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode(array_merge($package, ['status' => 'PASS']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $diff = is_array($package['diff_summary'] ?? null) ? $package['diff_summary'] : [];
        $impact = is_array($package['impact_estimate'] ?? null) ? $package['impact_estimate'] : [];
        $readiness = is_array($package['future_autolearn_4_readiness'] ?? null) ? $package['future_autolearn_4_readiness'] : [];

        $this->line('AI Learning Preview Report');
        $this->line('');
        $this->line('Generated: '.($package['generated_at'] ?? 'unknown'));
        $this->line('Candidates considered: '.($package['candidate_count'] ?? 0));
        $this->line('Approved: '.($package['approved'] ?? 0));
        $this->line('Staged: '.($package['staged'] ?? 0));
        $this->line('');
        $this->line('Impacted intents: '.implode(', ', $diff['intents_affected'] ?? []) ?: '(none)');
        $this->line('Added examples: +'.($diff['added_examples'] ?? 0));
        $this->line('');
        $this->line('Impact estimate:');
        $this->line('- Confidence: '.($impact['confidence'] ?? 'n/a'));
        $this->line('- Expected operator adoption: '.($impact['expected_operator_adoption_pct'] ?? 'n/a').'%');
        $this->line('- Expected rewrite reduction: '.($impact['expected_rewrite_reduction_pct'] ?? 'n/a').'%');
        $this->line('- Safety risk: '.($impact['safety_risk'] ?? 'n/a'));
        $this->line('');
        $this->line('Safety summary: high-risk candidates in preview package = '.($impact['high_risk_candidates'] ?? 0));
        $this->line('');
        $ready = ! empty($readiness['ready_for_staged_simulation']);
        $this->line('Future AUTOLEARN-4 readiness: '.($ready ? 'YES' : 'NO'));
        $this->line('');
        $this->line('Markdown: '.$preview->previewMarkdownPath());
        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }
}
