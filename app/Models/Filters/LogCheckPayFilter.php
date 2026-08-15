<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 19.06.2019
 * Time: 8:30
 */

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;

class LogCheckPayFilter extends ModelFilter
{
    /**
     * Установночный фильтр
     */
    public function setup()
    {
        //
    }

    /**
     * Фильтр по ID заявки
     *
     * @return LogCheckPayFilter
     */
    public function idOrder($id)
    {
        return $this->where('id_task', '=', $id);
    }

    /**
     * Фильтр по типу событий
     *
     * @return LogCheckPayFilter
     */
    public function eventType($ids)
    {
        return $this->whereIn('status', $ids);
    }

    /**
     * Фильтр по провайдеру
     *
     * @return LogCheckPayFilter
     */
    public function provider($provider)
    {
        return $this->where('provider', '=', $provider);
    }
}
