<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Contracts;

/**
 * Маркерный интерфейс: Response, который требует дальнейших проверок (polling/cron).
 *
 * Например:
 *  - payout создан, но tx_hash появится позже;
 *  - deposit/address создан, но подтверждения придут позже;
 *  - статус pending и нужно опрашивать API.
 */
interface PollingResponseInterface
{
    /**
     * Требуется ли ставить задачу на дальнейшие проверки (cron/polling).
     */
    public function isPollingRequired(): bool;

    /**
     * Причина/тип polling (не обязательно).
     * Например: 'tx_hash', 'confirmations', 'status'.
     */
    public function getPollingType(): ?string;

    /**
     * Ключ, по которому надо опрашивать (например externalId/withdrawRecordId).
     * Можно вернуть null, если polling не нужен.
     */
    public function getPollingKey(): ?string;
}
