<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 09.07.2019
 * Time: 14:48
 */

namespace App\Models\Filters;

use App\Services\Verification\VerificationIdentifierVault;
use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class VerificationCardFilter extends ModelFilter
{
    /**
     * Фильтр по активности
     *
     * @return VerificationCardFilter
     */
    public function status($value)
    {
        if (is_array($value)) {
            return $this->whereIn('status', $value);
        }

        return $this->where('status', $value);
    }

    /**
     * Exact/lookup match via encrypted-compatible whereIdentifier.
     * Short values (≤4) match card_number_last4. Mid-string LIKE on plaintext retired.
     *
     * @return VerificationCardFilter
     */
    public function cardNumber($value)
    {
        $vault = app(VerificationIdentifierVault::class);
        $normalized = $vault->normalize((string) $value);
        if ($normalized === '') {
            return $this;
        }

        if (! array_key_exists('checkbox_card_number', $this->input) && mb_strlen($normalized) <= 4) {
            return $this->where('card_number_last4', $normalized);
        }

        return $this->whereIdentifier($normalized);
    }

    /**
     * Поиск по IP Адресу
     *
     * @return VerificationCardFilter
     */
    public function ipAddress($ip)
    {
        return $this->where('ip_address', $ip);
    }

    /**
     * Поиск по ID Пользователя
     *
     * @return VerificationCardFilter
     */
    public function idUser($ip)
    {
        return $this->where('id_user', $ip);
    }

    public function idOrder($value)
    {
        $type = iEXSetting('client_id_type_for_order');

        return $this->whereHas('tasks', function($q) use ($value, $type) {
            if ($type == 1) {
                $q->where('public_id', $value);
            } else {
                $q->where('id', $value);
            }
        });
    }

    /**
     * Поиск по Валютам
     *
     * @return VerificationCardFilter
     */
    public function currencies($value)
    {
        return $this->whereIn('id_currency', $value);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return VerificationCardFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): VerificationCardFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
