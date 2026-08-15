<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Firekassa\Messages\Traits;

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
    public function getSecretKey(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('secret_key');

        return $value === null ? null : (string) $value;
    }

    public function getSignToken(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('sign_token');

        return $value === null ? null : (string) $value;
    }

    public function getSiteName(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('site_name');

        return $value === null ? null : (string) $value;
    }

    public function getSiteUrl(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('site_url');

        return $value === null ? null : (string) $value;
    }
}