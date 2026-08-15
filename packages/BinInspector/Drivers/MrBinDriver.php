<?php

declare(strict_types=1);

namespace iEXPackages\BinInspector\Drivers;

use iEXPackages\BinInspector\BinInspectorResult;

/**
 * Драйвер для сервиса MrBin (https://mrbin.io).
 */
final class MrBinDriver extends AbstractDriver
{
    /**
     * Базовый URL API MrBin.
     */
    private const BASE_URL = 'https://mrbin.io';

    /**
     * {@inheritdoc}
     */
    protected function performRequest(string $bin): ?array
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            return null;
        }

        $url = rtrim(self::BASE_URL, '/').'/bins/bin/getFull';

        $response = $this->http
            ->timeout(5)
            ->retry(2, 200)
            ->withToken($this->apiKey, 'Basic')
            ->post($url, [
                'fullBin' => $bin,
            ])
            ->throw();

        /** @var array<mixed>|null $json */
        $json = $response->json();

        return $json ?: null;
    }

    /**
     * {@inheritdoc}
     */
    protected function mapToResult(array $data): ?BinInspectorResult
    {
        if ($data === []) {
            return null;
        }

        // Используем поля, которые были в старом адаптере.
        $paymentSystem = $data['paymentSystem'] ?? null;
        $type          = $data['product']['category'] ?? null;
        $brand         = $data['product']['brand'] ?? null;

        $countryName = $data['countryName'] ?? ($data['country']['name'] ?? null);
        $currency    = $data['currency'] ?? null;

        $bankName  = $data['bankName'] ?? ($data['bank']['name'] ?? null);
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
