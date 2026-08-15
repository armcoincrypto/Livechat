<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\DirectionExchange;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Enums\CurrencyScope;
use iEXPackages\Order\Validation\Result\ValidationResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * CurrencyFieldsRule — валидация пользовательских полей валюты (fields_in / fields_out).
 *
 * Как работает:
 * 1) Берём scope из контекста шага: $context->option('scope', 'in')
 * 2) По scope выбираем список описаний полей из DirectionExchange:
 *    - scope=in  -> currency1->currency_in_fields
 *    - scope=out -> currency2->currency_out_fields
 * 3) Строим правила Laravel Validator на основе описаний
 * 4) Валидируем по данным формы из $context->data->all() (НЕ request())
 * 5) Ошибки складываем в ValidationResult через addError()
 *
 * ВАЖНО:
 * - rule не принимает массивы полей в конструктор
 * - rule не использует request()
 * - scope задаётся в ValidationStep options
 */
final class CurrencyFieldsRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        // 1) Получаем scope из options шага (не из конструктора)
        $scope = $context->currencyScope(CurrencyScope::IN); // вернёт CurrencyScope::IN/OUT

        // 2) Достаём описание полей валюты из направления по scope
        $fields = $this->getCurrencyFieldsByScope($direction, $scope);

        // Если полей нет — правило просто ничего не делает
        if ($fields === []) {
            return $result;
        }

        // 3) Собираем правила валидатора
        $rules = $this->buildRules($fields);
        if ($rules === []) {
            return $result;
        }

        // 4) Валидируем по данным из контекста (НЕ request)
        $validator = Validator::make(
            $context->data->all(),
            $rules,
            [], // при желании можно добавить кастомные сообщения
            $this->getAttributeNames($fields)
        );

        // 5) Переносим ошибки в ValidationResult
        if ($validator->fails()) {
            $this->collectErrorsToResult(
                $validator->errors()->messages(),
                $result,
                $fields
            );
        }

        return $result;
    }

    /**
     * Получить список описаний полей валюты из DirectionExchange по scope.
     *
     * @param DirectionExchange $direction
     * @param CurrencyScope $scope
     * @return array<int, object>
     */
    private function getCurrencyFieldsByScope(DirectionExchange $direction, CurrencyScope $scope): array
    {
        $fields = match ($scope) {
            CurrencyScope::IN  => $direction->currency1?->currency_in_fields ?? [],
            CurrencyScope::OUT => $direction->currency2?->currency_out_fields ?? [],
        };

        // currency_*_fields может быть Eloquent Collection
        if ($fields instanceof Collection) {
            $fields = $fields->all();
        }

        return is_array($fields) ? $fields : [];
    }

    /**
     * Построить массив правил для Laravel Validator.
     *
     * @param array<int, object> $fields
     * @return array<string, array<int, string|\Closure>>
     */
    private function buildRules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $item) {
            // status=1 => поле выключено
            if ((int)($item->status ?? 0) === 1) {
                continue;
            }

            // Ключ поля в input: fields_in.{key_id} или fields_out.{key_id}
            $fieldKey = $this->makeFieldKey($item);
            if ($fieldKey === '') {
                continue;
            }

            $validatorRules = $this->getValidatorTypeRules($item);

            // Если задан validator_type — используем спец. валидатор + required + length + start/end
            if ($validatorRules !== null) {
                $rules[$fieldKey] = array_values(array_filter(array_merge(
                    $this->getRequiredRule($item),
                    $validatorRules,
                    $this->getLengthRules($item),
                    $this->getFormatRules($item)
                )));
                continue;
            }

            // Иначе — базовые правила + языковые + start/end
            $rules[$fieldKey] = array_values(array_filter(array_merge(
                $this->getBaseRules($item),
                $this->getLanguageRules($item),
                $this->getFormatRules($item)
            )));
        }

        return $rules;
    }

    /**
     * Базовые правила (обязательность, тип, длина).
     *
     * @return array<int, string|\Closure>
     */
    private function getBaseRules(object $item): array
    {
        $rules = [$this->isRequired($item) ? 'required' : 'nullable'];

        $fieldType = (string)($item->field_type ?? '');

        return match ($fieldType) {
            'integer' => array_values(array_filter(array_merge($rules, [
                'numeric',
                ...($this->intVal($item->min_char) > 0 || $this->intVal($item->max_char) > 0
                    ? ["digits_between:{$this->intVal($item->min_char)},{$this->intVal($item->max_char)}"]
                    : []
                ),
            ]))),
            'string' => array_values(array_filter(array_merge($rules, [
                'regex:/^[\p{L}\s]+$/u',
                ...($this->intVal($item->min_char) > 0 ? ['min:' . $this->intVal($item->min_char)] : []),
                ...($this->intVal($item->max_char) > 0 ? ['max:' . $this->intVal($item->max_char)] : []),
            ]))),
            default => $rules,
        };
    }

    /** @return array<int, string> */
    private function getRequiredRule(object $item): array
    {
        return [$this->isRequired($item) ? 'required' : 'nullable'];
    }

    /** @return array<int, string> */
    private function getLengthRules(object $item): array
    {
        $rules = [];

        $min = $this->intVal($item->min_char);
        $max = $this->intVal($item->max_char);

        if ($min > 0) $rules[] = 'min:' . $min;
        if ($max > 0) $rules[] = 'max:' . $max;

        return $rules;
    }

    /** @return array<int, string> */
    private function getLanguageRules(object $item): array
    {
        return match ((int)($item->language_field ?? 0)) {
            1 => ['regex:/^[а-яА-ЯёЁ\s]+$/u'],
            2 => ['regex:/^[a-zA-Z\s]+$/u'],
            default => [],
        };
    }

    /** @return array<int, string> */
    private function getFormatRules(object $item): array
    {
        $rules = [];

        $start = isset($item->start_with) ? trim((string)$item->start_with) : '';
        $end   = isset($item->end_with) ? trim((string)$item->end_with) : '';

        if ($start !== '') $rules[] = 'starts_with:' . $start;
        if ($end !== '')   $rules[] = 'ends_with:' . $end;

        return $rules;
    }

    /** @return array<int, string>|null */
    private function getValidatorTypeRules(object $item): ?array
    {
        $type = (string)($item->validator_type ?? '');

        return match ($type) {
            'fio_ru' => ['regex:/^[А-ЯЁ][а-яё]+\s[А-ЯЁ][а-яё]+(\s[А-ЯЁ][а-яё]+)?$/u'],
            'fio_en' => ['regex:/^[A-Z][a-z]+\s[A-Z][a-z]+(\s[A-Z][a-z]+)?$/'],
            'bik' => ['digits:9'],
            'phone_with_plus' => ['regex:/^\+7\d{10}$/'],
            'phone_without_plus' => ['regex:/^7\d{10}$/'],
            'telegram_nickname' => ['regex:/^@?[a-zA-Z0-9_]{5,32}$/'],
            'iban' => ['regex:/^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/'],
            default => null,
        };
    }

    /**
     * Человекочитаемые названия полей для ошибок.
     *
     * @param array<int, object> $fields
     * @return array<string, string>
     */
    private function getAttributeNames(array $fields): array
    {
        $attributes = [];

        foreach ($fields as $item) {
            if ((int)($item->status ?? 0) === 1) {
                continue;
            }

            $key = $this->makeFieldKey($item);
            if ($key === '') {
                continue;
            }

            $attributes[$key] = (string)($item->name ?? $key);
        }

        return $attributes;
    }

    /**
     * Перенос ошибок валидатора в ValidationResult + примеры.
     *
     * @param array<string, array<int, string>> $messages
     * @param array<int, object> $fields
     */
    private function collectErrorsToResult(array $messages, ValidationResult $result, array $fields): void
    {
        foreach ($messages as $field => $fieldMessages) {
            foreach ($fieldMessages as $message) {
                $validatorType = $this->findValidatorTypeByField($field, $fields);
                $example = $validatorType ? ($this->getValidatorExamples()[$validatorType] ?? null) : null;

                $final = $example
                    ? __(":message Пример: «:example».", ['message' => $message, 'example' => $example])
                    : $message;

                $result->addError($field, $final, 'currency_field_invalid');
            }
        }
    }

    /**
     * Найти validator_type по ключу поля.
     *
     * @param array<int, object> $fields
     */
    private function findValidatorTypeByField(string $field, array $fields): ?string
    {
        foreach ($fields as $item) {
            if ($this->makeFieldKey($item) === $field) {
                $t = (string)($item->validator_type ?? '');
                return $t !== '' ? $t : null;
            }
        }
        return null;
    }

    /** @return array<string, string> */
    private function getValidatorExamples(): array
    {
        return [
            'fio_ru' => 'Иванов Иван Иванович',
            'fio_en' => 'John Michael Doe',
            'bik' => '044525225',
            'phone_with_plus' => '+79261234567',
            'phone_without_plus' => '79261234567',
            'telegram_nickname' => 'username или @username',
            'iban' => 'GB82WEST12345698765432',
        ];
    }

    /**
     * Сформировать ключ поля:
     * - when_print=0 -> fields_in.{key_id}
     * - when_print=1 -> fields_out.{key_id}
     */
    private function makeFieldKey(object $item): string
    {
        $when = (int)($item->when_print ?? -1);
        $keyId = trim((string)($item->key_id ?? ''));

        return match ($when) {
            0 => $keyId !== '' ? "fields_in.{$keyId}" : '',
            1 => $keyId !== '' ? "fields_out.{$keyId}" : '',
            default => $keyId,
        };
    }

    /** obligatory_field: 0 => required, иначе nullable */
    private function isRequired(object $item): bool
    {
        return (int)($item->obligatory_field ?? 1) === 0;
    }

    private function intVal(mixed $v): int
    {
        return is_numeric($v) ? (int)$v : 0;
    }
}
