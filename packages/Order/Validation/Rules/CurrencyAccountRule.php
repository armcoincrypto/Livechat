<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\DirectionExchange;
use App\Models\Task;
use Brick\Math\BigDecimal;
use App\Support\MoneyParser;
use Carbon\Carbon;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Enums\CurrencyScope;
use iEXPackages\Order\Validation\Result\ValidationResult;

/**
 * CurrencyAccountRule — проверка реквизитов (income_account / outcome_account) и лимитов по валюте.
 *
 * Основная идея:
 * - scope берётся из ValidationContext options (ValidationStep->options): ['scope' => 'in'|'out'].
 * - scope=in  → проверяем income_account и лимиты currency1.
 * - scope=out → проверяем outcome_account и лимиты currency2.
 *
 * Важно:
 * - Флаг visible_* трактуется по legacy-логике:
 *   visible_give = 1 / visible_receiving = 1  => проверки реквизита пропускаются
 *   visible_* = 0 => реквизит считается обычным и подлежит проверкам
 *
 * - Если scope=out и пришёл extra_out.enabled=true + осмысленные fields —
 *   outcome_account может быть пустым, проверки формата/символов пропускаются.
 *
 * - Правило валидирует:
 *   1) обязательность реквизита (по твоей бизнес-логике: поле "включено" и есть настроенные проверки)
 *   2) формат (wallet_validator, длина)
 *   3) допустимые символы (allowed_char, first_value)
 *   4) лимиты по валюте (day/month)
 */
final class CurrencyAccountRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        // Определяем сторону (in/out) из контекста шага
        $scope = $context->currencyScope(CurrencyScope::IN);
        $isOut = ($scope === CurrencyScope::OUT);

        // Имя поля в data
        $accountField = $isOut ? 'outcome_account' : 'income_account';

        // Сырой реквизит
        $rawAccount = $context->data->getString($accountField);
        $account = $this->compactAccount($rawAccount);

        // Валюта стороны
        $currency = $isOut ? $direction->currency2 : $direction->currency1;

        // Если валюта отсутствует — не проверяем (нечего проверять)
        if (!$currency) {
            return $result;
        }

        /**
         * 1) Для OUT возможно альтернативное заполнение реквизитов через extra_out.
         *    Тогда outcome_account может быть пустым — и формат/символы мы пропускаем.
         */
        $skipAccountChecksByExtraOut = $isOut && $this->shouldSkipAccountChecksBecauseExtraOut($context);

        /**
         * 2) Legacy: visible_* = 1 означает "пропустить проверки реквизита".
         *    Если visible_* = 1 → формат/символы не проверяем.
         */
        $skipAccountChecksByVisibility = $this->shouldSkipAccountValidationByVisibility($currency, $isOut);

        /**
         * 3) Формат/символы проверяем, если:
         *    - не включён skip по extra_out
         *    - не включён skip по visible_*
         */
        $shouldValidateAccount = !$skipAccountChecksByExtraOut && !$skipAccountChecksByVisibility;

        if ($shouldValidateAccount) {
            /**
             * 3.1) Обязательность реквизита по твоей логике:
             *      Если поле "включено" (visible_* = 0) И на валюте реально настроены проверки,
             *      то пустой account должен считаться ошибкой. Иначе валидаторы никогда не сработают.
             */
            if ($account === null || $account === '') {
                if ($this->isAccountValidationConfigured($currency)) {
                    $currencyName = $this->makeCurrencyName($currency);

                    return $result->addError(
                        $accountField,
                        __('Укажите номер счета для валюты :name.', ['name' => $currencyName]),
                        'account_required'
                    );
                }

                // Если проверок вообще нет — пустой реквизит не требуем
                // (иначе это будет бессмысленная ошибка)
                return $result;
            }

            /**
             * 3.2) Формат (wallet_validator, длина)
             */
            if ($err = $this->validateAccountFormat($currency, $accountField, $account)) {
                return $result->addError($err['field'], $err['message'], 'account_format_invalid');
            }

            /**
             * 3.3) Символы/first_value/allowed_char
             */
            if ($err = $this->validateAllowedCharacters($currency, $accountField, $account)) {
                return $result->addError($err['field'], $err['message'], 'account_chars_invalid');
            }
        }

        /**
         * 4) Лимиты (day/month) — проверяем всегда, т.к. они не зависят от заполненности реквизита
         *    и относятся к операции по валюте в целом.
         */
        if ($err = $this->validateLimits($context, $currency, $isOut)) {
            return $result->addError($err['field'], $err['message'], 'currency_limit_exceeded');
        }

        return $result;
    }

    /**
     * extra_out может заменить outcome_account.
     * Если включено и есть осмысленные поля — outcome_account можно не проверять.
     */
    private function shouldSkipAccountChecksBecauseExtraOut(ValidationContext $context): bool
    {
        $extra = $context->data->get('extra_out');
        if ($extra === null) {
            return false;
        }

        // поддержка JSON-строки / объекта / массива
        if (is_string($extra)) {
            $decoded = json_decode($extra, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $extra = $decoded;
            }
        } elseif (is_object($extra)) {
            $extra = (array)$extra;
        }

        if (!is_array($extra)) {
            return false;
        }

        if (empty($extra['enabled'])) {
            return false;
        }

        if (empty($extra['fields']) || !is_array($extra['fields'])) {
            return false;
        }

        // Есть ли хотя бы одна осмысленная запись (label или amount > 0)
        foreach ($extra['fields'] as $f) {
            $label = isset($f['label']) ? trim((string)$f['label']) : '';
            $amountRaw = isset($f['amount']) ? (string)$f['amount'] : '';
            $amount = MoneyParser::positiveOrZero($amountRaw);

            if ($label !== '' || $amount->isGreaterThan('0')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Legacy логика visible_*:
     * - 1 => поле особое/скрыто → проверки реквизита пропускаются
     * - 0 => обычное поле → проверки выполняются
     */
    private function shouldSkipAccountValidationByVisibility(object $currency, bool $isOut): bool
    {
        return $isOut
            ? (bool)($currency->visible_receiving ?? false)
            : (bool)($currency->visible_give ?? false);
    }

    /**
     * Определяет, настроены ли для валюты проверки реквизита.
     * Если да — пустой реквизит при "включённом" поле должен давать ошибку.
     */
    private function isAccountValidationConfigured(object $currency): bool
    {
        $hasWalletValidator = !empty($currency->validation_account_from) || !empty($currency->validation_account_to);
        $hasLength          = ((int)($currency->min_char ?? 0) > 0) && ((int)($currency->max_char ?? 0) > 0);
        $hasFirstValue      = !empty($currency->first_value);
        $hasAllowedChars    = (int)($currency->allowed_char ?? 0) > 0;

        return $hasWalletValidator || $hasLength || $hasFirstValue || $hasAllowedChars;
    }

    /**
     * Проверка формата реквизита:
     * - wallet_validator(validation_account_from / validation_account_to)
     * - min_char/max_char (длина)
     *
     * @return array{field:string,message:string}|null
     */
    private function validateAccountFormat(object $currency, string $fieldName, string $account): ?array
    {
        $currencyName = $this->makeCurrencyName($currency);

        if (!empty($currency->validation_account_from) && !wallet_validator((string)$currency->validation_account_from, $account)) {
            return $this->error($fieldName, __('Некорректный номер счета для валюты :name.', ['name' => $currencyName]));
        }

        if (!empty($currency->validation_account_to) && !wallet_validator((string)$currency->validation_account_to, $account)) {
            return $this->error($fieldName, __('Некорректный номер счета для валюты :name.', ['name' => $currencyName]));
        }

        $min = (int)($currency->min_char ?? 0);
        $max = (int)($currency->max_char ?? 0);

        // Если min/max не заданы — длину не проверяем
        if ($min > 0 && $max > 0) {
            $len = strlen($account);

            if ($len < $min || $len > $max) {
                $message = !empty($currency->min_max_error_message)
                    ? str_replace('[currency]', $currencyName, (string)$currency->min_max_error_message)
                    : __('Номер кошелька для :name должен содержать от :min до :max символов.', [
                        'name' => $currencyName,
                        'min' => $min,
                        'max' => $max,
                    ]);

                return $this->error($fieldName, (string)$message);
            }
        }

        return null;
    }

    /**
     * Проверка допустимых символов:
     * - first_value
     * - allowed_char
     *
     * @return array{field:string,message:string}|null
     */
    private function validateAllowedCharacters(object $currency, string $fieldName, string $account): ?array
    {
        $currencyName = $this->makeCurrencyName($currency);

        $firstValue = isset($currency->first_value) ? (string)$currency->first_value : '';
        if ($firstValue !== '' && !str_starts_with($account, $firstValue)) {
            return $this->error($fieldName, __('Счет для :name должен начинаться с символа \":char\".', [
                'name' => $currencyName,
                'char' => $firstValue,
            ]));
        }

        $mode = (int)($currency->allowed_char ?? 0);

        return match ($mode) {
            1 => ctype_digit($account) ? null : $this->error($fieldName, __('Счет для :name должен состоять только из цифр.', ['name' => $currencyName])),
            2 => preg_match('/^[a-zA-Zа-яА-ЯёЁ]+$/u', $account) ? null : $this->error($fieldName, __('Счет для :name должен содержать только буквы.', ['name' => $currencyName])),
            3 => ctype_alnum($account) ? null : $this->error($fieldName, __('Счет для :name должен содержать только латинские буквы и цифры.', ['name' => $currencyName])),
            4 => ctype_alpha($account) ? null : $this->error($fieldName, __('Счет для :name должен содержать только латинские буквы.', ['name' => $currencyName])),
            default => null,
        };
    }

    /**
     * Проверка дневного/месячного лимита по валюте.
     *
     * @return array{field:string,message:string}|null
     */
    private function validateLimits(ValidationContext $context, object $currency, bool $isOut): ?array
    {
        $currencyName = $this->makeCurrencyName($currency);

        $dayLimitRaw = $isOut
            ? (string)($currency->day_limit_receive ?? '0')
            : (string)($currency->day_limit_give ?? '0');

        $monthLimitRaw = $isOut
            ? (string)($currency->month_limit_out ?? '0')
            : (string)($currency->month_limit_in ?? '0');

        $limits = [
            'day'   => MoneyParser::parse($dayLimitRaw),
            'month' => MoneyParser::parse($monthLimitRaw),
        ];

        foreach ($limits as $period => $limit) {
            if ($limit === null || !$limit->isGreaterThan('0')) {
                continue;
            }

            if (!$this->checkCurrencyOrderLimit($context, $currency, $limit, $period, $isOut)) {
                $field = $isOut ? 'right_limit_account' : 'left_limit_account';

                return $this->error(
                    $field,
                    __('Превышен :period лимит для валюты :name.', [
                        'period' => $period === 'day' ? 'дневной' : 'месячный',
                        'name' => $currencyName,
                    ])
                );
            }
        }

        return null;
    }

    /**
     * Проверить суммарные операции по валюте за период + текущая сумма.
     */
    private function checkCurrencyOrderLimit(
        ValidationContext $context,
        object $currency,
        BigDecimal $limit,
        string $period,
        bool $isOut
    ): bool {
        $range = match ($period) {
            'month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            default => [Carbon::today(), Carbon::today()->endOfDay()],
        };

        $amountKey = $isOut ? 'outcome_amount' : 'income_amount';
        $currentAmount = MoneyParser::parse($context->data->getString($amountKey)) ?? BigDecimal::zero();

        $sumField = $isOut ? 'receiving_price' : 'give_price';
        $currencyColumn = $isOut ? 'id_currency2' : 'id_currency1';

        $total = Task::query()
            ->whereBetween('updated_at', $range)
            ->where('status', 4)
            ->whereHas('direction_exchange', fn ($q) => $q->where($currencyColumn, (int)$currency->id))
            ->sum($sumField);

        $totalAmount = MoneyParser::parse((string) $total) ?? BigDecimal::zero();

        return $totalAmount->plus($currentAmount)->isLessThanOrEqualTo($limit);
    }

    /**
     * Компактная очистка реквизита:
     * - trim
     * - security_xss
     * - удаление пробелов
     */
    private function compactAccount(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $s = trim(security_xss($raw));
        if ($s === '') {
            return null;
        }

        $s = preg_replace('/\s+/', '', $s) ?? $s;
        $s = trim($s);

        return $s !== '' ? $s : null;
    }

    /**
     * Человеческое имя валюты (для сообщений).
     */
    private function makeCurrencyName(object $currency): string
    {
        $payment = (string)($currency->payment->name ?? '');
        $code    = (string)($currency->code_currency->name ?? '');

        return trim($payment . ' ' . $code);
    }

    /** @return array{field:string,message:string} */
    private function error(string $field, string $message): array
    {
        return ['field' => $field, 'message' => $message];
    }

}
