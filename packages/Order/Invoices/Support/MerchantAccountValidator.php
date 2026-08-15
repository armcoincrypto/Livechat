<?php

declare(strict_types=1);

namespace iEXPackages\Order\Invoices\Support;

use App\Models\Currency;
use App\Models\GatewayMerchant;
use App\Support\ValidatorWallet;
use Illuminate\Support\Facades\Log;

/**
 * MerchantAccountValidator
 *
 * Назначение:
 * - Берёт validator_type для связки (Currency ↔ Merchant) из pivot currency_merchants.validator_type
 * - Если валидатор задан — проверяет реквизит (address/account) через ValidatorWallet
 *
 * Почему здесь (Order\Invoices):
 * - Сейчас реквизиты формируются и выдаются в рамках Invoices (HasMerchant/RequisiteManager),
 *   поэтому удобнее держать сервис рядом с этой логикой.
 */
final class MerchantAccountValidator
{
    /**
     * Проверяет реквизит мерчанта, если для связки (currency ↔ merchant) задан validator_type.
     *
     * Правила:
     * - Если validator_type пустой → TRUE (валидатор не настроен)
     * - Если реквизит пустой → FALSE
     * - Если проверка не прошла → FALSE + лог
     *
     * @param Currency $currency Валюта (обычно currency1)
     * @param GatewayMerchant $merchant Мерчант
     * @param string $accountNumber Реквизит (адрес/кошелёк/аккаунт)
     * @param array<string,mixed> $context Контекст для логов (task_id, source и т.п.)
     * @return bool
     */
    public function validateIfConfigured(
        Currency $currency,
        GatewayMerchant $merchant,
        string $accountNumber,
        array $context = [],
    ): bool {
        $accountNumber = trim($accountNumber);
        if ($accountNumber === '') {
            return false;
        }

        $merchantId = (int) ($merchant->id ?? 0);
        if ($merchantId <= 0) {
            return true;
        }

        $validatorType = $this->resolveValidatorTypeFromPivot($currency, $merchantId);


        if ($validatorType === '') {
            return true; // валидатор не выбран
        }

        $ok = app(ValidatorWallet::class)->setName($validatorType)
            ->verify($accountNumber);

        if ($ok) {
            return true;
        }

        Log::error('Валидатор реквизитов мерчанта: адрес не прошёл проверку', [
            ...$context,
            'currency_id'    => $currency->id ?? null,
            'merchant_id'    => $merchantId,
            'merchant_alias' => $merchant->alias ?? null,
            'validator_type' => $validatorType,
            'account'        => $accountNumber,
        ]);

        return false;
    }

    /**
     * Возвращает выбранный validator_type для связки (currency ↔ merchant).
     *
     * @param Currency $currency
     * @param GatewayMerchant $merchant
     * @return string
     */
    public function getValidatorType(Currency $currency, GatewayMerchant $merchant): string
    {
        $merchantId = (int) ($merchant->id ?? 0);
        if ($merchantId <= 0) {
            return '';
        }

        return $this->resolveValidatorTypeFromPivot($currency, $merchantId);
    }

    /**
     * Достаёт validator_type из pivot currency_merchants для конкретного merchant_id.
     *
     * @param Currency $currency
     * @param int $merchantId
     * @return string
     */
    private function resolveValidatorTypeFromPivot(Currency $currency, int $merchantId): string
    {
        try {
            // Если merchants уже загружены — берём из pivot в коллекции
            if ($currency->relationLoaded('merchants')) {
                $related = $currency->merchants
                    ->first(static fn ($m) => (int) ($m->pivot->gateway_merchant_id ?? 0) === $merchantId);

                return trim((string) ($related?->pivot?->validator_type ?? ''));
            }

            // Иначе — читаем напрямую из pivot таблицы
            return trim((string) $currency->merchants()
                ->where('currency_merchants.gateway_merchant_id', $merchantId)
                ->value('validator_type'));
        } catch (\Throwable $e) {
            Log::warning('MerchantAccountValidator: ошибка чтения pivot currency_merchants', [
                'currency_id' => $currency->id ?? null,
                'merchant_id' => $merchantId,
                'error'       => $e->getMessage(),
            ]);
            return '';
        }
    }
}
