<?php

namespace iEXPackages\Transaction;

use App\Models\DirectionExchange;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

class FeeCalculator
{
    protected DirectionExchange $directionExchange;
    protected BigDecimal $fromAmount;
    protected BigDecimal $toAmount;
    protected BigDecimal $userDiscount;
    protected array $options = [];
    protected User $user;

    protected int $scale = 18;

    /**
     * Объявляем класс для расчета комиссий
     */
    public function __construct(DirectionExchange $directionExchange)
    {
        $this->scale = (int)iEXSetting('max_decimal_places', 18);
        $this->directionExchange = $directionExchange;
        $this->fromAmount = BigDecimal::zero();
        $this->toAmount = BigDecimal::zero();
        $this->userDiscount = BigDecimal::zero();
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function withOptions(array $options = []): static
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Установить сумму Отдаю
     *
     * @return \iEXPackages\Order\OrderCalculator
     * @throws MathException
     */
    public function setFromAmount(float $amount): static
    {
        $this->fromAmount = BigDecimal::of($amount);
        return $this;
    }

    public function getFromAmount(): string
    {
        return $this->fromAmount->toScale($this->scale)->__toString();
    }

    public function setToAmount(float $amount): string
    {
        $this->toAmount = BigDecimal::of($amount);
        return $this->toAmount->toScale($this->scale)->__toString();
    }

    public function getToAmount(): string
    {
        return $this->toAmount->toScale($this->scale)->__toString();
    }

    /**
     * Начинаем расчеты
     */
    public function run(): array
    {
        [$from_other_fee, $from_pay_fee] = $this->fromFee();
        $this->fromAmount = $this->fromAmount->minus($from_other_fee);


        $currentRate = BigDecimal::of($this->options['amount']);
        $toAmount = $this->fromAmount->multipliedBy($currentRate);

        $promoBonus = BigDecimal::zero();

        if (!empty($this->options['promo_code'])) {
            $promoOpt = $this->options['promo_code'];

            if (is_array($promoOpt)) {
                $promoType = (string) ($promoOpt['type'] ?? '');
                $promoValue = $promoOpt['value'] ?? null;

                if ($promoType !== '' && $promoValue !== null) {
                    $discountValue = BigDecimal::of((string) $promoValue);

                    if ($discountValue->isGreaterThan(0)) {
                        if ($promoType === 'fixed') {
                            $promoBonus = $discountValue;
                        } else {
                            // percent
                            $promoBonus = $toAmount
                                ->multipliedBy($discountValue)
                                ->dividedBy(100, $this->scale, RoundingMode::HALF_UP);
                        }
                    }
                }
            }

            if ($promoBonus->isGreaterThan(0)) {
                $toAmount = $toAmount->plus($promoBonus);
            } else {
                $promoBonus = BigDecimal::zero();
            }
        }

        // Персональная скидка
        $personalDiscount = $this->usePersonalDiscount($toAmount);
        $toAmount = $toAmount->plus($personalDiscount);

        [$to_other_fee, $to_pay_fee] = $this->toFee($toAmount);

        return [
            'course_display' => $this->options['curs'],
            'course_float' => $this->formatDecimal($currentRate),

            'give_price' => $this->formatDecimal($this->fromAmount->plus($from_other_fee)),
            'give_price_default' => $this->formatDecimal($this->fromAmount),
            'give_price_with_comm' => $this->formatDecimal($this->fromAmount->plus($from_other_fee)),
            'give_price_with_comm_pay' => $this->formatDecimal($this->fromAmount->plus($from_other_fee)->plus($from_pay_fee)),
            'give_price_fee_comm' => $this->formatDecimal($from_other_fee),
            'give_price_fee_pay' => $this->formatDecimal($from_pay_fee),

            'receiving_price' => $this->formatDecimal($toAmount->minus($to_other_fee)->minus($to_pay_fee)),
            'receiving_price_default' => $this->formatDecimal($toAmount->minus($personalDiscount)),
            'receiving_price_with_comm' => $this->formatDecimal($toAmount->minus($to_other_fee)),
            'receiving_price_with_comm_pay' => $this->formatDecimal($toAmount->minus($to_other_fee)->minus($to_pay_fee)),
            'receiving_price_fee_comm' => $this->formatDecimal($to_other_fee),
            'receiving_price_fee_pay' => $this->formatDecimal($to_pay_fee),

            'receiving_price_with_user_discount' => $this->formatDecimal($toAmount->minus($to_other_fee)),
            'receiving_price_user_discount' => $this->formatDecimal($personalDiscount),
            'user_discount' => $this->formatDecimal($this->userDiscount),
            'receiving_price_with_promocode' => $this->formatDecimal($promoBonus)
        ];
    }

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

    private function feeResponse(BigDecimal $amount, float $percent, float $currency, float $min_limit): BigDecimal
    {
        $percentPart = $amount->multipliedBy($percent)->dividedBy(100, $this->scale, RoundingMode::HALF_UP);
        $fee = $percentPart->plus($currency);
        $min = BigDecimal::of($min_limit);
        return $fee->isLessThan($min) ? $min : $fee;
    }

    private function usePersonalDiscount(BigDecimal $amount): BigDecimal
    {
        if ($this->directionExchange->is_enable_user_discount != 0 || (int)iEXSetting('is_discount_disabled') !== 0 || $this->user->is_guest) {
            return BigDecimal::zero();
        }

        $percent = $this->user->personal_discount ?: optional($this->user->rewardProgram)->percent ?? 0;
        $this->userDiscount = BigDecimal::of($percent);

        if ($this->userDiscount->isGreaterThan(0)) {
            return $amount->multipliedBy($this->userDiscount)->dividedBy(100, $this->scale, RoundingMode::HALF_UP);
        }

        return BigDecimal::zero();
    }

    public function getUserDiscount(): string
    {
        return $this->formatDecimal($this->userDiscount);
    }

    /**
     * Приводит BigDecimal к строке без экспоненты и лишних нулей.
     */
    private function formatDecimal(BigDecimal $value, ?int $scale = null): string
    {
        $scale ??= $this->scale;
        return rtrim(rtrim($value->toScale($scale, RoundingMode::HALF_UP)->__toString(), '0'), '.');
    }
}
