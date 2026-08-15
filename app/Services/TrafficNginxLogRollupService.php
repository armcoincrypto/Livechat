<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use iEXPackages\GeoIp\GeoIp;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only nginx access log rollup for privacy-safe Telegram traffic reports.
 * Raw IPs are used in memory only for GeoIP aggregation and are never persisted or emitted.
 */
final class TrafficNginxLogRollupService
{
    private const LOG_PATTERN = '/^(?<ip>\S+) \S+ \S+ \[(?<time>[^\]]+)\] "(?<method>\S+) (?<path>\S+) HTTP\/[\d.]+" (?<status>\d+) \S+ "(?<referer>[^"]*)" "(?<ua>[^"]*)"/';

    /** @var list<string> */
    private const LOCALE_CODES = ['en', 'ru', 'uk', 'ka'];

    /** @var list<string> */
    private const BOT_UA_FRAGMENTS = [
        'bot', 'crawl', 'spider', 'slurp', 'headless', 'wget', 'curl/', 'python-requests',
        'googlebot', 'bingbot', 'yandex', 'petalbot', 'semrush', 'ahrefs', 'bytespider',
        'facebookexternalhit', 'uptime', 'monitor', 'pingdom', 'exswaping-notify',
    ];

    /** @var list<string> */
    private const STATIC_EXTENSIONS = [
        '.js', '.css', '.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', '.ico',
        '.woff', '.woff2', '.ttf', '.eot', '.map', '.avif',
    ];

    public function __construct(
        private readonly GeoIp $geoIp,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     available: bool,
     *     lines_scanned: int,
     *     lines_matched: int,
     *     countries: list<array{code: string, name: string, count: int, flag: string}>,
     *     locales: list<array{code: string, count: int, percent: int}>,
     *     top_pages: list<array{path: string, count: int}>,
     *     country_note: string|null
     * }
     */
    public function rollup(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $empty = [
            'enabled' => filter_var(config('traffic.report.nginx.enabled', false), FILTER_VALIDATE_BOOLEAN),
            'available' => false,
            'lines_scanned' => 0,
            'lines_matched' => 0,
            'countries' => [],
            'locales' => [],
            'top_pages' => [],
            'country_note' => null,
        ];

        if (! $empty['enabled']) {
            return $empty;
        }

        $logPath = trim((string) config('traffic.report.nginx.access_log_path', ''));
        if ($logPath === '' || ! is_readable($logPath)) {
            Log::warning('traffic nginx rollup: access log missing or unreadable', ['path' => $logPath]);

            return $empty;
        }

        $maxLines = max(1000, (int) config('traffic.report.nginx.max_lines', 250000));
        $periodStartUtc = $periodStart->utc();
        $periodEndUtc = $periodEnd->utc();

        $pageCounts = [];
        $localeCounts = [];
        $countryCounts = [];
        $linesScanned = 0;
        $linesMatched = 0;
        $geoResolved = 0;

        foreach ($this->resolveLogFiles($logPath) as $file) {
            foreach ($this->readLines($file) as $line) {
                if (++$linesScanned > $maxLines) {
                    break 2;
                }

                if (! preg_match(self::LOG_PATTERN, $line, $matches)) {
                    continue;
                }

                $timestamp = $this->parseLogTimestamp($matches['time'] ?? '');
                if ($timestamp === null) {
                    continue;
                }

                if ($timestamp->lt($periodStartUtc) || $timestamp->gte($periodEndUtc)) {
                    continue;
                }

                $method = strtoupper($matches['method'] ?? 'GET');
                if ($method !== 'GET') {
                    continue;
                }

                $status = (int) ($matches['status'] ?? 0);
                if ($status < 200 || $status >= 400) {
                    continue;
                }

                $ua = (string) ($matches['ua'] ?? '');
                if ($this->isBotUserAgent($ua)) {
                    continue;
                }

                $path = rawurldecode((string) ($matches['path'] ?? '/'));
                if ($this->shouldExcludePath($path)) {
                    continue;
                }

                $normalizedPath = $this->normalizePath($path);
                $pageCounts[$normalizedPath] = ($pageCounts[$normalizedPath] ?? 0) + 1;

                $locale = $this->inferLocale($normalizedPath);
                $localeCounts[$locale] = ($localeCounts[$locale] ?? 0) + 1;

                $countryKey = $this->resolveCountryKey((string) ($matches['ip'] ?? ''));
                if ($countryKey !== null) {
                    $countryCounts[$countryKey] = ($countryCounts[$countryKey] ?? 0) + 1;
                    $geoResolved++;
                }

                $linesMatched++;
            }
        }

        arsort($pageCounts);
        arsort($localeCounts);
        arsort($countryCounts);

        $topPagesLimit = max(1, (int) config('traffic.report.nginx.top_pages_limit', 5));
        $topCountriesLimit = max(1, (int) config('traffic.report.nginx.top_countries_limit', 5));
        $topLocalesLimit = max(1, (int) config('traffic.report.nginx.top_locales_limit', 4));

        $topPages = [];
        foreach (array_slice($pageCounts, 0, $topPagesLimit, true) as $path => $count) {
            $topPages[] = ['path' => $path, 'count' => (int) $count];
        }

        $localeTotal = array_sum($localeCounts);
        $locales = [];
        foreach (array_slice($localeCounts, 0, $topLocalesLimit, true) as $code => $count) {
            $percent = $localeTotal > 0 ? (int) round(((int) $count / $localeTotal) * 100) : 0;
            $locales[] = ['code' => (string) $code, 'count' => (int) $count, 'percent' => $percent];
        }

        $countries = [];
        foreach (array_slice($countryCounts, 0, $topCountriesLimit, true) as $key => $count) {
            [$code, $name] = explode('|', $key, 2);
            $countries[] = [
                'code' => $code,
                'name' => $name,
                'count' => (int) $count,
                'flag' => $this->countryFlag($code),
            ];
        }

        $countryNote = $countries !== [] && $geoResolved > 0
            ? 'Countries: estimate from nginx IP GeoIP'
            : null;

        return [
            'enabled' => true,
            'available' => $linesMatched > 0,
            'lines_scanned' => $linesScanned,
            'lines_matched' => $linesMatched,
            'countries' => $countries,
            'locales' => $locales,
            'top_pages' => $topPages,
            'country_note' => $countryNote,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveLogFiles(string $path): array
    {
        $files = [];
        $dir = dirname($path);
        $base = basename($path);

        if (is_readable($path)) {
            $files[] = $path;
        }

        for ($i = 1; $i <= 14; $i++) {
            $plain = $dir.'/'.$base.'.'.$i;
            if (is_readable($plain)) {
                $files[] = $plain;
            }

            $gz = $plain.'.gz';
            if (is_readable($gz)) {
                $files[] = $gz;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @return \Generator<int, string>
     */
    private function readLines(string $file): \Generator
    {
        if (str_ends_with($file, '.gz')) {
            $handle = @gzopen($file, 'rb');
        } else {
            $handle = @fopen($file, 'rb');
        }

        if ($handle === false) {
            return;
        }

        try {
            while (($line = str_ends_with($file, '.gz') ? gzgets($handle) : fgets($handle)) !== false) {
                $line = trim($line);
                if ($line !== '') {
                    yield $line;
                }
            }
        } finally {
            if (str_ends_with($file, '.gz')) {
                gzclose($handle);
            } else {
                fclose($handle);
            }
        }
    }

    private function parseLogTimestamp(string $value): ?CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('d/M/Y:H:i:s O', $value);
            if ($parsed === false) {
                return null;
            }

            return $parsed->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function isBotUserAgent(string $ua): bool
    {
        $ua = strtolower($ua);
        if ($ua === '' || $ua === '-') {
            return true;
        }

        if ($ua === 'node' || str_starts_with($ua, 'node/')) {
            return true;
        }

        foreach (self::BOT_UA_FRAGMENTS as $fragment) {
            if (str_contains($ua, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function shouldExcludePath(string $path): bool
    {
        $lower = strtolower($path);

        foreach (self::STATIC_EXTENSIONS as $extension) {
            if (str_ends_with($lower, $extension)) {
                return true;
            }
        }

        foreach ([
            '/apis/', '/client-api/', '/iexadmin/', '/storage/', '/build/', '/vendor/',
            '/favicon', '/robots.txt', '/sitemap', '/manifest', '/sw.js', '/livewire/',
        ] as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = preg_replace('#/+#', '/', $path) ?: '/';

        if (preg_match('#^/(en|ru|uk|ka)/order/\d+#', $path) === 1) {
            $path = preg_replace('#^/(en|ru|uk|ka)/order/\d+#', '/$1/order/…', $path) ?? $path;
        }

        if (preg_match('#^/(en|ru|uk|ka)/order/[0-9a-f-]{8,}#i', $path) === 1) {
            $path = preg_replace('#^/(en|ru|uk|ka)/order/[0-9a-f-]{8,}#i', '/$1/order/…', $path) ?? $path;
        }

        $path = preg_replace('#/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}#i', '/…', $path) ?? $path;
        $path = preg_replace('#/[A-Za-z0-9_-]{24,}#', '/…', $path) ?? $path;

        if (strlen($path) > 120) {
            $path = mb_substr($path, 0, 117, 'UTF-8').'…';
        }

        return $path === '' ? '/' : $path;
    }

    private function inferLocale(string $path): string
    {
        if (preg_match('#^/(en|ru|uk|ka)(/|$)#', $path, $matches) === 1) {
            return $matches[1];
        }

        return 'unknown';
    }

    private function resolveCountryKey(string $ip): ?string
    {
        if ($ip === '' || $ip === '-') {
            return null;
        }

        try {
            $location = $this->geoIp->locate($ip);
            $code = strtoupper(trim((string) ($location->countryIso ?? '')));
            $name = trim((string) ($location->countryName ?? ''));

            if ($code === '' && $name === '') {
                return null;
            }

            if ($code === '') {
                $code = '??';
            }

            if ($name === '') {
                $name = $code;
            }

            return $code.'|'.$name;
        } catch (Throwable) {
            return null;
        }
    }

    private function countryFlag(string $iso): string
    {
        $iso = strtoupper($iso);
        if (strlen($iso) !== 2 || $iso === '??') {
            return '🏳️';
        }

        return mb_chr(127397 + ord($iso[0]), 'UTF-8').mb_chr(127397 + ord($iso[1]), 'UTF-8');
    }
}
