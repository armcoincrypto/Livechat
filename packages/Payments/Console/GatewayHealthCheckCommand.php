<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Console;

use App\Models\GatewayHealthStatus;
use App\Models\GatewayMerchant;
use App\Models\GatewayPayment;
use Carbon\Carbon;
use iEXPackages\Payments\Core\Contracts\GatewayInterface;
use iEXPackages\Payments\Core\Contracts\HealthResponseInterface;
use iEXPackages\Payments\Core\Engine\GatewayManager;
use iEXPackages\Payments\Payments;
use Illuminate\Console\Command;

/**
 * CRON: проверка доступности шлюзов (health-check).
 *
 * Правила:
 * - alias отсутствует или не зарегистрирован → SKIPPED
 * - операция health не объявлена → SKIPPED
 * - health выполнен → OK | FAIL
 *
 * Вывод: одна строка на шлюз + итоговая сводка.
 */
final class GatewayHealthCheckCommand extends Command
{
    protected $signature = 'gateways:health-check {--type=all : all|merchant|payment}';
    protected $description = 'Health-check шлюзов (merchant / payment), где объявлена операция health';

    private int $processed = 0;
    private int $skipped   = 0;
    private int $ok        = 0;
    private int $fail      = 0;

    public function __construct(
        private readonly GatewayManager $gatewayManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = strtolower((string) $this->option('type'));

        if (!in_array($type, ['all', 'merchant', 'payment'], true)) {
            $this->error("Invalid --type={$type}. Allowed: all|merchant|payment");
            return self::FAILURE;
        }

        $this->line('Gateway health check started at ' . now()->toDateTimeString());
        $this->line('------------------------------------------------------------');

        if ($type === 'all' || $type === 'merchant') {
            GatewayMerchant::query()
                ->where('status', 1)
                ->orderBy('id')
                ->chunkById(100, fn ($items) => $items->each(
                    fn ($m) => $this->checkEntity(
                        type: 'merchant',
                        entityId: (int) $m->id,
                        alias: (string) $m->alias,
                        makeGateway: fn (): GatewayInterface => Payments::forMerchant($m),
                    )
                ));
        }

        if ($type === 'all' || $type === 'payment') {
            GatewayPayment::query()
                ->where('status', 1)
                ->orderBy('id')
                ->chunkById(100, fn ($items) => $items->each(
                    fn ($p) => $this->checkEntity(
                        type: 'payment',
                        entityId: (int) $p->id,
                        alias: (string) $p->alias,
                        makeGateway: fn (): GatewayInterface => Payments::forPayment($p),
                    )
                ));
        }

        $this->line('------------------------------------------------------------');
        $this->line('Summary:');
        $this->line("Processed: {$this->processed}");
        $this->line("Skipped:   {$this->skipped}");
        $this->line("OK:        {$this->ok}");
        $this->line("Fail:      {$this->fail}");

        return self::SUCCESS;
    }

    /**
     * @param 'merchant'|'payment' $type
     * @param callable():GatewayInterface $makeGateway
     */
    private function checkEntity(
        string $type,
        int $entityId,
        string $alias,
        callable $makeGateway
    ): void {
        $this->processed++;

        $label = strtoupper($type) . " #{$entityId}";

        // alias отсутствует или неизвестен
        if ($alias === '' || !$this->gatewayManager->hasAlias($alias)) {
            $this->skipped++;
            $this->line("{$label} [{$alias}]: SKIPPED (unknown gateway alias)");
            return;
        }

        $gateway = $makeGateway();

        // операция health не объявлена
        $opCfg = $gateway->gatewayConfig()->operationConfig('health');
        if (empty($opCfg) || empty($opCfg['request_class'])) {
            $this->skipped++;
            $this->line("{$label} [{$alias}]: SKIPPED (health operation not declared)");
            return;
        }

        try {
            $resp = $gateway->run('health', []);

            if (!$resp instanceof HealthResponseInterface) {
                $this->fail++;
                $this->persistFail($type, $entityId, $alias, 'Invalid health response type');

                $this->line("{$label} [{$alias}]: FAIL (invalid response)");
                return;
            }

            $status   = $resp->getHealthStatus();
            $http     = $resp->getHttpStatus();
            $latency  = $resp->getLatencyMs();
            $message  = $resp->getHealthMessage();

            $this->persistOk($type, $entityId, $alias, $resp);

            if ($status === 'ok') {
                $this->ok++;
                $this->line(
                    "{$label} [{$alias}]: OK"
                    . $this->fmt("http={$http}")
                    . $this->fmt("latency={$latency}ms")
                    . $this->fmtMsg($message)
                );
            } else {
                $this->fail++;
                $this->line(
                    "{$label} [{$alias}]: FAIL ({$status})"
                    . $this->fmt("http={$http}")
                    . $this->fmt("latency={$latency}ms")
                    . $this->fmtMsg($message)
                );
            }

        } catch (\Throwable $e) {
            $this->fail++;
            $this->persistFail($type, $entityId, $alias, $e->getMessage());

            $this->line(
                "{$label} [{$alias}]: FAIL"
                . $this->fmtMsg($e->getMessage())
            );
        }
    }

    private function fmt(?string $value): string
    {
        $value = trim((string) $value);
        return $value === '' ? '' : " | {$value}";
    }

    private function fmtMsg(?string $message): string
    {
        $message = trim((string) $message);
        return $message === '' ? '' : " | {$message}";
    }

    private function persistOk(
        string $type,
        int $entityId,
        string $alias,
        HealthResponseInterface $resp
    ): void {
        $now = Carbon::now();
        $status = $resp->getHealthStatus();

        GatewayHealthStatus::updateOrCreate(
            ['type' => $type, 'entity_id' => $entityId],
            [
                'gateway_alias' => $alias,
                'status'        => $status,
                'http_status'   => $resp->getHttpStatus(),
                'latency_ms'    => $resp->getLatencyMs(),
                'message'       => $resp->getHealthMessage(),
                'fail_streak'   => $status === 'ok' ? 0 : \DB::raw('fail_streak + 1'),
                'last_ok_at'    => $status === 'ok' ? $now : null,
                'checked_at'    => $now,
            ]
        );
    }

    private function persistFail(
        string $type,
        int $entityId,
        string $alias,
        string $message
    ): void {
        GatewayHealthStatus::updateOrCreate(
            ['type' => $type, 'entity_id' => $entityId],
            [
                'gateway_alias' => $alias,
                'status'        => 'fail',
                'message'       => $message,
                'fail_streak'   => \DB::raw('fail_streak + 1'),
                'checked_at'    => now(),
            ]
        );
    }
}
