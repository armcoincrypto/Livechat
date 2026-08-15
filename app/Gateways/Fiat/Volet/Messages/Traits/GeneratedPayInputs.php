<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Volet\Messages\Traits;

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
    public function getPayApiAccountEmail(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('pay')->get('api_account_email');

        return $value === null ? null : (string) $value;
    }

    public function getPayApiName(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('pay')->get('api_name');

        return $value === null ? null : (string) $value;
    }

    public function getPayApiSecret(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('pay')->get('api_secret');

        return $value === null ? null : (string) $value;
    }
}