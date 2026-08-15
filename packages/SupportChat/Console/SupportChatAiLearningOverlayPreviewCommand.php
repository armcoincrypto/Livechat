<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiLearningOverlayService;
use Illuminate\Console\Command;

final class SupportChatAiLearningOverlayPreviewCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-overlay-preview
                            {--intent= : Filter overlay preview to a specific intent}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Dry-run preview of runtime AI learning overlay (no OpenAI, no Telegram).';

    public function handle(SupportAiLearningOverlayService $overlay): int
    {
        $intent = $this->option('intent');
        $intent = is_string($intent) && trim($intent) !== '' ? trim($intent) : null;

        $package = $overlay->buildOverlayPackage($intent, null);
        $stats = is_array($package['stats'] ?? null) ? $package['stats'] : [];

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'overlay_enabled' => (bool) ($stats['overlay_enabled'] ?? false),
                'eligible_candidates' => (int) ($stats['eligible_candidates'] ?? 0),
                'included_candidates' => (int) ($stats['included_candidates'] ?? 0),
                'total_chars' => (int) ($stats['total_chars'] ?? 0),
                'intents' => $stats['intents'] ?? [],
                'context_preview' => (string) ($package['context'] ?? ''),
                'status' => 'PASS',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('Overlay enabled: '.((bool) ($stats['overlay_enabled'] ?? false) ? 'true' : 'false'));
        $this->line('Eligible candidates: '.(int) ($stats['eligible_candidates'] ?? 0));
        $this->line('Included candidates: '.(int) ($stats['included_candidates'] ?? 0));
        $this->line('Total chars: '.(int) ($stats['total_chars'] ?? 0));
        $this->line('Intents:');

        $intentCounts = is_array($stats['intents'] ?? null) ? $stats['intents'] : [];
        if ($intentCounts === []) {
            $this->line('- (none)');
        } else {
            foreach ($intentCounts as $name => $count) {
                $this->line('- '.$name.': '.$count);
            }
        }

        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }
}
