<?php

declare(strict_types=1);

namespace iEXPackages\BinInspector;

/**
 * Результат проверки BIN / карты в нормализованном виде.
 */
final class BinInspectorResult
{
    /**
     * @param string|null $paymentSystem Платёжная система (Visa, MasterCard и т.д.)
     * @param string|null $type          Тип карты (debit, credit, commercial и т.п.)
     * @param bool        $isValid       Флаг валидности (по данным провайдера / логике модуля)
     * @param string|null $brand         Бренд / уровень карты (Gold, Platinum и т.п.)
     * @param string|null $countryName   Название страны банka-эмитента
     * @param string|null $currency      Валюта страны / карты (ISO-код, например USD)
     * @param string|null $bankName      Название банка
     * @param string|null $bankUrl       Официальный сайт банка
     * @param string|null $bankPhone     Телефон поддержки банка
     * @param array       $raw           Сырые данные, возвращённые провайдером
     */
    public function __construct(
        public readonly ?string $paymentSystem,
        public readonly ?string $type,
        public readonly bool    $isValid,
        public readonly ?string $brand,
        public readonly ?string $countryName,
        public readonly ?string $currency,
        public readonly ?string $bankName,
        public readonly ?string $bankUrl,
        public readonly ?string $bankPhone,
        public readonly array   $raw,
    ) {
    }

    /**
     * Преобразовать результат в массив.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'paymentSystem' => $this->paymentSystem,
            'type'          => $this->type,
            'isValid'       => $this->isValid,
            'brand'         => $this->brand,
            'countryName'   => $this->countryName,
            'currency'      => $this->currency,
            'bankName'      => $this->bankName,
            'bankUrl'       => $this->bankUrl,
            'bankPhone'     => $this->bankPhone,
            'raw'           => $this->raw,
        ];
    }
}
