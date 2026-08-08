<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\BestChangeDirection;
use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

/**
 * RateRowFilter
 *
 * Фильтрация строк BestChange rates по правилам направления.
 *
 * Фильтры:
 * - auto-blacklist cooldown (AutoBlacklistService)
 * - exchanger pool (ExchangerPoolService): preferred/excluded, режим off|soft|strict
 * - blacklist/whitelist (глобальный + локальные списки направления)
 * - min_reserve / max_reserve
 * - корректность значения поля курса (rate|rankrate) > 0
 * - anti-fake score (AntiFakeScoreService) с порогом из BestChangeConfig
 *
 * Дополнительно:
 * - строки, прошедшие фильтрацию, получают мета-поля:
 *   - _quality_score (int 0..100)
 *   - _quality_reasons (array<string,int>)
 *   - _pool_preferred (bool) — только для pool_mode=soft
 */
final class RateRowFilter
{
    use InteractsWithNumbers;

    private const SCALE = 18;

    public function __construct(
        private readonly AntiFakeScoreService $antiFake,
        private readonly BestChangeConfig $settings,
        private readonly ExchangerPoolService $poolService,
        private readonly AutoBlacklistService $autoBlacklist,
    ) {}

    /**
     * @param array<int, array<string,mixed>> $rows
     * @param array<int,mixed> $globalBlacklist
     * @param array<string,int>|null $rejectCounters
     * @return array<int, array<string,mixed>>
     */
    public function filter(
        array $rows,
        BestChangeDirection $direction,
        RateSelectionPolicy $policy,
        array $globalBlacklist = [],
        ?array &$rejectCounters = null
    ): array {
        $rateField = $policy->typeField;

        $antiFakeEnabled = $this->settings->antiFakeEnabled();
        $antiFakeMinScore = $this->settings->antiFakeMinScore();

        // Exchanger pool
        $pool = $this->poolService->resolve();
        $poolMode = (string)($pool['mode'] ?? 'off'); // off|soft|strict
        /** @var array<string,true> $poolPreferred */
        $poolPreferred = is_array($pool['preferred'] ?? null) ? $pool['preferred'] : [];
        /** @var array<string,true> $poolExcluded */
        $poolExcluded = is_array($pool['excluded'] ?? null) ? $pool['excluded'] : [];

        /** @var array<string,true> $blacklist */
        $blacklist = $this->buildBlacklistSet($globalBlacklist, (string)($direction->blacklist_ids ?? ''));

        /** @var array<string,true>|null $whitelist */
        $whitelist = $this->buildWhitelistSet((string)($direction->whitelist_ids ?? ''));

        // Лимиты reserve
        $minReserveRaw = trim((string)($direction->min_reserve ?? '0'));
        $maxReserveRaw = trim((string)($direction->max_reserve ?? '0'));

        $hasMinReserve = $this->isGreaterThanZero($minReserveRaw);
        $hasMaxReserve = $this->isGreaterThanZero($maxReserveRaw);

        $minReserve = $hasMinReserve ? $this->toBcString($minReserveRaw, self::SCALE) : '0';
        $maxReserve = $hasMaxReserve ? $this->toBcString($maxReserveRaw, self::SCALE) : '0';

        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $this->inc($rejectCounters, 'invalid_row');
                continue;
            }

            // changer обязателен
            $changerRaw = $row['changer'] ?? null;
            if (!is_scalar($changerRaw)) {
                $this->inc($rejectCounters, 'invalid_row');
                continue;
            }

            $changerIdStr = trim((string)$changerRaw);
            if ($changerIdStr === '') {
                $this->inc($rejectCounters, 'invalid_row');
                continue;
            }

            $changerIdInt = is_numeric($changerIdStr) ? (int)$changerIdStr : 0;

            // Auto-blacklist cooldown
            if ($changerIdInt > 0 && $this->autoBlacklist->isBlocked($changerIdInt)) {
                $this->inc($rejectCounters, 'auto_blacklist');
                continue;
            }

            // Pool excluded
            if (isset($poolExcluded[$changerIdStr])) {
                $this->inc($rejectCounters, 'pool_excluded');
                continue;
            }

            // Pool strict: только preferred
            if ($poolMode === 'strict' && !isset($poolPreferred[$changerIdStr])) {
                $this->inc($rejectCounters, 'pool_strict_not_preferred');
                continue;
            }

            // blacklist / whitelist (глобальные/локальные)
            if (isset($blacklist[$changerIdStr])) {
                $this->inc($rejectCounters, 'blacklist');
                continue;
            }

            if (is_array($whitelist) && !isset($whitelist[$changerIdStr])) {
                $this->inc($rejectCounters, 'whitelist');
                continue;
            }

            // rate/rankrate обязано быть > 0
            $rateRaw = $row[$rateField] ?? null;
            if (!is_scalar($rateRaw)) {
                $this->inc($rejectCounters, 'invalid_rate');
                continue;
            }

            $rateValue = $this->toBcString($rateRaw, self::SCALE);
            if ($this->compareValues($rateValue, '0', self::SCALE) <= 0) {
                $this->inc($rejectCounters, 'invalid_rate');
                continue;
            }

            // reserve min/max
            $reserveRaw = $row['reserve'] ?? '0';
            $reserve = $this->toBcString($reserveRaw, self::SCALE);

            if ($hasMinReserve && $this->compareValues($reserve, $minReserve, self::SCALE) === -1) {
                $this->inc($rejectCounters, 'min_reserve');
                continue;
            }

            if ($hasMaxReserve && $this->compareValues($reserve, $maxReserve, self::SCALE) === 1) {
                $this->inc($rejectCounters, 'max_reserve');
                continue;
            }

            // Anti-Fake score
            if ($antiFakeEnabled) {
                $scoreData = $this->antiFake->score($direction, $row);

                $row['_quality_score'] = (int) $scoreData['score'];
                $row['_quality_reasons'] = (array) $scoreData['reasons'];

                if ($row['_quality_score'] < $antiFakeMinScore) {
                    $this->inc($rejectCounters, 'anti_fake_low_score');

                    foreach ($row['_quality_reasons'] as $reason => $cnt) {
                        $this->inc($rejectCounters, 'anti_fake_reason:' . (string)$reason, (int)$cnt);
                    }

                    // Auto-blacklist по “тяжёлым” причинам (без фанатизма)
                    // Здесь правило безопасное: блокируем только при unstable или fake_limits_*.
                    if ($changerIdInt > 0) {
                        $reasons = $row['_quality_reasons'];
                        $isHeavy =
                            !empty($reasons['unstable']) ||
                            !empty($reasons['fake_limits_min']) ||
                            !empty($reasons['fake_limits_max']);

                        if ($isHeavy) {
                            $this->autoBlacklist->block(
                                changerId: $changerIdInt,
                                minutes: 120,
                                reason: 'auto_blacklist:anti_fake',
                                meta: ['score' => $row['_quality_score'], 'reasons' => $reasons]
                            );
                        }
                    }

                    continue;
                }
            } else {
                $row['_quality_score'] = 100;
                $row['_quality_reasons'] = [];
            }

            // Pool soft: помечаем preferred
            if ($poolMode === 'soft' && isset($poolPreferred[$changerIdStr])) {
                $row['_pool_preferred'] = true;
            }

            $result[] = $row;
        }

        return array_values($result);
    }

    /**
     * @param array<string,int>|null $counters
     */
    private function inc(?array &$counters, string $key, int $by = 1): void
    {
        if ($counters === null) {
            return;
        }

        $by = max(1, $by);
        $counters[$key] = ($counters[$key] ?? 0) + $by;
    }

    /**
     * @param array<int,mixed> $globalBlacklist
     * @return array<string,true>
     */
    private function buildBlacklistSet(array $globalBlacklist, string $directionBlacklistCsv): array
    {
        $set = [];

        foreach ($globalBlacklist as $id) {
            $s = trim((string)$id);
            if ($s !== '') {
                $set[$s] = true;
            }
        }

        $directionBlacklistCsv = trim($directionBlacklistCsv);
        if ($directionBlacklistCsv !== '') {
            foreach (explode(',', $directionBlacklistCsv) as $id) {
                $s = trim($id);
                if ($s !== '') {
                    $set[$s] = true;
                }
            }
        }

        return $set;
    }

    /**
     * @return array<string,true>|null
     */
    private function buildWhitelistSet(string $directionWhitelistCsv): ?array
    {
        $directionWhitelistCsv = trim($directionWhitelistCsv);
        if ($directionWhitelistCsv === '') {
            return null;
        }

        $set = [];
        foreach (explode(',', $directionWhitelistCsv) as $id) {
            $s = trim($id);
            if ($s !== '') {
                $set[$s] = true;
            }
        }

        return $set === [] ? null : $set;
    }
}
