<?php
declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud\Rules;

use iEXPackages\GeoIp\AntiFraud\GeoDecision;
use iEXPackages\GeoIp\AntiFraud\PolicyContext;

final class DenyCountriesRule implements RuleInterface
{
    public function name(): string { return 'deny_countries'; }

    public function apply(PolicyContext $ctx): ?GeoDecision
    {
        $iso = strtoupper((string)$ctx->location->countryIso);
        if ($iso === '') return null;

        $deny = array_map('strtoupper', $ctx->denyIso);
        if ($deny !== [] && in_array($iso, $deny, true)) {
            return GeoDecision::deny('country_denied', ['country_iso' => $iso], $this->name());
        }

        return null;
    }
}
