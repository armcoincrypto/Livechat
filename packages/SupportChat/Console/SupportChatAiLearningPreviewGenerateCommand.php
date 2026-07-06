<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiLearningPreviewService;
use Illuminate\Console\Command;
use RuntimeException;

final class SupportChatAiLearningPreviewGenerateCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-preview-generate
                            {--days=30 : Lookback for impact baseline metrics}
                            {--json : Output machine-readable JSON only}';

    protected $description = 'Generate preview playbook/prompt artifacts from staged/approved candidates (no live changes).';

    public function handle(SupportAiLearningPreviewService $preview): int
    {
        if (! $preview->isAvailable()) {
            $this->error('Learning candidates table is not available.');

            return self::FAILURE;
        }

        $days = max(7, min(365, (int) $this->option('days')));

        try {
            $package = $preview->generatePreview($days);
            $preview->writePreviewArtifacts($package);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode(array_merge($package, ['status' => 'PASS']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $diff = is_array($package['diff_summary'] ?? null) ? $package['diff_summary'] : [];
        $impact = is_array($package['impact_estimate'] ?? null) ? $package['impact_estimate'] : [];

        $this->line('AI Learning Preview Generation');
        $this->line('');
        $this->line('Candidates considered: '.($package['candidate_count'] ?? 0));
        $this->line('Approved: '.($package['approved'] ?? 0));
        $this->line('Staged: '.($package['staged'] ?? 0));
        $this->line('Added examples: +'.($diff['added_examples'] ?? 0));
        $this->line('Intents affected: '.($diff['intents_affected_count'] ?? 0));
        $this->line('Confidence: '.($impact['confidence'] ?? 'n/a'));
        $this->line('');
        $this->line('Artifacts:');
        $this->line('- '.$preview->previewJsonPath());
        $this->line('- '.$preview->previewMarkdownPath());
        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }
}
