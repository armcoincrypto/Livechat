#!/usr/bin/env php
<?php
/**
 * Read-only forensic audit: BestChange partner reward root cause (P-BESTCHANGE-2)
 *
 * Usage:
 *   php scripts/audit-bestchange-referral-root-cause.php
 *   php scripts/audit-bestchange-referral-root-cause.php --json
 *
 * Safety: SELECT-only queries. No writes.
 */

declare(strict_types=1);

const PARTNER_USER_ID = 436;
const REFERRAL_CODE = 'MLyn';
const CUTOFF_DATE = '2026-03-21 15:18:48';
const ANOMALY_TASK_ID = 2828;

function loadEnv(string $path): array
{
    if (!is_readable($path)) {
        fwrite(STDERR, "Cannot read .env at {$path}\n");
        exit(1);
    }
    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $vars[trim($k)] = trim($v, " \t\"'");
    }
    return $vars;
}

function pdo(array $env): PDO
{
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3306';
    $db   = $env['DB_DATABASE'] ?? '';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    $dsn  = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function q(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function q1(PDO $pdo, string $sql, array $params = []): mixed
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function statusLabel(int $id): string
{
    return match ($id) {
        1 => 'EXPIRED',
        2 => 'PENDING_PAYMENT',
        3 => 'WAITING_HANDLE',
        4 => 'COMPLETED',
        5 => 'REJECTED',
        6 => 'CANCELED',
        7 => 'PAID',
        8 => 'FROZEN',
        9 => 'PROCESSING_PAYMENT',
        10 => 'INVALID',
        11 => 'DELETED',
        12 => 'CHECK_PAYMENT',
        13 => 'MERCHANT_CONFIRMATION',
        14 => 'AUTO_PAYOUT_ERROR',
        15 => 'PAYOUT_IN_PROGRESS',
        16 => 'PAYOUT_QUEUE',
        default => "UNKNOWN({$id})",
    };
}

function estimateCommissionUsd(float $givePrice, float $profitPartnerPct, float $partnerPct = 0.30): float
{
    // Rough estimate: base ≈ give in USD for stablecoin; profit = base * profit_partner; commission = profit * partner%
    // Used for screening only — not financial truth.
    if ($givePrice <= 0 || $profitPartnerPct <= 0) {
        return 0.0;
    }
    $exchangeProfit = $givePrice * $profitPartnerPct;
    return round($exchangeProfit * $partnerPct, 2);
}

$root = dirname(__DIR__);
$env  = loadEnv($root . '/.env');
$pdo  = pdo($env);
$json = in_array('--json', $argv ?? [], true);

$report = [
    'generated_at' => gmdate('c'),
    'partner_user_id' => PARTNER_USER_ID,
    'referral_code' => REFERRAL_CODE,
    'cutoff_date' => CUTOFF_DATE,
];

// --- Core counts ---
$report['totals'] = [
    'attributed_tasks' => (int) q1($pdo, "SELECT COUNT(*) FROM tasks WHERE referral_hash = ?", [REFERRAL_CODE]),
    'completed_status_4' => (int) q1($pdo, "SELECT COUNT(*) FROM tasks WHERE referral_hash = ? AND status = 4", [REFERRAL_CODE]),
    'paid_status_7' => (int) q1($pdo, "SELECT COUNT(*) FROM tasks WHERE referral_hash = ? AND status = 7", [REFERRAL_CODE]),
    'paid_status_7_with_profit' => (int) q1($pdo, "
        SELECT COUNT(*) FROM tasks t
        JOIN direction_exchange de ON de.id = t.id_direction_exchange
        WHERE t.referral_hash = ? AND t.status = 7 AND de.profit_partner > 0
    ", [REFERRAL_CODE]),
    'paid_status_7_is_bot_1' => (int) q1($pdo, "SELECT COUNT(*) FROM tasks WHERE referral_hash = ? AND status = 7 AND is_bot = 1", [REFERRAL_CODE]),
    'commission_rows' => (int) q1($pdo, "SELECT COUNT(*) FROM referral_log WHERE id_user = ?", [PARTNER_USER_ID]),
    'commission_sum_usd' => (float) q1($pdo, "SELECT COALESCE(SUM(bonus_number),0) FROM referral_log WHERE id_user = ?", [PARTNER_USER_ID]),
    'completed_after_cutoff' => (int) q1($pdo, "
        SELECT COUNT(*) FROM tasks WHERE referral_hash = ? AND status = 4 AND completed_at > ?
    ", [REFERRAL_CODE, CUTOFF_DATE]),
    'global_commission_after_cutoff' => (int) q1($pdo, "SELECT COUNT(*) FROM referral_log WHERE created_at > ?", [CUTOFF_DATE]),
    'global_completed_after_cutoff' => (int) q1($pdo, "SELECT COUNT(*) FROM tasks WHERE status = 4 AND completed_at > ?", [CUTOFF_DATE]),
];

// --- Status distribution ---
$statusRows = q($pdo, "
    SELECT status, COUNT(*) AS cnt, MIN(created_at) AS first_at, MAX(created_at) AS last_at
    FROM tasks WHERE referral_hash = ?
    GROUP BY status ORDER BY cnt DESC
", [REFERRAL_CODE]);
$report['status_distribution'] = array_map(static fn ($r) => [
    'status' => (int) $r['status'],
    'label' => statusLabel((int) $r['status']),
    'count' => (int) $r['cnt'],
    'first_at' => $r['first_at'],
    'last_at' => $r['last_at'],
], $statusRows);

// --- Settings ---
$report['settings'] = [
    'enabled_referral_system' => q1($pdo, "SELECT value FROM dynamic_config_settings WHERE `key` = 'enabled_referral_system' LIMIT 1"),
    'type_partner_deductions' => q1($pdo, "SELECT value FROM dynamic_config_settings WHERE `key` = 'type_partner_deductions' LIMIT 1"),
    'is_enabled_autopay_cron' => q1($pdo, "SELECT value FROM dynamic_config_settings WHERE `key` = 'is_enabled_autopay_cron' LIMIT 1"),
    'referral_hold_days' => q1($pdo, "SELECT value FROM dynamic_config_settings WHERE `key` = 'referral_hold_days' LIMIT 1"),
];

// --- Timeline ---
$report['timeline'] = [
    'first_attributed_task' => q1($pdo, "SELECT MIN(created_at) FROM tasks WHERE referral_hash = ?", [REFERRAL_CODE]),
    'first_commission' => q1($pdo, "SELECT MIN(created_at) FROM referral_log WHERE id_user = ?", [PARTNER_USER_ID]),
    'last_commission' => q1($pdo, "SELECT MAX(created_at) FROM referral_log WHERE id_user = ?", [PARTNER_USER_ID]),
    'last_completed_status_4' => q1($pdo, "SELECT MAX(completed_at) FROM tasks WHERE referral_hash = ? AND status = 4", [REFERRAL_CODE]),
    'first_paid_stuck_status_7_after_last_complete' => q1($pdo, "
        SELECT MIN(created_at) FROM tasks
        WHERE referral_hash = ? AND status = 7
          AND created_at > (SELECT COALESCE(MAX(completed_at),'1970-01-01') FROM tasks WHERE referral_hash = ? AND status = 4)
    ", [REFERRAL_CODE, REFERRAL_CODE]),
    'withdrawal_date' => q1($pdo, "SELECT created_at FROM withdrawal_request WHERE id_user = ? ORDER BY created_at DESC LIMIT 1", [PARTNER_USER_ID]),
];

// --- Recent tasks (20) ---
$report['recent_tasks'] = q($pdo, "
    SELECT t.id, t.public_id, t.created_at, t.updated_at, t.completed_at, t.status,
           t.referral_hash, t.is_bot, t.is_pay_referral_bonus,
           t.give_price, t.receiving_price, de.profit_partner,
           cc1.name AS from_code, cc2.name AS to_code,
           rl.bonus_number AS actual_commission
    FROM tasks t
    JOIN direction_exchange de ON de.id = t.id_direction_exchange
    LEFT JOIN currencies c1 ON c1.id = de.id_currency1
    LEFT JOIN currencies c2 ON c2.id = de.id_currency2
    LEFT JOIN code_currency cc1 ON cc1.id = c1.id_code_currency
    LEFT JOIN code_currency cc2 ON cc2.id = c2.id_code_currency
    LEFT JOIN referral_log rl ON rl.id_task = t.id AND rl.id_user = ?
    WHERE t.referral_hash = ?
    ORDER BY t.created_at DESC LIMIT 20
", [PARTNER_USER_ID, REFERRAL_CODE]);

// --- Missing commission candidates: status 7 + profit > 0 + no ledger ---
$missing = q($pdo, "
    SELECT t.id, t.created_at, t.status, t.give_price, t.receiving_price,
           de.profit_partner, cc1.name AS from_code,
           rl.bonus_number AS actual_commission
    FROM tasks t
    JOIN direction_exchange de ON de.id = t.id_direction_exchange
    LEFT JOIN currencies c1 ON c1.id = de.id_currency1
    LEFT JOIN code_currency cc1 ON cc1.id = c1.id_code_currency
    LEFT JOIN referral_log rl ON rl.id_task = t.id AND rl.id_user = ?
    WHERE t.referral_hash = ? AND t.status = 7 AND de.profit_partner > 0
    ORDER BY t.created_at DESC LIMIT 50
", [PARTNER_USER_ID, REFERRAL_CODE]);

$report['missing_commission_candidates'] = [];
foreach ($missing as $row) {
    $give = (float) $row['give_price'];
    $profitPct = (float) $row['profit_partner'];
    $expected = estimateCommissionUsd($give, $profitPct);
    $report['missing_commission_candidates'][] = [
        'task_id' => (int) $row['id'],
        'date' => $row['created_at'],
        'status' => statusLabel((int) $row['status']),
        'from_code' => $row['from_code'],
        'give_price' => $give,
        'profit_partner' => $profitPct,
        'expected_30_percent_screening' => $expected,
        'actual_commission' => $row['actual_commission'],
        'issue' => $row['actual_commission'] === null
            ? 'PAID(status 7) — payout never completed to status 4; ReferralBonusService not triggered'
            : 'unexpected ledger row',
    ];
}

// --- Abnormal commissions ---
$report['abnormal_commissions'] = q($pdo, "
    SELECT rl.id, rl.id_task, rl.bonus_number, rl.created_at,
           t.give_price, t.receiving_price, cc1.name AS from_code, cc2.name AS to_code
    FROM referral_log rl
    JOIN tasks t ON t.id = rl.id_task
    JOIN direction_exchange de ON de.id = t.id_direction_exchange
    LEFT JOIN currencies c1 ON c1.id = de.id_currency1
    LEFT JOIN currencies c2 ON c2.id = de.id_currency2
    LEFT JOIN code_currency cc1 ON cc1.id = c1.id_code_currency
    LEFT JOIN code_currency cc2 ON cc2.id = c2.id_code_currency
    WHERE rl.id_user = ? AND rl.bonus_number > 100
    ORDER BY rl.bonus_number DESC
", [PARTNER_USER_ID]);

// --- Task 2828 breakdown ---
$report['task_2828'] = q($pdo, "
    SELECT t.*, de.profit_partner, de.id AS direction_id,
           cc1.name AS from_code, cc1.internal_rate AS from_internal_rate,
           cc2.name AS to_code,
           rl.bonus_number, rl.current_percent, rl.text
    FROM tasks t
    JOIN direction_exchange de ON de.id = t.id_direction_exchange
    LEFT JOIN currencies c1 ON c1.id = de.id_currency1
    LEFT JOIN currencies c2 ON c2.id = de.id_currency2
    LEFT JOIN code_currency cc1 ON cc1.id = c1.id_code_currency
    LEFT JOIN code_currency cc2 ON cc2.id = c2.id_code_currency
    LEFT JOIN referral_log rl ON rl.id_task = t.id AND rl.id_user = ?
    WHERE t.id = ?
", [PARTNER_USER_ID, ANOMALY_TASK_ID]);

$tsl2828 = q($pdo, "
    SELECT old_status, new_status, created_at FROM tasks_status_log
    WHERE id_task = ? ORDER BY created_at
", [ANOMALY_TASK_ID]);
$report['task_2828_status_log'] = array_map(static fn ($r) => [
    'from' => statusLabel((int) $r['old_status']),
    'to' => statusLabel((int) $r['new_status']),
    'at' => $r['created_at'],
], $tsl2828);

// --- Global commission by date (last 30 days with any activity) ---
$report['global_commission_by_date'] = q($pdo, "
    SELECT DATE(created_at) AS d, COUNT(*) AS cnt, SUM(bonus_number) AS sum_bonus
    FROM referral_log
    GROUP BY DATE(created_at)
    ORDER BY d DESC LIMIT 15
");

// --- Verdict logic ---
$verdict = 'BESTCHANGE_INCONCLUSIVE';
$reasons = [];

if ($report['totals']['completed_after_cutoff'] === 0 && $report['totals']['paid_status_7'] > 0) {
    $reasons[] = 'No status-4 completions after cutoff; large backlog in status 7 (PAID).';
}
if ((int) ($report['settings']['is_enabled_autopay_cron'] ?? 0) === 0) {
    $reasons[] = 'Autopay cron disabled (is_enabled_autopay_cron=0) — paid orders never enter payout queue (16).';
}
if ($report['totals']['global_commission_after_cutoff'] === 0) {
    $reasons[] = 'Zero referral_log rows globally after cutoff — systemic commission trigger stall, not BestChange-only.';
}
if ($report['totals']['paid_status_7_is_bot_1'] > 0) {
    $reasons[] = sprintf('%d paid BestChange orders have is_bot=1 (polling merchant flow).', $report['totals']['paid_status_7_is_bot_1']);
}
if (!empty($report['abnormal_commissions'])) {
    $reasons[] = 'Historical task #2828 over-credit corrupts lifetime totals (separate from current stall).';
}

if ($report['totals']['completed_after_cutoff'] === 0
    && (int) ($report['settings']['is_enabled_autopay_cron'] ?? 0) === 0
    && $report['totals']['paid_status_7_with_profit'] > 0) {
    $verdict = 'BESTCHANGE_COMMISSION_EVENT_NOT_RUNNING';
} elseif ($report['totals']['completed_after_cutoff'] === 0) {
    $verdict = 'BESTCHANGE_NO_RECENT_COMPLETED_EXCHANGES';
}

$report['verdict'] = $verdict;
$report['verdict_reasons'] = $reasons;

if ($json) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

echo "=== BestChange Partner Reward Root Cause Audit ===\n\n";
echo "Verdict: {$verdict}\n\n";
foreach ($reasons as $r) {
    echo "  - {$r}\n";
}
echo "\n--- Totals ---\n";
foreach ($report['totals'] as $k => $v) {
    echo str_pad($k, 40) . $v . "\n";
}
echo "\n--- Settings ---\n";
foreach ($report['settings'] as $k => $v) {
    echo str_pad($k, 40) . json_encode($v) . "\n";
}
echo "\n--- Timeline ---\n";
foreach ($report['timeline'] as $k => $v) {
    echo str_pad($k, 45) . ($v ?? 'NULL') . "\n";
}
echo "\n--- Status distribution ---\n";
printf("%-6s %-22s %6s %s .. %s\n", 'ID', 'LABEL', 'COUNT', 'FIRST', 'LAST');
foreach ($report['status_distribution'] as $s) {
    printf("%-6d %-22s %6d %s .. %s\n", $s['status'], $s['label'], $s['count'], $s['first_at'], $s['last_at']);
}
echo "\n--- Missing commission candidates (status 7, profit>0, top 15) ---\n";
printf("%-8s %-20s %-12s %10s %10s %12s\n", 'TASK', 'DATE', 'FROM', 'GIVE', 'PROFIT%', 'EXPECTED~');
foreach (array_slice($report['missing_commission_candidates'], 0, 15) as $m) {
    printf("%-8d %-20s %-12s %10.2f %10.2f %12.2f\n",
        $m['task_id'], substr($m['date'], 0, 19), $m['from_code'] ?? '?',
        $m['give_price'], $m['profit_partner'], $m['expected_30_percent_screening']);
}
echo "\n--- Abnormal commissions (>100 USD) ---\n";
foreach ($report['abnormal_commissions'] as $a) {
    echo "task {$a['id_task']}: {$a['bonus_number']} USD ({$a['from_code']} -> {$a['to_code']}, give={$a['give_price']})\n";
}
echo "\n--- Task #2828 ---\n";
if (!empty($report['task_2828'])) {
    $t = $report['task_2828'][0];
    echo "give={$t['give_price']} {$t['from_code']} -> {$t['receiving_price']} {$t['to_code']}\n";
    echo "from_internal_rate={$t['from_internal_rate']} profit_partner={$t['profit_partner']}\n";
    echo "credited bonus={$t['bonus_number']} USD at {$t['current_percent']}%\n";
    echo "fair estimate (0.5 BCH ~ \$225, 30% margin, 30% partner): ~\$15-25 USD\n";
}
echo "\nStatus log:\n";
foreach ($report['task_2828_status_log'] as $sl) {
    echo "  {$sl['at']}: {$sl['from']} -> {$sl['to']}\n";
}
echo "\n--- Global commission by date (recent) ---\n";
foreach ($report['global_commission_by_date'] as $g) {
    echo "{$g['d']}: {$g['cnt']} rows, sum={$g['sum_bonus']} USD\n";
}
echo "\nDone.\n";
