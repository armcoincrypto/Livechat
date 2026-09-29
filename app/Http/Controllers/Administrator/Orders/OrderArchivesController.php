<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Orders\OrderArchivesResources;
use App\Models\Task;
use App\Services\Orders\Archival\OwnerConfirmedHistoricalSettlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderArchivesController extends Controller
{
    /**
     * Архивированные заявки.
     *
     * Query:
     *   filter=active|historical|owner_confirmed_settled (default historical/all archived)
     *   — active is served by the live orders list (is_archive=0), not this endpoint.
     */
    public function index(Request $request)
    {
        $filter = (string) $request->query('filter', 'historical');
        $query = Task::withTrashed()->where('is_archive', '=', 1);

        if ($filter === 'owner_confirmed_settled') {
            // JSON path filter for MySQL 5.7+/8: opt_params->>$.historical_resolution.resolution_type
            $query->whereNotNull('opt_params')
                ->where('opt_params->historical_resolution->resolution_type', OwnerConfirmedHistoricalSettlement::RESOLUTION_TYPE);
        }

        $orders = $query->orderByDesc('id')->paginate(20);

        return response()->json([
            'items' => new OrderArchivesResources($orders),
            'filter' => $filter,
            'filters_available' => ['historical', 'owner_confirmed_settled'],
        ]);
    }

    /**
     * Восстановление из архива.
     * Owner-confirmed historical settlements refuse restore (prevents re-entering money-at-risk).
     */
    public function update(int $id)
    {
        $task = Task::withTrashed()->find($id);
        if ($task === null) {
            return response()->json(['status' => 1, 'message' => 'Not found'], 404);
        }

        if (OwnerConfirmedHistoricalSettlement::isResolved(
            is_array($task->opt_params) ? $task->opt_params : null
        )) {
            return response()->json([
                'status' => 1,
                'message' => 'Owner-confirmed historically settled orders cannot be restored to the active backlog',
                'error_code' => 'owner_confirmed_historical_settlement',
            ], 409);
        }

        $task->update(['is_archive' => 0, 'archived_at' => null]);

        return response()->json([
            'status' => 0,
            'message' => 'Заявка восстановлена'
        ]);
    }
}
