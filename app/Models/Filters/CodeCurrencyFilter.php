<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class CodeCurrencyFilter extends ModelFilter
{
    /**
     * Искать названию
     *
     * @param string $value
     * @return CodeCurrencyFilter
     */
    public function name(string $value): CodeCurrencyFilter
    {
        // Проверяем наличие запятых
        if (str_contains($value, ',')) {
            // Разбиваем строку по запятым и удаляем лишние пробелы
            $values = array_map('trim', explode(',', $value));

            // Делаем поиск по нескольким значениям
            return $this->where(function($query) use ($values) {
                foreach ($values as $val) {
                    $query->orWhere('name', 'like', "%{$val}%");
                }
            });
        }

        // Поиск по одному значению
        return $this->whereLike('name', $value);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return CodeCurrencyFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): CodeCurrencyFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
