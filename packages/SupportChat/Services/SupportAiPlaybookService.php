<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Collection;

/**
 * Read-only extraction of anonymized operator reply patterns from historical chats.
 * Does not call OpenAI or write to the database.
 */
final class SupportAiPlaybookService
{
    /** @var list<string> */
    private const INTENTS = [
        'order_status',
        'exchange_limit',
        'otc_large',
        'payment_proof',
        'under_over_payment',
        'wrong_network',
        'aml_kyc',
        'impatient_customer',
        'audit_test',
        'general',
    ];

    /**
     * @return array{
     *     conversations_scanned: int,
     *     operator_replies: int,
     *     groups: array<string, array<string, list<string>>>,
     *     playbook_text: string
     * }
     */
    public function buildPlaybook(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $conversations = SupportConversation::query()
            ->where(function ($q): void {
                $q->where('status', SupportConversation::STATUS_CLOSED)
                    ->orWhereNotNull('last_operator_message_at');
            })
            ->whereNotNull('last_operator_message_at')
            ->orderByDesc('last_operator_message_at')
            ->limit($limit)
            ->get();

        $groups = [];
        $replyCount = 0;

        foreach ($conversations as $conversation) {
            $operatorMessages = SupportMessage::query()
                ->where('support_conversation_id', $conversation->id)
                ->where('sender_type', SupportMessage::SENDER_OPERATOR)
                ->orderByDesc('id')
                ->limit(3)
                ->get()
                ->reverse()
                ->values();

            if ($operatorMessages->isEmpty()) {
                continue;
            }

            $visitorSample = $this->visitorSample($conversation);
            $language = $this->detectLanguage($visitorSample, (string) ($conversation->locale ?? ''));
            $intent = $this->classifyIntent($visitorSample, $operatorMessages);

            foreach ($operatorMessages as $message) {
                $body = trim((string) $message->body);
                if ($body === '' || mb_strlen($body, 'UTF-8') < 20) {
                    continue;
                }

                $anonymized = SupportAiAnonymizer::anonymizeForPlaybook($body);
                if ($anonymized === '' || mb_strlen($anonymized, 'UTF-8') < 15) {
                    continue;
                }

                $groups[$language][$intent] ??= [];
                if (! in_array($anonymized, $groups[$language][$intent], true)) {
                    $groups[$language][$intent][] = $anonymized;
                }
                $replyCount++;
            }
        }

        ksort($groups);
        foreach ($groups as $lang => $intents) {
            ksort($groups[$lang]);
            foreach ($groups[$lang] as $intent => $patterns) {
                $groups[$lang][$intent] = array_slice($patterns, 0, 5);
            }
        }

        return [
            'conversations_scanned' => $conversations->count(),
            'operator_replies' => $replyCount,
            'groups' => $groups,
            'playbook_text' => $this->formatPlaybookText($groups, $conversations->count(), $replyCount),
        ];
    }

    /**
     * @param  array<string, array<string, list<string>>>  $groups
     */
    private function formatPlaybookText(array $groups, int $scanned, int $replies): string
    {
        $lines = [
            'Support AI Playbook (read-only, anonymized — style guidance only)',
            'Conversations scanned: '.$scanned,
            'Operator replies extracted: '.$replies,
            'NOTE: Not sent to OpenAI. Not source of truth for order/payment status.',
            '',
            '--- Built-in safe templates ---',
        ];

        foreach (SupportAiReplyPlaybook::builtInExamples() as $example) {
            $lines[] = sprintf('[%s / %s] %s', $example['language'], $example['intent'], $example['example']);
        }

        $lines[] = '';
        $lines[] = '--- Historical patterns (anonymized from closed/replied chats) ---';

        if ($groups === []) {
            $lines[] = '(no operator reply patterns found in sample)';
        }

        foreach ($groups as $language => $intents) {
            foreach ($intents as $intent => $patterns) {
                $lines[] = '';
                $lines[] = "=== {$language} / {$intent} ===";
                foreach ($patterns as $pattern) {
                    $lines[] = '- '.$pattern;
                }
            }
        }

        return implode("\n", $lines);
    }

    private function visitorSample(SupportConversation $conversation): string
    {
        $latest = SupportMessage::query()
            ->where('support_conversation_id', $conversation->id)
            ->where('sender_type', SupportMessage::SENDER_VISITOR)
            ->orderByDesc('id')
            ->value('body');

        if (is_string($latest) && trim($latest) !== '') {
            return trim($latest);
        }

        return trim((string) ($conversation->page_url ?? ''));
    }

    private function detectLanguage(string $sample, string $locale): string
    {
        if (preg_match('/[а-яё]/iu', $sample) === 1) {
            return preg_match('/[іїєґ]/iu', $sample) === 1 ? 'uk' : 'ru';
        }
        if (preg_match('/[ა-ჰ]/u', $sample) === 1) {
            return 'ka';
        }

        $base = strtolower(explode('-', trim($locale))[0] ?? '');
        if (in_array($base, ['ru', 'en', 'uk', 'ka'], true)) {
            return $base;
        }

        return 'en';
    }

    /**
     * @param  Collection<int, SupportMessage>  $operatorMessages
     */
    private function classifyIntent(string $visitorSample, Collection $operatorMessages): string
    {
        $text = mb_strtolower($visitorSample, 'UTF-8');
        foreach ($operatorMessages as $msg) {
            $text .= ' '.mb_strtolower((string) $msg->body, 'UTF-8');
        }

        $rules = [
            'audit_test' => '/\b(test|audit|тест|проверка\s*чата)\b/u',
            'order_status' => '/\b(статус|заявк|вывод|withdraw|order|ожид|delay|pending|where\s+is)\b/u',
            'exchange_limit' => '/\b(лимит|limit|maximum|min(?:imum)?|сумм)\b/u',
            'otc_large' => '/\b(otc|крупн|large\s+amount|офис|cash|налич)\b/u',
            'payment_proof' => '/\b(tx|hash|транзак|скрин|screenshot|proof|чек)\b/u',
            'under_over_payment' => '/\b(недоплат|переплат|underpay|overpay|wrong\s+amount)\b/u',
            'wrong_network' => '/\b(сеть|network|trc|erc|bep|не\s*ту\s*сеть|wrong\s+network)\b/u',
            'aml_kyc' => '/\b(aml|kyc|вериф|compliance|sanction)\b/u',
            'impatient_customer' => '/\b(сколько\s+ждать|when|urgent|!!!|где\s+деньги|where\s+money|жду)\b/u',
        ];

        foreach ($rules as $intent => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $intent;
            }
        }

        return 'general';
    }
}
