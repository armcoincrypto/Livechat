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

        $user->update(['is_frontend' => 1]);
        event(new SuccessAuthenticatorEvent($user));

        return response()->json([
            'status' => 0,
            'token' => $user->createToken('frontend')->plainTextToken,
            'user' => new SessionResource($user),
        ]);
    }
}
