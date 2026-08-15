<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\TaskMeta;
use App\Models\SelectorFee;
use App\Models\DirectionExchangeSelectorFee;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

/**
 * SelectedFeesResolver
 *
 * Разрешает «снимок» commissions ({id, scope}) в детальные данные.
 * Умеет:
 *  - enrichSnapshot: вернуть snapshot с вложенными details (из переданного массива или батчем из БД);
 *  - resolve / resolveArray: вернуть плоский массив деталей для UI/подсчётов;
 *  - resolveWithDetails / resolveArrayWithDetails: вернуть snapshot с details.
 */
final class SelectedFeesResolver
{
    private const DEFAULT_SCOPE = 'common';
    private const DEFAULT_FEE_TYPE = 'dynamic';

    /**
     * @pure
     */
    private function coerceScope(mixed $scope): string
    {
        return ($scope === 'individual') ? 'individual' : self::DEFAULT_SCOPE;
    }

    /**
     * @return array{name:string,description:string,fee:string,fee_type:string,sorting:int,snapshot_at:string,version:int}
     */
    private function defaultsDetails(): array
    {
        return [
            'name' => '',
            'description' => '',
            'fee' => '',
            'fee_type' => self::DEFAULT_FEE_TYPE,
            'sorting' => 0,
            'snapshot_at' => Carbon::now()->toISOString(),
            'version' => 1,
        ];
    }

    /**
     * Обогащает snapshot `{id, scope}` деталями (details.*). Если details уже есть — оставляет как есть.
     * Источник деталей: переданный массив `$selectorFees` (быстрее) или база (batch whereIn).
     * Возвращает массив в исходном порядке с вложенным `details`.
     *
     * @param array<int, array{id?:mixed, scope?:mixed, details?:mixed}> $snapshot
     * @param array<int, array{id:int,type:string,name?:string,description?:string,fee?:string,fee_type?:string,sorting?:int}>|null $selectorFees
     * @return array<int, array{id:int,scope:'common'|'individual',details:array<string,mixed>}>
     */
    public function enrichSnapshot(array $snapshot, ?array $selectorFees = null): array
    {
        if ($snapshot === []) return [];

        // Нормализация id/scope и сохранение порядка
        $norm = [];
        $seen = [];
        foreach ($snapshot as $row) {
            if (is_object($row)) $row = (array)$row;
            if (!is_array($row) || !isset($row['id'])) continue;
            $id = (int)($row['id'] ?? 0); if ($id <= 0) continue;
            $scope = $this->coerceScope($row['scope'] ?? null);
            $key = $id.'|'.$scope; if (isset($seen[$key])) continue; $seen[$key] = true;
            $norm[] = ['id'=>$id,'scope'=>$scope,'row'=>$row];
        }
        if ($norm === []) return [];

        // Индексация справочника (если он передан) — без SQL
        $byCommon = $byIndividual = null;
        if (is_array($selectorFees)) {
            $byCommon = []; $byIndividual = [];
            foreach ($selectorFees as $feeRow) {
                $fid = (int)($feeRow['id'] ?? 0); $type = (string)($feeRow['type'] ?? '');
                if ($fid <= 0) continue;
                if ($type === self::DEFAULT_SCOPE)     $byCommon[$fid] = $feeRow;
                if ($type === 'individual') $byIndividual[$fid] = $feeRow;
            }
        }

        // Если справочника нет, подготовим пул id и батчем достанем из БД
        $common = collect(); $individual = collect();
        if (!is_array($byCommon) || !is_array($byIndividual)) {
            $commonIds = []; $individualIds = [];
            foreach ($norm as $n) {
                if ($n['scope'] === 'individual') {
                    $individualIds[] = (int)$n['id'];
                } else {
                    $commonIds[] = (int)$n['id'];
                }
            }
            $commonIds = array_values(array_unique(array_map('intval', $commonIds)));
            $individualIds = array_values(array_unique(array_map('intval', $individualIds)));
            $common = $commonIds === [] ? collect() : SelectorFee::query()->whereIn('id',$commonIds)->get()->keyBy('id');
            $individual = $individualIds === [] ? collect() : DirectionExchangeSelectorFee::query()->whereIn('id',$individualIds)->get()->keyBy('id');
        }

        // Сбор результата
        $out = [];
        foreach ($norm as $n) {
            $id = $n['id']; $scope = $n['scope']; $row = $n['row'];
            $details = is_array($row['details'] ?? null) ? $row['details'] : null;
            if (!$details) {
                if (is_array($byCommon) && is_array($byIndividual)) {
                    $src = $scope==='individual' ? ($byIndividual[$id] ?? null) : ($byCommon[$id] ?? null);
                    if ($src) {
                        $details = [
                            'name' => (string)($src['name'] ?? ''),
                            'description' => (string)($src['description'] ?? ''),
                            'fee' => (string)($src['fee'] ?? ''),
                            'fee_type' => (string)($src['fee_type'] ?? self::DEFAULT_FEE_TYPE),
                            'sorting' => (int)($src['sorting'] ?? 0),
                        ];
                    }
                } else {
                    $model = $scope==='individual' ? $individual->get($id) : $common->get($id);
                    if ($model) {
                        $details = [
                            'name' => (string)($model->name ?? ''),
                            'description' => (string)($model->description ?? ''),
                            'fee' => (string)($model->fee ?? ''),
                            'fee_type' => (string)($model->fee_type ?? self::DEFAULT_FEE_TYPE),
                            'sorting' => (int)($model->sorting ?? 0),
                        ];
                    }
                }
            }

            $out[] = [
                'id'      => $id,
                'scope'   => $scope,
                'details' => array_merge(
                    $this->defaultsDetails(),
                    is_array($details) ? $details : []
                ),
            ];
        }
        return $out;
    }

    /**
     * Разрешить снимок комиссий для одной меты (плоский формат). Короткое замыкание при наличии details.
     * @param TaskMeta $meta
     * @return array<int, array{
     *     id:int, scope:'common'|'individual',
     *     name:string, description:string, fee:string,
     *     fee_type:'dynamic'|'profit', sorting:int, found:bool
     * }>
     */
    public function resolve(TaskMeta $meta): array
    {
        $snapshot = is_array($meta->selected_fees) ? $meta->selected_fees : [];
        if ($snapshot === []) return [];

        $hasAllDetails = collect($snapshot)->every(function ($r) {
            $d = $r['details'] ?? null; return is_array($d) && isset($d['fee']) && isset($d['fee_type']);
        });
        if ($hasAllDetails) {
            return collect($snapshot)->map(function ($r) {
                $d = (array)($r['details'] ?? []);
                return [
                    'id'         => (int)($r['id'] ?? 0),
                    'scope'      => $this->coerceScope($r['scope'] ?? null),
                    'name'       => (string)($d['name'] ?? ''),
                    'description'=> (string)($d['description'] ?? ''),
                    'fee'        => (string)($d['fee'] ?? ''),
                    'fee_type'   => (string)($d['fee_type'] ?? self::DEFAULT_FEE_TYPE),
                    'sorting'    => (int)($d['sorting'] ?? 0),
                    'found'      => true,
                ];
            })->all();
        }

        // Fallback: батч из БД
        [$commonIds, $individualIds] = $this->splitIdsByScope($snapshot);
        $common = $commonIds === [] ? collect() : SelectorFee::query()->whereIn('id', $commonIds)->get()->keyBy('id');
        $individual = $individualIds === [] ? collect() : DirectionExchangeSelectorFee::query()->whereIn('id', $individualIds)->get()->keyBy('id');
        return $this->buildResolved($snapshot, $common, $individual);
    }

    /**
     * Возвращает snapshot с вложенными details (как хранится в БД).
     * @param TaskMeta $meta
     * @param array<int, array{id:int,type:string,name?:string,description?:string,fee?:string,fee_type?:string,sorting?:int}>|null $selectorFees
     * @return array<int, array{id:int,scope:'common'|'individual',details:array<string,mixed>}>
     */
    public function resolveWithDetails(TaskMeta $meta, ?array $selectorFees = null): array
    {
        $snapshot = is_array($meta->selected_fees) ? $meta->selected_fees : [];
        return $this->enrichSnapshot($snapshot, $selectorFees);
    }

    /**
     * Разрешить snapshot (массив) в плоские детали. Предпочитает встроенные details, иначе — батч из БД.
     * @param array $snapshot
     * @param array<int, array{id:int,type:string,name?:string,description?:string,fee?:string,fee_type?:string,sorting?:int}>|null $selectorFees
     * @return array<int, array{
     *     id:int, scope:'common'|'individual',
     *     name:string, description:string, fee:string,
     *     fee_type:'dynamic'|'profit', sorting:int, found:bool
     * }>
     */
    public function resolveArray(array $snapshot, ?array $selectorFees = null): array
    {
        $hasAll = !empty($snapshot) && collect($snapshot)->every(fn($r) => isset($r['details']['fee']) && isset($r['details']['fee_type']));
        if ($hasAll) {
            return collect($snapshot)->map(function ($r) {
                $d = (array)($r['details'] ?? []);
                return [
                    'id'         => (int)($r['id'] ?? 0),
                    'scope'      => $this->coerceScope($r['scope'] ?? null),
                    'name'       => (string)($d['name'] ?? ''),
                    'description'=> (string)($d['description'] ?? ''),
                    'fee'        => (string)($d['fee'] ?? ''),
                    'fee_type'   => (string)($d['fee_type'] ?? self::DEFAULT_FEE_TYPE),
                    'sorting'    => (int)($d['sorting'] ?? 0),
                    'found'      => true,
                ];
            })->all();
        }

        if ($selectorFees !== null) {
            $withDetails = $this->enrichSnapshot($snapshot, $selectorFees);
            return $this->resolveArray($withDetails, $selectorFees); // теперь все имеют details → короткое замыкание
        }

        [$commonIds, $individualIds] = $this->splitIdsByScope($snapshot);
        $common = $commonIds === [] ? collect() : SelectorFee::query()->whereIn('id', $commonIds)->get()->keyBy('id');
        $individual = $individualIds === [] ? collect() : DirectionExchangeSelectorFee::query()->whereIn('id', $individualIds)->get()->keyBy('id');
        return $this->buildResolved($snapshot, $common, $individual);
    }

    /**
     * Вернуть snapshot (массив) с вложенными details.
     * @param array $snapshot
     * @param array<int, array{id:int,type:string,name?:string,description?:string,fee?:string,fee_type?:string,sorting?:int}>|null $selectorFees
     * @return array<int, array{id:int,scope:'common'|'individual',details:array<string,mixed>}>
     */
    public function resolveArrayWithDetails(array $snapshot, ?array $selectorFees = null): array
    {
        return $this->enrichSnapshot($snapshot, $selectorFees);
    }

    /**
     * Разделяет id по scope.
     * @param  array<int, array{id?:mixed, scope?:mixed}>  $snapshot
     * @return array{0: array<int,int>, 1: array<int,int>} [commonIds, individualIds]
     */
    private function splitIdsByScope(array $snapshot): array
    {
        $commonIds = [];
        $individualIds = [];
        foreach ($snapshot as $row) {
            $id = (int)($row['id'] ?? 0); if ($id <= 0) continue;
            $scope = $this->coerceScope($row['scope'] ?? null);
            if ($scope === 'individual') $individualIds[] = $id; else $commonIds[] = $id;
        }
        return [
            array_values(array_unique(array_map('intval', $commonIds))),
            array_values(array_unique(array_map('intval', $individualIds))),
        ];
    }

    /**
     * Конструирует массив детализированных элементов в исходном порядке снимка.
     *
     * @param  array<int, array{id?:mixed, scope?:mixed}> $snapshot
     * @param  Collection<int, SelectorFee>               $common
     * @param  Collection<int, DirectionExchangeSelectorFee> $individual
     * @return array<int, array{
     *     id:int, scope:'common'|'individual',
     *     name:string, description:string, fee:string,
     *     fee_type:'dynamic'|'profit', sorting:int, found:bool
     * }>
     */
    private function buildResolved(array $snapshot, Collection $common, Collection $individual): array
    {
        $out = [];
        foreach ($snapshot as $row) {
            $id = (int)($row['id'] ?? 0); if ($id <= 0) continue;
            $scope = $this->coerceScope($row['scope'] ?? null);
            $model = $scope === 'individual' ? ($individual->get($id)) : ($common->get($id));
            $out[] = [
                'id'         => $id,
                'scope'      => $scope,
                'name'       => isset($model) ? (string)($model->name ?? '') : '',
                'description'=> isset($model) ? (string)($model->description ?? '') : '',
                'fee'        => isset($model) ? (string)($model->fee ?? '') : '',
                'fee_type'   => isset($model) ? (string)($model->fee_type ?? self::DEFAULT_FEE_TYPE) : self::DEFAULT_FEE_TYPE,
                'sorting'    => isset($model) ? (int)($model->sorting ?? 0) : 0,
                'found'      => $model !== null,
            ];
        }
        return $out;
    }
}
