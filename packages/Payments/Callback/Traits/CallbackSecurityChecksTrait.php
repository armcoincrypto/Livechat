<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Callback\Traits;

use App\Models\GatewayMerchant;
use App\Models\Task;
use iEXPackages\Payments\Callback\DTO\CallbackHttpResult;
use iEXPackages\Payments\Callback\Security\CallbackUrlHashGuard;
use Symfony\Component\HttpFoundation\IpUtils;

trait CallbackSecurityChecksTrait
{
    /**
     * Возвращает CallbackHttpResult если нужно завершить обработку (403/200),
     * либо null если проверки пройдены.
     *
     * @param array<string,mixed> $callbackConfig
     */
    private function checkSecurityRules(
        Task $task,
        GatewayMerchant $merchant,
        string $gatewayAlias,
        string $securityHashFromUrl,
        string $ip,
        array $callbackConfig
    ): ?CallbackHttpResult {

        // 1) Алиас в заявке должен совпадать
        if (strtolower((string)$merchant->alias) !== strtolower($gatewayAlias)) {
            $this->flowLogger->security(
                event: 'callback_blocked_alias_mismatch',
                task: $task,
                merchant: $merchant,
                ctx: ['ip' => $ip, 'context' => ['task_alias' => $merchant->alias, 'callback_alias' => $gatewayAlias]],
                message: 'Уведомление отклонено: платежный сервис не совпадает с заявкой.',
                stage: 'security',
                flow: 'callback'
            );

            return new CallbackHttpResult(200, 'OK');
        }

        // 2) URL security_hash — required for enabled callbacks unless config opts out
        $hashCheck = CallbackUrlHashGuard::verify(
            expectedHash: (string) ($merchant->security_hash ?? ''),
            securityHashFromUrl: $securityHashFromUrl,
            required: CallbackUrlHashGuard::urlHashRequired($callbackConfig),
        );
        if (! $hashCheck['ok']) {
            $event = (string) ($hashCheck['event'] ?? 'signature_invalid');
            $this->flowLogger->security(
                event: $event,
                task: $task,
                merchant: $merchant,
                ctx: ['ip' => $ip],
                message: $event === 'signature_missing'
                    ? 'Уведомление отклонено: защитный ключ отсутствует.'
                    : 'Уведомление отклонено: неверный защитный ключ.',
                stage: 'security',
                flow: 'callback'
            );

            return new CallbackHttpResult((int) $hashCheck['http'], (string) $hashCheck['body']);
        }

        // 3) IP whitelist: проверяем только если список НЕ пустой
        $whitelistEnabled = (bool)($callbackConfig['ip_whitelist_enabled'] ?? false);
        if ($whitelistEnabled) {
            $allowed = trim((string)($merchant->allow_ip_address ?? ''));

            if ($allowed !== '') {
                $ranges = array_values(array_filter(array_map('trim', explode(',', $allowed))));
                if ($ranges === [] || !IpUtils::checkIp($ip, $ranges)) {
                    $this->flowLogger->security(
                        event: 'callback_blocked_ip_not_allowed',
                        task: $task,
                        merchant: $merchant,
                        ctx: ['ip' => $ip, 'context' => ['allowed' => $ranges]],
                        message: 'Уведомление отклонено: IP-адрес не разрешён.',
                        stage: 'security',
                        flow: 'callback'
                    );

                    return new CallbackHttpResult(403, 'Invalid ip');
                }
            } else {
                // whitelist включён, но список пуст — НЕ блокируем
                $this->flowLogger->warning(
                    event: 'callback_whitelist_enabled_but_empty',
                    task: $task,
                    merchant: $merchant,
                    ctx: ['ip' => $ip],
                    message: 'Включена проверка IP, но список разрешённых IP не задан. Уведомление пропущено без блокировки.',
                    stage: 'security',
                    flow: 'callback'
                );
            }
        }

        return null;
    }
}
