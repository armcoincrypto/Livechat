<?php

declare(strict_types=1);

namespace App\Services\Certification;

use App\Models\MerchantTransactionData;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Certification-only destination adapter. Never imports or calls Kobbopay.
 */
final class CertificationDestinationIssuer
{
    public const PROVIDER = 'INTERNAL_FAKE';

    /**
     * @return array<string, mixed>
     */
    public function resolve(Task $order, bool $allowIssue): array
    {
        $scenario = CertificationOrderMarker::isMarked($order)
            ? CertificationOrderMarker::scenario($order)
            : app(CertificationContext::class)->scenario();

        if ($scenario === CertificationContext::SCENARIO_FAIL_CLOSED) {
            if ($allowIssue) {
                $this->issueFailClosed($order);
                $order->refresh();
            }

            return $this->emptyContext($order);
        }

        if ($allowIssue && trim((string) ($order->transfer_to_account ?? '')) === '') {
            $this->issueSuccess($order);
            $order->refresh();
        }

        $account = (string) ($order->transfer_to_account ?? '');

        return [
            'mode' => $account !== '' ? 'requisites' : 'none',
            'checkout_url' => null,
            'checkout_id' => null,
            'account' => $account !== '' ? $account : null,
            'memo' => null,
            'bank' => null,
            'tokens' => [
                '[auto]' => '',
                '[auto_bank_name]' => '',
                '[order_id]' => (string) $order->id,
                '[public_id]' => (string) $order->public_id,
            ],
        ];
    }

    private function issueSuccess(Task $order): void
    {
        $reference = 'QA_CERT_'.$order->id;
        $destination = 'QA_CERT_UNROUTABLE_'.$order->public_id;

        DB::transaction(function () use ($order, $reference, $destination): void {
            $locked = Task::query()->whereKey($order->id)->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }
            $locked->loadMissing(['direction_exchange']);

            MerchantTransactionData::updateOrCreate(
                ['id_task' => $locked->id],
                [
                    'id_currency' => $locked->direction_exchange->id_currency1 ?? null,
                    'id_from_merchant' => $reference,
                    'service_name' => self::PROVIDER,
                    'id_merchant' => 0,
                    'is_checkout_url' => 0,
                    'ext_data' => [
                        'wallet_number' => $destination,
                        'provider' => [
                            'external_id' => $reference,
                            'payment_id' => $reference,
                            'status' => 'destination_issued',
                        ],
                    ],
                ]
            );

            $locked->forceFill([
                'merchant_provider' => self::PROVIDER,
                'transfer_to_account' => $destination,
                'transfer_to_account_type' => 'certification_internal_fake',
                'is_check_payment_merchant' => 0,
                'is_bot' => 0,
                'is_wallet_issued' => 1,
                'id_merchant' => 0,
                'is_reserve_out_used' => 1,
                'is_reserve_in_used' => 1,
            ])->save();
        });
    }

    private function issueFailClosed(Task $order): void
    {
        $reference = 'QA_CERT_FAIL_'.$order->id;

        DB::transaction(function () use ($order, $reference): void {
            $locked = Task::query()->whereKey($order->id)->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }
            $locked->loadMissing(['direction_exchange']);

            MerchantTransactionData::updateOrCreate(
                ['id_task' => $locked->id],
                [
                    'id_currency' => $locked->direction_exchange->id_currency1 ?? null,
                    'id_from_merchant' => $reference,
                    'service_name' => self::PROVIDER,
                    'id_merchant' => 0,
                    'is_checkout_url' => 0,
                    'ext_data' => [
                        'wallet_number' => null,
                        'provider' => [
                            'external_id' => $reference,
                            'payment_id' => $reference,
                            'status' => 'destination_unavailable',
                        ],
                    ],
                ]
            );

            $locked->forceFill([
                'merchant_provider' => self::PROVIDER,
                'transfer_to_account' => null,
                'is_check_payment_merchant' => 0,
                'is_bot' => 0,
                'is_wallet_issued' => 0,
                'id_merchant' => 0,
                'is_reserve_out_used' => 1,
                'is_reserve_in_used' => 1,
            ])->save();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyContext(Task $order): array
    {
        return [
            'mode' => 'none',
            'checkout_url' => null,
            'checkout_id' => null,
            'account' => null,
            'memo' => null,
            'bank' => null,
            'tokens' => [
                '[auto]' => '',
                '[auto_bank_name]' => '',
                '[order_id]' => (string) $order->id,
                '[public_id]' => (string) $order->public_id,
            ],
        ];
    }
}
