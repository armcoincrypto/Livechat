<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Firekassa\Messages\Traits;

/**
 * Этот trait сгенерирован автоматически командой gateways:generate-inputs.
 * Он предоставляет методы доступа к inputs.pay.fields из config.php.
 *
 * ⚠ Для группы pay методы генерируются с префиксом getPay* (чтобы не конфликтовать с merchant).
 *
 * Не редактируй этот файл вручную — изменения будут перезаписаны.
 *
 * Требование:
 *  - Класс, который использует этот trait, должен наследоваться от базового Request,
 *    в котором реализован метод inputs(string $group).
 *
 * @mixin \iEXPackages\Payments\Core\Engine\AbstractRequest
 */
trait GeneratedPayInputs
{
    public function getPaySecretKey(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('pay')->get('secret_key');

        return $value === null ? null : (string) $value;
    }

    public function getPaySignToken(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('pay')->get('sign_token');

        return $value === null ? null : (string) $value;
    }

    public function getPaySiteName(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('pay')->get('site_name');

        return $value === null ? null : (string) $value;
    }

    public function getPaySiteUrl(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('pay')->get('site_url');

        return $value === null ? null : (string) $value;
    }
}