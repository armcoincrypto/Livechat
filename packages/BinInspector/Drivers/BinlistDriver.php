<?php

declare(strict_types=1);

namespace iEXPackages\BinInspector\Drivers;

use iEXPackages\BinInspector\BinInspectorResult;

/**
 * Драйвер для сервиса Binlist (https://binlist.net).
 */
final class BinlistDriver extends AbstractDriver
{
    /**
     * Базовый URL API Binlist.
     */
    private const BASE_URL = 'https://lookup.binlist.net';

    /**
     * {@inheritdoc}
     */
    protected function performRequest(string $bin): ?array
    {
        $url  = rtrim(self::BASE_URL, '/').'/'.$bin;
        $data = $this->httpGet($url);

        return $data ?: null;
    }

    /**
     * {@inheritdoc}
     */
    protected function mapToResult(array $data): ?BinInspectorResult
    {
        if ($data === []) {
            return null;
        }

        $paymentSystem = $data['scheme'] ?? null;
        $type          = $data['type'] ?? null;
        $brand         = $data['brand'] ?? null;

        $countryName = $data['country']['name'] ?? null;
        $currency    = $data['country']['currency'] ?? null;

        $bankName  = $data['bank']['name'] ?? null;
        $bankUrl   = $data['bank']['url'] ?? null;
        $bankPhone = $data['bank']['phone'] ?? null;

        $isValid = $paymentSystem !== null || $bankName !== null || $countryName !== null;

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
