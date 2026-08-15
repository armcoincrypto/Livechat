<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Illuminate\Database\Eloquent\Builder;

class ReferralLogFilter extends ModelFilter
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
     * @return ReferralLogFilter
     */
    public function idUser($id)
    {
        return $this->where('id_user', '=', $id);
    }

    /**
     * Поиск по ID пользователя
     *
     * @return ReferralLogFilter
     */
    public function idReferral($id)
    {
        return $this->where('id_referral', '=', $id);
    }

    public function userName($value): ReferralLogFilter
    {
        return $this->whereHas('user_admin', function (Builder $query) use ($value) {
            $query->where('name', 'like', '%'.$value.'%');
        });
    }

    /**
     * Поиск по ID заявки
     *
     * @return ReferralLogFilter
     */
    public function idTask($value)
    {
        return $this->where('id_task', '=', $value);
    }

    /**
     * Поиск по ID (От)
     *
     * @return ReferralLogFilter
     */
    public function fromIdTask($id)
    {
        return $this->where('id_task', '>=', $id);
    }

    /**
     * Поиск по ID (Да)
     *
     * @return ReferralLogFilter
     */
    public function toIdTask($id)
    {
        return $this->where('id', '<=', $id);
    }

    /**
     * Фильтр по дате создания (От)
     *
     * @return ReferralLogFilter
     */
    public function fromCreatedAt($value)
    {
        return $this->where('created_at', '>=', $value);
    }

    /**
     * Фильтр по дате создания (До)
     *
     * @return ReferralLogFilter
     */
    public function toCreatedAt($value)
    {
        return $this->where('created_at', '<=', $value);
    }
}
