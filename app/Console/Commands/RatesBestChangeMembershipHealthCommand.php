<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Rates\BestChangeMappingVerifier;
use App\Services\Rates\CanonicalDirectionEligibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Fast BestChange membership drift gate.
 *
 * Fails when a public+orderable+owner-export-enabled+BC-VERIFIED direction
 * is missing from the live currencies.xml identity set, or when XML contains
 * a pair that is not public/orderable.
 */
final class RatesBestChangeMembershipHealthCommand extends Command
{
    protected $signature = 'rates:bestchange-membership-health
        {--xml= : Path to currencies.xml (default public/static/exports/currencies.xml)}
        {--write= : Optional path to write bestchange-membership-health.json}
        {--fail-on-critical : Exit 1 when supported_but_missing_xml or xml_but_not_public > 0}';

    protected $description = 'Compute BestChange membership health (supported-but-missing / XML-but-not-public)';

    public function handle(): int
    {
        $eligibility = CanonicalDirectionEligibility::make();
        $verifier = BestChangeMappingVerifier::fromStorageApp();
        $xmlPath = (string) ($this->option('xml') ?: public_path('static/exports/currencies.xml'));
        if (!is_file($xmlPath)) {
            $this->error("XML not found: {$xmlPath}");

            return self::FAILURE;
        }

        $xml = (string) file_get_contents($xmlPath);
        $xmlPairs = $this->parseXmlPairKeys($xml);
        $xmlRaw = substr_count($xml, '<item>');

        $verifyCache = [];
        $verify = function (string $code) use ($verifier, &$verifyCache): array {
            $code = strtoupper(trim($code));
            if ($code === 'TON') {
                $code = 'GRAM';
            }
            if (isset($verifyCache[$code])) {
                return $verifyCache[$code];
            }

            return $verifyCache[$code] = $verifier->verifyCode($code);
        };

        $public = 0;
        $exportEligible = 0;
        $supportedButMissing = 0;
        $xmlButNotPublic = 0;
        $taxonomyUnsupported = 0;
        $ownerDisabled = 0;
        $quarantined = 0;
        $mappingMissing = 0;
        $missingExamples = [];
        $staleExamples = [];

        $dirs = DirectionExchange::query()
            ->with(['currency1', 'currency2'])
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->orderBy('id')
            ->get();

        $exportedDirectionPresence = 0;

        foreach ($dirs as $dir) {
            $from = strtoupper((string) ($dir->currency1?->designation_xml ?? ''));
            $to = strtoupper((string) ($dir->currency2?->designation_xml ?? ''));
            $currenciesVisible = ((int) ($dir->currency1?->status ?? -1) === 0)
                && ((int) ($dir->currency2?->status ?? -1) === 0);
            if (!$currenciesVisible || $from === '' || $to === '') {
                continue;
            }

            $eval = $eligibility->evaluate($dir, CanonicalDirectionEligibility::CTX_BESTCHANGE_EXPORT);
            $order = $eligibility->evaluate($dir, CanonicalDirectionEligibility::CTX_ORDER);
            $quote = $eligibility->evaluate($dir, CanonicalDirectionEligibility::CTX_QUOTE);
            $orderable = !empty($order['eligible']);
            $quoteable = !empty($quote['eligible']);

            $isPublicOrderable = $quoteable && $orderable;
            if ($isPublicOrderable) {
                $public++;
            }

            $fromV = $verify($from);
            $toV = $verify($to);
            $fromOk = strtoupper((string) ($fromV['status'] ?? '')) === 'VERIFIED';
            $toOk = strtoupper((string) ($toV['status'] ?? '')) === 'VERIFIED';
            $taxSupported = $fromOk && $toOk;
            if ($isPublicOrderable && (!$fromOk || !$toOk)) {
                $taxonomyUnsupported++;
                $mappingMissing++;
            }

            $allowExport = (int) ($dir->allow_export ?? 0);
            if ($allowExport === 2) {
                $ownerDisabled++;
                $quarantined++;
            }

            $exportOk = !empty($eval['eligible']);
            if ($exportOk) {
                $exportEligible++;
            }

            $fromPub = $from === 'TON' ? 'GRAM' : $from;
            $toPub = $to === 'TON' ? 'GRAM' : $to;
            $pairKey = $fromPub . '->' . $toPub;
            $inXml = $this->xmlContainsPair($xmlPairs, $pairKey);
            if ($inXml && $isPublicOrderable) {
                $exportedDirectionPresence++;
            }

            $ownerEnabled = $allowExport !== 2;
            if ($isPublicOrderable && $taxSupported && $ownerEnabled && $exportOk && !$inXml) {
                $supportedButMissing++;
                if (count($missingExamples) < 20) {
                    $missingExamples[] = [
                        'direction_id' => (int) $dir->id,
                        'pair' => $pairKey,
                    ];
                }
            }
        }

        // Inverse: XML pair with zero public+orderable claimants.
        $claimants = [];
        foreach ($dirs as $dir) {
            $from = strtoupper((string) ($dir->currency1?->designation_xml ?? ''));
            $to = strtoupper((string) ($dir->currency2?->designation_xml ?? ''));
            if ($from === '' || $to === '') {
                continue;
            }
            $fromPub = $from === 'TON' ? 'GRAM' : $from;
            $toPub = $to === 'TON' ? 'GRAM' : $to;
            $pairKey = $fromPub . '->' . $toPub;
            if (!$this->xmlContainsPair($xmlPairs, $pairKey)) {
                continue;
            }
            $currenciesVisible = ((int) ($dir->currency1?->status ?? -1) === 0)
                && ((int) ($dir->currency2?->status ?? -1) === 0);
            if (!$currenciesVisible) {
                continue;
            }
            $order = $eligibility->evaluate($dir, CanonicalDirectionEligibility::CTX_ORDER);
            $quote = $eligibility->evaluate($dir, CanonicalDirectionEligibility::CTX_QUOTE);
            if (!empty($order['eligible']) && !empty($quote['eligible'])) {
                $claimants[$pairKey] = true;
            }
        }
        foreach (array_keys($xmlPairs) as $pairKey) {
            $base = explode('@', $pairKey, 2)[0];
            if (!isset($claimants[$base])) {
                $xmlButNotPublic++;
                if (count($staleExamples) < 20) {
                    $staleExamples[] = $pairKey;
                }
            }
        }

        $backend = '';
        $git = @shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse HEAD 2>/dev/null');
        if (is_string($git)) {
            $backend = trim($git);
        }

        $health = [
            'captured_at' => now()->utc()->toIso8601String(),
            'backend' => $backend,
            'public_count' => $public,
            'export_eligible_count' => $exportEligible,
            'xml_count' => $xmlRaw,
            'xml_unique_pair_keys' => count($xmlPairs),
            'supported_but_missing_xml' => $supportedButMissing,
            'xml_but_not_public' => $xmlButNotPublic,
            'mapping_missing' => $mappingMissing,
            'taxonomy_unsupported' => $taxonomyUnsupported,
            'owner_disabled' => $ownerDisabled,
            'quarantined' => $quarantined,
            'exported_direction_presence' => $exportedDirectionPresence,
            'missing_examples' => $missingExamples,
            'stale_examples' => $staleExamples,
            'critical_ok' => $supportedButMissing === 0 && $xmlButNotPublic === 0,
        ];

        $write = (string) ($this->option('write') ?: '');
        if ($write !== '') {
            $dir = dirname($write);
            if (!is_dir($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            File::put($write, json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            $this->info("Wrote {$write}");
        }

        $this->line(json_encode($health, JSON_UNESCAPED_SLASHES));

        if ($this->option('fail-on-critical') && !$health['critical_ok']) {
            $this->error('BestChange membership critical drift detected');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function parseXmlPairKeys(string $xml): array
    {
        $pairs = [];
        if (!preg_match_all('/<item>(.*?)<\/item>/s', $xml, $items)) {
            return $pairs;
        }
        foreach ($items[1] as $item) {
            if (!preg_match('/<from>(.*?)<\/from>/', $item, $from)) {
                continue;
            }
            if (!preg_match('/<to>(.*?)<\/to>/', $item, $to)) {
                continue;
            }
            $key = strtoupper($from[1]).'->'.strtoupper($to[1]);
            if (preg_match('/<city>(.*?)<\/city>/', $item, $city)) {
                $key .= '@'.strtoupper($city[1]);
            }
            $pairs[$key] = true;
        }

        return $pairs;
    }

    /**
     * @param  array<string, true>  $xmlPairs
     */
    private function xmlContainsPair(array $xmlPairs, string $pairKey): bool
    {
        if (isset($xmlPairs[$pairKey])) {
            return true;
        }
        $prefix = $pairKey.'@';
        foreach (array_keys($xmlPairs) as $key) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
