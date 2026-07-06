<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use App\Models\SupportAiLearningCandidate;
use App\Models\SupportAiLearningEvent;
use iEXPackages\SupportChat\Services\SupportAiLearningAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class SupportChatAiLearningReportCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-report
                            {--days=30 : Lookback window in days}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Report AI learning metrics, candidate queue, and safety trends.';

    public function handle(SupportAiLearningAnalyzer $analyzer): int
    {
        if (! $analyzer->isAvailable()) {
            $this->error('Learning tables are not available. Run migrations first.');

            return self::FAILURE;
        }

        $days = max(1, min(365, (int) $this->option('days')));
        $since = Carbon::now()->subDays($days);

        $analysis = $analyzer->analyzeRecentEvents($days);

        $pending = SupportAiLearningCandidate::query()->where('status', SupportAiLearningCandidate::STATUS_PENDING)->count();
        $approved = SupportAiLearningCandidate::query()->where('status', SupportAiLearningCandidate::STATUS_APPROVED)->count();
        $rejected = SupportAiLearningCandidate::query()->where('status', SupportAiLearningCandidate::STATUS_REJECTED)->count();

        $eventsTotal = SupportAiLearningEvent::query()->where('created_at', '>=', $since)->count();
        $eventsWithSafety = SupportAiLearningEvent::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('safety_flags')
            ->count();

        $report = [
            'days' => $days,
            'events' => $eventsTotal,
            'usage_rate' => $analysis['usage_rate'],
            'rewrite_rate' => $analysis['rewrite_rate'],
            'weak_intents' => $analysis['weak_intents'],
            'safety_flags' => $analysis['safety_flags'],
            'candidates' => [
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
            ],
            'safety_trend' => [
                'events_with_flags' => $eventsWithSafety,
                'events_total' => $eventsTotal,
            ],
            'status' => 'PASS',
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('AI Learning Report');
        $this->line('');
        $this->line('Window: '.$days.' days');
        $this->line('Events: '.$eventsTotal);
        $this->line('Usage rate: '.($analysis['usage_rate'] !== null ? $analysis['usage_rate'].'%' : 'n/a'));
        $this->line('Rewrite rate: '.($analysis['rewrite_rate'] !== null ? $analysis['rewrite_rate'].'%' : 'n/a'));
        $this->line('');
        $this->line('Pending candidates: '.$pending);
        $this->line('Approved: '.$approved);
        $this->line('Rejected: '.$rejected);
        $this->line('');
        $this->line('Weak intents:');
        if ($analysis['weak_intents'] === []) {
            $this->line('- (none)');
        } else {
            foreach ($analysis['weak_intents'] as $row) {
                $this->line('- '.$row['intent'].' ('.$row['usage_rate'].'%)');
            }
        }
        $this->line('');
        $this->line('Safety trend: '.$eventsWithSafety.' / '.$eventsTotal.' events with flags');
        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }
}
