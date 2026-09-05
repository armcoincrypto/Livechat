<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class DirectionExchangeFilter extends ModelFilter
{
    public function isErrorRate($value)
    {
        return $this->where('is_error_rate', '=', 1);
    }

    /**
     * Техническое название
     *
     * @param $value
     * @return DirectionExchangeFilter
     */
    public function techName($value): DirectionExchangeFilter
    {
        return $this->where('tech_name', 'like', '%'.$value.'%');
    }

    /**
     * Фильтр валюты (Отдаю)
     *
     * @param $value
     * @return DirectionExchangeFilter
     */
    public function currenciesIn($value): DirectionExchangeFilter
    {
        return $this->whereIn('id_currency1', $value);
    }

    /**
     * Фильтр валюты (Получаю)
     *
     * @param $value
     * @return DirectionExchangeFilter
     */
    public function currenciesOut($value): DirectionExchangeFilter
    {
        return $this->whereIn('id_currency2', $value);
    }

    /**
     * Фильтр по активности
     *
     * @param $value
     * @return DirectionExchangeFilter
     */
    public function status($value): DirectionExchangeFilter
    {
        return $this->whereIn('status', $value);
    }

    public function groups($values)
    {
        return $this->whereIn('direction_exchange.id', function ($query) use ($values) {
            $query->select('direction_exchange_id')
                ->from('direction_exchange_group_pivot')
                ->whereIn('group_id', $values);
        });
    }


    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return DirectionExchangeFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): DirectionExchangeFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }

    /**
     * Pricing family: bestchange|derived|zelle|manual
     */
    public function pricingOwner($value): DirectionExchangeFilter
    {
        $v = strtolower(trim((string) $value));
        return match ($v) {
            'bestchange' => $this->where('parser_source_name', 'BestChange'),
            'derived' => $this->where('parser_source_name', 'DERIVED_MARKET_BASELINE'),
            'zelle' => $this->where('parser_source_name', 'ZELLE_USDTTRC20_BENCHMARK'),
            'manual' => $this->whereIn('parser_source_name', ['Ручной курс', 'Manual']),
            default => $this,
        };
    }

    /**
     * Explicit profit = 0 (family-aware). Not "no margin".
     */
    public function zeroProfit($value): DirectionExchangeFilter
    {
        if (!(int) $value) {
            return $this;
        }

        return $this->where(function ($w) {
            $w->where(function ($b) {
                $b->where('parser_source_name', 'BestChange')
                    ->whereRaw('CAST(COALESCE(profit,0) AS DECIMAL(20,8)) = 0');
            })->orWhere(function ($b) {
                $b->where('parser_source_name', 'DERIVED_MARKET_BASELINE')
                    ->whereRaw('CAST(COALESCE(profit,0) AS DECIMAL(20,8)) = 0');
            })->orWhere(function ($b) {
                $b->where('parser_source_name', 'ZELLE_USDTTRC20_BENCHMARK')
                    ->whereRaw('CAST(COALESCE(floating_fee,0) AS DECIMAL(20,8)) = 0');
            })->orWhere(function ($b) {
                $b->whereIn('parser_source_name', ['Ручной курс', 'Manual'])
                    ->whereRaw('CAST(COALESCE(profit,0) AS DECIMAL(20,8)) = 0');
            });
        });
    }

    /**
     * BestChange pairs whose position differs from global setting.
     */
    public function positionOverride($value): DirectionExchangeFilter
    {
        if (!(int) $value) {
            return $this;
        }
        $global = (int) (new \App\Settings\BestChangeConfig())->position();

        return $this->where('parser_source_name', 'BestChange')
            ->whereHas('bestchange_directions', function ($b) use ($global) {
                $b->where('status', 1)
                    ->whereRaw('CAST(position_num AS UNSIGNED) <> ?', [$global]);
            });
    }
}
