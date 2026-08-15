<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class GatewayPaymentFilter extends ModelFilter
{
    /**
     * Фильтр по активности
     *
     * @param mixed $value
     * @return GatewayPaymentFilter
     */
    public function status(mixed $value): GatewayPaymentFilter
    {
        // Преобразуем строку в массив, если это не массив
        if (!is_array($value)) {
            $value = [$value];
        }
        return $this->whereIn('status', $value);
    }

    /**
     * Фильтровать по категориям
     *
     * @param string $value
     * @return GatewayPaymentFilter
     */
    public function name(string $value): GatewayPaymentFilter
    {
        return $this->where('name', 'like', '%'.$value.'%');
    }

    /**
     * Фильтр по провайдерам
     *
     * @param array $ids
     * @return GatewayPaymentFilter
     */
    public function alias(array $ids): GatewayPaymentFilter
    {
        return $this->whereIn('alias', $ids);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return GatewayPaymentFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): GatewayPaymentFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
