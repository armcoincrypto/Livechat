<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

use App\Models\DirectionExchange;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Issues opaque locked quotes from the same calculation as the public fast quote,
 * and applies them at order create without recalculating market price.
 */
final class LockedQuoteService
{
    public function __construct(
        private readonly LockedQuoteStore $store,
        private readonly FastMarketSourceRouter $router,
    ) {}

    /**
     * Issue a locked quote from an already-computed fast quote result.
     * Must use the SAME calculator outputs — no second market read.
     *
     * @param  array<string, mixed>  $fastQuote  FastMarketQuoteService::quote() result
     * @return array{quote_token: string, quoted_at: int, expires_at: int, valid_for_seconds: int}|null
     */
    public function issueFromFastQuote(DirectionExchange $direction, array $fastQuote): ?array
    {
        if (! FastMarketFlags::lockedQuoteEnabled()) {
            return null;
        }
        if (! ($fastQuote['used_fast_path'] ?? false)) {
            return null;
        }
        $route = $this->router->route($direction);
        if (! ($route['use_bestchange_snapshot'] ?? false)) {
            return null;
        }

        $payload = $fastQuote['calculator_payload'] ?? null;
        if (! is_array($payload)) {
            return null;
        }

        $rateValue = (string) ($fastQuote['rate_value'] ?? $fastQuote['rate'] ?? $payload['rate'] ?? '');
        if ($rateValue === '' || ! is_numeric($rateValue)) {
            return null;
        }

        $calculatorRate = (string) ($payload['rate'] ?? $rateValue);
        $courseDisplay = (string) ($fastQuote['course_display'] ?? '');
        if ($courseDisplay === '') {
            // Prefer full rate string captured during quote(); fall back to numeric.
            $courseDisplay = (string) ($payload['full_rate'] ?? $calculatorRate);
        }
        $displayRate = (string) ($fastQuote['display_rate'] ?? $calculatorRate);

        $ttl = FastMarketFlags::quoteTtlSeconds();
        $now = time();
        $token = $this->newToken();
        $canonical = $this->canonicalInput($direction, $route);
        $bindingHash = $this->bindingHash($canonical);
        $fingerprint = $this->pricingFingerprint($direction);

        $record = new LockedQuoteRecord(
            quoteId: $token,
            createdAt: $now,
            expiresAt: $now + $ttl,
            snapshotId: (string) ($fastQuote['snapshot_id'] ?? ''),
            snapshotFetchedAt: (int) ($fastQuote['snapshot_fetched_at'] ?? ($now - (int) ($fastQuote['market_age_seconds'] ?? 0))),
            directionId: (int) $direction->id,
            pricingFamily: (string) ($route['family'] ?? 'bestchange'),
            sourceOwner: (string) ($route['calculation_owner'] ?? $route['family'] ?? 'bestchange'),
            inputBindingHash: $bindingHash,
            canonicalInput: $canonical,
            rateValue: $rateValue,
            calculatorRate: $calculatorRate,
            courseDisplay: $courseDisplay,
            displayRate: $displayRate,
            pricingFingerprint: $fingerprint,
            generation: $this->store->currentGeneration(),
            status: LockedQuoteRecord::STATUS_ISSUED,
            usedFastPath: true,
            audit: [
                'freshness_state' => $fastQuote['freshness_state'] ?? null,
                'market_age_seconds' => $fastQuote['market_age_seconds'] ?? null,
            ],
        );

        try {
            $this->store->put($record, $ttl + 5);
        } catch (LockedQuoteException $e) {
            Log::warning('locked_quote_issue_failed', [
                'direction_id' => (int) $direction->id,
                'code' => $e->errorCode,
            ]);
            // Fail closed for token issuance: do not advertise an unlocked token.
            return null;
        }

        try {
            Log::info('locked_quote_issued', [
                'quote_id' => $token,
                'direction_id' => (int) $direction->id,
                'snapshot_id' => $record->snapshotId,
                'ttl' => $ttl,
                'expires_at' => $record->expiresAt,
            ]);
        } catch (Throwable) {
        }

        return [
            'quote_token' => $token,
            'quoted_at' => $now,
            'expires_at' => $record->expiresAt,
            'valid_for_seconds' => $ttl,
        ];
    }

    /**
     * Resolve + validate quote for order create. Does not consume.
     *
     * @param  array<string, mixed>  $orderBinding  direction_id, currency_in_id, currency_out_id
     */
    public function resolveForOrder(string $token, array $orderBinding, int $now = 0): LockedQuoteRecord
    {
        $now = $now > 0 ? $now : time();
        $token = trim($token);
        if ($token === '' || strlen($token) > 128 || ! preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
            throw LockedQuoteException::malformed();
        }

        $record = $this->store->get($token);
        if ($record === null) {
            throw LockedQuoteException::notFound();
        }
        if ($record->status === LockedQuoteRecord::STATUS_INVALIDATED) {
            throw LockedQuoteException::invalidated();
        }
        if ($record->generation !== $this->store->currentGeneration()) {
            throw LockedQuoteException::invalidated();
        }
        if ($record->isExpired($now)) {
            throw LockedQuoteException::expired();
        }
        if (! $record->usedFastPath) {
            throw LockedQuoteException::unsupported();
        }

        $this->assertBinding($record, $orderBinding);

        return $record;
    }

    /**
     * Hydrate direction with locked rate_value (no live snapshot reload).
     */
    public function hydrateDirectionFromLockedQuote(
        DirectionExchange $direction,
        LockedQuoteRecord $quote,
    ): DirectionExchange {
        $route = $this->router->route($direction);
        if (! ($route['use_bestchange_snapshot'] ?? false)) {
            throw LockedQuoteException::unsupported();
        }

        return app(FastMarketQuoteService::class)
            ->hydrateDirectionForLockedRate($direction, $quote->rateValue);
    }

    /**
     * Mark quote linkage after successful order create (multi-use until expiry).
     * Does not block retries; idempotency is owned by order_attempt_id.
     */
    public function rememberOrderLinkage(LockedQuoteRecord $quote, int $orderId): void
    {
        try {
            $remaining = max(1, $quote->expiresAt - time());
            // Multi-use until expiry: keep status=issued; record last order for audit.
            $updated = $quote->withStatus(
                LockedQuoteRecord::STATUS_ISSUED,
                consumedAt: time(),
                orderId: $orderId,
            );
            $this->store->replace($updated, $remaining);
        } catch (Throwable $e) {
            Log::warning('locked_quote_linkage_failed', [
                'quote_id' => $quote->quoteId,
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Shadow compare: what locked order course would be vs legacy calc.
     *
     * @return array{legacy_course_display: string, locked_course_display: string, rates_equal: bool, classification: string}
     */
    public function shadowCompareOrder(
        string $legacyCourseDisplay,
        LockedQuoteRecord $quote,
    ): array {
        $equal = $this->ratesEqual($legacyCourseDisplay, $quote->courseDisplay)
            || $this->ratesEqual($legacyCourseDisplay, $quote->calculatorRate)
            || $this->ratesEqual($legacyCourseDisplay, $quote->displayRate);

        $classification = $equal ? 'MATCH' : 'MARKET_MOVEMENT_AFTER_QUOTE';
        // If binding ok and quote valid, course_display string format differences
        // vs numeric rate are expected — compare calculator_rate to float extract.
        if (! $equal) {
            // Prefer algorithm mismatch only when locked rate_value path diverges unexpectedly.
            $classification = 'FORMAT_OR_MARKET_DIFF';
        }

        Log::info('fast_market_order_quote_shadow', [
            'quote_id' => $quote->quoteId,
            'legacy_course_display' => $legacyCourseDisplay,
            'locked_course_display' => $quote->courseDisplay,
            'locked_calculator_rate' => $quote->calculatorRate,
            'rates_equal' => $equal,
            'classification' => $classification,
        ]);

        return [
            'legacy_course_display' => $legacyCourseDisplay,
            'locked_course_display' => $quote->courseDisplay,
            'rates_equal' => $equal,
            'classification' => $classification,
        ];
    }

    /**
     * @param  array<string, mixed>  $route
     * @return array{direction_id: int, currency_in_id: int, currency_out_id: int, pricing_family: string, source_owner: string}
     */
    public function canonicalInput(DirectionExchange $direction, array $route = []): array
    {
        if ($route === []) {
            $route = $this->router->route($direction);
        }
        $family = (string) ($route['family'] ?? 'bestchange');
        $owner = (string) ($route['calculation_owner'] ?? $family);

        return [
            'direction_id' => (int) $direction->id,
            'currency_in_id' => (int) ($direction->id_currency1 ?? 0),
            'currency_out_id' => (int) ($direction->id_currency2 ?? 0),
            'pricing_family' => $family,
            'source_owner' => $owner,
        ];
    }

    /**
     * @param  array{direction_id: int, currency_in_id: int, currency_out_id: int, pricing_family: string, source_owner: string}  $canonical
     */
    public function bindingHash(array $canonical): string
    {
        ksort($canonical);

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE));
    }

    public function pricingFingerprint(DirectionExchange $direction): string
    {
        $fields = [
            'profit' => (string) ($direction->profit ?? ''),
            'add_course' => (string) ($direction->add_course ?? ''),
            'add_course1' => (string) ($direction->add_course1 ?? ''),
            'add_course2' => (string) ($direction->add_course2 ?? ''),
            'floating_fee' => (string) ($direction->floating_fee ?? ''),
            'fixed_fee' => (string) ($direction->fixed_fee ?? ''),
            'course_value' => (string) ($direction->course_value ?? ''),
            'parser_source_name' => (string) ($direction->parser_source_name ?? ''),
            'is_type_rate' => (int) ($direction->is_type_rate ?? 0),
        ];

        return hash('sha256', json_encode($fields, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $orderBinding
     */
    private function assertBinding(LockedQuoteRecord $record, array $orderBinding): void
    {
        $expected = $record->canonicalInput;
        $checks = [
            'direction_id' => (int) ($orderBinding['direction_id'] ?? 0),
            'currency_in_id' => (int) ($orderBinding['currency_in_id'] ?? 0),
            'currency_out_id' => (int) ($orderBinding['currency_out_id'] ?? 0),
        ];
        foreach ($checks as $key => $actual) {
            if ($actual <= 0 || $actual !== (int) ($expected[$key] ?? -1)) {
                throw LockedQuoteException::inputMismatch($key);
            }
        }
        if (($orderBinding['pricing_family'] ?? null) !== null
            && (string) $orderBinding['pricing_family'] !== $expected['pricing_family']
        ) {
            throw LockedQuoteException::inputMismatch('pricing_family');
        }
    }

    private function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function ratesEqual(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        // Extract leading numeric if full-rate string
        $na = $this->extractNumeric($a);
        $nb = $this->extractNumeric($b);
        if ($na === null || $nb === null) {
            return false;
        }
        if (function_exists('bccomp')) {
            return bccomp($na, $nb, 12) === 0;
        }

        return abs((float) $na - (float) $nb) < 1e-12;
    }

    private function extractNumeric(string $s): ?string
    {
        if (is_numeric($s)) {
            return $s;
        }
        if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $s, $m)) {
            return $m[1];
        }

        return null;
    }
}
