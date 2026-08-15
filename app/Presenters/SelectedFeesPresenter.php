<?php
declare(strict_types=1);

namespace App\Presenters;

/**
 * SelectedFeesPresenter
 *
 * Без-SQL форматирование снимка комиссий для фронтенда.
 * Принимает массив `selected_fees` (содержимое JSON из БД) и возвращает удобные структуры
 * для UI/ресурсов без каких-либо побочных эффектов.
 */
final class SelectedFeesPresenter
{
    private const DEFAULT_SCOPE = 'common';
    private const DEFAULT_FEE_TYPE = 'dynamic';

    /** @pure */
    private static function coerceScope(mixed $scope): string
    {
        return ($scope === 'individual') ? 'individual' : self::DEFAULT_SCOPE;
    }

    /** @pure */
    private static function str(mixed $v): string { return is_string($v) ? $v : (string)($v ?? ''); }

    /**
     * Преобразовать snapshot (после каста/модели) в плоский формат для API/UI.
     *
     * @param array<int, array{id?:mixed, scope?:mixed, details?:mixed}> $snapshot
     * @return array<int, array{
     *   id:int,
     *   scope:'common'|'individual',
     *   name:string,
     *   description:string,
     *   fee:string,
     *   fee_type:'dynamic'|'profit',
     *   sorting:int,
     *   found:bool
     * }>
     */
    public static function flatten(array $snapshot): array
    {
        if ($snapshot === []) return [];

        $out = [];
        foreach ($snapshot as $row) {
            if (!is_array($row) || !isset($row['id'])) { continue; }
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) { continue; }

            $scope = self::coerceScope($row['scope'] ?? null);
            $d = is_array($row['details'] ?? null) ? (array)$row['details'] : [];

            $out[] = [
                'id'          => $id,
                'scope'       => $scope,
                'name'        => self::str($d['name'] ?? ''),
                'description' => self::str($d['description'] ?? ''),
                'fee'         => self::str($d['fee'] ?? ''),
                'fee_type'    => self::str($d['fee_type'] ?? self::DEFAULT_FEE_TYPE),
                'sorting'     => (int)($d['sorting'] ?? 0),
                'found'       => true,
            ];
        }
        return $out;
    }

    /**
     * Короткий бейдж (первое имя + "+N"). Без побочных эффектов.
     * @param array<int, array{name?:string}> $flat
     */
    public static function badge(array $flat): string
    {
        if (!$flat) return '';
        $first = (string)($flat[0]['name'] ?? '');
        $extra = max(0, count($flat) - 1);
        return $extra > 0 ? trim($first.' +'.$extra) : $first;
    }

    /**
     * Полный title: перечисление имён через запятую. Без побочных эффектов.
     * @param array<int, array{name?:string}> $flat
     */
    public static function title(array $flat): string
    {
        return implode(', ', array_filter(array_map(fn($i) => (string)($i['name'] ?? ''), $flat)));
    }
}
