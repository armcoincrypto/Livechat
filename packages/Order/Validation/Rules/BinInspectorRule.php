<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\CurrencyBinBankRule;
use App\Models\DirectionExchange;
use App\Settings\BinInspectorConfig;
use iEXPackages\BinInspector\BinInspectorFacade;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Enums\EffectType;
use iEXPackages\Order\Validation\Result\ValidationResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * BinInspectorRule — проверка реквизитов карт по BIN/банкам (allow/block) + снятие cardInfo snapshot.
 *
 * Перенос логики BinInspectorValidator в новую систему:
 * - не использует request()/auth()
 * - не использует callbacks
 * - возвращает ValidationResult (ошибки через addError)
 * - передаёт cardInfo через effect EffectType::CardInfoSnapshot
 *
 * Требования к контексту:
 * - resources содержит DirectionExchange
 * - data содержит income_account / outcome_account
 */
final class BinInspectorRule implements ValidationRuleInterface
{
    /** @var array<int, array<string, array{allowed: string[], blocked: string[]}>> */
    private array $banksRulesCache = [];

    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        // Номера “карт” (реквизиты) — нормализуем как в старом коде
        $cardInShot  = $this->compactAccount($context->data->getString('income_account'));
        $cardOutShot = $this->compactAccount($context->data->getString('outcome_account'));

        /** @var BinInspectorConfig $cfg */
        $cfg = app(BinInspectorConfig::class);

        $allowedCurrencies = $cfg->idsCurrenciesArray();

        if (empty($allowedCurrencies)) {
            return $result;
        }

        $driver = BinInspectorFacade::driver($cfg->driver() ?? '')
            ->setApiKey($cfg->apiKey())
            ->setIsSaveData((int)$cfg->shouldSaveData());

        $currencyIn = $direction->currency1;
        $currencyOut = $direction->currency2;

        $cardInfoIn = $this->processCardSide(
            side: 'in',
            currency: $currencyIn,
            cardNumber: $cardInShot,
            allowedCurrencies: $allowedCurrencies,
            driver: $driver,
            result: $result,
            field: 'income_account'
        );

        $cardInfoOut = $this->processCardSide(
            side: 'out',
            currency: $currencyOut,
            cardNumber: $cardOutShot,
            allowedCurrencies: $allowedCurrencies,
            driver: $driver,
            result: $result,
            field: 'outcome_account'
        );

        // Снимок cardInfo (как раньше callback cardInfo)
        if (!empty($cardInfoIn) || !empty($cardInfoOut)) {
            $result->addEffect(EffectType::CardInfoSnapshot, [
                'in' => $cardInfoIn,
                'out' => $cardInfoOut,
            ]);
        }

        return $result;
    }

    /**
     * Применить allow/block правила по банкам к результату.
     *
     * @param string[] $allowedPatterns
     * @param string[] $blockedPatterns
     */
    private function applyBankRulesToResult(
        ValidationResult $result,
        string $bankName,
        array $allowedPatterns,
        array $blockedPatterns,
        string $field
    ): void {
        if ($bankName === '') {
            // если банк не определился — ничего не блокируем
            return;
        }

        // allow: если список задан — банк должен совпасть хотя бы с одним паттерном
        if (!empty($allowedPatterns) && !$this->bankMatchesAny($bankName, $allowedPatterns)) {
            $result->addError(
                $field,
                __('Номер счета не относится к банкам из разрешённого списка'),
                'bin_bank_not_allowed'
            );
        }

        // block: если совпал — ошибка
        if (!empty($blockedPatterns) && $this->bankMatchesAny($bankName, $blockedPatterns)) {
            $result->addError(
                $field,
                __('Номер счета относится к банкам из запрещённого списка'),
                'bin_bank_blocked'
            );
        }
    }

    /**
     * Унифицированная обработка BIN/банков для входящей/исходящей стороны.
     *
     * Правила:
     * - cardInfo снимаем всегда, если валюта входит в allowedCurrencies и номер проходит Luhn
     * - ограничения allow/block применяем только если списки реально настроены
     *
     * @param 'in'|'out' $side
     * @param object|null $currency
     * @param string|null $cardNumber
     * @param int[] $allowedCurrencies
     * @param mixed $driver
     */
    private function processCardSide(
        string $side,
        ?object $currency,
        ?string $cardNumber,
        array $allowedCurrencies,
        $driver,
        ValidationResult $result,
        string $field
    ): array {
        if (!$currency || $cardNumber === null) {
            return [];
        }

        if (!in_array((int)($currency->id ?? 0), $allowedCurrencies, true)) {
            return [];
        }

        if (!wallet_validator('luhn', $cardNumber)) {
            return [];
        }

        $rules = $this->getBanksRulesForCurrency((int)$currency->id, $side);
        $allowedBanks = $rules['allowed'] ?? [];
        $blockedBanks = $rules['blocked'] ?? [];

        try {
            // Всегда снимаем cardInfo (для последующей записи), даже если списки allow/block пустые
            $data = $driver->setCardNumber($cardNumber)->getData();
            $cardInfo = is_array($data) ? $data : [];

            // Ограничения применяем ТОЛЬКО если реально настроены списки
            if (!empty($allowedBanks) || !empty($blockedBanks)) {
                $bankName = $this->normalizeBankName((string)($cardInfo['bankName'] ?? ''));
                $this->applyBankRulesToResult(
                    result: $result,
                    bankName: $bankName,
                    allowedPatterns: $allowedBanks,
                    blockedPatterns: $blockedBanks,
                    field: $field
                );
            }

            return $cardInfo;
        } catch (\Throwable $e) {
            Log::debug('BinInspector ' . $side . ' error: ' . $e->getMessage(), [
                'currency_id' => (int)$currency->id,
            ]);
            return [];
        }
    }

    /**
     * Получить списки разрешённых и запрещённых банков для валюты и направления.
     *
     * @return array{allowed: string[], blocked: string[]}
     */
    private function getBanksRulesForCurrency(int $currencyId, string $direction): array
    {
        $direction = ($direction === 'out') ? 'out' : 'in';

        if (isset($this->banksRulesCache[$currencyId][$direction])) {
            return $this->banksRulesCache[$currencyId][$direction];
        }

        $allowed = [];
        $blocked = [];

        $rows = CurrencyBinBankRule::query()
            ->where('currency_id', $currencyId)
            ->where('direction', $direction)
            ->get(['mode', 'bank_name']);

        foreach ($rows as $row) {
            $name = $this->normalizeBankName((string)$row->bank_name);
            if ($name === '') continue;

            if ($row->mode === 'allow') $allowed[] = $name;
            if ($row->mode === 'block') $blocked[] = $name;
        }

        $allowed = array_values(array_unique($allowed));
        $blocked = array_values(array_unique($blocked));

        return $this->banksRulesCache[$currencyId][$direction] = [
            'allowed' => $allowed,
            'blocked' => $blocked,
        ];
    }

    /**
     * Нормализовать название банка: lower + squish.
     */
    private function normalizeBankName(string $name): string
    {
        return Str::of($name)->lower()->squish()->toString();
    }

    /**
     * Проверка: содержит ли имя банка одну из подстрок patterns.
     *
     * @param string[] $patterns
     */
    private function bankMatchesAny(string $bankName, array $patterns): bool
    {
        if ($bankName === '') return false;

        foreach ($patterns as $pattern) {
            $pattern = $this->normalizeBankName((string)$pattern);
            if ($pattern === '') continue;

            if (Str::contains($bankName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Компактная очистка реквизита:
     * - security_xss
     * - trim
     * - убираем пробелы
     */
    private function compactAccount(?string $raw): ?string
    {
        if ($raw === null) return null;
        $s = trim(security_xss($raw));
        if ($s === '') return null;
        $s = preg_replace('/\s+/', '', $s) ?? $s;
        $s = trim($s);
        return $s !== '' ? $s : null;
    }
}
