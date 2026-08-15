<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\DirectionExchange;
use App\Models\Task;
use Carbon\Carbon;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Result\ValidationResult;

/**
 * LimitRule — проверка лимитов создания заявок.
 *
 * Проверяет лимиты:
 * - глобальный лимит на пользователя (настройка max_num_order_user)
 * - лимиты направления: по пользователю, email, ip, реквизитам (from_shot/to_shot)
 * - дневные лимиты (whereDate(created_at))
 *
 * Важно:
 * - Не использует request()/auth()
 * - Email/IP берёт из Environment
 * - DirectionExchange берёт из ResourceBag
 */
final class LimitRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        $authId = (int)($context->data->get('authId') ?? 0);

        // Если нет пользователя (гость) — часть лимитов (по user) смысла не имеет,
        // но лимиты по email/ip/account работают.
        $email = $context->env->email() ?? '';
        $ip = $context->env->ip();

        $incomeAccount = $this->compactAccount($context->data->getString('income_account'));
        $outcomeAccount = $this->compactAccount($context->data->getString('outcome_account'));

        $checks = $this->buildChecks(
            direction: $direction,
            authId: $authId,
            email: $email,
            ip: $ip,
            incomeAccount: $incomeAccount,
            outcomeAccount: $outcomeAccount
        );

        foreach ($checks as $check) {
            if ($this->isExceeded($check['limit'], $check['current'])) {
                $result->addError(
                    field: $check['field'],
                    message: __($check['message'], $check['params'] ?? []),
                    code: $check['code'] ?? 'limit_exceeded'
                );
            }
        }

        return $result;
    }

    /**
     * Сформировать список проверок.
     *
     * @return array<int, array{
     *   limit:int,
     *   current:int,
     *   field:string,
     *   message:string,
     *   code?:string,
     *   params?:array<string,mixed>
     * }>
     */
    private function buildChecks(
        DirectionExchange $direction,
        int $authId,
        string $email,
        string $ip,
        ?string $incomeAccount,
        ?string $outcomeAccount
    ): array {
        // user counts (если authId=0, будет 0)
        $userTotal = $authId > 0 ? $this->countUserTasks($authId) : 0;
        $userDay = $authId > 0 ? $this->countUserTasks($authId, 'day') : 0;

        // email counts
        $emailTotal = $email !== '' ? $this->countEmailTasks($email) : 0;
        $emailDay = $email !== '' ? $this->countEmailTasks($email, 'day') : 0;

        // ip counts
        $ipTotal = $ip !== '' ? $this->countIpTasks($ip) : 0;
        $ipDay = $ip !== '' ? $this->countIpTasks($ip, 'day') : 0;

        // account counts
        $fromTotal = $incomeAccount ? $this->countAccountTasks($incomeAccount, 'from') : 0;
        $fromDay = $incomeAccount ? $this->countAccountTasks($incomeAccount, 'from', 'day') : 0;

        $toTotal = $outcomeAccount ? $this->countAccountTasks($outcomeAccount, 'to') : 0;
        $toDay = $outcomeAccount ? $this->countAccountTasks($outcomeAccount, 'to', 'day') : 0;

        return [
            [
                'limit' => (int)iEXSetting('max_num_order_user', 0),
                'current' => $userTotal,
                'field' => 'limit_order',
                'message' => 'Превышен лимит создания заявок, обратитесь к оператору',
                'code' => 'limit_user_total_global',
            ],
            [
                'limit' => (int)($direction->max_order_one_user ?? 0),
                'current' => $userTotal,
                'field' => 'limit_order',
                'message' => 'Превышен лимит создания заявок, обратитесь к оператору',
                'code' => 'limit_user_total_direction',
            ],
            [
                'limit' => (int)($direction->max_order_one_user_day ?? 0),
                'current' => $userDay,
                'field' => 'limit_order',
                'message' => 'Превышен дневной лимит создания заявок, обратитесь к оператору',
                'code' => 'limit_user_day_direction',
            ],

            [
                'limit' => (int)($direction->max_order_one_email ?? 0),
                'current' => $emailTotal,
                'field' => 'limit_order',
                'message' => 'Превышен лимит создания заявок с одного e-mail адреса',
                'code' => 'limit_email_total_direction',
            ],
            [
                'limit' => (int)($direction->max_order_one_email_day ?? 0),
                'current' => $emailDay,
                'field' => 'limit_order',
                'message' => 'Превышен дневной лимит создания заявок с одного e-mail адреса',
                'code' => 'limit_email_day_direction',
            ],

            [
                'limit' => (int)($direction->max_order_one_ip ?? 0),
                'current' => $ipTotal,
                'field' => 'limit_order',
                'message' => 'Превышен лимит создания заявок с одного ip адреса',
                'code' => 'limit_ip_total_direction',
            ],
            [
                'limit' => (int)($direction->max_order_one_ip_day ?? 0),
                'current' => $ipDay,
                'field' => 'limit_order',
                'message' => 'Превышен дневной лимит создания заявок с одного ip адреса',
                'code' => 'limit_ip_day_direction',
            ],

            [
                'limit' => (int)($direction->max_order_one_account1 ?? 0),
                'current' => $fromTotal,
                'field' => 'limit_order',
                'message' => 'Превышен лимит создания заявок для :account',
                'code' => 'limit_account_from_total_direction',
                'params' => ['account' => $incomeAccount ? security_xss($incomeAccount) : ''],
            ],
            [
                'limit' => (int)($direction->max_order_one_account1_day ?? 0),
                'current' => $fromDay,
                'field' => 'limit_order',
                'message' => 'Превышен дневной лимит создания заявок для :account',
                'code' => 'limit_account_from_day_direction',
                'params' => ['account' => $incomeAccount ? security_xss($incomeAccount) : ''],
            ],

            [
                'limit' => (int)($direction->max_order_one_account2 ?? 0),
                'current' => $toTotal,
                'field' => 'limit_order',
                'message' => 'Превышен лимит создания заявок для :account',
                'code' => 'limit_account_to_total_direction',
                'params' => ['account' => $outcomeAccount ? security_xss($outcomeAccount) : ''],
            ],
            [
                'limit' => (int)($direction->max_order_one_account2_day ?? 0),
                'current' => $toDay,
                'field' => 'limit_order',
                'message' => 'Превышен дневной лимит создания заявок для :account',
                'code' => 'limit_account_to_day_direction',
                'params' => ['account' => $outcomeAccount ? security_xss($outcomeAccount) : ''],
            ],
        ];
    }

    private function isExceeded(int $limit, int $current): bool
    {
        return $limit > 0 && $current >= $limit;
    }

    private function countUserTasks(int $userId, ?string $period = null): int
    {
        return Task::query()
            ->where('id_user', $userId)
            ->when($period === 'day', fn($q) => $q->whereDate('created_at', Carbon::today()))
            ->count();
    }

    private function countEmailTasks(string $email, ?string $period = null): int
    {
        return Task::query()
            ->where('email', $email)
            ->where('status', 4)
            ->when($period === 'day', fn($q) => $q->whereDate('created_at', Carbon::today()))
            ->count();
    }

    private function countIpTasks(string $ip, ?string $period = null): int
    {
        return Task::query()
            ->where('ip', $ip)
            ->when($period === 'day', fn($q) => $q->whereDate('created_at', Carbon::today()))
            ->count();
    }

    private function countAccountTasks(string $account, string $direction, ?string $period = null): int
    {
        $column = ($direction === 'from') ? 'from_shot' : 'to_shot';

        return Task::query()
            ->where('status', 4)
            ->where($column, security_xss($account))
            ->when($period === 'day', fn($q) => $q->whereDate('created_at', Carbon::today()))
            ->count();
    }

    /**
     * Компактная нормализация реквизита: trim + remove spaces.
     */
    private function compactAccount(?string $raw): ?string
    {
        if ($raw === null) return null;
        $s = trim((string)$raw);
        if ($s === '') return null;
        $s = preg_replace('/\s+/', '', $s) ?? $s;
        $s = trim($s);
        return $s !== '' ? $s : null;
    }
}
