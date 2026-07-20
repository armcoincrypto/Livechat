UPDATE direction_exchange SET status=1, allow_export=0, updated_at=NOW() WHERE id=268 AND status=0 AND allow_export=2;
