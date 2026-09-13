<?php
namespace iEXPackages\SmartMailer\Dispatches\Orders;

use App\Models\Task;
use App\Models\User;
use App\Services\Mail\OrderMailDisplayText;
use iEXPackages\Order\Facades\OrderInvoiceFacade;
use iEXPackages\SmartMailer\LocalizesOrderMailStatus;
use iEXPackages\SmartMailer\SmartMailable;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use iEXPackages\TagProcessors\TagProcessors;
use Throwable;

class OrderCreatedMail extends SmartMailable
{
    use Queueable, SerializesModels, LocalizesOrderMailStatus;

    protected Task $order;

    /**
     * Конструктор письма.
     */
    public function __construct(Task $order)
    {
        parent::__construct($order->task_info->language ?? null);
        $this->order = $order;
    }


    protected function compose(): void
    {
        $transaction = TransactionFacade::init($this->order);
        $item = $transaction->getTransaction();

        $setPasswordUrl = $this->resolveSetPasswordUrl();
        $direction = $this->order->direction_exchange;
        $currencyIn = $direction?->currency1;
        $currencyOut = $direction?->currency2;

        $this->setSubject( __('smart-mailer::messages.order_created_subject', ['id' => current_order_id($this->order)]))
            ->markdown('smart-mailer::orders.order_created', [
                'subject'              => $this->subject,
                'textInfo'             => $this->generateInstructionText($item),
                'directionCityInstruction' => $this->generateDirectionCityInstruction(),
                'order'                => $this->order,
                'account_number_field' => $this->getAccountNumberField($transaction),
                'sendCodeName'        => OrderMailDisplayText::scalar($currencyIn?->code_currency?->name ?? null),
                'sendPaymentName'     => OrderMailDisplayText::scalar($currencyIn?->payment?->name ?? null),
                'receiveCodeName'    => OrderMailDisplayText::scalar($currencyOut?->code_currency?->name ?? null),
                'receivePaymentName'  => OrderMailDisplayText::scalar($currencyOut?->payment?->name ?? null),
                'fieldNameFrom'       => OrderMailDisplayText::scalar($currencyIn?->field_name_from ?? null),
                'fieldNameTo'         => OrderMailDisplayText::scalar($currencyOut?->field_name_to ?? null),
                'fromShot'            => OrderMailDisplayText::scalar($this->order->from_shot ?? null),
                'toShot'              => OrderMailDisplayText::scalar($this->order->to_shot ?? null),
                'transaction'          => $transaction,
                'walletInfoAccount'    => $this->getWalletInfoAccount(),
                'tasks_fields'         => $this->scalarTasksFields($transaction->getTasksFields()),
                'localizedStatus'      => OrderMailDisplayText::scalar($this->localizedOrderStatusName($this->order)),
                'showSetPasswordCta'   => $setPasswordUrl !== null,
                'setPasswordUrl'       => $setPasswordUrl,
                'openOrderUrl'         => rtrim((string) config('app.frontend_url'), '/') . '/order/' . $this->order->public_id,
            ]);
    }

    /**
     * First-order auto-account only: newbie snapshot + mailbox not yet proven via set-password.
     * Retries regenerate a broker token (framework-safe; prior unused token replaced).
     */
    protected function resolveSetPasswordUrl(): ?string
    {
        try {
            if (!(bool) iEXSetting('is_email_auto_user')) {
                return null;
            }

            $this->order->loadMissing(['task_info', 'user']);
            $taskInfo = $this->order->task_info;
            if ($taskInfo === null || (int) ($taskInfo->newbie ?? 0) !== 1) {
                return null;
            }

            $user = $this->order->user;
            if (! $user instanceof User) {
                return null;
            }

            // Password setup proves mailbox ownership and clears this gate.
            if ($user->hasVerifiedEmail()) {
                return null;
            }

            $token = Password::broker()->createToken($user);

            return rtrim((string) config('app.frontend_url'), '/')
                . '/auth/reset-credentials/'
                . $token
                . '/?email='
                . urlencode($user->getEmailForPasswordReset());
        } catch (Throwable $e) {
            Log::warning('OrderCreatedMail set-password URL skipped', [
                'order_id' => $this->order->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function generateInstructionText($item): string
    {
        $placeholders = [
            '[direction]', '[created_at]', '[course]', '[city]', '[country]', '[in_amount]',
            '[out_amount]', '[in_code]', '[out_code]', '[in_currency]', '[out_currency]',
            '[public_id]', '[order_id]', '[profit_percent]', '[to_account]',
        ];

        $replacements = [
            OrderMailDisplayText::scalar(direction_name($item->direction_exchange)),
            OrderMailDisplayText::scalar($item->created_at?->translatedFormat('d M Y H:i')),
            OrderMailDisplayText::scalar($item->course_display ?? null),
            OrderMailDisplayText::scalar($item->task_info->city_name ?? null),
            OrderMailDisplayText::scalar($item->task_info->country_name ?? null),
            OrderMailDisplayText::scalar($item->give_price ?? null),
            OrderMailDisplayText::scalar($item->receiving_price ?? null),
            OrderMailDisplayText::scalar($item->direction_exchange->currency1->code_currency->name ?? null),
            OrderMailDisplayText::scalar($item->direction_exchange->currency2->code_currency->name ?? null),
            OrderMailDisplayText::scalar($item->direction_exchange->currency1->payment->name ?? null),
            OrderMailDisplayText::scalar($item->direction_exchange->currency2->payment->name ?? null),
            OrderMailDisplayText::scalar($item->public_id ?? null),
            OrderMailDisplayText::scalar($item->id ?? null),
            OrderMailDisplayText::scalar($item->direction_exchange->profit ?? null),
            OrderMailDisplayText::scalar($item->to_shot ?? null),
        ];

        $text = OrderMailDisplayText::scalar($item->direction_exchange->text_order_created_email ?? null);

        return str_replace($placeholders, $replacements, $text);
    }

    protected function getAccountNumberField($transaction): string
    {
        $inCurrency = $transaction->getCurrencyIn();

        return OrderMailDisplayText::scalar(
            $inCurrency->account_number_field ?? null,
            (string) __('Кошелек'),
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function scalarTasksFields(array $fields): array
    {
        foreach (['requisites_info_field', 'many', 'currency_in', 'currency_out'] as $group) {
            if (! isset($fields[$group]) || ! is_array($fields[$group])) {
                continue;
            }
            foreach ($fields[$group] as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (['key_name', 'value_name', 'field_name', 'field_value'] as $key) {
                    if (array_key_exists($key, $row)) {
                        $row[$key] = OrderMailDisplayText::scalar($row[$key] ?? null);
                    }
                }
                $fields[$group][$index] = $row;
            }
        }

        return $fields;
    }

    protected function getWalletInfoAccount(): string
    {
        $result = OrderInvoiceFacade::make($this->order)->snapshot();

        if (is_array($result)) {
            return OrderMailDisplayText::scalar(
                $result['wallet_number'] ?? $result['checkout_url'] ?? null,
                (string) __('Не определен'),
            );
        }

        return OrderMailDisplayText::scalar($result, (string) __('Не определен'));
    }

    /**
     * Сформировать текст инструкции по городу направления с применением TagProcessors.
     */
    protected function generateDirectionCityInstruction(): ?string
    {
        $taskInfo = $this->order->task_info ?? null;

        if (!$taskInfo || empty($taskInfo->country_name) || !$taskInfo->directionCity || empty($taskInfo->directionCity->instruction)) {
            return null;
        }

        $directionCity = $taskInfo->directionCity;
        $rawInstruction = $directionCity->instruction;

        // Пытаемся определить id направления из задачи
        $directionId = $this->order->id_direction_exchange
            ?? ($this->order->direction_exchange->id ?? null);

        /** @var TagProcessors $tagger */
        $tagger = app(TagProcessors::class);

        $processed = $tagger
            ->setProcessor('direction_city')
            ->setText(OrderMailDisplayText::scalar($rawInstruction))
            ->setData([
                'direction_id' => $directionId,
                'city_id'      => $directionCity->id,
            ])
            ->process()
            ->getText();

        return $processed !== '' ? $processed : null;
    }
}
