<?php

declare(strict_types=1);

namespace App\Services\Rates\ProviderIntegrity;

/**
 * Persist last-known-good derived asset-leg quotes with TTL and quality metadata.
 */
final class DerivedLegLastKnownGoodStore
{
    public function __construct(
        private readonly ?string $path = null,
    ) {
    }

    private function resolvePath(): string
    {
        if ($this->path !== null) {
            return $this->path;
        }
        $rel = 'rates/derived-leg-lkg.json';
        if (function_exists('config')) {
            $rel = (string) config('rates_provider_integrity.lkg_path', $rel);
        }
        if (function_exists('storage_path')) {
            return storage_path('app/' . ltrim($rel, '/'));
        }

        return sys_get_temp_dir() . '/derived-leg-lkg.json';
    }

    /**
     * @return array<string, array<string,mixed>>
     */
    public function all(): array
    {
        $path = $this->resolvePath();
        if (!is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : [];
    }

    /**
     * @param array<string,mixed> $entry
     */
    public function put(string $symbol, array $entry): void
    {
        $symbol = strtoupper(trim($symbol));
        $all = $this->all();
        $all[$symbol] = [
            'LAST_GOOD_PRICE' => (string) ($entry['rate'] ?? ''),
            'LAST_GOOD_AT' => (string) ($entry['as_of'] ?? gmdate('c')),
            'SOURCE_SET' => $entry['distinct_providers'] ?? ($entry['source_set'] ?? []),
            'QUALITY_STATE' => (string) ($entry['quality_state'] ?? ProviderLegAggregator::QUALITY_HEALTHY),
            'source' => (string) ($entry['source'] ?? ''),
            'updated_at_unix' => time(),
        ];
        $this->write($all);
    }

    /**
     * @return array{rate:string,as_of:string,age_seconds:int,quality_state:string,source:string,source_set:list<string>}|null
     */
    public function getFresh(string $symbol, int $ttlSeconds): ?array
    {
        $symbol = strtoupper(trim($symbol));
        $all = $this->all();
        $row = $all[$symbol] ?? null;
        if (!is_array($row)) {
            return null;
        }
        $rate = (string) ($row['LAST_GOOD_PRICE'] ?? '');
        if ($rate === '' || !is_numeric($rate) || bccomp($rate, '0', 18) !== 1) {
            return null;
        }
        $asOf = (string) ($row['LAST_GOOD_AT'] ?? '');
        $unix = (int) ($row['updated_at_unix'] ?? 0);
        if ($unix <= 0 && $asOf !== '') {
            $unix = (int) strtotime($asOf);
        }
        $age = max(0, time() - $unix);
        if ($age > $ttlSeconds) {
            return null;
        }
        $sources = $row['SOURCE_SET'] ?? [];
        if (!is_array($sources)) {
            $sources = [];
        }

        return [
            'rate' => bcmul($rate, '1', 18),
            'as_of' => $asOf !== '' ? $asOf : gmdate('c', $unix),
            'age_seconds' => $age,
            'quality_state' => ProviderLegAggregator::QUALITY_DEGRADED_SINGLE,
            'source' => 'lkg:' . (string) ($row['source'] ?? $symbol),
            'source_set' => array_values(array_map('strval', $sources)),
        ];
    }

    /**
     * @param array<string, array<string,mixed>> $all
     */
    private function write(array $all): void
    {
        $path = $this->resolvePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents(
            $path,
            json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            LOCK_EX
        );
    }
}
