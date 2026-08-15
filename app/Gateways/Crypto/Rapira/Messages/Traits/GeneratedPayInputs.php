<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira\Messages\Traits;

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
    public function getPayPrivateKey(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('pay')->get('private_key');

        return $value === null ? null : (string) $value;
    }

    public function getPayUuid(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('pay')->get('uuid');

        return $value === null ? null : (string) $value;
    }

    public function getPayApiHost(): ?string
    {
        /** @var \iEXPackages\Payments\Core\Engine\AbstractRequest $this */
        $value = $this->inputs('pay')->get('api_host');

        return $value === null ? null : (string) $value;
    }
}