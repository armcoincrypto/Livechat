<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\BestMerchant\Messages\Traits;

/**
 * Этот trait сгенерирован автоматически командой gateways:generate-inputs.
 * Он предоставляет get* методы для inputs.merchant.fields из config.php.
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
    public function getApiToken(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('merchant')->get('api_token');

        return $value === null ? null : (string) $value;
    }
}