<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\GatewayMerchant;
use App\Models\MerchantTransactionData;
use App\Models\Task;
use iEXPackages\Payments\Core\Contracts\RedirectResponseInterface;
use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use iEXPackages\Payments\Payments;
use iEXPackages\TagProcessors\TagProcessors;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Фоновое восстановление invoice/create после сбоя checkout (без смены transactionId).
 */
final class CheckoutInvoiceRecoveryService
{
    private const MAX_PURCHASE_ATTEMPTS = 5;

    private const ORDER_PAGE_MIN_INTERVAL_SEC = 90;

    public function recoverIfEligible(Task $order, string $trigger = 'order_page'): bool
    {
        if (!in_array($trigger, ['order_page', 'cron'], true)) {
            $trigger = 'order_page';
        }

        $mtd = MerchantTransactionData::query()
            ->where('id_task', $order->id)
            ->first();

        if (!$mtd || (int) ($mtd->is_checkout_url ?? 0) !== 1) {
            return false;
        }

        if (!$this->shouldRecover($mtd, $order, $trigger)) {
            return false;
        }

        return DB::transaction(function () use ($order, $mtd, $trigger): bool {
            $locked = MerchantTransactionData::query()
                ->whereKey($mtd->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return false;
            }

            if (!$this->shouldRecover($locked, $order, $trigger)) {
                return false;
            }

            $transaction = TransactionFacade::init($order);
            $merchant = $transaction->getMerchant();

            if (!$merchant || (string) $locked->service_name !== (string) $merchant->alias) {
                return false;
            }

            $expiredAt = Carbon::parse($order->created_at)->addSeconds((int) iEXSetting('max_time_task'));
            if (Carbon::now()->greaterThan($expiredAt)) {
                return false;
            }

            $description = $this->buildDescription($order, $merchant);
            $notifyUrl = $this->resolveNotifyUrl($merchant);
            $frontend = rtrim((string) config('app.frontend_url'), '/');
            $cancelUrl = $frontend . '/payment_status/fail';
            $returnUrl = $frontend . '/payment_status/success';

            $inPrice = match ((int) $merchant->pay_amount) {
                1 => $order->give_price_with_comm_pay,
                2 => $order->give_price_default,
                default => $order->give_price,
            };

            $paymentRequest = Payments::forMerchant($merchant);

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
                $this->recordFailure($locked, $e::class, $trigger);

                Log::warning('checkout_invoice_recovery.purchase_failed', [
                    'task_id'         => $order->id,
                    'trigger'         => $trigger,
                    'exception_class' => $e::class,
                ]);

                return false;
            }

            if ($paymentResponse instanceof RedirectResponseInterface && $paymentResponse->isRedirect()) {
                return $this->persistRedirectSuccess($locked, $order, $merchant, $paymentResponse, $transaction, $trigger);
            }

            if ($paymentResponse instanceof ResponseInterface && $paymentResponse->isSuccessful()) {
                $this->persistRequisitesSuccess($locked, $order, $merchant, $paymentResponse, $transaction, $trigger);

                return true;
            }

            $this->recordFailure($locked, 'unsuccessful_response', $trigger);

            return false;
        });
    }

    /**
     * @return array{0:int,1:int} [success_count, not_recovered_count]
     */
    public function recoverBatchForCron(int $limit = 50): array
    {
        $candidates = MerchantTransactionData::query()
            ->where('is_checkout_url', 1)
            ->whereNotNull('ext_data->invoice_create->last_failure_at')
            ->whereNull('ext_data->invoice_create->resolved_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $ok = 0;
        $fail = 0;

        foreach ($candidates as $mtd) {
            $ext = is_array($mtd->ext_data) ? $mtd->ext_data : [];
            $inv = is_array($ext['invoice_create'] ?? null) ? $ext['invoice_create'] : [];
            $att = (int) ($inv['attempts'] ?? 0);
            if ($att === 0) {
                $att = 1;
            }
            if ($att >= self::MAX_PURCHASE_ATTEMPTS) {
                continue;
            }
            if ($this->hasInvoicePayload($mtd)) {
                continue;
            }

            $order = Task::query()->find((int) $mtd->id_task);
            if (!$order) {
                continue;
            }
            if ($this->recoverIfEligible($order, 'cron')) {
                $ok++;
            } else {
                $fail++;
            }
        }

        return [$ok, $fail];
    }

    private function shouldRecover(MerchantTransactionData $mtd, Task $order, string $trigger): bool
    {
        if ((int) $order->status !== 2 || (int) $order->merchant_status !== 0) {
            return false;
        }

        $ext = is_array($mtd->ext_data) ? $mtd->ext_data : [];
        $invoiceCreate = is_array($ext['invoice_create'] ?? null) ? $ext['invoice_create'] : [];

        if (trim((string) ($invoiceCreate['last_failure_at'] ?? '')) === '') {
            return false;
        }

        if (trim((string) ($invoiceCreate['resolved_at'] ?? '')) !== '') {
            return false;
        }

        $attempts = (int) ($invoiceCreate['attempts'] ?? 0);
        if ($attempts === 0 && trim((string) ($invoiceCreate['last_failure_at'] ?? '')) !== '') {
            $attempts = 1;
        }
        if ($attempts >= self::MAX_PURCHASE_ATTEMPTS) {
            return false;
        }

        if ($this->hasInvoicePayload($mtd)) {
            return false;
        }

        if ($trigger === 'order_page') {
            $lastAt = trim((string) ($invoiceCreate['last_order_page_recovery_at'] ?? ''));
            if ($lastAt !== '') {
                try {
                    $prev = Carbon::parse($lastAt);
                    if ($prev->copy()->addSeconds(self::ORDER_PAGE_MIN_INTERVAL_SEC)->isFuture()) {
                        return false;
                    }
                } catch (\Throwable) {
                }
            }
        }

        return true;
    }

    private function hasInvoicePayload(MerchantTransactionData $mtd): bool
    {
        if (trim((string) ($mtd->id_from_merchant ?? '')) !== '') {
            return true;
        }

        $ext = is_array($mtd->ext_data) ? $mtd->ext_data : [];

        if (trim((string) ($ext['receiver'] ?? '')) !== '' && trim((string) ($ext['tracker_id'] ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($ext['wallet_number'] ?? '')) !== '') {
            return true;
        }

        $redirect = is_array($ext['redirect'] ?? null) ? $ext['redirect'] : [];

        return trim((string) ($redirect['provider_url'] ?? '')) !== '';
    }

    private function recordFailure(MerchantTransactionData $mtd, string $failureClass, string $trigger): void
    {
        $ext = is_array($mtd->ext_data) ? $mtd->ext_data : [];
        $prev = is_array($ext['invoice_create'] ?? null) ? $ext['invoice_create'] : [];
        $attempts = (int) ($prev['attempts'] ?? 0) + 1;

        $invoiceRow = array_merge($prev, [
            'attempts'            => $attempts,
            'last_failure_at'       => Carbon::now()->toIso8601String(),
            'last_failure_class'    => $failureClass,
            'last_trigger'          => $trigger,
        ]);

        if ($trigger === 'order_page') {
            $invoiceRow['last_order_page_recovery_at'] = Carbon::now()->toIso8601String();
        }

        $ext['invoice_create'] = $invoiceRow;

        $mtd->update(['ext_data' => $ext]);
    }

    private function persistRequisitesSuccess(
        MerchantTransactionData $mtd,
        Task $order,
        GatewayMerchant $merchant,
        ResponseInterface $paymentResponse,
        object $transaction,
        string $trigger,
    ): void {
        $externalId = method_exists($paymentResponse, 'getExternalId')
            ? trim((string) ($paymentResponse->getExternalId() ?? ''))
            : '';

        $accountNumber = method_exists($paymentResponse, 'getAccountNumber')
            ? trim((string) ($paymentResponse->getAccountNumber() ?? ''))
            : '';

        $accountTag = method_exists($paymentResponse, 'getAccountTag')
            ? trim((string) ($paymentResponse->getAccountTag() ?? ''))
            : '';

        $ext = is_array($mtd->ext_data) ? $mtd->ext_data : [];
        $invoicePrev = is_array($ext['invoice_create'] ?? null) ? $ext['invoice_create'] : [];

        if ($accountNumber !== '') {
            $ext['receiver'] = $accountNumber;
        }
        if ($externalId !== '') {
            $ext['tracker_id'] = $externalId;
        }

        $invoiceRow = array_merge($invoicePrev, [
            'resolved_at'          => Carbon::now()->toIso8601String(),
            'last_success_trigger' => $trigger,
        ]);
        if ($trigger === 'order_page') {
            $invoiceRow['last_order_page_recovery_at'] = Carbon::now()->toIso8601String();
        }
        $ext['invoice_create'] = $invoiceRow;

        $ext['wallet_number'] = $accountNumber;
        $ext['memo_id']        = $accountTag !== '' ? $accountTag : null;
        $ext['label']          = $externalId !== '' ? $externalId : null;

        $cfg = Payments::forConfig($merchant->alias);

        $mtd->update([
            'id_from_merchant' => $externalId !== '' ? $externalId : $mtd->id_from_merchant,
            'is_checkout_url'  => 0,
            'ext_data'         => $ext,
        ]);

        $fresh = Task::query()->whereKey($order->id)->first();
        if ($fresh) {
            $fresh->forceFill([
                'merchant_provider'         => (string) $merchant->alias,
                'transfer_to_account'       => $accountNumber !== '' ? $accountNumber : null,
                'transfer_to_account_type'  => 'merchant_' . (string) $merchant->alias,
                'is_check_payment_merchant' => (int) $cfg->supportsPollingIncoming(),
            ])->save();
        }

        Log::info('checkout_invoice_recovery.success_requisites', [
            'task_id' => $order->id,
            'trigger' => $trigger,
        ]);
    }

    private function persistRedirectSuccess(
        MerchantTransactionData $mtd,
        Task $order,
        GatewayMerchant $merchant,
        RedirectResponseInterface $paymentResponse,
        object $transaction,
        string $trigger,
    ): bool {
        $externalId = method_exists($paymentResponse, 'getExternalId')
            ? trim((string) ($paymentResponse->getExternalId() ?? ''))
            : '';

        $providerUrl = trim((string) ($paymentResponse->getRedirectUrl() ?? ''));
        $method = strtoupper(trim((string) $paymentResponse->getRedirectMethod()));
        $formData = (array) $paymentResponse->getRedirectData();

        if ($providerUrl === '' || !in_array($method, ['GET', 'POST'], true)) {
            $this->recordFailure($mtd, 'invalid_redirect_response', $trigger);

            return false;
        }

        $cfg = Payments::forConfig($merchant->alias);

        $ext = is_array($mtd->ext_data) ? $mtd->ext_data : [];
        $existingCheckout = is_array($ext['checkout'] ?? null) ? $ext['checkout'] : [];
        $checkoutId = trim((string) ($existingCheckout['id'] ?? ''));
        $checkoutUrl = trim((string) ($existingCheckout['url'] ?? ''));
        if ($checkoutId === '' || $checkoutUrl === '') {
            $checkoutId = sprintf('%s-%s', (string) $order->public_id, strtolower((string) Str::orderedUuid()));
            $checkoutUrl = rtrim((string) config('app.api_url'), '/') . '/payment_status/checkout/' . $checkoutId;
        }

        $invoicePrev = is_array($ext['invoice_create'] ?? null) ? $ext['invoice_create'] : [];

        $invoiceRow = array_merge($invoicePrev, [
            'resolved_at'          => Carbon::now()->toIso8601String(),
            'last_success_trigger' => $trigger,
        ]);
        if ($trigger === 'order_page') {
            $invoiceRow['last_order_page_recovery_at'] = Carbon::now()->toIso8601String();
        }
        $ext['invoice_create'] = $invoiceRow;

        $ext['flow'] = [
            'mode' => $method === 'POST' ? 'form' : 'redirect',
        ];
        $ext['checkout'] = [
            'id'  => $checkoutId,
            'url' => $checkoutUrl,
        ];
        $ext['redirect'] = [
            'provider_url' => $providerUrl,
            'method'       => $method,
            'data'         => $method === 'POST' ? $formData : [],
        ];
        $ext['provider'] = [
            'external_id' => $externalId !== '' ? $externalId : null,
        ];

        $mtd->update([
            'id_from_merchant' => $externalId !== '' ? $externalId : $mtd->id_from_merchant,
            'is_checkout_url'  => 1,
            'ext_data'         => $ext,
        ]);

        $fresh = Task::query()->whereKey($order->id)->first();
        if ($fresh) {
            $fresh->forceFill([
                'merchant_provider'         => (string) $merchant->alias,
                'transfer_to_account'       => null,
                'transfer_to_account_type'  => 'merchant_' . (string) $merchant->alias,
                'is_check_payment_merchant' => (int) $cfg->supportsPollingIncoming(),
            ])->save();
        }

        Log::info('checkout_invoice_recovery.success_redirect', [
            'task_id' => $order->id,
            'trigger' => $trigger,
        ]);

        return true;
    }

    private function buildDescription(Task $order, GatewayMerchant $merchant): string
    {
        if (!empty($merchant->comment)) {
            return (string) app(TagProcessors::class)
                ->setProcessor('order')
                ->setText((string) $merchant->comment)
                ->setData($order)
                ->process()
                ->getText();
        }

        $host = parse_url((string) config('app.api_url'), PHP_URL_HOST)
            ?: parse_url((string) config('app.url'), PHP_URL_HOST)
            ?: 'localhost';

        return (string) TransactionFacade::init($order)->merchantDescription((string) $host, (string) $merchant->alias);
    }

    private function resolveNotifyUrl(GatewayMerchant $merchant): string
    {
        $noticeRoute = 'merchant.receive_money';

        try {
            $cfg = Payments::forConfig($merchant->alias);
            $route = $cfg->callbackRouteName();
            if (!empty($route) && $cfg->callbacksEnabled()) {
                $noticeRoute = $route;
            }
        } catch (\Throwable) {
        }

        return route($noticeRoute, [$merchant->alias, $merchant->security_hash]);
    }
}
