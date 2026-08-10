<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Lightweight resume gate: refuse to go online on obviously poisoned/broken feeds.
 */
final class RateFeedResumeGate
{
    /**
     * @return array{ok:bool,reason:?string,details:array<string,mixed>}
     */
    public static function evaluate(string $xmlPath): array
    {
        if (!is_file($xmlPath) || filesize($xmlPath) < 50) {
            return ['ok' => false, 'reason' => 'missing_xml', 'details' => []];
        }

        $xml = @simplexml_load_file($xmlPath);
        if ($xml === false) {
            return ['ok' => false, 'reason' => 'xml_parse', 'details' => []];
        }

        $items = 0;
        $invalidRate = 0;
        $missingBounds = 0;
        $pairs = [];
        $zelleOk = null;
        $gramUsdt = null;

        foreach ($xml->item as $it) {
            $items++;
            $from = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $it->from) ?? '');
            $to = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $it->to) ?? '');
            $pairs[$from.'->'.$to] = true;

            $inn = (float) (string) $it->in;
            $out = (float) (string) $it->out;
            if ($inn <= 0.0 || $out <= 0.0) {
                $invalidRate++;
            }
            if ((string) ($it->minamount ?? '') === '' || (string) ($it->maxamount ?? '') === ''
                || (string) ($it->frommin ?? '') === '' || (string) ($it->frommax ?? '') === '') {
                $missingBounds++;
            }

            if (($from === 'ZELLEUSD' || $from === 'ZELLE') && str_contains($to, 'SBER')) {
                $rate = $inn > 0 ? $out / $inn : 0.0;
                $zelleOk = ($rate >= 50.0 && $rate <= 150.0);
            }
            if (str_contains($from, 'USDT') && ($to === 'GRAM' || $to === 'TONGRAM')) {
                $rate = $inn > 0 ? $out / $inn : 0.0;
                // GRAM ~ TON: USDT->GRAM should not be absurd.
                if ($rate > 50000.0 || $rate < 0.01) {
                    $gramUsdt = false;
                } elseif ($gramUsdt === null) {
                    $gramUsdt = true;
                }
            }
            if (($from === 'GRAM' || $from === 'TONGRAM') && str_contains($to, 'USDT')) {
                $rate = $inn > 0 ? $out / $inn : 0.0;
                if ($rate > 500.0 || $rate < 0.05) {
                    $gramUsdt = false;
                } elseif ($gramUsdt === null) {
                    $gramUsdt = true;
                }
            }
        }

        if ($items < 1) {
            return ['ok' => false, 'reason' => 'zero_items', 'details' => ['items' => 0]];
        }
        if ($invalidRate > 0) {
            return ['ok' => false, 'reason' => 'invalid_rate', 'details' => ['invalid_rate' => $invalidRate]];
        }
        if ($missingBounds > 0) {
            return ['ok' => false, 'reason' => 'missing_bounds', 'details' => ['missing_bounds' => $missingBounds]];
        }
        if ($zelleOk === false) {
            return ['ok' => false, 'reason' => 'zelle_drift', 'details' => []];
        }
        if ($gramUsdt === false) {
            return ['ok' => false, 'reason' => 'gram_poison', 'details' => []];
        }

        $requiredAny = [
            ['USDTTRC20->GRAM', 'USDTERC20->GRAM', 'USDT->GRAM'],
            ['BTC->GRAM'],
            ['ZELLEUSD->SBERRUB', 'ZELLE->SBERRUB'],
        ];
        foreach ($requiredAny as $group) {
            $found = false;
            foreach ($group as $p) {
                if (isset($pairs[$p])) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return ['ok' => false, 'reason' => 'missing_required_pair:'.$group[0], 'details' => []];
            }
        }

        return [
            'ok' => true,
            'reason' => null,
            'details' => [
                'items' => $items,
                'zelle_checked' => $zelleOk !== null,
                'gram_checked' => $gramUsdt !== null,
            ],
        ];
    }
}
