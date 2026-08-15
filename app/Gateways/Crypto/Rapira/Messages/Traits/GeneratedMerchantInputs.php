<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira\Messages\Traits;

/**
 * Этот trait сгенерирован автоматически командой gateways:generate-inputs.
 * Он предоставляет методы доступа к inputs.merchant.fields из config.php.
 *
 * Для группы merchant методы генерируются как get*.
 *
 * Не редактируй этот файл вручную — изменения будут перезаписаны.
 *
 * Требование:
 *  - Класс, который использует этот trait, должен наследоваться от базового Request,
 *    в котором реализован метод inputs(string $group).
 *
 * @mixin \iEXPackages\Payments\Core\Engine\AbstractRequest
 */
trait GeneratedMerchantInputs
{
    public function getPrivateKey(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('private_key');

        return $value === null ? null : (string) $value;
    }

    public function getUuid(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('uuid');

        return $value === null ? null : (string) $value;
    }

    public function getApiHost(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('api_host');

        return $value === null ? null : (string) $value;
    }
}