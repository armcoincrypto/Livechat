<?php
declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud\Rules;

use iEXPackages\GeoIp\AntiFraud\GeoDecision;
use iEXPackages\GeoIp\AntiFraud\PolicyContext;

final class AllowCountriesRule implements RuleInterface
{
    public function name(): string { return 'allow_countries'; }

    public function apply(PolicyContext $ctx): ?GeoDecision
    {
        $allow = array_map('strtoupper', $ctx->allowIso);
        if ($allow === []) return null;

        $iso = strtoupper((string)$ctx->location->countryIso);
        if ($iso === '') return null;

        if (!in_array($iso, $allow, true)) {
            return GeoDecision::deny('country_not_allowed', [
                'country_iso' => $iso,
                'allow' => $allow,
            ], $this->name());
        }

        return null;
    }
}
