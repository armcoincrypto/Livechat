<?php

namespace iEXPackages\Order;

use App\Models\DirectionExchange;
use App\Models\PromoCode;
use iEXPackages\Calculator\CalculatorFacade;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Log;

class OrderCalculator
{
    /**
     * Получаем информацию о направлении обмена
     */
    protected DirectionExchange $directionExchange;

    protected BigDecimal $fromAmount;
    protected BigDecimal $toAmount;

    protected array $options = [];

    protected float $promoCodePercent = 0;

    protected int $promoCodeId = 0;

    protected BigDecimal $userDiscount;

    protected int $defaultScale = 18;

    /**
     * Объявляем класс для расчета комиссий
     */
    public function __construct(DirectionExchange $directionExchange)
    {
        $this->defaultScale = (int)iEXSetting('max_decimal_places', 18);
        //$this->order = $order;
        $this->directionExchange = $directionExchange;
        $this->fromAmount = BigDecimal::zero();
        $this->toAmount = BigDecimal::zero();
        $this->userDiscount = BigDecimal::zero();
    }

    public function withOptions(array $options = []): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Установить сумму Отдаю
     *
     * @return OrderCalculator
     */
    public function setFromAmount(float|string $amount): static
    {
        $this->fromAmount = BigDecimal::of($amount);
        return $this;
    }


    /**
     * Получить сумму Отдаю
     */

    public function getFromAmount(): float
    {
        return $this->fromAmount->toFloat();
    }

    /**
     * Установить сумму Получаю
     */
    public function setToAmount(float|string $amount): float
    {
        $this->toAmount = BigDecimal::of($amount);
        return $this->toAmount->toFloat();
    }

    /**
     * Получить сумму Получаю
     */
    public function getToAmount(): float|int
    {
        return $this->toAmount;
    }

    public function run(): array
    {
        $calculator = CalculatorFacade::setDirectionExchange($this->directionExchange)
            ->calculateWithOptions([
                'exchange_city_id' => (int) ($this->options['city_id'] ?? 0),
                'amount' => $this->fromAmount->toFloat(),
                'type_rate' => $this->options['type_rate'] ?? null,
                'selected_fee_type' => $this->options['selected_fee_type'] ?? null,
                'selected_fees' => (isset($this->options['selected_fees']) && is_array($this->options['selected_fees']))
                    ? $this->options['selected_fees']
                    : [],
                'card_verification_required' => (bool) ($this->options['card_verification_required'] ?? false),
                'checkbox_fees' => $this->options['checkbox_fees'] ?? [],
            ]);

        // Комиссии "Отдаю"
        [$fromOtherFee, $fromPayFee] = $this->fromFee();

        $currentRate = BigDecimal::of($calculator->getRateValue());
        $toAmount = $this->fromAmount->multipliedBy($currentRate);

        // PROMO: usePromoCode() возвращает BONUS в percent_value
        $promoCodeResult = [];
        $promoBonus = BigDecimal::zero();

        if (!empty($this->options['promo_code'])) {
            $promoCodeResult = $this->usePromoCode((string) $this->options['promo_code'], $toAmount);

            if (isset($promoCodeResult['percent_value'])) {
                $promoBonus = BigDecimal::of((string) $promoCodeResult['percent_value']);

                if ($promoBonus->isGreaterThan(0)) {
                    $toAmount = $toAmount->plus($promoBonus);
                } else {
                    $promoBonus = BigDecimal::zero();
                }
            }
        }

        // Персональная скидка (как бонус)
        $personalDiscount = $this->usePersonalDiscount($toAmount);
        $toAmount = $toAmount->plus($personalDiscount);

        // Комиссии "Получаю"
        [$toOtherFee, $toPayFee] = $this->toFee($toAmount);

        $promoApplied = !empty($promoCodeResult) && ($promoBonus->isGreaterThan(0)) && ((int) ($promoCodeResult['id'] ?? 0) > 0);

        return [
            'course_display' => $calculator->getFullRate(),
            'course_float' => $this->toHumanDecimal($currentRate, $this->defaultScale),

            // Отдаю
            'give_price' => $this->toHumanDecimal($this->fromAmount->plus($fromOtherFee), $this->defaultScale),
            'give_price_default' => $this->toHumanDecimal($this->fromAmount, $this->defaultScale),
            'give_price_with_comm' => $this->toHumanDecimal($this->fromAmount->plus($fromOtherFee), $this->defaultScale),
            'give_price_with_comm_pay' => $this->toHumanDecimal($this->fromAmount->plus($fromOtherFee)->plus($fromPayFee), $this->defaultScale),
            'give_price_fee_comm' => $this->toHumanDecimal($fromOtherFee, $this->defaultScale),
            'give_price_fee_pay' => $this->toHumanDecimal($fromPayFee, $this->defaultScale),

            // Получаю
            'receiving_price' => $this->toHumanDecimal($toAmount->minus($toOtherFee)->minus($toPayFee), $this->defaultScale),
            'receiving_price_default' => $this->toHumanDecimal($toAmount->minus($personalDiscount), $this->defaultScale),
            'receiving_price_with_comm' => $this->toHumanDecimal($toAmount->minus($toOtherFee), $this->defaultScale),
            'receiving_price_with_comm_pay' => $this->toHumanDecimal($toAmount->minus($toOtherFee)->minus($toPayFee), $this->defaultScale),
            'receiving_price_fee_comm' => $this->toHumanDecimal($toOtherFee, $this->defaultScale),
            'receiving_price_fee_pay' => $this->toHumanDecimal($toPayFee, $this->defaultScale),

            // Персональная скидка
            'receiving_price_with_user_discount' => $this->toHumanDecimal($toAmount->minus($toOtherFee), $this->defaultScale),
            'receiving_price_user_discount' => $this->toHumanDecimal($personalDiscount, $this->defaultScale),
            'user_discount' => $this->toHumanDecimal($this->userDiscount, $this->defaultScale),

            // ПРОМОКОД (строго по полям БД)
            // receiving_price_with_promocode — BONUS (прибавка к "Получаю")
            'receiving_price_with_promocode' => $this->toHumanDecimal($promoBonus, $this->defaultScale),

            // snapshot
            'id_promo_code' => $promoApplied ? (int) ($promoCodeResult['id'] ?? 0) : 0,
            'promo_code_code' => $promoApplied ? (string) ($this->options['promo_code'] ?? '') : null,
            'promo_code_discount_type' => $promoApplied ? ($promoCodeResult['discount_type'] ?? null) : null, // percent|fixed
            'promo_code_value' => $promoApplied && isset($promoCodeResult['discount_value'])
                ? (string) $promoCodeResult['discount_value']
                : null
        ];
    }

    /**
     * Комиссии "Отдаю" (Доп. И для ПС)
     */
    private function fromFee(): array
    {
        $from_other_fee = $this->feeResponse(
            $this->fromAmount,
            (float) $this->directionExchange->oth_comm_percent,
            (float) $this->directionExchange->oth_comm_currency,
            (float) $this->directionExchange->oth_min_comm
        );
        $from_pay_fee = $this->feeResponse(
            $this->fromAmount->plus($from_other_fee),
            (float) $this->directionExchange->pay_comm_percent,
            (float) $this->directionExchange->pay_comm_currency,
            (float) $this->directionExchange->pay_min_comm
        );
        return [$from_other_fee, $from_pay_fee];
    }


    /**
     * Комиссии "Получаю" (Доп. И для ПС)
     */
    private function toFee(BigDecimal $amount): array
    {
        $to_other_fee = $this->feeResponse(
            $amount,
            (float) $this->directionExchange->oth_comm2_percent,
            (float) $this->directionExchange->oth_comm2_currency,
            (float) $this->directionExchange->oth_min2_comm
        );
        $to_pay_fee = $this->feeResponse(
            $amount->minus($to_other_fee),
            (float) $this->directionExchange->pay_comm2_percent,
            (float) $this->directionExchange->pay_comm2_currency,
            (float) $this->directionExchange->pay_min2_comm
        );
        return [$to_other_fee, $to_pay_fee];
    }

    /**
     * Использование Промо-Кода
     */
    private function usePromoCode(string $promo_code, BigDecimal $amount): array
    {
        $promo_code = trim($promo_code);

        // строгая валидация
        if ($promo_code === '' || !preg_match('/^[A-Za-z0-9]+$/', $promo_code)) {
            return [];
        }

        $now = now();

        /** @var PromoCode|null $promoCode */
        $promoCode = PromoCode::query()
            ->where('code', $promo_code)
            ->where('status', 1)
            ->where(function ($q) use ($now) {
                $q->whereNull('started_at')->orWhere('started_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expired_at')->orWhere('expired_at', '>=', $now);
            })
            ->first();

        if (!$promoCode) {
            return [];
        }

        // Применимость к направлению (через relations PromoCode::directionsIncluded/Excluded)
        $directionId = (int) $this->directionExchange->id;
        $scopeMode = (string) ($promoCode->scope_mode ?? 'all');

        if ($scopeMode === 'include') {
            if (!$promoCode->directionsIncluded()->whereKey($directionId)->exists()) {
                return [];
            }
        } else {
            if ($promoCode->directionsExcluded()->whereKey($directionId)->exists()) {
                return [];
            }
        }

        // Рассчитываем бонус
        $discountType = (string) ($promoCode->discount_type ?? 'percent'); // percent|fixed
        $discountValue = BigDecimal::of((string) ($promoCode->discount_value ?? '0'));

        if ($discountValue->isLessThanOrEqualTo(0)) {
            return [];
        }

        $promoBonus = $discountType === 'fixed'
            ? $discountValue
            : $amount
                ->multipliedBy($discountValue)
                ->dividedBy(100, $this->defaultScale, RoundingMode::HALF_UP);

        if ($promoBonus->isLessThanOrEqualTo(0)) {
            return [];
        }

        // Атомарно учитываем использование (защита от гонок)
        $consumed = false;

        \DB::transaction(function () use ($promoCode, &$consumed) {
            // NULL = безлимит
            if ($promoCode->count_uses === null) {
                PromoCode::query()->whereKey((int) $promoCode->id)->increment('used');
                $consumed = true;
                return;
            }

            // Лимитный: инкремент только если used < count_uses
            $affected = PromoCode::query()
                ->whereKey((int) $promoCode->id)
                ->where('status', 1)
                ->whereColumn('used', '<', 'count_uses')
                ->increment('used');

            if ($affected > 0) {
                $consumed = true;

                // если лимит исчерпан — закрываем
                PromoCode::query()
                    ->whereKey((int) $promoCode->id)
                    ->where('status', 1)
                    ->whereColumn('used', '>=', 'count_uses')
                    ->update(['status' => 2]);
            }
        });

        if (!$consumed) {
            return [];
        }

        return [
            'id' => (int) $promoCode->id,
            'percent_value' => $promoBonus->toFloat(),
            'discount_type' => $discountType,
            'discount_value' => $discountValue->toFloat()
        ];
    }

    /**
     * Персональная скидка (используется как бонус)
     */
    private function usePersonalDiscount(BigDecimal $amount): BigDecimal
    {
        // Проверка: авторизация, включена ли система скидок и не отключено ли это глобально
        if (!auth()->check() ||
            $this->directionExchange->is_enable_user_discount != 0 ||
            (int)iEXSetting('is_discount_disabled') !== 0
        ) {
            return BigDecimal::zero();
        }

        // Определяем значение скидки: сначала personal_discount, если его нет — берем из rewardProgram
        $discountPercent = auth()->user()->personal_discount;

        if ($discountPercent === null || $discountPercent === '') {
            $discountPercent = optional(auth()->user()->rewardProgram)->percent ?? 0;
        }

        $this->userDiscount = BigDecimal::of($discountPercent);

        // Если скидка больше 0 — считаем бонус от суммы до округления
        if ($this->userDiscount->isGreaterThan(0)) {
            return $amount
                ->multipliedBy($this->userDiscount)
                ->dividedBy(100, $this->defaultScale, RoundingMode::HALF_UP);
        }

        return BigDecimal::zero();
    }



    /**
     * Комиссии для Отдаю
     */
    private function feeResponse(BigDecimal $amount, float|string|null $percent, float|string|null $currency, float|string|null $min_limit): BigDecimal
    {
        $feePercent = BigDecimal::of(is_numeric($percent) && $percent !== '' ? $percent : 0);
        $feeCurrency = BigDecimal::of(is_numeric($currency) && $currency !== '' ? $currency : 0);
        $minLimit = BigDecimal::of(is_numeric($min_limit) && $min_limit !== '' ? $min_limit : 0);

        $feeFromPercent = $amount
            ->multipliedBy($feePercent)
            ->dividedBy(100, $this->defaultScale, RoundingMode::HALF_UP);

        $totalFee = $feeFromPercent->plus($feeCurrency);

        return $totalFee->isLessThan($minLimit) ? $minLimit : $totalFee;
    }

    public function getUserDiscount(): float
    {
        return $this->userDiscount->toFloat();
    }

    function toHumanDecimal(BigDecimal $decimal, int $scale = 18): string {
        return rtrim(rtrim($decimal->toScale($scale, RoundingMode::HALF_UP)->__toString(), '0'), '.');
    }
}
