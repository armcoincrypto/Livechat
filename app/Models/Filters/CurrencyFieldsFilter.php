<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class CurrencyFieldsFilter extends ModelFilter
{
    /**
     * Поиск по названию
     *
     * @param $value
     * @return CurrencyFieldsFilter
     */
    public function name($value): CurrencyFieldsFilter
    {
        return $this->where('name->'.app()->getLocale(), 'like', '%'.$value.'%');
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return CurrencyFieldsFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): CurrencyFieldsFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
