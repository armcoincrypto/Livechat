<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Export\Formats;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Models\ExportRatesFile;
use App\Services\Calculator\CalculatorMathService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Spatie\ArrayToXml\ArrayToXml;
use Throwable;

/**
 * BestChange XML export.
 *
 * ВАЖНО: алгоритм расчёта курса приведён к логике XMLExportFormat:
 * - одинаковая инверсия (isInverted) для городов
 * - одинаковая обработка profit / profit_s при инверсии
 * - одинаковое применение file_rate_source (floating|fix) через CalculatorMathService
 *
 * Отличия остаются только в структуре XML (schema, frommin/frommax, labels как теги, step/floating).
 */
class BestChangeXMLExportFormat extends AbstractExportFormat
{
    /**
     * Собирает данные направлений обмена и формирует XML.
     */
    public function assemble(Builder $builder, ExportRatesFile $config): string
    {
        // Фиксируем "now" один раз для проверки окон allow_export и т.п.
        $this->setExportNow(Carbon::now());

        $items = [];
        $this->countTotalUpdate = (int) $builder->count();

        $builder->chunkById(self::DEFAULT_CHUNK_SIZE, function ($rates) use (&$items, $config): void {
            foreach ($rates as $rate) {
                try {
                    if (!$this->shouldExportRate($rate, (int) ($config->is_offline_operator ?? 0))) {
                        continue;
                    }

                    $currencyIn  = $rate->currency1;
                    $currencyOut = $rate->currency2;

                    if (!$currencyIn || !$currencyOut) {
                        continue;
                    }

                    $typeNumberFormat = (int) ($config->type_number_format ?? 0);
                    $decimalFormat    = (int) ($config->number_format ?? 2);

                    $decimalIn  = $typeNumberFormat ? $decimalFormat : (int) ($currencyIn->number_format ?? 2);
                    $decimalOut = $typeNumberFormat ? $decimalFormat : (int) ($currencyOut->number_format ?? 2);

                    $mathValue = new CalculatorMathService((string) ($rate->course_value ?? '0'));

                    // Активные города: без N+1 (через AbstractExportFormat)
                    $activeCities = $this->getActiveCities($rate);

                    if ($activeCities->count() > 0) {
                        foreach ($activeCities as $city) {
                            try {
                                $item = $this->processCityRate(
                                    $rate,
                                    $city,
                                    $currencyIn,
                                    $currencyOut,
                                    $mathValue,
                                    $config,
                                    $decimalIn,
                                    $decimalOut
                                );

                                if ($item !== null) {
                                    $items[] = $item;
                                }
                            } catch (Throwable $e) {
                                Log::error('Ошибка при обработке города (BestChange XML)', [
                                    'direction_exchange_id' => $rate->id ?? null,
                                    'message' => $e->getMessage(),
                                ]);
                            }
                        }

                        $this->countUpdateData++;
                        continue;
                    }

                    // Без городов — считаем так же, как XMLExportFormat: CalculatorMathService + file_rate_source
                    $mathValue->setCurrentValue((string) ($rate->course_value ?? '0'));
                    $this->applyFileRateSourceSimple($mathValue, $rate);

                    $courseValues = $this->calculateCourseValues($mathValue->getSumma(), $decimalIn, $decimalOut);
                    if ($courseValues === null) {
                        continue;
                    }

                    $items[] = $this->buildItemData($rate, $currencyIn, $currencyOut, $courseValues, $config);
                    $this->countUpdateData++;
                } catch (Throwable $e) {
                    Log::error('Ошибка обработки направления BestChange XML', [
                        'direction_exchange_id' => $rate->id ?? null,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        });

        return ArrayToXml::convert(
            ['item' => array_values(array_filter($items))],
            [
                'rootElementName' => 'rates',
                '_attributes' => [
                    'version' => '1',
                    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    'xsi:noNamespaceSchemaLocation' => 'https://docs.bestchange.biz/schema/1.1.xsd',
                ],
            ]
        );
    }

    /**
     * Обрабатывает расчёт курса с учётом параметров города.
     */
    private function processCityRate(
        DirectionExchange $rate,
        mixed $city,
        Currency $currencyIn,
        Currency $currencyOut,
        CalculatorMathService $mathValue,
        ExportRatesFile $config,
        int $decimalIn,
        int $decimalOut
    ): ?array {
        $mathValue->setCurrentValue((string) ($rate->course_value ?? '0'));
        $initialRate = $this->toBcString((string) $mathValue->getSumma(), 18);

        // Некорректный/нулевой курс — пропускаем направление
        if (bccomp($initialRate, '0', 18) !== 1) {
            return null;
        }

        $this->isInverted = bccomp($initialRate, '1', 18) === -1;

        if ($this->isInverted) {
            $mathValue->setSumma(bcdiv('1', $initialRate, 18));
        }

        // add_comm
        if (isset($city->add_comm) && $this->isValidMathExpression($city->add_comm)) {
            $mathValue->calculate($this->sanitizeFlexibleMathExpression((string) $city->add_comm));
        }

        // profit (учёт isInverted)
        if (!empty($city->profit)) {
            if ($this->isInverted) {
                $mathValue->addPercentage($city->profit);
            } else {
                $mathValue->subtractPercentage($city->profit);
            }
        }

        // profit_s (учёт isInverted)
        if (!empty($city->profit_s)) {
            $fixedFee = $this->toBcString((string) $this->sanitizeNumber($city->profit_s), 18);
            $current  = $this->toBcString((string) $mathValue->getSumma(), 18);

            if (bccomp($current, $fixedFee, 18) === 1) {
                if ($this->isInverted) {
                    $mathValue->addFixedValue($fixedFee);
                } else {
                    $mathValue->subtractFixedValue($fixedFee);
                }
            }
        }

        // Применяем file_rate_source (floating|fix)
        $this->applyFileRateSourceSimple($mathValue, $rate);

        // Возвращаем обратно, если была инверсия
        if ($this->isInverted) {
            $current = $this->toBcString((string) $mathValue->getSumma(), 18);
            if (bccomp($current, '0', 18) !== 1) {
                return null;
            }
            $mathValue->setSumma(bcdiv('1', $current, 18));
        }

        $courseValues = $this->calculateCourseValues($mathValue->getSumma(), $decimalIn, $decimalOut);
        if ($courseValues === null) {
            return null;
        }

        $item = $this->buildItemData($rate, $currencyIn, $currencyOut, $courseValues, $config);

        // BestChange: city + labels как пустые теги
        $item['city'] = $city->city?->designation_xml ?? null;

        $labels = explode(',', (string) ($city->param ?? ($rate->export_label_param ?? 'manual')));
        $labels = array_values(array_filter(array_map('trim', $labels), static fn ($v) => $v !== ''));

        // Schema 1.1 boolean flags must be true/false — empty <manual></manual> is invalid.
        $item += array_fill_keys($labels, 'true');

        // BestChange: min/max (schema 1.1 frommin/frommax + classic minamount/maxamount).
        // bestchange.ru cabinet still reads legacy minamount/maxamount; frommin alone
        // is shown as «нет мин. суммы» / «нет макс. суммы».
        if (!empty($city->min_price)) {
            $formatted = iex_number_format($city->min_price, $currencyIn->number_format ?? 2);
            $item['frommin'] = $formatted;
            // Place legacy fields after labels so ArrayToXml order matches XSD legacy group.
            $item['minamount'] = $formatted;
        }

        if (!empty($city->max_price)) {
            $formatted = iex_number_format($city->max_price, $currencyIn->number_format ?? 2);
            $item['frommax'] = $formatted;
            $item['maxamount'] = $formatted;
        }

        return $item;
    }

    /**
     * Формирует элемент данных направления (структура BestChange).
     */
    private function buildItemData(
        DirectionExchange $rate,
        Currency $currencyIn,
        Currency $currencyOut,
        array $courseValues,
        ExportRatesFile $config
    ): array {
        // step: выводим только если есть хотя бы один НЕнулевой шаг.
        // Если в базе все шаги = 0 — step не выводим вообще.
        $stepNodes = $rate->direction_exchange_percentage_amount
            ->sortBy('from_amount')
            ->values()
            ->map(function ($step) {
                $percentage = trim((string) $step->percentage);

                if ($percentage === '') {
                    return null;
                }

                $isPercentage = str_ends_with($percentage, '%');
                $percentageValue = $isPercentage ? rtrim($percentage, '%') : $percentage;

                $percentageValue = str_replace(' ', '', $percentageValue);
                $percentageValue = ltrim($percentageValue, '+');

                $isNegative = str_starts_with($percentageValue, '-');
                $numericValue = $isNegative ? ltrim($percentageValue, '-') : $percentageValue;

                $numericValueBc = $this->toBcString((string) $numericValue, 18);
                $isZero = bccomp($numericValueBc, '0', 18) === 0;

                $node = [
                    '_attributes' => [
                        'frommin' => $step->from_amount,
                        'frommax' => $step->to_amount,
                    ],
                    'tofee' => $isPercentage
                        ? ['_attributes' => ['type' => '%'], '_value' => (string) $numericValue]
                        : (string) $numericValue,
                ];

                return [
                    'is_zero' => $isZero,
                    'node' => $node,
                ];
            })
            ->filter()
            ->values();

        $hasNonZeroStep = $stepNodes->contains(static fn ($x) => isset($x['is_zero']) && $x['is_zero'] === false);

        $stepsData = $hasNonZeroStep
            ? $stepNodes->map(static fn ($x) => $x['node'])->toArray()
            : [];

        $labels = explode(',', (string) ($rate->export_label_param ?? 'manual'));
        $labels = array_values(array_filter(array_map('trim', $labels), static fn ($v) => $v !== ''));
        // Schema 1.1 boolean flags (manual/reg/…) require true/false, not empty tags.
        $params = array_fill_keys($labels, 'true');

        // Если есть step, а min/max не заполнены — берём базу из step.
        $baseFromMin = $this->isPositiveAmount($rate->min_price1) ? $rate->min_price1 : null;
        $baseFromMax = $this->isPositiveAmount($rate->max_price1) ? $rate->max_price1 : null;

        if ($stepsData !== [] && ($baseFromMin === null || $baseFromMax === null)) {
            $mins = [];
            $maxs = [];

            foreach ($stepsData as $node) {
                $mins[] = $node['_attributes']['frommin'] ?? null;
                $maxs[] = $node['_attributes']['frommax'] ?? null;
            }

            $mins = array_values(array_filter($mins, static fn ($v) => $v !== null && $v !== ''));
            $maxs = array_values(array_filter($maxs, static fn ($v) => $v !== null && $v !== ''));

            if ($baseFromMin === null && $mins !== []) {
                $baseFromMin = min($mins);
            }
            if ($baseFromMax === null && $maxs !== []) {
                $baseFromMax = max($maxs);
            }
        }

        $floatingMinutes = (float) ($rate->label_floating_minutes ?? 0);
        $floatingPercent = (float) ($rate->label_floating_percent ?? 0);

        $floating = ($floatingMinutes > 0 || $floatingPercent > 0)
            ? [
                '_attributes' => [
                    'minutes' => (string) $floatingMinutes,
                    'percent' => (string) $floatingPercent,
                ],
            ]
            : null;

        $delay = (float) ($rate->label_delay ?? 0);
        $delayTag = $delay > 0 ? ['delay' => (string) $delay] : null;

        $item = [
            'from'   => $this->exportPublicCode((string) ($currencyIn->designation_xml ?: '')),
            'to'     => $this->exportPublicCode((string) ($currencyOut->designation_xml ?: '')),
            'in'     => $courseValues['in'] ?: '0',
            'out'    => $courseValues['out'] ?: '0',
            'amount' => $this->getReserveAmount($rate, $currencyOut),
        ];

        if (!isset($item['amount']) || $item['amount'] === null) {
            unset($item['amount']);
        }

        // Schema 1.1 required limits (from-side / customer send amounts).
        $legacyMin = null;
        $legacyMax = null;
        if ($baseFromMin !== null) {
            $legacyMin = $this->formatAmount($baseFromMin, $currencyIn);
            $item['frommin'] = $legacyMin;
        }
        if ($baseFromMax !== null) {
            $legacyMax = $this->formatAmount($baseFromMax, $currencyIn);
            $item['frommax'] = $legacyMax;
        }

        // Fees (как в твоей исходной логике, через float — оставляем без изменений по стилю BestChange)
        $fromFee = array_values(array_filter([
            ($percent = (float) (($config->in_type_fromfee ?? 0) === 0 ? $rate->oth_comm_percent : $rate->pay_comm_percent)) > 0
                ? ['_attributes' => ['type' => '%'], '_value' => (string) $percent]
                : null,
            ($fixed = (float) (($config->in_type_fromfee ?? 0) === 0 ? $rate->oth_comm_currency : $rate->pay_comm_currency)) > 0
                ? (string) $fixed
                : null,
        ], static fn ($v) => $v !== null));

        if (!empty($fromFee)) {
            $item['fromfee'] = $fromFee;
        }

        $toFee = array_values(array_filter([
            ($percent = (float) (($config->in_type_tofee ?? 0) === 0 ? $rate->oth_comm2_percent : $rate->pay_comm2_percent)) > 0
                ? ['_attributes' => ['type' => '%'], '_value' => (string) $percent]
                : null,
            ($fixed = (float) (($config->in_type_tofee ?? 0) === 0 ? $rate->oth_comm2_currency : $rate->pay_comm2_currency)) > 0
                ? (string) $fixed
                : null,
        ], static fn ($v) => $v !== null));

        if (!empty($toFee)) {
            $item['tofee'] = $toFee;
        }

        if ($stepsData !== []) {
            $item['step'] = $stepsData;
        }

        if ($delayTag !== null) {
            $item['delay'] = $delayTag['delay'];
        }

        if ($floating !== null) {
            $item['floating'] = $floating;
        }

        foreach ($params as $k => $v) {
            $item[$k] = $v;
        }

        // Classic BestChange.ru fields (schema 1.0 / XSD 1.1 legacy group).
        // Emit AFTER params so order matches RateItem sequence: … params → legacy.
        // Without these, the cabinet shows «нет мин. суммы» / «нет макс. суммы»
        // even when frommin/frommax are present.
        if ($legacyMin !== null) {
            $item['minamount'] = $legacyMin;
        }
        if ($legacyMax !== null) {
            $item['maxamount'] = $legacyMax;
        }

        return $item;
    }

    private function isPositiveAmount(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $s = trim((string) $value);
        if ($s === '') {
            return false;
        }

        $bc = $this->toBcString($s, 18);

        return bccomp($bc, '0', 18) === 1;
    }

    public function getExtension(): string
    {
        return 'xml';
    }
}
