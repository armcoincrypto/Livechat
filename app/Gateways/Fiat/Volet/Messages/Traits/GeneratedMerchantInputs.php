<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Volet\Messages\Traits;

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
    public function getSciAccountEmail(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('sci_account_email');

        return $value === null ? null : (string) $value;
    }

    public function getSciName(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('sci_name');

        return $value === null ? null : (string) $value;
    }

    public function getSciPassword(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('sci_password');

        return $value === null ? null : (string) $value;
    }

    public function getApiName(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('api_name');

        return $value === null ? null : (string) $value;
    }

    public function getApiSecret(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('api_secret');

        return $value === null ? null : (string) $value;
    }
}