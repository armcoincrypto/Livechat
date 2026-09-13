<?php

declare(strict_types=1);

/**
 * Internal order-lifecycle certification rail. Default OFF.
 * Must never be a public customer feature.
 */
return [
    'enabled' => filter_var(env('EXS_ORDER_CERTIFICATION_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'token' => (string) env('EXS_ORDER_CERTIFICATION_TOKEN', ''),
    'email_suffix' => '@qa.exswaping.com',
    'provider' => 'INTERNAL_FAKE',
    'request_header' => 'X-Exs-Certification-Request',
    'token_header' => 'X-Exs-Certification-Token',
];
