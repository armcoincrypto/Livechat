<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Engine;

use iEXPackages\Payments\Core\Contracts\GatewayInterface;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class GatewayManager
{
    /**
     * @var array<string, class-string<GatewayInterface>>
     */
    private array $gatewayMap = [];

    public function __construct(
        array $gatewayMap = [],
        private readonly array $scanSources = [],
    ) {
        $this->gatewayMap = $gatewayMap;
    }

    public static function fromConfigAndScan(): self
    {
        $config = config('payments', []);

        $map  = $config['gateways'] ?? [];
        $scan = $config['scan'] ?? [];

        $manager = new self($map, $scan);
        $manager->discoverGateways();

        return $manager;
    }

    public function forAlias(string $alias, array $merchantConfig): GatewayInterface
    {
        $class = $this->gatewayMap[$alias] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Unknown gateway alias: {$alias}");
        }

        return new $class($merchantConfig);
    }

    public function hasAlias(string $alias): bool
    {
        return isset($this->gatewayMap[$alias]);
    }

    public function discoverGateways(): void
    {
        foreach ($this->scanSources as $source) {
            $this->scanFolder($source['namespace'], $source['path']);
        }
    }

    private function scanFolder(string $namespace, string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getFilename() !== 'Gateway.php') {
                continue;
            }

            $dir      = $file->getPath();
            $relative = trim(str_replace($path, '', $dir), DIRECTORY_SEPARATOR);
            $subNs    = $relative ? '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative) : '';

            $class = $namespace . $subNs . '\\Gateway';

            if (!class_exists($class)) {
                continue;
            }

            $ref = new ReflectionClass($class);

            if (!$ref->implementsInterface(GatewayInterface::class) || $ref->isAbstract()) {
                continue;
            }

            /** @var GatewayInterface $instance */
            $instance = $ref->newInstance([]);

            $alias = $instance->getAlias();

            if (!isset($this->gatewayMap[$alias])) {
                $this->gatewayMap[$alias] = $class;
            }
        }
    }

    public function all(): array
    {
        return $this->gatewayMap;
    }
}
