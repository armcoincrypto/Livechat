<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\BestChangeCodesCacheRefresher;
use Illuminate\Console\Command;

/**
 * Refresh storage/app/bestchange-codes.json from live BestChange catalog.
 * Preserves intentional overrides; dry-run by default.
 */
final class RatesRefreshBestchangeCodesCacheCommand extends Command
{
    protected $signature = 'rates:refresh-bestchange-codes-cache
        {--apply : Write refreshed codes to storage/app/bestchange-codes.json}
        {--no-warmup : Skip forced catalog warmup}';

    protected $description = 'Rebuild bestchange-codes.json from live BC catalog + verified overrides (dry-run default).';

    public function handle(): int
    {
        $refresher = BestChangeCodesCacheRefresher::fromApp();
        $result = $refresher->refresh(
            write: (bool) $this->option('apply'),
            forceCatalogWarmup: ! (bool) $this->option('no-warmup'),
        );

        $this->line(json_encode([
            'generated_at' => now()->toIso8601String(),
            'result' => $result,
            'roles' => [
                'RUNTIME_AUTHORITY' => 'BestChange live currencies API / CatalogRepository',
                'STATIC_CACHE_ROLE' => 'storage/app/bestchange-codes.json CACHE_AND_AUDIT',
                'AUDIT_AUTHORITY' => 'live API confirmation required for migration cert',
                'BUILD_ARTIFACT' => 'resources/rates/bestchange-catalog-critical-codes.json + overrides',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
