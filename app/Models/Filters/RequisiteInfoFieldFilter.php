<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class RequisiteInfoFieldFilter extends ModelFilter
{
    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return RequisiteInfoFieldFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value)
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
