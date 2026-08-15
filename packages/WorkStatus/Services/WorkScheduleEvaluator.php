<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus\Services;

use App\Models\JobSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * WorkScheduleEvaluator
 *
 * Чистый вычислитель статуса по JobSchedule.
 *
 * Безопасные дефолты:
 * - если правил нет → OFFLINE
 * - если ни одно правило не подошло → OFFLINE
 *
 * @phpstan-type EvalResult array{
 *   isOnline: bool,
 *   ruleId: int|null,
 *   nextChangeAt: CarbonInterface|null,
 *   reason: non-empty-string
 * }
 */
final class WorkScheduleEvaluator
{
    /**
     * Рассчитать статус по расписанию.
     *
     * @param CarbonInterface|null $now Если null — текущее время.
     *
     * @return EvalResult
     */
    public function evaluate(?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();

        $rules = JobSchedule::query()
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        if ($rules->isEmpty()) {
            return $this->result(false, null, 'schedule_empty_default_offline');
        }

        $todayIso = (int) $now->dayOfWeekIso;
        $todayYmd = $now->toDateString();

        foreach ($rules as $schedule) {
            $activeFrom = $this->normalizeDateYmd($schedule->active_from ?? null);
            if ($activeFrom !== null && $todayYmd < $activeFrom) {
                continue;
            }

            $activeTo = $this->normalizeDateYmd($schedule->active_to ?? null);
            if ($activeTo !== null && $todayYmd > $activeTo) {
                continue;
            }

            $excludeDates = $this->normalizeDateArray($schedule->exclude_dates ?? []);
            if ($excludeDates !== [] && in_array($todayYmd, $excludeDates, true)) {
                continue;
            }

            $includeDates = $this->normalizeDateArray($schedule->include_dates ?? []);
            $inInclude = ($includeDates !== [] && in_array($todayYmd, $includeDates, true));

            $dateRanges = $this->normalizeDateRanges($schedule->date_ranges ?? []);
            $inRange = false;

            foreach ($dateRanges as $dr) {
                if ($dr['from'] <= $todayYmd && $todayYmd <= $dr['to']) {
                    $inRange = true;
                    break;
                }
            }

            $datesMatched = $inInclude || $inRange;

            $workDays = $this->normalizeWorkDays($schedule->work_days ?? '');
            if (!$datesMatched && $workDays !== [] && !in_array($todayIso, $workDays, true)) {
                continue;
            }

            $isOnlineInside = ((int) ($schedule->status ?? 0) === 1);

            if ((int) ($schedule->all_day ?? 0) === 1) {
                return $this->result($isOnlineInside, (int) $schedule->id, 'schedule_all_day');
            }

            $from = $this->normalizeTime((string) ($schedule->from_time ?? ''), $now);
            $to   = $this->normalizeTime((string) ($schedule->to_time ?? ''), $now);

            if ($from === null || $to === null) {
                continue;
            }

            $crossMidnight = $to->lessThan($from);

            $isWithin = $crossMidnight
                ? ($now->betweenIncluded($from, $from->copy()->endOfDay()) || $now->betweenIncluded($from->copy()->startOfDay(), $to))
                : $now->betweenIncluded($from, $to);

            if ($isWithin) {
                return $this->result($isOnlineInside, (int) $schedule->id, 'schedule_inside');
            }

            $policy = (string) ($schedule->outside_policy ?? 'inverse');

            $isOnlineOutside = match ($policy) {
                'no_change'     => null,
                'force_online'  => true,
                'force_offline' => false,
                default         => !$isOnlineInside,
            };

            if ($isOnlineOutside !== null) {
                return $this->result((bool) $isOnlineOutside, (int) $schedule->id, 'schedule_outside_policy:' . $policy);
            }
        }

        return $this->result(false, null, 'schedule_no_match_default_offline');
    }

    /**
     * @return EvalResult
     */
    private function result(bool $isOnline, ?int $ruleId, string $reason): array
    {
        return [
            'isOnline' => $isOnline,
            'ruleId' => $ruleId,
            'nextChangeAt' => null,
            'reason' => $reason === '' ? 'schedule_unknown' : $reason,
        ];
    }

    private function normalizeTime(string $raw, Carbon $now): ?Carbon
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }

        $s = str_replace('.', ':', $s);
        $s = (string) preg_replace('/\s+/', '', $s);

        if (preg_match('/^\d{3,4}$/', $s) === 1) {
            $s = str_pad($s, 4, '0', STR_PAD_LEFT);
            $s = substr($s, 0, -2) . ':' . substr($s, -2);
        } elseif (preg_match('/^\d{1,2}$/', $s) === 1) {
            $s = sprintf('%02d:00', (int) $s);
        }

        if (preg_match('/^\d{1,2}:\d{1,2}(:\d{1,2})?$/', $s) !== 1) {
            return null;
        }

        $p = array_map('intval', explode(':', $s));
        $h = $p[0] ?? 0;
        $m = $p[1] ?? 0;
        $sec = $p[2] ?? 0;

        if ($h === 24 && $m === 0 && $sec === 0) {
            return $now->copy()->startOfDay()->addDay();
        }

        if ($h < 0 || $h > 23 || $m < 0 || $m > 59 || $sec < 0 || $sec > 59) {
            return null;
        }

        return $now->copy()->setTime($h, $m, $sec);
    }

    /** @return list<int> */
    private function normalizeWorkDays(mixed $raw): array
    {
        $items = is_string($raw)
            ? (preg_split('/[\s,;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            : (is_array($raw) ? $raw : []);

        $days = array_values(array_filter(
            array_map('intval', $items),
            static fn (int $d): bool => $d >= 1 && $d <= 7
        ));

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }

    /** @return list<string> */
    private function normalizeDateArray(mixed $raw): array
    {
        $items = [];

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $items = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded
                : (preg_split('/[\s,;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        } elseif (is_array($raw)) {
            $items = $raw;
        }

        $out = [];
        foreach ($items as $v) {
            $ymd = $this->normalizeDateYmd($v);
            if ($ymd !== null) {
                $out[] = $ymd;
            }
        }

        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /** @return list<array{from: string, to: string}> */
    private function normalizeDateRanges(mixed $raw): array
    {
        $items = [];

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            }
        } elseif (is_array($raw)) {
            $items = $raw;
        }

        $out = [];
        foreach ($items as $r) {
            if (!is_array($r)) {
                continue;
            }

            $from = $this->normalizeDateYmd($r['from'] ?? null);
            $to   = $this->normalizeDateYmd($r['to'] ?? null);

            if ($from !== null && $to !== null && $from <= $to) {
                $out[] = ['from' => $from, 'to' => $to];
            }
        }

        return $out;
    }

    private function normalizeDateYmd(mixed $raw): ?string
    {
        if ($raw === null) return null;

        $s = trim((string) $raw);
        if ($s === '') return null;

        $s = (string) preg_replace('/[T\s].*$/', '', $s);

        $formats = [
            'Y-m-d',
            'd.m.Y', 'd-m-Y', 'd/m/Y', 'm/d/Y',
            'd.m.y', 'd-m-y', 'd/m/y', 'm/d/y',
        ];

        foreach ($formats as $fmt) {
            if (Carbon::hasFormat($s, $fmt)) {
                try {
                    return Carbon::createFromFormat('!' . $fmt, $s)->format('Y-m-d');
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }
}
