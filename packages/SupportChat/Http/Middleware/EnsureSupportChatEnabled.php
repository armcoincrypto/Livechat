<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EnsureSupportChatEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('support_chat.enabled')) {
            throw new NotFoundHttpException;
        }

        return $next($request);
    }
}
