<?php

namespace iEXPackages\Analytics\Http\Controllers\Partners;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Bonuses\ReferralListResources;
use iEXPackages\ReferralSystem\Models\ReferralRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralRelationsController extends Controller
{
    /**
     * Список рефералов + базовые метрики по каждому
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReferralRelationship::query()
            ->with([
                'user',
                'referralLink.user',
            ])
            ->withCount([
                'tasks as tasks_total_count',
                'completedTasks as tasks_completed_count',
            ]);

        // Фильтры
        if ($request->filled('referrer_id')) {
            $query->whereHas('referralLink', function ($q) use ($request) {
                $q->where('user_id', $request->integer('referrer_id'));
            });
        }

        if ($request->filled('has_completed')) {
            $hasCompleted = (bool) $request->get('has_completed');
            $query->when($hasCompleted, function ($q) {
                $q->whereHas('completedTasks');
            }, function ($q) {
                $q->whereDoesntHave('completedTasks');
            });
        }

        // Диапазон дат: поддерживаем и date_from/date_to, и from/to
        $from = $request->get('date_from', $request->get('from'));
        $to = $request->get('date_to', $request->get('to'));

        if (! empty($from)) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (! empty($to)) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Сортировка
        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'desc');

        // Белый список сортируемых полей
        $allowedSort = [
            'id',
            'created_at',
            'tasks_total_count',
            'tasks_completed_count',
        ];

        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'id';
        }

        $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');

        $referrals = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'items' => new ReferralListResources($referrals),
        ]);
    }
}
