<?php

declare(strict_types=1);

/**
 * Shared helpers for BestChange autopay/backlog recovery scripts (P-BESTCHANGE-3).
 * Read-only DB helpers + optional Laravel bootstrap for completion paths.
 */

const BC_PARTNER_USER_ID = 436;
const BC_REFERRAL_HASH = 'MLyn';
const BC_EXCLUDED_TASK_ID = 2828;
const BC_PARTNER_RATE = 0.30;

function bc_load_env(string $root): array
{
    $path = $root . '/.env';
    if (!is_readable($path)) {
        throw new RuntimeException("Cannot read .env at {$path}");
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

function bc_pdo(array $env): PDO
{
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3306';
    $db   = $env['DB_DATABASE'] ?? '';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

function bc_bootstrap_laravel(string $root): void
{
    static $booted = false;
    if ($booted) {
        return;
    }
    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $booted = true;
}

function bc_screen_commission(float $givePrice, float $profitPartner): float
{
    if ($givePrice <= 0 || $profitPartner <= 0) {
        return 0.0;
    }
    // Screening only: base≈give (USDT≈USD), exchange_profit=base*profit_partner, partner=profit*30%
    $exchangeProfit = $givePrice * $profitPartner;
    return round($exchangeProfit * BC_PARTNER_RATE, 2);
}

/**
 * @return array{safe: bool, reasons: string[]}
 */
function bc_evaluate_candidate(array $row): array
{
    $reasons = [];
    $taskId = (int) ($row['task_id'] ?? 0);

    if ($taskId === BC_EXCLUDED_TASK_ID) {
        $reasons[] = 'excluded task #2828 (historical over-credit review)';
    }
    if (($row['referral_hash'] ?? '') !== BC_REFERRAL_HASH) {
        $reasons[] = 'referral_hash != MLyn';
    }
    if ((int) ($row['status'] ?? 0) !== 7) {
        $reasons[] = 'status != 7 (PAID)';
    }
    if ((float) ($row['profit_partner'] ?? 0) <= 0) {
        $reasons[] = 'profit_partner <= 0';
    }
    if (!empty($row['has_referral_log'])) {
        $reasons[] = 'referral_log already exists';
    }
    if ((int) ($row['is_spam'] ?? 0) === 1) {
        $reasons[] = 'is_spam=1';
    }
    if ((int) ($row['is_frozen'] ?? 0) === 1) {
        $reasons[] = 'is_frozen=1';
    }
    if ((int) ($row['is_freeze_scam'] ?? 0) === 1) {
        $reasons[] = 'is_freeze_scam=1';
    }
    if (empty($row['has_inbound_payment'])) {
        $reasons[] = 'no merchants_transaction_data (inbound not confirmed)';
    }
    if (empty($row['has_outbound_payout'])) {
        $reasons[] = 'no outbound payout record (pays_transaction_data / wallets_history)';
    }
    if (empty($row['to_shot']) || trim((string) $row['to_shot']) === '') {
        $reasons[] = 'missing payout destination (to_shot)';
    }

    return ['safe' => $reasons === [], 'reasons' => $reasons];
}

function bc_fetch_status7_tasks(PDO $pdo): array
{
    $sql = "
        SELECT
            t.id AS task_id,
            t.public_id,
            t.created_at,
            t.updated_at,
            t.status,
            t.referral_hash,
            t.give_price,
            t.receiving_price,
            t.to_shot,
            t.is_spam,
            t.is_frozen,
            t.is_pay_referral_bonus,
            t.is_bot,
            de.profit_partner,
            de.allow_autopay AS direction_allow_autopay,
            cc1.name AS from_code,
            cc2.name AS to_code,
            COALESCE(ti.is_freeze_scam, 0) AS is_freeze_scam,
            (mtd.id IS NOT NULL) AS has_inbound_payment,
            (ptd.id IS NOT NULL OR wh.id IS NOT NULL) AS has_outbound_payout,
            (rl.id IS NOT NULL) AS has_referral_log,
            rl.bonus_number AS actual_commission
        FROM tasks t
        JOIN direction_exchange de ON de.id = t.id_direction_exchange
        LEFT JOIN currencies c1 ON c1.id = de.id_currency1
        LEFT JOIN currencies c2 ON c2.id = de.id_currency2
        LEFT JOIN code_currency cc1 ON cc1.id = c1.id_code_currency
        LEFT JOIN code_currency cc2 ON cc2.id = c2.id_code_currency
        LEFT JOIN tasks_info ti ON ti.id_task = t.id
        LEFT JOIN merchants_transaction_data mtd ON mtd.id_task = t.id
        LEFT JOIN pays_transaction_data ptd ON ptd.id_task = t.id
        LEFT JOIN wallets_history wh ON wh.id_task = t.id
        LEFT JOIN referral_log rl ON rl.id_task = t.id AND rl.id_user = :partner_id
        WHERE t.referral_hash = :ref_hash AND t.status = 7
        ORDER BY t.created_at ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute(['partner_id' => BC_PARTNER_USER_ID, 'ref_hash' => BC_REFERRAL_HASH]);
    return $st->fetchAll();
}

function bc_partner_balance(PDO $pdo): array
{
    $row = $pdo->query('
        SELECT balance, hold_balance, referral_total_profit, referral_total_withdrawal, id_code_currency
        FROM user_balance WHERE id_user = ' . BC_PARTNER_USER_ID . ' LIMIT 1
    ')->fetch();
    return $row ?: [];
}

function bc_parse_args(array $argv): array
{
    $out = ['execute' => false, 'task_id' => 0, 'limit' => 5, 'help' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--execute') {
            $out['execute'] = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            $out['help'] = true;
        } elseif (str_starts_with($arg, '--task-id=')) {
            $out['task_id'] = (int) substr($arg, 10);
        } elseif (str_starts_with($arg, '--limit=')) {
            $out['limit'] = max(1, (int) substr($arg, 8));
        }
    }
    return $out;
}
