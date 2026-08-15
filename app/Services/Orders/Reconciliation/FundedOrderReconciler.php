<?php

declare(strict_types=1);

namespace App\Services\Orders\Reconciliation;

use Illuminate\Support\Facades\DB;

/**
 * Read-only funded-order reconcilation. Never writes task status.
 */
final class FundedOrderReconciler
{
    /**
     * @return list<array<string,mixed>>
     */
    public function inspectStatuses(array $statuses): array
    {
        $statuses = array_values(array_map('intval', $statuses));
        if ($statuses === []) {
            return [];
        }
        $in = implode(',', $statuses);

        $rows = DB::select("
SELECT
  t.id AS order_id,
  t.public_id,
  t.status AS current_status,
  t.created_at,
  t.updated_at AS last_status_at,
  t.completed_at,
  t.started_at,
  t.is_bot,
  t.is_auto_check_pay,
  t.give_price,
  t.receiving_price,
  t.in_amount_merchant,
  t.opt_params,
  c1.designation_xml AS give_code,
  c2.designation_xml AS recv_code,
  TIMESTAMPDIFF(HOUR, t.created_at, NOW()) AS age_hours,
  EXISTS(SELECT 1 FROM merchants_transaction_data m WHERE m.id_task = t.id) AS has_merchant,
  EXISTS(SELECT 1 FROM wallet_transactions w WHERE w.id_task = t.id AND w.deleted_at IS NULL) AS has_wallet,
  (SELECT w.txid FROM wallet_transactions w WHERE w.id_task = t.id AND w.deleted_at IS NULL AND w.txid IS NOT NULL AND w.txid <> '' ORDER BY w.id DESC LIMIT 1) AS wallet_txid,
  (SELECT w.amount FROM wallet_transactions w WHERE w.id_task = t.id AND w.deleted_at IS NULL ORDER BY w.id DESC LIMIT 1) AS wallet_amount,
  (SELECT w.created_at FROM wallet_transactions w WHERE w.id_task = t.id AND w.deleted_at IS NULL ORDER BY w.id ASC LIMIT 1) AS inbound_confirmed_at,
  EXISTS(SELECT 1 FROM merchant_transaction_webhooks h WHERE h.id_task = t.id) AS has_webhook,
  EXISTS(SELECT 1 FROM pays_transaction_data p WHERE p.id_task = t.id) AS has_pays,
  EXISTS(SELECT 1 FROM autosender_payment a WHERE a.id_order = t.id) AS has_autosender,
  EXISTS(SELECT 1 FROM wallets_history wh WHERE wh.id_task = t.id AND wh.deleted_at IS NULL) AS has_wallets_history
FROM tasks t
LEFT JOIN direction_exchange d ON d.id = t.id_direction_exchange
LEFT JOIN currencies c1 ON c1.id = d.id_currency1
LEFT JOIN currencies c2 ON c2.id = d.id_currency2
WHERE t.deleted_at IS NULL AND t.status IN ($in)
ORDER BY t.id
");

        $duplicateTxids = $this->duplicateTxidSet();

        $out = [];
        foreach ($rows as $r) {
            $arr = (array) $r;
            $arr['status'] = (int) ($arr['current_status'] ?? 0);
            $txid = trim((string) ($arr['wallet_txid'] ?? ''));
            $arr['duplicate_inbound'] = ($txid !== '' && isset($duplicateTxids[$txid]) && $duplicateTxids[$txid] > 1) ? 1 : 0;
            $opt = $arr['opt_params'] ?? null;
            if (is_string($opt)) {
                $decoded = json_decode($opt, true);
                $opt = is_array($decoded) ? $decoded : [];
            }
            $settlement = is_array($opt) ? ($opt['manual_settlement'] ?? null) : null;
            $arr['manual_settlement'] = is_array($settlement) ? ($settlement['reference'] ?? '1') : null;
            $arr['inbound_evidence'] = FundedReconciliationClassifier::inboundStrength($arr);
            $arr['classification'] = FundedReconciliationClassifier::classification($arr);
            $arr['wh_detail'] = FundedReconciliationClassifier::waitingHandleDetail($arr);
            $arr['recommended_operator_action'] = FundedReconciliationClassifier::recommendedAction($arr['classification']);
            $arr['priority_group'] = FundedReconciliationClassifier::operatorPriorityGroup($arr, $arr['classification']);
            $arr['age_bucket'] = FundedReconciliationClassifier::ageBucket((int) $arr['age_hours']);
            $arr['destination_family'] = FundedReconciliationClassifier::destinationFamily($arr['recv_code'] ?? null);
            $arr['outbound_evidence'] = ((int) $arr['has_pays'] === 1 || (int) $arr['has_autosender'] === 1 || (int) $arr['has_wallets_history'] === 1) ? 'HAS_PAYOUT_MARKERS' : 'NO_PAYOUT_EVIDENCE';
            unset($arr['opt_params']);
            $out[] = $arr;
        }

        return $out;
    }

    /**
     * @return array<string,int>
     */
    public function duplicateTxidSet(): array
    {
        $rows = DB::select("
SELECT txid, COUNT(DISTINCT id_task) c
FROM wallet_transactions
WHERE deleted_at IS NULL AND txid IS NOT NULL AND txid <> ''
GROUP BY txid
HAVING c > 1
");
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r->txid] = (int) $r->c;
        }

        return $map;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function duplicateInboundRows(): array
    {
        return array_map(fn ($r) => (array) $r, DB::select("
SELECT w.txid, GROUP_CONCAT(DISTINCT w.id_task ORDER BY w.id_task) AS task_ids, COUNT(DISTINCT w.id_task) AS task_count
FROM wallet_transactions w
WHERE w.deleted_at IS NULL AND w.txid IS NOT NULL AND w.txid <> ''
GROUP BY w.txid
HAVING task_count > 1
"));
    }

    public function paymentDetectorSnapshot(): array
    {
        $lastWallet = DB::table('wallet_transactions')->whereNull('deleted_at')->max('created_at');
        $lastMerchant = DB::table('merchants_transaction_data')->max('created_at');
        $lastWebhook = DB::table('merchant_transaction_webhooks')->max('created_at');
        $lastBotTouch = DB::table('tasks')
            ->where('status', 3)
            ->where('is_bot', 1)
            ->where('is_auto_check_pay', 1)
            ->whereNull('deleted_at')
            ->max('next_checkout_at');

        $lastInbound = collect([$lastWallet, $lastMerchant, $lastWebhook])->filter()->max();
        $detectorFresh = $lastBotTouch && now()->diffInMinutes($lastBotTouch) < 15;
        $inboundAgeHours = $lastInbound ? (int) abs(now()->diffInHours(\Illuminate\Support\Carbon::parse($lastInbound), false)) : null;

        $state = 'NO_NEW_PAYMENTS';
        if (!$detectorFresh && DB::table('tasks')->where('status', 3)->where('is_bot', 1)->where('is_auto_check_pay', 1)->whereNull('deleted_at')->exists()) {
            $state = 'PAYMENT_DETECTOR_STALE';
        }

        return [
            'last_wallet_tx_at' => $lastWallet,
            'last_merchant_tx_at' => $lastMerchant,
            'last_webhook_at' => $lastWebhook,
            'last_bot_poll_touch_at' => $lastBotTouch,
            'last_inbound_at' => $lastInbound,
            'inbound_age_hours' => $inboundAgeHours,
            'detector_poll_fresh' => $detectorFresh,
            'state' => $state,
        ];
    }
}
