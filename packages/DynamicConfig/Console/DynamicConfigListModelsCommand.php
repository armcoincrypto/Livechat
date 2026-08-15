<?php

namespace iEXPackages\DynamicConfig\Console;

use iEXPackages\DynamicConfig\DynamicConfigModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DynamicConfigListModelsCommand extends Command
{
    protected $signature = 'dynamic-config:list-models';

    protected $description = 'Показать все модели настроек (DynamicConfigModel)';

    public function handle(): int
    {
        $paths  = config('dynamic_config.models_paths', [app_path('Settings')]);
        $models = [];

        foreach ($paths as $path) {
            if (!File::isDirectory($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                $fqcn = $this->classFromFile($file->getRealPath());
                if ($fqcn && is_subclass_of($fqcn, DynamicConfigModel::class)) {
                    $models[] = $fqcn;
                }
            }
        }

        if (empty($models)) {
            $this->warn('Модели настроек не найдены.');
            return self::SUCCESS;
        }

        foreach ($models as $class) {
            $this->line('- ' . $class);
        }

        return self::SUCCESS;
    }

    private function classFromFile(string $path): ?string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        if (!preg_match('/^namespace\s+(.+?);/m', $contents, $nsMatch)) {
            return null;
        }

        if (!preg_match('/^final\s+class\s+(\w+)/m', $contents, $classMatch)
            && !preg_match('/^class\s+(\w+)/m', $contents, $classMatch)) {
            return null;
        }

        $namespace = trim($nsMatch[1]);
        $class     = trim($classMatch[1]);

        return $namespace . '\\' . $class;
    }
}
