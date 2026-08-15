<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\Banned;
use App\Models\DirectionExchange;
use App\Models\Task;
use App\Models\User;
use App\Models\VerificationCard;
use App\Services\OrderLimitResolver;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Result\ValidationResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * UserRule — пользовательские ограничения при создании заявки.
 *
 * Перенос логики UserValidator в новую систему:
 * - НЕ использует request()/auth()
 * - Возвращает ValidationResult
 * - Правило НЕ выполняет сайд-эффекты (бан/изменения в БД) — только возвращает ошибку
 *
 * Требования к контексту:
 * - resources: DirectionExchange, опционально User
 * - env: email, ip
 * - data: authId, income_amount, income_account/outcome_account
 */
final class UserRule implements ValidationRuleInterface
{
    /** Кэш лимитов от OrderLimitResolver */
    private ?array $orderLimits = null;

    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        $authId = (int)($context->data->get('authId') ?? 0);
        $email = $context->env->email() ?? '';
        $ip = $context->env->ip();

        // Последовательно, как у тебя (break на первой ошибке)
        // 1) email banned
        if ($email !== '' && $this->isEmailBanned($email)) {
            return $result->addError(
                'sell',
                __('Ваш email :email заблокирован. Пожалуйста, используйте другой.', ['email' => $email]),
                'email_banned'
            );
        }

        // 2) duplicate amount_from (уникальная сумма)
        if ($this->isDuplicateAmountFrom($context, $direction, $authId)) {
            return $result->addError(
                'amount_from',
                __('Заявка с суммой :amount уже существует для выбранного направления обмена. Пожалуйста, немного измените сумму и повторите попытку.', [
                    'amount' => $this->formatIncomeAmountForCompare($context, $direction),
                ]),
                'duplicate_amount_from'
            );
        }

        // 3) first orders limit
        if ($this->isExceededFirstOrdersLimit($context, $direction, $authId)) {
            return $result->addError(
                'amount_from',
                __('Для первых заявок установлено ограничение по сумме. Пожалуйста, уменьшите сумму или выполните несколько успешных обменов.'),
                'first_orders_limit'
            );
        }

        // 4) hour/day limits from profiles
        if ($this->isExceededUserOrderLimit($direction, $authId, 'hour')) {
            return $result->addError('limit_order', __('Вы превысили часовой лимит создания заявок. Свяжитесь с оператором.'), 'limit_user_hour');
        }
        if ($this->isExceededUserOrderLimit($direction, $authId, 'day')) {
            return $result->addError('limit_order', __('Вы превысили дневной лимит создания заявок. Свяжитесь с оператором.'), 'limit_user_day');
        }

        // 5) rolling limit
        if ($this->isExceededRollingUserOrderLimit($direction, $authId)) {
            $limits = $this->getOrderLimits($context, $direction);
            return $result->addError(
                'limit_order',
                __('Вы слишком часто создаёте заявки. Пожалуйста, подождите :minutes минут перед следующей попыткой.', [
                    'minutes' => (int)($limits['order_limit_minutes'] ?? 0),
                ]),
                'limit_user_rolling'
            );
        }

        // 6) too fast between orders
        if ($this->isTooFastBetweenOrders($direction, $authId)) {
            return $result->addError(
                'limit_order',
                __('Вы создаёте заявки слишком быстро. Пожалуйста, подождите несколько секунд и повторите попытку.'),
                'limit_min_interval'
            );
        }

        // 7) verification failures
        if ($this->hasExceededVerificationFailures($context, $direction, $email)) {
            return $result->addError(
                'limit_order',
                __('Вам временно ограничен доступ к данному направлению из-за неудачных попыток верификации.'),
                'verification_failures_exceeded'
            );
        }

        // 8) spam orders detected (без auto-ban в правиле)
        if ($this->isSpamOrdersDetected($authId)) {
            return $result->addError(
                'limit_order',
                __('Вы временно заблокированы за частое создание заявок. Обратитесь к оператору.'),
                'spam_orders_detected'
            );
        }

        // 9) requires verified account
        if ($this->requiresVerifiedAccount($context, $direction, $authId)) {
            return $result->addError(
                'limit_order',
                __('Создание заявки доступно только верифицированным пользователям.'),
                'verified_account_required'
            );
        }

        // 10) newbie limit (FIX: сравниваем income_amount, а не income_account)
        if ($this->isExceededNewbieLimit($context, $direction, $authId, $email, $ip)) {
            return $result->addError(
                'limit_order',
                __('Новичкам разрешён обмен не более :amount :currency.', [
                    'amount' => (string)($direction->max_amount_newbie ?? 0),
                    'currency' => (string)($direction->currency1?->code_currency?->name ?? ''),
                ]),
                'newbie_amount_limit'
            );
        }

        // 11) user restrictions (ban/is_order)
        if ($this->hasUserRestrictions($context, $authId)) {
            return $result->addError(
                'email',
                $this->getUserRestrictionMessage($context, $authId),
                'user_restricted'
            );
        }

        return $result;
    }

    // ------------------------ helpers ------------------------

    private function isEmailBanned(string $email): bool
    {
        return Banned::query()
            ->where('filter_name', Str::lower($email))
            ->where('expired_at', '>=', Carbon::now())
            ->exists();
    }

    private function isDuplicateAmountFrom(ValidationContext $context, DirectionExchange $direction, int $authId): bool
    {
        if ($authId <= 0) {
            return false;
        }

        if (!(bool)($direction->is_unique_amount_from ?? false)) {
            return false;
        }

        $directionId = (int)$direction->id;
        $givePrice = $this->formatIncomeAmountForCompare($context, $direction); // строка как в БД
        $statuses = [3, 7];
        $fromTime = Carbon::now()->subHours(3);

        return Task::query()
            ->where('id_user', $authId)
            ->where('id_direction_exchange', $directionId)
            ->where('give_price', $givePrice)
            ->whereIn('status', $statuses)
            ->where('created_at', '>=', $fromTime)
            ->exists();
    }

    /**
     * Формат суммы "Отдаю" так, как она хранится/сравнивается в Task.give_price.
     * (В старом коде сравнение делалось по getFormattedIncomeAmount()).
     */
    private function formatIncomeAmountForCompare(ValidationContext $context, DirectionExchange $direction): string
    {
        $raw = $context->data->getString('income_amount') ?? '0';
        $scale = is_numeric($direction->currency1?->number_format) ? (int)$direction->currency1->number_format : 18;

        // Если у тебя iex_number_format делает нужный формат — используем его.
        return iex_number_format($raw, $scale);
    }

    private function isExceededFirstOrdersLimit(ValidationContext $context, DirectionExchange $direction, int $authId): bool
    {
        if ($authId <= 0) {
            return false;
        }

        $limits = $this->getOrderLimits($context, $direction);

        $windowCount = (int)($limits['first_orders_window_count'] ?? 0);
        $maxAmountRaw = $limits['first_orders_max_amount'] ?? null;

        if ($windowCount <= 0 || $maxAmountRaw === null || $maxAmountRaw === '') {
            return false;
        }

        $completedCount = Task::query()
            ->where('id_user', $authId)
            ->whereIn('status', [5, 6])
            ->count();

        if ($completedCount >= $windowCount) {
            return false;
        }

        $income = $this->readMoney($context->data->getString('income_amount'));
        $max = $this->readMoney((string)$maxAmountRaw);

        if ($income === null || $max === null) {
            return false;
        }

        if ($max->isLessThanOrEqualTo('0')) {
            return false;
        }

        return $income->isGreaterThan($max);
    }

    private function getOrderLimits(ValidationContext $context, DirectionExchange $direction): array
    {
        if ($this->orderLimits !== null) {
            return $this->orderLimits;
        }

        $user = null;
        if ($context->resources->has(User::class)) {
            /** @var User $u */
            $u = $context->resources->get(User::class);
            $user = $u;
        }

        /** @var OrderLimitResolver $resolver */
        $resolver = app(OrderLimitResolver::class);

        $this->orderLimits = $resolver->resolve($user, $direction);

        return $this->orderLimits;
    }

    private function isExceededUserOrderLimit(DirectionExchange $direction, int $authId, string $period): bool
    {
        if ($authId <= 0) {
            return false;
        }

        $limits = $this->orderLimits ?? [];

        $key = match ($period) {
            'hour' => 'max_num_order_user_hour',
            'day'  => 'max_num_order_user_day',
            default => null,
        };

        if ($key === null) {
            return false;
        }

        $limit = (int)($limits[$key] ?? 0);
        if ($limit === 0) {
            return false;
        }

        $fromTime = match ($period) {
            'hour' => Carbon::now()->subHour(),
            'day'  => Carbon::now()->subDay(),
        };

        $count = Task::query()
            ->where('id_user', $authId)
            ->whereIn('status', [3, 4])
            ->where('created_at', '>=', $fromTime)
            ->count();

        return $count >= $limit;
    }

    private function isExceededRollingUserOrderLimit(DirectionExchange $direction, int $authId): bool
    {
        if ($authId <= 0) {
            return false;
        }

        $limits = $this->orderLimits ?? [];

        $limitCount = (int)($limits['order_limit_count'] ?? 0);
        $limitMinutes = (int)($limits['order_limit_minutes'] ?? 0);

        if ($limitCount === 0 || $limitMinutes === 0) {
            return false;
        }

        $fromTime = Carbon::now()->subMinutes($limitMinutes);

        $count = Task::query()
            ->where('id_user', $authId)
            ->whereIn('status', [3, 4])
            ->where('created_at', '>=', $fromTime)
            ->count();

        return $count >= $limitCount;
    }

    private function isTooFastBetweenOrders(DirectionExchange $direction, int $authId): bool
    {
        if ($authId <= 0) {
            return false;
        }

        $limits = $this->orderLimits ?? [];
        $minInterval = (int)($limits['min_interval_between_orders_seconds'] ?? 0);

        if ($minInterval <= 0) {
            return false;
        }

        $lastTask = Task::query()
            ->where('id_user', $authId)
            ->whereIn('status', [3, 4])
            ->orderByDesc('created_at')
            ->first();

        if (!$lastTask) {
            return false;
        }

        $diff = Carbon::now()->diffInSeconds($lastTask->created_at);

        return $diff < $minInterval;
    }

    private function hasExceededVerificationFailures(ValidationContext $context, DirectionExchange $direction, string $email): bool
    {
        $limit = (int)iEXSetting('num_count_failed_verification', 0);
        if ($limit === 0) {
            return false;
        }

        $currencyIn = $direction->currency1;
        if (!$currencyIn) {
            return false;
        }

        // Базовый режим с уровня валюты
        $mode = (int)($currencyIn->is_enabled_verification ?? 0);

        // Учет направления
        if (!empty($direction->card_verification_type)) {
            $cardType = (int)($direction->card_verification_type ?? 0);
            $rules = is_array($direction->card_verification_rules ?? null) ? $direction->card_verification_rules : [];
            $dirCfg = is_array($rules['direction'] ?? null) ? $rules['direction'] : [];

            if ($cardType === 1 && array_key_exists('mode', $dirCfg)) {
                $mode = (int)$dirCfg['mode'];
            }
        }

        if ($mode === 0) {
            return false;
        }

        if ($email === '') {
            return false;
        }

        $failures = VerificationCard::query()
            ->where([
                'email' => $email,
                'id_currency' => (int)$currencyIn->id,
                'status' => 3,
            ])
            ->count();

        return $failures >= $limit;
    }

    private function isSpamOrdersDetected(int $authId): bool
    {
        if ($authId <= 0) {
            return false;
        }

        if ((int)iEXSetting('is_blocked_spam_order', 0) === 0) {
            return false;
        }

        return Task::query()
                ->where('id_user', $authId)
                ->whereIn('status', [2, 5, 6])
                ->where('created_at', '>=', Carbon::now()->subMinutes(10))
                ->count() >= 5;
    }

    private function requiresVerifiedAccount(ValidationContext $context, DirectionExchange $direction, int $authId): bool
    {
        if ((int)($direction->is_verified_account ?? 0) === 0) {
            return false;
        }

        if ($authId <= 0) {
            return true;
        }

        // если User есть в resources — не делаем лишний запрос
        $user = $context->resources->has(User::class)
            ? $context->resources->get(User::class)
            : User::find($authId);

        if (!$user) {
            return true;
        }

        return (int)($user->is_verify_account ?? 0) === 0;
    }

    private function isExceededNewbieLimit(ValidationContext $context, DirectionExchange $direction, int $authId, string $email, string $ip): bool
    {
        $maxAmount = $this->readMoney((string)($direction->max_amount_newbie ?? '0'));
        if ($maxAmount === null || $maxAmount->isLessThanOrEqualTo('0')) {
            return false;
        }

        if ($this->isUserNotNewbie($context, $authId, $email, $ip)) {
            return false;
        }

        // FIX: берём income_amount
        $income = $this->readMoney($context->data->getString('income_amount'));
        if ($income === null) {
            return false;
        }

        return $income->isGreaterThan($maxAmount);
    }

    private function hasUserRestrictions(ValidationContext $context, int $authId): bool
    {
        $user = $this->resolveUser($context, $authId);
        if (!$user) {
            return false;
        }

        // is_order или isBanned()
        $isOrderBlocked = (bool)($user->is_order ?? false);
        $isBanned = method_exists($user, 'isBanned') ? (bool)$user->isBanned() : false;

        return $isOrderBlocked || $isBanned;
    }

    private function getUserRestrictionMessage(ValidationContext $context, int $authId): string
    {
        $user = $this->resolveUser($context, $authId);
        if (!$user) {
            return '';
        }

        $isBanned = method_exists($user, 'isBanned') ? (bool)$user->isBanned() : false;

        if ($isBanned) {
            return __('Ваша учётная запись заблокирована. Создание новых заявок невозможно.');
        }

        if ((bool)($user->is_order ?? false)) {
            return __('Вам запрещено создание новых заявок. Пожалуйста, обратитесь в службу поддержки.');
        }

        return '';
    }

    private function isUserNotNewbie(ValidationContext $context, int $authId, string $email, string $ip): bool
    {
        $user = $this->resolveUser($context, $authId);

        // Нет пользователя или заблокирован — считаем новичком
        if (!$user) {
            return false;
        }

        $isBanned = method_exists($user, 'isBanned') ? (bool)$user->isBanned() : false;
        if ($isBanned) {
            return false;
        }

        // 1) >= 3 успешных по счётчику
        if ((int)($user->order_num ?? 0) >= 3) {
            return true;
        }

        // 2) Аккаунт старше 48 часов
        if (isset($user->created_at) && $user->created_at instanceof \Carbon\CarbonInterface) {
            if ($user->created_at->lessThanOrEqualTo(Carbon::now()->subHours(48))) {
                return true;
            }
        }

        // 3) Уже были заявки с текущими реквизитами (как у тебя)
        $conditions = [];

        if ($email !== '') $conditions[] = ['email', $email];
        if ($ip !== '') $conditions[] = ['ip', $ip];

        $fromShot = $this->compactAccount($context->data->getString('income_account'));
        $toShot = $this->compactAccount($context->data->getString('outcome_account'));

        if ($fromShot) $conditions[] = ['from_shot', $fromShot];
        if ($toShot) $conditions[] = ['to_shot', $toShot];

        if ($conditions === []) {
            return false;
        }

        $hasFamiliar = Task::query()
            ->where('status', 3)
            ->where(function ($q) use ($conditions) {
                foreach ($conditions as [$field, $value]) {
                    $q->orWhere($field, $value);
                }
            })
            ->exists();

        return $hasFamiliar;
    }

    private function resolveUser(ValidationContext $context, int $authId): ?User
    {
        if ($context->resources->has(User::class)) {
            /** @var User $u */
            $u = $context->resources->get(User::class);
            return $u;
        }

        if ($authId <= 0) {
            return null;
        }

        return User::find($authId);
    }

    private function compactAccount(?string $raw): ?string
    {
        if ($raw === null) return null;
        $s = trim((string)$raw);
        if ($s === '') return null;
        $s = preg_replace('/\s+/', '', $s) ?? $s;
        $s = trim($s);
        return $s !== '' ? $s : null;
    }

    private function readMoney(?string $raw, int $scale = 18): ?BigDecimal
    {
        if ($raw === null) return null;

        $s = trim($raw);
        if ($s === '') return null;

        $s = str_replace(["\u{00A0}", ' '], '', $s);
        $s = str_replace(',', '.', $s);
        $s = preg_replace('/\.$/', '', $s) ?? $s;

        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $s)) {
            return null;
        }

        try {
            return BigDecimal::of($s)->toScale($scale, RoundingMode::DOWN);
        } catch (\Throwable $e) {
            Log::warning('UserRule: cannot parse money', ['raw' => $raw, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
