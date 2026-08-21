<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Fail-closed mapping: telegram_user_id → authorized admin User.
 * Never trusts username/display name alone.
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

        if (! $user->can($permission)) {
            Log::warning('telegram_operator_auth_forbidden', [
                'telegram_user_id' => $telegramUserId,
                'user_id' => (int) $user->id,
                'permission' => $permission,
            ]);

            return null;
        }

        return $user;
    }

    /**
     * Telegram user IDs allowed to receive actionable order DMs.
     * Fail closed: must be linked and hold the given permission.
     *
     * @return list<int>
     */
    public function authorizedActionTelegramUserIds(?string $permission = null): array
    {
        $permission = $permission ?: (string) config(
            'telegram_operator.complete_permission',
            'admin_orders_execute'
        );

        $ids = [];
        foreach (array_keys($this->links()) as $telegramUserId) {
            if ($this->authorize((int) $telegramUserId, $permission) !== null) {
                $ids[] = (int) $telegramUserId;
            }
        }

        return $ids;
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
