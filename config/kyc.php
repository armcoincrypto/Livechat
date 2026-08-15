<?php

declare(strict_types=1);

/**
 * KYC provider selection. Default remains SumSub until Didit is enabled.
 * Secrets must come from environment / protected settings — never commit real keys.
 */
return [
    // sumsub | didit
    'provider' => strtolower((string) env('KYC_PROVIDER', 'sumsub')),

    // Comma-separated user IDs allowed to use Didit while rolling out. Empty = all users when provider=didit.
    'didit_allowlist_user_ids' => array_values(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) env('DIDIT_ALLOWLIST_USER_IDS', ''))
    ), static fn (int $id): bool => $id > 0)),

    'didit' => [
        'base_url' => rtrim((string) env('DIDIT_BASE_URL', 'https://verification.didit.me'), '/'),
        'api_key' => (string) env('DIDIT_API_KEY', ''),
        'workflow_id' => (string) env('DIDIT_WORKFLOW_ID', ''),
        'webhook_secret' => (string) env('DIDIT_WEBHOOK_SECRET', ''),
        'callback_url' => (string) env('DIDIT_CALLBACK_URL', 'https://exswaping.com/en/account/user-verify'),
        'timeout_seconds' => (int) env('DIDIT_TIMEOUT_SECONDS', 15),
    ],
];
