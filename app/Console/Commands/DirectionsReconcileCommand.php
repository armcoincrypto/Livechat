<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\DerivedMarketBaselineAuthority;
use App\Services\Rates\RateDirectionEligibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconcile configured / enabled / quotable / frontend / XML inventories.
 */
final class DirectionsReconcileCommand extends Command
{
    protected $signature = 'directions:reconcile {--json : JSON output}';

    protected $description = 'Explain every enabled-direction exclusion across quotable/frontend/XML surfaces.';

    public function handle(): int
    {
        $xml = $this->loadXmlPairs();
        $elig = RateDirectionEligibility::make();
        $rows = DB::table('direction_exchange as d')
            ->join('currencies as c1', 'c1.id', '=', 'd.id_currency1')
            ->join('currencies as c2', 'c2.id', '=', 'd.id_currency2')
            ->select([
                'd.id', 'd.status', 'd.deleted_at', 'd.allow_export', 'd.course_value', 'd.is_error_rate',
                'c1.designation_xml as f', 'c1.status as c1s', 'c1.visible_give',
                'c2.designation_xml as t', 'c2.status as c2s', 'c2.visible_receiving',
            ])
            ->orderBy('d.id')
            ->get();

        $out = [];
        $unexplained = 0;
        foreach ($rows as $r) {
            $adminEnabled = ((int) $r->status === 1) && empty($r->deleted_at);
            $pair = strtoupper((string) $r->f) . '->' . strtoupper((string) $r->t);
            $course = (float) $r->course_value;
            $quoteOk = false;
            $reasons = [];
            if ($adminEnabled) {
                try {
                    $dir = \App\Models\DirectionExchange::with(['currency1', 'currency2'])->find((int) $r->id);
                    if ($dir) {
                        $e = $elig->evaluateDirection($dir);
                        $quoteOk = !empty($e['quote_allowed']) || !empty($e['eligible_for_quote']);
                        if (!$quoteOk) {
                            $reasons = array_values((array) ($e['blocking_reasons'] ?? $e['reasons'] ?? []));
                        }
                    }
                } catch (\Throwable $ex) {
                    $reasons[] = 'elig_error';
                }
            }

            $frontendVisible = $adminEnabled
                && $quoteOk
                && (int) $r->c1s === 0
                && (int) $r->c2s === 0
                && (int) $r->visible_give === 1
                && (int) $r->visible_receiving === 1
                && (int) $r->is_error_rate === 0
                && $course > 0;

            $xmlVisible = isset($xml[$pair]);
            $exclusion = null;
            if ($adminEnabled && !$frontendVisible) {
                if ((int) $r->c1s !== 0) {
                    $exclusion = 'SOURCE_CURRENCY_DISABLED';
                } elseif ((int) $r->c2s !== 0) {
                    $exclusion = 'DESTINATION_CURRENCY_DISABLED';
                } elseif ((int) $r->visible_give !== 1 || (int) $r->visible_receiving !== 1) {
                    $exclusion = 'CURRENCY_VISIBILITY_POLICY';
                } elseif ($course <= 0 || (int) $r->is_error_rate === 1) {
                    $exclusion = 'RATE_UNAVAILABLE';
                } elseif (!$quoteOk) {
                    $exclusion = 'ELIGIBILITY_BLOCK:' . implode(',', $reasons);
                } else {
                    $exclusion = 'UNKNOWN';
                    $unexplained++;
                }
            }
            if ($adminEnabled && $frontendVisible && !$xmlVisible) {
                if ((int) $r->allow_export === 2) {
                    $exclusion = ($exclusion ? $exclusion . '|' : '') . 'EXPORT_HARD_DISABLED';
                } elseif ((int) $r->allow_export === 1) {
                    $exclusion = ($exclusion ? $exclusion . '|' : '') . 'EXPORT_TIME_WINDOW';
                } else {
                    $exclusion = ($exclusion ? $exclusion . '|' : '') . 'EXPORT_FILTER_OR_CANONICAL';
                }
            }

            if (!$adminEnabled) {
                continue; // reconciliation focuses on enabled inventory truth
            }

            $out[] = [
                'direction_id' => (int) $r->id,
                'source_code' => strtoupper((string) $r->f),
                'destination_code' => strtoupper((string) $r->t),
                'admin_enabled' => true,
                'quotable' => $quoteOk,
                'frontend_visible' => $frontendVisible,
                'xml_visible' => $xmlVisible,
                'exclusion_reason' => $exclusion,
            ];
        }

        $payload = [
            'generated_at' => gmdate('c'),
            'configured_total' => count($rows),
            'admin_enabled' => count($out),
            'quotable' => count(array_filter($out, static fn ($x) => !empty($x['quotable']))),
            'frontend_visible' => count(array_filter($out, static fn ($x) => !empty($x['frontend_visible']))),
            'xml_visible_among_enabled' => count(array_filter($out, static fn ($x) => !empty($x['xml_visible']))),
            'unexplained_enabled_exclusions' => $unexplained,
            'directions' => $out,
        ];

        $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $unexplained === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string,bool> */
    private function loadXmlPairs(): array
    {
        $path = public_path('static/exports/currencies.xml');
        $pairs = [];
        if (!is_file($path)) {
            return $pairs;
        }
        $t = (string) file_get_contents($path);
        if (preg_match_all('/<item>(.*?)<\/item>/is', $t, $items)) {
            foreach ($items[1] as $it) {
                if (preg_match('/<from>([^<]+)<\/from>/i', $it, $f) && preg_match('/<to>([^<]+)<\/to>/i', $it, $to)) {
                    $pairs[strtoupper($f[1]) . '->' . strtoupper($to[1])] = true;
                }
            }
        }

        return $pairs;
    }
}
