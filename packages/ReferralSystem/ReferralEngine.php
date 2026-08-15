<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem;

use App\Models\Task;
use App\Models\User;
use App\Models\UserBalance;
use iEXPackages\ReferralSystem\Contracts\BonusBaseResolver;
use iEXPackages\ReferralSystem\Contracts\BonusCalculator;
use iEXPackages\ReferralSystem\Contracts\EligibilityRule;
use iEXPackages\ReferralSystem\Contracts\PayoutLimiter;
use iEXPackages\ReferralSystem\DTO\CreditResult;
use iEXPackages\ReferralSystem\DTO\ReferralContext;
use iEXPackages\ReferralSystem\DTO\ReferralMoney;
use iEXPackages\ReferralSystem\DTO\ReferralPreview;
use iEXPackages\ReferralSystem\Models\ReferralLink;
use iEXPackages\ReferralSystem\Models\ReferralLog;
use iEXPackages\ReferralSystem\Models\ReferralProgram;
use iEXPackages\ReferralSystem\Services\ReferralAuditLogger;
use iEXPackages\ReferralSystem\Support\ReferralMath;
use App\Settings\ReferralConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReferralEngine
{
    /**
     * @param EligibilityRule[] $rules
     */
    public function __construct(
        private BonusBaseResolver $baseResolver,
        private BonusCalculator $calculator,
        private PayoutLimiter $limiter,
        private array $rules = [],
        private ReferralAuditLogger $audit,
    ) {}

    // ---------------- REGISTER ----------------

    public function register(User $user, ?string $ref = null): void
    {
        $this->audit->info('register_start', 'Старт регистрации в реферальной системе', ['client_user_id' => $user->id], ['ref' => $ref]);

        $this->attachClientToPartner($user, $ref);
        $this->assignDefaultReferralProgramIfMissing($user);
        $this->createBalanceIfMissing($user);

        $this->audit->info('register_finish', 'Регистрация в реферальной системе завершена', ['client_user_id' => $user->id]);
    }

    public function attachClientToPartner(User $client, ?string $ref = null): void
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            $this->audit->info('attach_skip', 'Привязка партнёра пропущена: ref пустой', ['client_user_id' => $client->id]);
            return;
        }

        $refLink = $this->resolveReferralLinkByRef($ref);
        if (!$refLink || !$refLink->user_id) {
            $this->audit->warning('attach_failed', 'Привязка партнёра не выполнена: ссылка не найдена', ['client_user_id' => $client->id], ['ref' => $ref]);
            return;
        }

        if ((int)$refLink->user_id === (int)$client->id) {
            $this->audit->warning('self_referral', 'Самореферал запрещён (регистрация)', [
                'client_user_id' => $client->id,
                'partner_user_id' => $refLink->user_id,
                'referral_link_id' => $refLink->id,
            ], ['ref' => $ref]);
            return;
        }

        $refLink->relationships()->firstOrCreate(['user_id' => $client->id]);

        $partnerName = (string) ($refLink->user?->name ?? ('ID ' . (int) $refLink->user_id));
        $this->audit->info('client_attached', 'Клиент привязан к партнёру', [
            'client_user_id' => $client->id,
            'partner_user_id' => $refLink->user_id,
            'referral_link_id' => $refLink->id,
            'referral_program_id' => $refLink->referral_program_id,
        ], ['ref' => $ref, 'partner_name' => $partnerName]);
    }

    public function assignDefaultReferralProgramIfMissing(User $user): void
    {
        if (ReferralLink::where('user_id', $user->id)->exists()) {
            return;
        }

        /** @var ReferralConfig $cfg */
        $cfg = app(ReferralConfig::class);
        $defaultProgramId = (int) $cfg->defaultReferralProgramId();

        if ($defaultProgramId <= 0) {
            $this->audit->warning('program_missing', 'Не задана дефолтная реферальная программа (default_referral_program_id=0)', [
                'client_user_id' => $user->id,
            ]);
            return;
        }

        $program = ReferralProgram::query()->find($defaultProgramId);
        if (!$program) {
            $this->audit->warning('program_missing', 'Дефолтная реферальная программа не найдена по ID', [
                'client_user_id' => $user->id,
            ], [
                'default_referral_program_id' => $defaultProgramId,
            ]);
            return;
        }

        $link = ReferralLink::create([
            'user_id' => $user->id,
            'referral_program_id' => $program->id,
        ]);

        $this->audit->info('program_assigned', 'Пользователю назначена дефолтная реферальная программа', [
            'client_user_id' => $user->id,
            'referral_link_id' => $link->id,
            'referral_program_id' => $program->id,
        ], [
            'default_referral_program_id' => $defaultProgramId,
        ]);
    }

    public function createBalanceIfMissing(User $user): void
    {
        if (UserBalance::where('id_user', $user->id)->exists()) {
            return;
        }

        UserBalance::create([
            'id_user' => $user->id,
            'id_code_currency' => (int) iEXSetting('id_referral_code_currency'),
            'balance' => '0',
            'hold_balance' => '0',
            'referral_total_profit' => '0',
            'referral_total_withdrawal' => '0',
        ]);

        $this->audit->info('balance_created', 'Создан баланс пользователя для реферальной системы', ['client_user_id' => $user->id]);
    }

    // ---------------- CREDIT ----------------

    public function creditForTask(Task $task, ?string $bonusCurrency = null): CreditResult
    {
        return $this->creditForTaskWithSuffix($task, $bonusCurrency, null);
    }

    public function restartForTask(Task $task, string $reason = 'recalculate'): CreditResult
    {
        $this->reverseForTask($task, $reason);
        $suffix = 'retry:' . now()->timestamp;
        return $this->creditForTaskWithSuffix($task, null, $suffix);
    }

    public function reverseForTask(Task $task, string $reason = 'clawback'): bool
    {
        $taskId = (int) $task->id;
        $bonusKey = $this->makeBonusEventKey($taskId, null);
        $reversalKey = $this->makeReversalEventKey($taskId);

        return (bool) DB::transaction(function () use ($bonusKey, $reversalKey, $reason) {
            /** @var ReferralLog|null $origin */
            $origin = ReferralLog::where('event_key', $bonusKey)
                ->where('is_reversal', 0)
                ->lockForUpdate()
                ->first();

            if (!$origin) {
                return false;
            }

            if (ReferralLog::where('event_key', $reversalKey)->exists()) {
                return false;
            }

            $amount = ReferralMath::norm((string) $origin->bonus_number);
            if (ReferralMath::isZero($amount)) {
                return false;
            }

            $balance = $this->getOrCreatePartnerBalance((int) $origin->id_user);

            if ((string) $origin->status === 'pending') {
                $this->safeDecrement($balance, 'hold_balance', $amount);
            } else {
                $this->safeDecrement($balance, 'balance', $amount);
                $this->safeDecrement($balance, 'referral_total_profit', $amount);
            }

            DB::table('referral_log')->insertOrIgnore([
                'event_key'        => $reversalKey,
                'status'           => 'confirmed',
                'available_at'     => null,
                'confirmed_at'     => now(),
                'reversed_at'      => null,

                'is_reversal'      => 1,
                'reversal_of_id'   => $origin->id,
                'reason'           => $reason,

                'id_referral_link' => $origin->id_referral_link,
                'id_task'          => $origin->id_task,
                'id_referral'      => $origin->id_referral,
                'id_user'          => $origin->id_user,

                'text'             => 'Сторно реферального бонуса (' . $reason . ')',
                'bonus'            => '-' . (string) $origin->bonus,
                'bonus_number'     => '-' . $amount,
                'fixed_bonus'      => (string) ($origin->fixed_bonus ?? '0'),
                'current_percent'  => (string) ($origin->current_percent ?? '0'),

                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $origin->update([
                'status' => 'reversed',
                'reversed_at' => now(),
            ]);

            $this->audit->info('credit_reversed', 'Выполнено сторно реферального начисления', [
                'task_id' => $origin->id_task,
                'partner_user_id' => $origin->id_user,
                'client_user_id' => $origin->id_referral,
                'referral_link_id' => $origin->id_referral_link,
            ], [
                'bonus_event_key' => $origin->event_key,
                'reversal_event_key' => $reversalKey,
                'amount' => $amount,
                'reason' => $reason,
            ]);

            return true;
        });
    }

    public function settleDueHolds(int $limit = 500): int
    {
        $rows = ReferralLog::query()
            ->where('status', 'pending')
            ->where('is_reversal', 0)
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $count = 0;

        foreach ($rows as $row) {
            DB::transaction(function () use ($row, &$count) {
                /** @var ReferralLog|null $log */
                $log = ReferralLog::where('id', $row->id)->lockForUpdate()->first();
                if (!$log || $log->status !== 'pending') {
                    return;
                }

                $amount = ReferralMath::norm((string) $log->bonus_number);
                $balance = $this->getOrCreatePartnerBalance((int) $log->id_user);

                if (!ReferralMath::isZero($amount)) {
                    $this->safeDecrement($balance, 'hold_balance', $amount);
                    $balance->increment('balance', (float) $amount);
                    $balance->increment('referral_total_profit', (float) $amount);
                }

                $log->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                ]);

                $this->audit->info('hold_settled', 'HOLD подтвержден, бонус стал доступен', [
                    'task_id' => $log->id_task,
                    'partner_user_id' => $log->id_user,
                    'client_user_id' => $log->id_referral,
                    'referral_link_id' => $log->id_referral_link,
                ], [
                    'event_key' => $log->event_key,
                    'amount' => $amount,
                ]);

                $count++;
            });
        }

        return $count;
    }

    private function creditForTaskWithSuffix(Task $task, ?string $bonusCurrency, ?string $suffix): CreditResult
    {
        $refHash = trim((string) ($task->referral_hash ?? ''));
        if ($refHash === '') {
            return new CreditResult(false, 'Нет referral_hash у заявки');
        }

        $refLink = ReferralLink::with('user', 'program')->where('code', $refHash)->first();
        if (!$refLink || !$refLink->user) {
            return new CreditResult(false, 'Реферальная ссылка/партнёр не найдены');
        }

        if ((int) $refLink->user_id === (int) ($task->id_user ?? 0)) {
            return new CreditResult(false, 'Самореферал запрещён');
        }

        $bonusCurrency ??= $this->getBonusCurrency();

        $context = new ReferralContext(
            task: $task,
            eventKey: null,
            client: $task->user,
            partner: $refLink->user,
            direction: $task->direction_exchange,
            referralLink: $refLink,
            bonusCurrency: $bonusCurrency,
        );

        return $this->creditTaskWithReferralLog($context, $this->makeBonusEventKey((int) $task->id, $suffix));
    }

    private function creditTaskWithReferralLog(ReferralContext $context, string $eventKey): CreditResult
    {
        foreach ($this->rules as $rule) {
            $reason = $rule->check($context);
            if ($reason !== null) {
                return new CreditResult(false, $reason);
            }
        }

        if (!$context->task) {
            return new CreditResult(false, 'Task обязателен для начисления');
        }

        $taskId = (int) $context->task->id;
        $lockKey = 'referral:task:' . $taskId;

        $run = function () use ($context, $eventKey): CreditResult {
            if (ReferralLog::where('event_key', $eventKey)->exists()) {
                return new CreditResult(false, 'Начисление уже выполнено');
            }

            $referralLinkId = (int) ($context->referralLink?->id ?? 0);
            if ($referralLinkId <= 0) {
                return new CreditResult(false, 'Referral link ID не найден');
            }

            $base = $this->baseResolver->resolveBase($context);
            $calc = $this->calculator->calculate($context, $base);

            $amountRaw = (string) ($calc['amount'] ?? '0');
            $amountRaw = $this->limiter->apply($context, $amountRaw);

            $amount = ReferralMath::norm($amountRaw);
            if (ReferralMath::isZero($amount)) {
                return new CreditResult(false, 'Сумма выплаты равна 0', null, ReferralMoney::zero($context->bonusCurrency, ReferralMath::SCALE));
            }

            $amountStr = sprintf('%s %s', ReferralMath::display($amount), $context->bonusCurrency);

            $holdDays = $this->getHoldDays();
            $status = ($holdDays > 0) ? 'pending' : 'confirmed';
            $availableAt = ($holdDays > 0) ? now()->addDays($holdDays) : null;
            $confirmedAt = ($status === 'confirmed') ? now() : null;

            return DB::transaction(function () use ($context, $referralLinkId, $amount, $amountStr, $calc, $eventKey, $status, $availableAt, $confirmedAt): CreditResult {
                $existsTx = ReferralLog::where('event_key', $eventKey)->lockForUpdate()->exists();
                if ($existsTx) {
                    return new CreditResult(false, 'Начисление уже выполнено (tx)');
                }

                $context->task->update(['id_referral_link' => $referralLinkId]);

                $inserted = DB::table('referral_log')->insertOrIgnore([
                    'event_key'        => $eventKey,
                    'status'           => $status,
                    'available_at'     => $availableAt,
                    'confirmed_at'     => $confirmedAt,
                    'reversed_at'      => null,

                    'is_reversal'      => 0,
                    'reversal_of_id'   => null,
                    'reason'           => null,

                    'id_referral_link' => $referralLinkId,
                    'id_task'          => $context->task->id,
                    'id_referral'      => $context->client->id,
                    'id_user'          => $context->partner->id,

                    'bonus'            => $amountStr,
                    'bonus_number'     => $amount,
                    'text'             => 'Бонус за обмен (' . ($context->direction->tech_name ?? '') . ')',
                    'fixed_bonus'      => (string) ($calc['fixed'] ?? '0'),
                    'current_percent'  => (string) ($calc['percent'] ?? '0'),

                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                if ((int) $inserted === 0) {
                    return new CreditResult(false, 'Дубликат начисления');
                }

                $balance = $this->getOrCreatePartnerBalance((int) $context->partner->id);

                if ($status === 'pending') {
                    $balance->increment('hold_balance', (float) $amount);
                } else {
                    $balance->increment('balance', (float) $amount);
                    $balance->increment('referral_total_profit', (float) $amount);
                }

                $this->audit->info('credit_success', 'Начислен реферальный бонус', [
                    'task_id' => $context->task->id,
                    'partner_user_id' => $context->partner->id,
                    'client_user_id' => $context->client->id,
                    'referral_link_id' => $referralLinkId,
                ], [
                    'event_key' => $eventKey,
                    'status' => $status,
                    'available_at' => $availableAt?->toIso8601String(),
                    'amount' => $amount,
                    'currency' => $context->bonusCurrency,
                ]);

                return new CreditResult(true, 'OK', null, new ReferralMoney($amount, $context->bonusCurrency, ReferralMath::SCALE));
            });
        };

        try {
            if (method_exists(Cache::class, 'lock')) {
                /** @var CreditResult $result */
                return Cache::lock($lockKey, 10)->block(5, $run);
            }
            return $run();
        } catch (Throwable $e) {
            $this->audit->error('credit_error', 'Ошибка начисления: ' . $e->getMessage(), [
                'task_id' => $context->task->id ?? null,
            ], ['event_key' => $eventKey]);

            Log::error('Referral credit failed', ['task_id' => $context->task->id ?? null, 'error' => $e->getMessage(), 'event_key' => $eventKey]);

            return new CreditResult(false, 'Ошибка начисления: ' . $e->getMessage());
        }
    }

    // ---------------- PREVIEW ----------------

    public function previewForTask(Task $task, ?string $bonusCurrency = null): ReferralPreview
    {
        $refHash = trim((string) ($task->referral_hash ?? ''));
        if ($refHash === '') {
            return new ReferralPreview(false, 'Нет referral_hash у заявки');
        }

        $refLink = ReferralLink::with('user', 'program')->where('code', $refHash)->first();
        if (!$refLink || !$refLink->user) {
            return new ReferralPreview(false, 'Реферальная ссылка/партнёр не найдены');
        }

        if ((int) $refLink->user_id === (int) ($task->id_user ?? 0)) {
            return new ReferralPreview(false, 'Самореферал запрещён');
        }

        $bonusCurrency ??= $this->getBonusCurrency();

        $context = new ReferralContext(
            task: $task,
            eventKey: null,
            client: $task->user,
            partner: $refLink->user,
            direction: $task->direction_exchange,
            referralLink: $refLink,
            bonusCurrency: $bonusCurrency,
        );

        foreach ($this->rules as $rule) {
            $reason = $rule->check($context);
            if ($reason !== null) {
                return new ReferralPreview(false, $reason);
            }
        }

        $base = $this->baseResolver->resolveBase($context);
        $calc = $this->calculator->calculate($context, $base);

        $amount = $this->limiter->apply($context, (string) ($calc['amount'] ?? '0'));

        return new ReferralPreview(
            eligible: true,
            message: ReferralMath::isZero($amount) ? 'Сумма выплаты равна 0 (процент/фикс/лимиты)' : 'OK',
            partnerId: $context->partner->id,
            clientId: $context->client->id,
            referralLinkId: $context->referralLink?->id,
            referralProgramId: $context->referralLink?->referral_program_id,
            baseAmount: (string) $base->amount,
            amount: ReferralMath::norm($amount),
            currency: $context->bonusCurrency,
            method: (string) ($calc['method'] ?? 'exchange'),
            percent: (string) ($calc['percent'] ?? '0'),
            fixed: (string) ($calc['fixed'] ?? '0'),
            profitPercent: (string) ($calc['profit_percent'] ?? '0'),
            profitFixed: (string) ($calc['profit_fixed'] ?? '0'),
            exchangeProfit: (string) ($calc['exchange_profit'] ?? '0'),
            profitBaseAfter: (string) ($calc['profit_base_after'] ?? '0'),
        );
    }

    // ---------------- HELPERS ----------------

    private function resolveReferralLinkByRef(string $ref): ?ReferralLink
    {
        if (str_starts_with($ref, 'partnerId:')) {
            $id = (int) substr($ref, strlen('partnerId:'));
            if ($id <= 0) return null;

            return ReferralLink::with('user', 'program')->where('user_id', $id)->first();
        }

        return ReferralLink::with('user', 'program')->where('code', $ref)->first();
    }

    private function getBonusCurrency(): string
    {
        $cfg = config('partners-bonus');
        return (string) ($cfg['name'] ?? 'USD');
    }

    private function getHoldDays(): int
    {
        return max(0, (int) iEXSetting('referral_hold_days', 0));
    }

    private function makeBonusEventKey(int $taskId, ?string $suffix): string
    {
        return ($suffix !== null && $suffix !== '') ? "task:{$taskId}:bonus:{$suffix}" : "task:{$taskId}:bonus";
    }

    private function makeReversalEventKey(int $taskId): string
    {
        return "task:{$taskId}:reversal";
    }

    private function getOrCreatePartnerBalance(int $userId): UserBalance
    {
        return UserBalance::firstOrCreate(
            ['id_user' => $userId],
            [
                'id_code_currency' => (int) iEXSetting('id_referral_code_currency'),
                'balance' => '0',
                'hold_balance' => '0',
                'referral_total_profit' => '0',
                'referral_total_withdrawal' => '0',
            ]
        );
    }

    /**
     * Защита: не уходим в минус.
     */
    private function safeDecrement(UserBalance $balance, string $field, string $amount): void
    {
        $current = ReferralMath::norm((string) ($balance->{$field} ?? '0'));
        $next = ReferralMath::sub($current, $amount);

        if (ReferralMath::cmp($next, '0') === -1) {
            $next = '0';
        }

        // set напрямую — точнее, чем decrement(float)
        $balance->{$field} = $next;
        $balance->save();
    }


    /**
     * Назначает пользователю реферальную программу (создаёт ReferralLink при необходимости).
     * Делает это идемпотентно: повторный вызов не создаст дубль.
     *
     * @return ReferralLink
     */
    public function registerUserToReferralProgram(User $user, int $referralProgramId): ReferralLink
    {
        $referralProgramId = max(0, (int) $referralProgramId);

        // Создаём/находим связь именно по user_id + referral_program_id
        $link = ReferralLink::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'referral_program_id' => $referralProgramId,
            ],
            [
                'user_id' => $user->id,
                'referral_program_id' => $referralProgramId,
            ]
        );

        // Баланс нужен в любом случае (как раньше)
        $this->createBalanceIfMissing($user);

        // Логируем только если запись новая
        if ($link->wasRecentlyCreated) {
            // новый лог (понятный и централизованный)
            $this->audit->info('program_assigned', 'Пользователь подключен к партнёрской программе', [
                'client_user_id' => $user->id,
                'referral_link_id' => $link->id,
                'referral_program_id' => $referralProgramId,
            ], [
                'program_id' => $referralProgramId,
            ]);
        }

        return $link;
    }
}
