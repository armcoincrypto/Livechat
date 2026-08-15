<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Security;

use Carbon\Carbon;
use iEXPackages\Payments\Core\Models\GatewaySecretAccessLog;
use iEXPackages\Payments\Core\Security\Contracts\SecretAccessManagerInterface;
use Illuminate\Support\Facades\Request;

final class SecretAccessManager implements SecretAccessManagerInterface
{
    private const SESSION_KEY = 'payments_secret_access';

    public function canView(string $scope): bool
    {
        $scope = trim($scope);

        $state = session(self::SESSION_KEY, []);
        if (!is_array($state)) {
            return false;
        }

        $row = $state[$scope] ?? null;
        if (!is_array($row)) {
            return false;
        }

        if (!($row['granted'] ?? false)) {
            return false;
        }

        $expiresAt = (string) ($row['expires_at'] ?? '');
        if ($expiresAt === '') {
            return false;
        }

        return Carbon::parse($expiresAt)->isFuture();
    }

    public function grant(string $scope, int $ttlSeconds): void
    {
        $scope = trim($scope);
        $ttlSeconds = max(30, $ttlSeconds);

        $expiresAt = now()->addSeconds($ttlSeconds);

        $state = session(self::SESSION_KEY, []);
        if (!is_array($state)) {
            $state = [];
        }

        $state[$scope] = [
            'granted'    => true,
            'expires_at' => $expiresAt->toDateTimeString(),
        ];

        session([self::SESSION_KEY => $state]);

        $this->log($scope, 'grant', $expiresAt->toDateTimeString());
    }

    public function revoke(string $scope): void
    {
        $scope = trim($scope);

        $state = session(self::SESSION_KEY, []);
        if (!is_array($state)) {
            $state = [];
        }

        unset($state[$scope]);
        session([self::SESSION_KEY => $state]);

        $this->log($scope, 'revoke', null);
    }

    public function placeholder(string $value): string
    {
        return SecretMasker::placeholder($value);
    }

    private function log(string $scope, string $action, ?string $expiresAt): void
    {
        try {
            GatewaySecretAccessLog::query()->create([
                'user_id'    => auth()->id(),
                'scope'      => $scope,
                'action'     => $action,
                'ip_address' => (string) Request::ip(),
                'expires_at' => $expiresAt,
            ]);
        } catch (\Throwable) {
            // логирование секретов не должно ломать UX
        }
    }
}
