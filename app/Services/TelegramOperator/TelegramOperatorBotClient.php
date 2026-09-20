<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramOperatorBotClient
{
    public function botToken(): string
    {
        $row = app(\App\Services\Orders\TelegramNotificationSelector::class)
            ->enabledForEvent('process_created_order')
            ->first();
        $fromNotification = $row ? (string) $row->token_access : '';

        return app(TelegramBotTokenResolver::class)->resolve($fromNotification);
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
        $this->editMessageResult($chatId, $messageId, $text, $keyboard);
    }

    /**
     * @param  list<list<array{text: string, callback_data?: string, url?: string}>>|null  $keyboard
     * @return array{ok: bool, message_missing: bool}
     */
    public function editMessageResult(int|string $chatId, int $messageId, string $text, ?array $keyboard = null): array
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

        $token = $this->botToken();
        if ($token === '') {
            return ['ok' => false, 'message_missing' => false];
        }

        try {
            $response = Http::timeout(12)
                ->asJson()
                ->post('https://api.telegram.org/bot'.$token.'/editMessageText', $payload);
            $json = $response->json();
            if (is_array($json) && ($json['ok'] ?? false)) {
                return ['ok' => true, 'message_missing' => false];
            }
            $desc = strtolower((string) ($json['description'] ?? ''));
            if (str_contains($desc, 'message is not modified')) {
                return ['ok' => true, 'message_missing' => false];
            }
            $missing = str_contains($desc, 'message to edit not found')
                || str_contains($desc, "message can't be edited")
                || (str_contains($desc, 'message') && str_contains($desc, 'not found'));

            return ['ok' => false, 'message_missing' => $missing];
        } catch (Throwable $e) {
            Log::warning('telegram_operator_bot_api_exception', [
                'method' => 'editMessageText',
                'exception' => $e::class,
            ]);

            return ['ok' => false, 'message_missing' => false];
        }
    }

    /**
     * @param  list<list<array{text: string, callback_data?: string, url?: string}>>|null  $keyboard
     */
    public function sendMessage(int|string $chatId, string $text, ?array $keyboard = null): void
    {
        $this->sendMessageResult($chatId, $text, $keyboard);
    }

    /**
     * @param  list<list<array{text: string, callback_data?: string, url?: string}>>|null  $keyboard
     * @return array{ok: bool, message_id: int|null}
     */
    public function sendMessageResult(int|string $chatId, string $text, ?array $keyboard = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if ($keyboard !== null) {
            $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
        }

        $result = $this->call('sendMessage', $payload);
        if (! ($result['ok'] ?? false)) {
            return ['ok' => false, 'message_id' => null];
        }
        $msgId = null;
        if (is_array($result['result'] ?? null) && isset($result['result']['message_id'])) {
            $msgId = (int) $result['result']['message_id'];
        }

        return ['ok' => true, 'message_id' => $msgId > 0 ? $msgId : null];
    }

    public function deleteMessage(int|string $chatId, int $messageId): bool
    {
        $result = $this->call('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);

        return (bool) ($result['ok'] ?? false);
    }
}
