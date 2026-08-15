<?php

namespace App\Http\Controllers\Administrator\Bonuses;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use iEXPackages\ReferralSystem\Models\ReferralAuditLog;

class ReferralLogsController extends Controller
{
    /**
     * Логи реферальной системы (новая таблица referral_audit_logs) + аналитика.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));

        $baseQuery = ReferralAuditLog::query();

        $this->applyFilters($baseQuery, $request);

        $logs = (clone $baseQuery)
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = $this->hydrateUsersForPage($logs->items());

        $stats = $this->buildStats($baseQuery, $request);

        return response()->json([
            'items' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page'    => $logs->lastPage(),
                    'per_page'     => $logs->perPage(),
                    'total'        => $logs->total(),
                    'from'         => $logs->firstItem(),
                    'to'           => $logs->lastItem(),
                ],
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Фильтры для ReferralAuditLog.
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        // event (одно/много)
        if ($request->filled('event')) {
            $events = (array) $request->get('event');
            $events = array_values(array_filter(array_map('trim', $events)));
            if ($events !== []) {
                $query->whereIn('event', $events);
            }
        }

        // level (info|warning|error)
        if ($request->filled('level')) {
            $levels = (array) $request->get('level');
            $levels = array_values(array_filter(array_map('trim', $levels)));
            if ($levels !== []) {
                $query->whereIn('level', $levels);
            }
        }

        // partner_user_id
        if ($request->filled('partner_user_id')) {
            $query->where('partner_user_id', (int) $request->integer('partner_user_id'));
        }

        // client_user_id
        if ($request->filled('client_user_id')) {
            $query->where('client_user_id', (int) $request->integer('client_user_id'));
        }

        // task_id
        if ($request->filled('task_id')) {
            $query->where('task_id', (int) $request->integer('task_id'));
        }

        // referral_link_id
        if ($request->filled('referral_link_id')) {
            $query->where('referral_link_id', (int) $request->integer('referral_link_id'));
        }

        // referral_program_id
        if ($request->filled('referral_program_id')) {
            $query->where('referral_program_id', (int) $request->integer('referral_program_id'));
        }

        // trace_id
        if ($request->filled('trace_id')) {
            $query->where('trace_id', trim((string) $request->get('trace_id')));
        }

        // период
        if ($request->filled('from')) {
            $from = $this->safeParseDate($request->get('from'))?->startOfDay();
            if ($from) {
                $query->where('created_at', '>=', $from);
            }
        }

        if ($request->filled('to')) {
            $to = $this->safeParseDate($request->get('to'))?->endOfDay();
            if ($to) {
                $query->where('created_at', '<=', $to);
            }
        }

        // поиск по message (и опционально по event/trace)
        if ($request->filled('search')) {
            $search = trim((string) $request->get('search'));

            $query->where(function (Builder $q) use ($search) {
                $q->where('message', 'LIKE', "%{$search}%")
                    ->orWhere('event', 'LIKE', "%{$search}%")
                    ->orWhere('trace_id', 'LIKE', "%{$search}%");
            });
        }
    }

    /**
     * Аналитика по тем же фильтрам.
     */
    protected function buildStats(Builder $baseQuery, Request $request): array
    {
        $statsQuery = clone $baseQuery;

        // Если период не задан — ограничим 90 днями, чтобы не убить базу
        if (!$request->filled('from') && !$request->filled('to')) {
            $statsQuery->where('created_at', '>=', now()->subDays(90));
        }

        $total = (clone $statsQuery)->count();

        $today = (clone $statsQuery)
            ->whereDate('created_at', today())
            ->count();

        $last7 = (clone $statsQuery)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $last30 = (clone $statsQuery)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // by_event
        $byEventRaw = (clone $statsQuery)
            ->selectRaw('event, COUNT(*) as cnt')
            ->groupBy('event')
            ->orderByDesc('cnt')
            ->get();

        $byEvent = $byEventRaw->map(function ($row) use ($total) {
            $share = $total > 0 ? round(((int) $row->cnt) / $total * 100, 1) : 0;
            return [
                'event'  => (string) $row->event,
                'count'  => (int) $row->cnt,
                'share'  => $share,
            ];
        })->values();

        // by_level
        $byLevelRaw = (clone $statsQuery)
            ->selectRaw('level, COUNT(*) as cnt')
            ->groupBy('level')
            ->orderByDesc('cnt')
            ->get();

        $byLevel = $byLevelRaw->map(function ($row) use ($total) {
            $share = $total > 0 ? round(((int) $row->cnt) / $total * 100, 1) : 0;
            return [
                'level'  => (string) $row->level,
                'count'  => (int) $row->cnt,
                'share'  => $share,
            ];
        })->values();

        // топ партнёров по активности
        $topPartnersRaw = (clone $statsQuery)
            ->selectRaw('partner_user_id, COUNT(*) as cnt')
            ->whereNotNull('partner_user_id')
            ->groupBy('partner_user_id')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        $partnerUsers = User::whereIn('id', $topPartnersRaw->pluck('partner_user_id')->filter())
            ->get()
            ->keyBy('id');

        $topPartners = $topPartnersRaw->map(function ($row) use ($partnerUsers) {
            $u = $partnerUsers->get((int) $row->partner_user_id);
            return [
                'id'    => (int) $row->partner_user_id,
                'name'  => $u?->name,
                'email' => $u?->email,
                'count' => (int) $row->cnt,
            ];
        })->values();

        // топ клиентов (рефералов) по активности
        $topClientsRaw = (clone $statsQuery)
            ->selectRaw('client_user_id, COUNT(*) as cnt')
            ->whereNotNull('client_user_id')
            ->groupBy('client_user_id')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        $clientUsers = User::whereIn('id', $topClientsRaw->pluck('client_user_id')->filter())
            ->get()
            ->keyBy('id');

        $topClients = $topClientsRaw->map(function ($row) use ($clientUsers) {
            $u = $clientUsers->get((int) $row->client_user_id);
            return [
                'id'    => (int) $row->client_user_id,
                'name'  => $u?->name,
                'email' => $u?->email,
                'count' => (int) $row->cnt,
            ];
        })->values();

        // timeline по дням
        $timeline = (clone $statsQuery)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date'  => (string) $row->date,
                'count' => (int) $row->cnt,
            ])
            ->values();

        return [
            'total'        => $total,
            'today'        => $today,
            'last_7_days'  => $last7,
            'last_30_days' => $last30,

            'by_event'     => $byEvent,
            'by_level'     => $byLevel,

            'top_partners' => $topPartners,
            'top_clients'  => $topClients,

            'timeline'     => $timeline,
        ];
    }

    /**
     * Подмешиваем partner/client user данные для текущей страницы (без N+1).
     *
     * @param array<int,ReferralAuditLog> $pageItems
     * @return array<int,array<string,mixed>>
     */
    protected function hydrateUsersForPage(array $pageItems): array
    {
        $partnerIds = [];
        $clientIds  = [];

        foreach ($pageItems as $log) {
            if (!empty($log->partner_user_id)) {
                $partnerIds[] = (int) $log->partner_user_id;
            }
            if (!empty($log->client_user_id)) {
                $clientIds[] = (int) $log->client_user_id;
            }
        }

        $partnerIds = array_values(array_unique($partnerIds));
        $clientIds  = array_values(array_unique($clientIds));

        $partners = $partnerIds
            ? User::whereIn('id', $partnerIds)->get()->keyBy('id')
            : collect();

        $clients = $clientIds
            ? User::whereIn('id', $clientIds)->get()->keyBy('id')
            : collect();

        $out = [];

        foreach ($pageItems as $log) {
            $partner = $partners->get((int) $log->partner_user_id);
            $client  = $clients->get((int) $log->client_user_id);

            $out[] = [
                'id' => (int) $log->id,
                'event' => (string) $log->event,
                'level' => (string) $log->level,
                'message' => (string) $log->message,
                'trace_id' => $log->trace_id,

                'partner_user_id' => $log->partner_user_id,
                'client_user_id' => $log->client_user_id,
                'referral_link_id' => $log->referral_link_id,
                'referral_program_id' => $log->referral_program_id,
                'task_id' => $log->task_id,

                'partner' => $partner ? [
                    'id' => (int) $partner->id,
                    'name' => (string) $partner->name,
                    'email' => (string) $partner->email,
                ] : null,

                'client' => $client ? [
                    'id' => (int) $client->id,
                    'name' => (string) $client->name,
                    'email' => (string) $client->email,
                ] : null,

                'meta' => $log->meta, // cast array/json
                'created_at' => $log->created_at?->toIso8601String(),
            ];
        }

        return $out;
    }

    protected function safeParseDate(mixed $value): ?Carbon
    {
        try {
            $s = trim((string) $value);
            if ($s === '') {
                return null;
            }
            return Carbon::parse($s);
        } catch (\Throwable) {
            return null;
        }
    }
}
