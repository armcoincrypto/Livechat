<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Telegram Bot API: when setWebhook is called with secret_token, each update includes header
 * X-Telegram-Bot-Api-Secret-Token. We require a non-empty configured secret so the endpoint is not world-open.
 */
final class VerifySupportTelegramWebhookSecret
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('support_chat.enabled')) {
            throw new NotFoundHttpException;
        }

        if (! filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new NotFoundHttpException;
        }

        $secret = trim((string) config('support_chat.telegram.webhook_secret', ''));
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
