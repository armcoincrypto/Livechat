<?php

declare(strict_types=1);

namespace iEXPackages\BinInspector\Drivers;

use iEXPackages\BinInspector\BinInspectorResult;

/**
 * Драйвер для сервиса BinCodes (https://bincodes.com).
 */
final class BinCodesDriver extends AbstractDriver
{
    /**
     * Базовый URL API BinCodes.
     */
    private const BASE_URL = 'https://api.bincodes.com/bin/';

    /**
     * {@inheritdoc}
     */
    protected function performRequest(string $bin): ?array
    {
        // Если API-ключ не задан — не выполняем запрос.
        if ($this->apiKey === null || $this->apiKey === '') {
            return null;
        }

        $data = $this->httpGet(self::BASE_URL, [
            'format'  => 'json',
            'api_key' => $this->apiKey,
            'bin'     => $bin,
        ]);

        return $data ?: null;
    }

    /**
     * {@inheritdoc}
     */
    protected function mapToResult(array $data): ?BinInspectorResult
    {
        if ($data === [] || ! empty($data['error'])) {
            return null;
        }

        // Маппинг может быть уточнён под реальный ответ BinCodes.
        $paymentSystem = $data['card'] ?? null;
        $type          = $data['type'] ?? null;
        $brand         = $data['level'] ?? null;

        $countryName = $data['country'] ?? null;
        $currency    = $data['currency'] ?? null;

        $bankName  = $data['bank'] ?? null;
        $bankUrl   = $data['website'] ?? null;
        $bankPhone = $data['phone'] ?? null;

        $isValid = isset($data['valid']) ? (bool) $data['valid'] : true;

        return new BinInspectorResult(
            paymentSystem: $paymentSystem,
            type:          $type,
            isValid:       $isValid,
            brand:         $brand,
            countryName:   $countryName,
            currency:      $currency,
            bankName:      $bankName,
            bankUrl:       $bankUrl,
            bankPhone:     $bankPhone,
            raw:           $data,
        );
    }
}
