<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use App\Services\PartnerAccrual\Exceptions\InvalidScheduleSeed;
use Illuminate\Support\Facades\Config;

/**
 * Binds preview RNG nonce to operator context so create cannot silently
 * regenerate a different schedule. Opaque schedule_seed for admin transport.
 *
 * Token format: v1.{payload_b64url}.{hmac_b64url}
 * payload JSON: n(nonce), u(user_id), t(total scale8), s(start), e(end), c(currency),
 *               v(variance), min, max
 */
final class PartnerAccrualScheduleSeedBinder
{
    public const VERSION = 'v1';

    /**
     * @param  array{
     *   user_id: int,
     *   total_amount: string,
     *   start_date: string,
     *   end_date: string,
     *   currency_code?: string,
     *   variance_percent?: int|null,
     *   min_installment?: string|null,
     *   max_installment?: string|null,
     *   rng_nonce?: string|null
     * }  $context
     */
    public function issue(array $context): string
    {
        $nonce = (string) ($context['rng_nonce'] ?? '');
        if ($nonce === '') {
            $nonce = bin2hex(random_bytes(16));
        }
        $payload = [
            'n' => $nonce,
            'u' => (int) $context['user_id'],
            't' => AccrualMoney::scale8(AccrualMoney::of((string) $context['total_amount'])),
            's' => (string) $context['start_date'],
            'e' => (string) $context['end_date'],
            'c' => strtoupper(trim((string) ($context['currency_code'] ?? PartnerAccrualCurrency::V1_CODE))),
            'v' => $context['variance_percent'] ?? null,
            'min' => $this->normOptionalMoney($context['min_installment'] ?? null),
            'max' => $this->normOptionalMoney($context['max_installment'] ?? null),
        ];
        $body = $this->b64(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = $this->b64(hash_hmac('sha256', self::VERSION.'.'.$body, $this->key(), true));

        return self::VERSION.'.'.$body.'.'.$sig;
    }

    /**
     * Validate token against create request context; return RNG nonce for generator.
     *
     * @param  array{
     *   user_id: int,
     *   total_amount: string,
     *   start_date: string,
     *   end_date: string,
     *   currency_code?: string,
     *   variance_percent?: int|null,
     *   min_installment?: string|null,
     *   max_installment?: string|null
     * }  $context
     */
    public function assertMatches(string $token, array $context): string
    {
        $token = trim($token);
        if ($token === '') {
            throw InvalidScheduleSeed::message('missing');
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
            throw InvalidScheduleSeed::message('malformed');
        }
        [$ver, $body, $sig] = $parts;
        $expected = $this->b64(hash_hmac('sha256', $ver.'.'.$body, $this->key(), true));
        if (! hash_equals($expected, $sig)) {
            throw InvalidScheduleSeed::message('signature mismatch (tampered or wrong key)');
        }
        $json = $this->ub64($body);
        $payload = json_decode($json, true);
        if (! is_array($payload) || ! isset($payload['n'], $payload['u'], $payload['t'], $payload['s'], $payload['e'], $payload['c'])) {
            throw InvalidScheduleSeed::message('payload incomplete');
        }

        $wantUser = (int) $context['user_id'];
        $wantTotal = AccrualMoney::scale8(AccrualMoney::of((string) $context['total_amount']));
        $wantStart = (string) $context['start_date'];
        $wantEnd = (string) $context['end_date'];
        $wantCur = strtoupper(trim((string) ($context['currency_code'] ?? PartnerAccrualCurrency::V1_CODE)));
        $wantVar = $context['variance_percent'] ?? null;
        $wantMin = $this->normOptionalMoney($context['min_installment'] ?? null);
        $wantMax = $this->normOptionalMoney($context['max_installment'] ?? null);

        if ((int) $payload['u'] !== $wantUser) {
            throw InvalidScheduleSeed::message('user mismatch — re-preview required');
        }
        if ((string) $payload['t'] !== $wantTotal) {
            throw InvalidScheduleSeed::message('total mismatch — re-preview required');
        }
        if ((string) $payload['s'] !== $wantStart || (string) $payload['e'] !== $wantEnd) {
            throw InvalidScheduleSeed::message('period mismatch — re-preview required');
        }
        if ((string) $payload['c'] !== $wantCur) {
            throw InvalidScheduleSeed::message('currency mismatch — re-preview required');
        }
        if (($payload['v'] ?? null) != $wantVar) {
            throw InvalidScheduleSeed::message('variance mismatch — re-preview required');
        }
        if (($payload['min'] ?? null) !== $wantMin || ($payload['max'] ?? null) !== $wantMax) {
            throw InvalidScheduleSeed::message('installment bounds mismatch — re-preview required');
        }

        $nonce = (string) $payload['n'];
        // Same charset as legacy raw RNG nonces / hex nonces issued by preview.
        if ($nonce === '' || ! preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $nonce)) {
            throw InvalidScheduleSeed::message('invalid nonce');
        }

        return $nonce;
    }


    /**
     * Resolve RNG nonce for create/preview.
     * Production: requires signed v1 token bound to context.
     * Testing only: bare hex/nonce strings allowed for legacy unit fixtures.
     *
     * @param  array{
     *   user_id: int,
     *   total_amount: string,
     *   start_date: string,
     *   end_date: string,
     *   currency_code?: string,
     *   variance_percent?: int|null,
     *   min_installment?: string|null,
     *   max_installment?: string|null
     * }  $context
     */
    public function resolveRngNonce(string $token, array $context): string
    {
        $token = trim($token);
        if ($token === '') {
            throw InvalidScheduleSeed::message('missing');
        }
        if (str_starts_with($token, self::VERSION.'.')) {
            return $this->assertMatches($token, $context);
        }

        $allowRaw = (bool) Config::get('partner_accrual.allow_unbound_schedule_seed', false)
            || app()->environment('testing');
        if (! $allowRaw) {
            throw InvalidScheduleSeed::message('unbound seed rejected — preview then create with signed schedule_seed');
        }
        if (! preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $token)) {
            throw InvalidScheduleSeed::message('invalid raw nonce');
        }

        return $token;
    }

    private function key(): string
    {
        $appKey = (string) Config::get('app.key', '');
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            if (is_string($decoded) && $decoded !== '') {
                $appKey = $decoded;
            }
        }
        if ($appKey === '') {
            // Fail closed — never issue/accept unbound seeds.
            throw InvalidScheduleSeed::message('signing key unavailable');
        }

        return hash('sha256', 'partner-accrual-schedule-seed|'.$appKey, true);
    }

    private function normOptionalMoney(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return AccrualMoney::scale8(AccrualMoney::of((string) $v));
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function ub64(string $b64): string
    {
        $pad = 4 - (strlen($b64) % 4);
        if ($pad < 4) {
            $b64 .= str_repeat('=', $pad);
        }
        $raw = base64_decode(strtr($b64, '-_', '+/'), true);
        if ($raw === false) {
            throw InvalidScheduleSeed::message('payload encoding');
        }

        return $raw;
    }
}
