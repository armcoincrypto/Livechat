<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use App\Models\SupportAiKnowledgeRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SupportChatKnowledgeAuditCommand extends Command
{
    protected $signature = 'support-chat:knowledge-audit
                            {--json : Output JSON}';

    protected $description = 'Audit support AI knowledge rule coverage and validation status.';

    public function handle(): int
    {
        $total = SupportAiKnowledgeRule::query()->count();
        $active = SupportAiKnowledgeRule::query()->active()->count();
        $validated = SupportAiKnowledgeRule::query()->validated()->count();
        $unvalidated = SupportAiKnowledgeRule::query()->where('requires_validation', true)->count();
        $highRisk = SupportAiKnowledgeRule::query()->where('risk_level', 'high')->count();

        $categories = SupportAiKnowledgeRule::query()
            ->select('category', DB::raw('COUNT(*) as cnt'))
            ->groupBy('category')
            ->orderByDesc('cnt')
            ->pluck('cnt', 'category')
            ->all();

        $coverageGaps = [
            'Per-direction confirmation counts beyond XMR→SBP',
            'Exact minimum/maximum limits per direction',
            'Complete bank matrix (single vs split by bank)',
            'AML hold trigger visitor-facing explanations',
            'OTC numeric threshold definition',
            'International direction flows (Zelle, Wise, Revolut)',
        ];

        $report = [
            'total_rules' => $total,
            'active_rules' => $active,
            'validated_rules' => $validated,
            'unvalidated_rules' => $unvalidated,
            'high_risk_rules' => $highRisk,
            'categories' => $categories,
            'coverage_gaps' => $coverageGaps,
            'knowledge_enabled' => filter_var(config('support_chat.ai.knowledge.enabled', true), FILTER_VALIDATE_BOOLEAN),
            'include_unvalidated' => filter_var(config('support_chat.ai.knowledge.include_unvalidated', false), FILTER_VALIDATE_BOOLEAN),
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->line('Support AI Knowledge Audit');
        $this->line('========================');
        $this->line('Total rules: '.$total);
        $this->line('Active rules: '.$active);
        $this->line('Validated rules (requires_validation=false): '.$validated);
        $this->line('Unvalidated rules: '.$unvalidated);
        $this->line('High-risk rules: '.$highRisk);
        $this->line('');
        $this->line('Categories:');
        foreach ($categories as $category => $count) {
            $this->line("  - {$category}: {$count}");
        }
        $this->line('');
        $this->line('Coverage gaps (from 1A extraction):');
        foreach ($coverageGaps as $gap) {
            $this->line('  - '.$gap);
        }
        $this->line('');
        $this->line('Runtime: knowledge_enabled='.($report['knowledge_enabled'] ? 'true' : 'false')
            .', include_unvalidated='.($report['include_unvalidated'] ? 'true' : 'false'));

        return self::SUCCESS;
    }
}
