<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Vue;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\VerificationCard;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class OrderVueController extends Controller
{
    /**
     * Настройки для Live режима
     */
    public function liveOrderSettings(Request $request)
    {
        $settings = [
            'iex_order_live_auto_update_page',
            'iex_order_live_auto_update_timeout',
            'iex_order_live_limit',
            'iex_order_live_sound_notification',
            'iex_order_live_statuses',
            'iex_order_live_view_page',
            'iex_order_is_hidden_order_opened',
            'iex_order_live_is_request_payment',
        ];

        // Обновление конфига
        $array = [];
        foreach ($settings as $item) {
            if ($request->has($item) and is_array($request->get($item))) {
                $array[$item] = implode(',', array_filter($request->get($item), function($a) { return ($a !== 0); }));
            } else {
                $array[$item] = ($request->has($item) ? $request->get($item) : null);
            }
        }
        iEXSetting($array);

        return Response::json([
            'status' => 0,
            'message' => __('Настройки успешно сохранены'),
        ]);
    }

    /**
     * Получаем настройки
     *
     * @return array
     */
    public function getLiveOrderSettings(Request $request)
    {
        if($request->has('withStatus'))
        {
            // Список всех статусов
            $taskStatuses = TaskStatus::pluck('name', 'id')->map(function($value, $key) {
                return [
                    'id' => $key,
                    'value' => $value
                ];
            })->values();
        }

        return [
            'all_statuses' => $taskStatuses ?? [],
            'options' => [
                'auto_update_page' => (int)iEXSetting('iex_order_live_auto_update_page', 0),
                'auto_update_timeout' => (int)iEXSetting('iex_order_live_auto_update_timeout', 10),
                'live_limit' => (int)iEXSetting('iex_order_live_limit', (int) 100),
                'sound_notification' => (int)iEXSetting('iex_order_live_sound_notification', 0),
                'available_statuses'   =>  array_map('intval', array_filter(explode(',', iEXSetting('iex_order_live_statuses')))),
                'view_page'  => (int)iEXSetting('iex_order_live_view_page', 30),
                'is_hidden_order_opened' => (int)iEXSetting('iex_order_is_hidden_order_opened', 0),
                'is_request_payment' => (int)iEXSetting('iex_order_live_is_request_payment', 0)
            ]
        ];
    }

    /**
     * Get live orders for the Online mode.
     *
     * Returns a JSON response containing live orders and system status.
     * The `status_site` field is determined via the unified `work_is_online()` helper.
     *
     * @return JsonResponse
     */
    public function liveOrders()
    {
        // Статусы для Live
        $liveStatus = explode(',', iEXSetting('iex_order_live_statuses'));

        // Получаем список статусов
        $orderStatuses = TaskStatus::select('id', 'name', 'class', 'sorting')->get()->keyBy('id');

        // Получаем лимит для live-режима
        $liveLimit = (int) iEXSetting('iex_order_live_limit', (int) iEXSetting('iex_order_live_view_page', 30));
        if ($liveLimit <= 0) {
            $liveLimit = 30;
        }

        // Получаем последний ID
        $list = Task::query()->with(['direction_exchange' => function ($q) {
            $q->select('id', 'id_currency1', 'id_currency2');
        }, 'direction_exchange.currency1' => function ($q) {
            $q->select('id', 'id_code_currency', 'id_payment', 'number_format');
        }, 'direction_exchange.currency1.code_currency' => function ($q) {
            $q->select('id', 'name');
        }, 'direction_exchange.currency1.payment' => function ($q) {
            $q->select('id', 'name');
        }, 'direction_exchange.currency2' => function ($q) {
            $q->select('id', 'id_code_currency', 'id_payment', 'number_format');
        }, 'direction_exchange.currency2.code_currency' => function ($q) {
            $q->select('id', 'name');
        }, 'direction_exchange.currency2.payment' => function ($q) {
            $q->select('id', 'name');
        }, 'task_info' => function ($q) {
            $q->select('id', 'is_freeze_scam');
        }, 'task_messages' => function ($q) {
            $q->select('id', 'is_view')->where('is_view', '=', 0);
        }, 'task_operators' => function ($q) {
            $q->select('id', 'id_user', 'id_task');
        }, 'task_operators.user' => function ($q) {
            $q->select('id', 'name', 'email');
        }])
            ->select('id', 'public_id', 'created_at', 'is_frozen', 'status', 'id_direction_exchange', 'in_flow_funds', 'give_price', 'started_at', 'receiving_price')
            ->orderBy('id', 'desc')->where('is_frozen', 0)
            ->where('is_archive', '=', 0)
            ->whereIn('status', $liveStatus)->limit($liveLimit);

        if ((int) iEXSetting('iex_order_live_is_request_payment', 0) == 1) {
            $list->orWhere('status', '=', 2)->where('is_request_payment_type', '=', 1);
        }

        if ((int) iEXSetting('iex_order_is_hidden_order_opened') == 1) {
            $list->has('task_operators', '=', 0);
        }

        $orderSingleGet = $list->get();

        $orderGroups = $orderSingleGet->groupBy(function ($item) {
            return $item->status;
        });

        $orders = [];
        $user_id = Auth::id();
        foreach ($orderGroups as $key => $orderGroup) {

            $orders[$key] = [
                'id' => $key,
                'class' => $orderStatuses[$key]['class'],
                'name' => $orderStatuses[$key]['name'],
                'sort' => $orderStatuses[$key]['sorting'],
                'count' => count($orderGroup),
                'items' => [],
            ];

            foreach ($orderGroup as $value) {
                $return = [];
                $return['auth_id'] = $user_id;
                $return['in_flow_funds'] = $value['in_flow_funds'];
                $return['amount'] = display_in_price_auto($value, 'give_price', true, true).' '.$value['direction_exchange']['currency1']['code_currency']['name'].' → '.
                    display_out_price_auto($value, 'receiving_price', false, true).' '.$value['direction_exchange']['currency2']['code_currency']['name'];
                $return['status_int'] = $value['status'];
                $return['id'] = $value['id'];
                $return['public_id'] = $value['public_id'] > 0 ? $value['public_id'] : $value['id'];
                $return['name'] = $value['direction_exchange']['currency1']['payment']['name'].' '.$value['direction_exchange']['currency1']['code_currency']['name'].' → '.
                    $value['direction_exchange']['currency2']['payment']['name'].' '.$value['direction_exchange']['currency2']['code_currency']['name'];
                $return['created'] = Carbon::parse($value['created_at'])->diffForHumans();
                $return['hidden'] = false;
                $return['is_freeze_scam'] = (isset($value['task_info']) and $value['task_info']['is_freeze_scam'] == 1);
                $return['payment_in_name'] = $value['direction_exchange']['currency1']['payment']['name'];

                // Получение операторов
                $return['operators'] = [];
                if (isset($value->task_operators) and count($value->task_operators) > 0) {
                    foreach ($value->task_operators as $operator) {
                        $return['operators'][] = [
                            'id' => $operator->user->id,
                            'name' => $operator->user->name,
                            'email' => $operator->user->email
                        ];
                    }
                }

                $orders[$key]['items'][] = $return;
            }
        }

        $counts = [
            'count_orders' => count($orderSingleGet),
            'count_verifications' => VerificationCard::where('status', '=', 0)->count()
        ];

        array_walk_recursive($counts, function ($item, $key) use (&$final) {
            $final[$key] = isset($final[$key]) ? $item + $final[$key] : $item;
        });

        $lastId = $orderSingleGet->first();

        $array['status_site'] = work_is_online();
        $array['counts'] = $counts;
        $array['last_id'] = isset($lastId) ? $lastId['id'] : 0;
        $array['settings'] = [
            'auto_update_page' => (int) iEXSetting('iex_order_live_auto_update_page', 0),
            'auto_update_timeout' => (int) iEXSetting('iex_order_live_auto_update_timeout', 10),
            'live_limit' => $liveLimit,
            'sound_notification' => (int) iEXSetting('iex_order_live_sound_notification', 0),
        ];
        $array['orders'] = collect($orders)->sortBy('sort')->values();


        return response()->json($array);
    }

    /**
     * @deprecated
     * Returns counts for header display. Uses the unified `work_is_online()` helper for status.
     */
    public function headerCounts()
    {
        // Статусы для Live
        $liveStatus = explode(',', iEXSetting('iex_order_live_statuses'));
        $liveCount = Task::select('id', 'status')->with(['task_operators'])->whereIn('status', $liveStatus);

        if ((int) iEXSetting('iex_order_live_is_request_payment', 0) == 1) {
            $liveCount->orWhere('status', '=', 2)->where('is_request_payment_type', '=', 1);
        }

        if ((int) iEXSetting('iex_order_is_hidden_order_opened') == 1) {
            $liveCount->has('task_operators', '=', 0);
        }

        // Все заявки
        $allCounts = Task::query()->count();
        // Замороженные заявки
        $allFrozenCounts = Task::select('id' ,'status')->where('status' , '=', 8)->count();

        $counts = [
            'count_orders' => [
                'live_orders' => $liveCount->count(),
                'all_orders' => $allCounts,
                'frozen_counts' => $allFrozenCounts
            ],
            'count_verifications' => VerificationCard::where('status', '=', 0)->count()
        ];

        $array['status_site'] = work_is_online();
        $array['counts'] = $counts;

        return $array;
    }

    /**
     * Обработчик заявок
     */
    public function handlerOrder(int $id, Request $request)
    {
        $otherFieldsForSuccess = collect($request->input('extra_fields', []))
            ->take(5)
            ->map(function ($item) {
                return [
                    'name'  => trim((string) ($item['name'] ?? '')),
                    'value' => trim((string) ($item['value'] ?? '')),
                ];
            })
            ->filter(fn (array $item) => $item['name'] !== '' && $item['value'] !== '')
            ->values()
            ->toArray();

        $transaction = TransactionFacade::find($request->id, [
            'preview' => false,
            'commission' => ($request->manual_fee ?? 0),
            'type' => ($request->type ?? 'default'),
            'actions' => ($request->action ?? null),
            'message' => ($request->message_success ?? null),
            'otherFieldsForSuccess' => $otherFieldsForSuccess,
            'rejection_status' => ($request->rejection_status ?? 0),
            'pin_code' => $request->has('pin_code') ? $request->get('pin_code') : null,
        ]);


        $response = [];
        $response['status'] = 0;
        try {
            if ($transaction->hasAction() == 'success') {
                $transaction->success();
                $response['message'] = 'Заявка выполнена';
            } elseif ($transaction->hasAction() == 'failed') {
                $transaction->reject();
                $response['message'] = 'Заявка отклонена';
            } elseif ($transaction->hasAction() == 'defer') {
                $transaction->setDeferType($request->defer_type);
                $transaction->defer();
                $response['message'] = 'Заявка отложена';
            }

        } catch (\Exception $exception) {
            $response['status'] = 1;
            $response['message'] = $exception->getMessage();
        }

        return Response::json($response);
    }

    /**
     * Проверяем поступление средств
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function checkOrderPayment(int $id, Request $request)
    {
        return response()->json(
            TransactionFacade::call($id)->checkInPayment($request->all())
        );
    }
}
