<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\DirectionExchange;
use App\Models\DirectionExchangeCity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Result\ValidationResult;

/**
 * AmountRule — проверка минимальных/максимальных сумм по направлению.
 *
 * Логика:
 * 1) Берём базовые лимиты направления (min/max по "Отдаю" и "Получаю").
 * 2) Если указан город — переопределяем лимиты "Отдаю" из DirectionExchangeCity (min_price/max_price).
 * 3) Если направление использует конструктор верификации (card_verification_type=2),
 *    то переопределяем min/max для "Отдаю" из card_verification_rules (no_verification/with_verification).
 * 4) Валидируем суммы income_amount/outcome_amount по лимитам.
 *
 * Важно:
 * - Правило не пишет в БД.
 * - Правило не читает request()/auth() напрямую.
 * - Сравнения выполняются через BigDecimal (без float погрешностей).
 */
final class AmountRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        // Суммы из формы (строки), нормализуем
        $incomeAmount = $this->readMoney(
            $context->data->getString('income_amount'),
            $this->currencyScaleSafe($direction->currency1?->number_format)
        );

        $outcomeAmount = $this->readMoney(
            $context->data->getString('outcome_amount'),
            $this->currencyScaleSafe($direction->currency2?->number_format)
        );

        // Базовые лимиты направления
        $minIncome = $this->readMoney((string)($direction->min_price1 ?? '0'), $this->currencyScaleSafe($direction->currency1?->number_format));
        $maxIncome = $this->readMoney((string)($direction->max_price1 ?? '0'), $this->currencyScaleSafe($direction->currency1?->number_format));
        $minOutcome = $this->readMoney((string)($direction->min_price2 ?? '0'), $this->currencyScaleSafe($direction->currency2?->number_format));
        $maxOutcome = $this->readMoney((string)($direction->max_price2 ?? '0'), $this->currencyScaleSafe($direction->currency2?->number_format));

        // 1) Город: переопределяем лимиты "Отдаю"
        $cityId = (int)($context->data->get('city_id') ?? 0);
        if ($cityId > 0) {
            $directionCity = DirectionExchangeCity::find($cityId);
            if ($directionCity) {
                $minIncome = $this->preferPositiveMoney(
                    $this->readMoney((string)($directionCity->min_price ?? ''), $this->currencyScaleSafe($direction->currency1?->number_format)),
                    $minIncome
                );
                $maxIncome = $this->preferPositiveMoney(
                    $this->readMoney((string)($directionCity->max_price ?? ''), $this->currencyScaleSafe($direction->currency1?->number_format)),
                    $maxIncome
                );
            }
        }

        // 2) Конструктор верификации: переопределяем лимиты "Отдаю"
        $cardType = (int)($direction->card_verification_type ?? 0);
        if ($cardType === 2 && !empty($direction->card_verification_rules) && is_array($direction->card_verification_rules)) {
            $isVerificationRequired = (bool)($context->data->get('card_verification_required') ?? false);

            $rules = $direction->card_verification_rules;
            $noVerification = is_array($rules['no_verification'] ?? null) ? $rules['no_verification'] : [];
            $withVerification = is_array($rules['with_verification'] ?? null) ? $rules['with_verification'] : [];

            $minField = $isVerificationRequired ? ($withVerification['min_amount'] ?? null) : ($noVerification['min_amount'] ?? null);
            $maxField = $isVerificationRequired ? ($withVerification['max_amount'] ?? null) : ($noVerification['max_amount'] ?? null);

            $minIncome = $this->preferPositiveMoney(
                $this->readMoney(is_null($minField) ? '' : (string)$minField, $this->currencyScaleSafe($direction->currency1?->number_format)),
                $minIncome
            );
            $maxIncome = $this->preferPositiveMoney(
                $this->readMoney(is_null($maxField) ? '' : (string)$maxField, $this->currencyScaleSafe($direction->currency1?->number_format)),
                $maxIncome
            );
        }

        // Подписи валют
        $inCurrencySign  = (string)($direction->currency1?->code_currency?->name ?? '');
        $outCurrencySign = (string)($direction->currency2?->code_currency?->name ?? '');
        $inPaymentName   = (string)($direction->currency1?->payment?->name ?? '');

        // -------- Проверки --------

        // max "Отдаю"
        if ($this->isPositive($maxIncome) && $incomeAmount !== null && $incomeAmount->isGreaterThan($maxIncome)) {
            $exceededBy = $incomeAmount->minus($maxIncome);

            $message = nl2br(
                __(
                    'В настоящее время мы не принимаем сумму более :sum на платежную систему :payment',
                    [
                        'sum' => sprintf('%s %s', $this->formatMoney($maxIncome, $direction->currency1?->number_format), $inCurrencySign),
                        'payment' => $inPaymentName,
                    ]
                ) . '<br />' .
                __(
                    'Превышение лимита на :limit',
                    [
                        'limit' => sprintf(
                            '%s %s',
                            $this->formatMoney($exceededBy, $direction->currency1?->number_format),
                            $inCurrencySign
                        ),
                    ]
                ),
                false
            );

            $result->addError('sell', $message, 'amount_in_max_exceeded');
        }

        // min "Отдаю"
        if ($this->isPositive($minIncome) && $incomeAmount !== null && $incomeAmount->isLessThan($minIncome)) {
            $result->addError(
                'sell',
                __('Минимальная сумма, доступная для отправки: :min', [
                    'min' => sprintf('%s %s', $this->formatMoney($minIncome, $direction->currency1?->number_format), $inCurrencySign),
                ]),
                'amount_in_min_not_reached'
            );
        }

        // min "Получаю"
        if ($this->isPositive($minOutcome) && $outcomeAmount !== null && $outcomeAmount->isLessThan($minOutcome)) {
            $result->addError(
                'buy',
                __('Минимальная сумма, которую вы можете получить: :min', [
                    'min' => sprintf('%s %s', $this->formatMoney($minOutcome, $direction->currency2?->number_format), $outCurrencySign),
                ]),
                'amount_out_min_not_reached'
            );
        }

        // max "Получаю"
        if ($this->isPositive($maxOutcome) && $outcomeAmount !== null && $outcomeAmount->isGreaterThan($maxOutcome)) {
            $result->addError(
                'buy',
                __('Максимальная сумма, которую вы можете получить за один обмен: :max', [
                    'max' => sprintf('%s %s', $this->formatMoney($maxOutcome, $direction->currency2?->number_format), $outCurrencySign),
                ]),
                'amount_out_max_exceeded'
            );
        }

        return $result;
    }

    /**
     * Нормализовать число из строки в BigDecimal.
     *
     * Поддерживает:
     * - пробелы и NBSP
     * - десятичную запятую
     *
     * @param string|null $raw
     * @param int $scale Сколько знаков после запятой оставить (RoundingMode::DOWN)
     * @return BigDecimal|null
     */
    private function readMoney(?string $raw, int $scale): ?BigDecimal
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

    /**
     * Если $candidate > 0 — вернуть его, иначе вернуть $fallback.
     */
    private function preferPositiveMoney(?BigDecimal $candidate, ?BigDecimal $fallback): ?BigDecimal
    {
        if ($candidate !== null && $candidate->isGreaterThan('0')) {
            return $candidate;
        }
        return $fallback;
    }

    /**
     * Проверка "значение задано и > 0".
     */
    private function isPositive(?BigDecimal $value): bool
    {
        return $value !== null && $value->isGreaterThan('0');
    }

    /**
     * Безопасно получить scale из number_format (если null/не число — 18).
     */
    private function currencyScaleSafe(mixed $numberFormat): int
    {
        $scale = is_numeric($numberFormat) ? (int)$numberFormat : 18;
        return ($scale >= 0 && $scale <= 30) ? $scale : 18;
    }

    /**
     * Форматирование суммы для UI с учётом number_format валюты.
     *
     * @param BigDecimal $value
     * @param mixed $numberFormat
     */
    private function formatMoney(BigDecimal $value, mixed $numberFormat): string
    {
        $scale = $this->currencyScaleSafe($numberFormat);

        // iex_number_format ожидает число/строку; передаём строку BigDecimal
        return iex_number_format($value->__toString(), $scale);
    }
}
