<?php

declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Support;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Possession / ownership gate for order-scoped card verification endpoints.
 *
 * Authorization contexts (in order):
 * 1. Admin with allow_admin
 * 2. Authenticated non-guest owner (user id match)
 * 3. Authenticated shared guest owner + creation-IP match
 * 4. Unauthenticated / guest: creation-IP match and/or unique_security_code
 *
 * public_id alone is never sufficient. Wrong authenticated users are always denied.
 */
final class AssertOrderVerificationAccess
{
    public static function allows(Request $request, Task $order): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        $ipMatches = (string) $request->ip() === (string) $order->ip;
        $codeMatches = self::securityCodeMatches($request, $order);
        $requireCode = (bool) config('verification.card_order_require_security_code', false);

        if ($user && method_exists($user, 'can') && $user->can('allow_admin')) {
            return true;
        }

        if ($user) {
            $isOwner = (int) $user->id === (int) $order->id_user;
            if (!$isOwner) {
                return false;
            }

            // Shared guest account row: require possession (IP and/or security code).
            if ((int) ($user->is_guest ?? 0) === 1) {
                return self::guestPossessionAllows($ipMatches, $codeMatches, $requireCode, $request);
            }

            return true;
        }

        // Unauthenticated guest order link holder.
        return self::guestPossessionAllows($ipMatches, $codeMatches, $requireCode, $request);
    }

    /**
     * Optional order capability: unique_security_code (51-char server secret).
     * Accepted from body/query or X-Order-Security-Code header.
     * If a non-empty code is supplied and wrong → deny (even if IP matches).
     */
    public static function securityCodeMatches(Request $request, Task $order): bool
    {
        $provided = self::extractSecurityCode($request);
        $expected = (string) ($order->unique_security_code ?? '');

        if ($provided === '' || $expected === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    public static function extractSecurityCode(Request $request): string
    {
        $fromHeader = (string) $request->header('X-Order-Security-Code', '');
        $fromInput = (string) $request->input('unique_security_code', $request->input('security_code', ''));
        $raw = $fromHeader !== '' ? $fromHeader : $fromInput;

        return trim($raw);
    }

    private static function guestPossessionAllows(
        bool $ipMatches,
        bool $codeMatches,
        bool $requireCode,
        Request $request,
    ): bool {
        $provided = self::extractSecurityCode($request);

        // Explicit wrong code must not be bypassed by IP.
        if ($provided !== '' && !$codeMatches) {
            return false;
        }

        if ($requireCode) {
            return $codeMatches;
        }

        return $codeMatches || $ipMatches;
    }

    public static function denyResponse(): JsonResponse
    {
        // Non-enumerating generic denial (same shape for unknown / foreign / unauthorized).
        return response()->json([
            'message' => 'Forbidden',
            'status' => 1,
        ], 403);
    }
}
