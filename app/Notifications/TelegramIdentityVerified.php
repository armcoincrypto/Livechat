<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\KycProviderSession;
use App\Models\User;
use App\Services\TelegramOperator\TelegramBotTokenResolver;
use iEXPackages\KYCPlugin\Drivers\Didit\DiditOperatorNoticePrivacy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramIdentityVerified extends Notification implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public int $timeout = 20;

    public int $uniqueFor = 86400;

    public function __construct(
        public mixed $tokens,
        public User $user,
        public KycProviderSession $session,
        public ?string $chatIdOverride = null,
    ) {
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        $dest = $this->resolvedChatId() ?: 'default';

        return 'telegram-identity-verified:'.$this->user->id.':'.$dest;
    }

    public function via(): array
    {
        return [TelegramChannel::class];
    }

    public function resolvedChatId(): string
    {
        if ($this->chatIdOverride !== null && $this->chatIdOverride !== '') {
            return (string) $this->chatIdOverride;
        }

        return (string) ($this->tokens->id_channel ?? '');
    }

    public function toTelegram(): TelegramMessage
    {
        // Use the destination row's bot. TELEGRAM_OPERATOR_BOT_TOKEN is a
        // different identity and is not a member of the configured channel
        // (production: chat not found). Fall back to the operator resolver
        // only when the row has no token.
        $rowToken = trim((string) ($this->tokens->token_access ?? ''));
        $token = $rowToken !== ''
            ? $rowToken
            : app(TelegramBotTokenResolver::class)->resolve(null);

        return TelegramMessage::create()
            ->token($token)
            ->to($this->resolvedChatId())
            ->content($this->buildContent())
            ->options([
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);
    }

    public static function maskEmail(?string $email): string
    {
        return DiditOperatorNoticePrivacy::maskEmail($email);
    }

    public static function maskSessionId(?string $sessionId): string
    {
        return DiditOperatorNoticePrivacy::maskSessionId($sessionId);
    }

    private function buildContent(): string
    {
        $adminFolder = trim((string) config('iexexchanger.admin_folder', 'iexadmin'), '/');
        $caseKey = 'didit:'.(int) $this->session->id;
        $adminPath = '/'.$adminFolder.'/kyc-ops/cases/'.$caseKey;

        $lines = [
            '✅ Identity verification approved',
            '',
            'User: #'.(int) $this->user->id,
            'Email: '.DiditOperatorNoticePrivacy::maskEmail($this->user->email ?? null),
            'Provider: Didit',
            'Session: '.DiditOperatorNoticePrivacy::maskSessionId((string) $this->session->provider_session_id),
            'Time: '.now()->utc()->toIso8601String(),
            'Admin: '.$adminPath,
        ];

        return implode("\n", $lines);
    }
}
