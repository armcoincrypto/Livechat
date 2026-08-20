<?php

declare(strict_types=1);

namespace iEXPackages\Analytics\Services\Exchanges;

use App\Services\Analytics\ConversionCohortClassifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Read-only conversion funnel aggregates for operators.
 * Backend task statuses remain authoritative for financial stages.
 */
class ConversionFunnelService
{
    /** @var array<string, int> */
    public const PERIOD_DAYS = [
        '24h' => 1,
        '7d' => 7,
        '30d' => 30,
    ];

    public const MAX_BREAKDOWN_ROWS = 25;

    /**
     * @return array<string, mixed>
     */
    public function report(string $period = '7d', ?string $cohort = null): array
    {
        [$from, $to, $periodKey] = $this->resolvePeriod($period);
        $cohortFilter = $this->normalizeCohortFilter($cohort);

        $totals = $this->aggregateTotals($from, $to, $cohortFilter);
        $cohorts = $this->aggregateByCohort($from, $to);
        $pairs = $this->aggregatePairs($from, $to, $cohortFilter);
        $sources = $this->aggregateSources($from, $to, $cohortFilter);
        $sessions = $this->sessionStats($from, $to);

        $created = (int) ($totals['orders_created'] ?? 0);
        $paid = (int) ($totals['orders_payment_received'] ?? 0);
        $completed = (int) ($totals['orders_completed'] ?? 0);
        $expired = (int) ($totals['orders_expired'] ?? 0);

        return [
            'meta' => [
                'period' => $periodKey,
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
                'cohort_filter' => $cohortFilter ?? 'ALL',
                'read_only' => true,
                'authoritative' => 'tasks.status / tasks_status_log',
                'notes' => [
                    'tasks.is_bot is merchant polling flag, not traffic bot',
                    'FE calculator/quote/details stages are vendor-only (NOT_CURRENTLY_MEASURABLE in DB)',
                    'Consent refusal is policy, not a technical defect',
                ],
            ],
            'measurability' => [
                'eligible_web_sessions' => 'NOT_CURRENTLY_MEASURABLE',
                'attributed_sessions' => 'MEASURED',
                'orders_created' => 'MEASURED',
                'orders_payment_received' => 'MEASURED',
                'orders_processing' => 'MEASURED',
                'orders_completed' => 'MEASURED',
                'orders_expired' => 'MEASURED',
                'orders_cancelled' => 'MEASURED',
                'pair_breakdown' => 'MEASURED',
                'source_landing_breakdown' => 'PARTIAL',
                'locale_device' => 'NOT_CURRENTLY_MEASURABLE',
            ],
            'sessions' => $sessions,
            'totals' => $totals,
            'rates' => [
                'attributed_session_to_order' => $this->rate(
                    (int) $sessions['sessions_with_task'],
                    (int) $sessions['attributed_sessions']
                ),
                'order_create_to_payment' => $this->rate($paid, $created),
                'payment_to_completed' => $this->rate($completed, $paid),
                'order_create_to_completed' => $this->rate($completed, $created),
                'order_create_to_expired' => $this->rate($expired, $created),
                'order_create_attributed' => $this->rate(
                    (int) ($totals['orders_attributed'] ?? 0),
                    $created
                ),
            ],
            'cohorts' => $cohorts,
            'top_pairs' => $pairs,
            'sources' => $sources,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public function resolvePeriod(string $period): array
    {
        $period = strtolower(trim($period));
        if (! isset(self::PERIOD_DAYS[$period])) {
            throw new InvalidArgumentException('period must be one of: 24h, 7d, 30d');
        }

        $to = Carbon::now();
        $from = $to->copy()->subDays(self::PERIOD_DAYS[$period]);

        return [$from, $to, $period];
    }

    private function normalizeCohortFilter(?string $cohort): ?string
    {
        if ($cohort === null || $cohort === '' || strtoupper($cohort) === 'ALL') {
            return null;
        }

        $cohort = strtoupper(trim($cohort));
        if (! in_array($cohort, ConversionCohortClassifier::all(), true)) {
            throw new InvalidArgumentException('invalid cohort filter');
        }

        // INTERNAL_OPERATOR is reserved but never assigned from current fields.
        return $cohort;
    }

    /**
     * @return array<string, int|float>
     */
    private function aggregateTotals(Carbon $from, Carbon $to, ?string $cohortFilter): array
    {
        $cohortSql = ConversionCohortClassifier::sqlCaseExpression('t');
        $paidList = implode(',', ConversionCohortClassifier::PAYMENT_RECEIVED_OR_BEYOND);
        $procList = implode(',', ConversionCohortClassifier::PROCESSING);

        $sql = "
            SELECT
              COUNT(*) AS orders_created,
              SUM(t.session_attribution_id IS NOT NULL) AS orders_attributed,
              SUM(t.status = 2) AS orders_waiting_payment,
              SUM(t.status IN ({$paidList})) AS orders_payment_received,
              SUM(t.status IN ({$procList})) AS orders_processing,
              SUM(t.status = 4) AS orders_completed,
              SUM(t.status = 1) AS orders_expired,
              SUM(t.status = 6) AS orders_cancelled,
              SUM(t.status = 5) AS orders_rejected,
              SUM(t.is_spam = 1) AS flagged_spam,
              SUM(t.is_bot = 1) AS merchant_bot_flag
            FROM tasks t
            WHERE t.created_at BETWEEN ? AND ?
              AND t.deleted_at IS NULL
        ";

        $bindings = [$from->toDateTimeString(), $to->toDateTimeString()];

        if ($cohortFilter !== null) {
            $sql .= " AND ({$cohortSql}) = ?";
            $bindings[] = $cohortFilter;
        }

        $row = DB::selectOne($sql, $bindings);

        return [
            'orders_created' => (int) ($row->orders_created ?? 0),
            'orders_attributed' => (int) ($row->orders_attributed ?? 0),
            'orders_waiting_payment' => (int) ($row->orders_waiting_payment ?? 0),
            'orders_payment_received' => (int) ($row->orders_payment_received ?? 0),
            'orders_processing' => (int) ($row->orders_processing ?? 0),
            'orders_completed' => (int) ($row->orders_completed ?? 0),
            'orders_expired' => (int) ($row->orders_expired ?? 0),
            'orders_cancelled' => (int) ($row->orders_cancelled ?? 0),
            'orders_rejected' => (int) ($row->orders_rejected ?? 0),
            'flagged_spam' => (int) ($row->flagged_spam ?? 0),
            'merchant_bot_flag' => (int) ($row->merchant_bot_flag ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function aggregateByCohort(Carbon $from, Carbon $to): array
    {
        $cohortSql = ConversionCohortClassifier::sqlCaseExpression('t');
        $paidList = implode(',', ConversionCohortClassifier::PAYMENT_RECEIVED_OR_BEYOND);

        $rows = DB::select("
            SELECT
              {$cohortSql} AS cohort,
              COUNT(*) AS orders_created,
              SUM(t.session_attribution_id IS NOT NULL) AS orders_attributed,
              SUM(t.status IN ({$paidList})) AS orders_payment_received,
              SUM(t.status = 4) AS orders_completed,
              SUM(t.status = 1) AS orders_expired,
              SUM(t.status = 6) AS orders_cancelled,
              SUM(t.status = 2) AS orders_waiting_payment
            FROM tasks t
            WHERE t.created_at BETWEEN ? AND ?
              AND t.deleted_at IS NULL
            GROUP BY cohort
            ORDER BY orders_created DESC
        ", [$from->toDateTimeString(), $to->toDateTimeString()]);

        return array_map(static function ($r) {
            $created = (int) $r->orders_created;

            return [
                'cohort' => (string) $r->cohort,
                'orders_created' => $created,
                'orders_attributed' => (int) $r->orders_attributed,
                'orders_payment_received' => (int) $r->orders_payment_received,
                'orders_completed' => (int) $r->orders_completed,
                'orders_expired' => (int) $r->orders_expired,
                'orders_cancelled' => (int) $r->orders_cancelled,
                'orders_waiting_payment' => (int) $r->orders_waiting_payment,
                'attr_rate' => $created > 0 ? round(100 * (int) $r->orders_attributed / $created, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function aggregatePairs(Carbon $from, Carbon $to, ?string $cohortFilter): array
    {
        $cohortSql = ConversionCohortClassifier::sqlCaseExpression('t');
        $paidList = implode(',', ConversionCohortClassifier::PAYMENT_RECEIVED_OR_BEYOND);
        $limit = self::MAX_BREAKDOWN_ROWS;

        $sql = "
            SELECT
              c1.designation_xml AS send,
              c2.designation_xml AS receive,
              COUNT(*) AS orders_created,
              SUM(t.session_attribution_id IS NOT NULL) AS orders_attributed,
              SUM(t.status IN ({$paidList})) AS orders_payment_received,
              SUM(t.status = 4) AS orders_completed,
              SUM(t.status = 1) AS orders_expired,
              SUM(t.status = 2) AS orders_waiting_payment
            FROM tasks t
            JOIN direction_exchange de ON de.id = t.id_direction_exchange
            JOIN currencies c1 ON c1.id = de.id_currency1
            JOIN currencies c2 ON c2.id = de.id_currency2
            WHERE t.created_at BETWEEN ? AND ?
              AND t.deleted_at IS NULL
        ";

        $bindings = [$from->toDateTimeString(), $to->toDateTimeString()];

        if ($cohortFilter !== null) {
            $sql .= " AND ({$cohortSql}) = ?";
            $bindings[] = $cohortFilter;
        }

        $sql .= " GROUP BY send, receive ORDER BY orders_created DESC LIMIT {$limit}";

        $rows = DB::select($sql, $bindings);

        return array_map(static function ($r) {
            $created = (int) $r->orders_created;
            $expired = (int) $r->orders_expired;

            return [
                'pair' => $r->send.'->'.$r->receive,
                'send' => (string) $r->send,
                'receive' => (string) $r->receive,
                'orders_created' => $created,
                'orders_attributed' => (int) $r->orders_attributed,
                'orders_payment_received' => (int) $r->orders_payment_received,
                'orders_completed' => (int) $r->orders_completed,
                'orders_expired' => $expired,
                'orders_waiting_payment' => (int) $r->orders_waiting_payment,
                'expire_rate' => $created > 0 ? round(100 * $expired / $created, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * Attributed orders only (PARTIAL — unattributed orders have no source).
     *
     * @return list<array<string, mixed>>
     */
    private function aggregateSources(Carbon $from, Carbon $to, ?string $cohortFilter): array
    {
        $cohortSql = ConversionCohortClassifier::sqlCaseExpression('t');
        $paidList = implode(',', ConversionCohortClassifier::PAYMENT_RECEIVED_OR_BEYOND);
        $limit = self::MAX_BREAKDOWN_ROWS;

        $sql = "
            SELECT
              CASE
                WHEN sa.first_utm_source IS NOT NULL AND sa.first_utm_source != '' THEN sa.first_utm_source
                WHEN sa.first_referrer LIKE '%bestchange%' THEN 'bestchange_ref'
                WHEN sa.first_referrer IS NOT NULL AND sa.first_referrer != '' THEN 'other_referrer'
                ELSE 'direct_or_unknown'
              END AS source_class,
              COALESCE(NULLIF(sa.first_landing_path, ''), '(none)') AS landing_path,
              COUNT(*) AS orders_created,
              SUM(t.status IN ({$paidList})) AS orders_payment_received,
              SUM(t.status = 4) AS orders_completed,
              SUM(t.status = 1) AS orders_expired
            FROM tasks t
            JOIN session_attributions sa ON sa.id = t.session_attribution_id
            WHERE t.created_at BETWEEN ? AND ?
              AND t.deleted_at IS NULL
        ";

        $bindings = [$from->toDateTimeString(), $to->toDateTimeString()];

        if ($cohortFilter !== null) {
            $sql .= " AND ({$cohortSql}) = ?";
            $bindings[] = $cohortFilter;
        }

        $sql .= " GROUP BY source_class, landing_path ORDER BY orders_created DESC LIMIT {$limit}";

        $rows = DB::select($sql, $bindings);

        return array_map(static function ($r) {
            return [
                'source_class' => (string) $r->source_class,
                'landing_path' => (string) $r->landing_path,
                'orders_created' => (int) $r->orders_created,
                'orders_payment_received' => (int) $r->orders_payment_received,
                'orders_completed' => (int) $r->orders_completed,
                'orders_expired' => (int) $r->orders_expired,
                'measurability' => 'PARTIAL',
            ];
        }, $rows);
    }

    /**
     * @return array<string, int|string>
     */
    private function sessionStats(Carbon $from, Carbon $to): array
    {
        $attributed = (int) DB::table('session_attributions')
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->count();

        $withTask = (int) (DB::selectOne("
            SELECT COUNT(DISTINCT sa.id) AS c
            FROM session_attributions sa
            JOIN tasks t ON t.session_attribution_id = sa.id
            WHERE sa.created_at BETWEEN ? AND ?
        ", [$from->toDateTimeString(), $to->toDateTimeString()])->c ?? 0);

        return [
            'attributed_sessions' => $attributed,
            'sessions_with_task' => $withTask,
            'eligible_web_sessions' => 'NOT_CURRENTLY_MEASURABLE',
            'unattributed_share_note' => 'Consent refusal and pre-ingest traffic are not stored as negative events',
        ];
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round(100 * $numerator / $denominator, 1);
    }
}
