<?php

declare(strict_types=1);

namespace App\Services\Rates;

use RuntimeException;

/**
 * Atomically publish a public XML rates file.
 *
 * Prevents nginx from serving a truncated file mid-write (observed as
 * pread() read only 0 of N while clients time out with 0 bytes).
 */
final class AtomicPublicXmlPublisher
{
    /**
     * @param array{min_items?:int,backup?:bool,sync_legacy?:bool} $options
     * @return array{published:bool,path:string,items:int,backup:?string,reason:?string}
     */
    public function publish(string $destinationPath, string $contents, array $options = []): array
    {
        $minItems = (int) ($options['min_items'] ?? 1);
        $doBackup = (bool) ($options['backup'] ?? true);
        $syncLegacy = (bool) ($options['sync_legacy'] ?? false);

        $validation = $this->validateXml($contents, $minItems);
        if (!$validation['ok']) {
            return [
                'published' => false,
                'path' => $destinationPath,
                'items' => $validation['items'],
                'backup' => null,
                'reason' => $validation['reason'],
            ];
        }

        $dir = dirname($destinationPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('cannot_create_export_directory');
        }

        $backup = null;
        if ($doBackup && is_file($destinationPath) && filesize($destinationPath) > 0) {
            $backup = $destinationPath . '.last-good';
            // Best-effort last-known-good (overwrite).
            @copy($destinationPath, $backup);
        }

        $tmp = $dir . '/.' . basename($destinationPath) . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        $fh = fopen($tmp, 'wb');
        if ($fh === false) {
            throw new RuntimeException('cannot_open_temp_xml');
        }
        try {
            $written = fwrite($fh, $contents);
            if ($written === false || $written !== strlen($contents)) {
                throw new RuntimeException('incomplete_temp_xml_write');
            }
            fflush($fh);
            fsync($fh);
        } finally {
            fclose($fh);
        }

        // Re-validate temp file on disk before rename.
        $onDisk = (string) file_get_contents($tmp);
        $recheck = $this->validateXml($onDisk, $minItems);
        if (!$recheck['ok']) {
            @unlink($tmp);

            return [
                'published' => false,
                'path' => $destinationPath,
                'items' => $recheck['items'],
                'backup' => $backup,
                'reason' => 'temp_' . ($recheck['reason'] ?? 'invalid'),
            ];
        }

        if (!rename($tmp, $destinationPath)) {
            @unlink($tmp);
            throw new RuntimeException('atomic_rename_failed');
        }
        @chmod($destinationPath, 0664);

        if ($syncLegacy) {
            $legacy = public_path('currencies.xml');
            $legacyTmp = dirname($legacy) . '/.currencies.xml.tmp.' . getmypid();
            if (@copy($destinationPath, $legacyTmp)) {
                @rename($legacyTmp, $legacy);
                @chmod($legacy, 0644);
            }
        }

        return [
            'published' => true,
            'path' => $destinationPath,
            'items' => $recheck['items'],
            'backup' => $backup,
            'reason' => null,
        ];
    }

    /**
     * @return array{ok:bool,items:int,reason:?string}
     */
    public function validateXml(string $contents, int $minItems = 1): array
    {
        $trim = trim($contents);
        if ($trim === '') {
            return ['ok' => false, 'items' => 0, 'reason' => 'empty_xml'];
        }
        if (!str_contains($trim, '<rates') || !str_contains($trim, '</rates>')) {
            return ['ok' => false, 'items' => 0, 'reason' => 'missing_rates_root'];
        }

        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($xml === false) {
            return ['ok' => false, 'items' => 0, 'reason' => 'xml_parse_error'];
        }

        $items = substr_count($contents, '<item>');
        if ($items < $minItems) {
            return ['ok' => false, 'items' => $items, 'reason' => 'item_count_below_minimum'];
        }

        // Collapse guard vs last-good when available is applied by caller if needed.
        return ['ok' => true, 'items' => $items, 'reason' => null];
    }

    /**
     * Refuse publish when new item count collapses vs last-known-good.
     */
    public function collapsesAgainstLastGood(string $destinationPath, int $newItems, float $ratio = 0.5): bool
    {
        $lastGood = $destinationPath . '.last-good';
        if (!is_file($lastGood)) {
            return false;
        }
        $prevItems = substr_count((string) file_get_contents($lastGood), '<item>');
        if ($prevItems <= 0) {
            return false;
        }

        return $newItems < (int) floor($prevItems * $ratio);
    }
}
