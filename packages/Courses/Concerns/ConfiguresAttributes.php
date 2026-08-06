<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Concerns;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Models\DirectionExchangeMode;
use App\Services\Rates\CanonicalDirectionEligibility;
use App\Services\Rates\CurrencyPublicRetirement;
use Illuminate\Support\Carbon;
use Jenssegers\Agent\Facades\Agent;

/**
 * Trait ConfiguresAttributes
 *
 * Назначение:
 * - Собрать структуру направлений: [id_currency1 => [id_currency2 => id_currency2, ...]]
 * - Собрать сортировку направлений: ["id_currency1-id_currency2" => sorting_2]
 *
 * C3-A: public catalog adjacency must not advertise directions that fail the
 * shared CanonicalDirectionEligibility catalog prefilter (public duplicates,
 * non-positive course_value). Quote/order/XML already honor the same policy.
 *
 * Производительность:
 * - Без N+1 по направлениям: используем chunkById() по DirectionExchange.
 * - Максимум фильтров применяем на уровне SQL.
 * - Устройство и текущее время вычисляются один раз.
 *
 * Ограничения текущей модели данных:
 * - languages/device хранятся CSV-строкой, поэтому точная фильтрация выполняется в PHP
 *   (но уже после сужения выборки SQL).
 */
trait ConfiguresAttributes
{
    /**
     * @var array<string,int> ключ "id_currency1-id_currency2" => sorting_2
     */
    protected array $directionSorting = [];

    /**
     * @var array<int, array<int,int>> id_currency1 => [id_currency2 => id_currency2]
     */
    protected array $directionRatesAll = [];

    /**
     * Количество направлений, добавленных в итоговую структуру.
     */
    private int $countUpdateData = 0;

    /**
     * Количество валют "отдаю", у которых есть хотя бы одно подходящее направление.
     */
    private int $countTotalUpdate = 0;

    /**
     * Построить структуру направлений.
     *
     * @return array<int, array<int,int>>
     */
    public function configureDirectionRates(): array
    {
        $this->directionRatesAll = [];
        $this->directionSorting = [];
        $this->countUpdateData = 0;
        $this->countTotalUpdate = 0;

        // 1) Определяем фильтры один раз
        $nowHm = Carbon::now()->format('H:i');
        $locale = app()->getLocale();
        $device = $this->resolveUserDevice();

        // 2) Есть ли активные режимы (1 запрос)
        $hasModes = DirectionExchangeMode::query()->where('status', 1)->exists();

        // 3) Список активных валют (в памяти, но это обычно немного и это сильно ускоряет проверки)
        //    Если валют очень много — можно заменить на join/whereHas, но чаще это нормально.
        $activeCurrencyIds = Currency::query()
            ->where('status', 0)
            ->pluck('id')
            ->map(static fn ($v) => (int) $v)
            ->all();

        $activeCurrencyMap = array_fill_keys($activeCurrencyIds, true);

        // 4) Основная выборка направлений — chunkById (без N+1)
        DirectionExchange::query()
            ->select([
                'id',
                'id_currency1',
                'id_currency2',
                'sorting_2',
                'status',
                'is_error_rate',
                'is_enabled_exchange',
                'from_on_time',
                'to_on_time',
                'is_hidden_not_locale',
                'languages',
                'is_hidden_not_device',
                'device',
                'course_value',
            ])
            ->where('status', 1)
            ->where('is_error_rate', 0)
            ->when($hasModes, function ($q) {
                $q->whereHas('direction_modes', fn ($m) => $m->where('status', 1));
            })
            ->chunkById(2000, function ($rows) use ($activeCurrencyMap, $nowHm, $locale, $device) {
                foreach ($rows as $direction) {
                    $directionId = (int) ($direction->id ?? 0);

                    // C3-A: shared catalog prefilter (PublicDuplicateExclusion + course_value > 0)
                    if (!CanonicalDirectionEligibility::passesPublicCatalogPrefilter(
                        $directionId,
                        $direction->course_value ?? null,
                    )) {
                        continue;
                    }

                    $c1 = (int) $direction->id_currency1;
                    $c2 = (int) $direction->id_currency2;

                    // C3-B: owner-retired currencies (TUSDTRC20, DAI) never enter public adjacency.
                    if (CurrencyPublicRetirement::isCurrencyIdRetired($c1)
                        || CurrencyPublicRetirement::isCurrencyIdRetired($c2)) {
                        continue;
                    }

                    // Валюты должны быть активны
                    if (!isset($activeCurrencyMap[$c1], $activeCurrencyMap[$c2])) {
                        continue;
                    }

                    // Время работы направления
                    if ((int) ($direction->is_enabled_exchange ?? 0) === 1) {
                        $from = (string) ($direction->from_on_time ?? '');
                        $to = (string) ($direction->to_on_time ?? '');

                        if ($from !== '' && $to !== '') {
                            // Простой сценарий без пересечения полуночи
                            if ($nowHm < $from || $nowHm > $to) {
                                continue;
                            }
                        }
                    }

                    // Локаль
                    if ((int) ($direction->is_hidden_not_locale ?? 0) === 1) {
                        $langs = trim((string) ($direction->languages ?? ''));
                        if ($langs !== '') {
                            $allowed = array_filter(array_map('trim', explode(',', $langs)));
                            if (!in_array($locale, $allowed, true)) {
                                continue;
                            }
                        }
                    }

                    // Устройство
                    if ((int) ($direction->is_hidden_not_device ?? 0) === 1) {
                        $devs = trim((string) ($direction->device ?? ''));
                        if ($devs !== '') {
                            $allowed = array_filter(array_map('trim', explode(',', $devs)));
                            if (!in_array($device, $allowed, true)) {
                                continue;
                            }
                        }
                    }

                    // Добавляем в итог
                    $this->directionSorting["{$c1}-{$c2}"] = (int) $direction->sorting_2;
                    $this->directionRatesAll[$c1][$c2] = $c2;
                    $this->countUpdateData++;
                }
            });

        // 5) Считаем количество валют "отдаю", у которых есть направления
        $this->countTotalUpdate = count($this->directionRatesAll);

        return $this->directionRatesAll;
    }

    /**
     * Получить сортировку направлений.
     *
     * @return array<string,int>
     */
    public function getDirectionSorting(): array
    {
        return $this->directionSorting;
    }

    /**
     * Получить количество добавленных направлений.
     */
    public function getCountUpdateData(): int
    {
        return $this->countUpdateData;
    }

    /**
     * Получить количество валют "отдаю" с найденными направлениями.
     */
    public function getCountTotalUpdate(): int
    {
        return $this->countTotalUpdate;
    }

    /**
     * Определить устройство один раз на весь расчёт.
     *
     * @return string 'desktop'|'mobile'|'tablet'
     */
    private function resolveUserDevice(): string
    {
        return match (true) {
            Agent::isTablet() => 'tablet',
            Agent::isMobile() => 'mobile',
            default => 'desktop',
        };
    }
}
