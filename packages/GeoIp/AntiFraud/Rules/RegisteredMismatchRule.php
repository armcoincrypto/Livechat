<?php
declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud\Rules;

use iEXPackages\GeoIp\AntiFraud\GeoDecision;
use iEXPackages\GeoIp\AntiFraud\PolicyContext;

final class RegisteredMismatchRule implements RuleInterface
{
    public function name(): string { return 'registered_mismatch'; }

    public function apply(PolicyContext $ctx): ?GeoDecision
    {
        if (!$ctx->checkRegisteredMismatch) return null;

        $c = strtoupper((string)$ctx->location->countryIso);
        $r = strtoupper((string)($ctx->location->registeredCountryIso ?? ''));

        if ($c === '' || $r === '' || $c === $r) {
            return null;
        }

        return $ctx->registeredMismatchAction === 'deny'
            ? GeoDecision::deny('registered_country_mismatch', ['country' => $c, 'registered' => $r], $this->name())
            : GeoDecision::review('registered_country_mismatch', ['country' => $c, 'registered' => $r], $this->name());
    }
}
