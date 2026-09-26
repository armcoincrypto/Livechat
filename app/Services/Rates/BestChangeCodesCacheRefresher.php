<?php

declare(strict_types=1);

namespace App\Services\Rates;

use iEXPackages\BestChange\Services\BestChangeCatalogRepository;

/**
 * Rebuild storage/app/bestchange-codes.json from the live BestChange catalog.
 *
 * Roles:
 * - RUNTIME_AUTHORITY: BestChange live API / CatalogRepository (pricing)
 * - STATIC_CACHE_ROLE: bestchange-codes.json is CACHE + AUDIT only
 * - Overrides in resources/rates/bestchange-codes.overrides.json preserve intentional rail corrections
 *
 * Never invents Payeer (PR*) rails; never sole authority for migration cert.
 */
final class BestChangeCodesCacheRefresher
{
    public function __construct(
        private readonly BestChangeCatalogRepository $catalog,
        private readonly string $codesPath,
        private readonly string $overridesPath,
    ) {
    }

    public static function fromApp(): self
    {
        return new self(
            catalog: app(BestChangeCatalogRepository::class),
            codesPath: storage_path('app/bestchange-codes.json'),
            overridesPath: base_path('resources/rates/bestchange-codes.overrides.json'),
        );
    }

    /**
     * @return array{
     *   written: bool,
     *   backup_path: ?string,
     *   before_count: int,
     *   after_count: int,
     *   added: list<array{id:string,code:string}>,
     *   updated: list<array{id:string,from:?string,to:string}>,
     *   removed: list<string>,
     *   overrides_applied: list<string>
     * }
     */
    public function refresh(bool $write, bool $forceCatalogWarmup = true): array
    {
        if ($forceCatalogWarmup) {
            $this->catalog->warmupAll(true);
        }

        $live = $this->catalog->currencies(false);
        $built = [];
        foreach ($live as $key => $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? $key);
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($id <= 0 || $code === '') {
                continue;
            }
            // Payeer has no exact BC rail — never materialize PR* from a wrong live id.
            if (in_array($code, ['PRUSD', 'PRRUB', 'PREUR'], true)) {
                continue;
            }
            $built[(string) $id] = [
                'id' => (string) $id,
                'code' => $code,
                'name' => (string) ($row['name'] ?? $code),
            ];
        }

        $overrides = $this->loadJson($this->overridesPath);
        $overridesApplied = [];
        foreach (($overrides['id_corrections'] ?? []) as $idKey => $meta) {
            if (! is_array($meta)) {
                continue;
            }
            $id = (string) ($meta['id'] ?? $idKey);
            $code = strtoupper(trim((string) ($meta['code'] ?? '')));
            if ($id === '' || $code === '') {
                continue;
            }
            $built[$id] = [
                'id' => $id,
                'code' => $code,
                'name' => (string) ($meta['name'] ?? $code),
            ];
            $overridesApplied[] = $id.':'.$code;
        }

        ksort($built, SORT_NUMERIC);

        $before = $this->loadJson($this->codesPath);
        $added = [];
        $updated = [];
        foreach ($built as $id => $row) {
            $old = is_array($before[$id] ?? null) ? $before[$id] : null;
            if ($old === null) {
                $added[] = ['id' => (string) $id, 'code' => $row['code']];
                continue;
            }
            $oldCode = strtoupper(trim((string) ($old['code'] ?? '')));
            if ($oldCode !== $row['code']) {
                $updated[] = ['id' => (string) $id, 'from' => $oldCode !== '' ? $oldCode : null, 'to' => $row['code']];
            }
        }
        $removed = [];
        foreach ($before as $id => $_) {
            if (! isset($built[(string) $id])) {
                $removed[] = (string) $id;
            }
        }

        $backupPath = null;
        if ($write) {
            if (is_file($this->codesPath)) {
                $backupPath = $this->codesPath.'.bak.'.gmdate('Ymd\THis\Z');
                copy($this->codesPath, $backupPath);
            }
            $tmp = $this->codesPath.'.tmp.'.getmypid();
            file_put_contents(
                $tmp,
                json_encode($built, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"
            );
            rename($tmp, $this->codesPath);
        }

        return [
            'written' => $write,
            'backup_path' => $backupPath,
            'before_count' => count($before),
            'after_count' => count($built),
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
            'overrides_applied' => $overridesApplied,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $raw = json_decode((string) file_get_contents($path), true);

        return is_array($raw) ? $raw : [];
    }
}
