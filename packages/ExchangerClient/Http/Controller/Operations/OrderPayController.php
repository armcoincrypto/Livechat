<?php
declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Http\Controller\Operations;

use App\Events\OrderStatusesEvent;
use App\Http\Controllers\Controller;
use App\Services\Payments\CheckoutInvoiceRecoveryService;
use App\Jobs\AdminNewOrderJob;
use App\Models\NotificationEvent;
use App\Models\Task;
use App\Models\TaskInfo;
use App\Rules\DomainEmailValidator;
use App\Support\Facades\iEXApp;
use iEXPackages\ExchangerClient\Http\Resources\Orders\OrderProcessResource;
use iEXPackages\ExchangerClient\Http\Resources\Orders\OrderStatusResource;
use iEXPackages\Order\Facades\OrderFacade;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Psr\SimpleCache\InvalidArgumentException;

use iEXPackages\WorkStatus\Services\WorkStatusService;


class OrderPayController extends Controller
{
    /**
     * Создание новой заявки
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function create(Request $request): JsonResponse
    {
        $ip = $request->ip();
        $cacheKey = "order_created_limit_{$ip}";
        $currentCount = Cache::get($cacheKey, 0);
        $orderLimit = (int)iEXSetting('order_limit_count', 0);
        $timeLimitMinutes = (int)iEXSetting('order_limit_minutes', 0);

        if (app(WorkStatusService::class)->isOffline()) {
            return response()->json([
                'status'  => 1,
                'message' => [[
                    'field' => 'buy',
                    'message' => 'No connection to the site, try again later',
                    'modal' => false,
                ]],
            ], 422);
        }

        if ($orderLimit > 0 && $timeLimitMinutes > 0 && $currentCount >= $orderLimit) {
            return response()->json([
                'status' => 1,
                'message' => 'Вы превысили лимит создания заявок. Пожалуйста, подождите.',
            ], 429);
        }

        $rules = [
            'income_amount' => 'required|numeric|min:0',
            'outcome_amount' => 'required|numeric|min:0',
            'income_payment_system' => 'required|integer|exists:currencies,id',
            'outcome_payment_system' => 'required|integer|exists:currencies,id',
        ];

        if ((bool)iEXSetting('is_security_captcha_type') && (bool)iEXSetting('is_enabled_captcha_order')) {
            $rules['recaptcha'] = 'required|captcha';
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->sometimes(
            'email',
            ['required', 'email', 'max:150', new DomainEmailValidator()],
            fn() => Auth::guest()
        );

        $validator->setAttributeNames([
            'email' => __('Email адрес'),
            'income_amount' => __('Отдаю'),
            'outcome_amount' => __('Получаю'),
            'recaptcha' => __('Капча')
        ]);

        if ($validator->fails()) {
            $errors = collect($validator->errors()->messages())->map(function ($messages, $field) {
                return [
                    'field' => $field,
                    'modal' => false,
                    'message' => $messages[0],
                ];
            })->values();

            return response()->json([
                'status' => 1,
                'errors' => $errors,
            ], 422);
        }

        try {
            $order = OrderFacade::request($request);
            $response = $order->created();

            if (isset($response['data'])) {

                // Увеличиваем счетчик только после успешного создания заявки и если лимиты включены
                // Повторное использование той же заявки (идемпотентность) не должно расходовать лимит.
                if ($orderLimit > 0 && $timeLimitMinutes > 0 && empty($response['idempotent_reuse'])) {
                    Cache::put($cacheKey, $currentCount + 1, now()->addMinutes($timeLimitMinutes));
                }

                unset($response['idempotent_reuse']);

                return response()->json($response);
            }

            return response()->json([
                'status' => 1,
                'errors' => $response
            ], 422);

        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $isDirectionMissing = str_contains($message, 'Направление не найдено')
                || (
                    str_contains(mb_strtolower($message), 'direction')
                    && str_contains(mb_strtolower($message), 'not found')
                );

            if ($isDirectionMissing) {
                Log::warning('Order creation rejected: direction unavailable', [
                    'error' => $message,
                    'income_payment_system' => $request->get('income_payment_system'),
                    'outcome_payment_system' => $request->get('outcome_payment_system'),
                ]);

                return response()->json([
                    'status' => 1,
                    'code' => 'DIRECTION_UNAVAILABLE',
                    'errors' => [[
                        'field' => 'buy',
                        'message' => $message,
                        'modal' => false,
                    ]],
                ], 422);
            }

            Log::error('Order creation error', [
                'error' => $message,
                'exception_class' => $e::class,
                'trace' => $e->getTraceAsString(),
                'income_payment_system' => $request->get('income_payment_system'),
                'outcome_payment_system' => $request->get('outcome_payment_system'),
                // Avoid logging full PII payload; keep shape only.
                'request_keys' => array_keys($request->all()),
            ]);

            return response()->json([
                'status' => 1,
                'code' => 'ORDER_INTERNAL_ERROR',
                'errors' => [
                    'field' => 'system',
                    'message' => __('Ошибка обработки заказ')
                ]
            ], 500);
        }
    }

    /**
     * Полная информация о заявке
     */
    public function process(int $public_id, CheckoutInvoiceRecoveryService $invoiceRecovery): OrderProcessResource
    {
        $order = Task::where('public_id', $public_id)->firstOrFail();

        try {
            $invoiceRecovery->recoverIfEligible($order, 'order_page');
            $order->refresh();
        } catch (\Throwable $e) {
            Log::warning('checkout_invoice_recovery.order_page_failed', [
                'task_id'         => $order->id,
                'exception_class' => $e::class,
            ]);
        }

        if (!$order->is_run_process)
        {
            $current_order_id = current_order_id($order);

            // Уведомление администратора о необходимости выдачи реквизитов
            if ($order->is_request_payment_type === 1) {
                iEXApp::reverbEvent([
                    'is_notice' => 1,
                    'message' => __('Новая заявка №:id ожидает выдачи реквизитов для оплаты.', ['id' => $current_order_id]),
                ]);

                // Сохраняем уведомление в историю
                NotificationEvent::create([
                    'is_read'    => 0,
                    'type_event' => 1,
                    'title'      => 'Заявка ожидает выдачи реквизитов для оплаты',
                    'id_value'   => $order->id,
                    'message'    => [
                        'Направление обмена: ' . $order->direction_exchange->tech_name,
                        'Клиент отдает: ' . $order->give_price_with_comm,
                        'Сервис переводит: ' . $order->receiving_price_with_comm_pay,
                    ],
                ]);
            }

            // Помечаем, что заявка начала обрабатываться
            $order->update(['is_run_process' => 1]);
        }

        return new OrderProcessResource($order);
    }

    /**
     * Статус заявки
     *
     * @throws InvalidArgumentException
     */
    public function status(int $public_id): OrderStatusResource|JsonResponse
    {
        $order = Task::select([
            'id',
            'status',
            'public_id',
            'created_at',
            'id_payment_requisites',
            'is_request_payment_type',
            'requisites_receive',
            'requisites_description',
            'is_from_verification_card',
            'is_from_identity_verification',
            'method_request_payment',
            'is_run_process',
        ])
            ->where('public_id', $public_id)
            ->first();

        if (!$order) {
            return response()->json(null, 404);
        }

        return new OrderStatusResource($order);
    }

    /**
     * Отклонение заявки
     *
     * @throws \Exception
     */
    public function cancel(int $public_id): JsonResponse
    {
        $order = Task::select([
            'id',
            'public_id',
            'status',
            'is_bot',
            'id_user',
            'give_price',
            'receiving_price'
        ])
            ->where('public_id', $public_id)
            ->firstOrFail();

        // Если заявка уже отменена, ничего не делаем.
        if ($order->status === 6) {
            return response()->json(['status' => 0]);
        }

        // Допустимые статусы для отмены заявки клиентом.
        $allowedStatusesForCancel = [2, 9];

        if (!in_array($order->status, $allowedStatusesForCancel, true)) {
            throw new \Exception('Невозможно отменить заявку №' . $order->id . ' в текущем статусе.');
        }

        // Отменяем заявку.
        $order->update([
            'status' => 6,
            'is_bot' => 0,
        ]);

        return response()->json(['status' => 0]);
    }

    /**
     * Подтверждение о создании заявки
     *
     * @throws \Exception
     */
    public function confirm(int $public_id, Request $request): JsonResponse
    {
        // Валидация запроса
        $validate = validator($request->all(), [
            'type' => 'required|in:paid,unpaid',
            'income_code' => 'sometimes|required|min:15',
            'num_tx' => 'sometimes|nullable|string|max:100',
            'note_tx' => 'sometimes|nullable|string|max:255',
        ], [
            'income_code.required' => 'Укажите "Номер чека".',
            'income_code.min' => 'Укажите корректный "Номер чека".',
            'type.required' => 'Не указан тип операции.',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => 1,
                'code' => 'VALIDATION_ERROR',
                'message' => $validate->errors()->first(),
            ], 422);
        }

        // Поиск заявки
        $task = Task::with('task_info')->where('public_id', $public_id)->firstOrFail();
        $previousStatus = (int) $task->status;
        $wantsPaid = $request->type === 'paid';

        // Idempotent: already customer-marked paid (status 3) → success without re-notify.
        if ($wantsPaid && $previousStatus === 3) {
            Log::info('order_confirm_idempotent', [
                'public_id' => $public_id,
                'task_id' => $task->id,
                'previous_status' => $previousStatus,
            ]);
            return response()->json([
                'status' => 0,
                'code' => 'ALREADY_MARKED_PAID',
                'idempotent' => true,
            ]);
        }

        // Only awaiting-customer-payment (status 2) can transition via this endpoint.
        if ($previousStatus !== 2) {
            return response()->json([
                'status' => 1,
                'code' => 'INVALID_ORDER_STATUS',
                'message' => 'Order cannot be confirmed in the current status.',
            ], 422);
        }

        $tx = TransactionFacade::init($task);
        $statusInt = $wantsPaid ? 3 : 1;
        $tx->setStatus($statusInt);

        // Обновляем дополнительную информацию одним запросом
        TaskInfo::updateOrCreate(
            ['id_task' => $task->id],
            [
                'num_transaction' => $request->input('num_tx', $task->task_info->num_transaction ?? null),
                'note_tx' => $request->input('note_tx', $task->task_info->note_tx ?? null),
            ]
        );

        if ($wantsPaid) {
            Log::info('order_customer_marked_paid', [
                'public_id' => $public_id,
                'task_id' => $task->id,
                'previous_status' => $previousStatus,
                'new_status' => 3,
            ]);

            try {
                if ((int)($task->task_info->is_freeze_scam ?? 0) === 1) {
                    $tx->setDeferType(5)->defer();
                } else {
                    $this->notifyOperator($task);
                }
            } catch (\Throwable $e) {
                // Payment acknowledgement already persisted — never roll back for notify failure.
                Log::error('order_confirm_notify_failed', [
                    'task_id' => $task->id,
                    'public_id' => $public_id,
                    'exception_class' => $e::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status' => 0,
            'code' => $wantsPaid ? 'CUSTOMER_MARKED_PAID' : 'CUSTOMER_MARKED_UNPAID',
        ]);
    }

    private function notifyOperator(Task $task): void
    {
        if ((int)iEXSetting('is_enabled_module_socket') === 1) {
            broadcast(new OrderStatusesEvent($task));
        }

        if ((int)iEXSetting('is_mail_notify_order_manager') === 1) {
            dispatch(new AdminNewOrderJob($task))
                ->delay(now()->addSeconds(30))
                ->onQueue('low');
        }

        $current_order_id = (int)iEXSetting('client_id_type_for_order', 0) === 0 ? $task->id : $task->public_id;

        iEXApp::reverbEvent([
            'is_notice' => 1,
            'message' => __('Поступила новая заявка №:id', ['id' => $current_order_id]),
        ]);

        NotificationEvent::create([
            'is_read' => 0,
            'type_event' => 2,
            'id_value' => $task->id,
            'title' => 'Поступила новая заявка №' . $current_order_id,
            'message' => [
                'Направление: ' . $task->direction_exchange->tech_name,
                'Клиент отдает: ' . $task->give_price_with_comm,
                'Сервис переводит: ' . $task->receiving_price_with_comm_pay
            ]
        ]);

        // Existing channel event — operators receive via configured Telegram bots/channels.
        iEXApp::telegramNotificationForChannel('new_order_for_operator', $task);
        // Explicit customer-marked-paid signal (same channel family; fail-open upstream).
        try {
            iEXApp::telegramNotificationForChannel('order_pay', $task);
        } catch (\Throwable $e) {
            Log::warning('telegram_order_pay_notify_failed', [
                'task_id' => $task->id,
                'exception_class' => $e::class,
            ]);
        }
    }
}
