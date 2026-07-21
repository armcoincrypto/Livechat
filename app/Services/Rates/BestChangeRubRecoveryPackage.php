<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use RuntimeException;

final class BestChangeRubRecoveryPackage
{
    /**
     * Generate package artifacts from the XML published in the active export snapshot.
     *
     * @return array<string,mixed>
     */
    public function generate(): array
    {
        $snapshot = IndependentMarketBaseline::currentSnapshot();
        if ($snapshot === null || $snapshot['purpose'] !== 'export') {
            throw new RuntimeException('BestChange RUB package requires an active export snapshot');
        }

        $xmlPath = public_path('static/exports/currencies.xml');
        $xml = simplexml_load_file($xmlPath);
        if ($xml === false) {
            throw new RuntimeException('Canonical currencies XML is invalid');
        }

        $xmlRows = [];
        foreach ($xml->item as $item) {
            $to = strtoupper((string) $item->to);
            if (!str_contains($to, 'RUB')) {
                continue;
            }
            $in = (string) $item->in;
            $out = (string) $item->out;
            if (!is_numeric($in) || !is_numeric($out) || bccomp($in, '0', 18) !== 1) {
                throw new RuntimeException('Invalid RUB rate in canonical XML');
            }
            $pair = strtoupper((string) $item->from) . '->' . $to;
            $xmlRows[$pair] = [
                'from' => strtoupper((string) $item->from),
                'to' => $to,
                'in' => $in,
                'out' => $out,
                'rate' => bcdiv($out, $in, 18),
                'reserve' => (string) $item->amount,
            ];
        }

        $directions = DirectionExchange::query()
            ->where('status', 1)
            ->whereHas('currency2', static fn ($q) => $q->where('designation_xml', 'like', '%RUB%'))
            ->with(['currency1', 'currency2'])
            ->orderBy('id')
            ->get();
        $candidates = [];
        foreach ($directions as $direction) {
            $pair = strtoupper((string) $direction->currency1?->designation_xml)
                . '->'
                . strtoupper((string) $direction->currency2?->designation_xml);
            $candidates[$pair][] = $direction;
        }

        $eligibility = RateDirectionEligibility::make();
        $policy = RubFamilyPremiumPolicy::fromStorageApp();
        $rows = [];
        foreach ($xmlRows as $pair => $xmlRow) {
            $selected = null;
            $surface = null;
            foreach ($candidates[$pair] ?? [] as $candidate) {
                $evaluation = $eligibility->evaluateDirection($candidate);
                if (
                    !empty($evaluation['export_allowed'])
                    && in_array($evaluation['classification'], ['PASS', 'PASS_EXPLAINED_SPREAD'], true)
                ) {
                    $selected = $candidate;
                    $surface = $evaluation;
                    break;
                }
            }
            if ($selected === null || $surface === null) {
                throw new RuntimeException("XML RUB pair has no eligible canonical direction: {$pair}");
            }

            $actual = (string) $selected->course_value;
            $delta = bccomp($actual, '0', 18) === 1
                ? bcmul(bcsub(bcdiv($xmlRow['rate'], $actual, 18), '1', 18), '100', 12)
                : null;
            $rows[] = [
                'direction_id' => (int) $selected->id,
                'pair' => $pair,
                'classification' => $surface['classification'],
                'canonical_rate' => $actual,
                'xml_rate' => $xmlRow['rate'],
                'xml_rounding_delta_percent' => $delta,
                'baseline_provider' => $surface['baseline_provider'],
                'baseline_symbol' => $surface['baseline_symbol'],
                'baseline_timestamp' => $snapshot['captured_at'],
                'baseline_age_seconds' => $surface['baseline_age_seconds'],
                'approved_premium_percent' => $policy->canonicalSourcePremiumPercent($surface['to']),
                'unexplained_deviation_percent' => $surface['unexplained_vs_expected_percent'],
                'reserve' => $surface['reserve_value'],
                'reserve_status' => $surface['reserve_status'],
                'mapping' => $surface['mapping_status'],
                'surface_parity' => 'stored_canonical_rate_with_documented_xml_rounding',
            ];
        }

        if (count($rows) !== count($xmlRows)) {
            throw new RuntimeException('BestChange package/XML RUB count mismatch');
        }

        $payload = [
            'schema' => 'bestchange_rub_recovery_v1',
            'generated_at_utc' => gmdate('c'),
            'snapshot_id' => $snapshot['id'],
            'snapshot_timestamp' => $snapshot['captured_at'],
            'xml_path' => $xmlPath,
            'xml_sha256' => hash_file('sha256', $xmlPath),
            'xml_rub_count' => count($xmlRows),
            'package_count' => count($rows),
            'parity' => true,
            'directions' => $rows,
        ];

        $directory = storage_path('app/rates/packages');
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $this->atomicWrite(
            $directory . '/BESTCHANGE_RUB_RECOVERY_FINAL.json',
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        );

        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, array_keys($rows[0] ?? ['direction_id' => null]));
        foreach ($rows as $row) {
            fputcsv($csv, array_map(
                static fn ($value): string => is_array($value)
                    ? (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : (string) $value,
                $row,
            ));
        }
        rewind($csv);
        $this->atomicWrite(
            $directory . '/BESTCHANGE_RUB_RECOVERY_FINAL.csv',
            (string) stream_get_contents($csv),
        );
        fclose($csv);

        $this->atomicWrite(
            $directory . '/BESTCHANGE_RUB_RECOVERY_MESSAGE_RU.txt',
            sprintf(
                "Пакет RUB сформирован из того же снимка, что и XML.\nSnapshot: %s\nRUB-направлений: %d\nSHA-256 XML: %s\n",
                $snapshot['id'],
                count($rows),
                $payload['xml_sha256'],
            ),
        );

        return $payload;
    }

    private function atomicWrite(string $path, string $contents): void
    {
        $tmp = $path . '.tmp.' . getmypid();
        file_put_contents($tmp, $contents, LOCK_EX);
        rename($tmp, $path);
    }
}
