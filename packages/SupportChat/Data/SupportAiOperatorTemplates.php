<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Data;

/**
 * Operator reply templates mined in AI-SUPPORT-OPERATOR-REPLY-MINING-1.
 *
 * @return list<array<string, mixed>>
 */
final class SupportAiOperatorTemplates
{
    public static function all(): array
    {
        return [
            [
                'template_code' => 'TPL_CARDRUB_001',
                'intent' => 'card_rub',
                'category' => 'card_rub',
                'title' => 'CARDRUB canonical block',
                'template_text' => <<<'TXT'
Здравствуйте. Да, можем выполнить выплату как одним платежом, так и разделить её на 1–3 платежа при необходимости.
Для обмена рекомендуем использовать направление Card RUB:
https://exswaping.com/ru/exchange/USDTTRC20/CARDRUB?city=YRV
Если у вас есть дополнительные пожелания по способу выплаты, сообщите заранее.
TXT,
                'template_type' => 'card_rub',
                'language' => 'ru',
                'frequency' => 6,
                'confidence' => 'high',
                'active' => true,
                'requires_validation' => false,
                'source_conversation_ids' => [22, 243, 259, 273, 283, 290, 296, 305, 308, 309],
                'metadata' => [
                    'audit_ref' => 'T1',
                    'question_patterns' => [
                        'card rub', 'cartrub', 'сбп', 'sbp', 'карт.*руб', 'trc20.*руб',
                    ],
                ],
            ],
            [
                'template_code' => 'TPL_SPLIT_001',
                'intent' => 'single_payment',
                'category' => 'payout_split',
                'title' => '1–3 payment explanation',
                'template_text' => 'Выплата может зависеть от суммы, направления и банка. Если для вас важен один платёж, сообщите об этом заранее — оператор проверит возможность такого варианта.',
                'template_type' => 'policy',
                'language' => 'ru',
                'frequency' => 7,
                'confidence' => 'high',
                'active' => true,
                'requires_validation' => false,
                'source_conversation_ids' => [295, 309],
                'metadata' => [
                    'audit_ref' => 'T2',
                    'question_patterns' => [
                        '1 платеж', 'одним платеж', 'гарантирован.*перевод', 'гарантирован.*платеж',
                        '1-3 платеж', '1–3 платеж', 'несколько платеж', 'частями',
                        'one transaction', 'single transaction', 'one payment', 'single payment',
                        'one transfer', 'single transfer', 'send rub in one transaction',
                        'rub in one transaction', 'will it be one payment',
                    ],
                ],
            ],
            [
                'template_code' => 'TPL_VOLUME_001',
                'intent' => 'otc',
                'category' => 'volume_gate',
                'title' => 'How much volume?',
                'template_text' => 'Какой объём планируете обменять? Это поможет подсказать подходящее направление и условия выплаты.',
                'template_type' => 'volume_gate',
                'language' => 'ru',
                'frequency' => 3,
                'confidence' => 'high',
                'active' => true,
                'requires_validation' => false,
                'source_conversation_ids' => [308, 309],
                'metadata' => [
                    'audit_ref' => 'T3',
                    'question_patterns' => [
                        'крупн', 'large amount', 'big amount', 'otc', 'limit', 'volume',
                        '3000 usdt', '600 usdt', '300к', 'объём', 'объем', 'какой объем', 'какой объём',
                    ],
                ],
            ],
            [
                'template_code' => 'TPL_RATE_001',
                'intent' => 'rate_question',
                'category' => 'rate',
                'title' => 'Rate explanation',
                'template_text' => 'Уточните сумму и направление обмена — оператор проверит доступные условия и подскажет наиболее подходящий вариант.',
                'template_type' => 'rate',
                'language' => 'ru',
                'frequency' => 8,
                'confidence' => 'high',
                'active' => true,
                'requires_validation' => false,
                'source_conversation_ids' => [281, 308],
                'metadata' => [
                    'audit_ref' => 'T4',
                    'question_patterns' => [
                        'курс', 'rate', 'exchange rate', 'комисси', 'fee', 'курс другой', 'нормальн.*курс',
                        'normal rate', 'different rate', 'better rate',
                    ],
                ],
            ],
            [
                'template_code' => 'TPL_BANK_001',
                'intent' => 'bank_transfer',
                'category' => 'banking',
                'title' => 'Verified accounts explanation',
                'template_text' => 'Выплаты выполняются через доступные проверенные реквизиты, однако конкретный способ зависит от направления и условий обработки.',
                'template_type' => 'banking',
                'language' => 'ru',
                'frequency' => 2,
                'confidence' => 'medium',
                'active' => true,
                'requires_validation' => false,
                'source_conversation_ids' => [309],
                'metadata' => [
                    'audit_ref' => 'T5',
                    'question_patterns' => [
                        'проверен.*счет', 'verified account', 'verified accounts', 'your verified accounts',
                        'from your accounts', 'checked accounts', 'trusted accounts',
                        'where do you send from', 'source of transfer',
                        'от кого', 'отправител', 'sender', 'своих счет', 'с ваших счет', 'откуда переводите',
                    ],
                ],
            ],
            [
                'template_code' => 'TPL_REFUND_001',
                'intent' => 'refund',
                'category' => 'refund',
                'title' => 'Refund handling',
                'template_text' => 'Возврат рассматривается оператором вручную — уточним доступные варианты по вашей заявке.',
                'template_type' => 'refund',
                'language' => 'ru',
                'frequency' => 10,
                'confidence' => 'high',
                'active' => true,
                'requires_validation' => false,
                'source_conversation_ids' => [],
                'metadata' => [
                    'audit_ref' => 'T6',
                    'question_patterns' => [
                        'возврат', 'refund', 'отмен', 'cancel', 'вернуть',
                    ],
                ],
            ],
            [
                'template_code' => 'TPL_STATUS_001',
                'intent' => 'status_question',
                'category' => 'status',
                'title' => 'Processing status',
                'template_text' => 'Ваша заявка находится в обработке — ожидайте поступлений. Если нужны детали, оператор проверит текущий статус.',
                'template_type' => 'status',
                'language' => 'ru',
                'frequency' => 19,
                'confidence' => 'high',
                'active' => true,
                'requires_validation' => false,
                'source_conversation_ids' => [],
                'metadata' => [
                    'audit_ref' => 'T7',
                    'question_patterns' => [
                        'в обработк', 'processing', 'ожида', 'когда поступ',
                    ],
                ],
            ],
            [
                'template_code' => 'TPL_STATUS_002',
                'intent' => 'status_question',
                'category' => 'status',
                'title' => 'Checking status',
                'template_text' => 'Проверим детали заявки и ответим по фактическому статусу. Если есть номер заявки или ссылка — пришлите, это ускорит проверку.',
                'template_type' => 'status',
                'language' => 'ru',
                'frequency' => 7,
                'confidence' => 'high',
                'active' => true,
                'requires_validation' => false,
                'source_conversation_ids' => [],
                'metadata' => [
                    'audit_ref' => 'T8',
                    'question_patterns' => [
                        'статус', 'status', 'order/', 'заявк', 'провер',
                    ],
                ],
            ],
            [
                'template_code' => 'TPL_VERIFICATION_001',
                'intent' => 'verification',
                'category' => 'verification',
                'title' => 'Verification explanation',
                'template_text' => 'На некоторых направлениях требуется верификация карты — загрузка через раздел /account/cards. Оператор уточнит, нужна ли верификация для вашего направления.',
                'template_type' => 'verification',
                'language' => 'ru',
                'frequency' => 5,
                'confidence' => 'high',
                'active' => true,
                'requires_validation' => false,
                'source_conversation_ids' => [],
                'metadata' => [
                    'audit_ref' => 'T9',
                    'question_patterns' => [
                        'вериф', 'verification', 'verify card', 'kyc', 'документ',
                    ],
                ],
            ],
        ];
    }
}
