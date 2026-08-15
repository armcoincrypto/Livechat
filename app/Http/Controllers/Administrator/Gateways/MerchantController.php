<?php

namespace App\Http\Controllers\Administrator\Gateways;

use App\Facades\Vault;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Gateways\MerchantsResources;
use App\Models\Currency;
use App\Models\GatewayMerchant;
use App\Services\Gateways\SafeGatewayVaultUpdater;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use iEXPackages\Payments\Core\Security\Contracts\SecretAccessManagerInterface;
use iEXPackages\Payments\Core\Services\GatewayInputsFormBuilder;
use iEXPackages\Payments\Core\Services\PaymentsConfigCatalog;
use iEXPackages\Payments\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MerchantController extends Controller
{
    /**
     * Список всех созданных мерчантов
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $dynamicMerchants = collect(app(PaymentsConfigCatalog::class)->merchant())
            ->keyBy('alias');

        $merchants = GatewayMerchant::with([
            'currencies:id,tech_name',
            'direction_exchange:id,tech_name,id_currency1,id_currency2',
            'healthStatus',
        ])
            ->filter($request->all())
            ->when(!$request->has('sorting_order'), fn($query) => $query->orderByDesc('id'))
            ->paginate(iEXSetting('admin_merchant_pagination', 20));

        $errorsSecurity = $merchants->mapWithKeys(function ($merchant) {
            $errors = [];

            try {
                $paymentConfig = Payments::forConfig($merchant->alias);
            } catch (\Throwable) {
                return [$merchant->id => []];
            }

            // Callback блок из config.php (inputs.merchant.callback)
            $callbackEnabled = $paymentConfig->callbacksEnabled();
            $routeName       = $paymentConfig->callbackRouteName();
            $ipWhitelist     = $paymentConfig->callbackIpWhitelistEnabled();

            if ($callbackEnabled && $ipWhitelist && empty($merchant->allow_ip_address)) {
                $errors[] = __('Ограничение по IP адресу не указано');
            }

            if ($callbackEnabled && !empty($routeName) && empty($merchant->security_hash)) {
                $errors[] = __('Секретный ключ не указан');
            }

            $errors = array_values(array_filter($errors, fn($v) => is_string($v) && $v !== ''));

            return [$merchant->id => $errors];
        });


        return response()->json([
            'items' => new MerchantsResources($merchants),
            'gateways' => $dynamicMerchants,
            'errorsSecurity' => $errorsSecurity,
            'selected_columns' => [],
            'per_page' => (int)iEXSetting('admin_merchant_pagination', 20),
        ]);
    }

    /**
     * Обработка и добавление мерчанта
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->get('updateField') === 'status') {
            GatewayMerchant::where('id', (int)$request->id)->update([
                'status' => (int)$request->status,
            ]);

            return response()->json(['status' => 0]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'alias' => 'required|string',
            'status' => 'nullable|integer',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->errors()->first(),
            ]);
        }

        $validated = $validator->validated();

        $gateway = app(PaymentsConfigCatalog::class)->merchantByAlias($validated['alias']);

        if (!$gateway) {
            return response()->json([
                'status' => 1,
                'message' => 'Мерчант не найден или недоступен.',
            ]);
        }

        $uniqueFilename = Vault::generateVaultFilename('merchant', $gateway['alias']);

        $paymentConfig = Payments::forConfig($gateway['alias']);
        $keys = collect($paymentConfig->fields('merchant'))
            ->pluck('key')
            ->filter()
            ->values()
            ->all();

        $payload = [];

        foreach ($keys as $key) {
            $payload[$key] = $data[$key] ?? ''; // или null
        }

        Vault::encryptToFile($uniqueFilename, $payload, 'gateways');

        $item = GatewayMerchant::create([
            'name' => $validated['name'],
            'alias' => $gateway['alias'],
            'status' => $validated['status'] ?? 0,
            'filename' => $uniqueFilename,
        ]);

        return response()->json([
            'status' => 0,
            'message' => "{$item->name} успешно добавлен",
        ]);
    }

    /**
     * Редактирование мерчанта
     *
     * @param int $id
     * @param Request $request
     *
     * @return JsonResponse
     * @throws EnvironmentIsBrokenException
     * @throws WrongKeyOrModifiedCiphertextException
     */
    public function edit(int $id, Request $request)
    {
        $item = GatewayMerchant::find($id);
        $isValidate = true;

        try {
            $paymentConfig = Payments::forConfig($item->alias);
            if (!$paymentConfig) {
                throw new RuntimeException("Конфигурация для {$item->alias} не найдена.");
            }

//            // Проверить
//            $editorFilePath = storage_path('/app/iexexchanger/editor.json');
//            if (!File::exists($editorFilePath)) {
//                throw new RuntimeException("Файл не найден: $editorFilePath");
//            }
//            $editorTextarea = json_decode(File::get($editorFilePath), true);


            $enabledCallback = (bool) $paymentConfig->callbacksEnabled();

            $merchantInputs = $paymentConfig->inputsGroup('merchant');
            $callback = is_array($merchantInputs['callback'] ?? null) ? $merchantInputs['callback'] : [];

            $form = app(GatewayInputsFormBuilder::class)->buildMerchantForm($item);

            $connectionFields   = $form['connectionFields'];
            $optionsFields      = $form['optionsFields'];

            // Нужно ли вообще формировать return urls
            $sendReturnUrls = (bool) ($callback['send_return_urls'] ?? false);

            if ($enabledCallback && $sendReturnUrls) {

                // route_name — куда придет callback (IPN)
                $routeName = (string) ($callback['route_name'] ?? '');

                if ($routeName !== '') {
                    $callbacksUrls['return_url'] = route($routeName, [$item->alias, '']);
                }

                if ((bool)($callback['success_url_enabled'] ?? true)) {
                    $callbacksUrls['success_url'] = rtrim((string)config('app.frontend_url'), '/') . '/payment_status/success';
                }

                if ((bool)($callback['fail_url_enabled'] ?? true)) {
                    $callbacksUrls['fail_url'] = rtrim((string)config('app.frontend_url'), '/') . '/payment_status/fail';
                }
            }

            $callback = (array) ($paymentConfig->inputsGroup('merchant')['callback'] ?? []);

            $isReturnUrl = (int) (
                (bool) ($callback['enabled'] ?? false)
            );
        } catch (\Throwable $exception) {
            $isValidate = false;
            Log::error('Ошибка при редактировании мерчанта', [
                'error' => $exception->getMessage(),
                'merchant_id' => $id,
            ]);
        }

        $currencies = Currency::active()->get()->map(fn($currency) => [
            'id' => $currency->id,
            'value' => $currency->tech_name
        ]);

        $secrets = app(SecretAccessManagerInterface::class);
        $isMerchantAccessGranted = $secrets->canView('merchant');

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'name' => $item->name,
                'status' => (bool)$item->status,
                'is_config_done' => $item->is_config_done,
                'alias' => $item->alias,
                'allow_ip_address' => $item->allow_ip_address,
                'security_hash' => $item->security_hash,
                'instruction_payment' => $item->instruction_payment,
                'comment' => $item->comment,
                'amount_fault' => $item->amount_fault,
                'day_limit_amount_merchant' => $item->day_limit_amount_merchant,
                'month_limit_amount_merchant' => $item->month_limit_amount_merchant,
                'min_amount_for_per_order' => $item->min_amount_for_per_order,
                'max_amount_for_per_order' => $item->max_amount_for_per_order,
                'day_limit_merchant' => $item->day_limit_merchant,
                'month_limit_merchant' => $item->month_limit_merchant,
                'is_enable_merchant_button' => $item->is_enable_merchant_button,
                'is_deny_ip_address' => $item->is_deny_ip_address,
                'pay_amount' => $item->pay_amount,
                'credit_amount' => $item->credit_amount,
                'priority' => $item->priority,
                'status_invalid_min_amount' => $item->status_invalid_min_amount,
                'status_invalid_max_amount' => $item->status_invalid_max_amount,
                'ids_currencies' => $item->currencies->pluck('id'),
            ],
            'currencies' => $currencies,
            'connectionFields' => $connectionFields ?? [],
            'optionsFields' => $optionsFields ?? [],
            'isReturnUrl' => $isReturnUrl ?? [],
            'callbacksUrls' => $callbacksUrls ?? [],
            'isValidate' => $isValidate,
            'isMerchantAccessGranted' => $isMerchantAccessGranted,
        ]);
    }

    /**
     * Обработка и обновление мерчанта
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $item = GatewayMerchant::findOrFail($id);

        // Валидация данных
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'connectionFields' => 'nullable|array',
            'currencies' => 'nullable|array',
            'direction_exchange' => 'nullable|array',
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

        $hiddenKeys = collect($paymentConfig->fields('merchant'))
            ->filter(fn (array $field) => (bool) ($field['is_hidden'] ?? false))
            ->pluck('key')
            ->filter()
            ->values()
            ->all();

        $vaultUpdateResult = null;

        // Транзакционное обновление
        try {
            \DB::transaction(function () use ($item, $validated, $hiddenKeys, $request, $paymentConfig, &$vaultUpdateResult) {
                // Safe partial vault merge: blank/masked secrets do not overwrite;
                // outbound credential changes require confirm_outbound_credential_change.
                $vaultUpdateResult = app(SafeGatewayVaultUpdater::class)->updateMerchantVault(
                    merchant: $item,
                    connectionFields: $validated['connectionFields'] ?? [],
                    hiddenKeys: $hiddenKeys,
                    fieldDefs: $paymentConfig->fields('merchant'),
                    confirmOutboundCredentialChange: $request->boolean('confirm_outbound_credential_change'),
                );

                // Сохранение основных данных
                $item->update([
                    'name' => $validated['name'],
                    'status' => $request->get('status', 0),
                    'security_hash' => $request->get('security_hash'),
                    'allow_ip_address' => $request->get('allow_ip_address'),
                    'is_deny_ip_address' => $request->get('is_deny_ip_address', 0),
                    'day_limit_merchant' => $request->get('day_limit_merchant', 0),
                    'amount_fault' => $request->get('amount_fault', 0),
                    'day_limit_amount_merchant' => $request->get('day_limit_amount_merchant', 0),
                    'is_enable_merchant_button' => $request->get('is_enable_merchant_button', 0),
                    'pay_amount' => $request->get('pay_amount', 0),
                    'credit_amount' => $request->get('credit_amount', 0),
                    'is_config_done' => $request->get('is_config_done', 0),
                    'month_limit_amount_merchant' => $request->get('month_limit_amount_merchant', 0),
                    'min_amount_for_per_order' => $request->get('min_amount_for_per_order', 0),
                    'max_amount_for_per_order' => $request->get('max_amount_for_per_order', 0),
                    'month_limit_merchant' => $request->get('month_limit_merchant', 0),
                    'priority' => $request->get('priority', 0),
                    'status_invalid_min_amount' => $request->get('status_invalid_min_amount', 0),
                    'status_invalid_max_amount' => $request->get('status_invalid_max_amount', 0),
                    'instruction_payment' => $request->get('instruction_payment'),
                    'comment' => $request->get('comment'),
                ]);

                // Обновление связей
                $item->currencies()->sync($validated['currencies'] ?? []);
               // $item->direction_exchange()->sync($validated['direction_exchange'] ?? []);


                // options_fields из config.php (merchant)
                $defaultOptionFields = collect($paymentConfig->optionFields('merchant'))
                    ->keyBy('key');

                if ($defaultOptionFields->isNotEmpty()) {

                    $inputOptionFields = (array) ($request->optionFields ?? []);

                    $optionsFields = collect($inputOptionFields)
                        ->filter(fn ($value, $key) => $defaultOptionFields->has((string) $key))
                        ->map(function ($value, $key) use ($defaultOptionFields) {

                            $field = (array) $defaultOptionFields->get((string) $key, []);

                            $valueType = $field['value_type'] ?? null;

                            return is_string($valueType) && $valueType !== ''
                                ? variableStrictValue($value, $valueType)
                                : $value;
                        });

                    $item->update([
                        'ext_options' => $optionsFields->toArray(),
                    ]);
                }
            });

        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 1,
                'message' => collect($exception->errors())->flatten()->first() ?: 'Credential update rejected',
                'errors' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            Log::error('Ошибка при обновлении мерчанта', [
                'error' => $exception->getMessage(),
                'merchant_id' => $id,
            ]);

            return response()->json([
                'status' => 1,
                'message' => 'Ошибка обновления мерчанта: ' . $exception->getMessage(),
            ], 500);
        }

        $message = "{$item->name} успешно обновлён";
        if (is_array($vaultUpdateResult) && !empty($vaultUpdateResult['webhook_only'])) {
            $message .= '. Outbound API credentials will remain unchanged.';
        }

        return response()->json([
            'status' => 0,
            'message' => $message,
            'credential_change_summary' => is_array($vaultUpdateResult) ? ($vaultUpdateResult['summary'] ?? null) : null,
        ]);
    }

    /**
     * Удаляем мерчант
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy( int $id)
    {
        $item = GatewayMerchant::findOrFail($id);
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
     * Проверка секретного ключа и выдача временного доступа.
     * Из фронтенда приходит ключ (key|secret). Если ключ совпадает с тем,
     * что находится в .env/config, в сессию записывается разрешение с TTL (в часах).
     *
     * ENV/Config параметры (с безопасными дефолтами):
     * - MERCHANT_ACCESS_SECRET — сам секретный ключ
     * - MERCHANT_ACCESS_SECRET_TTL — срок действия доступа в часах (по умолчанию 6)
     *
     * Также можно определить их в config/iexexchanger.php как:
     * config('iexexchanger.gateways.security.merchant_access_secret')
     * config('iexexchanger.gateways.security.merchant_access_secret_ttl')
     */
    public function secretKeyIndex(Request $request): JsonResponse
    {
        /** @var SecretAccessManagerInterface $secrets */
        $secrets = app(SecretAccessManagerInterface::class);

        // закрытие доступа
        if ($request->boolean('revoke')) {
            $secrets->revoke('merchant');

            return response()->json([
                'status'  => 0,
                'message' => __('Доступ закрыт.'),
            ]);
        }

        // входной ключ
        $inputKey = trim((string) ($request->input('code') ?? $request->input('token') ?? ''));
        if ($inputKey === '') {
            return response()->json([
                'status'  => 1,
                'message' => __('Секретный ключ не передан.'),
            ], 422);
        }

        // ожидаемый ключ
        $expectedKey = (string) (config('iexexchanger.gateways.security.merchant_access_secret') ?? '');
        $ttlHours    = (int) (config('iexexchanger.gateways.security.merchant_access_secret_ttl') ?? 6);

        if ($expectedKey === '') {
            Log::warning('merchant_access_secret не задан в конфиге. Проверка невозможна.');
            return response()->json([
                'status'  => 1,
                'message' => __('Система временно недоступна. Обратитесь к администратору.'),
            ], 503);
        }

        // безопасное сравнение
        if (!hash_equals($expectedKey, $inputKey)) {
            $secrets->revoke('merchant');

            return response()->json([
                'status'  => 1,
                'message' => __('Неверный секретный ключ.'),
            ], 401);
        }

        // выдаём доступ
        $ttlHours = max($ttlHours, 1);
        $ttlSeconds = $ttlHours * 3600;

        $secrets->grant('merchant', $ttlSeconds);

        // если хочешь вернуть expires_at — можно получить из session внутри менеджера,
        // но проще вычислить так же:
        $expiresAt = now()->addSeconds($ttlSeconds)->toIso8601String();

        return response()->json([
            'status'     => 0,
            'message'    => __('Доступ разрешён.'),
            'expires_at' => $expiresAt,
            'ttl_hours'  => $ttlHours,
        ]);
    }
}
