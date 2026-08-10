<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Enums\TaskStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Orders\OrderIdResource;
use App\Http\Resources\Admin\Orders\OrdersLiveResources;
use App\Http\Resources\Admin\Orders\OrdersResources;
use App\Models\AMLResponseData;
use App\Models\CodeCurrency;
use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Models\FilterCurrency;
use App\Models\Payment;
use App\Models\PendingOrderStatus;
use App\Models\ReserveLedger;
use App\Models\Task;
use App\Models\TaskOperator;
use App\Models\TaskRejectionStatus;
use App\Models\TasksOperatorLog;
use App\Models\TaskStatus;
use iEXPackages\Calculator\CalculatorFacade;
use iEXPackages\Transaction\Facades\TransactionFacade;
use iEXPackages\Transaction\Services\OrderProfitCalculator;
use iEXPackages\Transaction\Services\OrderRateDiagnosticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Psr\SimpleCache\InvalidArgumentException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;


class OrdersController extends Controller
{
    /**
     * Сортировки, которые буду записываться в локальное хранилище
     */
    protected array $allowSorting = [
        'sorting_order_id',
        'sorting_order_status',
        'sorting_order_created_at',
        'sorting_order_updated_at',
        'sorting_order_amount',
    ];

    /**
     * Дополнительные фильтры
     */
    protected array $allowFiltered = [
        'show_task_page',
        'max_time_task',
        'is_order_view_formatted_amount',
        'ids_order_page_hidden_statuses',
        'is_order_hidden_without_trashed',
    ];

    /**
     * Доступные варианты пагинации
     */
    protected array $allowedPerPage = [20, 30, 50, 100];

    public function index(Request $request)
    {
        // Лимит заявок для Live (только iex_order_live_limit)
        $liveLimit = (int) iEXSetting('iex_order_live_limit', 100);
        if ($liveLimit <= 0) {
            $liveLimit = 100;
        }
        if ($liveLimit > 800) {
            $liveLimit = 800;
        }

        if($request->has('isLoadingStatuses'))
        {
            $taskStatuses = TaskStatus::pluck('name', 'id')->map(function($value, $id) {
                return [
                    'id' => $id,
                    'value' => $value
                ];
            })->values();

            return response()->json($taskStatuses);
        }

        if($request->has('isLoadingFilters'))
        {
            if($request->get('isLoadingFilters') == 'payment') {
                $payments = Payment::pluck('name', 'id')->map(function ($value, $key) {
                    return [
                        'id' => $key,
                        'value' => $value
                    ];
                })->values();

                return response()->json($payments);
            }

            if($request->get('isLoadingFilters') == 'code_currency') {
                $code_currencies = CodeCurrency::select('name', 'id')->pluck('name', 'id')->map(function ($value, $key) {
                    return [
                        'id' => $key,
                        'value' => $value
                    ];
                })->values();

                return response()->json($code_currencies);
            }

            if($request->get('isLoadingFilters') == 'currencies') {
                $currencies = Currency::select('id', 'tech_name', 'status')->where('status', '=', 0)
                    ->pluck('tech_name', 'id')->map(function ($value, $key) {
                        return [
                            'id' => $key,
                            'value' => $value
                        ];
                    })->values();

                return response()->json($currencies);
            }

            if($request->get('isLoadingFilters') == 'direction') {
                $direction = DirectionExchange::select('id', 'tech_name', 'status')->active()
                    ->pluck('tech_name', 'id')->map(function ($value, $key) {
                        return [
                            'id' => $key,
                            'value' => $value
                        ];
                    })->values();

                return response()->json($direction);
            }

            if($request->get('isLoadingFilters') == 'filters') {
                $filter_currency = FilterCurrency::pluck('name', 'id')->map(function ($value, $key) {
                    return [
                        'id' => $key,
                        'value' => $value
                    ];
                })->values();

                return response()->json($filter_currency);
            }

            return response()->json([]);
        }


        // Информация об операторе
        $user_info = $request->user();

        $requestAll = collect($request->all())->filter()->all();

        $orders = Task::has('task_info')
            ->with([
                'meta' => function ($query) {
                    $query->select('task_id', 'selected_fees','selected_fee_id', 'direction_selected_fee_id', 'selected_fee_type');
                },

                'task_info:id_task,country_name,city_name,language',
                'user:id,name,email,language',
                'merchant',
                // Historical orders may reference soft-deleted (retired) directions.
                'direction_exchange' => function ($q) {
                    $q->withTrashed()->select('id', 'id_currency1', 'id_currency2', 'tech_name', 'deleted_at');
                },
                'direction_exchange.currency1' => function ($q) {
                    $q->select('id', 'id_code_currency', 'id_payment', 'number_format');
                },
                'direction_exchange.currency1.code_currency' => function ($q) {
                    $q->select('id', 'name');
                },
                'direction_exchange.currency1.payment' => function ($q) {
                    $q->select('id', 'name', 'logo');
                },
                'direction_exchange.currency2' => function ($q) {
                    $q->select('id', 'id_code_currency', 'id_payment', 'id_aml_service', 'number_format');
                },
                'direction_exchange.currency2.code_currency' => function ($q) {
                    $q->select('id', 'name');
                },
                'direction_exchange.currency2.payment' => function ($q) {
                    $q->select('id', 'name', 'logo');
                },
                'pending_order_status',
                'task_status',
                'task_operators' => function ($q) {
                    $q->select('id', 'id_user', 'id_task', 'created_at');
                },
                'task_operators.user' => function ($q) {
                    $q->select('id', 'name', 'email');
                }
            ])
            ->where('is_archive', '=', 0)
            ->filter($requestAll);

        // Общий поиск (поддержка нескольких токенов через запятую)
        if ($request->filled('searchValue'))
        {
            $raw = trim(security_xss($request->input('searchValue')));

            // Разбиваем по запятым, чистим пробелы, убираем пустые
            $tokens = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $raw))));

            // Ограничим количество токенов, чтобы не перегружать запрос
            if (count($tokens) > 20) {
                $tokens = array_slice($tokens, 0, 20);
            }

            // Применение логики поиска к одному токену
            $applyToken = function ($q, string $token) {

                // Явная форма field:value
                if (str_contains($token, ':')) {

                    [$field, $value] = explode(':', $token, 2);
                    $field = trim($field);
                    $value = trim($value);

                    switch ($field) {
                        case 'id':
                            if (ctype_digit($value)) $q->where('id', (int)$value);
                            return;
                        case 'p':
                            if (ctype_digit($value) && strlen($value) >= 8) $q->where('public_id', 'LIKE', $value.'%');
                            return;
                        case 'email':
                            if (filter_var($value, FILTER_VALIDATE_EMAIL)) $q->where('email', 'LIKE', $value.'%');
                            return;
                        case 'user':
                            if (ctype_digit($value)) $q->where('id_user', (int)$value);
                            return;
                        case 'w':
                            if ($value !== '') $q->where('transfer_to_account', 'LIKE', $value.'%');
                            return;
                        default:
                            if (ctype_digit($value)) $q->where('id', (int)$value);
                            return;
                    }
                }

                // Автоопределение без префикса
                if (ctype_digit($token)) {
                    $len = strlen($token);
                    if ($len <= 8) {
                        $q->where('id', (int)$token); // внутренний ID
                    } elseif ($len >= 9 && $len <= 13) {
                        $q->where('public_id', 'LIKE', $token.'%'); // Public ID
                    } else {
                        $q->where('transfer_to_account', 'LIKE', $token.'%'); // длинный номер счёта/кошелька
                    }
                } elseif (filter_var($token, FILTER_VALIDATE_EMAIL)) {
                    $q->where('email', 'LIKE', $token.'%');
                } else {
                    // По номеру счёта или имени пользователя
                    $q->where(function ($sub) use ($token) {
                        $sub->where('transfer_to_account', 'LIKE', $token.'%')
                            ->orWhereHas('user', function ($u) use ($token) {
                                $u->where('name', 'LIKE', $token.'%');
                            });
                    });
                }
            };


            // Один токен — прежняя логика; несколько — объединяем по OR
            if (count($tokens) === 1) {
                $orders->where(function ($q) use ($tokens, $applyToken) {
                    $applyToken($q, $tokens[0]);
                });
            } else {
                $orders->where(function ($q) use ($tokens, $applyToken) {
                    foreach ($tokens as $t) {
                        $q->orWhere(function ($sub) use ($t, $applyToken) { $applyToken($sub, $t); });
                    }
                });
            }
        }



        // Получаем фильтр
        if($request->has('tabValue'))
        {
            if($request->get('tabValue') == 'all')
            {
                if (! empty(iEXSetting('ids_order_page_hidden_statuses'))) {
                    $orders = $orders->whereNotIn('status', explode(',', iEXSetting('ids_order_page_hidden_statuses')));
                }

                if (\auth()->user()->can('admin_order_trashed') and (int) iEXSetting('is_order_hidden_without_trashed') == 0) {
                    $orders = $orders->withTrashed();
                }
            }

            if($request->get('tabValue') == 'handler')
            {
                // Статусы для Live
                $liveStatus = explode(',', iEXSetting('iex_order_live_statuses'));

                // ГРУППИРУЕМ условия статусов, чтобы OR не "ломал" предыдущие фильтры (в т.ч. searchValue)
                $orders->where(function ($q) use ($liveStatus) {
                    $q->where('is_frozen', 0)
                      ->whereIn('status', $liveStatus);

                    if ((int) iEXSetting('iex_order_live_is_request_payment', 0) == 1) {
                        $q->orWhere(function ($qq) {
                            $qq->where('status', 2)
                               ->where('is_request_payment_type', 1);
                        });
                    }
                });

                if ((int) iEXSetting('iex_order_is_hidden_order_opened') == 1) {
                    $orders->where(function ($query) {
                        $query->whereDoesntHave('task_operators')
                            ->orWhereHas('task_operators', function ($q) {
                                $q->where('id_user', '=', \auth()->id());
                            });
                    });
                }
            }

            if($request->get('tabValue') == 'frozen') {
                $orders->where('status', '=', 8);
            }
        }

        $items = $orders->orderBy('id', 'desc');

        if($request->get('tabValue') == 'handler') {

            // Отдельный запрос на всех операторов (без фильтра на текущего оператора)
            $liveStatuses = explode(',', iEXSetting('iex_order_live_statuses'));

            $ordersQuery = Task::query()
                ->with(['task_status', 'task_operators.user'])
                ->where('is_frozen', 0)
                ->whereIn('status', $liveStatuses);

            if ((int)iEXSetting('iex_order_live_is_request_payment', 0) == 1) {
                $ordersQuery->orWhere(function ($query) {
                    $query->where('status', 2)->where('is_request_payment_type', 1);
                });
            }

            $orders = $ordersQuery->orderByDesc('id')->limit(100)->get();

            // Собираем данные по операторам, статусам и количествам
            $operators = [];

            foreach ($orders as $order) {
                foreach ($order->task_operators as $operator) {
                    if ($operator->user === null) {
                        continue;
                    }
                    $opId = $operator->user->id;

                    if (!isset($operators[$opId])) {
                        $operators[$opId] = [
                            'id' => $opId,
                            'name' => $operator->user->name,
                            'email' => $operator->user->email,
                            'total_orders' => 0,
                            'statuses' => [],
                        ];
                    }

                    if ($order->task_status === null) {
                        continue;
                    }

                    $statusId = $order->task_status->id;
                    $statusName = $order->task_status->name;

                    if (!isset($operators[$opId]['statuses'][$statusId])) {
                        $operators[$opId]['statuses'][$statusId] = [
                            'status_name' => $statusName,
                            'count' => 0,
                        ];
                    }

                    $operators[$opId]['statuses'][$statusId]['count']++;
                    $operators[$opId]['total_orders']++;
                }
            }

            return response()->json([
                'items' => new OrdersLiveResources($items->limit($liveLimit)->get()),
                'operators' => array_values($operators),

            ]);
        } else {
            return response()->json([
                'items' => new OrdersResources($items->paginate(
                    in_array((int)$request->input('per_page'), $this->allowedPerPage, true)
                        ? (int)$request->input('per_page')
                        : 20
                ))
            ]);
        }
    }


    /**
     * Детали заявки
     *
     * @return OrderIdResource|\Illuminate\Http\JsonResponse
     *
     * @throws InvalidArgumentException
     * @throws \Throwable
     */
    public function show(int $id, Request $request)
    {
        $user = \auth()->user();

        $transaction = TransactionFacade::find($id, [
            'type' => ($request->type ?? 'default'),
            'payment' => ($request->payment ?? null),
            'actions' => ($request->actions ?? null),
            'message' => ($request->message_success ?? null),
            'type_message' => ($request->type_send_message ?? null),
            'feeAmount' => ($request->subtractfeefromamount ?? false),
        ], true);


        // Информация о заявке
        $detail = $transaction->getItem();

        $detail->loadMissing([
            'user' => function ($q) {
                $q->select(['users.id', 'users.name', 'users.email', 'users.last_activity_at', 'users.is_verify_account'])
                    ->withCount([
                        'exchangeTotals as exchanges_count',
                    ])
                    ->withSum('exchangeTotals as exchanges_sum_usd', 'exchange_usd');
            },
            // Soft-deleted directions must still resolve for historical order detail.
            'direction_exchange' => function ($q) {
                $q->withTrashed();
            },
            'direction_exchange.currency1.code_currency',
            'direction_exchange.currency1.payment.explorer',
            'direction_exchange.currency2.code_currency',
            'direction_exchange.currency2.payment.explorer',
        ]);

        if($detail->trashed()) {
            return response()->json([
                'id' => $detail->id,
                'public_id' => $detail->public_id,
                'attributes' => [
                    'is_trashed' => 1,
                    'is_archive' => $detail->is_archive,
                    'status' => $detail->status
                ]
            ]);
        }

        // Статусы, которые разрешены к обработке
        $handlerStatuses = stringToArray((string)iEXSetting('iex_order_live_statuses'));
        $isPreview = !in_array((int)$detail->status, (array)$handlerStatuses ?? []);


        // Если нет прав, то снова
        if(!$isPreview and !$user->can('admin_orders_execute')) {
            $isPreview = true;
        }

        if(!$isPreview and $transaction->limit_operator_view() == 0) {
            $isPreview = true;
        }

        // Проверяем, привязаны ли операторы к заявке
        $operators = TaskOperator::with(['user' => function($q) {
            $q->select('id', 'name', 'email');
        }])->where('id_task', $id)->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'id_user' => $item->id_user,
                'user' => [
                    'id' => $item->user?->id,
                    'name' => $item->user?->name,
                    'email' => $item->user?->email,
                    'avatar' => Str::upper(Str::substr($item->user?->name, 0, 1))
                ],
                'created_at' => (!is_null($item->created_at)) ? Carbon::parse($item->created_at)->diffForHumans() : ''
            ];
        });

        $hasOperator = $operators->where('id_user', Auth::id())->isEmpty();
        if ($hasOperator) {
            $isPreview = true;
        }

        $isOperator = false;
        if($operators->where('id_user', Auth::id())->count() > 0) {
            $isOperator = true;
        }


        // Переадресация транзакций со статусами (Оплаченная)
        if (! in_array($detail->status, [3, 7, 12, 14])) {
            $isPreview = true;
        }

        $walletInfoIn = null;
        if ($transaction->isStart() and $transaction->getWalletTransaction() != null) {
            $walletInfoIn = $transaction->getWalletTransaction();
        } elseif ($transaction->isStart() and $transaction->getHistoryCode() != null) {
            $walletInfoIn = $transaction->getHistoryCode();
        }

        $display_in_price = $transaction->getDisplayInPrice(true);
        $display_out_price = $transaction->getDisplayOutPrice(true);

        // Направление обмена
        $direction_exchange = $transaction->getDirectionExchange();

        $calcWith = [];
        if($detail->is_type_rate == 1) {
            $calcWith['type_rate'] = $detail->type_rate;
        }

        // Инициализация калькулятора
        $calculator = CalculatorFacade::setDirectionExchange($direction_exchange)
            ->setOrder($transaction->getItem())
            ->calculateWithOptions($calcWith);

        $newCourse = [
            'amount' => $calculator->getRateValue(),
            'curs' => $calculator->getFullRate(),
        ];

        $diagnostics = app(OrderRateDiagnosticsService::class)->calculate([
            'display_in' => (string) $display_in_price,
            'display_out' => (string) $display_out_price,
            'rate_amount' => (string) ($newCourse['amount'] ?? '0'),
            'user_discount' => (string) ($detail->receiving_price_user_discount ?? '0'),
            'promo_bonus' => (string) ($detail->receiving_price_with_promocode ?? '0'),
            'precision' => (int) $transaction->getCurrencyOut()->number_format,
        ]);

        $percent_diff = $diagnostics['percent_diff'];
        $new_out_amount = $diagnostics['new_out_amount'];


        // Ledger резерва по заявке (для истории/аудита).
        // Сохраняем прежнее поведение: показываем только для статуса 4.
        $reserves_log = $detail->status === 4
            ? ReserveLedger::query()
                ->with('directionExchange:id,tech_name')
                ->where('task_id', (int) $detail->id)
                ->orderBy('id')
                ->get()
            : [];

        // Причины отклонения заявок (кэш: справочник редко меняется; TTL ограничивает устаревание)
        $reasonRejection = Cache::remember('admin.orders.reference.task_rejection_status_pluck_v1', 300, static function () {
            return TaskRejectionStatus::all()->map(static function ($value) {
                return [
                    'id' => $value->id,
                    'name' => $value->name,
                ];
            })->pluck('name', 'id');
        });
        // Причина отложения заявки
        $pendingOrderStatus = Cache::remember('admin.orders.reference.pending_order_status_pluck_v1', 300, static function () {
            return PendingOrderStatus::all()->map(static function ($value) {
                return [
                    'id' => $value->id,
                    'name' => $value->name,
                ];
            })->pluck('name', 'id');
        });



        // История операторов
        $operatorsHistories = TasksOperatorLog::with(['new_operator' => function ($q) {
            return $q->select('id', 'name', 'email');
        }])->where('id_task', $detail->id)->get();

        // Проверка, есть ли API
        $hasAPI = 0;
        if($user->can('admin_orders_auto_successful')) {
            $hasAPI = ($transaction->getCurrencyOutApi()->isNotEmpty() ?? 0);
            if($hasAPI == 0) {
                $hasAPI = ($transaction->getDirectionExchange()->gateway_payments->isNotEmpty() ?? 0);
            }
        }

        // Грузим связь (и упорядочиваем), если ещё не загружена
        $detail->loadMissing(['extraOuts' => function ($q) {
            $q->orderBy('position')->orderBy('id');
        }]);

        // Коллекция строк (всегда коллекция)
        $extraOutRows = collect($detail->extraOuts ?? []);

        // Приводим к простому массиву для Vue (сырые значения, без форматирования)
        $extraOutItems = $extraOutRows->map(static function ($eo) {
            return [
                'label'    => (string) ($eo->label ?? ''),
                'amount'   => (string) ($eo->amount ?? '0'),
                'position' => (int) ($eo->position ?? 0),
            ];
        })->values();

        $possibleProfit = null;

        $statusEnum = TaskStatusEnum::tryFrom((int) $detail->status);

        $canShowPossibleProfit = in_array($statusEnum?->value, [
            TaskStatusEnum::PENDING_PAYMENT->value,
            TaskStatusEnum::WAITING_HANDLE->value,
            TaskStatusEnum::PAID->value,
        ], true);

        if ($canShowPossibleProfit) {
            try {
                /** @var OrderProfitCalculator $profitCalculator */
                $profitCalculator = app(OrderProfitCalculator::class);

                // Сумма «отдаю» как десятичная строка
                $amountIn = (string) $display_in_price;

                $dto = $profitCalculator->calculateForTask($detail, $amountIn);
                $possibleProfit = $dto?->toArray();
            } catch (\Throwable) {
                $possibleProfit = null;
            }
        }



        $response =  new OrderIdResource([
            'detail' => $detail,
            'isPreview' => $isPreview,
            'isOperator' => $isOperator,
            'newCourse' => $newCourse,
            'reasonRejection' => $reasonRejection,
            'pendingOrderStatus' => $pendingOrderStatus,
            'checkPayment' => $transaction->checkPayment(),
            'walletInfo' => $walletInfoIn ?? [],
            'cardInfoIn' => $transaction->getCardDetails('in'),
            'cardInfoOut' => $transaction->getCardDetails('out'),
            'percentDiff' => $percent_diff,
            'newOutAmount' => $new_out_amount,
            'hasAPI' => $hasAPI,
        ]);

        $response->additional([
            'id' => $detail->id,
            'public_id' => $detail->public_id,
            'display_id' => current_order_id($detail),
            'operators' => $operators ?? [],
            'operatorsHistories' => $operatorsHistories,
            'receive_wallet' => $transaction->getAddresses(),
            'account_number_field' => $transaction->getFieldAccountNumber(),
            'tasks_fields' => $transaction->getTasksFields(),
            'reserves_log' => $reserves_log,
            'possible_profit' => $possibleProfit,
            'extra_out'        => $extraOutItems,
        ]);

        return $response;
    }

    /**
     * Обновление данных
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws InvalidArgumentException
     */
    public function update(int $id, Request $request)
    {
        // Удаленные данные
        if(isset($request->deleteAction))
        {
            if($request->deleteAction == 'restore')
            {
                $item = Task::withTrashed()->find($id);
                $item->restore();
                TransactionFacade::call($id)->restoreOrder();

                return response()->json([
                    'status' => 0,
                    'message' => sprintf(__('Заявка %s успешно восстановлена'), $item->public_id)
                ]);
            }

            if($request->deleteAction == 'archived')
            {
                $item = Task::withTrashed()->find($id);
                $item->update([
                    'is_archive' => 1,
                    'archived_at' => now(),
                ]);

                return response()->json([
                    'status' => 0,
                    'message' => sprintf(__('Заявка %s успешно архивирована'), $item->public_id)
                ]);
            }


            return response()->json([
                'status' => 1
            ]);
        }



        $actionName = $request->action ?? '';

        $transaction = TransactionFacade::find($id);
        $user = \auth()->user();

        // Получаем данные
        $order = $transaction->getTransaction();


        // Выплата бонусов
        if ($actionName == 'referralBonus')
        {
            if(in_array($request->value, [0, 1]))
            {
                $transaction->getItem()->update([
                    'is_pay_referral_bonus' => (int)$request->value ?? 0,
                ]);
            }
            return response()->json([
                'status' => 0
            ]);
        }

        if($actionName == 'orderStep')
        {
            $transaction->changeOrderStep((int)$request->value ?? 0);
            return response()->json([
                'status' => 0
            ]);
        }

        if($actionName == 'ignoreAndContinueAmlAddress') {
            AMLResponseData::where([
                ['method', 'address'],
                ['id_task', $id]
            ])->update([
                'ext_params->is_check_address' => 0
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Ограничение по AML успешно сняты'
            ]);
        }
        if($actionName == 'ignoreAndContinueAmlTx') {
            AMLResponseData::where([
                ['method', 'tx'],
                ['id_task', $id]
            ])->update([
                'ext_params->is_check_tx' => 0
            ]);


            return response()->json([
                'status' => 0,
                'message' => 'Ограничение по AML успешно сняты'
            ]);
        }


        if($actionName == 'disabledAutoBot') {
            $transaction->disableIsBot();

            return response()->json([
                'status' => 0,
                'message' => 'Автовыплата выключена'
            ]);
        }

        // Изменить реквизит
        if($actionName == 'editData')
        {
            if($transaction->getStatus() == 4) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Редактирование недоступно'
                ]);
            }

            if(!auth()->user()->can('admin_orders_id_editor')) {
                return response()->json([
                    'status' => 1,
                    'message' => 'У вас нет прав на редактирование'
                ]);
            }

            if($request->typeName == 'income') {
                $order->update([
                    'from_shot' => $request->shotValue ?? '',
                    'id_edit_data_manager' => auth()->id(),
                ]);
            }

            if($request->typeName == 'outcome') {
                $order->update([
                    'to_shot' => $request->shotValue ?? '',
                    'id_edit_data_manager' => auth()->id(),
                ]);
            }

            return response()->json([
                'status' => 0,
                'message' => 'Данные успешно сохранены'
            ]);
        }


        // Обновляем статус заявки
        if($actionName == 'restoreOrder')
        {
            // разрешаем восстановить заявку
            if($user->can('admin_orders_restore'))
            {
                $transaction->restoreOrder();

                return response()->json([
                    'status' => 0,
                    'message' => 'Заявка №'.$transaction->getTransaction()->public_id.' переведен в режим обработки'
                ]);
            }

            return response()->json([
                'status' => 1,
                'message' => 'У вас нет прав на восстановление заявки'
            ]);
        }

        if($actionName == 'cancelOrder') {

            // разрешаем восстановить заявку
            if($user->can('admin_orders_restore'))
            {
                $transaction->setStatus(5);

                return response()->json([
                    'status' => 0,
                    'message' => 'Заявка №'.$transaction->getTransaction()->public_id.' отклонена'
                ]);
            }

            return response()->json([
                'status' => 1,
                'message' => 'У вас нет прав на восстановление заявки'
            ]);
        }


        if ($actionName == 'addRequisitesPayment')
        {
            $validator = Validator::make($request->all(),  [
                'requisites_field' => ['required']
            ]);

            if($validator->fails()) {
                return response()->json([
                    'status' => 1,
                    'message' => $validator->messages()->first()
                ]);
            }

            // Берем из списка транзитные реквизитов
            $transaction->updateAddRequisitesPayment($request->requisites_field, $request->all());

            return response()->json([
                'status' => 0,
                'message' => 'Реквизит прикреплен'
            ]);
        }

        return response()->json([
            'status' => 1
        ]);
    }


    function updateOrderById(int $id)
    {
        $detail = Task::find($id);
        $user = \auth()->user();

        // Статусы, которые разрешены к обработке
        $handlerStatuses = stringToArray((string)iEXSetting('iex_order_live_statuses'));
        $isPreview = !in_array((int)$detail->status, (array)$handlerStatuses ?? []);


        // Если нет прав, то снова
        if(!$isPreview and !$user->can('admin_orders_execute')) {
            $isPreview = true;
        }

        // Проверяем, привязаны ли операторы к заявке
        $operators = TaskOperator::with(['user' => function($q) {
            $q->select('id', 'name', 'email');
        }])->where('id_task', $id)->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'id_user' => $item->id_user,
                'user' => [
                    'id' => $item->user?->id,
                    'name' => $item->user?->name,
                    'email' => $item->user?->email,
                    'avatar' => Str::upper(Str::substr($item->user?->name, 0, 1))
                ],
                'created_at' => (!is_null($item->created_at)) ? Carbon::parse($item->created_at)->diffForHumans() : ''
            ];
        });

        $hasOperator = $operators->where('id_user', Auth::id())->isEmpty();
        if ($hasOperator) {
            $isPreview = true;
        }

        $isOperator = false;
        if($operators->where('id_user', Auth::id())->count() > 0) {
            $isOperator = true;
        }


        // Переадресация транзакций со статусами (Оплаченная)
        if (! in_array($detail->status, [3, 7, 12, 14])) {
            $isPreview = true;
        }

        return response()->json([
            'id' => $detail->id,
            'public_id' => $detail->public_id,
            'attributes' => [
                'status' => $detail->status,
                'status_name' => $detail->task_status?->name,
                'is_operator' => $isOperator,
                'is_preview' => $isPreview
            ],
            'operators' => $operators->toArray() ?? [],
        ]);
    }
}
