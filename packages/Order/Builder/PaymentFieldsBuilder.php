<?php

declare(strict_types=1);

namespace iEXPackages\Order\Builder;

use App\Models\Currency;
use App\Models\Task;
use App\Models\TaskField;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * PaymentFieldsBuilder
 *
 * Отвечает за формирование pay_fields для UI и (при необходимости) за фиксацию
 * реквизитов в БД (TaskField alias=requisites), как это было в старой логике.
 *
 * Важно:
 * - buildPayFields() всегда возвращает массив для UI.
 * - ensureTaskRequisitesSaved() один раз создаёт записи TaskField, если их нет.
 */
final class PaymentFieldsBuilder
{
    /**
     * Возвращает массив pay_fields для UI.
     *
     * Алгоритм:
     * 1) Если реквизиты уже сохранены в TaskField (alias=requisites) — возвращаем их.
     * 2) Иначе строим "виртуальные" поля из шаблонов Currency->requisites_fields (без записи в БД).
     *
     * @return array<int, array{name:string, value:string, comment:?string}>
     */
    public function buildPayFields(Task $task, Currency $inCurrency, array $invoiceCtx): array
    {
        $existing = $this->getExistingTaskRequisites($task);
        if ($existing->isNotEmpty()) {
            return $existing->map(static fn (TaskField $f) => [
                'name'    => (string) $f->field_name,
                'value'   => (string) $f->field_value,
                'comment' => $f->requisites_fields?->comment,
            ])->values()->all();
        }

        $tplFields = $this->getCurrencyTemplateFields($inCurrency);
        if ($tplFields->isEmpty()) {
            return [];
        }

        $tokens = is_array($invoiceCtx['tokens'] ?? null) ? $invoiceCtx['tokens'] : [];

        return $tplFields->map(static function ($field) use ($tokens) {
            $rawValue = (string) ($field->value ?? '');

            $value = $tokens !== []
                ? str_replace(array_keys($tokens), array_values($tokens), $rawValue)
                : $rawValue;

            $prefix = !empty($field->prefix) ? (string) $field->prefix : '';

            return [
                'name'    => (string) ($field->name ?? ''),
                'value'   => $prefix . $value,
                'comment' => isset($field->comment) ? (string) $field->comment : null,
            ];
        })->values()->all();
    }

    /**
     * Гарантированно сохраняет реквизиты в TaskField (alias=requisites),
     * если они ещё не сохранены.
     *
     * Используй это, если хочешь вернуть старое поведение:
     * - один раз сохранить сформированные реквизиты в БД
     * - дальше всегда читать из TaskField
     *
     * Рекомендуемое условие вызова:
     * - только когда invoiceCtx['mode'] === 'requisites'
     *
     * @return bool true если вставили новые записи, иначе false
     */
    public function ensureTaskRequisitesSaved(Task $task, Currency $inCurrency, array $invoiceCtx): bool
    {
        // Уже сохранено — ничего не делаем
        if ($this->getExistingTaskRequisites($task)->isNotEmpty()) {
            return false;
        }

        $tplFields = $this->getCurrencyTemplateFields($inCurrency);
        if ($tplFields->isEmpty()) {
            return false;
        }

        $tokens = is_array($invoiceCtx['tokens'] ?? null) ? $invoiceCtx['tokens'] : [];

        $now = Carbon::now()->toDateTimeString();

        $rows = $tplFields->map(static function ($field) use ($task, $tokens, $now) {
            $rawValue = (string) ($field->value ?? '');

            $value = $tokens !== []
                ? str_replace(array_keys($tokens), array_values($tokens), $rawValue)
                : $rawValue;

            $prefix = !empty($field->prefix) ? (string) $field->prefix : '';

            $finalValue = $prefix . $value;

            return [
                'id_task'     => (int) $task->id,
                'field_name'  => (string) ($field->name ?? ''),
                'field_value' => $finalValue,
                'created_at'  => $now,
                'updated_at'  => $now,

                // Важно: в старой системе это было id_field (привязка к шаблону)
                'id_field'    => isset($field->id) ? (int) $field->id : null,

                'alias'       => 'requisites',
            ];
        })->filter(static fn (array $row) => trim((string) ($row['field_value'] ?? '')) !== '')
            ->values()
            ->all();

        if ($rows === []) {
            return false;
        }

        // Вставляем одной пачкой
        TaskField::insert($rows);

        return true;
    }

    /**
     * @return Collection<int, TaskField>
     */
    private function getExistingTaskRequisites(Task $task): Collection
    {
        return TaskField::query()
            ->where('id_task', $task->id)
            ->where('alias', 'requisites')
            ->whereNotNull('field_value')
            ->where('field_value', '!=', '')
            ->get();
    }

    /**
     * @return Collection<int, mixed>
     */
    private function getCurrencyTemplateFields(Currency $inCurrency): Collection
    {
        // Если relation не загружен — подгружаем
        $fields = $inCurrency->relationLoaded('requisites_fields')
            ? $inCurrency->requisites_fields
            : $inCurrency->requisites_fields()->get();

        return $fields
            ->where('status', 1)
            ->sortBy('sorting')
            ->values();
    }
}
