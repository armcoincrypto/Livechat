-- Rollback controlled ZEC restores from this approval phase
UPDATE direction_exchange SET status=0, allow_export=2 WHERE id=541;
UPDATE direction_exchange SET status=0, allow_export=2 WHERE id=542;
-- 540 was never restored
