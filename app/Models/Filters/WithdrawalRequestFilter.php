<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 22.06.2019
 * Time: 8:54
 */

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Illuminate\Database\Eloquent\Builder;

class WithdrawalRequestFilter extends ModelFilter
{
    /**
     * Фильтр по ID пользователя
     *
     * @return WithdrawalRequestFilter
     */
    public function idUser($value)
    {
        return $this->where('id_user', '=', $value);
    }

    /**
     * Фильтр по Email адресу клиента
     *
     * @return WithdrawalRequestFilter
     */
    public function emailAddress($value)
    {
        return $this->whereHas('user', function (Builder $query) use ($value) {
            array_key_exists('checkbox_full_email', $this->input) ?
                $query->where('email', $value) :
                $query->where('email', 'like', '%'.$value.'%');
        });
    }

    /**
     * Фильтр по имени клиента
     *
     * @return WithdrawalRequestFilter
     */
    public function userName($value)
    {
        return $this->whereHas('user', function (Builder $query) use ($value) {
            array_key_exists('checkbox_full_name', $this->input) ?
                $query->where('name', $value) :
                $query->where('name', 'like', '%'.$value.'%');
        });
    }

    /**
     * Фильтр по дате создания (От)
     *
     * @return WithdrawalRequestFilter
     */
    public function fromCreatedAt($value)
    {
        return $this->where('created_at', '>=', $value);
    }

    /**
     * Фильтр по дате создания (До)
     *
     * @return WithdrawalRequestFilter
     */
    public function toCreatedAt($value)
    {
        return $this->where('created_at', '<=', $value);
    }

    /**
     * Фильтр статусу
     *
     * @return WithdrawalRequestFilter
     */
    public function status($value)
    {
        return $this->whereIn('status', $value);
    }

    /**
     * Фильтр по номеру счета
     *
     * @return WithdrawalRequestFilter
     */
    public function score($value)
    {
        return array_key_exists('checkbox_score', $this->input) ?
            $this->where('score', $value) :
            $this->where('score', 'like', '%'.$value.'%');
    }

    /**
     * Фильтр по валютам
     *
     * @return WithdrawalRequestFilter
     */
    public function currencies($ids)
    {
        return $this->whereIn('id_currency', $ids);
    }
}
