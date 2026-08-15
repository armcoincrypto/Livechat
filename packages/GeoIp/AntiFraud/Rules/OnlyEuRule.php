<?php
declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud\Rules;

use iEXPackages\GeoIp\AntiFraud\GeoDecision;
use iEXPackages\GeoIp\AntiFraud\PolicyContext;

final class OnlyEuRule implements RuleInterface
{
    public function name(): string { return 'only_eu'; }

    public function apply(PolicyContext $ctx): ?GeoDecision
    {
        if (!$ctx->onlyEu) return null;

        if ($ctx->location->countryIsEU !== true) {
            return GeoDecision::deny('country_not_in_eu', [
                'country_iso' => $ctx->location->countryIso,
                'is_eu' => $ctx->location->countryIsEU,
            ], $this->name());
        }

        return null;
    }
}
