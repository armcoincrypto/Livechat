<?php

namespace iEXPackages\ExchangerClient\Http\Controller\Account;

use App\Models\Currency;
use App\Models\Task;
use App\Models\TaskStatus;
use iEXPackages\ExchangerClient\Http\Resources\Orders\OrderAccountResource;
use iEXPackages\ExchangerClient\Http\Resources\Orders\OrdersUserResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OrdersController
{
    /**
     * Список всех заявок клиента
     */
    public function index(Request $request): OrdersUserResource
    {
        $items = Task::with(['task_info' => function ($q) {
            $q->select('id');
        }, 'tasks_rejection_status' => function ($q) {
            $q->select('id', 'name');
        }, 'direction_exchange' => function ($q) {
            $q->select('id', 'id_currency1', 'id_currency2');
        }, 'direction_exchange.currency1' => function ($q) {
            $q->select('id', 'number_format', 'id_payment', 'id_code_currency');
        }, 'direction_exchange.currency1.payment' => function ($q) {
            $q->select('id', 'name', 'logo', 'is_local_image');
        }, 'direction_exchange.currency1.code_currency' => function ($q) {
            $q->select('id', 'name');
        }, 'direction_exchange.currency2' => function ($q) {
            $q->select('id', 'number_format', 'id_payment', 'id_code_currency');
        }, 'direction_exchange.currency2.payment' => function ($q) {
            $q->select('id', 'name', 'logo', 'is_local_image');
        }, 'direction_exchange.currency2.code_currency' => function ($q) {
            $q->select('id', 'name');
        }, 'task_status' => function ($q) {
            $q->select('id', 'name');
        }])->select('id', 'id_direction_exchange', 'started_at', 'status', 'updated_at', 'public_id', 'id_rejection_status', 'give_price', 'receiving_price', 'from_shot', 'to_shot', 'id_user', 'created_at', 'is_from_verification_card')
            ->where('id_user', '=', $request->user()->id);

        if($request->has('status_int') and $request->status_int > 0) {
            $items = $items->where('status', '=', (int)$request->status_int);
        } else {
            $items = $items->whereIn('status', explode(',', iEXSetting('displayed_statuses')));
        }


        if($request->has('currency_int') and (int)$request->currency_int > 0) {
            $items->whereHas('direction_exchange', function (Builder $query) use ($request) {
                $query->where('id_currency1', '=', (int) $request->currency_int);
            });
        }


        $items = $items->orderBy('id', 'desc');


        return new OrdersUserResource($items->simplePaginate(10));
    }

    /**
     * Информация о заявки в личном кабинете
     *
     * @param Request $request
     * @param int $public_id
     * @return OrderAccountResource|JsonResponse
     */
    /**
     * Информация о заявке в личном кабинете
     *
     * @param Request $request
     * @param int $public_id
     * @return OrderAccountResource|JsonResponse
     */
    public function show(Request $request, int $public_id): OrderAccountResource|JsonResponse
    {
        $order = Task::where('public_id', '=', $public_id)->firstOrFail();

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $isAdmin   = $user && $user->can('allow_admin');
        $isOwner   = $user && (int) $user->id === (int) $order->id_user;
        $ipMatches = $request->ip() === $order->ip;

        // 1. Администратор всегда имеет доступ к чеку
        if ($isAdmin) {
            return new OrderAccountResource($order);
        }

        // 2. Режимы отображения чека заявки
        $mode = (int) iEXSetting('disable_check_display');

        $accessGranted = false;

        switch ($mode) {
            // 0 — чек доступен всем по ссылке (без ограничений)
            case 0:
                $accessGranted = true;
                break;

            // 1 — только владелец заявки и с того же IP-адреса, с которого она создавалась
            case 1:
                $accessGranted = $isOwner && $ipMatches;
                break;

            // 2 — только владелец (IP не важен)
            case 2:
                $accessGranted = $isOwner;
                break;

            // 3 — только авторизованный владелец заявки (акцент на личный кабинет)
            case 3:
                // $isOwner уже подразумевает авторизованного пользователя
                $accessGranted = $isOwner;
                break;

            // 8 — временный доступ 24 часа всем по ссылке,
            //      после истечения срока — только авторизованному владельцу
            case 8:
                // Если это владелец заявки, он всегда имеет доступ, независимо от времени
                if ($isOwner) {
                    $accessGranted = true;
                    break;
                }

                // Для остальных: проверяем временное окно по view_expires_at
                $expiresAt = $order->view_expires_at;

                if ($expiresAt === null) {
                    // Первое открытие чека "снаружи" — запускаем окно в 24 часа
                    $order->view_expires_at = now()->addDay();
                    $order->save();

                    $accessGranted = true;
                } else {
                    // Если срок ещё не истёк — даём доступ
                    $accessGranted = now()->lessThanOrEqualTo($expiresAt);
                }

                break;

            // Дефолт — максимально строгий вариант: только владелец и тот же IP
            default:
                $accessGranted = $isOwner && $ipMatches;
                break;
        }

        if ($accessGranted) {
            return new OrderAccountResource($order);
        }

        return response()->json([
            'isErrorIp' => 1,
        ]);
    }

    /**
     * Настройки заявок
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function filterOptions(Request $request): JsonResponse
    {
        $statuses = cache()->remember(
            'account-order-settings-statuses-' . app()->getLocale(),
            Carbon::now()->addMinutes(10),
            function () {
                return TaskStatus::whereIn('id', explode(',', iEXSetting('displayed_statuses')))
                    ->orderBy('id')
                    ->get()
                    ->map(function($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->name,
                        ];
                    })
                    ->values()
                    ->toArray();
            }
        );

        $currencies = cache()->remember(
            'account-order-settings-currencies-' . app()->getLocale(),
            Carbon::now()->addMinutes(10),
            function () {
                return Currency::where('status', 0)
                    ->get()
                    ->map(function($item) {
                        $name = trim(($item->payment?->name ?? '') . ' ' . ($item->code_currency?->name ?? ''));
                        if (!$name) {
                            return null;
                        }
                        return [
                            'id' => $item->id,
                            'name' => $name,
                        ];
                    })
                    ->filter() // Убираем null-ы
                    ->values()
                    ->toArray();
            }
        );

        return response()->json([
            'statuses' => $statuses,
            'currencies' => $currencies
        ]);
    }
}
