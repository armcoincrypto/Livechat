<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Console;

use iEXPackages\GeoIp\GeoIp;
use Illuminate\Console\Command;

final class GeoIpBenchCommand extends Command
{
    protected $signature = 'geoip:bench {ip : IP} {--n=1000} {--type=locate : locate|country|asn}';
    protected $description = 'Бенчмарк GeoIP';

    public function handle(GeoIp $geoip): int
    {
        $ip = (string)$this->argument('ip');
        $n = (int)$this->option('n');
        $type = (string)$this->option('type');

        $t0 = microtime(true);
        for ($i = 0; $i < $n; $i++) {
            match ($type) {
                'country' => $geoip->country($ip),
                'asn' => $geoip->asn($ip),
                default => $geoip->locate($ip),
            };
        }
        $ms = (microtime(true) - $t0) * 1000;

        $this->info("Iterations: {$n}");
        $this->info("Total ms: ".number_format($ms, 2));
        $this->info("Avg ms: ".number_format($ms / max(1, $n), 4));

        return self::SUCCESS;
    }
}
