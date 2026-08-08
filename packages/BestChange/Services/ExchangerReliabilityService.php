<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\BestChangeExchangerStat;
use Illuminate\Support\Facades\DB;

/**
 * ExchangerReliabilityService
 *
 * Сбор и запись агрегированной статистики по обменникам BestChange.
 *
 * Цель:
 * - минимизировать количество запросов к БД при массовых обновлениях (cron/updateRates)
 * - обеспечить атомарные инкременты при параллельных запусках (несколько воркеров/кронов)
 *
 * Как работает:
 * - методы seen/selected/rejected/error не трогают БД сразу, а накапливают события в буфере
 * - flush() делает батчевую запись одним SQL: INSERT ... ON DUPLICATE KEY UPDATE
 *
 * Требования к таблице:
 * - bestchange_exchanger_stats
 * - UNIQUE KEY (changer_id)
 */
final class ExchangerReliabilityService
{
    private const TABLE = 'bestchange_exchanger_stats';

    /**
     * Максимум записей в буфере до авто-flush.
     */
    private int $flushThreshold;

    /**
     * Авто-flush в деструкторе (на случай если забыли вызвать flush()).
     */
    private bool $autoFlushOnDestruct;

    /**
     * @var array<int,array{
     *   seen:int,
     *   selected:int,
     *   rejected:int,
     *   error:int,
     *   score_sum:int,
     *   touch_seen:bool,
     *   touch_selected:bool
     * }>
     */
    private array $buffer = [];

    public function __construct(int $flushThreshold = 400, bool $autoFlushOnDestruct = true)
    {
        $this->flushThreshold = max(50, $flushThreshold);
        $this->autoFlushOnDestruct = $autoFlushOnDestruct;
    }

    public function __destruct()
    {
        if ($this->autoFlushOnDestruct) {
            $this->flush();
        }
    }

    /**
     * Обменник встретился в отфильтрованном списке (seen).
     */
    public function seen(int $changerId): void
    {
        if ($changerId <= 0) return;

        $r = &$this->row($changerId);
        $r['seen']++;
        $r['touch_seen'] = true;

        $this->maybeFlush();
    }

    /**
     * Обменник выбран как источник курса (selected) + score.
     */
    public function selected(int $changerId, int $score): void
    {
        if ($changerId <= 0) return;

        $score = max(0, min(100, $score));

        $r = &$this->row($changerId);
        $r['selected']++;
        $r['score_sum'] += $score;
        $r['touch_selected'] = true;

        $this->maybeFlush();
    }

    /**
     * Обменник отклонён фильтрами (rejected).
     */
    public function rejected(int $changerId): void
    {
        if ($changerId <= 0) return;

        $r = &$this->row($changerId);
        $r['rejected']++;

        $this->maybeFlush();
    }

    /**
     * Ошибка при расчёте на выбранном обменнике (error).
     */
    public function error(int $changerId): void
    {
        if ($changerId <= 0) return;

        $r = &$this->row($changerId);
        $r['error']++;

        $this->maybeFlush();
    }

    /**
     * Сбросить накопленные значения в БД.
     * Рекомендуется вызывать в конце RatesUpdateService::run().
     */
    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $now = now()->toDateTimeString();

        $rows = [];
        foreach ($this->buffer as $changerId => $c) {
            $rows[] = [
                'changer_id' => $changerId,
                'seen_count' => $c['seen'],
                'selected_count' => $c['selected'],
                'rejected_count' => $c['rejected'],
                'error_count' => $c['error'],
                'quality_score_sum' => $c['score_sum'],
                'last_seen_at' => $c['touch_seen'] ? $now : null,
                'last_selected_at' => $c['touch_selected'] ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // батчим, чтобы не упереться в max_allowed_packet
        foreach (array_chunk($rows, 700) as $chunk) {
            $this->insertOrIncrement($chunk, $now);
        }

        $this->buffer = [];
    }

    // ---------------------------------------------------------------------
    // Internal
    // ---------------------------------------------------------------------

    /**
     * @return array{seen:int,selected:int,rejected:int,error:int,score_sum:int,touch_seen:bool,touch_selected:bool}
     */
    private function &row(int $changerId): array
    {
        if (!isset($this->buffer[$changerId])) {
            $this->buffer[$changerId] = [
                'seen' => 0,
                'selected' => 0,
                'rejected' => 0,
                'error' => 0,
                'score_sum' => 0,
                'touch_seen' => false,
                'touch_selected' => false,
            ];
        }

        return $this->buffer[$changerId];
    }

    private function maybeFlush(): void
    {
        if (count($this->buffer) >= $this->flushThreshold) {
            $this->flush();
        }
    }

    /**
     * Атомарная запись: INSERT ... ON DUPLICATE KEY UPDATE (инкрементами).
     *
     * Важно: требует UNIQUE KEY по changer_id.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    private function insertOrIncrement(array $rows, string $now): void
    {
        // (опционально) гарантируем существование модели/каста — но используем таблицу напрямую
        // чтобы сделать инкременты атомарно.

        $placeholders = [];
        $bindings = [];

        foreach ($rows as $r) {
            $placeholders[] = '(?,?,?,?,?,?,?,?,?)';

            $bindings[] = (int) $r['changer_id'];
            $bindings[] = (int) $r['seen_count'];
            $bindings[] = (int) $r['selected_count'];
            $bindings[] = (int) $r['rejected_count'];
            $bindings[] = (int) $r['error_count'];
            $bindings[] = (int) $r['quality_score_sum'];
            $bindings[] = $r['last_seen_at'];       // string|null
            $bindings[] = $r['last_selected_at'];   // string|null
            $bindings[] = $now;
        }

        $sql = '
INSERT INTO ' . self::TABLE . '
(changer_id, seen_count, selected_count, rejected_count, error_count, quality_score_sum, last_seen_at, last_selected_at, updated_at)
VALUES ' . implode(',', $placeholders) . '
ON DUPLICATE KEY UPDATE
  seen_count = seen_count + VALUES(seen_count),
  selected_count = selected_count + VALUES(selected_count),
  rejected_count = rejected_count + VALUES(rejected_count),
  error_count = error_count + VALUES(error_count),
  quality_score_sum = quality_score_sum + VALUES(quality_score_sum),
  last_seen_at = COALESCE(VALUES(last_seen_at), last_seen_at),
  last_selected_at = COALESCE(VALUES(last_selected_at), last_selected_at),
  updated_at = VALUES(updated_at)
';

        DB::statement($sql, $bindings);

        // created_at мы не трогаем при UPDATE — остаётся как у первой вставки.
        // Если хочешь гарантировать created_at на вставке — добавь его в INSERT-список выше.
    }
}
