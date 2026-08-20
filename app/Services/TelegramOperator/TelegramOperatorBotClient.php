<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramOperatorBotClient
{
    public function botToken(): string
    {
        $env = trim((string) config('telegram_operator.bot_token', ''));
        if ($env !== '') {
            return $env;
        }

        $row = app(\App\Services\Orders\TelegramNotificationSelector::class)
            ->enabledForEvent('process_created_order')
            ->first();

        return $row ? trim((string) $row->token_access) : '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, result?: mixed}
     */
    public function call(string $method, array $payload): array
    {
        $token = $this->botToken();
        if ($token === '') {
            Log::warning('telegram_operator_bot_token_missing', ['method' => $method]);

            return ['ok' => false];
        }

        try {
            $response = Http::timeout(12)
                ->asJson()
                ->post('https://api.telegram.org/bot'.$token.'/'.$method, $payload);

            $json = $response->json();
            if (! is_array($json) || ! ($json['ok'] ?? false)) {
                Log::warning('telegram_operator_bot_api_failed', [
                    'method' => $method,
                    'http' => $response->status(),
                ]);

                return ['ok' => false];
            }

            return ['ok' => true, 'result' => $json['result'] ?? null];
        } catch (Throwable $e) {
            Log::warning('telegram_operator_bot_api_exception', [
                'method' => $method,
                'exception' => $e::class,
            ]);

            return ['ok' => false];
        }
    }

    public function answerCallback(string $callbackQueryId, string $text, bool $alert = false): void
    {
        $this->call('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => mb_substr($text, 0, 180),
            'show_alert' => $alert,
        ]);
    }

    /**
     * @param  list<list<array{text: string, callback_data?: string, url?: string}>>|null  $keyboard
     */
    public function editMessage(int|string $chatId, int $messageId, string $text, ?array $keyboard = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if ($keyboard !== null) {
            $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
        } else {
            $payload['reply_markup'] = ['inline_keyboard' => []];
        }

        $this->call('editMessageText', $payload);
    }

    /**
     * @param  list<list<array{text: string, callback_data?: string, url?: string}>>|null  $keyboard
     */
    public function sendMessage(int|string $chatId, string $text, ?array $keyboard = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if ($keyboard !== null) {
            $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
        }

        $this->call('sendMessage', $payload);
    }
}
