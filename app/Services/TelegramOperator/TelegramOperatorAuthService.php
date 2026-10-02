<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fail-closed mapping: telegram_user_id → authorized admin User.
 * Never trusts username/display name alone.
 *
 * Destination resolution re-evaluates Spatie permission state on each call
 * (Horizon-safe) and performs one supported cache reset when links are
 * configured but authorization returns empty — without bypassing can().
 */
final class TelegramOperatorAuthService
{
    /**
     * @return array<int, int> telegram_user_id => user_id
     */
    public function links(): array
    {
        $raw = trim((string) config('telegram_operator.operator_links', ''));
        if ($raw === '') {
            return [];
        }

        $map = [];
        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || ! str_contains($pair, ':')) {
                continue;
            }
            [$tg, $uid] = array_map('trim', explode(':', $pair, 2));
            if (! ctype_digit($tg) || ! ctype_digit($uid)) {
                continue;
            }
            $map[(int) $tg] = (int) $uid;
        }

        return $map;
    }

    public function resolveOperator(int $telegramUserId): ?User
    {
        if ($telegramUserId <= 0) {
            return null;
        }

        $userId = $this->links()[$telegramUserId] ?? null;
        if ($userId === null) {
            Log::warning('telegram_operator_auth_unknown', [
                'telegram_user_id' => $telegramUserId,
            ]);

            return null;
        }

        $user = User::query()->find($userId);
        if ($user === null) {
            Log::warning('telegram_operator_auth_missing_user', [
                'telegram_user_id' => $telegramUserId,
                'user_id' => $userId,
            ]);

            return null;
        }

        return $user;
    }

    public function authorize(int $telegramUserId, string $permission): ?User
    {
        $user = $this->resolveOperator($telegramUserId);
        if ($user === null) {
            return null;
        }

        if ($user->can($permission)) {
            return $user;
        }

        // Linked operator failed can() — one supported Spatie cache heal then re-check.
        // Destination routing already heals this way; callbacks must not stay denied on
        // stale redis permission ids while the operator still holds the role permission.
        $this->reloadPermissionRegistrar(forgetCache: true);
        $user = $user->fresh() ?? $user;
        if ($user->can($permission)) {
            Log::info('telegram_operator_auth_cache_healed', [
                'telegram_user_id' => $telegramUserId,
                'user_id' => (int) $user->id,
                'permission' => $permission,
            ]);

            return $user;
        }

        Log::warning('telegram_operator_auth_forbidden', [
            'telegram_user_id' => $telegramUserId,
            'user_id' => (int) $user->id,
            'permission' => $permission,
        ]);

        return null;
    }

    /**
     * Telegram user IDs allowed to receive actionable order DMs.
     * Fail closed: must be linked and hold the given permission.
     *
     * Re-evaluates Spatie registrar state each call. If links are configured
     * but authorization is empty, performs one supported permission-cache reset
     * and re-checks (heals corrupt cache without bypassing can()).
     *
     * @return list<int>
     */
    public function authorizedActionTelegramUserIds(?string $permission = null): array
    {
        $permission = $permission ?: (string) config(
            'telegram_operator.complete_permission',
            'admin_orders_execute'
        );

        $links = $this->links();
        $this->reloadPermissionRegistrar(forgetCache: false);
        $ids = $this->collectAuthorizedIds($links, $permission);

        if ($links !== [] && $ids === []) {
            // Supported Spatie reset — reload from DB; never skip can().
            $this->reloadPermissionRegistrar(forgetCache: true);
            $healed = $this->collectAuthorizedIds($links, $permission);
            $health = app(TelegramOperatorDestinationHealth::class);

            if ($healed !== []) {
                $health->recordAuthRecovered([
                    'permission' => $permission,
                    'links' => $links,
                    'authorized_ids' => $healed,
                    'configured_link_count' => count($links),
                    'authorized_count' => count($healed),
                    'healed_from_cache' => true,
                    'classification' => TelegramOperatorDestinationHealth::CLASS_OK,
                ]);

                return $healed;
            }

            $health->recordAuthEmpty([
                'permission' => $permission,
                'links' => $links,
                'authorized_ids' => [],
                'configured_link_count' => count($links),
                'authorized_count' => 0,
                'healed_from_cache' => false,
                'classification' => TelegramOperatorDestinationHealth::CLASS_AUTHORIZATION_EMPTY,
            ]);
        }

        return $ids;
    }

    /**
     * @param  array<int, int>  $links
     * @return list<int>
     */
    private function collectAuthorizedIds(array $links, string $permission): array
    {
        $ids = [];
        foreach (array_keys($links) as $telegramUserId) {
            if ($this->authorize((int) $telegramUserId, $permission) !== null) {
                $ids[] = (int) $telegramUserId;
            }
        }

        return $ids;
    }

    /**
     * Horizon-safe Spatie permission re-evaluation.
     * forgetCache=true uses the supported PermissionRegistrar::forgetCachedPermissions API.
     */
    public function reloadPermissionRegistrar(bool $forgetCache = false): void
    {
        if (! class_exists(PermissionRegistrar::class)) {
            return;
        }

        try {
            $registrar = app(PermissionRegistrar::class);
            if ($forgetCache) {
                $registrar->forgetCachedPermissions();
            }
            if (method_exists($registrar, 'clearPermissionsCollection')) {
                $registrar->clearPermissionsCollection();
            }
        } catch (\Throwable $e) {
            Log::warning('telegram_operator_permission_cache_reload_failed', [
                'forget_cache' => $forgetCache,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function displayName(User $user): string
    {
        $name = trim((string) ($user->name ?? ''));
        if ($name !== '') {
            return $name;
        }
        $username = trim((string) ($user->username ?? ''));
        if ($username !== '') {
            return $username;
        }

        return 'operator#'.(int) $user->id;
    }
}
