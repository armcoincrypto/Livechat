<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;

class ReferralStatisticsFilter extends ModelFilter
{
    /**
     * Установночный фильтр
     */
    public function setup()
    {
        //
    }

    /**
     * Поиск по ID пользователя
     *
     * @return ReferralStatisticsFilter
     */
    public function idUser($id)
    {
        return $this->where('rs.id_user', '=', $id);
    }

    /**
     * Поиск по IP Адресу
     *
     * @return ReferralStatisticsFilter
     */
    public function ipAddress($value)
    {
        return $this->where('rs.ip_address', '=', $value);
    }
}
