<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisitRecorder
{
    /**
     * Записывает визит (UTC):
     *  - visit_counters_hour: агрегат по часу
     *  - user_request_daily: агрегат по дню для авторизованного пользователя
     *
     * Важно:
     * - Этот класс НЕ должен вызываться на каждый запрос без фильтрации/троттлинга в middleware.
     * - Таблицы должны иметь уникальные индексы:
     *   visit_counters_hour: UNIQUE(bucket_hour)
     *   user_request_daily : UNIQUE(bucket_day, user_id)
     */
    public function record(Request $request): void
    {
        // 1) Единое время в UTC (immutable, без лишних now()/format() вызовов)
        $now = CarbonImmutable::now('UTC');
        $hourBucket = $now->startOfHour()->format('Y-m-d H:i:s'); // YYYY-MM-DD HH:00:00
        $dayBucket  = $now->toDateString();                        // YYYY-MM-DD
        $nowStr     = $now->format('Y-m-d H:i:s');

        $user = $request->user();
        $isAuth = $user !== null;

        // 2) Общие счётчики по часу (высококонкурентная строка -> только атомарные инкременты)
        DB::table('visit_counters_hour')->upsert(
            values: [[
                'bucket_hour' => $hourBucket,
                'total'       => 1,
                'auth'        => $isAuth ? 1 : 0,
                'guest'       => $isAuth ? 0 : 1,
                'created_at'  => $nowStr,
                'updated_at'  => $nowStr,
            ]],
            uniqueBy: ['bucket_hour'],
            update: [
                'total'      => DB::raw('total + 1'),
                'auth'       => $isAuth ? DB::raw('auth + 1')  : DB::raw('auth'),
                'guest'      => $isAuth ? DB::raw('guest')     : DB::raw('guest + 1'),
                'updated_at' => $nowStr,
            ]
        );

        // 3) Авторизованные: запросы/посещения по дням
        if (!$isAuth) {
            return;
        }

        $userId = (int) $user->getAuthIdentifier();
        if ($userId <= 0) {
            return;
        }

        DB::table('user_request_daily')->upsert(
            values: [[
                'bucket_day' => $dayBucket, // YYYY-MM-DD (UTC)
                'user_id'    => $userId,
                'hits'       => 1,
                'last_seen'  => $nowStr,
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ]],
            uniqueBy: ['bucket_day', 'user_id'],
            update: [
                'hits'       => DB::raw('hits + 1'),
                'last_seen'  => $nowStr,
                'updated_at' => $nowStr,
            ]
        );
    }
}
