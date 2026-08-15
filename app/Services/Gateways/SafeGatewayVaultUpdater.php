<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Facades\Vault;
use App\Gateways\Crypto\Kobbopay\Services\SecureSignatureService;
use App\Models\GatewayMerchant;
use App\Models\MerchantTransactionData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Safe partial updates for gateway vault credentials.
 *
 * - Blank / masked / omitted secret fields do not overwrite stored values.
 * - Pre-write backup + optional post-write auth probe with automatic rollback.
 */
final class SafeGatewayVaultUpdater
{
    private const MASKED_PLACEHOLDER = '** Параметр заполнен **';

    /** @var list<string> */
    private const OUTBOUND_KEYS = ['private_key', 'public_key', 'api_base_url'];

    /**
     * @param  array<string, mixed>  $connectionFields
     * @param  list<string>  $hiddenKeys
     * @param  list<array<string, mixed>>  $fieldDefs
     * @return array{
     *     summary: array<string, string>,
     *     webhook_only: bool,
     *     rolled_back: bool,
     *     probed: bool
     * }
     */
    public function updateMerchantVault(
        GatewayMerchant $merchant,
        array $connectionFields,
        array $hiddenKeys,
        array $fieldDefs,
        bool $confirmOutboundCredentialChange = false,
    ): array {
        $filename = trim((string) $merchant->filename);
        if ($filename === '') {
            throw new RuntimeException('Gateway vault filename missing.');
        }

        $before = Vault::decryptFromFile($filename, 'gateways', false);
        if (!is_array($before)) {
            throw new RuntimeException('Gateway vault decrypt failed before update.');
        }

        $allowedKeys = collect($fieldDefs)
            ->pluck('key')
            ->filter(fn ($k) => is_string($k) && $k !== '')
            ->values()
            ->all();

        $incoming = $this->normalizeIncoming($connectionFields, $hiddenKeys, $allowedKeys);
        $merged = $before;
        $summary = [];

        foreach ($allowedKeys as $key) {
            $summary[$key] = 'unchanged';
        }

        foreach ($incoming as $key => $value) {
            $old = (string) ($before[$key] ?? '');
            if (!hash_equals($old, $value)) {
                $merged[$key] = $value;
                $summary[$key] = 'changed';
            }
        }

        // Preserve unrelated keys that may already exist in the vault.
        foreach ($before as $key => $value) {
            if (!array_key_exists($key, $merged)) {
                $merged[$key] = $value;
            }
        }

        $this->validateAliasSpecific($merchant->alias, $merged, $summary);

        $outboundChanging = collect(self::OUTBOUND_KEYS)
            ->contains(fn (string $key) => ($summary[$key] ?? 'unchanged') === 'changed');

        $webhookOnly = (($summary['webhook_secret'] ?? 'unchanged') === 'changed') && !$outboundChanging
            && collect($summary)->filter(fn ($state, $key) => $state === 'changed' && $key !== 'webhook_secret')->isEmpty();

        if ($outboundChanging && !$confirmOutboundCredentialChange) {
            throw ValidationException::withMessages([
                'confirm_outbound_credential_change' => [
                    'Outbound API credentials would change. Re-submit with confirm_outbound_credential_change=true. '
                    . 'Summary: ' . $this->formatSummary($summary)
                    . ($outboundChanging ? ' This will affect invoice creation immediately.' : ''),
                ],
                'credential_change_summary' => [$this->formatSummary($summary)],
            ]);
        }

        if (!collect($summary)->contains('changed')) {
            return [
                'summary' => $summary,
                'webhook_only' => false,
                'rolled_back' => false,
                'probed' => false,
            ];
        }

        $backupPath = $this->createPreWriteBackup($filename);

        Log::info('gateway_vault_prewrite', [
            'merchant_id' => $merchant->id,
            'alias' => $merchant->alias,
            'summary' => $summary,
            'webhook_only' => $webhookOnly,
            'backup' => basename($backupPath),
        ]);

        try {
            Vault::encryptToFile($filename, $merged, 'gateways');
        } catch (Throwable $e) {
            $this->restoreBackup($filename, $backupPath);
            throw $e;
        }

        $probed = false;
        $rolledBack = false;

        if ($merchant->alias === 'kobbopay' && $outboundChanging) {
            $probed = true;
            if (!$this->probeKobbopayOutboundAuth($filename)) {
                $this->restoreBackup($filename, $backupPath);
                $rolledBack = true;
                Log::error('gateway_vault_postwrite_probe_failed_rolled_back', [
                    'merchant_id' => $merchant->id,
                    'alias' => $merchant->alias,
                    'summary' => $summary,
                ]);
                throw ValidationException::withMessages([
                    'connectionFields' => [
                        'Post-write Kobbopay authentication probe failed. Vault restored from pre-write backup. '
                        . 'Outbound API credentials were not left in a broken state.',
                    ],
                ]);
            }
        }

        // Presence-only readback
        $after = Vault::decryptFromFile($filename, 'gateways', false);
        foreach ($allowedKeys as $key) {
            if (($summary[$key] ?? 'unchanged') === 'unchanged') {
                if (!hash_equals((string) ($before[$key] ?? ''), (string) ($after[$key] ?? ''))) {
                    $this->restoreBackup($filename, $backupPath);
                    throw new RuntimeException("Vault field unexpectedly changed: {$key}");
                }
            }
        }

        Log::info('gateway_vault_postwrite_ok', [
            'merchant_id' => $merchant->id,
            'alias' => $merchant->alias,
            'summary' => $summary,
            'webhook_only' => $webhookOnly,
            'probed' => $probed,
            'rolled_back' => $rolledBack,
        ]);

        return [
            'summary' => $summary,
            'webhook_only' => $webhookOnly,
            'rolled_back' => $rolledBack,
            'probed' => $probed,
        ];
    }

    /**
     * @param  array<string, mixed>  $connectionFields
     * @param  list<string>  $hiddenKeys
     * @param  list<string>  $allowedKeys
     * @return array<string, string>
     */
    private function normalizeIncoming(array $connectionFields, array $hiddenKeys, array $allowedKeys): array
    {
        $hidden = array_fill_keys($hiddenKeys, true);
        $allowed = array_fill_keys($allowedKeys, true);
        $out = [];

        foreach ($connectionFields as $key => $value) {
            if (!is_string($key) || !isset($allowed[$key])) {
                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $trimmed = trim((string) $value);
            if ($trimmed === '' || $trimmed === self::MASKED_PLACEHOLDER) {
                // Blank / masked secrets must not overwrite stored values.
                continue;
            }

            // Non-hidden empty strings for optional non-secret fields (e.g. clearing) are still omitted
            // unless explicitly non-empty — keeps partial updates safe.
            if (isset($hidden[$key]) && $trimmed === '') {
                continue;
            }

            $out[$key] = $trimmed;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $merged
     * @param  array<string, string>  $summary
     */
    private function validateAliasSpecific(string $alias, array $merged, array $summary): void
    {
        if ($alias !== 'kobbopay') {
            return;
        }

        $errors = [];

        if (($summary['private_key'] ?? 'unchanged') === 'changed') {
            $pk = (string) ($merged['private_key'] ?? '');
            // Outbound signing secrets are long HMAC keys (historically 64). A 36-char UUID-shaped
            // value is the known webhook_secret mix-up class — reject it here.
            if (strlen($pk) < 48) {
                $errors['connectionFields.private_key'] = 'API private signing key is too short for Kobbopay outbound HMAC-SHA512 (min 48).';
            } elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $pk) === 1) {
                $errors['connectionFields.private_key'] = 'API private signing key looks like a UUID. That value belongs in webhook_secret, not private_key.';
            }
            $ws = (string) ($merged['webhook_secret'] ?? '');
            if ($ws !== '' && hash_equals($pk, $ws)) {
                $errors['connectionFields.private_key'] = 'private_key must not equal webhook_secret. They serve different roles.';
            }
        }

        if (($summary['public_key'] ?? 'unchanged') === 'changed') {
            $pub = (string) ($merged['public_key'] ?? '');
            if (strlen($pub) < 16) {
                $errors['connectionFields.public_key'] = 'API public identifier is too short.';
            }
        }

        if (($summary['webhook_secret'] ?? 'unchanged') === 'changed') {
            $ws = (string) ($merged['webhook_secret'] ?? '');
            if (strlen($ws) < 16) {
                $errors['connectionFields.webhook_secret'] = 'Inbound webhook signing secret is too short.';
            }
            $pk = (string) ($merged['private_key'] ?? '');
            if ($pk !== '' && hash_equals($pk, $ws)) {
                $errors['connectionFields.webhook_secret'] = 'webhook_secret must not equal private_key.';
            }
        }

        if (($summary['api_base_url'] ?? 'unchanged') === 'changed') {
            $url = (string) ($merged['api_base_url'] ?? '');
            if (!str_starts_with($url, 'https://')) {
                $errors['connectionFields.api_base_url'] = 'api_base_url must be HTTPS.';
            } else {
                $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
                $allowedHosts = ['merchant.kobbex.com', 'api.kobbex.com'];
                if (!in_array($host, $allowedHosts, true)) {
                    $errors['connectionFields.api_base_url'] = 'api_base_url host is not an approved Kobbopay API host (not pay.kobbex.com frontend).';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function createPreWriteBackup(string $filename): string
    {
        $src = storage_path('app/vault/gateways/' . $filename . '.dat');
        if (!is_file($src)) {
            throw new RuntimeException('Vault file missing for backup.');
        }

        $dir = storage_path('app/vault/.prewrite-backups');
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create vault prewrite backup directory.');
        }
        @chmod($dir, 0700);

        $dest = $dir . '/' . $filename . '-' . gmdate('Ymd\THis\Z') . '.dat';
        if (!copy($src, $dest)) {
            throw new RuntimeException('Vault prewrite backup failed.');
        }
        @chmod($dest, 0600);

        return $dest;
    }

    private function restoreBackup(string $filename, string $backupPath): void
    {
        $dest = storage_path('app/vault/gateways/' . $filename . '.dat');
        if (!is_file($backupPath)) {
            throw new RuntimeException('Backup missing; cannot restore vault.');
        }
        if (!copy($backupPath, $dest)) {
            throw new RuntimeException('Vault restore from prewrite backup failed.');
        }
    }

    private function probeKobbopayOutboundAuth(string $filename): bool
    {
        try {
            $cfg = Vault::decryptFromFile($filename, 'gateways', false);
            $public = trim((string) ($cfg['public_key'] ?? ''));
            $private = trim((string) ($cfg['private_key'] ?? ''));
            $base = rtrim(trim((string) ($cfg['api_base_url'] ?? 'https://merchant.kobbex.com')), '/');

            if ($public === '' || $private === '' || $base === '') {
                return false;
            }

            // Prefer a real historical tracker when available; otherwise use a non-mutating probe id.
            // Auth success is proven by any non-401/403 response (including 404/422).
            $tracker = (string) (MerchantTransactionData::query()
                ->where('service_name', 'kobbopay')
                ->whereNotNull('id_from_merchant')
                ->where('id_from_merchant', '!=', '')
                ->orderByDesc('id')
                ->value('id_from_merchant') ?: 'credential-probe-no-create');

            $sig = new SecureSignatureService($private);
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'ApiPublic' => $public,
                'Signature' => (string) $sig->generateSignature(null),
                'Timestamp' => (string) $sig->getTimestamp(),
            ])->timeout(20)->get($base . '/api/crypto/invoice/get/', [
                'tracker_id' => $tracker,
            ]);

            $private = str_repeat("\0", strlen($private));
            unset($private, $cfg);

            if ($response->status() === 401 || $response->status() === 403) {
                return false;
            }

            // 200 with JSON, or 404/422 that still proves auth accepted.
            return $response->successful() || in_array($response->status(), [404, 422], true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, string>  $summary
     */
    private function formatSummary(array $summary): string
    {
        $parts = [];
        foreach ($summary as $key => $state) {
            $parts[] = $key . '=' . $state;
        }

        return implode(', ', $parts);
    }
}
