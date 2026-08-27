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
    /**
     * @param list<array{id:int|string,name?:string}>|null $catalogOverride
     * @param array<int|string, array<string,mixed>>|null $codesOverride
     */
    public function __construct(
        private readonly string $catalogPath,
        private readonly string $codesPath,
        private readonly ?array $catalogOverride = null,
        private readonly ?array $codesOverride = null,
        private readonly bool $useVersionedOverrides = true,
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
     * Build a guard from an already-warmed BestChange currency catalog
     * (API / CatalogRepository), not the daily on-disk currencies.json.
     *
     * @param list<array{id:int|string,name?:string}>|array<int, array{id?:int,name?:string}> $catalogRows
     */
    public static function fromCatalogRows(array $catalogRows, string $codesPath): self
    {
        $normalized = [];
        foreach ($catalogRows as $id => $row) {
            if (!is_array($row)) {
                continue;
            }
            $cid = (int) ($row['id'] ?? $id);
            if ($cid <= 0) {
                continue;
            }
            $normalized[] = [
                'id' => $cid,
                'name' => (string) ($row['name'] ?? ''),
                'code' => strtoupper(trim((string) ($row['code'] ?? ''))),
            ];
        }

        return new self(
            catalogPath: '',
            codesPath: $codesPath,
            catalogOverride: $normalized,
            codesOverride: null,
            useVersionedOverrides: false,
        );
    }

    /**
     * @return array{in: ?string, out: ?string}
     */
    public static function expectedCodesFromName(string $name): array
    {
        $out = null;
        if (preg_match('/->\s*\[([A-Za-z0-9]+)\]/', $name, $m) === 1) {
            $out = strtoupper($m[1]);
        }
        $in = null;
        if (preg_match('/^\s*\[([A-Za-z0-9]+)\]/', $name, $m) === 1) {
            $in = strtoupper($m[1]);
        }

        return ['in' => $in, 'out' => $out];
    }

    /**
     * Name-bracket codes win when present; otherwise use Exswaping designation_xml.
     *
     * @param array{in:?string,out:?string} $fromName
     * @return array{in:?string,out:?string}
     */
    public static function mergeExpectedCodes(array $fromName, ?string $fromXml, ?string $toXml): array
    {
        $in = $fromName['in'] ?? null;
        $out = $fromName['out'] ?? null;
        if ($in === null || $in === '') {
            $xml = strtoupper(trim((string) $fromXml));
            $in = $xml !== '' ? $xml : null;
        }
        if ($out === null || $out === '') {
            $xml = strtoupper(trim((string) $toXml));
            $out = $xml !== '' ? $xml : null;
        }

        return ['in' => $in, 'out' => $out];
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
            if (!$this->catalogMatchesCode($cat, $expectedCode)) {
                return [
                    'ok' => false,
                    'reason' => 'currency_mapping_mismatch',
                    'catalog_name' => $catalogName,
                    'code_name' => $codeName,
                    'code' => $code !== '' ? $code : $expectedCode,
                ];
            }
        }

        if ($code !== '' && !$this->catalogMatchesCode($cat, $code)) {
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
            'code' => $code !== '' ? $code : (string) ($cat['code'] ?? ''),
        ];
    }

    /**
     * Live BestChange API names are "Банковская карта THB" with a separate
     * `code` field (CARDTHB). On-disk currencies.json uses "[CARDTHB] - ...".
     * Match either form. Never use default_code (THB ≠ CARDTHB).
     *
     * @param array{id?:int,name?:string,code?:string} $cat
     */
    private function catalogMatchesCode(array $cat, string $expectedCode): bool
    {
        $expected = strtoupper(trim($expectedCode));
        if ($expected === '') {
            return false;
        }
        $catalogCode = strtoupper(trim((string) ($cat['code'] ?? '')));
        if ($catalogCode !== '' && $catalogCode === $expected) {
            return true;
        }
        $catalogName = strtoupper((string) ($cat['name'] ?? ''));

        return str_contains($catalogName, '[' . $expected . ']');
    }

    /**
     * @return array<int, array{id:int,name:string,code?:string}>
     */
    private function loadCatalog(): array
    {
        if ($this->catalogOverride !== null) {
            $out = [];
            foreach ($this->catalogOverride as $row) {
                if (!is_array($row) || !isset($row['id'])) {
                    continue;
                }
                $out[(int) $row['id']] = [
                    'id' => (int) $row['id'],
                    'name' => (string) ($row['name'] ?? ''),
                    'code' => strtoupper(trim((string) ($row['code'] ?? ''))),
                ];
            }

            return $out;
        }

        if ($this->catalogPath === '' || !is_file($this->catalogPath)) {
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
                'code' => strtoupper(trim((string) ($row['code'] ?? ''))),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{id:string,code:string,name:string}>
     */
    private function loadCodes(): array
    {
        if ($this->codesOverride !== null) {
            $out = [];
            foreach ($this->codesOverride as $key => $row) {
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

        // Prefer versioned overrides so drifted storage codes cannot silently win.
        if ($this->useVersionedOverrides && function_exists('base_path')) {
            try {
                $overrides = base_path('resources/rates/bestchange-codes.overrides.json');
            } catch (\Throwable) {
                $overrides = '';
            }
            if ($overrides !== '' && is_file($overrides)) {
                return (new BestChangeMappingRegistry($this->codesPath, $overrides))->loadEffectiveCodes();
            }
        }

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
