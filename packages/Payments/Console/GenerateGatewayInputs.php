<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use iEXPackages\Payments\Core\Engine\GatewayManager;
use iEXPackages\Payments\Core\Contracts\GatewayInterface;
use ReflectionClass;

final class GenerateGatewayInputs extends Command
{
    protected $signature = 'gateways:generate-inputs
        {group? : merchant|pay|all (если не указано — генерирует merchant+pay)}
        {--only= : Alias шлюза (например rapira) — генерировать только для него}
        {--class= : Полный FQCN класса Gateway (например App\\Gateways\\Crypto\\Rapira\\Gateway)}
        {--target= : Абсолютный путь до папки шлюза, куда писать (например /.../app/Gateways/Crypto/Rapira)}
        {--force : Перезаписать файл trait, если уже существует}';

    protected $description = 'Генерирует traits с get* методами по inputs.*.fields. Если group не указан — генерирует merchant+pay. Для pay — getPay* во избежание конфликтов.';

    public function handle(GatewayManager $manager, Filesystem $files): int
    {
        $groupArg = $this->argument('group');
        $groupArg = is_string($groupArg) ? trim($groupArg) : '';

        // group не указан -> merchant+pay
        // group=all -> merchant+pay
        // group=merchant/pay -> только одну
        $groups = match ($groupArg) {
            '', 'all' => ['merchant', 'pay'],
            default   => [$groupArg],
        };

        $only   = (string) ($this->option('only') ?? '');
        $class  = (string) ($this->option('class') ?? '');
        $target = (string) ($this->option('target') ?? '');
        $force  = (bool) $this->option('force');

        $gateways = $manager->all(); // alias => class-string
        if (empty($gateways)) {
            $this->warn('Gateways list is empty. Проверь config(payments.php) scan/gateways и discoverGateways().');
            return self::SUCCESS;
        }

        // --class: только этот gateway
        if ($class !== '') {
            if (!class_exists($class)) {
                $this->error("Gateway class not found: {$class}");
                return self::FAILURE;
            }

            $alias = $only !== '' ? $only : $this->guessAliasFromClass($class);
            $gateways = [$alias => $class];
        }

        // --only: фильтр по alias
        if ($only !== '' && $class === '') {
            if (!isset($gateways[$only])) {
                $this->error("Gateway alias not found in manager->all(): {$only}");
                $this->info('Доступные alias: ' . implode(', ', array_keys($gateways)));
                return self::FAILURE;
            }

            $gateways = [$only => $gateways[$only]];
        }

        // Валидация groups
        foreach ($groups as $g) {
            if (!is_string($g) || $g === '') {
                $this->error('Invalid group argument.');
                return self::FAILURE;
            }
        }

        foreach ($gateways as $alias => $gatewayClass) {
            if (!is_string($gatewayClass) || $gatewayClass === '' || !class_exists($gatewayClass)) {
                $this->warn("[{$alias}] class [{$gatewayClass}] not found, skipping");
                continue;
            }

            /** @var GatewayInterface $gateway */
            $gateway = new $gatewayClass([]);
            $definition = $gateway->gatewayConfig();

            foreach ($groups as $group) {
                $fields = $definition->fields($group); // ТОЛЬКО fields

                if (empty($fields)) {
                    $this->info("[{$alias}] inputs.{$group}.fields is empty, skipping");
                    continue;
                }

                $this->generateTraitForGateway(
                    files: $files,
                    gatewayClass: $gatewayClass,
                    group: $group,
                    fields: $fields,
                    forcedTargetDir: $target !== '' ? rtrim($target, '/\\') : null,
                    forceOverwrite: $force
                );

                $this->info("[{$alias}] OK: Generated Generated" . ucfirst($group) . "Inputs.php");
            }
        }

        $this->info('Done: traits generated.');
        return self::SUCCESS;
    }

    protected function generateTraitForGateway(
        Filesystem $files,
        string $gatewayClass,
        string $group,
        array $fields,
        ?string $forcedTargetDir = null,
        bool $forceOverwrite = false
    ): void {
        $ref = new ReflectionClass($gatewayClass);

        $gatewayNs = $ref->getNamespaceName();
        $gatewayDir = $forcedTargetDir ?? dirname($ref->getFileName());

        $traitNamespace = $gatewayNs . '\\Messages\\Traits';
        $traitName = 'Generated' . ucfirst($group) . 'Inputs';

        $traitDir  = $gatewayDir . '/Messages/Traits';
        $traitPath = $traitDir . '/' . $traitName . '.php';

        if (!is_dir($traitDir)) {
            $files->makeDirectory($traitDir, 0777, true);
        }

        if (file_exists($traitPath) && !$forceOverwrite) {
            $this->warn("SKIP: {$traitPath} (exists, use --force)");
            return;
        }

        $methodsCode = $this->buildMethods($fields, $group);

        $prefixNote = $this->methodPrefix($group) === 'getPay'
            ? "⚠ Для группы pay методы генерируются с префиксом getPay* (чтобы не конфликтовать с merchant)."
            : "Для группы {$group} методы генерируются как get*.";

        $code = <<<PHP
<?php

declare(strict_types=1);

namespace {$traitNamespace};

/**
 * Этот trait сгенерирован автоматически командой gateways:generate-inputs.
 * Он предоставляет методы доступа к inputs.{$group}.fields из config.php.
 *
 * {$prefixNote}
 *
 * Не редактируй этот файл вручную — изменения будут перезаписаны.
 *
 * Требование:
 *  - Класс, который использует этот trait, должен наследоваться от базового Request,
 *    в котором реализован метод inputs(string \$group).
 *
 * @mixin \\iEXPackages\\Payments\\Core\\Engine\\AbstractRequest
 */
trait {$traitName}
{
{$methodsCode}}
PHP;

        $files->put($traitPath, $code);
    }

    protected function buildMethods(array $fields, string $group): string
    {
        $lines = [];
        $prefix = $this->methodPrefix($group);

        foreach ($fields as $field) {
            $key = $field['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }

            $methodSuffix = str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
            $methodName   = $prefix . $methodSuffix;

            $lines[] = <<<PHP
    public function {$methodName}(): ?string
    {
        /** @var \\iEXPackages\\Payments\\Core\\Engine\\AbstractRequest \$this */
        \$value = \$this->inputs('{$group}')->get('{$key}');

        return \$value === null ? null : (string) \$value;
    }

PHP;
        }

        return implode("\n", $lines);
    }

    protected function methodPrefix(string $group): string
    {
        $group = strtolower(trim($group));

        if ($group === 'merchant') {
            return 'get';
        }

        if ($group === 'pay') {
            return 'getPay';
        }

        $studly = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $group)));

        return 'get' . $studly;
    }

    protected function guessAliasFromClass(string $gatewayClass): string
    {
        $ref = new ReflectionClass($gatewayClass);
        $ns  = $ref->getNamespaceName();
        $parts = explode('\\', $ns);
        $last = end($parts) ?: 'gateway';

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $last) ?? $last);
    }
}
