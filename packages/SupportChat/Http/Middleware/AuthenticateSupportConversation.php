<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Middleware;

use App\Models\SupportConversation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateSupportConversation
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $uuid = (string) $request->route('uuid');
        if ($uuid === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $hash = hash('sha256', $token);

        $conversation = SupportConversation::query()
            ->where('uuid', $uuid)
            ->where('access_token_hash', $hash)
            ->first();

        if ($conversation === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('support_conversation', $conversation);

        return $next($request);
    }
}
