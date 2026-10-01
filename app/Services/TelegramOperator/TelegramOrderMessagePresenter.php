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

    public const LIFECYCLE_PAID = 'paid';

    public const LIFECYCLE_CLAIMED = 'claimed';

    public const LIFECYCLE_COMPLETED = 'completed';

    public const LIFECYCLE_CANCELLED = 'cancelled';

    public const LIFECYCLE_REJECTED = 'rejected';

    public const LIFECYCLE_EXPIRED = 'expired';

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
            $payload['customer_marked_paid'] = self::isCustomerMarkedPaidStatus((int) ($task->status ?? 0));
            $payload['risk_frozen'] = $this->isFrozenManualAttention($task);

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
                'customer_marked_paid' => false,
                'risk_frozen' => false,
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
        $lines[] = '📋 Заявка №'.$id;
        if (! empty($payload['created_at'])) {
            $lines[] = '🕐 Создана: '.$payload['created_at'];
        }
        $lines[] = '';
        $lines[] = '💰 ОПЛАТА';
        if (! empty($payload['give_ps'])) {
            $lines[] = (string) $payload['give_ps'];
        }
        if (! empty($payload['give_amount'])) {
            $lines[] = (string) $payload['give_amount'];
        }
        foreach ($payload['give_fields'] ?? [] as $row) {
            $lines[] = ($row['label'] ?? '').': '.($row['value'] ?? '');
        }
        $lines[] = '';
        $lines[] = '➡️ ВЫПЛАТА';
        if (! empty($payload['receive_ps'])) {
            $lines[] = (string) $payload['receive_ps'];
        }
        if (! empty($payload['receive_amount'])) {
            $lines[] = (string) $payload['receive_amount'];
        }
        $mode = (string) ($payload['rate_mode'] ?? 'floating');
        $lines[] = 'Тип курса: '.($mode === 'fixed' ? 'Фиксированный' : 'Плавающий');
        foreach ($this->rateLines($payload) as $rateLine) {
            $lines[] = $rateLine;
        }
        $recvFields = $payload['receive_fields'] ?? [];
        if ($recvFields !== []) {
            $lines[] = '';
            $lines[] = '👤 ПОЛУЧАТЕЛЬ';
            foreach ($recvFields as $row) {
                $lines[] = ($row['label'] ?? '').': '.($row['value'] ?? '');
            }
        }
        $contactLines = [];
        if (! empty($payload['email'])) {
            $contactLines[] = 'E-mail: '.$payload['email'];
        }
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name !== '' && strcasecmp($name, 'User') !== 0 && strcasecmp($name, 'Пользователь') !== 0) {
            $contactLines[] = 'Имя: '.$name;
        }
        if ($contactLines !== []) {
            $lines[] = '';
            $lines[] = '🙋 КЛИЕНТ';
            foreach ($contactLines as $c) {
                $lines[] = $c;
            }
        }
        $lines[] = '';
        $lines[] = $this->stateLine($payload);

        return implode("\n", $lines);
    }

    /**
     * Whether to SEND a separate PAID attention alert.
     *
     * Always: main card is EDITed on PAID elsewhere.
     * Always: every genuine customer PAID event also gets exactly one short alert
     * (normal or frozen/high-severity). Risk/freeze classification remains orthogonal
     * and is never treated as "no alert".
     *
     * @return array{send: bool, reason: string}
     */
    public function paidAttentionAlertDecision(Task $task): array
    {
        if ($this->isFrozenManualAttention($task)) {
            return ['send' => true, 'reason' => 'FROZEN_HIGH_RISK'];
        }

        return ['send' => true, 'reason' => 'NORMAL_PAID'];
    }

    private function isFrozenManualAttention(Task $task): bool
    {
        try {
            return (bool) ($task->is_frozen ?? false);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Short operator-attention alert for customer "I paid" — not a second full card.
     * Operator-critical fields only. Frozen/high-risk uses higher-severity wording;
     * freeze never suppresses the alert and never authorizes payout.
     */
    public function renderPaidAttentionAlert(array $payload): string
    {
        $id = (string) ($payload['public_id'] ?? '');
        $givePs = (string) ($payload['give_ps'] ?? '');
        $recvPs = (string) ($payload['receive_ps'] ?? '');
        $giveAmt = (string) ($payload['give_amount'] ?? '');
        $recvAmt = (string) ($payload['receive_amount'] ?? '');
        $operator = $this->nonEmpty((string) ($payload['operator'] ?? ''));
        $frozen = (bool) ($payload['risk_frozen'] ?? false);

        $lines = [];
        if ($operator !== null) {
            $lines[] = '🟡 Оператор: '.$operator;
        }
        $lines[] = $frozen ? '⚠️💰 Клиент отметил оплату' : '💰 Клиент отметил оплату';
        $lines[] = '';
        $lines[] = 'Заявка №'.$id;
        if ($giveAmt !== '' || $recvAmt !== '') {
            $lines[] = trim($giveAmt.($recvAmt !== '' ? ' → '.$recvAmt : ''));
        }
        if ($givePs !== '' || $recvPs !== '') {
            $lines[] = trim($givePs.($recvPs !== '' ? ' → '.$recvPs : ''));
        }
        $lines[] = '';
        if ($frozen) {
            $lines[] = '🚫 Выплата заблокирована до ручной проверки';
        } elseif ($operator !== null) {
            $lines[] = '🔎 Проверьте поступление средств';
        } else {
            $lines[] = '🔴 Требуется взять заявку и проверить оплату';
        }

        return implode("\n", $lines);
    }

    /**
     * Compact NEW card: direction, amounts, single rate, masked contact, status.
     * Omits deposit/payout field dumps until customer marks paid.
     */
    public function renderCompactNew(array $payload): string
    {
        $id = (string) ($payload['public_id'] ?? '');
        $givePs = (string) ($payload['give_ps'] ?? '');
        $recvPs = (string) ($payload['receive_ps'] ?? '');
        $lines = [
            '🆕 Новая заявка №'.$id,
            '',
        ];
        if ($givePs !== '' || $recvPs !== '') {
            $lines[] = 'Направление:';
            $lines[] = trim($givePs.' → '.$recvPs);
            $lines[] = '';
        }
        if (! empty($payload['give_amount'])) {
            $lines[] = 'Отдает: '.$payload['give_amount'];
        }
        if (! empty($payload['receive_amount'])) {
            $lines[] = 'Получает: '.$payload['receive_amount'];
        }
        foreach ($this->rateLines($payload, compact: true) as $rateLine) {
            $lines[] = $rateLine;
        }
        $contact = $this->compactContact($payload);
        if ($contact !== null) {
            $lines[] = 'Клиент: '.$contact;
        }
        $lines[] = '';
        $lines[] = '🟡 Ожидается оплата';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function rateLines(array $payload, bool $compact = false): array
    {
        $mode = (string) ($payload['rate_mode'] ?? 'floating');
        $orderRate = $this->nonEmpty((string) ($payload['order_rate'] ?? ''));
        $currentRate = $this->nonEmpty((string) ($payload['current_rate'] ?? ''));
        $life = (string) ($payload['lifecycle'] ?? self::LIFECYCLE_NEW);
        $prefix = '';

        if ($mode === 'fixed' || $currentRate === null || $orderRate === null
            || $this->normalizeRate($orderRate) === $this->normalizeRate($currentRate)
            || $life === self::LIFECYCLE_COMPLETED
            || self::isTerminalLifecycle($life)
        ) {
            if ($orderRate !== null) {
                return [$prefix.'Курс: '.$orderRate];
            }
            if ($currentRate !== null) {
                return [$prefix.'Курс: '.$currentRate];
            }

            return [];
        }

        // Floating: creation lock vs live direction quote (not settlement payout rate).
        return [
            $prefix.'Курс при создании: '.$orderRate,
            $prefix.'Курс направления сейчас: '.$currentRate,
        ];
    }

    private function compactContact(array $payload): ?string
    {
        $email = $this->nonEmpty((string) ($payload['email'] ?? ''));
        if ($email !== null) {
            return $this->maskEmail($email);
        }
        $name = $this->nonEmpty((string) ($payload['name'] ?? ''));
        if ($name !== null && strcasecmp($name, 'User') !== 0 && strcasecmp($name, 'Пользователь') !== 0) {
            return $name;
        }

        return null;
    }

    private function maskEmail(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at < 1) {
            return '***';
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at);
        $keep = min(1, strlen($local));

        return substr($local, 0, $keep).'***'.$domain;
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
        $paidMarked = (bool) ($payload['customer_marked_paid'] ?? false)
            || $life === self::LIFECYCLE_PAID;

        if ($life === self::LIFECYCLE_CANCELLED) {
            return '❌ Заявка отменена';
        }
        if ($life === self::LIFECYCLE_REJECTED) {
            return '🔴 Заявка отклонена';
        }
        if ($life === self::LIFECYCLE_EXPIRED) {
            return '⚪ Заявка истекла';
        }

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

        $lines = [];
        // Payment, ownership, and risk are orthogonal dimensions.
        if ($paidMarked) {
            $lines[] = '💰 Клиент отметил оплату';
            $lines[] = '🔎 Требуется проверить оплату';
        } elseif ($life === self::LIFECYCLE_NEW) {
            $lines[] = '🟡 Ожидается оплата';
        } else {
            $status = trim((string) ($payload['status_label'] ?? ''));
            $lines[] = $status !== '' ? '🟡 '.$status : '🟡 В работе';
        }

        if ($operator !== null) {
            $owner = '👤 В работе у: '.$operator;
            if ($claimed !== null) {
                $owner .= ' · '.$claimed;
            }
            $lines[] = $owner;
        } elseif ($life === self::LIFECYCLE_CLAIMED) {
            $lines[] = '🟡 В работе';
        }

        if ((bool) ($payload['risk_frozen'] ?? false)) {
            $lines[] = '⚠️ Риск: заморожено';
        }

        return implode("\n", $lines);
    }

    /**
     * Customer "I paid" / payment-check domain statuses (orthogonal to claim ownership).
     */
    public static function isCustomerMarkedPaidStatus(int $status): bool
    {
        return in_array($status, [3, 7, 12], true);
    }

    private function normalizeLifecycle(mixed $lifecycle, ?Task $task, mixed $operator): string
    {
        $life = is_string($lifecycle) ? $lifecycle : '';
        if (in_array($life, [
            self::LIFECYCLE_NEW,
            self::LIFECYCLE_PAID,
            self::LIFECYCLE_CLAIMED,
            self::LIFECYCLE_COMPLETED,
            self::LIFECYCLE_CANCELLED,
            self::LIFECYCLE_REJECTED,
            self::LIFECYCLE_EXPIRED,
        ], true)) {
            return $life;
        }
        try {
            if ($task !== null) {
                return self::lifecycleFromStatus((int) ($task->status ?? 0), $operator);
            }
        } catch (Throwable) {
        }
        $op = $this->nonEmpty((string) $operator);

        return $op !== null ? self::LIFECYCLE_CLAIMED : self::LIFECYCLE_NEW;
    }

    /**
     * Map authoritative TaskStatusEnum id to operator-card lifecycle.
     */
    public static function lifecycleFromStatus(int $status, mixed $operator = null): string
    {
        return match ($status) {
            1 => self::LIFECYCLE_EXPIRED,
            4 => self::LIFECYCLE_COMPLETED,
            5 => self::LIFECYCLE_REJECTED,
            6 => self::LIFECYCLE_CANCELLED,
            3, 7, 12 => self::LIFECYCLE_PAID,
            default => (is_string($operator) && trim($operator) !== '')
                ? self::LIFECYCLE_CLAIMED
                : self::LIFECYCLE_NEW,
        };
    }

    public static function isTerminalLifecycle(string $lifecycle): bool
    {
        return in_array($lifecycle, [
            self::LIFECYCLE_CANCELLED,
            self::LIFECYCLE_REJECTED,
            self::LIFECYCLE_EXPIRED,
            self::LIFECYCLE_COMPLETED,
        ], true);
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
                    if ($this->looksLikePhone($value)) {
                        $label = 'Телефон';
                    }
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
        if ($this->looksLikePhone($value)) {
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

    private function looksLikePhone(string $value): bool
    {
        $compact = preg_replace('/[\s\-()]/', '', $value) ?? $value;

        return preg_match('/^\+?[0-9]{6,15}$/', $compact) === 1;
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
        $id = (int) ($task->status ?? 0);
        $legacy = null;
        try {
            $name = $task->task_status?->name ?? null;
            if (is_array($name)) {
                $name = $name['ru'] ?? $name['en'] ?? reset($name);
            }
            $legacy = $this->nonEmpty((string) $name);
        } catch (Throwable) {
        }

        if ($id > 0) {
            return \App\Services\Orders\OperatorStatusVocabulary::telegramLabel($id, $legacy);
        }

        return $legacy;
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
