<?php
declare(strict_types=1);

namespace App\Services\ExtraFields;

use App\Models\ExtraField;
use App\Models\ExtraFieldValue;
use App\Models\User;
use Illuminate\Support\Collection;

final class ExtraFieldsService
{
    /**
     * @return Collection<int, ExtraField>
     */
    public function getActiveUserFields(): Collection
    {
        return ExtraField::query()
            ->active()
            ->whereIn('scope', ['user_registration', 'user_profile'])
            ->orderBy('sorting')
            ->get();
    }

    /**
     * Подмешать значения из профиля пользователя (owner=User).
     */
    public function mergeFromUserProfile(User $user, array $input): array
    {
        $rows = $user->extraFieldValues()
            ->with('field:id,key_id')
            ->get()
            ->filter(fn($row) => $row->field && $row->field->key_id)
            ->keyBy(fn($row) => (string)$row->field->key_id);

        foreach ($rows as $keyId => $row) {
            if (!array_key_exists($keyId, $input) || $this->isEmpty($input[$keyId])) {
                $input[$keyId] = $row->field_value;
            }
        }

        return $input;
    }

    /**
     * Нормализовать значения по правилам поля.
     */
    public function normalizeValues(Collection $fields, array $values): array
    {
        $fieldsByKey = $fields->keyBy('key_id');

        foreach ($values as $keyId => $value) {
            $field = $fieldsByKey->get($keyId);
            if (!$field) continue;

            $v = is_string($value) ? $value : (string)$value;

            if ((int)$field->remove_spaces === 1) {
                $v = remove_all_spaces($v);
            }

            $v = trim($v);

            $start = trim((string)$field->start_with);
            $end   = trim((string)$field->end_with);

            if ($start !== '' && $v !== '' && !str_starts_with($v, $start)) {
                $v = $start . $v;
            }
            if ($end !== '' && $v !== '' && !str_ends_with($v, $end)) {
                $v = $v . $end;
            }

            $values[$keyId] = $v;
        }

        return $values;
    }

    /**
     * Сохранить значения в профиль пользователя (owner=User).
     */
    public function saveUserProfileValues(User $user, array $values): void
    {
        if ($values === []) return;

        $fields = ExtraField::query()
            ->active()
            ->whereIn('key_id', array_keys($values))
            ->get()
            ->keyBy('key_id');

        foreach ($values as $keyId => $value) {
            $field = $fields->get($keyId);
            if (!$field) continue;

            ExtraFieldValue::query()->updateOrCreate(
                [
                    'field_id'   => $field->id,
                    'owner_type' => $user::class,
                    'owner_id'   => $user->id,
                ],
                [
                    'field_value' => is_string($value) ? $value : (string)$value,
                ]
            );
        }
    }

    private function isEmpty(mixed $v): bool
    {
        if ($v === null) return true;
        if (is_string($v) && trim($v) === '') return true;
        return false;
    }
}
