<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use Illuminate\Support\Facades\Log;

/**
 * Operational health for Telegram order-operator destinations.
 * Never routes alerts through the broken operator Telegram path.
 */
final class TelegramOperatorDestinationHealth
{
    public const EVENT_AUTH_EMPTY = 'TELEGRAM_OPERATOR_DESTINATION_AUTH_EMPTY';

    public const EVENT_AUTH_RECOVERED = 'TELEGRAM_OPERATOR_DESTINATION_AUTH_RECOVERED';

    public const CLASS_NO_CONFIGURATION = 'NO_CONFIGURATION';

    public const CLASS_AUTHORIZATION_EMPTY = 'AUTHORIZATION_EMPTY';

    public const CLASS_BOT_SEND_FAILURE = 'BOT_SEND_FAILURE';

    public const CLASS_OK = 'OK';

    /**
     * Classify why order-card destinations are empty (or send failed).
     *
     * @param  list<int>  $authorizedIds
     */
    public function classifyEmptyDestinations(
        array $links,
        array $authorizedIds,
        bool $notifyDmsEnabled,
        ?string $sendFailure = null,
    ): string {
        if ($sendFailure !== null && $sendFailure !== '') {
            return self::CLASS_BOT_SEND_FAILURE;
        }

        if (! $notifyDmsEnabled || $links === []) {
            return self::CLASS_NO_CONFIGURATION;
        }

        if ($authorizedIds === []) {
            return self::CLASS_AUTHORIZATION_EMPTY;
        }

        return self::CLASS_OK;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordAuthEmpty(array $context): void
    {
        $payload = $this->normalizePayload(self::EVENT_AUTH_EMPTY, $context);
        if (! $this->shouldEmit(self::EVENT_AUTH_EMPTY, $payload)) {
            return;
        }

        Log::error(self::EVENT_AUTH_EMPTY, $payload);
        $this->appendJsonl($payload);
        $this->writeState('AUTH_EMPTY', $payload);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordAuthRecovered(array $context): void
    {
        $payload = $this->normalizePayload(self::EVENT_AUTH_RECOVERED, $context);
        Log::warning(self::EVENT_AUTH_RECOVERED, $payload);
        $this->appendJsonl($payload);
        $this->writeState('AUTH_OK', $payload);
    }

    /**
     * Snapshot for monitors / artisan health command.
     *
     * @return array{
     *   state: string,
     *   telegram_operator_auth_empty_total: int,
     *   configured_link_count: int,
     *   authorized_count: int,
     *   permission: string,
     *   classification: string,
     *   last_event: ?array<string, mixed>
     * }
     */
    public function snapshot(TelegramOperatorAuthService $auth): array
    {
        $permission = (string) config('telegram_operator.complete_permission', 'admin_orders_execute');
        $links = $auth->links();
        $ids = $auth->authorizedActionTelegramUserIds($permission);
        $notify = filter_var(config('telegram_operator.notify_operator_dms', true), FILTER_VALIDATE_BOOLEAN);
        $classification = $this->classifyEmptyDestinations($links, $ids, $notify);
        $state = $this->readState();
        $total = (int) ($state['telegram_operator_auth_empty_total'] ?? 0);

        return [
            'state' => $classification === self::CLASS_AUTHORIZATION_EMPTY ? 'AUTH_EMPTY' : 'AUTH_OK',
            'telegram_operator_auth_empty_total' => $total,
            'configured_link_count' => count($links),
            'authorized_count' => count($ids),
            'permission' => $permission,
            'classification' => $classification,
            'last_event' => is_array($state['last_event'] ?? null) ? $state['last_event'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizePayload(string $event, array $context): array
    {
        $links = is_array($context['links'] ?? null) ? $context['links'] : [];
        $authorized = is_array($context['authorized_ids'] ?? null) ? $context['authorized_ids'] : [];

        return [
            'event' => $event,
            'ts' => now()->toIso8601String(),
            'permission' => (string) ($context['permission'] ?? config('telegram_operator.complete_permission', 'admin_orders_execute')),
            'configured_link_count' => (int) ($context['configured_link_count'] ?? count($links)),
            'authorized_count' => (int) ($context['authorized_count'] ?? count($authorized)),
            'linked_user_ids' => array_values(array_unique(array_map('intval', array_values($links)))),
            'telegram_user_id_suffixes' => array_values(array_map(
                static fn ($id) => substr((string) $id, -4),
                array_keys($links)
            )),
            'authorized_telegram_suffixes' => array_values(array_map(
                static fn ($id) => substr((string) $id, -4),
                $authorized
            )),
            'healed_from_cache' => (bool) ($context['healed_from_cache'] ?? false),
            'classification' => (string) ($context['classification'] ?? self::CLASS_AUTHORIZATION_EMPTY),
        ];
    }

    /**
     * Dedup AUTH_EMPTY until recovery or payload identity changes.
     *
     * @param  array<string, mixed>  $payload
     */
    private function shouldEmit(string $event, array $payload): bool
    {
        if ($event !== self::EVENT_AUTH_EMPTY) {
            return true;
        }

        $state = $this->readState();
        if (($state['status'] ?? '') !== 'AUTH_EMPTY') {
            return true;
        }

        $prev = $state['last_event'] ?? [];
        $keys = ['permission', 'configured_link_count', 'authorized_count', 'linked_user_ids', 'telegram_user_id_suffixes'];
        foreach ($keys as $key) {
            if (($prev[$key] ?? null) != ($payload[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function appendJsonl(array $payload): void
    {
        $path = storage_path('logs/telegram_operator_destination_health.jsonl');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents(
            $path,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeState(string $status, array $payload): void
    {
        $path = $this->statePath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $prev = $this->readState();
        $total = (int) ($prev['telegram_operator_auth_empty_total'] ?? 0);
        if ($status === 'AUTH_EMPTY' && ($prev['status'] ?? '') !== 'AUTH_EMPTY') {
            $total++;
        }

        $state = [
            'status' => $status,
            'updated_at' => $payload['ts'] ?? now()->toIso8601String(),
            'telegram_operator_auth_empty_total' => $total,
            'last_event' => $payload,
        ];

        @file_put_contents(
            $path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
            LOCK_EX
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(): array
    {
        $path = $this->statePath();
        if (! is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function statePath(): string
    {
        return storage_path('app/telegram_operator_destination_health_state.json');
    }
}
