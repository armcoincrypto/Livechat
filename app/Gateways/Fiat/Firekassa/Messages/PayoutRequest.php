<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Firekassa\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use iEXPackages\Payments\Core\Traits\PayoutContextTrait;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class PayoutRequest extends AbstractRequest
{
    use PayoutContextTrait;

    public function getData(): array
    {
        $this->validate('amount');


        // Контекст
        $task    = $this->getTask();
        $payment = $this->getPayment();

        if (!$task || !$payment) {
            throw new InvalidArgumentException(
                'Для выплаты обязательно привязать Task (withTask) и GatewayPayment (forPayment/withPayment).'
            );
        }

        // Адрес/карта/счёт
        $fromShot = $this->cleanString($task->from_shot);
        $toShot   = $this->getCleanPayoutAccount(); // лучше единый хелпер
        if ($toShot === '' || $toShot === null) {
            throw new InvalidArgumentException('Адрес выплаты (task->to_shot) пустой.');
        }

        // Доп. поля из заявки (out)
        $recipientFullname = $this->getCurrencyOutField('recipient_fullname', '--');
        $phone             = $this->getCurrencyOutField('outcome_phone', '');

        $rawSiteAccount = $this->payString('site_account', '');
        $parsed = $this->parseCompositeAccount($rawSiteAccount);
        $methodPay   = $parsed['method'] ?? 'card';
        $siteAccount = $parsed['site_account'] ?? '';

        $orderId = (string) ($this->getTransactionId() ?: (string) $task->id);

        $amount = trim((string) $this->getParameter('amount'));
        if ($amount === '') {
            throw new InvalidArgumentException('amount пустой.');
        }

        // email/ip/ua
        $email = (string) ($task->user?->email ?? '');
        $ip    = (string) ($task->ip ?? '');
        $ua    = (string) ($task->meta?->user_agent ?? request()->userAgent() ?? 'none');

        // currency xml (страхуемся)
        $cFrom = (string) ($task->direction_exchange?->currency1?->designation_xml ?? '');
        $senderSystem = function_exists('currency_in') ? (string) currency_in($task, true) : '';


        return [
            'account'        => (string) $toShot,
            'amount'         => (string) $amount,
            'order_id'       => $orderId,
            'comment'        => (string) $this->getPayoutComment(),

            // уникальный внешний txn
            'ext_txn'        => 'OUT_' . $orderId,
            'ext_date'       => Carbon::now()->format('c'),

            'ext_client'     => $email,
            'ext_email'      => $email,
            'ext_ip'         => $ip,
            'ext_user_agent' => $ua,

            'ext_sender_system' => $senderSystem,
            'ext_sender'        => $fromShot !== '' ? $fromShot : '',
            'ext_c_from'        => $cFrom,

            'ext_partner'       => (string) $this->getPaySiteName(),

            // теперь правильно: method/site_account для провайдера
            'site_account'   => (string) $siteAccount,
            'method'         => (string) $methodPay,

            // ФИО
            'ext_last_name'   => (string) $recipientFullname,
            'ext_first_name'  => '--',
            'ext_middle_name' => '--',

            'ext_phone'       => (string) $phone,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/api/v2/withdrawal', $data, 'asJson');

        return $this->response = new PayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }

    /**
     * Разбирает composite-значение вида "method|site_account".
     *
     * @return array{method: ?string, site_account: ?string}
     */
    protected function parseCompositeAccount(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return ['method' => null, 'site_account' => null];
        }

        // убираем пробелы
        $value = preg_replace('/\s+/u', '', $value) ?: '';

        $parts = explode('|', $value, 2);

        $method = $parts[0] ?? null;
        $site   = $parts[1] ?? null;

        $method = ($method !== null && $method !== '') ? $method : null;
        $site   = ($site !== null && $site !== '') ? $site : null;

        return ['method' => $method, 'site_account' => $site];
    }
}
