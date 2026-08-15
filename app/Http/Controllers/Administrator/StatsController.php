<?php

namespace App\Http\Controllers\Administrator;

use App\Repositories\VisitStatsRepo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StatsController
{
    public function __construct(private readonly VisitStatsRepo $repo) {}

    /** /api/stats/visits/hourly?hours=24&page=1&per_page=20 */
    public function visitsHourly(Request $req)
    {
        $hours = max(1, min(24 * 31, (int) $req->query('hours', 24)));
        $page  = (int) $req->query('page', 1);
        $per   = (int) $req->query('per_page', 20);

        return response()->json($this->repo->hourly($hours, $page, $per));
    }

    /** /api/stats/visits/daily?from=2025-11-01&to=2025-11-06&page=1&per_page=20 */
    public function visitsDaily(Request $req)
    {
        $from = Carbon::parse($req->query('from', now()->subDays(7)->toDateString()));
        $to   = Carbon::parse($req->query('to', now()->toDateString()));
        $page = (int) $req->query('page', 1);
        $per  = (int) $req->query('per_page', 20);

        return response()->json($this->repo->daily($from, $to, $page, $per));
    }

    /** /api/stats/visits/monthly?from=2025-01-01&to=2025-12-31&page=1&per_page=20 */
    public function visitsMonthly(Request $req)
    {
        $from = Carbon::parse($req->query('from', now()->subMonths(6)->startOfMonth()->toDateString()));
        $to   = Carbon::parse($req->query('to', now()->endOfMonth()->toDateString()));
        $page = (int) $req->query('page', 1);
        $per  = (int) $req->query('per_page', 20);

        return response()->json($this->repo->monthly($from, $to, $page, $per));
    }

    /** /api/stats/users/{id}/daily?from=2025-11-01&to=2025-11-06&page=1&per_page=20 */
    public function userDaily(Request $req)
    {
        $from = Carbon::parse($req->query('from', now()->subDays(30)->toDateString()));
        $to   = Carbon::parse($req->query('to', now()->toDateString()));
        $page = (int) $req->query('page', 1);
        $per  = (int) $req->query('per_page', 20);

        return response()->json($this->repo->userDaily($from, $to, $page, $per));
    }

    /** /api/stats/users/top?from=2025-11-01&to=2025-11-06&page=1&per_page=20 */
    public function topUsers(Request $req)
    {
        $from = Carbon::parse($req->query('from', now()->subDays(30)->toDateString()));
        $to   = Carbon::parse($req->query('to', now()->toDateString()));
        $page = (int) $req->query('page', 1);
        $per  = (int) $req->query('per_page', 20);

        return response()->json($this->repo->topUsers($from, $to, $page, $per));
    }
}
