<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\DirectionExchange;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Result\ValidationResult;
use Illuminate\Support\Facades\Validator;

/**
 * DirectionFieldsRule — валидация дополнительных полей направления (direction_fields).
 *
 * Ожидаемые входные данные (в DataBag):
 * - direction_fields: array<string, mixed>
 *   где ключи совпадают с key_id полей направления.
 *
 * Правило:
 * - НЕ использует request()
 * - НЕ пишет в $errors
 * - Возвращает ValidationResult
 */
final class DirectionFieldsRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        $directionFields = $direction->direction_field ?? [];
        if (empty($directionFields)) {
            return $result;
        }

        $rules = [];
        $attributes = [];

        foreach ($directionFields as $field) {
            if ((int)($field->status ?? 0) === 1) {
                continue;
            }

            $fieldKey = 'direction_fields.' . (string)($field->key_id ?? '');
            if ($fieldKey === 'direction_fields.') {
                continue;
            }

            $rules[$fieldKey] = $this->buildFieldRules($field);
            $attributes[$fieldKey] = (string)($field->name ?? $fieldKey);
        }

        if ($rules === []) {
            return $result;
        }

        // ВАЖНО: берём входные данные только из контекста
        $input = [
            'direction_fields' => $context->data->get('direction_fields', []),
        ];

        $validator = Validator::make($input, $rules, [], $attributes);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $result->addError($field, (string)$message, 'direction_field_invalid');
                }
            }
        }

        return $result;
    }

    /**
     * Сформировать правила для одного direction field.
     *
     * @param object $field
     * @return array<int, string|\Closure>
     */
    private function buildFieldRules(object $field): array
    {
        $rules = [];

        // required/nullable: в твоей логике obligatory_field === 0 => required
        $rules[] = ((int)($field->obligatory_field ?? 1) === 0) ? 'required' : 'nullable';

        // Тип поля
        $this->applyFieldTypeRules($rules, $field);

        // Язык поля
        $this->applyLanguageRules($rules, $field);

        // min/max символов — только для нечисловых (как у тебя)
        if ((int)($field->field_type ?? 0) !== 1) {
            $this->applyLengthRules($rules, $field);
        }

        // starts_with / ends_with
        $this->applyStartEndRules($rules, $field);

        return array_values(array_filter($rules));
    }

    /**
     * Правила на основе типа поля.
     *
     * field_type:
     *  1 => integer (numeric + digits_between)
     *  2 => string letters (regex)
     *  3 => alpha_num
     *  4 => alpha
     */
    private function applyFieldTypeRules(array &$rules, object $field): void
    {
        $type = (int)($field->field_type ?? 0);
        $min = (int)($field->min_char ?? 0);
        $max = (int)($field->max_char ?? 0);

        match ($type) {
            1 => $rules = array_merge($rules, [
                'numeric',
                ($min > 0 && $max > 0) ? "digits_between:{$min},{$max}" : null,
            ]),
            2 => $rules[] = 'regex:/^[\p{L}\s]+$/u',
            3 => $rules[] = 'alpha_num',
            4 => $rules[] = 'alpha',
            default => null,
        };
    }

    /**
     * Языковые правила:
     * language_field:
     *  1 => кириллица
     *  2 => латиница
     */
    private function applyLanguageRules(array &$rules, object $field): void
    {
        $lang = (int)($field->language_field ?? 0);

        if ($lang === 1) {
            $rules[] = 'regex:/^[а-яА-ЯёЁ\s]+$/u';
        } elseif ($lang === 2) {
            $rules[] = 'regex:/^[a-zA-Z\s]+$/u';
        }
    }

    /**
     * min/max длина (для нечисловых).
     */
    private function applyLengthRules(array &$rules, object $field): void
    {
        $min = (int)($field->min_char ?? 0);
        $max = (int)($field->max_char ?? 0);

        if ($min > 0) $rules[] = 'min:' . $min;
        if ($max > 0) $rules[] = 'max:' . $max;
    }

    /**
     * starts_with / ends_with
     */
    private function applyStartEndRules(array &$rules, object $field): void
    {
        $start = isset($field->start_with) ? trim((string)$field->start_with) : '';
        $end   = isset($field->end_with) ? trim((string)$field->end_with) : '';

        if ($start !== '') $rules[] = 'starts_with:' . $start;
        if ($end !== '')   $rules[] = 'ends_with:' . $end;
    }
}
