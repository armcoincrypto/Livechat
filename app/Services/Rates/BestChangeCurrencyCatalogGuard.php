<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Detects BestChange currency ID collisions between local codes.json and
 * the live BestChange currencies catalog (currencies.json).
 *
 * Example proven defect: local code 108 = PRUSD, live catalog 108 = CARDVND.
 */
final class BestChangeCurrencyCatalogGuard
{
    public function __construct(
        private readonly string $catalogPath,
        private readonly string $codesPath,
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
        );
    }

    /**
     * @return array{ok: bool, reason: string|null, catalog_name: string|null, code_name: string|null, code: string|null}
     */
    public function validateId(int $currencyId, ?string $expectedCode = null): array
    {
        $catalog = $this->loadCatalog();
        $codes = $this->loadCodes();

        $cat = $catalog[$currencyId] ?? null;
        $codeMeta = $codes[$currencyId] ?? null;

        if ($cat === null) {
            return [
                'ok' => false,
                'reason' => 'currency_id_missing_from_catalog',
                'catalog_name' => null,
                'code_name' => is_array($codeMeta) ? (string) ($codeMeta['name'] ?? '') : null,
                'code' => is_array($codeMeta) ? (string) ($codeMeta['code'] ?? '') : null,
            ];
        }

        $catalogName = (string) ($cat['name'] ?? '');
        $code = is_array($codeMeta) ? (string) ($codeMeta['code'] ?? '') : '';
        $codeName = is_array($codeMeta) ? (string) ($codeMeta['name'] ?? '') : '';

        if ($expectedCode !== null && $expectedCode !== '') {
            // Catalog entries look like "[CARDVND] - ..."
            if (!str_contains(strtoupper($catalogName), '[' . strtoupper($expectedCode) . ']')) {
                return [
                    'ok' => false,
                    'reason' => 'currency_mapping_mismatch',
                    'catalog_name' => $catalogName,
                    'code_name' => $codeName,
                    'code' => $code !== '' ? $code : $expectedCode,
                ];
            }
        }

        if ($code !== '' && !str_contains(strtoupper($catalogName), '[' . strtoupper($code) . ']')) {
            return [
                'ok' => false,
                'reason' => 'currency_mapping_mismatch',
                'catalog_name' => $catalogName,
                'code_name' => $codeName,
                'code' => $code,
            ];
        }

        return [
            'ok' => true,
            'reason' => null,
            'catalog_name' => $catalogName,
            'code_name' => $codeName,
            'code' => $code,
        ];
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private function loadCatalog(): array
    {
        if (!is_file($this->catalogPath)) {
            return [];
        }
        $raw = json_decode((string) file_get_contents($this->catalogPath), true);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }
            $out[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'name' => (string) ($row['name'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{id:string,code:string,name:string}>
     */
    private function loadCodes(): array
    {
        if (!is_file($this->codesPath)) {
            return [];
        }
        $raw = json_decode((string) file_get_contents($this->codesPath), true);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? $key);
            $out[$id] = [
                'id' => (string) $id,
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
            ];
        }

        return $out;
    }
}
