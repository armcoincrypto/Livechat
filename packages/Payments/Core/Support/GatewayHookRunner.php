<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Support;

use App\Models\Task;
use iEXPackages\Payments\Core\Contracts\GatewayInterface;
use iEXPackages\Payments\Core\Contracts\RequestInterface;
use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * GatewayHookRunner
 *
 * Универсальный запуск опциональных hook-операций шлюза.
 *
 * Правила:
 * - Если операция не объявлена в config.php → возвращаем null (тихий skip)
 * - Если объявлена → выполняем request->send() и возвращаем ResponseInterface
 * - Исключения НЕ глотаем здесь намеренно: у тебя 2 режима вызова:
 *   - runIfSupported()        — бросает исключение (для строгих хуков)
 *   - runIfSupportedSafe()    — никогда не бросает (best-effort, безопасно)
 */
final class GatewayHookRunner
{
    public function supports(GatewayInterface $gateway, string $operation): bool
    {
        $opCfg = $gateway->gatewayConfig()->operationConfig($operation);
        return is_array($opCfg) && !empty($opCfg['request_class']);
    }

    /**
     * Строгий вариант: если хук объявлен — выполняем, исключения пробрасываем наверх.
     */
    public function runIfSupported(
        GatewayInterface $gateway,
        string $operation,
        array $payload = [],
        ?Task $task = null
    ): ?ResponseInterface {
        if (!$this->supports($gateway, $operation)) {
            return null;
        }

        $req = $gateway->request($operation, $payload);

        if ($task !== null && method_exists($req, 'withTask')) {
            $req->withTask($task);
        }

        return $req->send();
    }

    /**
     * Безопасный вариант: если хук объявлен — выполняем, но ошибки НЕ ломают основной поток.
     *
     * @return array{response:?ResponseInterface, error:?string}
     */
    public function runIfSupportedSafe(
        GatewayInterface $gateway,
        string $operation,
        array $payload = [],
        ?Task $task = null
    ): array {
        try {
            $resp = $this->runIfSupported($gateway, $operation, $payload, $task);
            return ['response' => $resp, 'error' => null];
        } catch (\Throwable $e) {
            return ['response' => null, 'error' => $e->getMessage()];
        }
    }
}
