<?php
declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud\Rules;

use iEXPackages\GeoIp\AntiFraud\GeoDecision;
use iEXPackages\GeoIp\AntiFraud\PolicyContext;

final class IpQualityRule implements RuleInterface
{
    public function name(): string { return 'ip_quality'; }

    public function apply(PolicyContext $ctx): ?GeoDecision
    {
        $iso = $ctx->location->countryIso;

        if ($iso === null || trim($iso) === '') {
            return $ctx->denyIfUnknown
                ? GeoDecision::deny('country_unknown', ['ip' => $ctx->location->ip], $this->name())
                : GeoDecision::review('country_unknown', ['ip' => $ctx->location->ip], $this->name());
        }

        return null;
    }
}
