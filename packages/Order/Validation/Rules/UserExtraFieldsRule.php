<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\ExtraField;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Result\ValidationResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * UserExtraFieldsRule — валидация глобальных пользовательских полей для заявок (scope=user_order).
 *
 * Ожидаемые входные данные (в DataBag):
 * - user_fields: array<string, mixed>
 *   где ключи совпадают с key_id полей из extra_fields (scope=user_order)
 *
 * Правило:
 * - НЕ использует request()
 * - НЕ пишет в $errors
 * - Возвращает ValidationResult
 */
final class UserExtraFieldsRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        $fields = ExtraField::query()
            ->active()
            ->where('scope', 'user_order')
            ->where('attach_to_order', true)
            ->orderBy('sorting')
            ->get();

        if ($fields->isEmpty()) {
            return $result;
        }

        $rules = $this->buildRules($fields);
        if ($rules === []) {
            return $result;
        }

        // ВАЖНО: берём только нужный блок из контекста
        $input = [
            'user_fields' => $context->data->get('user_fields', []),
        ];

        $validator = Validator::make(
            $input,
            $rules,
            [],
            $this->getAttributeNames($fields)
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $fieldKey => $messages) {
                foreach ($messages as $message) {
                    $type = $this->findValidatorTypeByFieldKey($fieldKey, $fields);
                    $example = $type ? ($this->examples()[$type] ?? null) : null;

                    $final = $example
                        ? __(":message Пример: «:example».", ['message' => $message, 'example' => $example])
                        : $message;

                    $result->addError($fieldKey, (string)$final, 'user_field_invalid');
                }
            }
        }

        return $result;
    }

    /**
     * @param Collection<int, ExtraField> $fields
     * @return array<string, array<int, string>>
     */
    private function buildRules(Collection $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            if ((int)($field->status ?? 0) === 1) {
                continue;
            }

            $fieldKey = $this->makeKey((string)$field->key_id);
            if ($fieldKey === '') {
                continue;
            }

            $base = [$this->isRequired($field) ? 'required' : 'nullable'];

            $validatorRules = $this->validatorTypeRules((string)$field->validator_type);

            $rules[$fieldKey] = array_values(array_filter(array_merge(
                $base,
                $validatorRules ?? [],
                $this->lengthRules((int)$field->min_char, (int)$field->max_char),
                $this->formatRules((string)$field->start_with, (string)$field->end_with)
            )));
        }

        return $rules;
    }

    /**
     * Ключ правила:
     * user_fields.{key_id}
     */
    private function makeKey(string $keyId): string
    {
        $keyId = trim($keyId);
        return $keyId !== '' ? "user_fields.{$keyId}" : '';
    }

    /**
     * obligatory_field: 0 => required
     */
    private function isRequired(ExtraField $field): bool
    {
        return (int)$field->obligatory_field === 0;
    }

    /** @return array<int, string> */
    private function lengthRules(int $min, int $max): array
    {
        $r = [];
        if ($min > 0) $r[] = 'min:' . $min;
        if ($max > 0) $r[] = 'max:' . $max;
        return $r;
    }

    /** @return array<int, string> */
    private function formatRules(string $start, string $end): array
    {
        $r = [];
        $start = trim($start);
        $end = trim($end);
        if ($start !== '') $r[] = 'starts_with:' . $start;
        if ($end !== '')   $r[] = 'ends_with:' . $end;
        return $r;
    }

    /** @return array<int, string>|null */
    private function validatorTypeRules(string $type): ?array
    {
        return match ($type) {
            '' => null,
            'fio_ru' => ['regex:/^[А-ЯЁ][а-яё]+\s[А-ЯЁ][а-яё]+(\s[А-ЯЁ][а-яё]+)?$/u'],
            'fio_en' => ['regex:/^[A-Z][a-z]+\s[A-Z][a-z]+(\s[A-Z][a-z]+)?$/'],
            'bik' => ['digits:9'],
            'phone_with_plus' => ['regex:/^\+?\d{7,15}$/'],
            'phone_without_plus' => ['regex:/^\d{7,15}$/'],
            'telegram_nickname' => ['regex:/^@?[a-zA-Z0-9_]{5,32}$/'],
            'iban' => ['regex:/^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/'],
            default => null,
        };
    }

    /**
     * Человекочитаемые имена атрибутов для ошибок.
     *
     * @param Collection<int, ExtraField> $fields
     * @return array<string, string>
     */
    private function getAttributeNames(Collection $fields): array
    {
        $attrs = [];
        foreach ($fields as $field) {
            $key = $this->makeKey((string)$field->key_id);
            if ($key === '') continue;

            // name — translatable
            $attrs[$key] = $field->getTranslation('name', app()->getLocale()) ?? $key;
        }
        return $attrs;
    }

    private function findValidatorTypeByFieldKey(string $fieldKey, Collection $fields): ?string
    {
        if (!str_starts_with($fieldKey, 'user_fields.')) {
            return null;
        }

        $keyId = substr($fieldKey, strlen('user_fields.'));

        foreach ($fields as $f) {
            if ((string)$f->key_id === $keyId) {
                $t = trim((string)$f->validator_type);
                return $t !== '' ? $t : null;
            }
        }

        return null;
    }

    /** @return array<string,string> */
    private function examples(): array
    {
        return [
            'fio_ru' => 'Иванов Иван Иванович',
            'fio_en' => 'John Michael Doe',
            'bik' => '044525225',
            'phone_with_plus' => '+79261234567',
            'phone_without_plus' => '79261234567',
            'telegram_nickname' => __('username или @username'),
            'iban' => 'GB82WEST12345698765432',
        ];
    }
}
