<?php
namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class GatewayMerchantFilter extends ModelFilter
{

    /**
     * Фильтр по активности
     *
     * @param mixed $value
     * @return GatewayMerchantFilter
     */
    public function status(mixed $value): GatewayMerchantFilter
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
     * @return GatewayMerchantFilter
     */
    public function name(string $value): GatewayMerchantFilter
    {
        return $this->where('name', 'like', '%'.$value.'%');
    }

    /**
     * Фильтр по провайдерам
     *
     * @param array $ids
     * @return GatewayMerchantFilter
     */
    public function alias(array $ids): GatewayMerchantFilter
    {
        return $this->whereIn('alias', $ids);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return GatewayMerchantFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): GatewayMerchantFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
