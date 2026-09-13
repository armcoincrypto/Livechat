<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Seo\ValidExchangePairsNginxMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Canonical generator for storage/app/seo/valid_exchange_pairs.nginx.map.
 *
 * Source of truth: DirectionExchange::quoteable() (status=1 + both currencies status=0),
 * excluding archived currencies. Do not hand-edit the map.
 */
final class GenerateValidExchangePairsNginxMapCommand extends Command
{
    public const DEFAULT_RELATIVE_PATH = 'app/seo/valid_exchange_pairs.nginx.map';

    protected $signature = 'seo:generate-valid-exchange-pairs-map
        {--path= : Absolute output path (default: storage/app/seo/valid_exchange_pairs.nginx.map)}
        {--dry-run : Print summary and write nothing}';

    protected $description = 'Regenerate nginx valid-exchange-pairs map from quoteable directions';

    public function handle(): int
    {
        $pairs = $this->loadQuoteablePairs();
        $body = ValidExchangePairsNginxMap::render($pairs);
        $keyCount = preg_match_all('/^"[^"]+:[^"]+" 1;$/m', $body);

        $polygonGive = 0;
        foreach ($pairs as [$from]) {
            if ($from === 'USDTPOLYGON') {
                $polygonGive++;
            }
        }

        $this->line(sprintf(
            'quoteable_pairs=%d nginx_keys=%d usdtpolygon_give=%d',
            count($pairs),
            $keyCount,
            $polygonGive
        ));

        if ($this->option('dry-run')) {
            $this->info('dry-run: map not written');

            return self::SUCCESS;
        }

        $path = $this->resolveOutputPath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $body);
        $this->info('wrote ' . $path);

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function loadQuoteablePairs(): array
    {
        $rows = DirectionExchange::query()
            ->quoteable()
            ->with(['currency1:id,designation_xml,is_archive,status', 'currency2:id,designation_xml,is_archive,status'])
            ->get(['id', 'id_currency1', 'id_currency2']);

        $pairs = [];
        foreach ($rows as $row) {
            $fromCurrency = $row->currency1;
            $toCurrency = $row->currency2;
            if ($fromCurrency === null || $toCurrency === null) {
                continue;
            }
            if ((int) ($fromCurrency->is_archive ?? 0) !== 0 || (int) ($toCurrency->is_archive ?? 0) !== 0) {
                continue;
            }
            $from = strtoupper(trim((string) $fromCurrency->designation_xml));
            $to = strtoupper(trim((string) $toCurrency->designation_xml));
            if ($from === '' || $to === '') {
                continue;
            }
            $pairs[] = [$from, $to];
        }

        return $pairs;
    }

    private function resolveOutputPath(): string
    {
        $override = trim((string) $this->option('path'));
        if ($override !== '') {
            return $override;
        }

        return storage_path(self::DEFAULT_RELATIVE_PATH);
    }
}
