-- Fail-closed: USDTTRC20→SBPRUB unexplained >2% vs approved SBP target; order path lacks family gate.
UPDATE direction_exchange SET status=0, allow_export=2, updated_at=NOW() WHERE id=268 AND status=1;
