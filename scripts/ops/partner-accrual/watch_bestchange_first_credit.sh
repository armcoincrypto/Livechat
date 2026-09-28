#!/usr/bin/env bash
# BestChange plan 6 first scheduled credit watcher (Oct 2026 window).
# NON-ARMED by default. Does not enable scheduler unless EXPLICIT_ARM=YES.
set -euo pipefail
: "${EXPLICIT_ARM:=NO}"
: "${LIVE:=/var/www/app_exswapin_usr/data/www/app.exswaping.com}"
: "${PLAN_ID:=6}"
: "${USER_ID:=436}"
: "${EXPECTED_FIRST_INSTALLMENT_ID:=16}"
: "${EXPECTED_AMOUNT:=8.33000000}"
: "${BUSINESS_TZ:=Europe/Moscow}"

ENV_FILE="$LIVE/.env"
fail() { echo "FAIL: $*" >&2; exit 1; }
get_flag() { grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2-; }
set_flag() {
  local k="$1" v="$2"
  if grep -qE "^${k}=" "$ENV_FILE"; then
    sed -i "s|^${k}=.*|${k}=${v}|" "$ENV_FILE"
  else
    echo "${k}=${v}" >> "$ENV_FILE"
  fi
}
kill_switch() {
  set_flag PARTNER_ACCRUAL_ALLOW_APPLY false
  set_flag PARTNER_ACCRUAL_SCHEDULER_ENABLED false
  echo "KILL_SWITCH_APPLIED $(date -u +%FT%TZ)"
}
trap 'kill_switch' EXIT INT TERM

echo "WATCHER_START $(date -u +%FT%TZ) ARM=$EXPLICIT_ARM"
[[ "$(get_flag PARTNER_ACCRUAL_ENABLED)" == "true" ]] || fail "ENABLED must be true"
[[ "$(get_flag PARTNER_ACCRUAL_SCHEDULER_ENABLED)" == "false" ]] || fail "SCHEDULER must start OFF"
[[ "$(get_flag PARTNER_ACCRUAL_ALLOW_APPLY)" == "false" ]] || fail "ALLOW_APPLY must start OFF"

sudo -u app_exswapin_usr bash -lc "cd '$LIVE' && php8.4 -r '
require \"vendor/autoload.php\";
\$app=require \"bootstrap/app.php\";
\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
\$p=App\\Models\\PartnerAccrualPlan::find($PLAN_ID);
\$i=App\\Models\\PartnerAccrualInstallment::find($EXPECTED_FIRST_INSTALLMENT_ID);
if(!\$p || (\$p->status->value??\$p->status)!==\"ACTIVE\") exit(11);
if((int)\$p->user_id!==$USER_ID) exit(12);
if(!\$i || (int)\$i->plan_id!==$PLAN_ID) exit(13);
if((\$i->status->value??\$i->status)!==\"PENDING\") exit(14);
\$active=DB::table(\"partner_accrual_plans\")->where(\"status\",\"ACTIVE\")->count();
if(\$active!==1) { fwrite(STDERR, \"ACTIVE_PLANS=\$active\\n\"); exit(15); }
echo \"PRECHECK_OK plan=$PLAN_ID inst=$EXPECTED_FIRST_INSTALLMENT_ID amount={\$i->amount}\\n\";
'"

if [[ "$EXPLICIT_ARM" != "YES" ]]; then
  echo "NOT_ARMED — set EXPLICIT_ARM=YES and approved window to enable gates"
  exit 0
fi

echo "ARMING controlled window"
set_flag PARTNER_ACCRUAL_ALLOW_APPLY true
set_flag PARTNER_ACCRUAL_SCHEDULER_ENABLED true
echo "GATES_ON $(date -u +%FT%TZ)"

# Wait up to ~20 minutes for real cron credit (do not --apply)
deadline=$((SECONDS+1200))
while (( SECONDS < deadline )); do
  st=$(sudo -u app_exswapin_usr bash -lc "cd '$LIVE' && php8.4 -r '
require \"vendor/autoload.php\";
\$app=require \"bootstrap/app.php\";
\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
\$i=App\\Models\\PartnerAccrualInstallment::find($EXPECTED_FIRST_INSTALLMENT_ID);
echo (\$i->status->value??\$i->status);
'")
  echo "POLL status=$st $(date -u +%FT%TZ)"
  if [[ "$st" == "CREDITED" ]]; then
    refs=$(sudo -u app_exswapin_usr bash -lc "cd '$LIVE' && php8.4 -r '
require \"vendor/autoload.php\";
\$app=require \"bootstrap/app.php\";
\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
echo DB::table(\"user_balance_log\")->where(\"reference\",\"partner_accrual_installment:$EXPECTED_FIRST_INSTALLMENT_ID\")->count();
'")
    [[ "$refs" == "1" ]] || fail "REFERENCE_COUNT=$refs"
    echo "CREDIT_OBSERVED_PASS"
    exit 0
  fi
  sleep 30
done
fail "TIMEOUT waiting for real scheduled credit"
