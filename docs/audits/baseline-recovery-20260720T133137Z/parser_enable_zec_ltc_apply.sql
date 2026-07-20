-- ENABLE_EXISTING_ZEC_FEED + LTC gap closure (status only; updater will refresh summa)
-- Before: id 344 status=0 summa=54.2 updated 2024-11-12; id 228 status=0 summa=53.63
UPDATE parser_exchange SET status=1 WHERE id=344 AND code='[binance_zec-usdt]';
UPDATE parser_exchange SET status=1 WHERE id=228 AND code='[binance_ltc-usdt]';
