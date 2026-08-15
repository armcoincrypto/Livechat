<?php

declare(strict_types=1);

return [
    /*
    | Dedicated key for verification_card identifier ciphertext (base64:...).
    | Never commit real values. Distinct from VERIFICATION_CARD_LOOKUP_KEY.
    */
    'card_key' => env('VERIFICATION_CARD_KEY'),

    /*
    | HMAC key for exact-match blind index (base64:... or raw secret string).
    */
    'lookup_key' => env('VERIFICATION_CARD_LOOKUP_KEY'),

    /*
    | When true, new writes must produce ciphertext + lookup + last4.
    | Fail closed if keys are invalid while enabled.
    */
    'encrypted_write_enabled' => filter_var(
        env('VERIFICATION_IDENTIFIER_ENCRYPTED_WRITE_ENABLED', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | When true, also write legacy card_number / card_number_string plaintext.
    | Keep false after CARDS_VERIFICATION_DISABLE_PLAINTEXT_WRITES.
    */
    'legacy_plaintext_write_enabled' => filter_var(
        env('VERIFICATION_IDENTIFIER_LEGACY_PLAINTEXT_WRITE_ENABLED', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | When true, dual-read may use legacy plaintext if ciphertext is absent.
    */
    'plaintext_fallback_enabled' => filter_var(
        env('VERIFICATION_IDENTIFIER_PLAINTEXT_FALLBACK_ENABLED', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | When true, unauthenticated order card verification requires unique_security_code
    | (IP match alone is insufficient). Default false — vendor SPA does not yet send the code.
    */
    'card_order_require_security_code' => filter_var(
        env('VERIFICATION_CARD_ORDER_REQUIRE_SECURITY_CODE', false),
        FILTER_VALIDATE_BOOLEAN
    ),
];
