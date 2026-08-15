<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Orders\OrderRecalculationResources;
use App\Models\HistoryRecalculation;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\SimpleCache\InvalidArgumentException;

class OrderRecountController extends Controller
{

    public function index(Request $request, int $id): JsonResponse
    {
        // Проверяем наличие задачи по переданному ID
        $items = HistoryRecalculation::where('id_task', $id)->get();

        // Если записи не найдены, возвращаем понятный ответ
        if ($items->isEmpty()) {
            return response()->json([
                'status' => 1,
                'message' => 'История пересчетов не найдена для указанной заявки.',
                'data' => [],
            ], 404);
        }

        // Возвращаем структурированный ресурс
        return response()->json([
            'status' => 0,
            'data' => new OrderRecalculationResources($items),
        ]);
    }

    /**
     * Обновляем и записываем в историю
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    public function update(int $id, Request $request): JsonResponse
    {
        // Проверяем права пользователя на пересчёт
        if (!auth()->user()->can('admin_orders_id_recount')) {
            return response()->json([
                'status' => 1,
                'message' => 'У вас нет прав для пересчёта заявки.'
            ], 403);
        }

        // Ищем транзакцию по идентификатору
        $transaction = TransactionFacade::find($id);

        // Если транзакция не найдена
        if (!$transaction) {
            return response()->json([
                'status' => 1,
                'message' => 'Заявка не найдена.'
            ], 404);
        }

        // Валидация входных данных
        $validatedData = $request->validate([
            'at_rate'    => 'required|integer',
            'amount'     => 'required|numeric|min:0',
            'manualRate' => 'required|string'
        ]);

        try {
            // Пересчитываем заявку с валидными данными
            $transaction->recount(
                (int)$validatedData['at_rate'],
                (float)$validatedData['amount'],
                (string)$validatedData['manualRate']
            );
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => 1,
                'message' => 'Ошибка пересчёта: ' . $exception->getMessage()
            ], 500);
        }

        // Успешный ответ
        return response()->json([
            'status' => 0,
            'message' => 'Заявка успешно пересчитана.'
        ]);
    }
}
