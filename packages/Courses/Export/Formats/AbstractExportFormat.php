<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Export\Formats;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Models\ExportRatesFile;
use App\Services\Calculator\CalculatorMathService;
use App\Services\Reserves\ReserveLinkResolver;
use iEXPackages\Courses\Export\Concerns\ExportFormatHelpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class AbstractExportFormat
{
    use ExportFormatHelpers;

    protected int $countUpdateData = 0;
    protected int $countTotalUpdate = 0;

    /**
     * Флаг инверсии для текущего расчёта (используется в форматах экспорта).
     */
    protected bool $isInverted = false;

    /**
     * Дефолтный размер чанка для chunkById().
     */
    protected const int DEFAULT_CHUNK_SIZE = 500;

    /**
     * Кешируем resolver, чтобы не дергать container в цикле.
     */
    private ?ReserveLinkResolver $reserveResolver = null;

    abstract public function assemble(Builder $builder, ExportRatesFile $config): string;

    abstract public function getExtension(): string;

    public function getCountUpdateData(): int
    {
        return $this->countUpdateData;
    }

    public function getCountTotalUpdate(): int
    {
        return $this->countTotalUpdate;
    }

    /**
     * Получить ReserveLinkResolver с кешированием.
     */
    protected function getReserveResolver(): ReserveLinkResolver
    {
        if ($this->reserveResolver instanceof ReserveLinkResolver) {
            return $this->reserveResolver;
        }

        return $this->reserveResolver = app(ReserveLinkResolver::class);
    }

    /**
     * Applies BestChange floating-rate policy when file_rate_source is set.
     *
     * Release A revised: XML out = canonical floating_rate (floating_fee percent).
     * Never uses fix_fee. file_rate_source remains the historical eligibility gate
     * (must be floating|fix); mode selection is always FLOATING.
     */
    protected function applyFileRateSourceSimple(CalculatorMathService $mathValue, DirectionExchange $rate): void
    {
        $source = trim((string) ($rate->file_rate_source ?? ''));
        if ($source === '') {
            return;
        }

        if (!in_array($source, ['floating', 'fix'], true)) {
            return;
        }

        $before = (string) $mathValue->getSumma();
        try {
            $canonical = \App\Services\Rates\CanonicalDirectionRateCalculator::make()
                ->calculateForExport($rate, $before);

            if (!$canonical->eligible || bccomp($canonical->finalRate, '0', 18) !== 1) {
                // Keep pre-fee rate so the pair is not dropped from public XML.
                $mathValue->setSumma($before);
                Log::warning('file_rate_source_skipped_non_positive', [
                    'direction_exchange_id' => $rate->id ?? null,
                    'type' => $source,
                    'export_mode' => 'floating',
                    'before' => $before,
                    'after' => $canonical->finalRate,
                    'fee_percent' => $canonical->adjustmentValue,
                ]);

                return;
            }

            $mathValue->setSumma($canonical->finalRate);
        } catch (Throwable $e) {
            $mathValue->setSumma($before);
            Log::error('Ошибка при применении file_rate_source (canonical floating)', [
                'direction_exchange_id' => $rate->id ?? null,
                'source' => $source,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Получить активные города для направления:
     * - если relation уже загружена — используем без SQL
     * - иначе fallback на запрос (устойчивость при "неидеальном" builder)
     *
     * @return Collection<int, mixed>
     */
    protected function getActiveCities(DirectionExchange $rate): Collection
    {
        if (method_exists($rate, 'relationLoaded') && $rate->relationLoaded('direction_exchange_cities')) {
            /** @var Collection<int, mixed> $cities */
            $cities = $rate->direction_exchange_cities instanceof Collection
                ? $rate->direction_exchange_cities
                : collect($rate->direction_exchange_cities);

            return $cities;
        }

        return $rate->direction_exchange_cities()
            ->where('status', 1)
            ->get();
    }

    /**
     * Возвращает резерв как строку (без scientific notation).
     * Если резерв <= 0 — null (amount не выводим).
     *
     * Логика:
     * - type_reserve = 0 → берём эффективный резерв валюты (через корень цепочки)
     * - type_reserve = 1 → используем direction_reserve
     */
    protected function getReserveAmount(DirectionExchange $rate, Currency $currencyOut): ?string
    {
        try {
            if ((int) ($rate->type_reserve ?? 0) === 0) {
                $reserve = $currencyOut->reserve;

                if (!$reserve) {
                    return null;
                }

                $raw = $this->getReserveResolver()->getEffectiveSumma($reserve, 18);
            } else {
                $raw = $rate->direction_reserve ?? null;
            }

            if ($raw === null || $raw === '') {
                return null;
            }

            $amount = $this->toBcString((string) $raw, 18);

            if (!$this->isGreaterThanZero($amount)) {
                return null;
            }

            return $amount;
        } catch (Throwable $e) {
            Log::error('Ошибка получения резерва валюты', [
                'direction_exchange_id' => $rate->id ?? null,
                'currency_id' => $currencyOut->id ?? null,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Безопасное форматирование суммы обмена.
     */
    protected function formatAmount(mixed $amount, Currency $currency): string
    {
        try {
            return iex_number_format($amount ?? 0, $currency->number_format ?? 2);
        } catch (Throwable $e) {
            Log::error('Ошибка форматирования суммы обмена', [
                'message' => $e->getMessage(),
            ]);

            return '0';
        }
    }
}
