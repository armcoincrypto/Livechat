<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Models\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrdersStatusController extends Controller
{
    /**
     * Главная страница
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $today = now()->toDateString();
        $year = now()->year;
        $month = now()->month;

        $statuses = TaskStatus::query()
            ->withCount([
                'tasks',
                'tasks as tasks_today_count' => function ($q) use ($today) {
                    $q->whereDate('created_at', $today);
                },
                'tasks as tasks_month_count' => function ($q) use ($year, $month) {
                    $q->whereYear('created_at', $year)->whereMonth('created_at', $month);
                },
            ])
            ->orderBy('sorting')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'count' => $item->tasks_count,
                    'today_count' => $item->tasks_today_count,
                    'month_count' => $item->tasks_month_count,
                ];
            });

        return response()->json([
            'data' => $statuses,
            'total' => $statuses->count(),
        ]);
    }

    public function edit(int $id)
    {
        $status = TaskStatus::find($id);

        return response()->json([
            'id' => $status->id,
            'name' => $status->getTranslations('name'),
        ]);
    }

    /**
     * Обработка и обновления записи
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $status = TaskStatus::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $status->update([
            'name' => $request->name
        ]);

        return response()->json([
            'status' => 0,
            'message' => $status->name . ' успешно обновлен'
        ]);
    }
}
