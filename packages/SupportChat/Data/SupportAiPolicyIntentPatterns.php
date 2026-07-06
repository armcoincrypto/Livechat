<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Data;

/**
 * Multilingual policy-intent patterns shared by draft, knowledge, template, and UX layers.
 */
final class SupportAiPolicyIntentPatterns
{
    /** @var array<string, list<string>>|null */
    private static ?array $knowledgePatterns = null;

    /** @return array<string, list<string>> */
    public static function knowledgeIntentPatterns(): array
    {
        if (self::$knowledgePatterns !== null) {
            return self::$knowledgePatterns;
        }

        self::$knowledgePatterns = [
            'single_payment' => [
                '1 платеж', 'один платеж', 'одним платеж', 'одним платежом', 'в один платеж',
                'одним перевод', 'одним переводом', 'один перевод', 'в один перевод',
                'single payment', 'one payment', 'one transfer', 'single transfer',
                'one transaction', 'single transaction', 'will it be one payment',
                'pay in one transfer', 'can you pay in one transfer',
                'send rub in one transaction', 'rub in one transaction',
                'one payment only', 'in one payment',
                'гарантирован.*перевод', 'гарантирован.*платеж', '1 платежом гарант',
                'mi poxancum', 'mek poxancum', 'մեկ փոխանցում', 'մեկ վճարում',
            ],
            'payout_split' => [
                '1-3 платеж', '1–3 платеж', '1-3 платежа', '1–3 платежа',
                'несколько платеж', 'несколько платежей', 'разделить платеж', 'разбить платеж',
                'частями', 'дробите', 'сколько.*платеж',
                'split payment', 'multiple payments', 'several payments', 'partial payment',
                'split transfer', 'multiple transfers', 'pay in parts', 'split payout',
                'мелких',
            ],
            'card_rub' => [
                'card rub', 'cartrub', 'card.*rub', 'bank card rub', 'ruble card', 'rub to card',
                'rub payout', 'send rub', 'rub in one transaction',
                'sber rub', 'sberbank rub', 'sber.*rub',
                'сбп', 'sbp', 'карт.*руб', 'кард.*руб', 'карта руб', 'кард руб',
                'рубли на карту', 'выплата руб', 'перевод руб', 'сбер руб', 'сбербанк руб',
                'тиньк', 'сбер', 'втб', 'альф', 'trc20.*руб', 'usdt.*руб',
            ],
            'rate_question' => [
                'курс', 'другой курс', 'нормальн.*курс', 'лучше курс', 'по нормальному курсу',
                'rate', 'exchange rate', 'different rate', 'normal rate', 'better rate',
                'can you do better rate', 'комисси', 'fee',
            ],
            'large_amount' => [
                'крупн', 'большая сумма', 'крупная сумма', 'large amount', 'big amount',
                'how much can i exchange', 'limit', 'лимит', '3000 usdt', '600 usdt', '300к', '300 000', '145к',
            ],
            'otc' => [
                'otc', 'крупн.*сумм', 'индивидуальн', '3000 usdt', '300 usd',
                'какой объем', 'какой объём', 'объем', 'объём', 'volume',
            ],
            'verified_accounts' => [
                'verified account', 'verified accounts', 'your verified accounts',
                'checked accounts', 'trusted accounts', 'from your accounts',
                'where do you send from', 'source of transfer',
                'со своих счет', 'с ваших счет', 'своих\s+.*счет', 'проверенные счета', 'проверен.*счет',
                'откуда переводите', 'источник перевода',
            ],
            'verification' => [
                'вериф', 'verification', 'verify card', 'kyc', 'документ',
            ],
            'aml' => [
                'aml', 'комплаенс', '115', 'блокир.*карт', 'frozen', 'замороз',
            ],
            'bank_transfer' => [
                'банк', 'bank', 'реквизит', 'requisit', 'проверен.*счет',
                'от кого', 'отправител', 'sender', 'swift', 'sepa',
            ],
            'confirmation_question' => [
                'подтвержден', 'confirmation', 'confirms', 'xmr.*сбп',
            ],
            'status_question' => [
                'статус', 'status', 'в обработк', 'processing', 'order/',
            ],
            'wrong_route' => [
                'другое направление', 'wrong direction', 'не то направление',
            ],
        ];

        return self::$knowledgePatterns;
    }

    /** @return list<string> */
    public static function policyIntents(): array
    {
        return [
            'single_payment',
            'payout_split',
            'card_rub',
            'rate_question',
            'large_amount',
            'otc',
            'verified_accounts',
            'verification',
            'aml',
            'bank_transfer',
            'confirmation_question',
            'wrong_route',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    public static function matchKnowledgeIntents(string $text, array $context = []): array
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');
        if ($normalized === '') {
            return [];
        }

        $matched = [];
        foreach (self::knowledgeIntentPatterns() as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (@preg_match('/'.$pattern.'/iu', $normalized) === 1) {
                    $matched[] = $intent;
                    break;
                }
            }
        }

        if (in_array('verified_accounts', $matched, true) && ! in_array('bank_transfer', $matched, true)) {
            $matched[] = 'bank_transfer';
        }

        if (isset($context['draft_intent']) && is_string($context['draft_intent'])) {
            $mapped = self::mapDraftIntentToKnowledge((string) $context['draft_intent']);
            if ($mapped !== null) {
                $matched[] = $mapped;
            }
        }

        return array_values(array_unique($matched));
    }

    public static function resolvePrimaryDraftIntent(string $text): ?string
    {
        $intents = self::matchKnowledgeIntents($text);
        if ($intents === []) {
            return null;
        }

        $policyHits = array_intersect($intents, self::policyIntents());
        if ($policyHits === []) {
            return null;
        }

        $priority = [
            'single_payment',
            'payout_split',
            'verified_accounts',
            'bank_transfer',
            'card_rub',
            'rate_question',
            'otc',
            'large_amount',
            'verification',
            'aml',
            'confirmation_question',
            'wrong_route',
        ];

        foreach ($priority as $intent) {
            if (in_array($intent, $policyHits, true)) {
                return self::knowledgeToDraftIntent($intent);
            }
        }

        return null;
    }

    public static function knowledgeToDraftIntent(string $knowledgeIntent): string
    {
        return match ($knowledgeIntent) {
            'bank_transfer' => 'verified_accounts',
            default => $knowledgeIntent,
        };
    }

    public static function mapDraftIntentToKnowledge(string $draftIntent): ?string
    {
        return match ($draftIntent) {
            'card_or_bank_transfer' => 'card_rub',
            'rate_question' => 'rate_question',
            'large_otc_exchange' => 'otc',
            'eta_request' => 'status_question',
            'funds_safety_question' => 'single_payment',
            'single_payment', 'payout_split', 'card_rub', 'large_amount', 'otc' => $draftIntent,
            'verified_accounts' => 'bank_transfer',
            default => null,
        };
    }

    public static function hasRubCardBankContext(string $text): bool
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');

        return @preg_match(
            '/\b(rub|ruble|руб|рубл|card|bank|сбер|sber|sbp|сбп|cartrub|card rub|transfer|перевод|выплат|payout)\b/iu',
            $normalized,
        ) === 1;
    }

    /**
     * @param  list<string>  $knowledgeIntents
     */
    public static function classifyPolicyStrength(string $text, array $knowledgeIntents): string
    {
        $policyHits = array_intersect($knowledgeIntents, self::policyIntents());
        if ($policyHits === []) {
            return 'low';
        }

        if (self::hasRubCardBankContext($text)) {
            return 'high';
        }

        $normalized = mb_strtolower(trim($text), 'UTF-8');
        $wordCount = str_word_count($normalized);
        if ($wordCount <= 4) {
            return 'medium';
        }

        return 'medium';
    }

    public static function isVagueQuestion(string $text): bool
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');

        return @preg_match(
            '/^(?:what happens|what is going on|что происходит|help\??|помогите\??)$/iu',
            trim($normalized),
        ) === 1;
    }
}
