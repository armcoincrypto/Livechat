<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Orders\OrderLogs\OrderAmlLogsResources;
use App\Http\Resources\Admin\Orders\OrderLogs\OrderRequisitesLogsResources;
use App\Http\Resources\Admin\Orders\OrderLogs\OrderStatusLogsResources;
use App\Models\AMLResponseData;
use App\Models\TaskRequisiteAttached;
use App\Models\TaskStatusLog;
use Illuminate\Http\Request;

class OrderLogController extends Controller
{
    protected array $attributes_status_log = [
        'admin_order_status_log_hidden_columns',
        'admin_order_status_log_pagination'
    ];

    /**
     * Лог статусов заявок
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatusLog(Request $request)
    {
        $statusLog = TaskStatusLog::query()
            ->with(['user', 'oldStatus', 'newStatus', 'task', 'directionExchange'])
            ->filter($request->all())
            ->orderByDesc('id')
            ->paginate(50);


        return response()->json([
            'items' => new OrderStatusLogsResources($statusLog)
        ]);
    }

    /**
     * Лог AML проверок
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAmlLog(Request $request)
    {
        $logs = AMLResponseData::filter($request->all())->orderBy('id', 'desc')->paginate(20);

        return response()->json([
            'items' => new OrderAmlLogsResources($logs)
        ]);
    }

    /**
     * Лог привязанных реквизитов
     *
     * @param Request $request
     * @return OrderRequisitesLogsResources
     */
    public function getRequisitesLog(Request $request)
    {
        $logs = TaskRequisiteAttached::filter($request->all())->orderByDesc('id')->paginate(20);

        return new OrderRequisitesLogsResources($logs);
    }
}
