<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\BestChangeMappingRegistry;
use App\Services\Rates\BestChangeMappingVerifier;
use Illuminate\Console\Command;

/**
 * Apply verified BestChange ID corrections into storage codes.json.
 * Dry-run by default; requires --apply to write.
 */
final class RatesSyncMappingRegistryCommand extends Command
{
    protected $signature = 'rates:sync-mapping-registry
        {--apply : Write corrected codes to storage/app/bestchange-codes.json}
        {--format=json : json|table}';

    protected $description = 'Sync BestChange codes.json with verified overrides (fail-closed; dry-run default).';

    public function handle(): int
    {
        $registry = BestChangeMappingRegistry::fromStorageApp();
        $result = $registry->applyToStorageCodes(write: (bool) $this->option('apply'));

        $verifier = BestChangeMappingVerifier::fromStorageApp();
        $checkCodes = ['CARDVND', 'PRUSD', 'PREUR', 'PRRUB', 'TON', 'GRAM', 'BNB', 'BNBBEP20'];
        $verification = $verifier->verifyLocalCodes($checkCodes);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'applied' => (bool) $this->option('apply'),
            'changed' => $result['changed'],
            'backup_path' => $result['backup_path'],
            'verification' => $verification,
            'rule' => 'only_VERIFIED_may_export',
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
