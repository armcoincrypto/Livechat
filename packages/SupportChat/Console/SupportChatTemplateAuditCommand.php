<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use App\Models\SupportAiTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SupportChatTemplateAuditCommand extends Command
{
    protected $signature = 'support-chat:template-audit
                            {--json : Output JSON}';

    protected $description = 'Audit support AI operator template coverage and validation status.';

    public function handle(): int
    {
        $total = SupportAiTemplate::query()->count();
        $active = SupportAiTemplate::query()->active()->count();
        $validated = SupportAiTemplate::query()->validated()->count();
        $needsReview = SupportAiTemplate::query()->where('requires_validation', true)->count();

        $categories = SupportAiTemplate::query()
            ->select('category', DB::raw('COUNT(*) as cnt'))
            ->groupBy('category')
            ->orderByDesc('cnt')
            ->pluck('cnt', 'category')
            ->all();

        $types = SupportAiTemplate::query()
            ->select('template_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('template_type')
            ->orderByDesc('cnt')
            ->pluck('cnt', 'template_type')
            ->all();

        $topIntents = SupportAiTemplate::query()
            ->select('intent', DB::raw('SUM(frequency) as total_freq'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('intent')
            ->orderByDesc('total_freq')
            ->limit(10)
            ->get()
            ->map(static fn ($row): array => [
                'intent' => $row->intent,
                'templates' => (int) $row->cnt,
                'total_frequency' => (int) $row->total_freq,
            ])
            ->all();

        $report = [
            'templates' => $total,
            'active_templates' => $active,
            'categories' => $categories,
            'template_types' => $types,
            'validated' => $validated,
            'needs_review' => $needsReview,
            'top_intents' => $topIntents,
            'templates_enabled' => filter_var(config('support_chat.ai.templates.enabled', true), FILTER_VALIDATE_BOOLEAN),
            'include_unvalidated' => filter_var(config('support_chat.ai.templates.include_unvalidated', false), FILTER_VALIDATE_BOOLEAN),
            'max_templates' => (int) config('support_chat.ai.templates.max', 3),
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->line('Support AI Template Audit');
        $this->line('=========================');
        $this->line('Templates: '.$total);
        $this->line('Active: '.$active);
        $this->line('Validated: '.$validated);
        $this->line('Needs review: '.$needsReview);
        $this->line('');
        $this->line('Categories:');
        foreach ($categories as $category => $count) {
            $this->line("  - {$category}: {$count}");
        }
        $this->line('');
        $this->line('Template types:');
        foreach ($types as $type => $count) {
            $this->line("  - {$type}: {$count}");
        }
        $this->line('');
        $this->line('Top intents (by mined frequency):');
        foreach ($topIntents as $row) {
            $this->line(sprintf(
                '  - %s: %d template(s), freq sum=%d',
                $row['intent'],
                $row['templates'],
                $row['total_frequency']
            ));
        }
        $this->line('');
        $this->line('Runtime: templates_enabled='.($report['templates_enabled'] ? 'true' : 'false')
            .', include_unvalidated='.($report['include_unvalidated'] ? 'true' : 'false')
            .', max='.$report['max_templates']);

        return self::SUCCESS;
    }
}
