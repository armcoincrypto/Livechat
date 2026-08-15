<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\Currency;
use App\Models\DirectionExchange;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use iEXPackages\AMLPlugin\Facades\AMLFacade;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Enums\EffectType;
use iEXPackages\Order\Validation\Result\ValidationResult;
use Illuminate\Support\Facades\Log;

/**
 * AmlRule — AML-проверка адреса (кошелька) для currency2 ("Получаю").
 *
 * Поведение:
 * - Если outcome_account пустой → rule пропускается
 * - Если условия активации AML не выполнены → пропускается
 * - Если AML pending → ошибка (как раньше)
 * - Если AML successful → сохраняем snapshot через effect AmlSnapshot
 * - Если валюта настроена на блокировку при рисках → возвращаем ошибку
 *
 * Важно:
 * - rule НЕ использует request()/auth()
 * - rule НЕ пишет в БД (только effect)
 */
final class AmlRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        $currencyOut = $direction->currency2;
        if (!$currencyOut instanceof Currency) {
            return $result;
        }

        // Реквизит "Получаю" обязателен для AML
        $outcomeAccountRaw = $context->data->getString('outcome_account');
        if ($outcomeAccountRaw === null || $outcomeAccountRaw === '') {
            return $result;
        }

        // Нормализуем адрес
        $account = preg_replace('/\s+/', '', (string)$outcomeAccountRaw) ?? (string)$outcomeAccountRaw;
        $account = trim($account);

        if ($account === '') {
            return $result;
        }

        // Суммы: без float, только BigDecimal
        $incomeAmount = $this->readMoney($context->data->getString('income_amount'));
        $outcomeAmount = $this->readMoney($context->data->getString('outcome_amount'));

        if ($incomeAmount === null || $outcomeAmount === null) {
            // Если суммы нет — не активируем AML
            return $result;
        }

        if (!$this->shouldActivateAmlCheck($currencyOut, $incomeAmount)) {
            return $result;
        }

        // Драйвер AML
        try {
            $driver = AMLFacade::driver(
                $currencyOut->aml_service->alias,
                $currencyOut->aml_service
            )->useCache();
        } catch (\Throwable $e) {
            Log::error('AML driver init error: ' . $e->getMessage());
            return $result->addError(
                'amlvalidator_check',
                __('При выполнении проверки адреса произошла ошибка. Обратитесь, пожалуйста, в службу поддержки.'),
                'aml_driver_error'
            );
        }

        try {
            $checker = $driver->checkAddress([
                'currency' => (string)$currencyOut->designation_xml,
                'address'  => $account,
                // если драйвер принимает float — вынужденно приводим,
                // но snapshot и сравнения делаем строками
                'amount'   => (float)$outcomeAmount->__toString(),
            ]);

            if ($checker->isPending()) {
                Log::info('AML pending for account', ['account_hash' => sha1($account)]);
                return $result->addError(
                    'amlvalidator_check',
                    __('Адрес проходит проверку в системе AML. Пожалуйста, подождите завершения проверки.'),
                    'aml_pending'
                );
            }

            if (!$checker->isSuccessful()) {
                // мягкое поведение: если не successful и не pending — просто пропускаем
                return $result;
            }

            $isRiskExceeded = (bool)$checker->isRiskExceeded();
            $exceededRisks = (array)$checker->getExceededRiskSignals();

            $snapshot = $this->buildAmlResponse($checker, $isRiskExceeded, $exceededRisks);

            // effect: сохранить snapshot (запись в БД сделает EffectsApplier)
            $result->addEffect(EffectType::AmlSnapshot, [
                'id_aml_service' => (int)($currencyOut->aml_service->id ?? $currencyOut->id_aml_service ?? 0),
                'alias' => (string)($currencyOut->aml_service->alias ?? ''),
                'ext_params' => $snapshot,
                'method' => 'address',
            ]);

            // Блокировка при рисках — как у тебя
            if ((int)($currencyOut->error_for_aml_check_wallet ?? 0) === 1) {
                if (!$isRiskExceeded && !empty($exceededRisks)) {
                    $maxRiskCategory = array_key_first($exceededRisks);

                    return $result->addError(
                        'amlvalidator_check',
                        sprintf(
                            __('Создание заявки невозможно. Указанный вами адрес (%s) имеет высокий уровень риска по категории «%s».'),
                            $account,
                            (string)$maxRiskCategory
                        ),
                        'aml_risk_category_block'
                    );
                }

                if ($isRiskExceeded) {
                    return $result->addError(
                        'amlvalidator_check',
                        sprintf(
                            __('Создание заявки невозможно. Указанный вами адрес (%s) имеет недопустимый уровень риска: %s.'),
                            $account,
                            (string)$checker->getRiskScore()
                        ),
                        'aml_risk_block'
                    );
                }
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('AML validation error', [
                'exception' => $e->getMessage(),
                'account_hash' => sha1($account),
            ]);

            return $result->addError(
                'amlvalidator_check',
                __('При выполнении проверки адреса произошла ошибка. Обратитесь, пожалуйста, в службу поддержки.'),
                'aml_error'
            );
        }
    }

    /**
     * Условия активации AML:
     * - назначен AML-сервис
     * - включена проверка кошелька
     * - сумма "Отдаю" >= порога aml_wallet_from_amount
     */
    private function shouldActivateAmlCheck(Currency $currencyOut, BigDecimal $incomeAmount): bool
    {
        $serviceId = (int)($currencyOut->id_aml_service ?? 0);
        $enabled = (int)($currencyOut->is_aml_check_wallet ?? 0) === 1;

        $threshold = $this->readMoney((string)($currencyOut->aml_wallet_from_amount ?? '0'));
        if ($threshold === null) {
            $threshold = BigDecimal::zero();
        }

        return $serviceId > 0
            && $enabled
            && $incomeAmount->isGreaterThan($threshold);
    }

    /**
     * Построение snapshot AML (как было у тебя).
     *
     * @param mixed $checker
     * @param bool $isRiskExceeded
     * @param array $exceededRisks
     * @return array<string,mixed>
     */
    private function buildAmlResponse($checker, bool $isRiskExceeded, array $exceededRisks): array
    {
        $response = [
            'risk_score' => (string)$checker->getRiskScore(),
            'all_risks' => $checker->getDataToDatabase(),
            'is_check_address' => $isRiskExceeded,
        ];

        if (!$isRiskExceeded && !empty($exceededRisks)) {
            $maxRiskCategory = array_key_first($exceededRisks);
            $response['max_risk_for_category'] = $maxRiskCategory;
            $response['max_risk_for_category_value'] = (float)iex_number_format($exceededRisks[$maxRiskCategory]);
        }

        return $response;
    }

    /**
     * Парсер суммы в BigDecimal (без float).
     */
    private function readMoney(?string $raw, int $scale = 18): ?BigDecimal
    {
        if ($raw === null) return null;

        $s = trim($raw);
        if ($s === '') return null;

        $s = str_replace(["\u{00A0}", ' '], '', $s);
        $s = str_replace(',', '.', $s);
        $s = preg_replace('/\.$/', '', $s) ?? $s;

        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $s)) {
            return null;
        }

        try {
            return BigDecimal::of($s)->toScale($scale, RoundingMode::DOWN);
        } catch (\Throwable) {
            return null;
        }
    }
}
