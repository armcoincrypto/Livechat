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
    public const LIFECYCLE_NEW = 'new';

    public const LIFECYCLE_CLAIMED = 'claimed';

    public const LIFECYCLE_COMPLETED = 'completed';

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
    public function present(Task $task, ?string $lifecycle = null): array
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

        $giveFields = $this->fieldsFrom($task->tasks_fields_currency_in ?? collect(), 'in');
        $giveFields = $this->prependIfMissing($giveFields, 'Сеть', $this->networkHint((string) ($c1?->designation_xml ?? ''), $givePs));
        // Service inbound destination (customer pays TO this). Not the customer payout wallet.
        $giveFields = $this->prependIfMissing($giveFields, 'Адрес для депозита', $this->inboundWallet($task));

        $recvFields = $this->fieldsFrom($task->tasks_fields_currency_out ?? collect(), 'out');
        $recvFields = $this->prependIfMissing($recvFields, 'Сеть', $this->networkHint((string) ($c2?->designation_xml ?? ''), $recvPs));
        $toShot = $this->plain((string) ($task->to_shot ?? ''));
        if ($toShot !== null && ! $this->valuesContain($recvFields, $toShot)) {
            $recvFields[] = ['label' => 'На счет', 'value' => $toShot];
        }

        $statusLabel = $this->statusLabel($task);
        $operator = $this->operatorLabel($task);
        $rateMode = $this->rateMode($task);
        if ($rateMode === 'fixed') {
            $currentRate = null;
        }

        try {
            $payload = $this->payload(
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
                $operator,
                $rateMode
            );
            $payload['lifecycle'] = $this->normalizeLifecycle($lifecycle, $task, $operator);
            $payload['completed_at'] = $this->completedAt($task, $payload['lifecycle']);

            return $payload;
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
                'rate_mode' => 'floating',
                'claimed_at' => null,
                'lifecycle' => self::LIFECYCLE_NEW,
                'completed_at' => null,
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
        string $rateMode,
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
            'rate_mode' => $rateMode,
            'claimed_at' => $this->claimedAt($task),
        ];
    }


    /**
     * Canonical populated operational rows for admin + Telegram.
     *
     * @return array{
     *   rows: list<array{label: string, value: string, group: string}>,
     *   deposit_address: ?string,
     *   payout_wallet: ?string,
     *   operator: ?string,
     *   claimed_at: ?string
     * }
     */
    public function operationalRequisites(Task $task): array
    {
        $payload = $this->present($task);
        $rows = [];
        $seen = [];
        $deposit = null;
        $payout = null;

        $push = static function (string $label, ?string $value, string $group) use (&$rows, &$seen): void {
            $value = trim((string) $value);
            if ($value === '') {
                return;
            }
            $key = mb_strtolower($label.'|'.$value);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $rows[] = ['label' => $label, 'value' => $value, 'group' => $group];
        };

        foreach ($payload['give_fields'] ?? [] as $row) {
            $label = (string) ($row['label'] ?? '');
            $value = (string) ($row['value'] ?? '');
            $push($label, $value, 'give');
            if ($deposit === null && (str_contains(mb_strtolower($label), 'депозит') || str_contains(mb_strtolower($label), 'deposit'))) {
                $deposit = $value;
            }
        }
        foreach ($payload['receive_fields'] ?? [] as $row) {
            $label = (string) ($row['label'] ?? '');
            $value = (string) ($row['value'] ?? '');
            $push($label, $value, 'receive');
            $lk = mb_strtolower($label);
            if ($payout === null && (str_contains($lk, 'кошел') || str_contains($lk, 'wallet') || $label === 'На счет')) {
                $payout = $value;
            }
        }
        $push('E-mail', $payload['email'] ?? null, 'contact');
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name !== '' && strcasecmp($name, 'User') !== 0 && strcasecmp($name, 'Пользователь') !== 0) {
            $push('Имя', $name, 'contact');
        }

        return [
            'rows' => $rows,
            'deposit_address' => $deposit,
            'payout_wallet' => $payout,
            'operator' => $payload['operator'] ?? null,
            'claimed_at' => $payload['claimed_at'] ?? null,
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
        $lines[] = 'Получает клиент:';
        if (! empty($payload['receive_ps'])) {
            $lines[] = ' — ПС: '.$payload['receive_ps'];
        }
        if (! empty($payload['receive_amount'])) {
            $lines[] = ' — Сумма: '.$payload['receive_amount'];
        }
        $mode = (string) ($payload['rate_mode'] ?? 'floating');
        $lines[] = ' — Тип курса: '.($mode === 'fixed' ? 'Фиксированный' : 'Плавающий');
        if (! empty($payload['order_rate'])) {
            $lines[] = ' — Курс заявки: '.$payload['order_rate'];
        }
        $life = (string) ($payload['lifecycle'] ?? self::LIFECYCLE_NEW);
        if ($mode === 'floating' && ! empty($payload['current_rate']) && $life !== self::LIFECYCLE_COMPLETED) {
            $lines[] = ' — Текущий курс: '.$payload['current_rate'];
        }
        foreach ($payload['receive_fields'] ?? [] as $row) {
            $lines[] = ' — '.$row['label'].': '.$row['value'];
        }
        if (! empty($payload['email'])) {
            $lines[] = ' — E-mail: '.$payload['email'];
        }
        $lines[] = '';
        $lines[] = $this->stateLine($payload);

        return implode("\n", $lines);
    }

    public function renderCompletionPrompt(array $payload): string
    {
        $id = (string) ($payload['public_id'] ?? '');
        $lines = [
            'Подтвердить завершение заявки №'.$id.'?',
            '',
        ];
        if (! empty($payload['receive_amount'])) {
            $ps = (string) ($payload['receive_ps'] ?? '');
            $lines[] = trim($payload['receive_amount'].($ps !== '' ? ' → '.$ps : ''));
        }
        foreach ($payload['receive_fields'] ?? [] as $row) {
            $label = mb_strtolower($row['label'] ?? '');
            if (str_contains($label, 'фио') || str_contains($label, 'карт') || str_contains($label, 'card') || str_contains($label, 'кошел') || str_contains($label, 'телефон') || str_contains($label, 'phone')) {
                $lines[] = ($row['label'] ?? '').': '.$row['value'];
            }
        }

        return implode("\n", $lines);
    }

    private function stateLine(array $payload): string
    {
        $life = $this->normalizeLifecycle($payload['lifecycle'] ?? null, null, $payload['operator'] ?? null);
        $operator = $this->nonEmpty((string) ($payload['operator'] ?? ''));
        $claimed = $this->nonEmpty((string) ($payload['claimed_at'] ?? ''));
        $completed = $this->nonEmpty((string) ($payload['completed_at'] ?? ''));
        $clock = $completed ?? $claimed;

        if ($life === self::LIFECYCLE_COMPLETED) {
            $lines = ['✅ Выполнено'];
            if ($operator !== null) {
                $bits = ['👤 '.$operator];
                if ($clock !== null) {
                    $bits[] = '🕐 '.$clock;
                }
                $lines[] = implode(' · ', $bits);
            }

            return implode("\n", $lines);
        }

        if ($life === self::LIFECYCLE_CLAIMED && $operator !== null) {
            $bits = ['👤 '.$operator];
            if ($claimed !== null) {
                $bits[] = '🕐 '.$claimed;
            }
            $bits[] = '🟡 В работе';

            return implode(' · ', $bits);
        }

        $status = trim((string) ($payload['status_label'] ?? ''));

        return $status !== '' ? '🟡 '.$status : '🟡 Ожидается оплата';
    }

    private function normalizeLifecycle(mixed $lifecycle, ?Task $task, mixed $operator): string
    {
        $life = is_string($lifecycle) ? $lifecycle : '';
        if (in_array($life, [self::LIFECYCLE_NEW, self::LIFECYCLE_CLAIMED, self::LIFECYCLE_COMPLETED], true)) {
            return $life;
        }
        try {
            if ($task !== null && (int) ($task->status ?? 0) === 4) {
                return self::LIFECYCLE_COMPLETED;
            }
        } catch (Throwable) {
        }
        $op = $this->nonEmpty((string) $operator);

        return $op !== null ? self::LIFECYCLE_CLAIMED : self::LIFECYCLE_NEW;
    }

    private function completedAt(Task $task, string $lifecycle): ?string
    {
        if ($lifecycle !== self::LIFECYCLE_COMPLETED) {
            return null;
        }
        try {
            $at = $task->updated_at ?? $task->created_at ?? null;
            if ($at === null) {
                return now()->format('H:i');
            }

            return $at->format('H:i');
        } catch (Throwable) {
            return now()->format('H:i');
        }
    }

    private function rateMode(Task $task): string
    {
        try {
            $dirEnables = (int) ($task->direction_exchange?->is_type_rate ?? $task->is_type_rate ?? 0) === 1;
            if (! $dirEnables) {
                return 'fixed';
            }
            $orderMode = $task->type_rate ?? null;
            if ($orderMode === null || $orderMode === '') {
                return 'floating';
            }

            return (int) $orderMode === 1 ? 'floating' : 'fixed';
        } catch (Throwable) {
            return 'floating';
        }
    }

    private function claimedAt(Task $task): ?string
    {
        try {
            $asg = OrderOperatorAssignment::query()
                ->where('task_id', $task->id)
                ->whereNull('released_at')
                ->latest('id')
                ->first();
            $at = $asg?->claimed_at;
            if ($at === null) {
                return null;
            }

            return $at->format('H:i');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  Collection<int, TaskField>|iterable<TaskField>  $fields
     * @return list<array{label: string, value: string}>
     */
    private function fieldsFrom(iterable $fields, string $side = 'out'): array
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
                $label = $this->canonicalLabel($label, $key, $side);
                if ($label === 'Telegram') {
                    $value = $this->normalizeTelegramHandle($value) ?? $value;
                }
                $out[] = ['label' => $label, 'value' => $value];
            } catch (Throwable) {
                continue;
            }
        }

        return $out;
    }

    private function canonicalLabel(string $label, string $key, string $side): string
    {
        $lk = mb_strtolower($label.' '.$key);
        if (str_contains($lk, 'telegram')) {
            return 'Telegram';
        }
        if (str_contains($lk, 'email') || str_contains($lk, 'e-mail') || str_contains($lk, 'почт')) {
            return 'E-mail';
        }
        if (str_contains($lk, 'iban')) {
            return $label;
        }
        if (str_contains($lk, 'карт') || str_contains($lk, 'card')) {
            return 'Карта';
        }
        if (str_contains($lk, 'phone') || str_contains($lk, 'телефон')) {
            return 'Телефон';
        }
        if (str_contains($lk, 'фио') || str_contains($lk, 'recipient') || str_contains($lk, 'fullname') || str_contains($lk, 'full name')) {
            return 'ФИО';
        }
        // Customer payout wallet on the receive leg. Form CMS often labels this "Адрес для депозита".
        if ($side === 'out' && $this->looksLikeWalletLabel($lk)) {
            return 'Кошелек';
        }

        return $label;
    }

    private function normalizeTelegramHandle(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        // Emails in the Telegram field stay emails.
        if (str_contains($value, '@') && preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $value) === 1) {
            return $value;
        }
        $value = preg_replace('#^(https?://)?(t\.me|telegram\.me)/#i', '', $value) ?? $value;
        $value = ltrim($value, '@');
        $value = ltrim($value);
        if ($value === '' || str_contains($value, ' ')) {
            return null;
        }

        return '@'.$value;
    }

    private function looksLikeWalletLabel(string $lk): bool
    {
        foreach (['депозит', 'кошел', 'wallet', 'address', 'adress', 'адрес'] as $needle) {
            if (str_contains($lk, $needle)) {
                return true;
            }
        }

        return false;
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
        $fromReq = $this->plain((string) ($task->payment_requisites?->account_number
            ?? $task->payment_requisites?->account_number
            ?? ''));
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
