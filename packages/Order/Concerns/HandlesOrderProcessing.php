<?php

namespace iEXPackages\Order\Concerns;

use App\Models\CheckboxAgreement;
use App\Models\CurrencyFields;
use App\Models\DirectionField;
use App\Models\ExtraField;
use App\Models\Requisites;
use App\Models\Task;
use App\Models\TaskCardDetail;
use App\Models\TaskField;
use App\Models\User;
use iEXPackages\ReferralSystem\DTO\ReferralCaptureResult;
use iEXPackages\ReferralSystem\Services\ReferralCaptureResolver;
use iEXPackages\ReferralSystem\Services\ReferralAuditLogger;
use iEXPackages\ReferralSystem\Support\ReferralAuditEvent;
use Illuminate\Support\Facades\Log;

trait HandlesOrderProcessing
{
    protected ?ReferralCaptureResult $referralCaptureResult = null;
    protected function formatIncomeAmount(?string $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0';
        }

        // Нормализуем ввод
        $amount = trim($amount);
        $amount = str_replace(',', '.', $amount); // поддержка запятой как десятичного разделителя
        $amount = rtrim($amount, '.');            // убираем хвостовую точку

        // Безопасно считаем длину дробной части
        $pos = strrpos($amount, '.');
        $decimalLength = $pos === false ? 0 : max(0, strlen($amount) - $pos - 1);

        // Кламп точности по настройке валюты
        $maxPrecision = (int) ($this->getInCurrency()?->number_format ?? 0);
        $precision    = min($decimalLength, max(0, $maxPrecision));

        return iex_number_format($amount, $precision);
    }


    protected function resolveReferralData(): array
    {
        $this->referralCaptureResult = app(ReferralCaptureResolver::class)
            ->resolve(request(), $this->authInfo);

        if (!$this->referralCaptureResult->hasCode()) {
            return [null, 0];
        }

        return [
            $this->referralCaptureResult->code,
            $this->referralCaptureResult->linkId,
        ];
    }

    protected function logReferralCaptureForTask(Task $task): void
    {
        $capture = $this->referralCaptureResult;
        if ($capture === null || !$capture->hasCode() || empty($task->referral_hash)) {
            return;
        }

        try {
            app(ReferralAuditLogger::class)->info(
                ReferralAuditEvent::REFERRAL_CAPTURED,
                'Referral captured at order creation (attribution only)',
                [
                    'referral_link_id' => $task->id_referral_link ?: $capture->linkId ?: null,
                    'partner_user_id' => $capture->partnerUserId,
                    'task_id' => $task->id,
                ],
                [
                    'code' => (string) $task->referral_hash,
                    'source' => $capture->source,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('referral_capture_audit_failed', [
                'task_id' => $task->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function isRequestPaymentType(): bool
    {
        $direction = $this->directionId;
        $currency  = $direction?->currency1;

        // 1. Приоритет за направлением
        $directionFlag = (int)($direction?->method_request_payment ?? 0) === 1;
        if ($directionFlag) {
            return true;
        }

        // 2. Проверяем наличие реквизитов
        $currencyId = $direction?->id_currency1 ?? null;
        $hasAccount = false;

        if ($currencyId) {
            $hasAccount = (bool) Requisites::where('id_currency', $currencyId)->value('account_number');
        }

        if ($hasAccount) {
            return true;
        }

        // 3. Флаг валюты, если направление не включено
        $currencyFlag = (int)($currency?->method_request_payment ?? 0) === 1;

        return $currencyFlag;
    }

    protected function getCalculationOptions(): array
    {
        $selectedFees = $this->normalizeSelectedFeesFromRequest($this->options['selected_fees'] ?? null);

        $options = [
            'city_id' => (int)($this->options['city_id'] ?? 0),
            'promo_code' => $this->options['promo_code'] ?? null,
            'type_rate' => $this->directionId->is_type_rate === 1
                ? ($this->options['type_rate'] ?? null)
                : null,
            'selected_fees' => $selectedFees,
            'card_verification_required' => (bool)($this->options['card_verification_required'] ?? false),
        ];


        // Добавляем комиссии из чекбоксов, если они отмечены и содержат метку {fee=...}
        if (!empty($this->options['checkbox_agreements'])) {
            $checkboxFees = CheckboxAgreement::query()
                ->whereIn('key_id', array_keys($this->options['checkbox_agreements']))
                ->pluck('label', 'key_id')
                ->map(function ($label, $key) {
                    if (!empty($this->options['checkbox_agreements'][$key]) && is_string($label)) {
                        preg_match_all('/\{fee=([-+]?[0-9]*\.?[0-9]+%?)\}(.*?)\{\/fee\}/', $label, $matches, PREG_SET_ORDER);
                        return collect($matches)->pluck(1)->toArray();
                    }

                    return [];
                })
                ->flatten()
                ->filter()
                ->values()
                ->toArray();

            if (!empty($checkboxFees)) {
                $options['checkbox_fees'] = $checkboxFees;
            }
        }

        return $options;
    }

    /**
     * Нормализует входящий параметр selected_fees до канонического массива.
     * Возвращает список уникальных элементов в порядке следования.
     *
     * Формат результата: [{ id:int, scope:'common'|'individual' }]
     *
     * @param mixed $input     Данные из запроса/опций (массив/объект/null)
     * @param int   $maxItems  Максимум элементов (защита от перегруза)
     * @return array<int, array{id:int, scope:'common'|'individual'}>
     */
    protected function normalizeSelectedFeesFromRequest(mixed $input, int $maxItems = 20): array
    {
        if (!is_array($input)) {
            return [];
        }

        // Ограничиваем длину массива
        $input = array_slice($input, 0, max(1, $maxItems));

        $out = [];
        $seen = [];
        foreach ($input as $fee) {
            if (is_object($fee)) { $fee = (array) $fee; }
            if (!is_array($fee) || empty($fee['id'])) { continue; }

            $id = (int) ($fee['id'] ?? 0);
            if ($id <= 0) { continue; }

            $scope = isset($fee['scope']) ? strtolower((string)$fee['scope']) : 'common';
            $scope = $scope === 'individual' ? 'individual' : 'common';

            $key = $id.'|'.$scope;
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;

            $out[] = ['id' => $id, 'scope' => $scope];
        }

        return $out;
    }

    protected function resolveOrderType(): int
    {
        // Release A revised: missing/legacy mode defaults to FLOATING (1).
        // Explicit 0 (fixed) remains fixed when the direction supports type_rate.
        return ($this->directionId->is_type_rate === 1)
            ? (int)($this->options['type_rate'] ?? 1)
            : 0;
    }

    protected function saveAdditionalDirectionFields(int $taskId): void
    {
        $fields = collect($this->getAdditionFieldsDirection())
            ->map(function ($value, $key) {
                return [$key => $value];
            })
            ->filter()
            ->mapWithKeys(fn ($field) => $field)
            ->map(function ($value, $key) use ($taskId) {
                $fieldItem = DirectionField::whereKeyId($key)->first();

                if (!$fieldItem) {
                    return null;
                }

                return [
                    'id_task' => $taskId,
                    'id_field' => $fieldItem->id,
                    'field_key' => $fieldItem->key_id,
                    'field_name' => $fieldItem->name,
                    'field_value' => $fieldItem->remove_spaces
                        ? remove_all_spaces($value)
                        : $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'alias' => 'direction',
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        if (!empty($fields)) {
            TaskField::insert($fields);
        }
    }

    /**
     * Подготавливает массив дополнительных полей валюты для записи в базу данных.
     *
     * @param array $fields Массив входящих полей.
     * @param string $type Тип валютной операции ('in' или 'out').
     * @param int $taskId Идентификатор задачи.
     * @return array Подготовленный массив данных для вставки.
     */
    protected function prepareCurrencyFields(array $fields, string $type, int $taskId): array
    {
        if (empty($fields)) {
            return [];
        }

        $fieldItems = CurrencyFields::whereIn('key_id', array_keys($fields))->get()->keyBy('key_id');

        $result = [];

        foreach ($fields as $key => $value) {
            $fieldItem = $fieldItems->get($key);

            if (!$fieldItem) {
                continue;
            }

            $filteredValue = $fieldItem->remove_spaces
                ? remove_all_spaces($value)
                : $value;

            $result[] = [
                'type_field'  => $type,
                'id_task'     => $taskId,
                'id_field'    => $fieldItem->id,
                'field_key'   => $fieldItem->key_id,
                'field_name'  => $fieldItem->name,
                'field_value' => $filteredValue,
                'created_at'  => now(),
                'updated_at'  => now(),
                'alias'       => 'currency',
            ];
        }

        return $result;
    }


    protected function saveAdditionalUserOrderFields(int $taskId): void
    {
        $fields = collect($this->getAdditionFieldsUserOrder())
            ->map(function ($value, $key) {
                return [$key => $value];
            })
            ->filter()
            ->mapWithKeys(fn ($field) => $field)
            ->map(function ($value, $key) use ($taskId) {

                /** @var ExtraField|null $fieldItem */
                $fieldItem = ExtraField::query()
                    ->active()
                    ->where('scope', 'user_order')
                    ->where('key_id', $key)
                    ->first();

                if (!$fieldItem) {
                    return null;
                }

                $filteredValue = ((int)$fieldItem->remove_spaces === 1)
                    ? remove_all_spaces((string)$value)
                    : (string)$value;

                return [
                    'type_field'  => 'user_order',
                    'id_task'     => $taskId,
                    'id_field'    => $fieldItem->id,
                    'field_key'   => $fieldItem->key_id,
                    'field_name'  => $fieldItem->name,
                    'field_value' => $filteredValue,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                    'alias'       => 'user_order',
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        if (!empty($fields)) {
            TaskField::insert($fields);
        }
    }
}
