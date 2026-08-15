<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Callback\Services;

use iEXPackages\Payments\Callback\DTO\CallbackHttpResult;
use iEXPackages\Payments\Callback\Traits\CallbackConfigTrait;
use iEXPackages\Payments\Callback\Traits\CallbackInputTrait;
use iEXPackages\Payments\Callback\Traits\CallbackPaymentHandlingTrait;
use iEXPackages\Payments\Callback\Traits\CallbackSecurityChecksTrait;
use iEXPackages\Payments\Callback\Traits\CallbackTargetLookupTrait;
use iEXPackages\Payments\Core\Engine\GatewayManager;
use iEXPackages\Payments\Logging\Services\MerchantFlowLogger;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MerchantIncomingCallbackService
 *
 * Центральный обработчик входящих callback/webhook уведомлений от мерчант-провайдеров.
 *
 * Задачи сервиса:
 * 1) Нормализовать входные параметры (alias, hash, payload, ip, userAgent).
 * 2) Проверить, что шлюз зарегистрирован в системе (старые/удалённые alias не должны ломать callback).
 * 3) Прочитать config.php и понять, включены ли callback’и.
 * 4) Найти цель обработки:
 *    - режим "by_order_id"  → ищем Task по order_id_field из callback-конфига;
 *    - режим "by_external_id" → ищем MerchantTransactionData по external_id_field/правилу.
 * 5) Применить проверки безопасности:
 *    - совпадение alias;
 *    - security_hash из URL;
 *    - IP whitelist (если включён и заполнен).
 * 6) Если шлюз поддерживает complete_purchase — выполнить операцию и финализировать заявку.
 *
 * Важно:
 * - Для callback’ов почти всегда возвращаем HTTP 200 "OK", чтобы провайдер не ретраил бесконечно.
 * - HTTP 403 возвращаем только там, где это действительно "жёсткая" блокировка (неверный hash, IP не разрешён).
 */
final class MerchantIncomingCallbackService
{
    /**
     * Режим поиска заявки через order_id_field.
     */
    public const LOOKUP_BY_ORDER_ID = 'order_id_field';

    /**
     * Режим поиска заявки через внешний id (id_from_merchant).
     */
    public const LOOKUP_BY_EXTERNAL_ID = 'by_external_id';

    use CallbackInputTrait;
    use CallbackConfigTrait;
    use CallbackTargetLookupTrait;
    use CallbackSecurityChecksTrait;
    use CallbackPaymentHandlingTrait;

    public function __construct(
        private readonly GatewayManager $gatewayManager,
        private readonly MerchantFlowLogger $flowLogger,
    ) {}

    /**
     * Callback “по заявке”.
     * Типичный случай: провайдер присылает order_id/ac_order_id/merchant_id и т.п.
     *
     * @param array<string,mixed> $payload
     */
    public function handleByOrderId(
        string $gatewayAlias,
        string $securityHashFromUrl,
        array $payload,
        string $ip,
        string $userAgent,
    ): CallbackHttpResult {
        return $this->handleIncomingCallback(
            gatewayAlias: $gatewayAlias,
            securityHashFromUrl: $securityHashFromUrl,
            payload: $payload,
            ip: $ip,
            userAgent: $userAgent,
            lookupMode: self::LOOKUP_BY_ORDER_ID,
        );
    }

    /**
     * Webhook “по внешнему ID”.
     * Типичный случай: провайдер присылает внешний идентификатор транзакции (external_id),
     * а мы находим заявку через MerchantTransactionData.id_from_merchant.
     *
     * @param array<string,mixed> $payload
     */
    public function handleByExternalId(
        string $gatewayAlias,
        string $securityHashFromUrl,
        array $payload,
        string $ip,
        string $userAgent,
    ): CallbackHttpResult {
        return $this->handleIncomingCallback(
            gatewayAlias: $gatewayAlias,
            securityHashFromUrl: $securityHashFromUrl,
            payload: $payload,
            ip: $ip,
            userAgent: $userAgent,
            lookupMode: self::LOOKUP_BY_EXTERNAL_ID,
        );
    }

    /**
     * Общая реализация обработки callback/webhook.
     *
     * @param array<string,mixed> $payload
     * @param self::LOOKUP_* $lookupMode
     */
    private function handleIncomingCallback(
        string $gatewayAlias,
        string $securityHashFromUrl,
        array $payload,
        string $ip,
        string $userAgent,
        string $lookupMode,
    ): CallbackHttpResult {

        $alias = $this->normalizeAlias($gatewayAlias);
        $securityHashFromUrl = trim((string) $securityHashFromUrl);

        // 0) Базовая валидация входных параметров
        if ($alias === '') {
            return new CallbackHttpResult(400, 'Bad request');
        }

        // 1) Если alias неизвестен (старый провайдер/удалённый шлюз) — молча OK.
        // Это предотвращает падения и бесконечные ретраи со стороны провайдера.
        if (!$this->gatewayManager->hasAlias($alias)) {
            $this->flowLogger->info(
                event: 'callback_skipped_unknown_alias',
                task: null,
                merchant: null,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'gateway_alias' => $alias,
                    'context' => [
                        'payload' => $this->payloadPreview($payload),
                    ],
                ],
                message: 'Уведомление получено, но данный платежный сервис в системе не поддерживается. Уведомление пропущено.',
                stage: 'validate',
                flow: 'callback'
            );

            return new CallbackHttpResult(200, 'OK');
        }

        // 2) Статический config.php шлюза
        $gatewayConfig = $this->tryGetGatewayConfig($alias);

        if (!$gatewayConfig) {
            $this->flowLogger->warning(
                event: 'callback_skipped_no_gateway_config',
                task: null,
                merchant: null,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'gateway_alias' => $alias,
                    'context' => [
                        'payload' => $this->payloadPreview($payload),
                    ],
                ],
                message: 'Уведомление получено, но конфигурация шлюза недоступна. Уведомление пропущено.',
                stage: 'config',
                flow: 'callback'
            );

            return new CallbackHttpResult(200, 'OK');
        }

        // 3) Callback конфиг + проверка enabled
        $callbackConfig = $this->getMerchantCallbackConfig($gatewayConfig);
        if (!(bool) ($callbackConfig['enabled'] ?? false)) {
            // callback выключен в config.php → просто OK
            return new CallbackHttpResult(200, 'OK');
        }

        // 4) Находим цель: Task + Merchant + (опционально) MerchantTransactionData
        [$task, $merchant, $merchantTxData] = $this->resolveCallbackTarget(
            gatewayConfig: $gatewayConfig,
            callbackConfig: $callbackConfig,
            alias: $alias,
            payload: $payload,
            lookupMode: $lookupMode
        );

        if (!$task || !$merchant) {
            // ничего не нашли → OK
            return new CallbackHttpResult(200, 'OK');
        }

        // 5) Лог: уведомление привязали к заявке
        $this->flowLogger->info(
            event: 'callback_received',
            task: $task,
            merchant: $merchant,
            ctx: [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'gateway_alias' => $alias,
                'context' => [
                    'lookup_mode' => $lookupMode,
                    'payload' => $this->payloadPreview($payload),
                ],
            ],
            message: 'Получено уведомление от платежного сервиса по заявке.',
            stage: 'lookup',
            flow: 'callback'
        );

        // 6) Проверки безопасности (hash/ip/alias/callback rules)
        $securityResult = $this->checkSecurityRules(
            task: $task,
            merchant: $merchant,
            gatewayAlias: $alias,
            securityHashFromUrl: $securityHashFromUrl,
            ip: $ip,
            callbackConfig: $callbackConfig
        );

        // checkSecurityRules может вернуть CallbackHttpResult (например 403 Invalid ip/hash)
        if ($securityResult instanceof CallbackHttpResult) {
            return $securityResult;
        }

        // 7) Если у шлюза нет complete_purchase — по callback ничего не подтверждаем
        // (мягко пропускаем, чтобы провайдер не долбил ретраями).
        if (!$this->supportsCompletePurchase($gatewayConfig)) {
            $this->flowLogger->warning(
                event: 'callback_skipped_no_complete_purchase',
                task: $task,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'gateway_alias' => $alias,
                ],
                message: 'Уведомление получено, но этот платежный сервис не поддерживает подтверждение оплаты через callback. Уведомление пропущено.',
                stage: 'process',
                flow: 'callback'
            );

            return new CallbackHttpResult(200, 'OK');
        }

        // 8) Основная обработка (complete_purchase + статусы/суммы/логирование)
        try {

            return $this->processPaymentCallback(
                gatewayConfig: $gatewayConfig,
                callbackConfig: $callbackConfig,
                task: $task,
                merchant: $merchant,
                merchantTxData: $merchantTxData,
                payload: $payload,
                ip: $ip,
                userAgent: $userAgent
            );

        } catch (Throwable $e) {
            Log::error('Merchant callback processing failed', [
                'task_id' => $task->id,
                'gateway_alias' => $alias,
                'lookup_mode' => $lookupMode,
                'error' => $e->getMessage(),
            ]);

            $this->flowLogger->error(
                event: 'callback_failed_exception',
                task: $task,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'gateway_alias' => $alias,
                    'context' => [
                        'lookup_mode' => $lookupMode,
                        'error' => $e->getMessage(),
                    ],
                ],
                message: 'Ошибка при обработке уведомления от платежного сервиса. Уведомление пропущено, чтобы избежать повторных запросов.',
                stage: 'final',
                flow: 'callback'
            );

            // Для callback безопаснее отвечать 200 OK, чтобы не получить бесконечные ретраи.
            return new CallbackHttpResult(200, 'OK');
        }
    }

    /**
     * Безопасный preview payload для логов.
     *
     * Задача: дать максимум полезной информации для диагностики,
     * но не утащить в БД секреты и не раздуть запись.
     *
     * - маскируем чувствительные ключи (token, key, secret, signature, authorization и т.п.)
     * - строки режем до 500 символов
     * - массивы/объекты не раскрываем глубоко (заменяем на описание)
     * - сохраняем первые 50 ключей в исходном порядке
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function payloadPreview(array $payload): array
    {
        $needles = [
            'authorization',
            'token',
            'api_key',
            'api-key',
            'private_key',
            'secret',
            'signature',
            'x-signature',
            'jwt',
            'access-token',
            'access_token',
            'password',
            'hash',
        ];

        $out = [];
        $i = 0;

        foreach ($payload as $k => $v) {
            if ($i >= 50) {
                $out['__truncated__'] = true;
                break;
            }
            $i++;

            $key = is_string($k) ? $k : (string) $k;
            $keyLower = mb_strtolower($key, 'UTF-8');

            $isSensitive = false;
            foreach ($needles as $needle) {
                if (str_contains($keyLower, $needle)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $out[$key] = '***';
                continue;
            }

            if (is_scalar($v) || $v === null) {
                $str = (string) $v;
                if (mb_strlen($str, 'UTF-8') > 500) {
                    $str = mb_substr($str, 0, 500, 'UTF-8') . '…';
                }
                $out[$key] = $str;
                continue;
            }

            if (is_array($v)) {
                $out[$key] = 'array(' . count($v) . ')';
                continue;
            }

            $out[$key] = 'object(' . $v::class . ')';
        }

        return $out;
    }
}
