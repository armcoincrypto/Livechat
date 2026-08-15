<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Console;

use iEXPackages\GeoIp\GeoIp;
use Illuminate\Console\Command;

final class GeoIpTestCommand extends Command
{
    protected $signature = 'geoip:test {ip?} {--type=locate : locate|country|asn} {--locale=} {--compact}';
    protected $description = 'Тест GeoIP: вывод Location';

    public function handle(GeoIp $geoip): int
    {
        $ip = $this->argument('ip');
        $type = (string)$this->option('type');
        $locale = $this->option('locale');
        $compact = (bool)$this->option('compact');

        $loc = match ($type) {
            'country' => $geoip->country($ip, is_string($locale) ? $locale : null),
            'asn' => $geoip->asn($ip, is_string($locale) ? $locale : null),
            default => $geoip->locate($ip, is_string($locale) ? $locale : null),
        };

        $data = $compact ? $loc->toCompactArray() : $loc->toArray();
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info('Summary: '.$loc->summary());

        return self::SUCCESS;
    }
}
