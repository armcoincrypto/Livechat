<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Firekassa\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use Illuminate\Support\Carbon;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('amount');

        $task = $this->getTask();
        if (!$task) {
            throw new \InvalidArgumentException('Task не привязан к запросу (withTask обязателен).');
        }

        $txId = (string) $this->getTransactionId();
        if ($txId === '') {
            throw new \InvalidArgumentException('transactionId пустой (setTransactionId обязателен).');
        }

        $amount = (string) $this->getAmount();
        if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
            throw new \InvalidArgumentException('amount должен быть числом больше 0.');
        }

        // Безопасные зависимости (не падаем на null)
        $userEmail  = (string) ($task->user->email ?? '');
        $ip         = (string) ($task->ip ?? '');
        $userAgent  = (string) ($task->meta->user_agent ?? '');

        // Реквизиты (очищаем)
        $fromShot = $this->cleanString((string) ($task->from_shot ?? ''));
        $toShot = $this->cleanString((string) ($task->to_shot ?? ''));

        $rawSiteAccount = $this->merchantString('site_account', ''); // "card|sber"
        $parsed = $this->parseCompositeAccount($rawSiteAccount);
        $methodPay   = $parsed['method'] ?? 'card';
        $siteAccount = $parsed['site_account'] ?? '';

        // Доп. поля
        $senderFullname = (string) $this->getCurrencyInField('sender_fullname', 'Default Name');
        $phone          = (string) $this->getCurrencyInField('income_phone', '');

        // currency_out(...) и designation_xml — лучше безопасно
        $recipientSystem = function_exists('currency_out')
            ? (string) currency_out($task, true)
            : '';

        $extCTo = (string) ($task->direction_exchange->currency2->designation_xml ?? '');


        return [
            'order_id'            => $txId,
            'amount'              => $amount,

            'account'             => $fromShot ?: null,

            'ext_txn'             => 'IN_' . $txId,
            'ext_date'            => Carbon::now()->toIso8601String(),

            'ext_client'          => $userEmail,
            'ext_email'           => $userEmail,

            'ext_ip'              => $ip,
            'ext_user_agent'      => $userAgent,

            'ext_partner'         => (string) $this->getSiteName(),
            'ext_recipient_system'=> $recipientSystem,

            // Если обязательно — лучше кидать исключение, а не отправлять пустоту
            'ext_recipient'       => $toShot ?: null,

            'ext_c_to'            => $extCTo ?: null,

            'site_account'        => $siteAccount ?: null,
            'method'              => $methodPay,

            // ФИО: оставил как у тебя, но без мусора
            'ext_last_name'       => $senderFullname !== '' ? $senderFullname : 'Default Name',
            'ext_first_name'      => '--',
            'ext_middle_name'     => '--',

            // Телефон: лучше null, чем "no phone" (если API не требует строку)
            'ext_phone'           => $phone !== '' ? $phone : null,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        // TODO: замени endpoint на реальный
        $response = $this->sendRequest('post', '/api/v2/deposit', $data, 'asJson');

        return $this->response = new PurchaseResponse(
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
