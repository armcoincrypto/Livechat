<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiDraftService;
use iEXPackages\SupportChat\Services\SupportAiSuggestionUxService;
use Illuminate\Console\Command;

final class SupportChatUxSelfTestCommand extends Command
{
    protected $signature = 'support-chat:ux-self-test';

    protected $description = 'Self-test UX deduplication and compact Telegram formatting.';

    public function handle(SupportAiSuggestionUxService $ux, SupportAiDraftService $draft): int
    {
        $options = [
            ['label' => 'a', 'style' => 'x', 'text' => 'Отправьте номер заявки для проверки.'],
            ['label' => 'b', 'style' => 'x', 'text' => 'Укажите номер заявки, пожалуйста.'],
            ['label' => 'c', 'style' => 'x', 'text' => 'Для проверки пришлите номер заявки.'],
            ['label' => 'd', 'style' => 'x', 'text' => 'Нам нужен номер заявки.'],
        ];
        $deduped = $ux->deduplicateOptions($options);
        $this->line('Dedup: '.count($options).' → '.count($deduped));

        $legacyLen = mb_strlen((string) $draft->formatTelegramSeparateMessage([
            'language' => 'ru',
            'confidence' => 'low',
            'operator_confidence' => 'low',
            'ux' => ['intent' => 'unknown_context'],
            'options' => $options,
        ]) ?? '', 'UTF-8');

        $compactLen = mb_strlen((string) $draft->formatTelegramSeparateMessage([
            'language' => 'ru',
            'confidence' => 'high',
            'operator_confidence' => 'high',
            'ux' => ['intent' => 'single_payment'],
            'options' => [[
                'label' => 'Short professional',
                'style' => 'short_professional',
                'text' => 'Выплата может зависеть от суммы, направления и банка. Если для вас важен один платёж, сообщите заранее.',
            ]],
        ]) ?? '', 'UTF-8');

        $this->line('Telegram length low/4: '.$legacyLen);
        $this->line('Telegram length high/1: '.$compactLen);
        if ($legacyLen > 0) {
            $reduction = (int) round((1 - ($compactLen / $legacyLen)) * 100);
            $this->line('Estimated reduction: '.$reduction.'%');
        }

        return count($deduped) <= 2 ? self::SUCCESS : self::FAILURE;
    }
}
