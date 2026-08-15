<?php
declare(strict_types=1);

namespace iEXPackages\Payments\Core\Traits;

use App\Models\GatewayPayment;
use App\Models\Task;
use iEXPackages\TagProcessors\TagProcessors;
use InvalidArgumentException;
use Illuminate\Support\Str;

trait PayoutContextTrait
{
    /**
     * Требует контекст выплаты: Task + GatewayPayment.
     *
     * @return array{task: Task, payment: GatewayPayment}
     */
    protected function requirePaymentContext(): array
    {
        $task = $this->getTask();
        $payment = $this->getPayment();

        if (!$task || !$payment) {
            throw new InvalidArgumentException(
                'Для операции выплаты обязательно привязать Task (withTask) и GatewayPayment (forPayment/withPayment).'
            );
        }

        return ['task' => $task, 'payment' => $payment];
    }

    /**
     * Получить очищенный адрес выплаты (без тегов, пробелов и спецсимволов).
     *
     * Используется ТОЛЬКО для формирования payload,
     * данные в БД не изменяет.
     */
    protected function getCleanPayoutAddress(): ?string
    {
        $task = $this->getTask();

        if (!$task) {
            return null;
        }

        $raw = (string) ($task->to_shot ?? '');
        if ($raw === '') {
            return null;
        }

        // Убираем пробелы, переносы строк
        $clean = preg_replace('/\s+/u', '', $raw);

        // Убираем типичные разделители тегов (memo/tag)
        // Например: "address:tag", "address|tag", "address,tag"
        $clean = preg_split('/[:|,;]/u', $clean)[0] ?? $clean;

        // Дополнительная защита: оставляем только допустимые символы
        // (буквы, цифры, точка, дефис, подчёркивание)
        $clean = preg_replace('/[^a-zA-Z0-9._-]/u', '', $clean);

        return $clean !== '' ? $clean : null;
    }

    protected function getCleanPayoutAccount()
    {
        return $this->getCleanPayoutAddress();
    }

    /**
     * Comment для payout.
     *
     * Приоритет:
     *  1) GatewayPayment->ext_options['comment']
     *  2) параметр request: comment
     *  3) fallback: sitename:task_id
     */
    protected function getPayoutComment(): string
    {
        ['task' => $task, 'payment' => $payment] = $this->requirePaymentContext();

        $ext = is_array($payment->ext_options ?? null) ? $payment->ext_options : [];
        $comment = trim((string)($ext['comment'] ?? ''));

        if ($comment === '') {
            $comment = trim((string)$this->getParameter('comment', ''));
        }

        if ($comment !== '') {
            $processed = app(TagProcessors::class)
                ->setProcessor('order')
                ->setText($comment)
                ->setData($task)
                ->process()
                ->getText();

            return Str::markdown($processed);
        }

        if (function_exists('iEXContentLanguage')) {
            return sprintf('%s:%s', iEXContentLanguage('sitename'), (string)$task->id);
        }

        return 'task:' . (string)$task->id;
    }

    /**
     * Memo/Tag для выплат из доп. полей (out).
     */
    protected function getPayoutMemoTag(): ?string
    {
        return $this->getCurrencyOutField('outcome_unk');
    }

    /**
     * Network code для выплат.
     */
    protected function getPayoutNetworkCode(bool $defaultCurrency = true): string
    {
        return (string) $this->getPaymentNetworkCode($defaultCurrency);
    }
}
