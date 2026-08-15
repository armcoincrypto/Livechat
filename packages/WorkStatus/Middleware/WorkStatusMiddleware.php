<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus\Middleware;

use Closure;
use Illuminate\Http\Request;
use iEXPackages\WorkStatus\Services\WorkStatusService;

/**
 * WorkStatusMiddleware
 *
 * Вычисляет статус один раз на запрос и кладёт его в Request::attributes:
 * - key: "work_status"
 *
 * Зачем:
 * - чтобы не дергать сервис в каждом контроллере;
 * - чтобы дальше по цепочке использовать одно и то же значение (консистентность).
 */
final class WorkStatusMiddleware
{
    public function __construct(
        private readonly WorkStatusService $workStatus,
    ) {}

    /**
     * @param Closure(Request): mixed $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('work_status', $this->workStatus->getStatus());
        return $next($request);
    }
}
