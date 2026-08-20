<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Fail closed: feature must be enabled and webhook secret must match.
 */
final class VerifyTelegramOperatorWebhookSecret
{
    public function handle(Request $request, Closure $next)
    {
        if (! filter_var(config('telegram_operator.actions_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new NotFoundHttpException;
        }

        $secret = trim((string) config('telegram_operator.webhook_secret', ''));
        if ($secret === '') {
            throw new NotFoundHttpException;
        }

        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if ($header === '' || ! hash_equals($secret, $header)) {
            abort(403);
        }

        return $next($request);
    }
}
