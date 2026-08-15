<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Console;

use Illuminate\Console\Command;
use iEXPackages\Payments\Core\Engine\GatewayManager;
use iEXPackages\Payments\Core\Validation\GatewayConfigValidator;
use iEXPackages\Payments\Core\Validation\ValidationError;

final class ValidateGatewaysConfig extends Command
{
    protected $signature = 'gateways:validate
        {--only= : Alias шлюза (например rapira) — проверить только его}
        {--strict : Строгий режим (больше ошибок вместо warning)}
        {--fail-on-warn : Считать warnings ошибками (вернуть non-zero exit)}';

    protected $description = 'Проверяет корректность config.php всех шлюзов (schema validation).';

    public function handle(GatewayManager $manager, GatewayConfigValidator $validator): int
    {
        $only = (string)($this->option('only') ?? '');
        $strict = (bool)$this->option('strict');
        $failOnWarn = (bool)$this->option('fail-on-warn');

        $gateways = $manager->all(); // alias => class

        if ($only !== '') {
            if (!isset($gateways[$only])) {
                $this->error("Unknown gateway alias: {$only}");
                $this->line('Known: ' . implode(', ', array_keys($gateways)));
                return self::FAILURE;
            }

            $gateways = [$only => $gateways[$only]];
        }

        $totalErrors = 0;
        $totalWarnings = 0;

        foreach ($gateways as $alias => $class) {
            if (!is_string($class) || $class === '' || !class_exists($class)) {
                $this->warn("[{$alias}] Gateway class not found: {$class}");
                $totalErrors++;
                continue;
            }

            try {
                $gateway = new $class([]);
                $cfg = $gateway->gatewayConfig();

                $issues = $validator->validate($cfg, ['strict' => $strict]);

                if ($issues === []) {
                    $this->info("[{$alias}] OK");
                    continue;
                }

                $this->line("[{$alias}]");

                foreach ($issues as $issue) {
                    if ($issue->level === 'error') {
                        $totalErrors++;
                        $this->error("  {$issue->path}: {$issue->message} ({$issue->code})");
                    } else {
                        $totalWarnings++;
                        $this->warn(" {$issue->path}: {$issue->message} ({$issue->code})");
                    }
                }

            } catch (\Throwable $e) {
                $totalErrors++;
                $this->error("[{$alias}] Exception: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->line("Result: errors={$totalErrors}, warnings={$totalWarnings}");

        if ($totalErrors > 0) {
            return self::FAILURE;
        }

        if ($failOnWarn && $totalWarnings > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
