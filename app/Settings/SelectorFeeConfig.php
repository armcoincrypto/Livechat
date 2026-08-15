<?php

namespace App\Settings;

use iEXPackages\DynamicConfig\DynamicConfigModel;

final class SelectorFeeConfig extends DynamicConfigModel
{
    protected function prefix(): string
    {
        return 'selector_fee';
    }

    protected function fields(): array
    {
        return [
            'is_multi_selector',
        ];
    }

    public function isMultiSelector(): bool
    {
        return $this->getBool('is_multi_selector', false);
    }
}
