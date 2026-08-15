<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ReservesFilter extends ModelFilter
{
    /**
     * Поиск по группам
     *
     * @param $ids
     * @return ReservesFilter
     */
    public function idGroups($ids): ReservesFilter
    {
        return $this->whereIn('id_group', $ids);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return ReservesFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): ReservesFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
