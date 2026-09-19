<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TelegramOperator\TelegramBotTokenResolver;
use App\Services\TelegramOperator\TelegramOperatorAuthService;
use App\Services\TelegramOperator\TelegramSecretRedactor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Operator-only transport check for the private KYC bot path.
 * Does not approve a user, create a Didit session, or touch telegram_notifications.
 */
final class KycOperatorTransportTestCommand extends Command
{
    protected $signature = 'exswaping:kyc-operator-transport-test';

    protected $description = 'Send one labelled KYC transport test to linked operator private chats';

    public function handle(TelegramOperatorAuthService $auth, TelegramBotTokenResolver $tokens): int
    {
        $token = $tokens->resolve(null);
        if ($token === '') {
            $this->error('MISSING_OPERATOR_TOKEN');

            return self::FAILURE;
        }

        $me = $this->call($token, 'getMe', []);
        $username = (string) ($me['username'] ?? '');
        $this->line('BOT_USERNAME='.($username !== '' ? '@'.$username : 'none'));
        $this->line('TOKEN_VALID='.($me['ok'] ? 'yes' : 'no'));
        if (!$me['ok'] || strcasecmp($username, 'exswapingnotyfbot') !== 0) {
            $this->error('UNEXPECTED_BOT');

            return self::FAILURE;
        }

        $chatIds = $auth->authorizedActionTelegramUserIds();
        $this->line('PRIVATE_OPERATOR_SUBSCRIBER_COUNT='.count($chatIds));
        if ($chatIds === []) {
            $this->error('NO_PRIVATE_OPERATOR');

            return self::FAILURE;
        }

        $text = "Exswaping KYC notification transport test\n".gmdate('Y-m-d\TH:i:s\Z');
        $sent = 0;
        foreach ($chatIds as $chatId) {
            $probe = $this->call($token, 'getChat', ['chat_id' => $chatId]);
            $type = (string) ($probe['type'] ?? '');
            if (!$probe['ok'] || $type !== 'private') {
                $this->line('DESTINATION_SKIPPED suffix='.substr((string) $chatId, -3).' type='.($type !== '' ? $type : 'none'));

                continue;
            }
            $send = $this->call($token, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);
            $this->line('SEND suffix='.substr((string) $chatId, -3).' ok='.($send['ok'] ? 'yes' : 'no').' type=private http='.(string) ($send['http'] ?? ''));
            if (!$send['ok']) {
                $this->line('SEND_ERROR='.(string) ($send['error'] ?? ''));

                return self::FAILURE;
            }
            $sent++;
        }

        $this->line('PRIVATE_KYC_BOT_TEST_SENT='.($sent > 0 ? 'yes' : 'no'));
        $this->line('PRIVATE_KYC_BOT_TEST_ACCEPTED='.($sent > 0 ? 'yes' : 'no'));
        $this->line('SENDER_USERNAME=@exswapingnotyfbot');
        $this->line('DESTINATION_TYPE=private_chat');
        $this->line('SENT_COUNT='.$sent);

        return $sent > 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(string $token, string $method, array $params): array
    {
        try {
            $response = Http::timeout(12)->asJson()->post('https://api.telegram.org/bot'.$token.'/'.$method, $params);
            $json = $response->json();
            $ok = is_array($json) && ($json['ok'] ?? false) === true;
            $result = is_array($json['result'] ?? null) ? $json['result'] : [];
            $error = $ok ? '' : TelegramSecretRedactor::message((string) ($json['description'] ?? ('http_'.$response->status())));

            return [
                'ok' => $ok,
                'http' => $response->status(),
                'error' => $error,
                'username' => (string) ($result['username'] ?? ''),
                'type' => (string) ($result['type'] ?? ''),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'http' => 0,
                'error' => TelegramSecretRedactor::message($e->getMessage()),
                'username' => '',
                'type' => '',
            ];
        }
    }
}
