<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Validation;

use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Validation\Rules\CallbackRules;
use iEXPackages\Payments\Core\Validation\Rules\CapabilitiesRules;
use iEXPackages\Payments\Core\Validation\Rules\InputsRules;
use iEXPackages\Payments\Core\Validation\Rules\MetaRules;
use iEXPackages\Payments\Core\Validation\Rules\OperationsRules;
use iEXPackages\Payments\Core\Validation\Rules\OptionsRules;

final class GatewayConfigValidator
{
    /**
     * @param array{strict?:bool} $options
     * @return ValidationError[]
     */
    public function validate(GatewayConfig $config, array $options = []): array
    {
        $strict = (bool)($options['strict'] ?? false);

        $errors = [];

        $errors = array_merge($errors, MetaRules::check($config, $strict));
        $errors = array_merge($errors, CapabilitiesRules::check($config, $strict));
        $errors = array_merge($errors, OperationsRules::check($config, $strict));
        $errors = array_merge($errors, InputsRules::check($config, $strict));
        $errors = array_merge($errors, OptionsRules::check($config, $strict));
        $errors = array_merge($errors, CallbackRules::check($config, $strict));

        return $errors;
    }
}
