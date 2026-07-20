<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Export\Concerns;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Services\Rates\BestChangeMappingVerifier;
use App\Services\Rates\RateExportQuarantine;
use Carbon\Carbon;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trait ExportFormatHelpers
 *
 * Вспомогательные методы для экспорта курсов/направлений.
 *
 * Цели:
 * - Устойчивость: любой мусор в данных не должен “валить” экспорт.
 * - Точность: сравнения/деления делаем строками (BC-safe), без float.
 * - Производительность: кешируем now() и настройки iEXSetting().
 *
 * Важно:
 * - roundBcmath() сохранён без изменений (как ты просил).
 */
trait ExportFormatHelpers
{
    use InteractsWithNumbers;

    /**
     * Кеш времени “сейчас” для экспортного прохода.
     */
    private ?Carbon $exportNow = null;

    /**
     * Кеш настроек.
     */
    private ?int $exportMaxDecimals = null;
    private ?int $exportOperatorOnline = null;

    /**
     * Опционально: установить текущее время экспорта (для тестов/детерминизма).
     */
    protected function setExportNow(?Carbon $now): void
    {
        $this->exportNow = $now;
    }

    /**
     * Получить текущее время экспорта (кешируем).
     */
    protected function getExportNow(): Carbon
    {
        if ($this->exportNow instanceof Carbon) {
            return $this->exportNow;
        }

        return $this->exportNow = Carbon::now();
    }

    /**
     * Проверка: можно ли экспортировать направление.
     *
     * Правила:
     * - пустой course_value или allow_export=2 => нет
     * - allow_export=1 => разрешено только в окне allow_export_from/allow_export_to (H:i)
     *   (корректно обрабатываем окно через полночь, например 23:00–02:00)
     * - offlineOperatorCheck:
     *   если включено и hidden_export_label_param=2 и оператор онлайн => нет
     */
    protected function shouldExportRate(DirectionExchange $rate, int $offlineOperatorCheck): bool
    {
        try {
            if (empty($rate->course_value)) {
                return false;
            }

            // Fail closed: non-positive / non-numeric courses must never be exported.
            $quarantine = new RateExportQuarantine();
            if (!$quarantine->isExportableCourse((string) $rate->course_value)) {
                return false;
            }
            if ((int) ($rate->status ?? 1) !== 1) {
                return false;
            }

            // Fail closed: drifted / absent / ambiguous BestChange identities must not export.
            // Prevents TON (ABSENT; ID 209 is GRAM) and Payeer PR* from public feeds.
            $fromCode = strtoupper((string) ($rate->currency1->designation_xml ?? ''));
            $toCode = strtoupper((string) ($rate->currency2->designation_xml ?? ''));
            if ($fromCode !== '' && !$this->isExportMappingAllowed($fromCode)) {
                return false;
            }
            if ($toCode !== '' && !$this->isExportMappingAllowed($toCode)) {
                return false;
            }

            $allowExport = (int) ($rate->allow_export ?? 0);

            if ($allowExport === 2) {
                return false;
            }

            if ($allowExport === 1) {
                $from = isset($rate->allow_export_from) ? trim((string) $rate->allow_export_from) : '';
                $to = isset($rate->allow_export_to) ? trim((string) $rate->allow_export_to) : '';

                if ($from === '' || $to === '') {
                    return false;
                }

                $now = $this->getExportNow();

                $fromAt = $this->parseTodayTime($now, $from);
                $toAt = $this->parseTodayTime($now, $to);

                if ($fromAt === null || $toAt === null) {
                    return false;
                }

                if (!$this->isNowInTimeWindow($now, $fromAt, $toAt)) {
                    return false;
                }
            }

            if ($offlineOperatorCheck === 1) {
                if ((int) ($rate->hidden_export_label_param ?? 0) === 2) {
                    if ($this->getOperatorOnlineSetting() === 1) {
                        return false;
                    }
                }
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Ошибка проверки экспорта направления', [
                'direction_exchange_id' => $rate->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Only VERIFIED BestChange identities may appear in public XML export.
     * ABSENT / DRIFTED / AMBIGUOUS / DEPRECATED → blocked.
     */
    protected function isExportMappingAllowed(string $localCode): bool
    {
        static $cache = [];
        $code = strtoupper(trim($localCode));
        if ($code === '') {
            return false;
        }
        if (array_key_exists($code, $cache)) {
            return $cache[$code];
        }

        try {
            $status = strtoupper((string) (BestChangeMappingVerifier::fromStorageApp()->verifyCode($code)['status'] ?? ''));
            $cache[$code] = $status === 'VERIFIED';
        } catch (Throwable $e) {
            // Fail closed on verifier errors.
            $cache[$code] = false;
        }

        return $cache[$code];
    }

    /**
     * Рассчитывает значения курса обмена для направлений.
     *
     * Правило:
     * - Если rate < 1: in = 1/rate (округление по decimal_in), out = 1
     * - Иначе: in = 1, out = rate (округление по decimal_out)
     *
     * Важно:
     * - ВСЕ сравнения/деления делаем в bc-строках.
     * - Округление выполняем ТОЛЬКО через твой roundBcmath() (без изменений).
     *
     * @param string|float|int $rate
     * @return array{in:string,out:string}|null
     */
    protected function calculateCourseValues(string|float|int $rate, int $decimal_in, int $decimal_out): ?array
    {
        try {
            $maxDecimal = $this->getMaxDecimalsSetting();

            // sanitizeNumber часто полезен, но научную нотацию/разделители надёжнее приводить к bc-формату:
            $safeRate = $this->toBcString((string) $this->sanitizeNumber($rate), $maxDecimal);

            if (bccomp($safeRate, '0', $maxDecimal) !== 1) {
                return null;
            }

            $currencyFromDecimals = $this->adjustNumberFormat($decimal_in, $maxDecimal);
            $currencyToDecimals = $this->adjustNumberFormat($decimal_out, $maxDecimal);

            if (bccomp($safeRate, '1', $maxDecimal) === -1) {
                $inverseRate = bcdiv('1', $safeRate, $maxDecimal);
                $inverseRateRounded = $this->roundBcmath($inverseRate, $currencyFromDecimals);

                return [
                    'in' => $inverseRateRounded,
                    'out' => '1',
                ];
            }

            $rateRounded = $this->roundBcmath($safeRate, $currencyToDecimals);

            return [
                'in' => '1',
                'out' => $rateRounded,
            ];
        } catch (Throwable $e) {
            Log::error('Ошибка расчета значений курса', [
                'rate' => (string) $rate,
                'decimal_in' => $decimal_in,
                'decimal_out' => $decimal_out,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Формирует описание комиссии на входе (fromfee).
     *
     * Формат:
     * - "X%" и/или "Y CODE"
     * - если комиссии нет — пустая строка
     *
     * Важно:
     * - никаких float-сравнений: проверяем >0 через bc.
     */
    public function exportFromFee(DirectionExchange $rate, Currency $currency, int $fromFee = 0): string
    {
        try {
            $currencyCodeName = (string) ($currency->code_currency->name ?? '');

            $percentRaw = $fromFee === 0 ? ($rate->oth_comm_percent ?? null) : ($rate->pay_comm_percent ?? null);
            $fixedRaw = $fromFee === 0 ? ($rate->oth_comm_currency ?? null) : ($rate->pay_comm_currency ?? null);

            $parts = [];

            $percent = $this->positiveBcOrNull($percentRaw);
            if ($percent !== null) {
                $parts[] = $this->trimZeros($percent) . '%';
            }

            $fixed = $this->positiveBcOrNull($fixedRaw);
            if ($fixed !== null && $currencyCodeName !== '') {
                $parts[] = $this->trimZeros($fixed) . ' ' . $currencyCodeName;
            }

            return $parts !== [] ? implode(', ', $parts) : '';
        } catch (Throwable $e) {
            Log::error('Ошибка формирования комиссии на входе', [
                'direction_exchange_id' => $rate->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Формирует описание комиссии на выходе (tofee).
     *
     * Важно:
     * - никаких float-сравнений: проверяем >0 через bc.
     */
    public function exportToFee(DirectionExchange $rate, Currency $currency, int $fee = 0): string
    {
        try {
            $currencyCodeName = (string) ($currency->code_currency->name ?? '');

            $percentRaw = $fee === 0 ? ($rate->oth_comm2_percent ?? null) : ($rate->pay_comm2_percent ?? null);
            $fixedRaw = $fee === 0 ? ($rate->oth_comm2_currency ?? null) : ($rate->pay_comm2_currency ?? null);

            $parts = [];

            $percent = $this->positiveBcOrNull($percentRaw);
            if ($percent !== null) {
                $parts[] = $this->trimZeros($percent) . '%';
            }

            $fixed = $this->positiveBcOrNull($fixedRaw);
            if ($fixed !== null && $currencyCodeName !== '') {
                $parts[] = $this->trimZeros($fixed) . ' ' . $currencyCodeName;
            }

            return $parts !== [] ? implode(', ', $parts) : '';
        } catch (Throwable $e) {
            Log::error('Ошибка формирования комиссии на выходе', [
                'direction_exchange_id' => $rate->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * roundBcmath — ОСТАВЛЕНО БЕЗ ИЗМЕНЕНИЙ (как ты просил).
     */
    protected function roundBcmath(string|float $number, int $decimals = 8): string
    {
        $number = (string)$number;
        $factor = bcpow('10', (string)($decimals + 1));
        $temp = bcmul($number, $factor, 0);
        $lastDigit = (int)substr($temp, -1);
        $roundUp = $lastDigit >= 5 ? 1 : 0;
        $result = bcdiv(
            bcadd(bcmul($number, bcpow('10', (string)$decimals), 0), (string)$roundUp, 0),
            bcpow('10', (string)$decimals),
            $decimals
        );

        return $result;
    }

    /**
     * Настройка max_decimal_places (кешируем).
     */
    private function getMaxDecimalsSetting(): int
    {
        if ($this->exportMaxDecimals !== null) {
            return $this->exportMaxDecimals;
        }

        $v = (int) iEXSetting('max_decimal_places', 18);
        $v = $v > 0 ? $v : 18;
        $v = min($v, 18);

        return $this->exportMaxDecimals = $v;
    }

    /**
     * Настройка is_operator_online (кешируем).
     */
    private function getOperatorOnlineSetting(): int
    {
        if ($this->exportOperatorOnline !== null) {
            return $this->exportOperatorOnline;
        }

        return $this->exportOperatorOnline = (int) iEXSetting('is_operator_online', 0);
    }

    /**
     * Привести значение к bc-строке и вернуть, если строго > 0.
     *
     * @return string|null
     */
    private function positiveBcOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);
        if ($s === '' || $s === 'null') {
            return null;
        }

        $maxDecimal = $this->getMaxDecimalsSetting();
        $bc = $this->toBcString($s, $maxDecimal);

        return bccomp($bc, '0', $maxDecimal) === 1 ? $bc : null;
    }

    /**
     * Убрать хвостовые нули и точку.
     */
    private function trimZeros(string $value): string
    {
        $value = trim($value);

        if ($value === '' || $value === '0' || $value === '-0') {
            return '0';
        }

        if (str_contains($value, '.')) {
            $value = rtrim($value, '0');
            $value = rtrim($value, '.');
        }

        if ($value === '' || $value === '-0') {
            return '0';
        }

        return $value;
    }

    /**
     * Парсинг времени "H:i" и привязка к дате $now (сегодня).
     */
    private function parseTodayTime(Carbon $now, string $time): ?Carbon
    {
        $time = trim($time);
        if ($time === '') {
            return null;
        }

        // Предварительная валидация, чтобы не ловить исключения на мусоре.
        if (!preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return null;
        }

        try {
            $t = Carbon::createFromFormat('H:i', $time);
            return $now->copy()->setTime((int) $t->hour, (int) $t->minute, 0);
        } catch (Throwable $e) {
            Log::warning('Не удалось распарсить время allow_export_*', [
                'time' => $time,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Проверить, входит ли $now в окно [$fromAt, $toAt], включая переход через полночь.
     */
    private function isNowInTimeWindow(Carbon $now, Carbon $fromAt, Carbon $toAt): bool
    {
        if ($fromAt->lessThanOrEqualTo($toAt)) {
            return $now->betweenIncluded($fromAt, $toAt);
        }

        // Окно через полночь: now >= from OR now <= to
        return $now->greaterThanOrEqualTo($fromAt) || $now->lessThanOrEqualTo($toAt);
    }
}
