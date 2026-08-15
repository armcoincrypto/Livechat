<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Http\Controllers;

use iEXPackages\DynamicConfig\Monitoring\DynamicConfigMonitoringService;
use Illuminate\Http\JsonResponse;

/**
 * Контроллер мониторинга DynamicConfig.
 *
 * Возвращает JSON-статистику, которую затем можно красиво
 * отрисовать в админке.
 */
final class DynamicConfigMonitoringController
{
    public function __invoke(
        DynamicConfigMonitoringService $monitoringService
    ): JsonResponse {
        $summary = $monitoringService->getSummary();

        return response()->json($summary);
    }
}
