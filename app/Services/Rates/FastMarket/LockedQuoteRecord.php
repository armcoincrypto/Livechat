<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

/**
 * Immutable server-side quote artifact. Financial authority lives here — never
 * in client-supplied monetary fields.
 *
 * @phpstan-type CanonicalInput array{
 *   direction_id: int,
 *   currency_in_id: int,
 *   currency_out_id: int,
 *   pricing_family: string,
 *   source_owner: string
 * }
 */
final class LockedQuoteRecord
{
    public const STATUS_ISSUED = 'issued';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_INVALIDATED = 'invalidated';

    public const CALC_VERSION = 'locked_quote_v1';

    /**
     * @param  CanonicalInput  $canonicalInput
     * @param  array<string, mixed>  $audit
     */
    public function __construct(
        public readonly string $quoteId,
        public readonly int $createdAt,
        public readonly int $expiresAt,
        public readonly string $snapshotId,
        public readonly int $snapshotFetchedAt,
        public readonly int $directionId,
        public readonly string $pricingFamily,
        public readonly string $sourceOwner,
        public readonly string $inputBindingHash,
        public readonly array $canonicalInput,
        public readonly string $rateValue,
        public readonly string $calculatorRate,
        public readonly string $courseDisplay,
        public readonly string $displayRate,
        public readonly string $pricingFingerprint,
        public readonly int $generation,
        public readonly string $status,
        public readonly bool $usedFastPath,
        public readonly array $audit = [],
        public readonly ?int $consumedAt = null,
        public readonly ?int $orderId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'quote_id' => $this->quoteId,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'snapshot_id' => $this->snapshotId,
            'snapshot_fetched_at' => $this->snapshotFetchedAt,
            'direction_id' => $this->directionId,
            'pricing_family' => $this->pricingFamily,
            'source_owner' => $this->sourceOwner,
            'input_binding_hash' => $this->inputBindingHash,
            'canonical_input' => $this->canonicalInput,
            'rate_value' => $this->rateValue,
            'calculator_rate' => $this->calculatorRate,
            'course_display' => $this->courseDisplay,
            'display_rate' => $this->displayRate,
            'pricing_fingerprint' => $this->pricingFingerprint,
            'calculation_version' => self::CALC_VERSION,
            'generation' => $this->generation,
            'status' => $this->status,
            'used_fast_path' => $this->usedFastPath,
            'audit' => $this->audit,
            'consumed_at' => $this->consumedAt,
            'order_id' => $this->orderId,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $canonical = $data['canonical_input'] ?? null;
        if (! is_array($canonical)) {
            throw LockedQuoteException::malformed();
        }
        foreach (['quote_id', 'rate_value', 'course_display', 'display_rate', 'calculator_rate', 'input_binding_hash', 'pricing_fingerprint', 'snapshot_id', 'pricing_family', 'source_owner', 'status'] as $req) {
            if (! isset($data[$req]) || ! is_scalar($data[$req])) {
                throw LockedQuoteException::malformed();
            }
        }

        return new self(
            quoteId: (string) $data['quote_id'],
            createdAt: (int) ($data['created_at'] ?? 0),
            expiresAt: (int) ($data['expires_at'] ?? 0),
            snapshotId: (string) $data['snapshot_id'],
            snapshotFetchedAt: (int) ($data['snapshot_fetched_at'] ?? 0),
            directionId: (int) ($data['direction_id'] ?? 0),
            pricingFamily: (string) $data['pricing_family'],
            sourceOwner: (string) $data['source_owner'],
            inputBindingHash: (string) $data['input_binding_hash'],
            canonicalInput: [
                'direction_id' => (int) ($canonical['direction_id'] ?? 0),
                'currency_in_id' => (int) ($canonical['currency_in_id'] ?? 0),
                'currency_out_id' => (int) ($canonical['currency_out_id'] ?? 0),
                'pricing_family' => (string) ($canonical['pricing_family'] ?? ''),
                'source_owner' => (string) ($canonical['source_owner'] ?? ''),
            ],
            rateValue: (string) $data['rate_value'],
            calculatorRate: (string) $data['calculator_rate'],
            courseDisplay: (string) $data['course_display'],
            displayRate: (string) $data['display_rate'],
            pricingFingerprint: (string) $data['pricing_fingerprint'],
            generation: (int) ($data['generation'] ?? 0),
            status: (string) $data['status'],
            usedFastPath: (bool) ($data['used_fast_path'] ?? false),
            audit: is_array($data['audit'] ?? null) ? $data['audit'] : [],
            consumedAt: isset($data['consumed_at']) ? (int) $data['consumed_at'] : null,
            orderId: isset($data['order_id']) ? (int) $data['order_id'] : null,
        );
    }

    public function isExpired(int $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function withStatus(string $status, ?int $consumedAt = null, ?int $orderId = null): self
    {
        return new self(
            quoteId: $this->quoteId,
            createdAt: $this->createdAt,
            expiresAt: $this->expiresAt,
            snapshotId: $this->snapshotId,
            snapshotFetchedAt: $this->snapshotFetchedAt,
            directionId: $this->directionId,
            pricingFamily: $this->pricingFamily,
            sourceOwner: $this->sourceOwner,
            inputBindingHash: $this->inputBindingHash,
            canonicalInput: $this->canonicalInput,
            rateValue: $this->rateValue,
            calculatorRate: $this->calculatorRate,
            courseDisplay: $this->courseDisplay,
            displayRate: $this->displayRate,
            pricingFingerprint: $this->pricingFingerprint,
            generation: $this->generation,
            status: $status,
            usedFastPath: $this->usedFastPath,
            audit: $this->audit,
            consumedAt: $consumedAt ?? $this->consumedAt,
            orderId: $orderId ?? $this->orderId,
        );
    }
}
