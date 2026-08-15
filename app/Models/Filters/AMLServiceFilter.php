<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AMLServiceFilter  extends ModelFilter
{
    /**
     * Поиск по названию
     *
     * @param $value
     * @return AMLServiceFilter
     */
    public function name($value): AMLServiceFilter
    {
        return $this->where('name', 'LIKE', "%{$value}%");
    }

    /**
     * Поиск по alias
     *
     * @param $value
     * @return AMLServiceFilter
     */
    public function alias($value): AMLServiceFilter
    {
        return $this->where('alias', 'LIKE', "%{$value}%");
    }

    /**
     * Фильтр по активности
     *
     * @return AMLServiceFilter
     */
    public function status($value)
    {
        if (is_array($value)) {
            return $this->whereIn('status', $value);
        }

        return $this->where('status', $value);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return AMLServiceFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): AMLServiceFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
