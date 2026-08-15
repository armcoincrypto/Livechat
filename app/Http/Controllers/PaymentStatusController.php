<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatusEnum;
use App\Models\MerchantTransactionData;
use iEXPackages\Payments\Core\Contracts\RedirectResponseInterface;
use iEXPackages\Payments\Logging\Services\MerchantFlowLogger;
use iEXPackages\Payments\Payments;
use iEXPackages\TagProcessors\TagProcessors;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Psr\SimpleCache\InvalidArgumentException;

class PaymentStatusController extends Controller
{
    /**
     * Переход по ссылке оплаты (checkout).
     *
     * Важные задачи:
     * - проверяем валидность ссылки
     * - находим MerchantTransactionData и связанную заявку
     * - проверяем состояние заявки и ограничения (IP/alias)
     * - создаём “платёж у провайдера” и перенаправляем клиента
     * @throws InvalidArgumentException
     */
    public function checkout(Request $request, MerchantFlowLogger $flowLogger): RedirectResponse|\Illuminate\Http\Response
    {
        $ip = (string) $request->ip();
        $ua = (string) $request->userAgent();

        $validator = Validator::make($request->route()->parameters(), [
            'hash' => ['required'],
        ]);

        if ($validator->fails()) {
            abort(404);
        }

        $hashId = (string) $request->route('hash');

        // 2) Ищем MerchantTransactionData по checkout_id
        $merchantData = MerchantTransactionData::query()
            ->where('is_checkout_url', 1)
            ->where('ext_data->checkout->id', $hashId)
            ->first();

        if (!$merchantData) {
            abort(404);
        }

        // 3) Заявка
        $order = $merchantData->tasks;
        if (!$order) {
            abort(404);
        }

        $flowLogger->info(
            event: 'checkout.opened',
            task: $order,
            merchant: $merchantData->merchant ?? null,
            ctx: [
                'ip' => $ip,
                'user_agent' => $ua,
                'checkout_id' => $hashId,
                'context' => [
                    'checkout_id' => $hashId,
                ],
            ],
            message: 'Пользователь открыл страницу оплаты',
            stage: 'open',
            flow: 'checkout'
        );

        // 4) Разрешаем checkout только в нужных состояниях
        // У тебя было: status=2 and merchant_status=0
        if ((int) $order->status !== 2 || (int) $order->merchant_status !== 0) {
            $flowLogger->security(
                event: 'checkout.blocked_wrong_state',
                task: $order,
                merchant: $merchantData->merchant ?? null,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $ua,
                    'checkout_id' => $hashId,
                    'context' => [
                        'task_status' => (int) $order->status,
                        'merchant_status' => (int) $order->merchant_status,
                    ],
                ],
                message: 'Оплата по этой ссылке недоступна: заявка уже в другом состоянии',
                stage: 'state',
                flow: 'checkout'
            );

            abort(403, 'Доступ запрещен');
        }

        // 5) Инициализируем транзакцию
        $transaction = TransactionFacade::init($order);

        // 6) Если клиент слишком поздно открыл — отклоняем
        $expiredAt = Carbon::parse($order->created_at)->addSeconds((int) iEXSetting('max_time_task'));
        if (Carbon::now()->greaterThan($expiredAt)) {

            $flowLogger->warning(
                event: 'checkout.expired',
                task: $order,
                merchant: $merchantData->merchant ?? null,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $ua,
                    'checkout_id' => $hashId,
                    'context' => [
                        'expired_at' => $expiredAt->toIso8601String(),
                        'now' => now()->toIso8601String(),
                    ],
                ],
                message: 'Ссылка оплаты устарела, заявка отклонена',
                stage: 'timeout',
                flow: 'checkout'
            );


            try {
                $transaction->reject();
            } catch (\Throwable $e) {
                Log::warning('Checkout reject failed', [
                    'task_id' => $order->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            abort(410, 'Ссылка устарела');
        }

        // 7) Получаем мерчант
        $merchant = $transaction->getMerchant();

        if (!$merchant) {
            abort(404);
        }

        // 8) Защита: mtd должен относиться к тому же alias мерчанта
        if ((string)$merchantData->service_name !== (string)$merchant->alias) {

            $flowLogger->security(
                event: 'checkout.blocked_alias_mismatch',
                task: $order,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $ua,
                    'checkout_id' => $hashId,
                    'context' => [
                        'mtd_alias' => (string) $merchantData->service_name,
                        'merchant_alias' => (string) $merchant->alias,
                    ],
                ],
                message: 'Оплата заблокирована: ссылка не соответствует выбранному платежному сервису',
                stage: 'security',
                flow: 'checkout'
            );

            abort(403, 'Доступ запрещен');
        }

        // 9) Защита IP (deny_ip_address)
        if ((int)($merchant->is_deny_ip_address ?? 0) === 1) {
            if ((string)$request->ip() !== (string)$order->ip) {

                $flowLogger->security(
                    event: 'checkout.blocked_ip_mismatch',
                    task: $order,
                    merchant: $merchant,
                    ctx: [
                        'ip' => $ip,
                        'user_agent' => $ua,
                        'checkout_id' => $hashId,
                        'context' => [
                            'order_ip' => (string) $order->ip,
                            'request_ip' => $ip,
                        ],
                    ],
                    message: 'Оплата заблокирована: попытка открыть ссылку с другого IP-адреса',
                    stage: 'security',
                    flow: 'checkout'
                );


                return redirect()->to('/');
            }
        }


        // 10) Description (твой код оставляю)
        if (!empty($merchant->comment))
        {
            $app = app(TagProcessors::class);

            $description = $app->setProcessor('order')
                ->setText($merchant->comment)
                ->setData($order)
                ->process()
                ->getText();
        } else {
            $description = $transaction->merchantDescription($request->getHttpHost(), $merchant->alias);
        }

        // 11) URLs
        $frontend  = rtrim((string) config('app.frontend_url'), '/');
        $cancelUrl = $frontend . '/payment_status/fail';
        $returnUrl = $frontend . '/payment_status/success';
        $noticeRoute = 'merchant.receive_money';

        $hasCompletePurchase = false;
        $supportsPolling = false;

        try {
            $cfg = Payments::forConfig($merchant->alias);
            $route = $cfg->callbackRouteName();

            $op = $cfg->operationConfig('complete_purchase');
            $hasCompletePurchase = is_array($op) && !empty($op['request_class']);

            $supportsPolling = $cfg->supportsPollingIncoming();

            if (!empty($route) && $cfg->callbacksEnabled()) {
                $noticeRoute = $route;
            }
        } catch (\Throwable) {

            $hasCompletePurchase = false;
            $supportsPolling = false;
        }



        $notifyUrl = route($noticeRoute, [$merchant->alias, $merchant->security_hash]);

        // 12) Сумма
        $inPrice = match ((int)$merchant->pay_amount) {
            1 => $order->give_price_with_comm_pay,
            2 => $order->give_price_default,
            default => $order->give_price,
        };

        $flowLogger->info(
            event: 'checkout.payment_prepare',
            task: $order,
            merchant: $merchant,
            ctx: [
                'ip' => $ip,
                'user_agent' => $ua,
                'checkout_id' => $hashId,
                'context' => [
                    'amount' => (string) $inPrice,
                    'currency' => Str::upper((string) $transaction->getCodeIn()->name),
                ],
            ],
            message: 'Подготовлена оплата: формируем данные для перехода на страницу оплаты',
            stage: 'prepare',
            flow: 'checkout'
        );

        // 13) Платёж у провайдера: повторно используем существующий инвойс или создаём новый
        $extData = is_array($merchantData->ext_data) ? $merchantData->ext_data : [];
        $storedExternalId = trim((string) ($merchantData->id_from_merchant ?? ''));

        $paymentResponse = null;
        $externalId = '';

        if ($storedExternalId !== '' && $extData === []) {
            Log::info('creating_new_invoice', [
                'reason' => 'empty_ext_data',
                'task_id' => $order->id,
                'checkout_id' => $hashId,
            ]);
        }

        if ($storedExternalId !== '' && $extData !== []) {
            Log::info('reuse_existing_invoice', [
                'task_id' => $order->id,
                'checkout_id' => $hashId,
                'merchant_alias' => (string) $merchant->alias,
            ]);

            $externalId = $storedExternalId;

            $redirectBlock = isset($extData['redirect']) && is_array($extData['redirect'])
                ? $extData['redirect']
                : [];
            $providerUrl = trim((string) ($redirectBlock['provider_url'] ?? ''));
            $redirectMethod = strtoupper(trim((string) ($redirectBlock['method'] ?? 'GET')));
            $redirectFormData = isset($redirectBlock['data']) && is_array($redirectBlock['data'])
                ? $redirectBlock['data']
                : [];

            if ($providerUrl !== '' && in_array($redirectMethod, ['GET', 'POST'], true)) {
                $isPostRedirect = $redirectMethod === 'POST';
                $hasRedirectFormData = $isPostRedirect && $redirectFormData !== [];

                $flowLogger->info(
                    event: 'checkout.redirect',
                    task: $order,
                    merchant: $merchant,
                    ctx: [
                        'ip' => $ip,
                        'user_agent' => $ua,
                        'checkout_id' => $hashId,
                        'external_id' => $externalId !== '' ? $externalId : null,
                        'context' => [
                            'redirect_method' => $redirectMethod,
                            'redirect_url' => $providerUrl,
                            'is_form' => (bool) $hasRedirectFormData,
                            'reuse' => true,
                        ],
                    ],
                    message: $hasRedirectFormData
                        ? 'Открываем страницу оплаты (форма оплаты)'
                        : 'Открываем страницу оплаты (переход по ссылке)',
                    stage: 'redirect',
                    flow: 'checkout'
                );

                if ($isPostRedirect) {
                    $order->update(['merchant_status' => 1]);
                } else {
                    $order->update(['started_at' => Carbon::now()->toDateTimeString()]);
                }

                if ($hasCompletePurchase) {
                    $nextStatus = TaskStatusEnum::PROCESSING_PAYMENT->value;
                } else {
                    $nextStatus = ($externalId !== '' && $supportsPolling)
                        ? TaskStatusEnum::WAITING_HANDLE->value
                        : TaskStatusEnum::MERCHANT_CONFIRMATION->value;
                }

                $transaction->setStatus($nextStatus);

                if ($externalId !== '' && $supportsPolling) {
                    $order->update([
                        'is_bot' => 1,
                        'is_auto_check_pay' => 1,
                    ]);
                }

                if ($redirectMethod === 'GET') {
                    return redirect()->away($providerUrl);
                }

                return $this->checkoutPostFormRedirectResponse($providerUrl, $redirectFormData);
            }

            $flowLogger->warning(
                event: 'checkout.no_redirect',
                task: $order,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $ua,
                    'checkout_id' => $hashId,
                    'external_id' => $externalId !== '' ? $externalId : null,
                    'context' => [
                        'reuse' => true,
                    ],
                ],
                message: 'Платежный сервис не вернул страницу оплаты. Открываем страницу заявки.',
                stage: 'final',
                flow: 'checkout'
            );

            return redirect()->to($frontend . '/order/' . $order->public_id);
        }

        Log::info('creating_new_invoice', [
            'task_id' => $order->id,
            'checkout_id' => $hashId,
            'merchant_alias' => (string) $merchant->alias,
        ]);

        $paymentRequest  = Payments::forMerchant($merchant);

        try {
            $paymentResponse = $paymentRequest->purchase([
                'notifyUrl'     => $notifyUrl,
                'cancelUrl'     => $cancelUrl,
                'returnUrl'     => $returnUrl,
                'amount'        => (string) $inPrice,
                'currency'      => Str::upper((string) $transaction->getCodeIn()->name),
                'transactionId' => (string) $order->id,
                'description'   => (string) $description,
            ])->withTask($order)->send();
        } catch (\Throwable $e) {
            $flowLogger->error(
                event: 'checkout.payment_failed',
                task: $order,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $ua,
                    'checkout_id' => $hashId,
                    'context' => [
                        'error' => 'provider_request_failed',
                        'exception_class' => $e::class,
                    ],
                ],
                message: 'Не удалось подготовить оплату у платежного сервиса',
                stage: 'payment',
                flow: 'checkout'
            );

            Log::warning('checkout.payment_failed_graceful_redirect', [
                'task_id'         => $order->id,
                'public_id'       => $order->public_id,
                'checkout_id'     => $hashId,
                'merchant_alias'  => (string) $merchant->alias,
                'exception_class' => $e::class,
            ]);

            $ext = is_array($merchantData->ext_data) ? $merchantData->ext_data : [];
            $prevInvoiceMeta = is_array($ext['invoice_create'] ?? null) ? $ext['invoice_create'] : [];
            $ext['invoice_create'] = array_merge($prevInvoiceMeta, [
                'attempts'           => (int) ($prevInvoiceMeta['attempts'] ?? 0) + 1,
                'last_failure_at'    => Carbon::now()->toIso8601String(),
                'last_failure_class' => $e::class,
            ]);
            $merchantData->update(['ext_data' => $ext]);

            return redirect()->to($frontend . '/order/' . $order->public_id);
        }

        // 14) Сохраняем externalId (если есть)
        if (method_exists($paymentResponse, 'getExternalId')) {
            $externalId = (string)($paymentResponse->getExternalId() ?? '');
            if ($externalId !== '') {
                $merchantData->update(['id_from_merchant' => $externalId]);
            }
        }


        // 14) Редирект (GET или POST форма) — единая логика
        if ($paymentResponse instanceof RedirectResponseInterface && $paymentResponse->isRedirect()) {
            $method  = strtoupper((string) $paymentResponse->getRedirectMethod());
            $isPost  = $method === 'POST';
            $hasData = $isPost && !empty($paymentResponse->getRedirectData());

            $flowLogger->info(
                event: 'checkout.redirect',
                task: $order,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'user_agent' => $ua,
                    'checkout_id' => $hashId,
                    'external_id' => $externalId !== '' ? $externalId : null,
                    'context' => [
                        'redirect_method' => $method,
                        'redirect_url' => (string) ($paymentResponse->getRedirectUrl() ?? ''),
                        'is_form' => (bool) $hasData,
                    ],
                ],
                message: $hasData
                    ? 'Открываем страницу оплаты (форма оплаты)'
                    : 'Открываем страницу оплаты (переход по ссылке)',
                stage: 'redirect',
                flow: 'checkout'
            );

            // 1) Обновляем поля заявки (UI/бизнес-метки)
            if ($isPost) {
                $order->update(['merchant_status' => 1]);
            } else {
                $order->update(['started_at' => Carbon::now()->toDateTimeString()]);
            }

            if ($hasCompletePurchase) {
                $nextStatus = TaskStatusEnum::PROCESSING_PAYMENT->value;
            } else {
                $nextStatus = ($externalId !== '' && $supportsPolling)
                    ? TaskStatusEnum::WAITING_HANDLE->value
                    : TaskStatusEnum::MERCHANT_CONFIRMATION->value;
            }

            $transaction->setStatus($nextStatus);

            // 3) Авто-проверка — только если реально поддерживается polling
            if ($externalId !== '' && $supportsPolling) {
                $order->update([
                    'is_bot' => 1,
                    'is_auto_check_pay' => 1,
                ]);
            }


            return $paymentResponse->getRedirectResponse();
        }

        // 15) Если не редирект — отправляем на страницу заявки
        $flowLogger->warning(
            event: 'checkout.no_redirect',
            task: $order,
            merchant: $merchant,
            ctx: [
                'ip' => $ip,
                'user_agent' => $ua,
                'checkout_id' => $hashId,
                'external_id' => $externalId !== '' ? $externalId : null,
            ],
            message: 'Платежный сервис не вернул страницу оплаты. Открываем страницу заявки.',
            stage: 'final',
            flow: 'checkout'
        );

        return redirect()->to($frontend . '/order/' . $order->public_id);
    }

    /**
     * POST-редирект на страницу провайдера через auto-submit HTML (как RedirectResponseTrait).
     */
    private function checkoutPostFormRedirectResponse(string $url, array $data): HttpResponse
    {
        $fieldsHtml = '';

        foreach ($data as $key => $value) {
            $fieldsHtml .= sprintf(
                "<input type=\"hidden\" name=\"%s\" value=\"%s\">\n",
                htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'),
            );
        }

        $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="no-referrer">
  <title>Redirecting...</title>
</head>
<body onload="document.forms[0].submit();">
  <form action="{$escapedUrl}" method="post">
    <noscript>
      <p>Для продолжения нажмите кнопку ниже.</p>
    </noscript>
    {$fieldsHtml}
    <button type="submit">Continue</button>
  </form>
</body>
</html>
HTML;

        return new HttpResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
