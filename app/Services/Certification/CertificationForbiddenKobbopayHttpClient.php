<?php

declare(strict_types=1);

namespace App\Services\Certification;

use iEXPackages\Payments\Core\Contracts\GatewayHttpClientInterface;
use iEXPackages\Payments\Logging\GatewayLogContext;

/**
 * When a certification session is active, any Kobbopay/Kobbex HTTP is a hard stop.
 * Pass-through otherwise so production rails are unchanged.
 */
final class CertificationForbiddenKobbopayHttpClient implements GatewayHttpClientInterface
{
    public function __construct(
        private readonly GatewayHttpClientInterface $inner,
        private readonly CertificationContext $context,
    ) {
    }

    public function send(
        array $spec,
        string $method,
        string $path,
        array $data,
        GatewayLogContext $ctx,
        string $format = 'asJson'
    ): array {
        if ($this->context->isActive()) {
            $this->context->recordKobbopayHttpAttempt();
            throw new CertificationKobbopayForbiddenException(
                'REAL_KOBBOPAY_CALL_POSSIBLE=NO certification session forbids gateway HTTP'
            );
        }

        return $this->inner->send($spec, $method, $path, $data, $ctx, $format);
    }
}
