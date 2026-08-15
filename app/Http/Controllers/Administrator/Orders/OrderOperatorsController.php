<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Models\TaskOperator;
use App\Models\TasksOperatorLog;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class OrderOperatorsController extends Controller
{
    public function index(int $id)
    {
        $allOperators = TasksOperatorLog::where([
            ['id_task', '=', $id]
        ])->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'user' => [
                    'id' => $item->new_operator?->id,
                    'name' => $item->new_operator?->name,
                    'email' => $item->new_operator?->email,
                    'avatar' => \Str::upper(\Str::substr($item->new_operator?->name, 0, 1)),
                ],

                'created_at' => Carbon::parse($item->created_at)->diffForHumans()
            ];
        });

        return response()->json([
            'data' => $allOperators
        ]);
    }

    /**
     * Добавляем оператора к заявке
     *
     * @param int $id
     * @return JsonResponse
     */
    public function store(int $id)
    {
        $transaction = TransactionFacade::find($id);

        // Проверяем, привязан ли оператор который открывает страницу к заявке
        $operator = TaskOperator::where([
            ['id_task', $id],
        ])->get();

        // Привязываем оператора к заявке
        if ($operator->isEmpty()) {
            $operator = $operator->add($transaction->setOperator(Auth::id()));
        }

        if(!\auth()->user()->can('admin_orders_execute')) {
            return response()->json([
                'status' => 1,
                'message' => 'Вы не можете принять заявку'
            ]);
        }


        // Уникальные условия для менеджеров с ролями
        if (!in_array(Auth::id(), $operator->map->id_user->toArray())) {
            $operator = $operator->add($transaction->setOperator(Auth::id()));
        }

        //Проверяем запущен ли процесс обработки заявки
        if (! $transaction->isStart()) {
            $transaction->start();
        }

        return response()->json([
            'status' => 0,
            'message' => 'Заявка принята'
        ]);
    }

    /**
     * Удаляем оператора
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id)
    {
        // Проверяем, привязан ли оператор который открывает страницу к заявке
        $operator = TaskOperator::where([
            ['id_task', $id],
            ['id_user', \auth()->id()]
        ])->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Настройки успешно сохранены'
        ]);
    }
}
