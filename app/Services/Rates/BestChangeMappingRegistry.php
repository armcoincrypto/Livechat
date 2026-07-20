<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Versioned BestChange ID↔code registry with fail-closed verified corrections.
 *
 * Local storage/app/bestchange-codes.json may drift from the live catalog.
 * Overrides under resources/rates/bestchange-codes.overrides.json win for
 * known collisions (108≠PRUSD, 209≠TON, etc.).
 */
final class BestChangeMappingRegistry
{
    public function __construct(
        private readonly string $codesPath,
        private readonly string $overridesPath,
    ) {
    }

    public static function fromStorageApp(?string $storageApp = null, ?string $basePath = null): self
    {
        $storage = $storageApp ?? (function_exists('storage_path')
            ? storage_path('app')
            : '/var/www/app_exswapin_usr/data/www/app.exswaping.com/storage/app');
        $base = $basePath ?? (function_exists('base_path')
            ? base_path()
            : dirname(__DIR__, 3));

        return new self(
            codesPath: $storage . '/bestchange-codes.json',
            overridesPath: $base . '/resources/rates/bestchange-codes.overrides.json',
        );
    }

    /**
     * @return array<int, array{id:string,code:string,name:string}>
     */
    public function loadEffectiveCodes(): array
    {
        $codes = $this->loadJsonObject($this->codesPath);
        $overrides = $this->loadJsonObject($this->overridesPath);

        $idCorrections = is_array($overrides['id_corrections'] ?? null)
            ? $overrides['id_corrections']
            : [];

        foreach ($idCorrections as $idKey => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $id = (string) ($meta['id'] ?? $idKey);
            $code = strtoupper(trim((string) ($meta['code'] ?? '')));
            if ($id === '' || $code === '') {
                continue;
            }
            $codes[$id] = [
                'id' => $id,
                'code' => $code,
                'name' => (string) ($meta['name'] ?? $code),
            ];
        }

        $out = [];
        foreach ($codes as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? $key);
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($id <= 0 || $code === '') {
                continue;
            }
            $out[$id] = [
                'id' => (string) $id,
                'code' => $code,
                'name' => (string) ($row['name'] ?? $code),
            ];
        }

        return $out;
    }

    /**
     * @return array{code:string,reason:string}|null
     */
    public function absentReason(string $localCode): ?array
    {
        $overrides = $this->loadJsonObject($this->overridesPath);
        $absent = is_array($overrides['absent_local_codes'] ?? null)
            ? $overrides['absent_local_codes']
            : [];
        $code = strtoupper(trim($localCode));
        if ($code === '' || !isset($absent[$code])) {
            return null;
        }

        return ['code' => $code, 'reason' => (string) $absent[$code]];
    }

    public function exportAllowedForStatus(string $status): bool
    {
        return strtoupper($status) === 'VERIFIED';
    }

    /**
     * Apply id_corrections into storage codes file. Returns diff summary.
     *
     * @return array{changed: list<array<string,mixed>>, backup_path: ?string}
     */
    public function applyToStorageCodes(bool $write): array
    {
        $before = $this->loadJsonObject($this->codesPath);
        $overrides = $this->loadJsonObject($this->overridesPath);
        $idCorrections = is_array($overrides['id_corrections'] ?? null)
            ? $overrides['id_corrections']
            : [];

        $changed = [];
        $after = $before;
        foreach ($idCorrections as $idKey => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $id = (string) ($meta['id'] ?? $idKey);
            $new = [
                'id' => $id,
                'code' => strtoupper(trim((string) ($meta['code'] ?? ''))),
                'name' => (string) ($meta['name'] ?? ''),
            ];
            $old = is_array($after[$id] ?? null) ? $after[$id] : null;
            if ($old === $new) {
                continue;
            }
            $changed[] = [
                'id' => $id,
                'from' => $old,
                'to' => $new,
                'reason' => (string) ($meta['reason'] ?? 'override'),
            ];
            $after[$id] = $new;
        }

        $backupPath = null;
        if ($write && $changed !== []) {
            $backupPath = $this->codesPath . '.bak.' . gmdate('Ymd\THis\Z');
            if (is_file($this->codesPath)) {
                copy($this->codesPath, $backupPath);
            }
            file_put_contents(
                $this->codesPath,
                json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
            );
        }

        return ['changed' => $changed, 'backup_path' => $backupPath];
    }

    /**
     * @return array<string,mixed>
     */
    private function loadJsonObject(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $raw = json_decode((string) file_get_contents($path), true);

        return is_array($raw) ? $raw : [];
    }
}
