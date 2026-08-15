<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Console;

use GeoIp2\Database\Reader;
use Illuminate\Console\Command;

final class GeoIpInfoCommand extends Command
{
    protected $signature = 'geoip:info {type=city : city|country|asn}';
    protected $description = 'Информация о базе GeoIP (metadata)';

    public function handle(): int
    {
        $cfg = (array) config('geoip', []);
        $db = (array)($cfg['databases'] ?? []);
        $type = (string)$this->argument('type');

        $path = (string)($db[$type] ?? '');
        if ($path === '' || !is_file($path)) {
            $this->error("Файл базы не найден: {$path}");
            return self::FAILURE;
        }

        $meta = (new Reader($path))->metadata();

        $this->info("Файл: {$path}");
        $this->line("Размер: ".filesize($path)." bytes");
        $this->line("Изменён: ".date('c', (int)filemtime($path)));
        $this->line("DB type: ".$meta->databaseType);
        $this->line("Build epoch: ".$meta->buildEpoch);
        $this->line("Languages: ".implode(', ', $meta->languages));

        return self::SUCCESS;
    }
}
