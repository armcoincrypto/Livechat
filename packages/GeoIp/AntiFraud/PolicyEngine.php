<?php
declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud;

use iEXPackages\GeoIp\AntiFraud\Rules\RuleInterface;

final class PolicyEngine
{
    /**
     * @param RuleInterface[] $rules
     */
    public function __construct(
        private readonly array $rules,
    ) {}

    public function decide(PolicyContext $ctx): GeoDecision
    {
        foreach ($this->rules as $rule) {
            $decision = $rule->apply($ctx);

            if ($decision !== null) {
                return $decision;
            }
        }

        return GeoDecision::allow('ok');
    }
}
