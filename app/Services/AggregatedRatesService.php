<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * AggregatedRatesService
 *
 * Назначение:
 * - Собрать «снимок» курсов из нескольких таблиц-источников и вернуть единый словарь:
 *     [CODE => 'summa']
 *
 * Почему это важно для производительности:
 * - Этот сервис часто используется внутри расчётов (в том числе массовых), поэтому:
 *   - нормализацию кода делаем на стороне SQL (TRIM(...))
 *   - конфликты по источникам решаем по приоритету (меньше число = выше приоритет)
 *
 * Важные принципы:
 * - Никаких Schema::hasTable(): у тебя таблицы гарантированно существуют, а проверки только тормозят.
 * - Без «долгого» кеша: при обновлениях каждые 5–10 сек мы не держим устаревшие значения.
 * - Максимальный прирост даёт не Redis-кеш, а вызов fetch() один раз на прогон/чанк и дальнейшее
 *   использование snapshot (в памяти) в калькуляторе.
 */
final class AggregatedRatesService
{
    /**
     * Приоритеты источников по умолчанию.
     * Меньше число = выше приоритет.
     */
    private const DEFAULT_PRIORITIES = [
        'parser_exchange'            => 1,
        'competitor_rates'           => 2,
        'file_parser_rates'          => 3,
        'parser_formula_coefficient' => 4,
        'bestchange_directions'      => 5,
    ];

    /**
     * Описание каждого источника.
     *
     * Требования:
     * - code_expr должен возвращать нормализованный CODE (TRIM(...))
     * - value_expr должен возвращать строку (CAST(... AS CHAR)), чтобы не терять точность
     */
    private const SOURCE_MAP = [
        'parser_exchange' => [
            'table'      => 'parser_exchange',
            'code_expr'  => 'TRIM(code)',
            'value_expr' => 'CAST(summa AS CHAR)',
            'where_sql'  => "status = 1 AND code IS NOT NULL AND TRIM(code) <> ''",
        ],
        'competitor_rates' => [
            'table'      => 'competitor_rates',
            'code_expr'  => 'TRIM(code)',
            'value_expr' => 'CAST(summa AS CHAR)',
            'where_sql'  => "status = 1 AND code IS NOT NULL AND TRIM(code) <> ''",
        ],
        'file_parser_rates' => [
            'table'      => 'file_parser_rates',
            'code_expr'  => 'TRIM(code)',
            'value_expr' => 'CAST(summa AS CHAR)',
            'where_sql'  => "status = 1 AND code IS NOT NULL AND TRIM(code) <> ''",
        ],
        'parser_formula_coefficient' => [
            'table'      => 'parser_formula_coefficient',
            'code_expr'  => 'TRIM(alias)',
            'value_expr' => 'CAST(summa AS CHAR)',
            'where_sql'  => "type_index = 0 AND alias IS NOT NULL AND TRIM(alias) <> ''",
        ],
        'bestchange_directions' => [
            'table'      => 'bestchange_directions',
            'code_expr'  => 'TRIM(code)',
            'value_expr' => 'CAST(rate_value AS CHAR)',
            'where_sql'  => "status = 1 AND code IS NOT NULL AND TRIM(code) <> ''",
        ],
    ];

    /**
     * Кеш-флаг поддержки оконных функций на текущем подключении.
     */
    private ?bool $supportsWindowFunctions = null;

    /**
     * Получить агрегированные курсы.
     *
     * @param array<string>|null     $sources    Какие источники использовать (null = все)
     * @param array<string,int>|null $priorities Переопределение приоритетов (меньше = выше)
     * @return array<string,string>             [CODE => 'summa']
     */
    public function fetch(?array $sources = null, ?array $priorities = null): array
    {
        $enabledSources = $this->resolveSources($sources);
        $resolvedPriorities = $this->resolvePriorities($priorities);

        // Быстрый путь: используем оконные функции, если они поддерживаются.
        if ($this->supportsWindowFunctions()) {
            return $this->fetchWithWindowFunction($enabledSources, $resolvedPriorities);
        }

        // Fallback (совместимость): UNION ALL + дедуп в PHP.
        return $this->fetchWithPhpDedup($enabledSources, $resolvedPriorities);
    }

    /**
     * Проверить поддержку оконных функций (ROW_NUMBER) для текущей БД.
     *
     * Принцип:
     * - MySQL поддерживает окна с 8.0+
     * - MariaDB поддерживает окна с 10.2+
     *
     * Важно:
     * - Мы не делаем «широкий» try/catch вокруг основного запроса, чтобы не скрывать реальные ошибки.
     */
    private function supportsWindowFunctions(): bool
    {
        if ($this->supportsWindowFunctions !== null) {
            return $this->supportsWindowFunctions;
        }

        try {
            $row = DB::selectOne('SELECT VERSION() AS v');
            $version = strtolower((string) ($row->v ?? ''));

            if ($version === '') {
                return $this->supportsWindowFunctions = false;
            }

            if (preg_match('/^(\d+)\.(\d+)/', $version, $m) !== 1) {
                return $this->supportsWindowFunctions = false;
            }

            $major = (int) $m[1];
            $minor = (int) $m[2];

            // MariaDB: 10.2+
            if (str_contains($version, 'mariadb')) {
                return $this->supportsWindowFunctions = ($major > 10) || ($major === 10 && $minor >= 2);
            }

            // MySQL: 8+
            return $this->supportsWindowFunctions = ($major >= 8);
        } catch (Throwable) {
            // Если не смогли определить версию — безопаснее считать, что окон нет.
            return $this->supportsWindowFunctions = false;
        }
    }

    /**
     * Быстрый путь: делаем дедуп в SQL через ROW_NUMBER().
     *
     * Требует MySQL 8+.
     *
     * @param array<int, string>     $sources
     * @param array<string, int>     $priorities
     * @return array<string, string>
     */
    private function fetchWithWindowFunction(array $sources, array $priorities): array
    {
        [$unionSql, $bindings] = $this->buildUnionSql($sources, $priorities);

        if ($unionSql === '') {
            return [];
        }

        $sql = "
            SELECT code, summa
            FROM (
                SELECT
                    code,
                    summa,
                    pr,
                    ROW_NUMBER() OVER (PARTITION BY code ORDER BY pr ASC, src ASC) AS rn
                FROM (
                    {$unionSql}
                ) u
                WHERE code <> ''
            ) x
            WHERE x.rn = 1
        ";

        $rows = DB::select($sql, $bindings);

        $out = [];
        foreach ($rows as $r) {
            $code = trim((string) ($r->code ?? ''));
            if ($code === '') {
                continue;
            }

            $summa = (string) ($r->summa ?? '0');
            $out[$code] = ($summa !== '' ? $summa : '0');
        }

        return $out;
    }

    /**
     * Совместимый путь: UNION ALL + выбор минимального pr в PHP.
     *
     * @param array<int, string> $sources
     * @param array<string, int> $priorities
     * @return array<string, string>
     */
    private function fetchWithPhpDedup(array $sources, array $priorities): array
    {
        // Сбор QueryBuilder-ов удобен для fallback.
        $builders = [];

        foreach ($sources as $src) {
            $def = self::SOURCE_MAP[$src] ?? null;
            if ($def === null) {
                continue;
            }

            $priority = $priorities[$src] ?? PHP_INT_MAX;

            $builders[] = DB::table($def['table'])
                ->selectRaw(
                    $def['code_expr'] . ' AS code, ' . $def['value_expr'] . ' AS summa, ? AS pr, ? AS src',
                    [$priority, $this->sourceOrder($src)]
                )
                ->whereRaw($def['where_sql']);
        }

        if ($builders === []) {
            return [];
        }

        $union = array_shift($builders);
        foreach ($builders as $b) {
            $union = $union->unionAll($b);
        }

        $rows = $union->get();

        /** @var array<string, array{summa:string, pr:int, src:int}> $best */
        $best = [];

        foreach ($rows as $r) {
            $code = trim((string) ($r->code ?? ''));
            if ($code === '') {
                continue;
            }

            $summa = (string) ($r->summa ?? '0');
            if ($summa === '') {
                $summa = '0';
            }

            $pr = (int) ($r->pr ?? PHP_INT_MAX);
            $srcOrder = (int) ($r->src ?? PHP_INT_MAX);

            if (!isset($best[$code])
                || $pr < $best[$code]['pr']
                || ($pr === $best[$code]['pr'] && $srcOrder < $best[$code]['src'])
            ) {
                $best[$code] = ['summa' => $summa, 'pr' => $pr, 'src' => $srcOrder];
            }
        }

        $out = [];
        foreach ($best as $code => $v) {
            $out[$code] = $v['summa'];
        }

        return $out;
    }

    /**
     * Построить UNION SQL для SQL-ветки (ROW_NUMBER).
     *
     * @param array<int, string> $sources
     * @param array<string, int> $priorities
     * @return array{0:string,1:array<int,int>}
     */
    private function buildUnionSql(array $sources, array $priorities): array
    {
        $unions = [];
        $bindings = [];

        foreach ($sources as $src) {
            $def = self::SOURCE_MAP[$src] ?? null;
            if ($def === null) {
                continue;
            }

            $priority = $priorities[$src] ?? PHP_INT_MAX;
            $srcOrder = $this->sourceOrder($src);

            $unions[] = sprintf(
                'SELECT %s AS code, %s AS summa, ? AS pr, ? AS src FROM %s WHERE %s',
                $def['code_expr'],
                $def['value_expr'],
                $def['table'],
                $def['where_sql']
            );

            $bindings[] = $priority;
            $bindings[] = $srcOrder;
        }

        return [implode(' UNION ALL ', $unions), $bindings];
    }

    /**
     * Вернёт список источников (валидные), либо все по умолчанию.
     *
     * @param array<string>|null $sources
     * @return array<int, string>
     */
    private function resolveSources(?array $sources): array
    {
        $all = array_keys(self::DEFAULT_PRIORITIES);

        if ($sources === null) {
            return $all;
        }

        $filtered = [];
        foreach ($sources as $s) {
            $s = (string) $s;
            if (in_array($s, $all, true)) {
                $filtered[] = $s;
            }
        }

        return $filtered ?: $all;
    }

    /**
     * Детерминированный порядок источников для tie-break при равных приоритетах.
     *
     * Меньше число = «выше» источник при равных pr.
     */
    private function sourceOrder(string $source): int
    {
        return self::DEFAULT_PRIORITIES[$source] ?? PHP_INT_MAX;
    }

    /**
     * Слить пользовательские приоритеты с дефолтными.
     *
     * @param array<string,int>|null $overrides
     * @return array<string,int>
     */
    private function resolvePriorities(?array $overrides): array
    {
        $priorities = self::DEFAULT_PRIORITIES;

        if ($overrides) {
            foreach ($overrides as $src => $p) {
                if (array_key_exists($src, $priorities)) {
                    $priorities[$src] = (int) $p;
                }
            }
        }

        return $priorities;
    }
}
