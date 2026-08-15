<?php
namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ReviewFilter extends ModelFilter
{
    /**
     * Фильтр по статусам
     *
     * @param $value
     * @return ReviewFilter
     */
    public function status($value): ReviewFilter
    {
        if (is_array($value)) {
            return $this->whereIn('status', $value);
        }
        return $this->where('status', $value);
    }

    /**
     * Поиск по имени
     *
     * @param $name
     * @return ReviewFilter
     */
    public function name($name): ReviewFilter
    {
        return $this->where('name', 'like', '%'.$name.'%');
    }

    /**
     * Поиск по ID заявки
     *
     * @param $id
     * @return ReviewFilter
     */
    public function idTask($id): ReviewFilter
    {
        return $this->where('id_task', 'like', '%'.$id.'%');
    }

    /**
     * Поиск по IP
     *
     * @param $ip
     * @return ReviewFilter
     */
    public function ipAddress($ip): ReviewFilter
    {
        return $this->where('ip_address', '=', $ip);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return ReviewFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): ReviewFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
