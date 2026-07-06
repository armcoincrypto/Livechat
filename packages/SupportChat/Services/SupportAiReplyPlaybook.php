<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

/**
 * Built-in anonymized style examples for AI drafting (not raw customer history).
 */
final class SupportAiReplyPlaybook
{
    /**
     * Max examples injected into OpenAI prompt (style guidance only).
     */
    private const PROMPT_EXAMPLE_LIMIT = 5;

    /**
     * @return list<array{language: string, intent: string, example: string}>
     */
    public static function builtInExamples(): array
    {
        return [
            [
                'language' => 'ru',
                'intent' => 'exchange_direction',
                'example' => 'Направление [FROM] → [TO] доступно. По проверке кошелька оператор подскажет, можно ли принять адрес перед обменом.',
            ],
            [
                'language' => 'ru',
                'intent' => 'wallet_check',
                'example' => 'Оператор проверит кошелёк и подскажет, можно ли принять адрес перед обменом.',
            ],
            [
                'language' => 'ru',
                'intent' => 'order_status',
                'example' => 'Проверим заявку №[ORDER_ID] по системе и вернёмся с точным статусом здесь.',
            ],
            [
                'language' => 'ru',
                'intent' => 'withdrawal',
                'example' => 'Сейчас проверим вывод по заявке №[ORDER_ID] и уточним, на каком этапе обработка.',
            ],
            [
                'language' => 'ru',
                'intent' => 'payment_delay',
                'example' => 'Платёж по заявке №[ORDER_ID] проверим вручную и уточним, поступил ли он на нашу сторону.',
            ],
            [
                'language' => 'ru',
                'intent' => 'missing_tx',
                'example' => 'Пришлите, пожалуйста, TX hash платежа — так оператор быстрее проверит поступление.',
            ],
            [
                'language' => 'ru',
                'intent' => 'payment_not_received',
                'example' => 'Проверим поступление платежа по заявке и уточним, видим ли его на нашей стороне.',
            ],
            [
                'language' => 'ru',
                'intent' => 'complaint',
                'example' => 'Понимаем ситуацию, сейчас проверим детали заявки и ответим по существу.',
            ],
            [
                'language' => 'ru',
                'intent' => 'large_otc',
                'example' => 'Уточните направление обмена и способ получения — оператор проверит доступность суммы и актуальные условия.',
            ],
            [
                'language' => 'ru',
                'intent' => 'follow_up',
                'example' => 'Понимаем, что ожидание затянулось. Проверим заявку №[ORDER_ID] повторно и ответим здесь по фактическому статусу.',
            ],
            [
                'language' => 'ru',
                'intent' => 'follow_up',
                'example' => 'Проверим статус повторно и вернёмся с конкретным ответом по заявке №[ORDER_ID].',
            ],
            [
                'language' => 'ru',
                'intent' => 'operator_data_received',
                'example' => 'Спасибо, проверим заявку №[ORDER_ID] по системе и ответим здесь с актуальным статусом.',
            ],
            [
                'language' => 'ru',
                'intent' => 'operator_data_received',
                'example' => 'Спасибо, проверим TX hash по платежу и уточним, видим ли поступление на нашей стороне.',
            ],
            [
                'language' => 'ru',
                'intent' => 'operator_escalated',
                'example' => 'Вопрос уже передан на дополнительную проверку, уточним результат и ответим здесь по фактическому статусу.',
            ],
            [
                'language' => 'ru',
                'intent' => 'eta_request',
                'example' => 'Точное время без проверки назвать нельзя — оператор уточнит статус обработки и ответит здесь.',
            ],
            [
                'language' => 'ru',
                'intent' => 'funds_safety',
                'example' => 'Проверим детали заявки по системе и ответим здесь по фактическому статусу — без неподтверждённых гарантий.',
            ],
            [
                'language' => 'ru',
                'intent' => 'wrong_network',
                'example' => 'Проверим детали перевода и сеть отправки, после этого оператор уточнит возможные варианты.',
            ],
            [
                'language' => 'ru',
                'intent' => 'payment_proof',
                'example' => 'Спасибо, оператор проверит данные на скриншоте; если потребуется TX hash, мы уточним это здесь.',
            ],
            [
                'language' => 'en',
                'intent' => 'follow_up',
                'example' => 'We understand the wait — we will re-check order #[ORDER_ID] and reply here with the actual status.',
            ],
            [
                'language' => 'en',
                'intent' => 'order_status',
                'example' => 'We will check order #[ORDER_ID] in the system and reply here with the latest status.',
            ],
            [
                'language' => 'en',
                'intent' => 'payment_proof',
                'example' => 'Please share the transaction hash so the operator can verify whether the payment reached us.',
            ],
            [
                'language' => 'uk',
                'intent' => 'order_status',
                'example' => 'Перевіримо заявку №[ORDER_ID] в системі та повернемося з актуальним статусом тут.',
            ],
            [
                'language' => 'ka',
                'intent' => 'order_status',
                'example' => 'შევამოწმებთ განაცხად №[ORDER_ID] სისტემაში და აქ დაგიბრუნებთ ზუსტ სტატუსს.',
            ],
        ];
    }

    public static function promptBlock(string $language, ?string $intent = null): string
    {
        $examples = array_values(array_filter(
            self::builtInExamples(),
            static fn (array $row): bool => $row['language'] === $language
                && ($intent === null || $row['intent'] === $intent || self::intentMatches($intent, $row['intent']))
        ));

        if ($examples === [] && $intent !== null) {
            $examples = array_values(array_filter(
                self::builtInExamples(),
                static fn (array $row): bool => $row['language'] === $language
            ));
        }

        if ($examples === []) {
            $examples = array_values(array_filter(
                self::builtInExamples(),
                static fn (array $row): bool => $row['language'] === 'en'
            ));
        }

        $examples = array_slice($examples, 0, self::PROMPT_EXAMPLE_LIMIT);
        if ($examples === []) {
            return '';
        }

        $lines = ['NATURAL STYLE EXAMPLES (tone only — NOT verified facts, adapt to detected intent):'];
        foreach ($examples as $row) {
            $lines[] = sprintf('- [%s] %s', $row['intent'], $row['example']);
        }

        return implode("\n", $lines);
    }

    private static function intentMatches(string $detected, string $exampleIntent): bool
    {
        $map = [
            'withdrawal_delay' => 'withdrawal',
            'payment_delay' => 'payment_delay',
            'payment_sent_waiting' => 'payment_delay',
            'payment_not_received' => 'payment_delay',
            'exchange_direction' => 'exchange_direction',
            'wallet_check' => 'wallet_check',
            'general_support' => 'general_support',
            'general_question' => 'general_support',
            'missing_tx_hash' => 'missing_tx',
            'complaint_or_angry_customer' => 'complaint',
            'repeated_follow_up' => 'follow_up',
            'visitor_provided_order_after_request' => 'operator_data_received',
            'visitor_provided_tx_after_request' => 'operator_data_received',
            'operator_escalated_follow_up' => 'operator_escalated',
            'eta_request' => 'eta_request',
            'funds_safety_question' => 'funds_safety',
            'payment_proof_only' => 'payment_proof',
            'wrong_network' => 'wrong_network',
            'wrong_amount' => 'wrong_amount',
        ];

        return ($map[$detected] ?? $detected) === $exampleIntent
            || $detected === $exampleIntent;
    }

    public static function greetingRules(): string
    {
        return <<<'RULES'
GREETING RULE (mandatory):
- Do NOT greet in every option. Do NOT start all 4 options with Здравствуйте / Hello / Hi / Добрый день.
- Use a greeting ONLY when context says "Greeting recommended: YES".
- When greeting is NOT recommended, start directly with the action: "Проверим заявку №…", "Уточним поступление…", "Проверим TX hash…"
- At most ONE option may include a brief greeting when greeting is optional; prefer zero greetings in ongoing chats.
- Match the visitor problem first — greeting is secondary.
RULES;
    }

    public static function contextAwareRules(): string
    {
        return <<<'RULES'
CONTEXT-AWARE REPLY (mandatory):
- Read the visitor's latest message, Conversation memory, and recent messages BEFORE drafting.
- Each option must directly address the visitor's actual problem — not a generic support template.
- Do not ask for order ID, TX hash, amount, network, wallet, direction, or payment proof if already in conversation (see "Do NOT ask again for").
- Ask for missing data ONLY when intent requires it and data is not in the thread.
- Do not repeat the same sentence structure across all 4 options.
- Do not repeat verbatim what the operator already said — suggest the NEXT useful reply for this stage.
RULES;
    }

    public static function conversationMemoryRules(): string
    {
        return <<<'RULES'
CONVERSATION MEMORY (mandatory):
- Read the "Conversation memory" block and "--- recent messages ---" before drafting.
- Understand what the visitor already asked and what the operator already answered.
- If operator already promised to check, suggest re-check / concrete update wording — do NOT ask basic questions again.
- If visitor is following up, acknowledge the wait and re-check — do NOT restart intake from scratch.
- Use known data from the thread (order ID, TX hash, amount, network) — never ask for it again.
RULES;
    }

    public static function operatorActionAwarenessRules(): string
    {
        return <<<'RULES'
OPERATOR ACTION AWARENESS (mandatory):
- Read "Operator action awareness" block before drafting.
- Detect what the operator/admin already did: requested data, promised check, escalated, gave status, explained delay.
- If visitor provided data AFTER operator request — thank briefly and verify using that data; do NOT ask again.
- If operator already promised check and visitor follows up — acknowledge delay, re-check substantively; do NOT repeat empty "we will check".
- If operator already escalated — reference escalation in progress; do NOT say "we will pass to operator" again.
- If operator already gave status and visitor asks why — clarify status reason without fake ETA or completion promises.
- Suggest the NEXT professional reply — not a copy of the last operator message.
RULES;
    }

    public static function edgeCaseRules(): string
    {
        return <<<'RULES'
EDGE CASES (mandatory — real difficult support threads):
- Angry customer: acknowledge concern calmly; check details; NO "не переживайте", NO "ваши средства в безопасности".
- Exact ETA request: say exact timing cannot be stated without verification; operator will check status and reply here.
- Funds safe / guaranteed: do NOT guarantee safety or outcome; offer factual status check only.
- Wrong network: verify transfer details and network sent; do NOT promise automatic recovery.
- Wrong amount / partial payment: verify received amount vs order; mention possible top-up or recalculation — do NOT say order will complete.
- Screenshot/proof only: thank visitor; operator reviews screenshot; ask for TX hash ONLY if still missing and needed.
- TX hash only: verify payment receipt using hash — do NOT confirm received.
- Short unclear message (ну?, что там?): if order/tx in thread — re-check; otherwise ask ONE clarifying question (order ID or TX hash).
- Mixed language: reply in visitor's primary language from context; preserve exact IDs.
RULES;
    }

    public static function answerSelectionRules(): string
    {
        return <<<'RULES'
ANSWER SELECTION (mandatory — match conversation stage):
- first_message_no_order: ask for order ID so operator can check faster.
- first_message_with_order: check order in system, reply here with status — use exact order ID.
- follow_up_with_order: re-check order, acknowledge follow-up — do NOT ask for order ID again.
- follow_up_with_tx: verify TX hash / payment receipt — do NOT ask for hash again.
- operator_promised_check_with_order: re-check status and reply substantively — do NOT repeat empty promise.
- visitor_sent_tx: check TX hash payment receipt on our side.
- angry_complaint: calm acknowledgment + re-check details — no argument, no fake ETA.
- otc_inquiry: ask direction/payout if missing; do not quote rates.
- issue_details_provided: verify the specific issue (amount/network) — use data already in thread.
- visitor_provided_order_after_request: thank visitor; check order in system — do NOT ask for order ID.
- visitor_provided_tx_after_request: thank visitor; verify TX hash payment — do NOT ask for hash.
- visitor_provided_proof_after_request: thank visitor; verify payment proof — do NOT ask for proof again.
- operator_escalated_follow_up: reference escalation already in progress — do NOT re-escalate.
- operator_gave_status_follow_up: clarify status/reason — do NOT repeat same status without substance.

All 4 options must fit the SAME stage but vary wording/structure — not 4 copies of "please send order ID" when ID is known.
RULES;
    }

    public static function answerSelectionGuidance(string $stage): string
    {
        return match ($stage) {
            'first_message_no_order' => 'Ask for order number so operator can check faster.',
            'first_message_with_order' => 'Check order in system and reply here with actual status.',
            'follow_up_with_order' => 'Acknowledge follow-up; re-check order and reply with factual status — do not ask for order ID.',
            'follow_up_with_tx' => 'Re-check payment by TX hash; do not ask for hash again.',
            'follow_up_general' => 'Acknowledge follow-up; state what operator will re-verify.',
            'operator_promised_check' => 'Re-check and reply substantively — avoid repeating empty promise.',
            'operator_promised_check_with_order' => 'Re-check order status again and reply with concrete update.',
            'visitor_sent_tx' => 'Verify TX hash payment receipt on our side.',
            'otc_inquiry' => 'Clarify direction/amount/payout; operator checks availability.',
            'angry_complaint' => 'Acknowledge calmly; re-check details and reply substantively.',
            'issue_details_provided' => 'Verify the specific issue using data already provided.',
            'visitor_provided_order_after_request' => 'Thank visitor; check order in system with provided order ID.',
            'visitor_provided_tx_after_request' => 'Thank visitor; verify payment using provided TX hash.',
            'visitor_provided_proof_after_request' => 'Thank visitor; verify payment using provided proof.',
            'visitor_provided_wallet_after_request' => 'Thank visitor; verify using provided wallet/network details.',
            'operator_escalated_follow_up' => 'Reference escalation in progress; reply with factual update when available.',
            'operator_gave_status_follow_up' => 'Clarify reason for current status without unverified promises.',
            'sensitive_expectation_question' => 'No fake ETA or safety guarantees — operator checks and replies with facts.',
            'visitor_sent_proof_only' => 'Thank visitor; review screenshot/proof; ask for TX hash only if still needed.',
            default => 'Address latest visitor message using conversation context; ask only truly missing info.',
        };
    }

    /**
     * @param  array{
     *     provided_order_id: bool,
     *     provided_tx_hash: bool,
     *     provided_payment_proof: bool,
     *     provided_wallet_or_network: bool,
     * }  $providedAfterRequest
     * @param  array{
     *     order_ids: list<string>,
     *     tx_hashes: list<string>,
     * }  $knownData
     */
    public static function operatorActionGuidance(
        string $action,
        array $providedAfterRequest,
        array $knownData,
        bool $isFollowUp,
        string $stage,
    ): string {
        if ($providedAfterRequest['provided_order_id']) {
            return 'Thank visitor; check provided order ID in system and reply here with actual status.';
        }
        if ($providedAfterRequest['provided_tx_hash']) {
            return 'Thank visitor; verify payment using provided TX hash on our side.';
        }
        if ($providedAfterRequest['provided_payment_proof']) {
            return 'Thank visitor; verify payment using provided proof.';
        }
        if ($providedAfterRequest['provided_wallet_or_network']) {
            return 'Thank visitor; verify using provided wallet/network details.';
        }

        return match ($action) {
            'operator_requested_order_id' => 'Wait for or acknowledge order ID if visitor just sent it; otherwise ask once for order number.',
            'operator_requested_tx_hash' => 'Wait for TX hash or verify if visitor just sent it.',
            'operator_requested_payment_proof' => 'Wait for proof or verify if visitor just sent it.',
            'operator_promised_check', 'operator_said_manual_verification' => $isFollowUp
                ? 'Acknowledge delay; re-check status substantively — avoid repeating empty promise.'
                : 'Operator promised check — next reply should add substance or concrete re-check.',
            'operator_escalated_to_admin' => $isFollowUp
                ? 'Reference escalation already in progress; reply with factual update.'
                : 'Issue escalated — operator follows up with result.',
            'operator_gave_status' => $isFollowUp
                ? 'Clarify reason for current status without fake ETA.'
                : 'Build on status already given — next step or re-check if needed.',
            'operator_explained_delay' => 'Acknowledge wait; re-check or provide substantive update.',
            default => self::answerSelectionGuidance($stage),
        };
    }

    public static function intentGuidance(string $intent): string
    {
        return match ($intent) {
            'order_status' => 'Visitor asks about order status. Reply: check order in system, reply here with actual status. Use exact order ID if provided.',
            'exchange_direction' => 'Visitor asks about exchange direction/pair availability. LEAD with verified direction availability from context. If wallet check is also mentioned, say operator will advise on wallet acceptance — do NOT open by asking for wallet address. Do NOT claim unsupported unless availability_status says unsupported/paused/manual_review_required.',
            'wallet_check' => 'Visitor asks to verify/check wallet before exchange. Reply: operator will review whether the wallet can be accepted — pair with direction availability if known.',
            'payment_delay' => 'Visitor reports payment or withdrawal delay. Reply: check order/payment/withdrawal stage — do NOT confirm received, sent, or completed.',
            'payment_sent_waiting' => 'Visitor says they paid and waits. Reply: verify payment receipt on our side — do NOT confirm received.',
            'payment_not_received' => 'Visitor says payment/credit not received. Reply: check payment/credit status — do NOT claim sent or completed.',
            'withdrawal_delay' => 'Visitor asks about withdrawal delay. Reply: check withdrawal stage — do NOT say funds sent.',
            'wrong_amount' => 'Wrong amount issue. Reply: verify sent amount vs order — ask for tx/amount if missing.',
            'wrong_network' => 'Wrong network/details. Reply: verify network and details sent — operator checks blockchain/admin.',
            'missing_order_id' => 'No order ID in message. Reply: ask for order number so operator can check faster.',
            'missing_tx_hash' => 'Payment issue but no TX hash. Reply: ask for TX hash or screenshot to verify payment.',
            'rate_question' => 'Rate/limit question. Reply: operator confirms actual rate/limit after review — do NOT quote rate.',
            'large_otc_exchange' => 'Large/OTC exchange. Reply: ask direction, amount, payout method — operator checks availability.',
            'card_or_bank_transfer' => 'Card/bank transfer question. Reply: clarify method and order details — operator verifies.',
            'refund_request' => 'Refund request. Reply: operator will review refund eligibility — do NOT promise refund.',
            'complaint_or_angry_customer' => 'Angry/impatient visitor. Reply: acknowledge calmly, check details, reply substantively — no argument, no fake ETA.',
            'repeated_follow_up' => 'Visitor follows up after prior messages. Reply: acknowledge wait, re-check using known order/tx data — do NOT ask for data already in thread.',
            'eta_request' => 'Visitor asks exact timing/ETA. Reply: cannot state exact time without verification; operator checks status and replies here.',
            'funds_safety_question' => 'Visitor asks if funds are safe/guaranteed. Reply: check order/payment facts — do NOT guarantee safety or completion.',
            'payment_proof_only' => 'Visitor sent screenshot/proof only. Reply: thank visitor; operator reviews proof; ask for TX hash only if still needed.',
            'general_support', 'general_question' => 'General support question. Reply: address the specific question directly; ask clarifying info only if needed.',
            default => 'Intent unclear. Reply: ask one clarifying question OR state what operator will verify.',
        };
    }

    public static function operatorToneRules(): string
    {
        return <<<'RULES'
OPERATOR TONE (mandatory):
- Sound like a real BestChange / top crypto exchange support operator: human, confident, useful, concise.
- Not robotic, not copy-paste generic, not over-explaining.
- Prefer one sentence; two short sentences only if needed.
- Vary wording across options — do NOT repeat the same opening or template.
- Do not ask for data the visitor already provided (order ID, tx hash, amount, network).
- Ask for missing data only when needed for that scenario.
- Mention exact order IDs, tx hashes, amounts, currencies, networks ONLY if present in context.
- Ready to send after operator review — complete replies, not fragments.

RUSSIAN STYLE — prefer: Проверим, Уточним, Оператор проверит, актуальный статус, заявка, поступление, вывод, обработка, по системе, на каком этапе.
RUSSIAN STYLE — avoid unless context supports: Мы обязательно, гарантируем, скоро, в ближайшее время, не переживайте, как только получим информацию, ваши средства в безопасности.

ENGLISH STYLE — prefer: We will check, verify, confirm in the system, reply here with the latest status.
ENGLISH STYLE — avoid unless verified: will arrive soon, payment received, order completed, we guarantee.

UK / KA: same natural professional tone in visitor language.
RULES;
    }

    public static function varietyRules(int $choices): string
    {
        return <<<RULES
VARIETY (mandatory — {$choices} options must feel like different operator choices):
1) Direct professional reply — check status / next step clearly.
2) Warmer human reply — polite, calm, still no fake ETA or confirmation.
3) Clarification or request-for-info — only if something is missing; otherwise state what operator will verify.
4) Safe expectation-setting — what happens next without promising timing or outcome.

Do NOT output nearly identical sentences. Change structure, verbs, and focus — not just one swapped word.
RULES;
    }

    public static function categoryPlaybook(): string
    {
        return <<<'PLAYBOOK'
SCENARIO PLAYBOOK (safe wording — never state unverified status):

order status / withdrawal wait
  GOOD: "Проверим заявку №… по системе и ответим здесь с актуальным статусом."
  BAD: "Ваша заявка скоро будет выполнена." / "Средства уже отправлены."

payment delay
  GOOD: "Платёж по заявке №… проверим вручную и уточним, поступил ли он на нашу сторону."
  BAD: "Ваш платёж уже получен."

withdrawal delay
  GOOD: "Проверим вывод по заявке №… и уточним, был ли он уже передан в обработку."
  BAD: "Средства уже отправлены."

missing order ID
  GOOD: "Пожалуйста, отправьте номер заявки, чтобы оператор смог проверить статус быстрее."
  BAD: "Мы проверим вашу заявку." (without asking for ID when none in context)

missing TX hash
  GOOD: "Пришлите, пожалуйста, TX hash платежа — так оператор быстрее проверит поступление."
  BAD: "Ожидайте, мы всё проверим." (when hash is needed)

OTC / large exchange
  GOOD: "Уточните сумму, валюту и направление обмена — оператор проверит возможность и предложит актуальные условия."
  BAD: "Мы можем обменять любую сумму."

exchange limit / rate
  GOOD: operator confirms actual limit or rate after review.
  BAD: quoting exact rate or limit as final without verification.

exchange direction / pair availability
  GOOD: "Направление [FROM] → [TO] доступно. По проверке кошелька оператор подскажет, можно ли принять адрес перед обменом."
  BAD: "Уточните номер кошелька." (when direction availability is already verified)
  BAD: "Направление недоступно." (when availability_status=supported)

follow-up (order ID already in thread)
  GOOD: "Проверим заявку №… повторно и ответим здесь по фактическому статусу."
  BAD: "Пожалуйста, отправьте номер заявки." (when order ID already known)

follow-up after operator promised check
  GOOD: "Проверим статус повторно и вернёмся с конкретным ответом по заявке."
  BAD: repeating "мы проверим" without acknowledging follow-up

visitor sent TX hash
  GOOD: "Проверим TX hash по платежу и уточним, видим ли поступление на нашей стороне."
  BAD: asking for TX hash again

operator asked for order ID, visitor provides it
  GOOD: "Спасибо, проверим заявку №… по системе и ответим здесь с актуальным статусом."
  BAD: "Пожалуйста, пришлите номер заявки."

operator promised check, visitor asks again
  GOOD: "Понимаем, что ожидание затянулось. Повторно уточним статус заявки и ответим здесь по фактической информации."
  BAD: "Проверим заявку и сообщим вам." (empty repeat)

operator already escalated
  GOOD: "Вопрос уже передан на дополнительную проверку, уточним результат и ответим здесь по фактическому статусу."
  BAD: "Мы передадим вопрос оператору."

operator gave status, visitor asks why
  GOOD: "Уточним причину текущего статуса заявки и ответим здесь без неподтверждённых обещаний."
  BAD: "Заявка скоро будет выполнена."

angry customer / where is my money
  GOOD: "Понимаем ваше беспокойство. Проверим детали заявки и ответим здесь по фактическому статусу."
  BAD: "Не переживайте, ваши средства в безопасности."

exact ETA request
  GOOD: "Точное время без проверки назвать нельзя, оператор уточнит статус обработки и ответит здесь."
  BAD: "Средства поступят скоро."

wrong network
  GOOD: "Проверим детали перевода и сеть отправки, после этого оператор уточнит возможные варианты."
  BAD: "Мы обязательно восстановим средства."

wrong amount / partial payment
  GOOD: "Проверим сумму поступления по заявке и уточним, требуется ли доплата или перерасчёт."
  BAD: "Заявка будет выполнена."

screenshot only
  GOOD: "Спасибо, оператор проверит данные на скриншоте; если потребуется TX hash, мы уточним это здесь."
  BAD: "Пришлите номер заявки." (when proof already sent and no intake needed)

funds safe / guaranteed
  GOOD: "Проверим детали заявки по системе и ответим здесь по фактическому статусу."
  BAD: "Ваши средства гарантированно в безопасности."

short unclear message
  GOOD: "Уточните, пожалуйста, номер заявки или TX hash, чтобы оператор смог проверить ситуацию."
  BAD: "Здравствуйте, чем можем помочь?"

CORE SAFETY: UNKNOWN STATUS must never become processing/sent/confirmed/paid/completed/soon.
PLAYBOOK;
    }
}
