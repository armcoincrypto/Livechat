<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use App\Models\OrderOperatorAssignment;
use App\Models\Task;
use Throwable;

/**
 * Builds operator Telegram copy from persisted Task data.
 * Field-selection semantics follow the retired Python formatter
 * (exswaping_notify_bot/formatter.py) without re-enabling that bot.
 * Never throws; optional lines are omitted on failure.
 */
final class TelegramOrderMessagePresenter
{
    /** @var list<string> */
    private const SECRET_NEEDLES = [
        'token', 'secret', 'password', 'private', 'webhook', 'api_key',
        'apikey', 'mnemonic', 'seed', 'credential',
    ];

    /**
     * @return array{
     *   public_id: string,
     *   created_at: string,
     *   give_ps: string,
     *   give_amount: string,
     *   give_fields: list<array{label: string, value: string}>,
     *   receive_ps: string,
     *   receive_amount: string,
     *   order_rate: ?string,
     *   current_rate: ?string,
     *   receive_fields: list<array{label: string, value: string}>,
     *   user_id: ?string,
     *   email: ?string,
     *   name: ?string,
     *   operator: ?string,
     *   status_label: ?string
     * }
     */
    public function present(Task $task): array
    {
        try {
            $task->loadMissing([
                'direction_exchange.currency1.payment',
                'direction_exchange.currency1.code_currency',
                'direction_exchange.currency2.payment',
                'direction_exchange.currency2.code_currency',
                'user',
                'task_status',
                'tasks_fields_currency_in',
                'tasks_fields_currency_out',
                'payment_requisites',
            ]);
        } catch (Throwable) {
            // render with whatever is already loaded
        }

        $dir = $task->direction_exchange;
        $c1 = $dir?->currency1;
        $c2 = $dir?->currency2;

        $givePs = $this->paymentSystemLabel($c1);
        $recvPs = $this->paymentSystemLabel($c2);
        $giveCode = (string) ($c1?->code_currency?->name ?? '');
        $recvCode = (string) ($c2?->code_currency?->name ?? '');

        $orderRate = $this->nonEmpty((string) ($task->course_display ?? ''));
        $liveRate = $this->nonEmpty((string) ($dir?->exchange_rate ?? ''));
        $currentRate = null;
        if ($liveRate !== null && $orderRate !== null && $this->normalizeRate($liveRate) !== $this->normalizeRate($orderRate)) {
            $currentRate = $liveRate;
        } elseif ($liveRate !== null && $orderRate === null) {
            $currentRate = $liveRate;
        }

        $giveFields = $this->fieldsFrom($task->tasks_fields_currency_in ?? collect());
        $giveFields = $this->prependIfMissing($giveFields, 'Сеть', $this->networkHint((string) ($c1?->designation_xml ?? ''), $givePs));
        $inboundWallet = $this->inboundWallet($task);
        $giveFields = $this->prependIfMissing($giveFields, 'Кошелек', $inboundWallet);

        $recvFields = $this->fieldsFrom($task->tasks_fields_currency_out ?? collect());
        $recvFields = $this->prependIfMissing($recvFields, 'Сеть', $this->networkHint((string) ($c2?->designation_xml ?? ''), $recvPs));
        $toShot = $this->plain((string) ($task->to_shot ?? ''));
        if ($toShot !== null && ! $this->valuesContain($recvFields, $toShot)) {
            $recvFields[] = ['label' => 'На счет', 'value' => $toShot];
        }

        $statusLabel = $this->statusLabel($task);
        $operator = $this->operatorLabel($task);

        try {
            return $this->payload(
                $task,
                $givePs,
                $giveCode,
                $recvPs,
                $recvCode,
                $orderRate,
                $currentRate,
                $giveFields,
                $recvFields,
                $statusLabel,
                $operator
            );
        } catch (Throwable) {
            return [
                'public_id' => (string) (($task->public_id ?: $task->id) ?? ''),
                'created_at' => '',
                'give_ps' => '',
                'give_amount' => '',
                'give_fields' => [],
                'receive_ps' => '',
                'receive_amount' => '',
                'order_rate' => null,
                'current_rate' => null,
                'receive_fields' => [],
                'user_id' => null,
                'email' => null,
                'name' => null,
                'operator' => null,
                'status_label' => null,
            ];
        }
    }

    private function payload(
        Task $task,
        string $givePs,
        string $giveCode,
        string $recvPs,
        string $recvCode,
        ?string $orderRate,
        ?string $currentRate,
        array $giveFields,
        array $recvFields,
        ?string $statusLabel,
        ?string $operator,
    ): array {
        return [
            'public_id' => (string) (($task->public_id ?: $task->id) ?? ''),
            'created_at' => $this->formatDate($task),
            'give_ps' => $givePs,
            'give_amount' => $this->formatAmount((string) ($task->give_price ?? ''), $giveCode),
            'give_fields' => $giveFields,
            'receive_ps' => $recvPs,
            'receive_amount' => $this->formatAmount((string) ($task->receiving_price ?? ''), $recvCode),
            'order_rate' => $orderRate,
            'current_rate' => $currentRate,
            'receive_fields' => $recvFields,
            'user_id' => isset($task->user) ? (string) ($task->user->id ?? '') : null,
            'email' => $this->nonEmpty((string) ($task->user?->email ?? '')),
            'name' => $this->nonEmpty((string) ($task->user?->name ?? '')),
            'operator' => $operator,
            'status_label' => $statusLabel,
        ];
    }

    public function renderText(array $payload): string
    {
        $lines = [];
        $id = $payload['public_id'] ?? '';
        $lines[] = '📋 Заявка №: '.$id;
        if (! empty($payload['created_at'])) {
            $lines[] = '📅 '.$payload['created_at'];
        }
        $lines[] = '';
        $lines[] = 'Отдает клиент:';
        if (! empty($payload['give_ps'])) {
            $lines[] = ' — ПС: '.$payload['give_ps'];
        }
        if (! empty($payload['give_amount'])) {
            $lines[] = ' — Сумма: '.$payload['give_amount'];
        }
        foreach ($payload['give_fields'] ?? [] as $row) {
            $lines[] = ' — '.$row['label'].': '.$row['value'];
        }
        $lines[] = '';
        $lines[] = 'Переводит сервис:';
        if (! empty($payload['receive_ps'])) {
            $lines[] = ' — ПС: '.$payload['receive_ps'];
        }
        if (! empty($payload['receive_amount'])) {
            $lines[] = ' — Сумма: '.$payload['receive_amount'];
        }
        if (! empty($payload['order_rate'])) {
            $lines[] = ' — Курс обмена: '.$payload['order_rate'];
        }
        if (! empty($payload['current_rate'])) {
            $lines[] = ' — Актуальный: '.$payload['current_rate'];
        }
        foreach ($payload['receive_fields'] ?? [] as $row) {
            $lines[] = ' — '.$row['label'].': '.$row['value'];
        }
        $lines[] = '--------------------------';
        $lines[] = 'Информация о пользователе';
        if (! empty($payload['user_id'])) {
            $lines[] = ' — ID: '.$payload['user_id'];
        }
        if (! empty($payload['email'])) {
            $lines[] = ' — E-mail: '.$payload['email'];
        }
        if (! empty($payload['name'])) {
            $lines[] = ' — Имя: '.$payload['name'];
        }
        $lines[] = '';
        $lines[] = '👤 Оператор:';
        $lines[] = $payload['operator'] ?: '—';
        $lines[] = '';
        $lines[] = '🟡 Статус:';
        $lines[] = $payload['status_label'] ?: '—';

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, TaskField>|iterable<TaskField>  $fields
     * @return list<array{label: string, value: string}>
     */
    private function fieldsFrom(iterable $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            try {
                $label = $this->nonEmpty((string) ($field->field_name ?? ''));
                $value = $this->plain((string) ($field->field_value ?? ''));
                $key = strtolower((string) ($field->field_key ?? ''));
                if ($label === null || $value === null) {
                    continue;
                }
                if ($this->looksSecret($label) || $this->looksSecret($key)) {
                    continue;
                }
                if (str_contains(mb_strtolower($label), 'telegram') || str_contains($key, 'telegram')) {
                    $label = 'Telegram';
                }
                $out[] = ['label' => $label, 'value' => $value];
            } catch (Throwable) {
                continue;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{label: string, value: string}>  $rows
     * @return list<array{label: string, value: string}>
     */
    private function prependIfMissing(array $rows, string $label, ?string $value): array
    {
        if ($value === null || $value === '') {
            return $rows;
        }
        if ($this->valuesContain($rows, $value)) {
            return $rows;
        }

        return array_merge([['label' => $label, 'value' => $value]], $rows);
    }

    /** @param  list<array{label: string, value: string}>  $rows */
    private function valuesContain(array $rows, string $value): bool
    {
        foreach ($rows as $row) {
            if (strcasecmp($row['value'], $value) === 0) {
                return true;
            }
        }

        return false;
    }

    private function paymentSystemLabel(mixed $currency): string
    {
        $pay = (string) ($currency?->payment?->name ?? '');
        $code = (string) ($currency?->code_currency?->name ?? '');
        $label = trim($pay.' '.$code);

        return $label !== '' ? $label : '';
    }

    private function inboundWallet(Task $task): ?string
    {
        $fromReq = $this->plain((string) ($task->payment_requisites?->account_number ?? ''));
        if ($fromReq !== null) {
            return $fromReq;
        }
        $addr = $this->plain((string) ($task->payment_address ?? ''));
        if ($addr !== null) {
            return $addr;
        }

        return $this->plain((string) ($task->from_shot ?? ''));
    }

    private function networkHint(string $xml, string $psLabel): ?string
    {
        $xml = strtoupper($xml);
        $map = [
            'TRC20' => 'TRC20',
            'BEP20' => 'BEP20',
            'ERC20' => 'ERC20',
            'POLYGON' => 'Polygon',
            'SOL' => 'Solana',
            'TON' => 'TON',
        ];
        foreach ($map as $needle => $label) {
            if (str_contains($xml, $needle) && ! str_contains(mb_strtolower($psLabel), mb_strtolower($label))) {
                return $label;
            }
        }

        return null;
    }

    private function statusLabel(Task $task): ?string
    {
        try {
            $name = $task->task_status?->name ?? null;
            if (is_array($name)) {
                $name = $name['ru'] ?? $name['en'] ?? reset($name);
            }
            $name = $this->nonEmpty((string) $name);
            if ($name !== null) {
                return $name;
            }
        } catch (Throwable) {
        }

        $id = (int) ($task->status ?? 0);

        return $id > 0 ? (string) $id : null;
    }

    private function operatorLabel(Task $task): ?string
    {
        try {
            $asg = OrderOperatorAssignment::query()
                ->where('task_id', $task->id)
                ->whereNull('released_at')
                ->latest('id')
                ->first();
            if ($asg !== null) {
                $asg->loadMissing('operator');
                $n = $this->nonEmpty((string) ($asg->operator?->name ?? $asg->operator?->email ?? ''));
                if ($n !== null) {
                    return $n;
                }
            }
        } catch (Throwable) {
        }
        try {
            $mgr = $task->edit_data_manager;
            $n = $this->nonEmpty((string) ($mgr?->name ?? $mgr?->email ?? ''));
            if ($n !== null) {
                return $n;
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function formatDate(Task $task): string
    {
        try {
            $at = $task->created_at;
            if ($at === null) {
                return '';
            }

            return $at->copy()->locale('ru')->translatedFormat('j M Y H:i');
        } catch (Throwable) {
            return (string) ($task->created_at ?? '');
        }
    }

    private function formatAmount(string $amount, string $code): string
    {
        $amount = trim($amount);
        if ($amount === '') {
            return $code;
        }
        if (str_contains($amount, '.')) {
            $amount = rtrim(rtrim($amount, '0'), '.');
        }
        $code = trim($code);

        return $code !== '' ? $amount.' '.$code : $amount;
    }

    private function plain(string $value): ?string
    {
        $value = trim(html_entity_decode(strip_tags($value)));

        return $this->nonEmpty($value);
    }

    private function nonEmpty(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeRate(string $rate): string
    {
        return strtolower(preg_replace('/\s+/', '', $rate) ?? $rate);
    }

    private function looksSecret(string $label): bool
    {
        $l = strtolower($label);
        foreach (self::SECRET_NEEDLES as $needle) {
            if (str_contains($l, $needle)) {
                return true;
            }
        }

        return false;
    }
}
