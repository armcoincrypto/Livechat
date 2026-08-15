<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class UserFilter extends ModelFilter
{
    /**
     * Поиск по ID
     *
     * @return UserFilter
     */
    public function id($id)
    {
        return $this->where('id', '=', $id);
    }

    /**
     * Доступ к админ панели
     */
    public function allowAdmin()
    {
        return $this->permission('allow_admin');
    }

    /**
     * Фильтровать по ?
     *
     * @return UserFilter
     */
    public function filterBy($filter)
    {
        switch ($filter) {
            case 'referral':
                return $this->select(['users.*'])
                    ->join('user_balance', 'users.id', '=', 'user_balance.id_user')
                    ->orderBy('user_balance.balance', 'desc');
            case 'created':
                return $this->orderBy('created_at', 'desc');
            case 'last_activity':
                return $this->orderBy('last_activity_at', 'desc');
        }
    }

    /**
     * Проверка в списке ролей
     *
     * @return mixed
     */
    public function group($group)
    {
        // В случае, если переданы несколько значений
        if (is_array($group)) {
            return $this->role($group);
        }

        return $this->roles(urldecode($group));
    }

    /**
     * Проверяем в списке забаненных
     */
    public function isBan()
    {
        return $this->onlyBanned();
    }

    /**
     * Автовыплата бонусов
     */
    public function isWithdrawal()
    {
        return $this->where('auto_withdrawal', '=', 1);
    }

    /**
     * Фильтр по провайдерам
     *
     * @param  string  $value
     * @return UserFilter
     */
    public function provider($value)
    {
        if (is_array($value)) {
            return $this->whereIn('provider', $value);
        }

        return $this->where('provider', '=', $value);
    }

    /**
     * Уникальные пользователи
     */
    public function isUniqueUser()
    {
        return $this->where('is_unique_user', '=', 1);
    }

    /**
     * Верифицированные пользователи
     */
    public function isVerified()
    {
        return $this->whereNotNull('email_verified_at');
    }

    /**
     * Отключенные пользователи
     */
    public function isDisabled()
    {
        return $this->where('deactivation', '=', 1);
    }

    /**
     * Клиенты которые запрещено создавать заявки
     */
    public function isOrder()
    {
        return $this->where('is_order', '=', 1);
    }

    /**
     * Клиенты которые авторизовались используя сторонний сервис
     */
    public function isProvider()
    {
        return $this->whereNotNull('provider');
    }

    /**
     * Поиск по имени
     *
     * @return UserFilter
     */
    public function name($name)
    {
        return array_key_exists('checkbox_full_name', $this->input) ?
            $this->where('name', $name) :
            $this->where('name', 'like', '%'.$name.'%');
    }

    /**
     * Поиск по IP
     *
     * @return UserFilter
     */
    public function ipAddress($ip)
    {
        return $this->where('ip_address', '=', $ip);
    }

    /**
     * Фильтр по рефералам
     *
     * @return UserFilter|\Illuminate\Database\Eloquent\Builder
     */
    public function referral($referral)
    {
        return $this->whereHas('fromReferral.referral_link', function ($query) use ($referral) {
            $query->where('code', '=', $referral);
        });
    }

    /**
     * Фильтрация пользователей по реферальной программе
     *
     * @param int|array<int> $referral
     * @return $this
     */
    public function referralProgram(int|array $referral): static
    {
        return $this->whereHas('referrals', function ($query) use ($referral) {
            is_array($referral)
                ? $query->whereIn('referral_program_id', $referral)
                : $query->where('referral_program_id', $referral);
        });
    }

    /**
     * Поиск по Email
     *
     * @return UserFilter
     */
    public function email($email)
    {
        $array_mail = explode(',', preg_replace('/\s+/', '', $email));

        // Если указано несколько e-mail адресов
        if (count($array_mail) <= 1) {
            return array_key_exists('checkbox_full_email', $this->input) ?
                $this->where('email', $email) :
                $this->where('email', 'like', '%'.$email.'%');
        }

        return $this->whereIn('email', $array_mail);
    }

    /**
     * Дата регистрации (От)
     *
     * @return UserFilter
     */
    public function fromDateRegister($date)
    {
        return $this->where('created_at', '>=', $date);
    }

    /**
     * Дата регистрации (До)
     *
     * @return UserFilter
     */
    public function toDateRegister($date)
    {
        return $this->where('created_at', '<=', $date);
    }

    /**
     * Дата последней активности (От)
     *
     * @return UserFilter
     */
    public function fromLastActivity($date)
    {
        return $this->where('last_activity_at', '>=', $date);
    }

    /**
     * Дата последней активности (До)
     *
     * @return UserFilter
     */
    public function toLastActivity($date)
    {
        return $this->where('last_activity_at', '<=', $date);
    }

    /**
     * Дата последнего входа в систему (От)
     *
     * @return UserFilter
     */
    public function fromLastLoginAt($date)
    {
        return $this->where('last_login_at', '>=', $date);
    }

    /**
     * Дата последнего входа в систему (До)
     *
     * @return UserFilter
     */
    public function toLastLoginAt($date)
    {
        return $this->where('last_login_at', '<=', $date);
    }

    /**
     * Дата последнего выхода из системы (От)
     *
     * @return UserFilter
     */
    public function fromLastLogoutAt($date)
    {
        return $this->where('last_logout_at', '>=', $date);
    }

    /**
     * Дата последнего выхода из системы (От)
     *
     * @return UserFilter
     */
    public function toLastLogoutAt($date)
    {
        return $this->where('last_logout_at', '<=', $date);
    }

    /**
     * Количество заявок (От)
     *
     * @return UserFilter
     */
    public function fromOrderNum($order)
    {
        return $this->where('order_num', '>=', $order);
    }

    /**
     * Количество заявок (До)
     *
     * @return UserFilter
     */
    public function toOrderNum($order)
    {
        return $this->where('order_num', '<=', $order);
    }

    /**
     * Реферальные бонусы (От)
     *
     * @return UserFilter|\Illuminate\Database\Eloquent\Builder
     */
    public function fromReferralNum($num)
    {
        return $this->whereHas('user_balance', function ($query) use ($num) {
            $query->where('balance', '>=', $num);
        });
    }

    /**
     * Реферальные бонусы (До)
     *
     * @return UserFilter|\Illuminate\Database\Eloquent\Builder
     */
    public function toReferralNum($num)
    {
        return $this->whereHas('user_balance', function ($query) use ($num) {
            $query->where('balance', '<=', $num);
        });
    }

    /**
     * Сортировка по ID
     *
     * @return UserFilter
     */
    public function sortingIds($id)
    {
        return $this->orderBy('id', $id);
    }

    /**
     * Сортировка по Email
     *
     * @return UserFilter
     */
    public function sortingEmail($email)
    {
        return $this->orderBy('email', $email);
    }

    /**
     * Сортировка по Заявкам
     *
     * @return UserFilter
     */
    public function sortingOrderNum($order_num)
    {
        return $this->orderBy('order_num', $order_num);
    }

    /**
     * Сортировка по дате регистрации
     *
     * @return UserFilter
     */
    public function sortingCreatedAt($created_at)
    {
        return $this->orderBy('created_at', $created_at);
    }

    /**
     * Сортировка по дате последней активности
     *
     * @return UserFilter
     */
    public function sortingLastActivity($last_activity)
    {
        return $this->orderBy('last_activity_at', $last_activity);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return UserFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): UserFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
