<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class PaymentFilter extends ModelFilter
{
    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return PaymentFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value)
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
