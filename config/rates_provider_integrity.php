<?php

declare(strict_types=1);

/**
 * Provider integrity for IndependentMarketBaseline / DERIVED legs.
 *
 * Never trusts BREAK/delisted/no-book single-venue ticks.
 * Certified fallbacks are market references only — they do not change direction ownership.
 */
return [
    'outlier_max_deviation' => '0.08', // 8%: catch frozen/wrong-symbol outliers without rejecting normal venue noise
    'min_distinct_providers_for_healthy' => 2,
    'allow_single_provider' => true, // yields DEGRADED_SINGLE_PROVIDER
    'lkg_ttl_crypto_seconds' => 3600, // 1h
    'lkg_ttl_fiat_seconds' => 21600, // 6h
    'lkg_path' => 'rates/derived-leg-lkg.json',
    /**
     * Static exchange-status denylist (overridable by probe / constructor).
     * Keys: provider => [ SYMBOL => TRADING|BREAK|DELISTED|HALT ]
     */
    'exchange_status' => [
        'binance' => [
            'TONUSDT' => 'BREAK',
            'XMRUSDT' => 'BREAK',
        ],
    ],
    /**
     * When no eligible venue parser quotes remain, use certified peer market refs.
     * These are NOT BestChange ownership of derived rows — only USDT peg references.
     */
    'symbol_fallbacks' => [
        'TONUSDT' => [
            [
                'type' => 'direction_inverse_course',
                'direction_id' => 41, // USDTTRC20→GRAM (GRAM per USDT) ⇒ TON USD = 1/course
                'label' => 'bc_usdttrc20_gram_inverse',
            ],
            [
                'type' => 'direction_btc_gram_cross',
                'direction_id' => 19, // BTC→GRAM (GRAM per BTC) ⇒ TON USD = BTCUSDT/course
                'btc_symbol' => 'BTCUSDT',
                'label' => 'bc_btc_gram_cross',
            ],
        ],
    ],
    'fallback_agreement_max_deviation' => '0.05', // 5% between fallback refs for HEALTHY
];
