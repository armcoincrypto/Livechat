<?php

namespace iEXPackages\Transaction\Concerns;

use App\Models\AMLResponseData;
use App\Models\Currency;
use Exception;
use iEXPackages\AMLPlugin\Contracts\AMLResponseInterface;
use iEXPackages\AMLPlugin\Facades\AMLFacade;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Trait AMLValidator
 *
 * Реализует проверки AML для транзакций и адресов с использованием внешних AML-сервисов.
 *
 * @package iEXPackages\Transaction\Concerns
 */
trait AMLValidator
{
    /**
     * Проверка AML для адреса отправления транзакции.
     *
     * @param Currency $currencyOut Валюта отправления
     *
     * @throws Exception Если AML-проверка выявила высокий риск
     */
    protected function validatedAMLAddress(Currency $currencyOut): void
    {
        $existingAmlCheck = AMLResponseData::where([
            ['id_task', $this->transaction->id],
            ['method', 'address']
        ])->first();

        if ($existingAmlCheck) {
            if (Arr::get($existingAmlCheck->ext_params, 'is_check_address') === 1) {
                throw new Exception(__('Проверка приостановлена, у адреса плохой AML результат.'));
            }
            return;
        }

        $account = trim($this->transaction->to_shot);
        $in_price = (float)trim($this->transaction->give_price, '.');
        $out_price = (float)trim($this->transaction->receiving_price, '.');

        if ($currencyOut->aml_wallet_from_amount >= $in_price) {
            return;
        }

        /** @var AMLResponseInterface $amlChecker */
        $amlChecker = AMLFacade::driver(
            $currencyOut->aml_service->alias,
            $currencyOut->aml_service
        )->useCache()->checkAddress([
            'currency' => $currencyOut->designation_xml,
            'address'  => $account,
            'amount'   => $out_price
        ]);

        if ($amlChecker->isPending()) {
            throw new Exception(__('Адрес проверяется системой AML, пожалуйста, подождите...'));
        }

        if (!$amlChecker->isSuccessful()) {
            Log::warning('AML Address check unsuccessful', ['account' => $account]);
            throw new Exception(__('Ошибка AML-сервиса при проверке адреса.'));
        }

        $amlResponse = $this->buildAmlResponse($amlChecker, 'address');

        AMLResponseData::create([
            'id_task' => $this->transaction->id,
            'id_aml_service' => $currencyOut->aml_service->id,
            'alias' => $currencyOut->aml_service->alias,
            'ext_params' => $amlResponse,
            'method' => 'address'
        ]);

        if ($currencyOut->error_for_aml_check_wallet && $amlChecker->isRiskExceeded()) {
            throw new Exception(__('Проверка приостановлена, у адреса плохой AML рейтинг.'));
        }
    }

    /**
     * Проверка AML для входящей транзакции.
     *
     * @param Currency $currencyIn Валюта получения
     * @param array    $params     Параметры транзакции ['currency', 'tx', 'address', 'amount']
     *
     * @throws Exception Если AML-проверка выявила высокий риск
     */
    protected function validatedInTransaction(Currency $currencyIn, array $params): void
    {
        $existingAmlCheck = AMLResponseData::where([
            ['id_task', $this->transaction->id],
            ['method', 'tx']
        ])->first();

        if ($existingAmlCheck) {
            if (Arr::get($existingAmlCheck->ext_params, 'is_check_tx') === 1) {
                throw new Exception(__('Проверка приостановлена, у транзакции плохой AML результат.'));
            }
            return;
        }

        $in_price = (float)trim($this->transaction->give_price, '.');

        if ($currencyIn->aml_tx_from_amount >= $in_price) {
            return;
        }

        /** @var AMLResponseInterface $amlChecker */
        $amlChecker = AMLFacade::driver(
            $currencyIn->aml_service->alias,
            $currencyIn->aml_service
        )->useCache()->checkTransaction($params);

        if ($amlChecker->isPending()) {
            throw new Exception(__('Транзакция проверяется системой AML, пожалуйста, подождите...'));
        }

        if (!$amlChecker->isSuccessful()) {
            Log::warning('AML Transaction check unsuccessful', ['params' => $params]);
            throw new Exception(__('Ошибка AML-сервиса при проверке транзакции.'));
        }

        $amlResponse = $this->buildAmlResponse($amlChecker, 'tx');

        AMLResponseData::create([
            'id_task' => $this->transaction->id,
            'id_aml_service' => $currencyIn->aml_service->id,
            'alias' => $currencyIn->aml_service->alias,
            'ext_params' => $amlResponse,
            'method' => 'tx'
        ]);

        if ($currencyIn->error_for_aml_check_tx && $amlChecker->isRiskExceeded()) {
            throw new Exception(sprintf(
                __('Проверка приостановлена, у транзакции плохой AML рейтинг: %s'),
                $amlChecker->getRiskScore()
            ));
        }
    }

    /**
     * Подготавливает и возвращает данные AML-проверки для сохранения в базе данных.
     *
     * @param AMLResponseInterface $amlChecker Экземпляр ответа AML-сервиса
     * @param string               $type       Тип проверки ('address' или 'tx')
     *
     * @return array Подготовленный массив данных для базы данных
     */
    protected function buildAmlResponse(AMLResponseInterface $amlChecker, string $type): array
    {
        $amlResponse = [
            'risk_score' => (string)$amlChecker->getRiskScore(),
            'all_risks' => $amlChecker->getDataToDatabase(),
            'is_check_' . $type => (int)$amlChecker->isRiskExceeded(),
        ];

        $exceededRisks = $amlChecker->getExceededRiskSignals();

        if (!empty($exceededRisks)) {
            $maxRiskCategory = array_key_first($exceededRisks);
            $amlResponse['max_risk_for_category'] = $maxRiskCategory;
            $amlResponse['max_risk_for_category_value'] = (float)iex_number_format($exceededRisks[$maxRiskCategory]);
        }

        return $amlResponse;
    }
}
