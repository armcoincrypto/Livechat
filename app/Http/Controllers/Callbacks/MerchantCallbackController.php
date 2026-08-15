<?php

declare(strict_types=1);

namespace App\Http\Controllers\Callbacks;

use App\Gateways\Crypto\Kobbopay\Services\KobbopayInboundWebhookService;
use App\Http\Controllers\Controller;
use iEXPackages\Payments\Callback\DTO\CallbackHttpResult;
use iEXPackages\Payments\Callback\Services\MerchantIncomingCallbackService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MerchantCallbackController
 *
 * Единая точка входа для входящих уведомлений от платёжных провайдеров.
 *
 * Контроллер не содержит бизнес-логики:
 * - не проверяет hash / IP
 * - не ищет заявку
 * - не меняет статусы
 *
 * Он только:
 * 1) принимает HTTP-запрос,
 * 2) собирает входные данные,
 * 3) вызывает сервис,
 * 4) возвращает ответ провайдеру.
 */
final class MerchantCallbackController extends Controller
{
    public function __construct(
        private readonly MerchantIncomingCallbackService $service,
        private readonly KobbopayInboundWebhookService $kobbopayWebhookService,
    ) {}

    /**
     * Callback по ID заявки.
     *
     * Используй, когда провайдер присылает идентификатор заявки в payload
     * (ключ берётся из config.php шлюза: inputs.merchant.callback.order_id_field).
     *
     * Пример:
     *  POST /callbacks/v1/receive/{payment_system}/{security_hash?}
     */
    public function receive(Request $request, string $payment_system, ?string $security_hash = null): Response
    {
        $result = $this->service->handleByOrderId(
            gatewayAlias: (string) $payment_system,
            securityHashFromUrl: (string) ($security_hash ?? ''),
            payload: $this->payload($request),
            ip: (string) $request->ip(),
            userAgent: (string) ($request->userAgent() ?? ''),
        );

        return $this->toResponse($result);
    }

    /**
     * Webhook по external_id.
     *
     * Используй, когда провайдер присылает внешний ID операции,
     * а мы должны найти заявку через MerchantTransactionData.id_from_merchant.
     *
     * Пример:
     *  POST /callbacks/v1/webhook/{payment_system}/{security_hash?}
     */
    public function webhook(Request $request, string $payment_system, ?string $security_hash = null): Response
    {
        // Kobbopay uses signed raw-body webhooks (X-Kobbopay-*) and must return JSON.
        if (strtolower((string) $payment_system) === 'kobbopay') {
            return $this->kobbopayWebhookService->handleHttp(
                request: $request,
                securityHashFromUrl: (string) ($security_hash ?? ''),
            );
        }

        $result = $this->service->handleByExternalId(
            gatewayAlias: (string) $payment_system,
            securityHashFromUrl: (string) ($security_hash ?? ''),
            payload: $this->payload($request),
            ip: (string) $request->ip(),
            userAgent: (string) ($request->userAgent() ?? ''),
        );

        return $this->toResponse($result);
    }

    /**
     * Универсально достаёт payload независимо от content-type:
     * - application/json
     * - application/x-www-form-urlencoded
     * - multipart/form-data
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        return (array) $request->all();
    }

    /**
     * Преобразует результат сервиса в HTTP-ответ.
     */
    private function toResponse(CallbackHttpResult $result): Response
    {
        return response((string) $result->body, (int) $result->httpStatus)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
