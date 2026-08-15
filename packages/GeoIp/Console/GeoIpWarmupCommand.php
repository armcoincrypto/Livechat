<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Console;

use iEXPackages\GeoIp\GeoIp;
use Illuminate\Console\Command;

final class GeoIpWarmupCommand extends Command
{
    protected $signature = 'geoip:warmup {file : Файл со списком IP} {--type=locate : locate|country|asn} {--limit=10000}';
    protected $description = 'Прогрев кеша GeoIP по списку IP';

    public function handle(GeoIp $geoip): int
    {
        $file = (string)$this->argument('file');
        $type = (string)$this->option('type');
        $limit = (int)$this->option('limit');

        if (!is_file($file)) {
            $this->error("Файл не найден: {$file}");
            return self::FAILURE;
        }

        $fh = fopen($file, 'rb');
        if (!$fh) {
            $this->error("Не удалось открыть файл: {$file}");
            return self::FAILURE;
        }

        $count = 0;
        while (!feof($fh) && $count < $limit) {
            $line = trim((string)fgets($fh));
            if ($line === '') continue;

            match ($type) {
                'country' => $geoip->country($line),
                'asn' => $geoip->asn($line),
                default => $geoip->locate($line),
            };

            $count++;
            if ($count % 500 === 0) {
                $this->line("Processed: {$count}");
            }
        }
        fclose($fh);

        $this->info("Done. Processed: {$count}");
        return self::SUCCESS;
    }
}
