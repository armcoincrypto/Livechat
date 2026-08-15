<?php

namespace App\Http\Controllers\Administrator\Gateways;

use App\Facades\Vault;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Gateways\AutoPaymentsResources;
use App\Models\Currency;
use App\Models\GatewayPayment;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use iEXPackages\Payments\Core\Security\Contracts\SecretAccessManagerInterface;
use iEXPackages\Payments\Core\Services\GatewayInputsFormBuilder;
use iEXPackages\Payments\Core\Services\PaymentsConfigCatalog;
use iEXPackages\Payments\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AutoPaymentController extends Controller
{
    /**
     * Ключи для настройки автовыплат
     *
     * @var array
     */
    protected array $settingOptions = [
        'settings' => [
            'is_enabled_autopay_cron',
            'is_disabled_log_request_autopayment'
        ]
    ];


    /**
     * Список созданных автовыплат
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        if ($request->get('showPage') === 'settings') {
            return response()->json([
                'is_enabled_autopay_cron' => (bool)iEXSetting('is_enabled_autopay_cron', 0),
                'is_disabled_log_request_autopayment' => (bool)iEXSetting('is_disabled_log_request_autopayment', 0)
            ]);
        }

        $payments = GatewayPayment::filter($request->all())
            ->when(!$request->has('sorting_order'), fn($q) => $q->orderByDesc('id'))
            ->paginate(iEXSetting('admin_autopayment_pagination', 20));


        $dynamicPay = collect(app(PaymentsConfigCatalog::class)->payout())
            ->keyBy('alias');


        return response()->json([
            'items' => new AutoPaymentsResources($payments),
            'gateways' => $dynamicPay,
            'per_page' => (int)iEXSetting('admin_autopayment_pagination', 20)
        ]);
    }

    /**
     * Обработка и добавление автовыплаты
     *
     * @param Request $request
     * @return JsonResponse
     * @throws EnvironmentIsBrokenException
     */
    public function store(Request $request): JsonResponse
    {
        // Если включена возможность обновления данных
        if($request->is_update == 1 and $request->has('showPage'))
        {
            if($request->showPage == 'settings')
            {
                if ($request->user() === null || ! $request->user()->can('admin_autopayment')) {
                    return response()->json([
                        'status' => 1,
                        'message' => 'Forbidden',
                    ], 403);
                }
                // Обновление конфига
                $array = [];
                foreach ($this->settingOptions[$request->showPage] as $value) {

                    if ($request->has($value) and is_array($request->get($value))) {
                        $array[$value] = implode(',', $request->get($value));
                    } else {
                        $array[$value] = ($request->has($value) ? $request->get($value) : null);
                    }
                }
                iEXSetting($array);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }

        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                GatewayPayment::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }

        // Если включена возможность обновления данных
        if($request->is_update == 1)
        {
            return response()->json([
                'status' => 0,
                'message' => 'Данные успешно обновлены'
            ]);
        }


        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'alias' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $validated = $validator->validated();
        $gateway = app(PaymentsConfigCatalog::class)->payoutByAlias($validated['alias']);

        if (empty($gateway)) {
            return response()->json([
                'status' => 1,
                'message' => 'Автовыплата не добавлена'
            ]);
        }

        $uniqueFilename = Vault::generateVaultFilename('pay', $gateway['alias']);


        $paymentConfig = Payments::forConfig($gateway['alias']);
        $keys = collect($paymentConfig->fields('pay'))
            ->pluck('key')
            ->filter()
            ->values()
            ->all();

        $payload = [];

        foreach ($keys as $key) {
            $payload[$key] = $data[$key] ?? '';
        }


        try {
            Vault::encryptToFile($uniqueFilename, $payload, 'gateways');
        } catch (\Throwable $e) {
            Log::error('Ошибка записи Vault-файла', [
                'error' => $e->getMessage(),
                'filename' => $uniqueFilename,
            ]);
            return response()->json([
                'status' => 1,
                'message' => 'Ошибка сохранения конфигурации автовыплаты.'
            ], 500);
        }

        $item = GatewayPayment::create([
            'name' => $request->get('name'),
            'alias' => $gateway['alias'],
            'status' => (int)$request->get('status', 0),
            'filename' => $uniqueFilename,
        ]);


        return response()->json([
            'status' => 0,
            'message' => "{$item->name} успешно добавлен"
        ]);
    }

    /**
     * Редактирование автовыплты
     *
     * @return JsonResponse
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException
     */
    public function edit(int $id)
    {
        $item = GatewayPayment::findOrFail($id);
        $isValidate = true;

        try {
//            $editorFilePath = storage_path('/app/iexexchanger/editor.json');
//            if (!File::exists($editorFilePath)) {
//                throw new \RuntimeException("Файл не найден: {$editorFilePath}");
//            }
//            $editorTextarea = json_decode(File::get($editorFilePath), true);

            $form = app(GatewayInputsFormBuilder::class)->buildPayForm($item);

            $connectionFields   = $form['connectionFields'];
            $optionsFields      = $form['optionsFields'];


        } catch (\Throwable $exception) {
            Log::error('Ошибка при редактировании автовыплаты', [
                'error' => $exception->getMessage(),
                'payment_id' => $id,
            ]);

            $isValidate = false;
            $connectionFields = [];
            $optionsFields = [];
        }


        $currencies = Currency::active()->get()->map(fn($currency) => [
            'id' => $currency->id,
            'value' => $currency->tech_name
        ]);

        /** @var SecretAccessManagerInterface $secrets */
        $secrets = app(SecretAccessManagerInterface::class);
        $isAutopayAccessGranted = $secrets->canView('pay');


        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'name' => $item->name,
                'status' => (bool)$item->status,
                'alias' => $item->alias,
                'comment' => $item->comment,
                'pay_amount_type' => $item->pay_amount_type,
                'allow_autopay' => (bool)$item->allow_autopay,
                'day_limit_amount_pay' => $item->day_limit_amount_pay,
                'month_limit_amount_pay' => $item->month_limit_amount_pay,
                'min_amount_for_per_order' => $item->min_amount_for_per_order,
                'max_amount_for_per_order' => $item->max_amount_for_per_order,
                'day_limit_pay' => $item->day_limit_pay,
                'month_limit_pay' => $item->month_limit_pay,
                'manual_pay_order' => (bool)$item->manual_pay_order,
                'ids_currencies' => $item->currencies->pluck('id')
            ],
            'isValidate' => $isValidate,
            'currencies' => $currencies,
            'connectionFields' => $connectionFields,
            'optionsFields' => $optionsFields,
            'isAutopayAccessGranted' => $isAutopayAccessGranted,
        ]);
    }

    /**
     * Обработка и обновление автовыплаты
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     *
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $item = GatewayPayment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ], 422);
        }

        $validated = $validator->validated();

        // Получение конфига мерчанта
        $paymentConfig = Payments::forConfig($item->alias);

        $hiddenKeys = collect($paymentConfig->fields('pay'))
            ->filter(fn (array $field) => (bool) ($field['is_hidden'] ?? false))
            ->pluck('key')
            ->filter()
            ->values()
            ->all();

        try {
            \DB::transaction(function () use ($item, $request, $validated, $hiddenKeys, $paymentConfig) {
                // Безопасное обновление файла конфигурации
                Vault::updateFile($item->filename, $request->connectionFields ?? [], 'gateways', $hiddenKeys);

                // Обновление данных мерчанта в базе
                $options = [
                    'name' => $validated['name'],
                    'status' => (int)$request->get('status', 0),
                    'id_proxy' => (int)$request->get('id_proxy', 0),
                    'manual_pay_order' => (bool)$request->get('manual_pay_order', 0),
                    'direction' => (int)$request->get('direction', 0),
                    'pay_amount_type' => $request->get('pay_amount_type', 0),
                    'day_limit_amount_pay' => (float)$request->get('day_limit_amount_pay', 0),
                    'month_limit_amount_pay' => (float)$request->get('month_limit_amount_pay', 0),
                    'min_amount_for_per_order' => (float)$request->get('min_amount_for_per_order', 0),
                    'max_amount_for_per_order' => (float)$request->get('max_amount_for_per_order', 0),
                    'day_limit_pay' => (float)$request->get('day_limit_pay', 0),
                    'month_limit_pay' => (float)$request->get('month_limit_pay', 0),
                    'allow_autopay' => (bool)$request->get('allow_autopay', 0),
                    'comment' => $request->get('comment', null)
                ];

                $item->update($options);

                $item->currencies()->sync($request->get('currencies', []));
                //$item->direction_exchange()->sync($request->get('ids_direction_exchange', []));


                // options_fields из config.php (merchant)
                $defaultOptionFields = collect($paymentConfig->optionFields('pay'))
                    ->keyBy('key');

                if ($defaultOptionFields->isNotEmpty()) {

                    $inputOptionFields = (array) ($request->optionFields ?? []);


                    $optionsFields = collect($inputOptionFields)
                        ->filter(fn ($value, $key) => $defaultOptionFields->has((string) $key))
                        ->map(function ($value, $key) use ($defaultOptionFields) {

                            $field = (array) $defaultOptionFields->get((string) $key, []);
                            $valueType = $field['value_type'] ?? null;

                            if ($valueType === 'bool') {
                                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1 : 0;
                            }

                            return (is_string($valueType) && $valueType !== '')
                                ? variableStrictValue($value, $valueType)
                                : $value;
                        });

                    $item->update([
                        'ext_options' => $optionsFields->toArray(),
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            Log::error('Ошибка при обновлении автовыплаты', [
                'error' => $exception->getMessage(),
                'payment_id' => $id
            ]);

            return response()->json([
                'status' => 1,
                'message' => 'Ошибка обновления автовыплаты: ' . $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => 0,
            'message' => "{$item->name} успешно обновлен"
        ]);
    }

    /**
     * Удаляем выплату
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy( int $id)
    {
        $item = GatewayPayment::find($id);
        $oldItem = $item;

        Vault::deleteFile($oldItem->filename, 'gateways');

        $item->currencies()->detach();
        $item->direction_exchange()->detach();
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name .' успешно удален'
        ]);
    }

    /**
     * Проверка кода доступа для раздела Автовыплаты и выдача временного доступа.
     * Поддерживает отзыв доступа через revoke=true.
     * Конфиг/ENV ключи:
     *  - config('iexexchanger.gateways.security.autopay_access_secret') | MERCHANT_AUTOPAY_ACCESS_SECRET
     *  - config('iexexchanger.gateways.security.autopay_access_secret_ttl') | MERCHANT_AUTOPAY_ACCESS_SECRET_TTL
     */
    public function secretKeyIndex(Request $request): JsonResponse
    {
        /** @var SecretAccessManagerInterface $secrets */
        $secrets = app(SecretAccessManagerInterface::class);

        // 1) Отзыв доступа (через менеджер)
        if ($request->boolean('revoke')) {
            $secrets->revoke('pay');

            return response()->json([
                'status'  => 0,
                'message' => __('Доступ закрыт.'),
            ]);
        }

        // 2) Ключ из запроса (code|token)
        $inputKey = trim((string) ($request->input('code') ?? $request->input('token') ?? ''));
        if ($inputKey === '') {
            return response()->json([
                'status'  => 1,
                'message' => __('Секретный ключ не передан.'),
            ], 422);
        }

        // 3) Ожидаемый ключ и TTL (в часах)
        $expectedKey = (string) (config('iexexchanger.gateways.security.autopay_access_secret') ?? '');
        $ttlHours    = (int) (config('iexexchanger.gateways.security.autopay_access_secret_ttl') ?? 6);
        $ttlHours    = max(1, $ttlHours);

        if ($expectedKey === '') {
            Log::warning('autopay_access_secret не задан в окружении/конфиге.');
            return response()->json([
                'status'  => 1,
                'message' => __('Система временно недоступна. Обратитесь к администратору.'),
            ], 503);
        }

        // 4) Проверка ключа
        if (!hash_equals($expectedKey, $inputKey)) {
            // на всякий случай закрываем доступ через менеджер
            $secrets->revoke('pay');

            return response()->json([
                'status'  => 1,
                'message' => __('Неверный секретный ключ.'),
            ], 401);
        }

        // 5) Выдача доступа через менеджер
        $ttlSeconds = $ttlHours * 3600;
        $secrets->grant('pay', $ttlSeconds);

        return response()->json([
            'status'     => 0,
            'message'    => __('Доступ разрешён.'),
            // expires_at фронту можно продолжать возвращать (как раньше)
            'expires_at' => now()->addSeconds($ttlSeconds)->toIso8601String(),
            'ttl_hours'  => $ttlHours,
        ]);
    }
}
