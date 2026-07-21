<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\RubCatalogMonitor;
use Illuminate\Console\Command;

final class RatesRubCatalogMonitorCommand extends Command
{
    protected $signature = 'rates:rub-catalog-monitor {--format=text}';

    protected $description = 'Record RUB eligibility movement without changing rates or statuses';

    public function handle(RubCatalogMonitor $monitor): int
    {
        $record = $monitor->capture();
        if ((string) $this->option('format') === 'json') {
            $this->line((string) json_encode(
                $record,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        } else {
            $this->info(sprintf(
                'RUB exportable=%d snapshot=%s material_change=%s',
                $record['current_rub_export_count'],
                $record['snapshot_id'],
                $record['material_change'] ? 'yes' : 'no',
            ));
        }

        return self::SUCCESS;
    }
}
