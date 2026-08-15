<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\ObmenkaClubV2\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use iEXPackages\Payments\Core\Traits\PayoutContextTrait;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PayoutRequest extends AbstractRequest
{
    use PayoutContextTrait;

    public function getData(): array
    {
        $this->validate('amount');

        // Контекст
        ['task' => $task] = $this->requirePaymentContext();

        // Сумма
        $amount = trim((string) $this->getParameter('amount'));
        if ($amount === '') {
            throw new InvalidArgumentException('amount пустой.');
        }

        // Карта/счёт (очищенный)
        $card = (string) $this->getCleanPayoutAccount(); // должен вернуть string|null
        $card = trim($card);

        if ($card === '') {
            throw new InvalidArgumentException('Реквизиты выплаты (task->to_shot) пустые.');
        }

        // OUT поля заявки
        $recipientFullname = $this->getCurrencyOutField('recipient_fullname');
        $telegramAccount   = $this->getCurrencyOutField('outcome_telegram');
        $whatsappAccount   = $this->getCurrencyOutField('outcome_whatsapp');
        $phoneNumber       = $this->getCurrencyOutField('outcome_phone');

        $bankName = $this->getCurrencyOutField('outcome_bank_name')
            ?? $this->getCurrencyOutField('outcome_bank');

        // messenger_type/contact: telegram → whatsapp → phone
        [$messengerType, $contact] = $this->resolveMessenger($telegramAccount, $whatsappAccount, $phoneNumber);

        // slug/currency из currency2
        $slug = (string) ($task->direction_exchange?->currency2?->designation_xml ?? '');
        $currency = (string) ($task->direction_exchange?->currency2?->code_currency?->name ?? '');

        if ($slug === '' || $currency === '') {
            throw new InvalidArgumentException('Не удалось определить slug/currency из направления (currency2).');
        }

        $slug = Str::upper($slug);
        $currency = Str::upper($currency);

        return [
            'token'          => (string) 'ORDER_TEST_'.$this->getTask()->id,
            'amount'         => $amount,
            'slug'           => $slug,
            'account_holder' => $recipientFullname ?? 'Default Name',
            'currency'       => $currency,
            'bank_name'      => $bankName ?? 'Default Bank',
            'card'           => $card,
            'account_tin'    => '',
            'iban'           => '',
            'messenger_type' => $messengerType,
            'contact'        => $contact,
            'urgent'        =>  1,
            'priority_support' => 1,
            'third_party_payment' => 1,
            'one_time_payout'   =>  1,
            'payout_receipt' => 1,

        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/payments/create', $data);

        return $this->response = new PayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }

    /**
     * Выбор канала связи в приоритете:
     * telegram → whatsapp → phone.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function resolveMessenger(?string $telegram, ?string $whatsapp, ?string $phone): array
    {
        $telegram = $this->normalizeContact($telegram);
        $whatsapp = $this->normalizeContact($whatsapp);
        $phone    = $this->normalizeContact($phone);

        if ($telegram !== null) {
            return ['telegram', $telegram];
        }

        if ($whatsapp !== null) {
            return ['whatsapp', $whatsapp];
        }

        if ($phone !== null) {
            return ['phone', $phone];
        }

        return [null, null];
    }

    protected function normalizeContact(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        return $value !== '' ? $value : null;
    }
}
