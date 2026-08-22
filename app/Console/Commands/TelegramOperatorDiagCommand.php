<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TelegramNotification;
use App\Services\TelegramOperator\TelegramBotTokenResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Safe operator-bot diagnostic: getMe + getChat. Optional one sendMessage.
 */
final class TelegramOperatorDiagCommand extends Command
{
    protected $signature = 'exswaping:telegram-operator-diag {--send : Send one diagnostic text (default off)}';

    protected $description = 'Check operator Telegram bot reachability without printing secrets';

    public function handle(): int
    {
        $row = TelegramNotification::query()->where('status', 1)->orderBy('id')->first();
        if ($row === null) {
            $this->error('NO_ACTIVE_TELEGRAM_NOTIFICATION');

            return self::FAILURE;
        }

        $token = app(TelegramBotTokenResolver::class)->resolve((string) $row->token_access);
        $chatId = trim((string) $row->id_channel);
        if ($token === '') {
            $this->error('MISSING_TOKEN');

            return self::FAILURE;
        }

        $timeout = (float) config('services.telegram-bot-api.timeout', 5);
        $connect = (float) config('services.telegram-bot-api.connect_timeout', 2);

        $getMe = $this->telegramCall($token, 'getMe', [], $timeout, $connect);
        $this->line('getMe_ok='.($getMe['ok'] ? '1' : '0'));
        $this->line('getMe_http='.(string) ($getMe['http'] ?? ''));
        $this->line('getMe_error='.(string) ($getMe['error'] ?? ''));
        $this->line('bot_username='.(string) ($getMe['username'] ?? ''));
        $this->line('ACTIVE_OPERATOR_BOT='.(string) ($getMe['username'] ?? ''));

        $getChat = $this->telegramCall($token, 'getChat', ['chat_id' => $chatId], $timeout, $connect);
        $this->line('getChat_ok='.($getChat['ok'] ? '1' : '0'));
        $this->line('getChat_http='.(string) ($getChat['http'] ?? ''));
        $this->line('getChat_error='.(string) ($getChat['error'] ?? ''));
        $this->line('chat_type='.(string) ($getChat['type'] ?? ''));
        $this->line('chat_title_present='.(! empty($getChat['title']) ? '1' : '0'));

        if (! $this->option('send')) {
            return $getMe['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $text = 'Exswaping operator diagnostic '.gmdate('Y-m-d\TH:i:s\Z').' (ignore)';
        $send = $this->telegramCall($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_notification' => true,
        ], $timeout, $connect);
        $this->line('send_ok='.($send['ok'] ? '1' : '0'));
        $this->line('send_http='.(string) ($send['http'] ?? ''));
        $this->line('send_error='.(string) ($send['error'] ?? ''));

        return $send['ok'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function telegramCall(string $token, string $method, array $params, float $timeout, float $connect): array
    {
        try {
            $url = 'https://api.telegram.org/bot'.$token.'/'.$method;
            $response = Http::timeout($timeout)
                ->connectTimeout($connect)
                ->asJson()
                ->post($url, $params);

            $json = $response->json();
            $ok = is_array($json) && ($json['ok'] ?? false) === true;
            $result = is_array($json['result'] ?? null) ? $json['result'] : [];

            return [
                'ok' => $ok,
                'http' => $response->status(),
                'error' => $ok ? '' : (string) ($json['description'] ?? ('http_'.$response->status())),
                'username' => (string) ($result['username'] ?? ''),
                'type' => (string) ($result['type'] ?? ''),
                'title' => (string) ($result['title'] ?? $result['username'] ?? ''),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'http' => 0,
                'error' => $e::class,
                'username' => '',
                'type' => '',
                'title' => '',
            ];
        }
    }
}
