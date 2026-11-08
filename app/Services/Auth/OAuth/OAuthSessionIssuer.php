<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

use App\Events\Auth\SuccessAuthenticatorEvent;
use App\Models\User;
use iEXPackages\ExchangerClient\Http\Resources\SessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class OAuthSessionIssuer
{
    /**
     * Establish the same frontend session as password login.
     */
    public function issue(User $user, Request $request): JsonResponse
    {
        Auth::guard()->login($user);
        $request->session()->regenerate();

        // Fresh OAuth login counts as reauth for linking another provider (TTL-bound).
        app(OAuthReauthGuard::class)->markFresh($request);

        $user->update(['is_frontend' => 1]);
        event(new SuccessAuthenticatorEvent($user));

        // Cookie/session auth only — do not mint unused Sanctum PATs (orphan tokens).
        return response()->json([
            'status' => 0,
            'user' => new SessionResource($user),
        ]);
    }
}
