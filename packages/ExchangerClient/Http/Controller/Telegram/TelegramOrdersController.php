<?php

namespace iEXPackages\ExchangerClient\Http\Controller\Telegram;

use App\Models\Task;
use iEXPackages\ExchangerClient\Http\Resources\Orders\OrdersUserResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TelegramOrdersController
{
    /**
     * Список всех заявок клиента
     */
    public function index(Request $request): OrdersUserResource
    {
        $items = Task::with([
            'task_info' => function ($q) {
                $q->select('id');
            },
            'tasks_rejection_status' => function ($q) {
                $q->select('id', 'name');
            },
            'direction_exchange' => function ($q) {
                $q->select('id', 'id_currency1', 'id_currency2');
            },
            'direction_exchange.currency1' => function ($q) {
                $q->select('id', 'number_format', 'id_payment', 'id_code_currency');
            },
            'direction_exchange.currency1.payment' => function ($q) {
                $q->select('id', 'name', 'logo', 'is_local_image');
            },
            'direction_exchange.currency1.code_currency' => function ($q) {
                $q->select('id', 'name');
            },
            'direction_exchange.currency2' => function ($q) {
                $q->select('id', 'number_format', 'id_payment', 'id_code_currency');
            },
            'direction_exchange.currency2.payment' => function ($q) {
                $q->select('id', 'name', 'logo', 'is_local_image');
            },
            'direction_exchange.currency2.code_currency' => function ($q) {
                $q->select('id', 'name');
            },
            'task_status' => function ($q) {
                $q->select('id', 'name');
            },
        ])
            ->select(
                'id',
                'id_direction_exchange',
                'started_at',
                'status',
                'updated_at',
                'public_id',
                'id_rejection_status',
                'give_price',
                'receiving_price',
                'from_shot',
                'to_shot',
                'id_user',
                'created_at'
            );

        $items = $items->whereIn('status', explode(',', (string) iEXSetting('displayed_statuses')));

        $telegramId = (int) $request->input('telegram_id', 0);

        // Если пришёл telegram_id — считаем, что это режим Telegram Mini App и фильтруем по meta.telegram_id.
        // В этом режиме НЕ ограничиваем по id_user, потому что заявка может не быть привязана к пользователю сайта.
        if ($telegramId > 0) {
            $items->whereHas('meta', function (Builder $query) use ($telegramId) {
                $query->where('telegram_id', '=', $telegramId);
            });
        } else {
            // Обычный режим (личный кабинет сайта): фильтрация по авторизованному пользователю.
            $user = $request->user();
            abort_if($user === null, 401);
            $items->where('id_user', '=', $user->id);
        }

        $items = $items->orderBy('id', 'desc');

        return new OrdersUserResource($items->simplePaginate(10));
    }
}
