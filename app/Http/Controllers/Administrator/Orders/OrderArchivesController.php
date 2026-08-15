<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Orders\OrderArchivesResources;
use App\Models\Task;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderArchivesController extends Controller
{
    /**
     * Получаем архивированные заявки
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $orders = Task::withTrashed()->where('is_archive', '=', 1)
            ->orderByDesc('id')->paginate(20);

        return response()->json([
            'items' => new OrderArchivesResources($orders)
        ]);
    }

    /**
     * Обновляем заявку и восстанавливаем
     *
     * @param int $id
     * @return JsonResponse
     */
    public function update(int $id)
    {
        Task::withTrashed()->find($id)->update(['is_archive' => 0, 'archived_at' => null]);

        return response()->json([
            'status' => 0,
            'message' => 'Заявка восстановлена'
        ]);
    }
}
