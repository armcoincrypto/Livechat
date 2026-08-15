<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\DirectionExchange;
use App\Models\Task;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use App\Services\Reserves\ReserveLinkResolver;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Result\ValidationResult;

/**
 * ReserveLimitRule — проверка резервов и лимитов резерва направления.
 *
 * Перенос ReserveLimitValidator в новую систему:
 * - без request()
 * - результат через ValidationResult
 * - суммы сравниваем через BigDecimal (без float)
 */
final class ReserveLimitRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        // 1) max_limit_in_reserve для currency1
        if ($this->exceedsCurrencyMaxLimit($direction)) {
            $value = (string)($direction->currency1?->max_limit_in_reserve ?? 0);
            $code  = (string)($direction->currency1?->code_currency?->name ?? '');

            $result->addError(
                'sell',
                __('В данный момент сумма обмена превышает допустимый резерв валюты. Максимально доступно для обмена: :value :code.', [
                    'value' => $value,
                    'code' => $code,
                ]),
                'reserve_currency1_max_limit_exceeded'
            );
        }

        // 2) reserve_* лимиты направления (max/day/month)
        $reserveChecks = [
            'reserve_max_limit'   => __('В данный момент превышен общий лимит доступного резерва. Пожалуйста, повторите попытку позже.'),
            'reserve_limit_day'   => __('На сегодня превышен лимит доступного резерва. Попробуйте снова завтра.'),
            'reserve_limit_month' => __('Превышен месячный лимит доступного резерва. Создание заявки будет доступно в следующем месяце.'),
        ];

        foreach ($reserveChecks as $type => $message) {
            if (!$this->checkReserveLimit($context, $direction, $type)) {
                $result->addError('sell', $message, 'reserve_limit_exceeded');
                break; // как в старом коде
            }
        }

        // 3) недостаточный резерв currency2 для выдачи outcome_amount
        if ($this->isReserveInsufficient($context, $direction)) {
            $result->addError(
                'buy',
                __('К сожалению, текущий резерв валюты недостаточен для проведения данного обмена. Пожалуйста, попробуйте позже или уменьшите сумму операции.'),
                'reserve_insufficient'
            );
        }

        return $result;
    }

    /**
     * Проверка reserve_max_limit / reserve_limit_day / reserve_limit_month.
     */
    private function checkReserveLimit(ValidationContext $context, DirectionExchange $direction, string $limitType): bool
    {
        $limitValueRaw = $direction->{$limitType} ?? null;

        $limit = $this->readMoney(is_null($limitValueRaw) ? null : (string)$limitValueRaw);
        if ($limit === null || $limit->isLessThanOrEqualTo('0')) {
            return true;
        }

        $currencyOut = $direction->currency2;
        if (!$currencyOut) {
            return true;
        }

        $query = Task::query()
            ->where('status', 4)
            ->whereHas('direction_exchange', function ($q) use ($currencyOut) {
                $q->where('id_currency2', (int)$currencyOut->id);
            });

        if ($limitType === 'reserve_limit_day') {
            $query->whereDate('updated_at', Carbon::today());
        } elseif ($limitType === 'reserve_limit_month') {
            $query->whereBetween('updated_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
        }

        // sum может быть float/строка — приводим к BigDecimal
        $sumRaw = $query->sum('receiving_price');
        $sum = $this->readMoney((string)$sumRaw) ?? BigDecimal::zero();

        $currentOutcome = $this->readMoney($context->data->getString('outcome_amount')) ?? BigDecimal::zero();

        // Старый код: ($sum + outcome_amount) < limitValue
        return $sum->plus($currentOutcome)->isLessThan($limit);
    }

    /**
     * Проверка max_limit_in_reserve у currency1.
     *
     * Старый код сравнивал текущий резерв (direction_reserve или общий reserve валюты)
     * с max_limit_in_reserve. Оставляем поведение 1:1.
     */
    private function exceedsCurrencyMaxLimit(DirectionExchange $direction): bool
    {
        $currency = $direction->currency1;
        if (!$currency) {
            return false;
        }

        $maxLimit = $this->readMoney((string)($currency->max_limit_in_reserve ?? '0')) ?? BigDecimal::zero();
        if ($maxLimit->isLessThanOrEqualTo('0')) {
            return false;
        }

        $currentReserveRaw = null;

        if ((int)($direction->type_reserve ?? 0) === 1) {
            $currentReserveRaw = (string)($direction->direction_reserve ?? '0');
        } else {
            $reserve = $currency->reserve;

            if (!$reserve) {
                $currentReserveRaw = '0';
            } else {
                /** @var ReserveLinkResolver $resolver */
                $resolver = app(ReserveLinkResolver::class);
                $currentReserveRaw = $resolver->getEffectiveSumma($reserve, 18);
            }
        }

        $currentReserve = $this->readMoney($currentReserveRaw) ?? BigDecimal::zero();

        // В старом коде: currentReserve > max_limit_in_reserve
        return $currentReserve->isGreaterThan($maxLimit);
    }

    /**
     * Проверка: хватает ли резерва currency2 для выдачи outcome_amount.
     */
    private function isReserveInsufficient(ValidationContext $context, DirectionExchange $direction): bool
    {
        $outcomeAmount = $this->readMoney($context->data->getString('outcome_amount')) ?? BigDecimal::zero();

        $reserveRaw = null;
        if ((int)($direction->type_reserve ?? 0) === 1) {
            $reserveRaw = (string)($direction->direction_reserve ?? '0');
        } else {
            $reserveModel = $direction->currency2?->reserve;

            if (!$reserveModel) {
                $reserveRaw = '0';
            } else {
                /** @var ReserveLinkResolver $resolver */
                $resolver = app(ReserveLinkResolver::class);
                $reserveRaw = $resolver->getEffectiveSumma($reserveModel, 18);
            }
        }

        $reserve = $this->readMoney($reserveRaw) ?? BigDecimal::zero();

        return $reserve->isLessThan($outcomeAmount);
    }

    /**
     * Парсер денег в BigDecimal (без float).
     */
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
        } catch (\Throwable) {
            return null;
        }
    }
}
