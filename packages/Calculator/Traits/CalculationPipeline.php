<?php
declare(strict_types=1);

namespace iEXPackages\Calculator\Traits;

use App\Services\Calculator\CalculatorMathService;
use Illuminate\Support\Collection;

/**
 * CalculationPipeline
 *
 * Трейт для применения выбранных клиентом комиссий (selector fees) к расчёту курса.
 *
 * Используется в методе calculateWithOptions(), где:
 * - сначала строится справочник доступных комиссий ($this->options['selector_fees']),
 * - затем из snapshot (options/meta) берётся список выбранных комиссий,
 * - и они применяются в заданном порядке.
 *
 * Snapshot выбранных комиссий (selected_fees) должен быть массивом элементов вида:
 *  - ['id' => 123, 'scope' => 'common'|'individual']
 * Дополнительно может содержать:
 *  - ['details' => ['fee' => '+1.5%', 'fee_type' => 'dynamic'|'profit']]
 *
 * Если details присутствует — справочник selector_fees не нужен для этого элемента.
 */
trait CalculationPipeline
{
    private const DEFAULT_SCOPE    = 'common';
    private const DEFAULT_FEE_TYPE = 'dynamic';

    /**
     * Нормализует scope выбранной комиссии.
     *
     * @param mixed $scope
     * @return string 'common'|'individual'
     */
    private function coerceScope(mixed $scope): string
    {
        return ($scope === 'individual') ? 'individual' : self::DEFAULT_SCOPE;
    }

    /**
     * Нормализует тип комиссии.
     *
     * @param mixed $type
     * @return string 'dynamic'|'profit'
     */
    private function coerceFeeType(mixed $type): string
    {
        $t = strtolower(trim((string) $type));
        return ($t === 'profit') ? 'profit' : self::DEFAULT_FEE_TYPE;
    }

    /**
     * Проверяет, является ли комиссия нейтральной (не влияет на результат).
     *
     * Нейтральные значения:
     * - пусто
     * - 0 / 0.0 / 0.000 / +0 / -0
     * - 0% / 0.0% / +0% / -0%
     *
     * @param string $feeExpr
     * @return bool
     */
    private function isNeutralFee(string $feeExpr): bool
    {
        $s = trim($feeExpr);
        if ($s === '') {
            return true;
        }

        // Нормализация
        $s = str_replace(' ', '', $s);

        // 0 / 0.0 / 000.000
        if (preg_match('/^[+\-]?0+(?:\.0+)?%?$/', $s) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Нормализует snapshot выбранных комиссий для расчёта (calculateWithOptions).
     *
     * Приводит к массиву элементов:
     *  [
     *    ['id' => 123, 'scope' => 'common'|'individual', 'details' => [...optional...]],
     *    ...
     *  ]
     *
     * @param mixed $input     Массив/объект/null из options или meta заявки.
     * @param int   $maxItems  Защита от перегруза: максимум элементов в snapshot.
     * @return array<int, array{id:int, scope:'common'|'individual', details?:array}>
     */
    protected function normalizeSelectedFeesForCalculation(mixed $input, int $maxItems = 20): array
    {
        if (!is_array($input)) {
            return [];
        }

        $input = array_slice($input, 0, max(1, $maxItems));

        return collect($input)
            ->map(function ($row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                if (!is_array($row) || !isset($row['id'])) {
                    return null;
                }

                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    return null;
                }

                $scope = $this->coerceScope($row['scope'] ?? null);

                $out = ['id' => $id, 'scope' => $scope];

                // Если передали детали сразу — сохраняем
                if (isset($row['details']) && is_array($row['details'])) {
                    $out['details'] = $row['details'];
                }

                return $out;
            })
            ->filter()
            ->unique(fn ($r) => $r['id'].'|'.$r['scope'])
            ->values()
            ->all();
    }

    /**
     * Применяет к текущему расчёту комиссии из snapshot `selected_fees`.
     *
     * Источник snapshot берётся из:
     * - $options['selected_fees'] (приоритет),
     * - иначе $this->order?->meta?->selected_fees (fallback).
     *
     * Справочник комиссий берётся из:
     * - $options['selector_fees'] (если передан),
     * - иначе $this->options['selector_fees'].
     *
     * Требования к классу-носителю:
     * - метод applySelectedFee(CalculatorMathService $mathValue, string $feeExpr, string $feeType): void
     * - свойство $this->order (может быть null)
     *
     * @param CalculatorMathService $mathValue Объект, управляющий арифметикой курса.
     * @param array $options Опции расчёта (selected_fees, selector_fees).
     * @param int $maxItems Ограничение количества элементов snapshot.
     * @return void
     */
    protected function applySelectedFeesFromOptions(
        CalculatorMathService $mathValue,
        array $options,
        int $maxItems = 20
    ): void {
        // 1) Источник snapshot
        $selectedFeesInput = [];
        if (!empty($options['selected_fees']) && is_array($options['selected_fees'])) {
            $selectedFeesInput = $options['selected_fees'];
        } elseif (!empty($this->order?->meta?->selected_fees) && is_array($this->order->meta->selected_fees)) {
            $selectedFeesInput = $this->order->meta->selected_fees;
        }

        if (empty($selectedFeesInput)) {
            return;
        }

        // 2) Нормализация snapshot
        $normalizedSelected = $this->normalizeSelectedFeesForCalculation($selectedFeesInput, $maxItems);
        if (empty($normalizedSelected)) {
            return;
        }

        // 3) Справочник комиссий
        $catalog = $options['selector_fees'] ?? $this->options['selector_fees'] ?? [];
        $selectorFees = collect(is_array($catalog) ? $catalog : []);
        if ($selectorFees->isEmpty()) {
            // если в snapshot есть details — справочник не обязателен,
            // но если ни у одного элемента details нет — применять нечего
            $hasAnyDetails = collect($normalizedSelected)->contains(fn ($s) => isset($s['details']) && is_array($s['details']));
            if (!$hasAnyDetails) {
                return;
            }
        }

        // 4) Применение в порядке snapshot
        foreach ($normalizedSelected as $sel) {
            $targetId = (int) ($sel['id'] ?? 0);
            if ($targetId <= 0) {
                continue;
            }

            $scope = $this->coerceScope($sel['scope'] ?? null);

            // 4.1) Если детали уже есть в snapshot — применяем их
            if (isset($sel['details']) && is_array($sel['details'])) {
                $d = $sel['details'];

                $feeExpr = (string) ($d['fee'] ?? '');
                $feeType = $this->coerceFeeType($d['fee_type'] ?? self::DEFAULT_FEE_TYPE);

                if (!$this->isNeutralFee($feeExpr)) {
                    $this->applySelectedFee($mathValue, $feeExpr, $feeType);
                }
                continue;
            }

            // 4.2) Иначе ищем в справочнике по id + scope (type == scope), затем fallback по id
            $match = $selectorFees->first(function ($fee) use ($targetId, $scope) {
                return (int) ($fee['id'] ?? 0) === $targetId && (($fee['type'] ?? null) === $scope);
            }) ?? $selectorFees->first(fn ($fee) => (int) ($fee['id'] ?? 0) === $targetId);

            if (!$match) {
                continue;
            }

            $feeExpr = (string) ($match['fee'] ?? '');
            $feeType = $this->coerceFeeType($match['fee_type'] ?? self::DEFAULT_FEE_TYPE);

            if ($this->isNeutralFee($feeExpr)) {
                continue;
            }

            $this->applySelectedFee($mathValue, $feeExpr, $feeType);
        }
    }
}
