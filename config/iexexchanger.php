<?php

return [
    'admin_folder' => env('APP_ADMIN_PATH', 'iexadmin'),
    'private_image_folder' => env('APP_ADMIN_PATH').'/frontend-api',

    'default_locale' => 'ru',

    'app_secret_password' => env('IEX_APP_SECRET_PASSWORD'),

    'rate_limiter' => [
        'global' => 1000,
        'api' => 60,
        'api-frontend' => 1000
    ],


    // Включить двойную защиту (backup codes)
    'is_backup_code' => env('IS_BACKUP_CODE', false),
    'is_reading_mode' => env('IS_READING_MODE', false),

    'search_filter_except' => ['page', 'sorting_order', 'sorting_type'],

    'version' => [
        'current' => '11.0.5',
        'previous_version' => '11.0.4',
        'type' => 'Press-release',
    ],

    'orders' => [
// Время актуальности заявки (в секундах)
        'active_job_timeout' => [
            10,     // 10 секунд
            50,     // 50 секунд
            300,    // 5 минут
            600,    // 10 минут
            900,    // 15 минут
            1020,   // 17 минут
            1200,   // 20 минут
            1800,   // 30 минут
            3000,   // 50 минут
            3600,   // 1 час
//            5400,   // 1 час 30 минут
//            7200,   // 2 часа
//            10800,  // 3 часа
        ],

        // Время выполнение заявки
        'active_handler_timeout' => [

        ],

        // Заявки, которые будут переведены в ожидание
        'restore_statuses' => [1, 2, 5, 6, 8, 9, 11, 12, 13, 14],
    ],

    'gateways' => [
        'security' => [
            // Секрет для временного доступа к ключам соединения в мерчанте
            'merchant_access_secret' => env('MERCHANT_ACCESS_SECRET', ''),
            // TTL в часах для сессионного доступа
            'merchant_access_secret_ttl' => env('MERCHANT_ACCESS_SECRET_TTL', 6),

            'autopay_access_secret' => env('MERCHANT_AUTOPAY_ACCESS_SECRET', ''),
            'autopay_access_secret_ttl' => env('MERCHANT_AUTOPAY_ACCESS_SECRET_TTL', 6),
        ],
    ],

    'api' => [
        'balances' => [
            'binance' => '["BTC","LTC","ETH","NEO","BNB","QTUM","EOS","SNT","BNT","GAS","BCC","USDT","HSR","OAX","DNT","MCO","ICN","ZRX","OMG","WTC","YOYO","LRC","TRX","SNGLS","STRAT","BQX","FUN","KNC","CDT","XVG","IOTA","SNM","LINK","CVC","TNT","REP","MDA","MTL","SALT","NULS","SUB","STX","MTH","ADX","ETC","ENG","ZEC","AST","GNT","DGD","BAT","DASH","POWR","BTG","REQ","XMR","EVX","VIB","ENJ","VEN","ARK","XRP","MOD","STORJ","KMD","RCN","EDO","DATA","DLT","MANA","PPT","RDN","GXS","AMB","ARN","BCPT","CND","GVT","POE","BTS","FUEL","XZC","QSP","LSK","BCD","TNB","ADA","LEND","XLM","CMT","WAVES","WABI","GTO","ICX","OST","ELF","AION","WINGS","BRD","NEBL","NAV","VIBE","LUN","TRIG","APPC","CHAT","RLC","INS","PIVX","IOST","STEEM","NANO","AE","VIA","BLZ","SYS","RPX","NCASH","POA","ONT","ZIL","STORM","XEM","WAN","WPR","QLC","GRS","CLOAK","LOOM","BCN","TUSD","ZEN","SKY","THETA","IOTX","QKC","AGI","NXS","SC","NPXS","KEY","NAS","MFT","DENT","IQ","ARDR","HOT","VET","DOCK","POLY","VTHO","ONG","PHX","HC","GO","PAX","RVN","DCR","USDC","MITH","BCHABC","BCHSV","REN","BTT","USDS","FET","TFUEL","CELR","MATIC","ATOM","PHB","ONE","FTM","BTCB","USDSB","CHZ","COS","ALGO","ERD","DOGE","BGBP","DUSK","ANKR","WIN","TUSDB","COCOS","PERL","TOMO","BUSD","BAND","BEAM","HBAR","XTZ","NGN","DGB","NKN","GBP","EUR","KAVA","RUB","UAH","ARPA","TRY","CTXC","AERGO","BCH","TROY","BRL","VITE","FTT","PLN","RON","AUD","OGN","DREP","BULL","BEAR","ETHBULL","ETHBEAR","XRPBULL","XRPBEAR","EOSBULL","EOSBEAR","TCT","WRX","LTO","ZAR","MBL","COTI","BKRW","BNBBULL","BNBBEAR","HIVE","STPT","SOL","IDRT","CTSI","CHR","BTCUP","BTCDOWN","HNT","JST","FIO","BIDR","STMX","MDT","PNT","COMP","IRIS","MKR","SXP","SNX","DAI","ETHUP","ETHDOWN","ADAUP","ADADOWN","LINKUP","LINKDOWN","DOT","RUNE","BNBUP","BNBDOWN","XTZUP","XTZDOWN","AVA","BAL","YFI","SRM","ANT","CRV","SAND","OCEAN","NMR","LUNA","IDEX","RSR","PAXG","WNXM","TRB","EGLD","BZRX","WBTC","KSM","SUSHI","YFII","DIA","BEL","UMA","EOSUP","TRXUP","EOSDOWN","TRXDOWN","XRPUP","XRPDOWN","DOTUP","DOTDOWN","NBS","WING","SWRV","LTCUP","LTCDOWN","CREAM","UNI","OXT","SUN","AVAX","BURGER","BAKE","FLM","SCRT","XVS","CAKE","SPARTA","UNIUP","UNIDOWN","ALPHA","ORN","UTK","NEAR","VIDT","AAVE","FIL","SXPUP","SXPDOWN","INJ","FILDOWN","FILUP","YFIUP","YFIDOWN","CTK","EASY","AUDIO","BCHUP","BCHDOWN","BOT","AXS","AKRO","HARD","KP3R","RENBTC","SLP","STRAX","UNFI","CVP","BCHA","FOR","FRONT","ROSE","MDX","HEGIC","AAVEUP","AAVEDOWN","PROM","BETH","SKL","GLM","SUSD","COVER","GHST","SUSHIUP","SUSHIDOWN","XLMUP","XLMDOWN","DF","JUV","PSG","BVND","GRT","CELO","TWT","REEF","OG","ATM","ASR","1INCH","RIF","BTCST","TRU","DEXE","CKB","FIRO","LIT","PROS","VAI","SFP","FXS","DODO","AUCTION","UFT","ACM","PHA","TVK","BADGER","FIS","QI","OM","POND","ALICE","DEGO","BIFI","LINA","PERP","RAMP","SUPER","CFX","TKO","AUTO","EPS","PUNDIX","TLM","1INCHUP","1INCHDOWN","MIR","BAR","FORTH","EZ","AR","ICP","SHIB","POLS","MASK","LPT","AGIX","ATA","NU","GTC","KLAY","TORN","KEEP","ERN","BOND","MLN","C98","FLOW","QUICK","RAY","MINA","QNT","CLV","XEC","ALPACA","FARM","VGX","MBOX","WAXP","TRIBE","GNO","USDP","DYDX","GALA","ILV","YGG","FIDA","AGLD","BETA","RAD","RARE","SSV","LAZIO","MOVR","CHESS","DAR","ACA","ASTR","BNX","RGT","CITY","ENS","PORTO","SGB","JASMY","AMP","PLA","PYR","SANTOS","RNDR","ALCX","MC","ANY","VOXEL","BICO","FLUX","UST","HIGH","OOKI","CVX","PEOPLE","SPELL","JOE","BDOT","GLMR","ACH","IMX","LOKA","BTTC","ANC","API3","XNO","WOO","ALPINE","T","NBT","KDA","APE","GMT","MOB","BSW","MULTI","REI","GAL","NEXO","EPX","LDO","USTC","LUNC","OP","LEVER","STG","POLYX","GMX","APT","FLR","OSMO","HFT","HOOK","MAGIC","HIFI","RPL","GFT","GNS","SYN","LQTY","ID","ARB","RDNT"]',
            'westwallet' => '["BTC","BCH","ETC","XRP","LTC","ADA","DASH","ZEC","DOGE","SOL","XMR","XTZ","XLM","EOS","TRX","USDT","BNB","BNB20","USDTBEP","TUSD","USDCTRC","BUSD","USDC","BTG","USDP","LEO","SHIB","XVS","YFI","CRO","LINK","HT","MKR","UAX","MCR","MDT","PZM","KVD","WWT","BSV","USDTOMNI","USDTTRC","ETH"]',
            'whitebitcrypto' => '["BTC","ETH","USD","LTC","ETC","BCH","DASH","NEO","GAS","UAH","XLM","OMG","BNB","USDT","SNT","MKR","ZRX","BAT","WAVES","EUR","RUB","ILC","CCOH","TRX","CSC","MATIC","XRP","BTT","WIN","AGRS","USDT_ETH","USDT_TRON","TUSD","USDC","XEM","LINK","ZEC","DBTC","DUSDT","TNC","MWC","USDT_OMNI","ADK","XMR","INTX","ZEN","AMB","DOGE","AXEL","KOCJ","COMP","YFI","SNX","DAI","SXP","BAND","KNC","UNI","NCDT","EOS","NEEO","DYN","KALA","MVEDA","OVO","VCG","SUN","ICX","HMR","PIVX","GRT","EDC","FIO","AGRS_OMNI","AGRS_ETH","XFC","RXC","HOGE","AZU","STON","SFM","ASH","HLO","BNB_BEP2","BNB_BSC","XTZ","SPE","TYC","DECL","PSI","FLIX","POODL2","STORY","HZM","USDT_BSC","WRT","C98","DOT","TUSD_ETH","TUSD_TRON","USDC_ETH","USDC_TRON","KSM","1INCH","CHZ","TABOO","MANA","SAND","DATA","MATIC_POLYGON","MATIC_ETH","DBX","EURT","SUSHI","SUSHI_ETH","SUSHI_BSC","BNT","CRV","ARV","USDT_EOS","BERRY","DYDX","ANKR","ANKR_ETH","ANKR_BSC","SHIB","MTO","SOL","KLEE","RARE","CYCE","LUFFY","EMPIRE","ADA","ESHK","DTNG","ZAMZAM","CCAT","MLNK","STEMX","XRDOGE","ALPACA","IJZ","PXP","CT","XDC","VICA","TCG2","VET","HMETA","BLXM","LRC","AXS","EQ","NEAR","BTCZ","AAVE","ENJ","ENS","LPT","LUNC","SRO","RNDR","REN","SKL","YGG","MASK","SONO","AVAX","AVAX_CCHAIN","GLM","NOOFT","APE","TOMO","TOMO_ETH","TOMO_TOMO","TYV","PYR","FIL","AKITA","JST","USTC","INJ","ANT","RAD","STRM","CHR","SUPER","CTSI","STORJ","RSR","MDX","OGN","POLS","HEC","RBT","CVC","WSD","OXT","BICO","NKN","POND","XDEN","EAI","MLN","BADGER","BAL","RLC","ALICE","KLC","VOLT","POWR","BITCCA","IHC","QUA","QUA_BSC","QUA_ETH","DNT","HBAR","JAM","GAL","STPT","UTK","IDEX","SL","GMT","KZT","AGLD","CLV","ARPA","LINA","PHA","YFII","BLZ","AKRO","TRB","FRONT","WNXM","FIS","DF","CVP","HDX","FOR","USDC_XLM","UMA","KLAY","LUNA","F0BTC","PLCU","GULF","TRY","GEL","XRD","XRD_RADIX","XRD_ETH","AVAX_XCHAIN","JASMY","PEOPLE","WOO","IMX","NEXO","ADX","AMP","API3","DODO","OP","AUDIO","UNFI","OCEAN","CAKE","VOXEL","CUC","ATOM","ALGO","FTM","SSLX","REEF","ZIL","THETA","LDO","KAVA","CELO","WBT","WBT-HOLD","RVN","QTUM","ICP","WBT_ETH","WBT_TRON","STG","FOF","ETHW","ELAN","BRZ","BRZ_ETH","BRZ_BSC","GLMR","IOST","ASTR","GARI","IOTX","APT","F0ADA","F0ETH","F0SOL","F0XRP","ETH_ETH","ETH_BSC","ETH_OP","ETH_ARB","GALA","ONT","WAXP","XSPECT","BITCI","STEEM","AR","FLOW","FUN","F0DOGE","F0LTC","F0SHIB","F0ETC","F0APE","F0AVAX","F0DOT","F0MATIC","F0TRX","GBP","PLN","CZK","BGN","FET","XAUT","ARB"]',
        ],
    ]
];
