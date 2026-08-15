<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;

class FaqFilter extends ModelFilter
{
    /**
     * Установночный фильтр
     */
    public function setup()
    {
        //
    }

    /**
     * Фильтр по активности
     *
     * @return FaqFilter
     */
    public function status($value)
    {
        if (is_array($value)) {
            return $this->whereIn('status', $value);
        }

        return $this->where('status', $value);
    }

    /**
     * Фильтровать по категориям
     *
     * @return FaqFilter
     */
    public function categories($value)
    {
        if (is_array($value)) {
            return $this->whereIn('id_group', $value);
        }

        return $this->where('id_group', $value);
    }
}
