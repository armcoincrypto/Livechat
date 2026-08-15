<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SettingsKeyCollector;
use Illuminate\Console\Command;

final class IexSettingsKeysCommand extends Command
{
    protected $signature = 'iex:settings-keys
        {--with-sources : Show where each key was found (json/php files)}
        {--format=table : table|json|lines|csv}
        {--output= : Save result to a file (json/txt)}';

    protected $description = 'Collect all available iEXSetting keys from JSON configs, App\\Settings classes, and iEXSetting() usages';

    public function handle(SettingsKeyCollector $collector): int
    {
        $withSources = (bool) $this->option('with-sources');
        $format = strtolower((string) ($this->option('format') ?: 'table'));
        $output = (string) ($this->option('output') ?: '');

        $result = $collector->collect($withSources);

        // ---- JSON ----
        if ($format === 'json') {
            $payload = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
            $this->line($payload);

            if ($output !== '') {
                file_put_contents($output, $payload);
                $this->line("Saved: {$output}");
            }

            return self::SUCCESS;
        }

        // ---- LINES ----
        if ($format === 'lines') {
            foreach ($result['keys'] as $k) {
                $this->line($k);
            }

            if ($output !== '') {
                file_put_contents($output, implode(PHP_EOL, $result['keys']));
                $this->line("Saved: {$output}");
            }

            return self::SUCCESS;
        }

        // ---- CSV (comma separated) ----
        if ($format === 'csv') {
            $line = implode(', ', $result['keys']);
            $this->line($line);

            if ($output !== '') {
                file_put_contents($output, $line);
                $this->line("Saved: {$output}");
            }

            return self::SUCCESS;
        }

        // ---- TABLE ----
        if ($format === 'table') {
            $rows = [];

            foreach ($result['keys'] as $k) {
                $row = ['Key' => $k];

                if ($withSources) {
                    $src = $result['sources'][$k] ?? ['json' => [], 'php' => []];
                    $row['JSON'] = implode("\n", $src['json'] ?? []);
                    $row['PHP']  = implode("\n", $src['php'] ?? []);
                }

                $rows[] = $row;
            }

            $this->info('Total keys: ' . count($result['keys']));
            $headers = $withSources ? ['Key', 'JSON', 'PHP'] : ['Key'];
            $this->table($headers, $rows);

            if ($output !== '') {
                // Таблицу в файл сохраняем как построчный список (самый полезный формат)
                file_put_contents($output, implode(PHP_EOL, $result['keys']));
                $this->line("Saved: {$output}");
            }

            return self::SUCCESS;
        }

        $this->error("Unknown format: {$format}. Allowed: table|json|lines|csv");
        return self::FAILURE;
    }
}
