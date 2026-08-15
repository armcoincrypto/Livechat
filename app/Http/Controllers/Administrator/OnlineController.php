<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator;

/**
 * API для админки: счётчики + постраничные списки.
 */
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OnlineController
{
    public function counters()
    {
        $window = max(30, (int) config('online_presence.window_seconds', 300));
        $from = CarbonImmutable::now()->subSeconds($window);

        $auth = (int) DB::table('online_sessions')
            ->where('type', 'user')
            ->where('last_seen', '>=', $from)
            ->count();

        $guests = (int) DB::table('online_sessions')
            ->where('type', 'guest')
            ->where('last_seen', '>=', $from)
            ->count();

        return response()->json([
            'data' => [
                'auth' => $auth,
                'guests' => $guests,
                'all' => $auth + $guests,
                'window' => $window,
            ],
        ]);
    }

    public function list(Request $request)
    {
        $type = trim((string) $request->query('type', 'all'));
        $perPage = max(1, min(200, (int) $request->integer('per_page', 50)));

        $window = max(30, (int) config('online_presence.window_seconds', 300));
        $from = \Carbon\CarbonImmutable::now()->subSeconds($window);

        $q = \Illuminate\Support\Facades\DB::table('online_sessions')
            ->where('last_seen', '>=', $from)
            ->orderByDesc('last_seen')
            ->select([
                'type',
                'user_id',
                'gid',
                'user_name',
                'user_email',
                'last_seen',
                'ua_hash', // если хочешь отдавать, иначе убери
            ]);

        if ($type === 'user' || $type === 'guest') {
            $q->where('type', $type);
        }

        $paginator = $q->paginate($perPage);

        $items = array_map(static function ($row) {
            return [
                'type' => (string) $row->type,
                'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                'gid' => $row->gid !== null ? (string) $row->gid : null,
                'user_name' => $row->user_name !== null ? (string) $row->user_name : null,
                'user_email' => $row->user_email !== null ? (string) $row->user_email : null,
                'last_seen' => $row->last_seen, // Carbon|string — ок
                'ua_hash' => !empty($row->ua_hash) ? base64_encode($row->ua_hash) : null,
            ];
        }, $paginator->items());

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function daily(Request $request)
    {
        $perPage = max(1, min(200, (int) $request->integer('per_page', 50)));
        $from = trim((string) $request->query('from', ''));
        $to   = trim((string) $request->query('to', ''));

        $q = DB::table('online_daily_stats')
            ->orderByDesc('day');

        if ($from !== '') {
            $q->where('day', '>=', $from);
        }
        if ($to !== '') {
            $q->where('day', '<=', $to);
        }

        $paginator = $q->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from'         => $paginator->firstItem(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'to'           => $paginator->lastItem(),
                'total'        => $paginator->total(),
            ],
        ]);
    }
}
