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
 * XMLExportFormat
 *
 * Экспорт направлений в XML (внутренний формат).
 */
class XMLExportFormat extends AbstractExportFormat
{
    /**
     * Собирает данные направлений обмена и формирует XML.
     */
    public function assemble(Builder $builder, ExportRatesFile $config): string
    {
        // Фиксируем "now" один раз для всего прохода (окна времени allow_export и т.п.)
        $this->setExportNow(Carbon::now());

        $items = [];
        $this->countTotalUpdate = (int) $builder->count();

        $builder->chunkById(self::DEFAULT_CHUNK_SIZE, function ($rates) use (&$items, $config): void {
            foreach ($rates as $rate) {
                try {
                    // Проверка возможности экспорта направления
                    if (!$this->shouldExportRate($rate, (int) ($config->is_offline_operator ?? 0))) {
                        continue;
                    }

                    $currencyIn = $rate->currency1;
                    $currencyOut = $rate->currency2;

                    if (!$currencyIn || !$currencyOut) {
                        continue;
                    }

                    $typeNumberFormat = (int) ($config->type_number_format ?? 0);
                    $decimalFormat = (int) ($config->number_format ?? 2);

                    $decimalIn = $typeNumberFormat ? $decimalFormat : (int) ($currencyIn->number_format ?? 2);
                    $decimalOut = $typeNumberFormat ? $decimalFormat : (int) ($currencyOut->number_format ?? 2);

                    $mathValue = new CalculatorMathService((string) ($rate->course_value ?? '0'));

                    // Активные города (через базовый метод; без N+1, есть fallback)
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
                                Log::error('Ошибка при обработке города (XMLExportFormat)', [
                                    'direction_exchange_id' => $rate->id ?? null,
                                    'message' => $e->getMessage(),
                                ]);
                            }
                        }

                        $this->countUpdateData++;
                        continue;
                    }

                    // Без городов: применяем file_rate_source по простому принципу (как applyGroupFee)
                    $mathValue->setCurrentValue((string) ($rate->course_value ?? '0'));
                    $this->applyFileRateSourceSimple($mathValue, $rate);

                    $courseValues = $this->calculateCourseValues($mathValue->getSumma(), $decimalIn, $decimalOut);
                    if ($courseValues === null) {
                        continue;
                    }

                    $items[] = $this->buildItemData($rate, $currencyIn, $currencyOut, $courseValues, $config);
                    $this->countUpdateData++;
                } catch (Throwable $e) {
                    Log::error('Ошибка обработки направления экспорта XML', [
                        'direction_exchange_id' => $rate->id ?? null,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        });

        return ArrayToXml::convert(
            ['item' => array_values(array_filter($items))],
            ['rootElementName' => 'rates']
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

        // profit
        if (!empty($city->profit)) {
            if ($this->isInverted) {
                $mathValue->addPercentage($city->profit);
            } else {
                $mathValue->subtractPercentage($city->profit);
            }
        }

        // profit_s
        if (!empty($city->profit_s)) {
            $fixedFee = $this->toBcString((string) $this->sanitizeNumber($city->profit_s), 18);
            $current = $this->toBcString((string) $mathValue->getSumma(), 18);

            if (bccomp($current, $fixedFee, 18) === 1) {
                if ($this->isInverted) {
                    $mathValue->addFixedValue($fixedFee);
                } else {
                    $mathValue->subtractFixedValue($fixedFee);
                }
            }
        }

        // По принципу applyGroupFee — если выбран file_rate_source, применяем к текущему значению
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

        // Город (объектная структура)
        $item['city'] = $city->city?->designation_xml ?? null;

        $item['param'] = isset($city->param) && trim((string) $city->param) !== ''
            ? (string) $city->param
            : (string) ($rate->export_label_param ?? 'manual');

        if (!empty($city->min_price)) {
            $item['minamount'] = iex_number_format($city->min_price, $currencyIn->number_format ?? 2);
        }

        if (!empty($city->max_price)) {
            $item['maxamount'] = iex_number_format($city->max_price, $currencyIn->number_format ?? 2);
        }

        return $item;
    }

    /**
     * Формирует элемент данных направления обмена.
     */
    private function buildItemData(
        DirectionExchange $rate,
        Currency $currencyIn,
        Currency $currencyOut,
        array $courseValues,
        ExportRatesFile $config
    ): array {
        return array_filter([
            'from'      => $this->exportPublicCode((string) ($currencyIn->designation_xml ?: '')),
            'to'        => $this->exportPublicCode((string) ($currencyOut->designation_xml ?: '')),
            'in'        => $courseValues['in'] ?: '0',
            'out'       => $courseValues['out'] ?: '0',
            'amount'    => $this->getReserveAmount($rate, $currencyOut),
            'minamount' => $this->formatAmount($rate->min_price1, $currencyIn),
            'maxamount' => $this->formatAmount($rate->max_price1, $currencyIn),
            'fromfee'   => $this->exportFromFee($rate, $currencyIn, (int) ($config->in_type_fromfee ?? 0)) ?: null,
            'tofee'     => $this->exportToFee($rate, $currencyOut, (int) ($config->in_type_tofee ?? 0)) ?: null,
            ...(empty($rate->hidden_export_label_param) || (int) $rate->hidden_export_label_param === 0
                ? ['param' => $rate->export_label_param ?? 'manual']
                : (isset($rate->export_label_param) ? ['param' => $rate->export_label_param] : [])),
        ]);
    }

    public function getExtension(): string
    {
        return 'xml';
    }
}
