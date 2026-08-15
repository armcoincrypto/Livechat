<?php

namespace iEXPackages\Order\Services;

use App\Models\Task;
use iEXPackages\Order\Facades\OrderInvoiceFacade;

/**
 * InvoiceContext (нормализованный)
 *
 * mode:
 *  - none
 *  - requisites
 *  - checkout
 *
 * + данные для подстановок в шаблоны и UI
 */
final class InvoiceContextService
{
    public function resolve(Task $task): array
    {
        $raw = OrderInvoiceFacade::make($task)->get();

        $ctx = [
            'mode' => 'none',

            // checkout
            'checkout_url' => null,
            'checkout_id'  => null,

            // requisites
            'account' => null,
            'memo'    => null,
            'bank'    => null,

            // удобные “токены” для шаблонов
            'tokens' => [
                '[auto]'          => '',
                '[auto_bank_name]'=> '',
                '[order_id]'      => (string) $task->id,
                '[public_id]'     => (string) $task->public_id,
            ],
        ];

        if (!is_array($raw)) {
            return $ctx;
        }

        if (!empty($raw['checkout_url'])) {
            $ctx['mode'] = 'checkout';
            $ctx['checkout_url'] = (string) $raw['checkout_url'];
            $ctx['checkout_id']  = isset($raw['checkout_id']) ? (string) $raw['checkout_id'] : null;
            return $ctx;
        }

        $account = (string) ($raw['wallet_number'] ?? $raw['account'] ?? '');
        if ($account !== '') {
            $ctx['mode']    = 'requisites';
            $ctx['account'] = $account;

            $memo = (string) ($raw['memo_id'] ?? $raw['memo'] ?? '');
            $bank = (string) ($raw['bank_name'] ?? $raw['bank'] ?? '');

            $ctx['memo'] = $memo !== '' ? $memo : null;
            $ctx['bank'] = $bank !== '' ? $bank : null;

            $ctx['tokens']['[auto]']           = $ctx['memo'] ?? '';
            $ctx['tokens']['[auto_bank_name]'] = $ctx['bank'] ?? '';
        }

        return $ctx;
    }
}
