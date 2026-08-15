<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\DerivedMarketBaselineAuthority;
use Illuminate\Console\Command;

final class DirectionsDerivedBaselineRefreshCommand extends Command
{
    protected $signature = 'directions:derived-baseline-refresh
        {--dry-run : show actions without writing}
        {--apply : apply derived baseline ownership}';

    protected $description = 'Refresh DERIVED_MARKET_BASELINE owned directions (e.g. 1249 GRAM→CARDKZT).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $auth = DerivedMarketBaselineAuthority::fromStorageApp();
        $results = $auth->refreshAll(dryRun: !$apply);
        $this->line(json_encode([
            'mode' => $apply ? 'apply' : 'dry-run',
            'results' => $results,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $owned = 0;
        $failed = 0;
        foreach ($results as $r) {
            if (($r['eval']['reason'] ?? '') === 'not_owned') {
                continue;
            }
            $owned++;
            if (empty($r['eval']['ok'])) {
                $failed++;
            }
        }
        if ($owned > 0 && $failed === $owned) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
