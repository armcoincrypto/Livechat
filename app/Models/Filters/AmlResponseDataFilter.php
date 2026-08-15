<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AmlResponseDataFilter extends ModelFilter
{
    /**
     * Искать ID заявке
     *
     * @param string $value
     * @return AmlResponseDataFilter
     */
    public function idTask(string $value): AmlResponseDataFilter
    {
        return $this->where('id_task', '=', $value);
    }

    /**
     * Искать по алиасу
     *
     * @param string $value
     * @return AmlResponseDataFilter
     */
    public function alias(string $value): AmlResponseDataFilter
    {
        return $this->where('alias', '=', $value);
    }

    /**
     * Искать по типу
     *
     * @param array $value
     * @return AmlResponseDataFilter
     */
    public function method(array $value): AmlResponseDataFilter
    {
        return $this->whereIn('method', $value);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return AmlResponseDataFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): AmlResponseDataFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
