<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When attachments are disabled, routes behave as if they do not exist (no storage path leakage).
 */
final class EnsureSupportAttachmentsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! filter_var(config('support_chat.attachments.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return $next($request);
    }
}
