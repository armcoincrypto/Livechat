<?php

namespace App\Models\Filters;


use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class TaskRequisiteAttachedFilter extends ModelFilter
{
    /**
     * Искать ID заявке
     *
     * @param string $value
     * @return TaskRequisiteAttachedFilter
     */
    public function idTask(string $value): TaskRequisiteAttachedFilter
    {
        return $this->where('id_task', '=', $value);
    }

    /**
     * Искать по счету
     *
     * @param string $value
     * @return TaskRequisiteAttachedFilter
     */
    public function walletNumber(string $value): TaskRequisiteAttachedFilter
    {
        return $this->where('wallet_number', '=', $value);
    }

    /**
     * Искать по ip адресу
     *
     * @param string $value
     * @return TaskRequisiteAttachedFilter
     */
    public function ipAddress(string $value): TaskRequisiteAttachedFilter
    {
        return $this->where('ip_address', '=', $value);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return TaskRequisiteAttachedFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): TaskRequisiteAttachedFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
