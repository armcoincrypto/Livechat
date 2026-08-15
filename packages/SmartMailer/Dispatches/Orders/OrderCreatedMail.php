<?php
namespace iEXPackages\SmartMailer\Dispatches\Orders;

use App\Models\Task;
use iEXPackages\Order\Facades\OrderInvoiceFacade;
use iEXPackages\SmartMailer\SmartMailable;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use iEXPackages\TagProcessors\TagProcessors;

class OrderCreatedMail extends SmartMailable
{
    use Queueable, SerializesModels;

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

        $this->setSubject( __('smart-mailer::messages.order_created_subject', ['id' => current_order_id($this->order)]))
            ->markdown('smart-mailer::orders.order_created', [
                'subject'              => $this->subject,
                'textInfo'             => $this->generateInstructionText($item),
                'directionCityInstruction' => $this->generateDirectionCityInstruction(),
                'order'                => $this->order,
                'account_number_field' => $this->getAccountNumberField($transaction),
                'transaction'          => $transaction,
                'walletInfoAccount'    => $this->getWalletInfoAccount(),
                'tasks_fields'         => $transaction->getTasksFields(),
            ]);
    }

    protected function generateInstructionText($item): string
    {
        $placeholders = [
            '[direction]', '[created_at]', '[course]', '[city]', '[country]', '[in_amount]',
            '[out_amount]', '[in_code]', '[out_code]', '[in_currency]', '[out_currency]',
            '[public_id]', '[order_id]', '[profit_percent]', '[to_account]',
        ];

        $replacements = [
            direction_name($item->direction_exchange),
            $item->created_at->translatedFormat('d M Y H:i'),
            $item->course_display,
            $item->task_info->city_name,
            $item->task_info->country_name,
            $item->give_price,
            $item->receiving_price,
            $item->direction_exchange->currency1->code_currency->name,
            $item->direction_exchange->currency2->code_currency->name,
            $item->direction_exchange->currency1->payment->name,
            $item->direction_exchange->currency2->payment->name,
            $item->public_id,
            $item->id,
            $item->direction_exchange->profit,
            $item->to_shot,
        ];

        $text = $item->direction_exchange->text_order_created_email;

        if (is_array($text)) {
            $text = implode("\n", $text);
        }

        return str_replace($placeholders, $replacements, (string)$text);
    }

    protected function getAccountNumberField($transaction): string
    {
        $inCurrency = $transaction->getCurrencyIn();

        return $inCurrency->account_number_field ?: __('Кошелек');
    }

    protected function getWalletInfoAccount(): string
    {
        $result = OrderInvoiceFacade::make($this->order)->get();

        if (is_array($result)) {
            return $result['wallet_number']
                ?? $result['checkout_url']
                ?? __('Не определен');
        }

        return (string)$result;
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
            ->setText((string) $rawInstruction)
            ->setData([
                'direction_id' => $directionId,
                'city_id'      => $directionCity->id,
            ])
            ->process()
            ->getText();

        return $processed !== '' ? $processed : null;
    }
}
