<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Callback\Traits;

trait CallbackInputTrait
{
    private function normalizeAlias(string $alias): string
    {
        $alias = strtolower(trim($alias));
        return $alias;
    }
}
