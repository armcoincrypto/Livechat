<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * compiler:courses can copy a stale manual_rate_value back onto course_value
 * for BestChange-owned rows whose profit is already 0. Public Calculator
 * ignores that mirror and keeps the live BestChange rate.
 *
 * This reassert runs after the courses compiler. It does not change profit,
 * position, mapping, ownership, or the Top-N profit floor.
 */
final class BestChangeStoredCourseReassert
{
    public const RELATIVE_GAP = '0.01';

    /**
     * Soft age ceiling for mirror reassert only.
     * Full market-base health stays at 1800s for Top-N / BASE selection.
     * Mirror repair may still use a clean positive rate that is only slightly older.
     */
    public const MIRROR_REASSERT_MAX_AGE_SECONDS = 7200;

    /**
     * True when a zero-profit BestChange row is holding a manual mirror
     * strictly below the live BestChange rate_value.
     */
    public static function isStaleManualMirror(
        string $course,
        string $manual,
        string $rate,
        string $profit,
    ): bool {
        if (!self::isZero($profit) || !self::isPositive($rate) || !self::isPositive($course)) {
            return false;
        }
        if (!self::sameNumber($course, $manual)) {
            return false;
        }
        if (bccomp(self::norm($course), self::norm($rate), 8) >= 0) {
            return false;
        }
        $gap = bcdiv(bcsub(self::norm($rate), self::norm($course), 12), self::norm($rate), 12);

        return bccomp($gap, self::RELATIVE_GAP, 12) === 1;
    }

    /**
     * Fail-closed usability for mirror reassert (not Top-N BASE selection).
     *
     * Rejects: missing/inactive link, parser error, non-positive rate, extreme age.
     * Allows: age slightly above the 1800s market-health window when the rate is
     * still clean and positive (courses can rewrite mirrors while the book is only
     * a few minutes past the BASE-health threshold).
     *
     * @param array<string,mixed> $health from BestChangeMarketBaseHealth::evaluate
     */
    public static function isUsableRateForMirrorReassert(array $health): bool
    {
        if ((int) ($health['link_status'] ?? 0) !== 1) {
            return false;
        }
        $rate = (string) ($health['rate_value'] ?? '');
        if (!self::isPositive($rate)) {
            return false;
        }
        $reason = (string) ($health['reason'] ?? '');
        if ($reason === 'parser_error' || $reason === 'link_inactive' || $reason === 'link_missing') {
            return false;
        }
        // evaluate() with a raised max-age still reports stale_* when past that ceiling.
        if (($health['healthy'] ?? false) === true) {
            return true;
        }
        if (str_starts_with($reason, 'stale')) {
            return false;
        }
        // Non-healthy for other reasons (e.g. non_positive already handled) → reject.
        return false;
    }

    /**
     * @return array{scanned:int,matched:int,updated:int,ids:list<int>}
     */
    public function reassert(bool $dryRun = true): array
    {
        $derived = DerivedMarketBaselineAuthority::fromStorageApp();
        $zelleId = ZelleOutgoingRateWriteGuard::zelleCurrencyId();
        $rows = DB::select("
            SELECT de.id, de.course_value, de.manual_rate_value, de.profit, de.id_currency1, bd.rate_value
            FROM direction_exchange de
            INNER JOIN bestchange_directions bd
                ON bd.id_direction_exchange = de.id AND bd.status = 1
            WHERE de.status = 1
              AND de.parser_source_name = 'BestChange'
              AND bd.is_error_parser = 0
        ");

        $matched = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if ($derived->owns($id) || (int) $row->id_currency1 === $zelleId) {
                continue;
            }
            if (!self::isStaleManualMirror(
                (string) $row->course_value,
                (string) $row->manual_rate_value,
                (string) $row->rate_value,
                (string) $row->profit,
            )) {
                continue;
            }
            $health = BestChangeMarketBaseHealth::evaluate($id, self::MIRROR_REASSERT_MAX_AGE_SECONDS);
            if (!self::isUsableRateForMirrorReassert($health)) {
                continue;
            }
            $matched[$id] = (string) $row->rate_value;
        }

        $updated = 0;
        if (!$dryRun && $matched !== []) {
            $updated = $this->write($matched);
        }

        return [
            'scanned' => count($rows),
            'matched' => count($matched),
            'updated' => $updated,
            'ids' => array_keys($matched),
        ];
    }

    /**
     * @param array<int,string> $courseById
     */
    private function write(array $courseById): int
    {
        $now = now()->toDateTimeString();
        $updates = [];
        $models = DirectionExchange::query()
            ->with([
                'currency1:id,id_code_currency',
                'currency1.code_currency:id,name',
                'currency2:id,id_code_currency',
                'currency2.code_currency:id,name',
            ])
            ->whereIn('id', array_keys($courseById))
            ->get()
            ->keyBy('id');

        foreach ($courseById as $id => $course) {
            $model = $models->get($id);
            if ($model === null) {
                continue;
            }
            $model->course_value = $course;
            $label = $course;
            try {
                $label = AdminRateSourcePresenter::liveExchangeRateLabel($model);
            } catch (Throwable) {
                $label = $course;
            }
            $updates[] = [
                'id' => $id,
                'course_value' => $course,
                'manual_rate_value' => $course,
                'exchange_rate' => $label,
                'updated_at' => $now,
            ];
            RateWriteAuditLogger::record([
                'event' => 'base_change',
                'direction_id' => $id,
                'old_base' => null,
                'new_base' => $course,
                'writer' => self::class,
                'source' => 'BestChange',
                'reason' => 'courses_manual_mirror_reassert',
            ]);
        }

        if ($updates === []) {
            return 0;
        }

        DirectionExchange::upsert(
            $updates,
            ['id'],
            ['course_value', 'manual_rate_value', 'exchange_rate', 'updated_at']
        );

        return count($updates);
    }

    private static function isZero(string $value): bool
    {
        return self::isNumericPlain($value) && bccomp(self::norm($value), '0', 8) === 0;
    }

    private static function isPositive(string $value): bool
    {
        return self::isNumericPlain($value) && bccomp(self::norm($value), '0', 8) === 1;
    }

    private static function sameNumber(string $a, string $b): bool
    {
        return self::isNumericPlain($a) && self::isNumericPlain($b)
            && bccomp(self::norm($a), self::norm($b), 6) === 0;
    }

    private static function isNumericPlain(string $value): bool
    {
        return (bool) preg_match('/^-?\d+(\.\d+)?$/', trim($value));
    }

    private static function norm(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !self::isNumericPlain($value)) {
            return '0';
        }

        return bcadd($value, '0', 12);
    }
}
