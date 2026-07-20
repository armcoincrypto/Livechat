UPDATE parser_exchange SET status=0 WHERE id IN (344, 228);
-- note: summa/updated_at intentionally left as last live values after rollback of status only
