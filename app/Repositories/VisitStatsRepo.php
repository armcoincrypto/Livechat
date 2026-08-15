<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VisitStatsRepo
{
    /** Часы */
    public function hourly(int $lastHours = 24, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(1000, $perPage));

        $fromUtc = now('UTC')->subHours($lastHours)->startOfHour();

        $total = (int) DB::table('visit_counters_hour')
            ->where('bucket_hour', '>=', $fromUtc)
            ->count();

        $rows = DB::table('visit_counters_hour')
            ->where('bucket_hour', '>=', $fromUtc)
            ->orderBy('bucket_hour')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get(['bucket_hour','total','auth','guest']);

        return [
            'data' => $rows->map(fn($r) => [
                'bucket_hour' => (string) $r->bucket_hour,
                'total' => (int) $r->total,
                'auth'  => (int) $r->auth,
                'guest' => (int) $r->guest,
            ])->all(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil(max(1, $total) / $perPage),
            ],
        ];
    }

    /** Дни */
    public function daily(Carbon $from, Carbon $to, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(1000, $perPage));

        // контроллер должен отдавать UTC-границы, но на всякий случай:
        $fromUtc = $from->copy()->startOfDay()->utc();
        $toUtc   = $to->copy()->endOfDay()->utc();

        $total = (int) DB::table('visit_counters_hour')
            ->whereBetween('bucket_hour', [$fromUtc, $toUtc])
            ->selectRaw('COUNT(DISTINCT DATE(bucket_hour)) AS cnt')
            ->value('cnt');

        $rows = DB::table('visit_counters_hour')
            ->selectRaw("DATE(bucket_hour) AS d, SUM(total) AS total, SUM(auth) AS auth, SUM(guest) AS guest")
            ->whereBetween('bucket_hour', [$fromUtc, $toUtc])
            ->groupBy('d')
            ->orderBy('d')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $rows->map(fn($r) => [
                'day'   => (string) $r->d,
                'total' => (int) $r->total,
                'auth'  => (int) $r->auth,
                'guest' => (int) $r->guest,
            ])->all(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil(max(1, $total) / $perPage),
            ],
        ];
    }

    /** Месяцы */
    public function monthly(Carbon $from, Carbon $to, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(1000, $perPage));

        $fromUtc = $from->copy()->startOfMonth()->startOfDay()->utc();
        $toUtc   = $to->copy()->endOfMonth()->endOfDay()->utc();

        $total = (int) DB::table('visit_counters_hour')
            ->whereBetween('bucket_hour', [$fromUtc, $toUtc])
            ->selectRaw("COUNT(DISTINCT DATE_FORMAT(bucket_hour, '%Y-%m')) AS cnt")
            ->value('cnt');

        $rows = DB::table('visit_counters_hour')
            ->selectRaw("DATE_FORMAT(bucket_hour, '%Y-%m') AS ym, SUM(total) AS total, SUM(auth) AS auth, SUM(guest) AS guest")
            ->whereBetween('bucket_hour', [$fromUtc, $toUtc])
            ->groupBy('ym')
            ->orderBy('ym')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $rows->map(fn($r) => [
                'month' => (string) $r->ym,
                'total' => (int) $r->total,
                'auth'  => (int) $r->auth,
                'guest' => (int) $r->guest,
            ])->all(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil(max(1, $total) / $perPage),
            ],
        ];
    }

    /** ТОП пользователей */
    public function topUsers(Carbon $from, Carbon $to, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(1000, $perPage));

        $fromDay = $from->toDateString(); // YYYY-MM-DD
        $toDay   = $to->toDateString();

        $total = (int) DB::table('user_request_daily as urd')
            ->whereBetween('urd.bucket_day', [$fromDay, $toDay])
            ->distinct('urd.user_id')
            ->count('urd.user_id');

        $rows = DB::table('user_request_daily as urd')
            ->leftJoin('users as u', 'u.id', '=', 'urd.user_id')
            ->select('urd.user_id', DB::raw('SUM(urd.hits) AS hits'), 'u.name as name', 'u.email as email')
            ->whereBetween('urd.bucket_day', [$fromDay, $toDay])
            ->groupBy('urd.user_id', 'u.name', 'u.email')
            ->orderByDesc(DB::raw('SUM(urd.hits)'))
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $rows->map(fn($r) => [
                'user_id' => (int) $r->user_id,
                'name'    => (string) ($r->name ?? ''),
                'email'   => (string) ($r->email ?? ''),
                'hits'    => (int) $r->hits,
            ])->all(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil(max(1, $total) / $perPage),
            ],
        ];
    }

    /** Админы (роль admin ИЛИ permission allow_admin) */
    public function admins(Carbon $from, Carbon $to, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(1000, $perPage));

        $fromDay = $from->toDateString();
        $toDay   = $to->toDateString();

        $adminIds = User::query()
            ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
            ->orWhereHas('permissions', fn($q) => $q->where('name', 'allow_admin'))
            ->pluck('id')
            ->all();

        if (!$adminIds) {
            return [
                'data' => [],
                'pagination' => ['page'=>$page,'per_page'=>$perPage,'total'=>0,'last_page'=>1],
            ];
        }

        $total = (int) DB::table('user_request_daily as urd')
            ->whereBetween('urd.bucket_day', [$fromDay, $toDay])
            ->whereIn('urd.user_id', $adminIds)
            ->distinct('urd.user_id')
            ->count('urd.user_id');

        $rows = DB::table('user_request_daily as urd')
            ->leftJoin('users as u', 'u.id', '=', 'urd.user_id')
            ->select('u.id as user_id', 'u.name', 'u.email',
                DB::raw('SUM(urd.hits) as hits'),
                DB::raw('MAX(urd.last_seen) as last_seen')
            )
            ->whereBetween('urd.bucket_day', [$fromDay, $toDay])
            ->whereIn('urd.user_id', $adminIds)
            ->groupBy('u.id','u.name','u.email')
            ->orderByDesc(DB::raw('SUM(urd.hits)'))
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $rows->map(fn($r) => [
                'user_id' => (int) $r->user_id,
                'name' => (string) ($r->name ?? ''),
                'email'=> (string) ($r->email ?? ''),
                'hits' => (int) $r->hits,
                'last_seen' => $r->last_seen ? (string) $r->last_seen : null,
            ])->all(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil(max(1, $total) / $perPage),
            ],
        ];
    }
}
