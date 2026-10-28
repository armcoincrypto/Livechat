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

        // Customer-visible rate (post-canonical) is the financial lock authority.
        $displayRate = (string) ($fastQuote['lock_rate'] ?? $fastQuote['display_rate'] ?? '');
        if ($displayRate === '' || ! is_numeric($displayRate)) {
            $displayRate = (string) ($payload['rate'] ?? $rateValue);
        }
        $calculatorRate = $displayRate;
        $courseDisplay = $this->formatCourseDisplay($direction, $calculatorRate);
        if ($courseDisplay === '') {
            $courseDisplay = (string) ($fastQuote['course_display'] ?? $calculatorRate);
        }

        $ttl = FastMarketFlags::quoteTtlSeconds();
        $now = time();
        $token = $this->newToken();
        $orderOpts = is_array($fastQuote['order_binding'] ?? null) ? $fastQuote['order_binding'] : [];
        $canonical = $this->canonicalInput($direction, $route, $orderOpts);
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
        if ($record->status === LockedQuoteRecord::STATUS_CONSUMED) {
            // Idempotent retry may re-resolve the same quote for the same order_id.
            $reuseOrderId = (int) ($orderBinding['reuse_order_id'] ?? 0);
            if ($reuseOrderId <= 0 || $record->orderId === null || (int) $record->orderId !== $reuseOrderId) {
                throw LockedQuoteException::used();
            }
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
            // One financial order per quote; idempotent retries reuse via order_attempt_id.
            $this->consumeForOrder($quote, $orderId);
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
     * @param  array<string, mixed>  $orderOpts  rate-affecting order options at quote time (defaults for public)
     * @return array<string, mixed>
     */
    public function canonicalInput(DirectionExchange $direction, array $route = [], array $orderOpts = []): array
    {
        if ($route === []) {
            $route = $this->router->route($direction);
        }
        $family = (string) ($route['family'] ?? 'bestchange');
        $owner = (string) ($route['calculation_owner'] ?? $family);

        // Public fast quote locks the website default display mode (floating).
        $typeRate = array_key_exists('type_rate', $orderOpts)
            ? $this->normalizeTypeRate($orderOpts['type_rate'])
            : ((int) ($direction->is_type_rate ?? 0) === 1 ? '1' : '');
        $cityId = (int) ($orderOpts['city_id'] ?? 0);
        $selectedFees = $this->normalizeFeesList($orderOpts['selected_fees'] ?? []);
        $checkboxFees = $this->normalizeFeesList($orderOpts['checkbox_fees'] ?? []);
        $cardV = (bool) ($orderOpts['card_verification_required'] ?? false);
        $promo = $this->normalizePromo($orderOpts['promo_code'] ?? null);
        // Rate-only public quotes: amount unbound (give/receive scale from locked rate).
        $amount = $orderOpts['income_amount'] ?? null;
        $amountCanon = ($amount !== null && $amount !== '' && is_numeric((string) $amount))
            ? $this->canonicalAmount((string) $amount)
            : '';

        return [
            'direction_id' => (int) $direction->id,
            'currency_in_id' => (int) ($direction->id_currency1 ?? 0),
            'currency_out_id' => (int) ($direction->id_currency2 ?? 0),
            'pricing_family' => $family,
            'source_owner' => $owner,
            'type_rate' => $typeRate,
            'city_id' => $cityId,
            'selected_fees' => $selectedFees,
            'checkbox_fees' => $checkboxFees,
            'card_verification_required' => $cardV ? 1 : 0,
            'promo_code' => $promo,
            'income_amount' => $amountCanon,
            'amount_mode' => $amountCanon !== '' ? 'bound' : 'rate_only',
        ];
    }

    /**
     * @param  array<string, mixed>  $canonical
     */
    public function bindingHash(array $canonical): string
    {
        $copy = $canonical;
        ksort($copy);

        return hash('sha256', json_encode($copy, JSON_UNESCAPED_UNICODE));
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
            && (string) $orderBinding['pricing_family'] !== (string) ($expected['pricing_family'] ?? '')
        ) {
            throw LockedQuoteException::inputMismatch('pricing_family');
        }

        // Rate-affecting order options must match the quote issuance binding.
        // Missing type_rate on order inherits quote default (public floating).
        $expectedType = (string) ($expected['type_rate'] ?? '');
        $orderType = array_key_exists('type_rate', $orderBinding) && $orderBinding['type_rate'] !== null && $orderBinding['type_rate'] !== ''
            ? $this->normalizeTypeRate($orderBinding['type_rate'])
            : $expectedType;
        if ($orderType !== $expectedType) {
            throw LockedQuoteException::inputMismatch('type_rate');
        }
        if ((int) ($orderBinding['city_id'] ?? 0) !== (int) ($expected['city_id'] ?? 0)) {
            throw LockedQuoteException::inputMismatch('city_id');
        }
        if ($this->normalizeFeesList($orderBinding['selected_fees'] ?? []) !== (string) ($expected['selected_fees'] ?? '')) {
            throw LockedQuoteException::inputMismatch('selected_fees');
        }
        if ($this->normalizeFeesList($orderBinding['checkbox_fees'] ?? []) !== (string) ($expected['checkbox_fees'] ?? '')) {
            throw LockedQuoteException::inputMismatch('checkbox_fees');
        }
        $card = ! empty($orderBinding['card_verification_required']) ? 1 : 0;
        if ($card !== (int) ($expected['card_verification_required'] ?? 0)) {
            throw LockedQuoteException::inputMismatch('card_verification_required');
        }
        $promo = $this->normalizePromo($orderBinding['promo_code'] ?? null);
        if ($promo !== (string) ($expected['promo_code'] ?? '')) {
            throw LockedQuoteException::inputMismatch('promo_code');
        }
        if (($expected['amount_mode'] ?? 'rate_only') === 'bound') {
            $amt = $this->canonicalAmount((string) ($orderBinding['income_amount'] ?? ''));
            if ($amt === '' || $amt !== (string) ($expected['income_amount'] ?? '')) {
                throw LockedQuoteException::inputMismatch('income_amount');
            }
        }
    }

    /**
     * Mark quote one-time consumed after a successful distinct order create.
     */
    public function consumeForOrder(LockedQuoteRecord $quote, int $orderId): void
    {
        if ($quote->status === LockedQuoteRecord::STATUS_CONSUMED
            && $quote->orderId !== null
            && (int) $quote->orderId !== $orderId
        ) {
            throw LockedQuoteException::used();
        }
        $remaining = max(1, $quote->expiresAt - time());
        $updated = $quote->withStatus(
            LockedQuoteRecord::STATUS_CONSUMED,
            consumedAt: time(),
            orderId: $orderId,
        );
        $this->store->replace($updated, $remaining);
    }

    private function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function normalizeTypeRate(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if ($v === 'fixed' || $v === 0 || $v === '0' || $v === false) {
            return '0';
        }
        if ($v === 'floating' || $v === 1 || $v === '1' || $v === true) {
            return '1';
        }

        return (string) $v;
    }

    /**
     * @param  mixed  $fees
     */
    private function normalizeFeesList(mixed $fees): string
    {
        if (! is_array($fees) || $fees === []) {
            return '';
        }
        $flat = [];
        foreach ($fees as $k => $v) {
            if (is_array($v)) {
                $flat[] = json_encode($v, JSON_UNESCAPED_UNICODE);
            } else {
                $flat[] = (string) $k.':'.(string) $v;
            }
        }
        sort($flat, SORT_STRING);

        return hash('sha256', implode('|', $flat));
    }

    private function normalizePromo(mixed $code): string
    {
        if (! is_string($code)) {
            return '';
        }
        $code = mb_strtolower(trim($code));

        return $code;
    }

    private function canonicalAmount(string $amount): string
    {
        $amount = trim($amount);
        if ($amount === '' || ! is_numeric($amount)) {
            return '';
        }
        if (function_exists('bcadd')) {
            return bcadd($amount, '0', 18);
        }

        return (string) $amount;
    }

    private function formatCourseDisplay(DirectionExchange $direction, string $rate): string
    {
        if ($rate === '' || ! is_numeric($rate)) {
            return '';
        }
        try {
            $c1 = $direction->currency1->code_currency->name ?? null;
            $c2 = $direction->currency2->code_currency->name ?? null;
            if (! is_string($c1) || $c1 === '' || ! is_string($c2) || $c2 === '') {
                return $rate;
            }
            $maxDecimal = 18;
            if (function_exists('bccomp') && bccomp($rate, '1', $maxDecimal) === -1) {
                if (bccomp($rate, '0', $maxDecimal) <= 0) {
                    return sprintf('1 %s = 0 %s', $c1, $c2);
                }
                $inv = bcdiv('1', $rate, $maxDecimal);

                return sprintf('%s %s = 1 %s', rtrim(rtrim($inv, '0'), '.'), $c1, $c2);
            }

            return sprintf('1 %s = %s %s', $c1, rtrim(rtrim($rate, '0'), '.'), $c2);
        } catch (Throwable) {
            return $rate;
        }
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
