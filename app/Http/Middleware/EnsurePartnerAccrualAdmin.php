<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Partner accrual admin gate.
 * View: admin_users | admin_partner_accrual_view | admin_partner_accrual_manage
 * Manage: admin_users | admin_partner_accrual_manage
 */
final class EnsurePartnerAccrualAdmin
{
    public function handle(Request $request, Closure $next, string $ability = 'view'): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['status' => 1, 'code' => 'UNAUTHORIZED', 'message' => 'Unauthorized'], 401);
        }

        $manage = $ability === 'manage';
        $allowed = $manage
            ? ($user->can('admin_partner_accrual_manage') || $user->can('admin_users'))
            : (
                $user->can('admin_partner_accrual_view')
                || $user->can('admin_partner_accrual_manage')
                || $user->can('admin_users')
            );

        if (! $allowed) {
            return response()->json([
                'status' => 1,
                'code' => 'FORBIDDEN',
                'message' => 'Недостаточно прав для партнёрского начисления',
            ], 403);
        }

        return $next($request);
    }
}
