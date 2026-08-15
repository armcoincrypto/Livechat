<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Vue;

use App\Http\Controllers\Controller;
use App\Services\Administrator\Notify\AdminNotifyCountsService;
use Illuminate\Http\JsonResponse;

final class NotificationVueController extends Controller
{
    /**
     * getNotify
     *
     * Возвращает данные для верхней панели админки:
     * - статус работы сайта (online/offline)
     * - счётчики уведомлений
     *
     * Важно:
     * - Статус берём через единую точку входа `work_is_online()`.
     *   Если подключён WorkStatusMiddleware — значение уже вычислено один раз на запрос.
     *
     * @param AdminNotifyCountsService $service Сервис формирования счётчиков уведомлений.
     *
     * @return JsonResponse
     */
    public function getNotify(
        AdminNotifyCountsService $service,
    ): JsonResponse {
        $user = auth()->user();

        return response()->json([
            'status_site' => work_is_online(),
            'counts' => $service->build($user),
        ]);
    }
}
