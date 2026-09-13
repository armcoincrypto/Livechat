<?php

declare(strict_types=1);

namespace App\Services\Seo;

/**
 * SEO Phase D4 — nginx map of quoteable exchange pairs.
 *
 * Consumed by nginx `map $exchange_pair_key $exchange_pair_valid`.
 * Keys are FROM:TO using currencies.designation_xml (same as public /exchange/ URLs).
 */
final class ValidExchangePairsNginxMap
{
    /**
     * @param list<array{0: string, 1: string}> $pairs
     */
    public static function render(array $pairs): string
    {
        $keys = [];
        foreach ($pairs as $pair) {
            $from = strtoupper(trim((string) ($pair[0] ?? '')));
            $to = strtoupper(trim((string) ($pair[1] ?? '')));
            if ($from === '' || $to === '' || str_contains($from, ':') || str_contains($to, ':')) {
                continue;
            }
            $keys[$from . ':' . $to] = true;
        }

        ksort($keys, SORT_STRING);

        $lines = [
            '# SEO Phase D4 — active exchange pairs',
            '# generated-by: seo:generate-valid-exchange-pairs-map',
            '# keys: ' . count($keys),
            '',
        ];

        foreach (array_keys($keys) as $key) {
            $lines[] = '"' . $key . '" 1;';
        }

        return implode("\n", $lines) . "\n";
    }
}
