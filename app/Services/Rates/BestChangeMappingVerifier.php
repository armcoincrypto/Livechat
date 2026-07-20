<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Build a versioned local↔BestChange mapping verification table.
 * Only VERIFIED mappings may be used for automatic restore/export updates.
 */
final class BestChangeMappingVerifier
{
    public function __construct(
        private readonly string $catalogPath,
        private readonly string $codesPath,
        private readonly ?BestChangeCurrencyCatalogGuard $catalogGuard = null,
    ) {
    }

    public static function fromStorageApp(?string $storageApp = null): self
    {
        $base = $storageApp ?? (function_exists('storage_path')
            ? storage_path('app')
            : '/var/www/app_exswapin_usr/data/www/app.exswaping.com/storage/app');

        return new self(
            catalogPath: $base . '/bestchange/currencies.json',
            codesPath: $base . '/bestchange-codes.json',
            catalogGuard: BestChangeCurrencyCatalogGuard::fromStorageApp($base),
        );
    }

    /**
     * @param list<string> $localCodes
     * @return list<array<string,mixed>>
     */
    public function verifyLocalCodes(array $localCodes): array
    {
        $rows = [];
        $now = gmdate('c');
        foreach ($localCodes as $code) {
            $code = strtoupper(trim($code));
            if ($code === '') {
                continue;
            }
            $rows[] = $this->verifyCode($code, $now);
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    public function verifyCode(string $localCode, ?string $verifiedAt = null): array
    {
        $localCode = strtoupper(trim($localCode));
        $verifiedAt ??= gmdate('c');

        $matches = $this->findCatalogEntriesByCode($localCode);
        if ($matches === []) {
            $absent = $this->mappingRegistry()->absentReason($localCode);
            if ($absent !== null) {
                $status = 'ABSENT';
                $note = $absent['reason'];
            } elseif (in_array($localCode, ['PRUSD', 'PREUR', 'PRRUB', 'TON'], true)) {
                $status = 'DRIFTED';
                $note = 'no_exact_bracket_code_in_live_catalog';
            } else {
                $status = 'AMBIGUOUS';
                $note = 'no_exact_bracket_code_in_live_catalog';
            }

            return [
                'local_currency_id' => null,
                'local_code' => $localCode,
                'bestchange_currency_id' => null,
                'bestchange_code' => null,
                'bestchange_name' => null,
                'verified_at' => $verifiedAt,
                'verification_source' => 'live_currencies.json',
                'status' => $status,
                'note' => $note,
                'export_allowed' => false,
            ];
        }

        if (count($matches) > 1) {
            return [
                'local_currency_id' => null,
                'local_code' => $localCode,
                'bestchange_currency_id' => null,
                'bestchange_code' => $localCode,
                'bestchange_name' => array_map(static fn ($m) => $m['name'], $matches),
                'verified_at' => $verifiedAt,
                'verification_source' => 'live_currencies.json',
                'status' => 'AMBIGUOUS',
                'note' => 'multiple_catalog_entries',
                'candidates' => $matches,
            ];
        }

        $m = $matches[0];
        $id = (int) $m['id'];
        $localCodesMeta = $this->localCodeForId($id);

        $note = null;
        if ($localCodesMeta !== null && $localCodesMeta !== $localCode) {
            $note = 'local_codes_json_maps_id_to_' . $localCodesMeta;
        }

        $status = 'VERIFIED';
        if ($note !== null && str_starts_with($note, 'local_codes_json_maps_id_to_')) {
            // Effective registry overrides should already align; residual drift stays non-exportable.
            $effective = $this->mappingRegistry()->loadEffectiveCodes()[$id]['code'] ?? null;
            if ($effective !== null && strtoupper((string) $effective) === $localCode) {
                $note = 'aligned_via_mapping_registry_override';
            } else {
                $status = 'DRIFTED';
            }
        }

        return [
            'local_currency_id' => $id,
            'local_code' => $localCode,
            'bestchange_currency_id' => $id,
            'bestchange_code' => $localCode,
            'bestchange_name' => $m['name'],
            'verified_at' => $verifiedAt,
            'verification_source' => 'live_currencies.json',
            'status' => $status,
            'note' => $note,
            'local_codes_json_code' => $localCodesMeta,
            'export_allowed' => $status === 'VERIFIED',
        ];
    }

    public function catalogGuard(): BestChangeCurrencyCatalogGuard
    {
        return $this->catalogGuard ?? new BestChangeCurrencyCatalogGuard($this->catalogPath, $this->codesPath);
    }

    private function mappingRegistry(): BestChangeMappingRegistry
    {
        $storageApp = dirname($this->codesPath);
        // Prefer .../storage/app → app base via dirname x3 when layout is standard.
        $baseGuess = dirname($this->codesPath, 3);
        $overrides = $baseGuess . '/resources/rates/bestchange-codes.overrides.json';
        if (!is_file($overrides) && function_exists('base_path')) {
            $overrides = base_path('resources/rates/bestchange-codes.overrides.json');
            $baseGuess = function_exists('base_path') ? base_path() : $baseGuess;
        }
        if (!is_file($overrides)) {
            // Unit-test / shallow layouts: allow overrides beside codes file.
            $sibling = $storageApp . '/bestchange-codes.overrides.json';
            if (is_file($sibling)) {
                return new BestChangeMappingRegistry($this->codesPath, $sibling);
            }
        }

        return new BestChangeMappingRegistry($this->codesPath, $overrides);
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    private function findCatalogEntriesByCode(string $code): array
    {
        $code = strtoupper(trim($code));
        if ($code === '' || !is_file($this->catalogPath)) {
            return [];
        }
        $raw = json_decode((string) file_get_contents($this->catalogPath), true);
        if (!is_array($raw)) {
            return [];
        }
        $needle = '[' . $code . ']';
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }
            $name = (string) ($row['name'] ?? '');
            if (str_contains(strtoupper($name), $needle)) {
                $out[] = ['id' => (int) $row['id'], 'name' => $name];
            }
        }

        return $out;
    }

    private function localCodeForId(int $currencyId): ?string
    {
        if (!is_file($this->codesPath)) {
            return null;
        }
        $raw = json_decode((string) file_get_contents($this->codesPath), true);
        if (!is_array($raw)) {
            return null;
        }
        foreach ($raw as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? $key);
            if ($id !== $currencyId) {
                continue;
            }
            $code = trim((string) ($row['code'] ?? ''));

            return $code !== '' ? strtoupper($code) : null;
        }

        return null;
    }
}
