<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VerificationCard;
use App\Services\Verification\VerificationIdentifierVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Resumable, idempotent backfill of verification_card encrypted identifier derivatives.
 * Never prints plaintext, ciphertext, lookup, or last4.
 */
class EncryptVerificationCardIdentifiersCommand extends Command
{
    protected $signature = 'verification-card:encrypt-identifiers
        {--dry-run : Count candidates only; write nothing}
        {--chunk=100 : Chunk size for processing}
        {--limit= : Maximum rows to encrypt this run}
        {--after-id=0 : Resume after this primary key}
        {--verify : Read-only cryptographic validation of encrypted rows}
        {--ids-file= : Write affected row IDs (JSON list) for rollback targeting}';

    protected $description = 'Backfill verification_card identifier ciphertext/lookup/last4 without changing plaintext';

    public function handle(VerificationIdentifierVault $vault): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $afterId = (int) ($this->option('after-id') ?: 0);
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt === null || $limitOpt === '') ? null : max(1, (int) $limitOpt);
        $dryRun = (bool) $this->option('dry-run');
        $verify = (bool) $this->option('verify');
        $idsFile = $this->option('ids-file');

        try {
            $vault->assertEncryptionKeysReady();
            $vault->assertLookupKeyReady();
        } catch (Throwable $e) {
            $this->error('Key configuration invalid: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($verify) {
            return $this->runVerify($vault, $chunk, $afterId, $limit);
        }

        $candidatesQuery = $this->candidatesQuery($afterId);
        $candidateCount = (clone $candidatesQuery)->count();
        $this->info('candidates='.$candidateCount);
        $this->info('dry_run='.($dryRun ? '1' : '0'));
        $this->info('chunk='.$chunk);
        $this->info('limit='.($limit ?? 'none'));
        $this->info('after_id='.$afterId);

        if ($dryRun) {
            $this->info('writes=0');
            $this->info('failures=0');
            $this->info('note=plaintext_columns_retired_backfill_noop');

            return self::SUCCESS;
        }

        if ($candidateCount === 0) {
            $this->info('encrypted=0');
            $this->info('already_encrypted_skipped=0');
            $this->info('empty=0');
            $this->info('failures=0');
            $this->info('note=plaintext_columns_retired_no_candidates');

            return self::SUCCESS;
        }

        $this->error('unexpected_plaintext_backfill_candidates='.$candidateCount);

        return self::FAILURE;
    }

    private function runVerify(
        VerificationIdentifierVault $vault,
        int $chunk,
        int $afterId,
        ?int $limit
    ): int {
        $query = VerificationCard::query()
            ->whereNotNull('card_number_ciphertext')
            ->where('card_number_ciphertext', '!=', '')
            ->where('id', '>', $afterId)
            ->orderBy('id');

        $checked = 0;
        $pass = 0;
        $fail = 0;
        $failIds = [];
        $budget = $limit;

        $query->chunkById($chunk, function ($rows) use (
            $vault,
            &$checked,
            &$pass,
            &$fail,
            &$failIds,
            &$budget
        ) {
            foreach ($rows as $row) {
                if ($budget !== null && $budget <= 0) {
                    return false;
                }

                $checked++;
                $ok = true;
                $reason = '';

                try {
                    if (! str_starts_with((string) $row->card_number_ciphertext, VerificationIdentifierVault::ENVELOPE_PREFIX)) {
                        $ok = false;
                        $reason = 'unknown_envelope';
                    } else {
                        $decrypted = $vault->decrypt((string) $row->card_number_ciphertext);
                        $normalized = $vault->normalize($decrypted);
                        if ($normalized === '') {
                            $ok = false;
                            $reason = 'empty_decrypt';
                        } elseif ((string) $row->card_number_lookup !== $vault->lookupHash($normalized)) {
                            $ok = false;
                            $reason = 'lookup_mismatch';
                        } elseif ((string) $row->card_number_last4 !== $vault->lastFour($normalized)) {
                            $ok = false;
                            $reason = 'last4_mismatch';
                        } elseif ((int) $row->identifier_key_version !== VerificationIdentifierVault::KEY_VERSION) {
                            $ok = false;
                            $reason = 'key_version_mismatch';
                        }
                    }
                } catch (Throwable $e) {
                    $ok = false;
                    $reason = $e->getMessage();
                }

                if ($ok) {
                    $pass++;
                } else {
                    $fail++;
                    $failIds[] = (int) $row->id;
                    $this->error('verify_fail_id='.$row->id.' reason='.$reason);
                }

                if ($budget !== null) {
                    $budget--;
                }
            }

            return $budget === null || $budget > 0;
        }, 'id');

        $this->info('verify_checked='.$checked);
        $this->info('verify_pass='.$pass);
        $this->info('verify_fail='.$fail);
        if ($failIds !== []) {
            $this->info('verify_fail_ids='.implode(',', $failIds));
        }

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function candidatesQuery(int $afterId)
    {
        // Plaintext columns retired — no legacy backfill candidates remain.
        return VerificationCard::query()->whereRaw('1 = 0')->where('id', '>', $afterId);
    }

    private function alreadyEncrypted(object $row): bool
    {
        $ct = (string) ($row->card_number_ciphertext ?? '');

        return $ct !== '';
    }
}
