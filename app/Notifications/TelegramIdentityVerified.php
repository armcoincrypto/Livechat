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

/**
 * Didit approval DM. Token is TELEGRAM_OPERATOR_BOT_TOKEN only.
 * Destination is a linked operator private chat. The channel notification row is not used.
 */
class TelegramIdentityVerified extends Notification implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public int $timeout = 20;

    public int $uniqueFor = 86400;

    public function __construct(
        public User $user,
        public KycProviderSession $session,
        public string $operatorChatId,
    ) {
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        return 'telegram-identity-verified:'.$this->user->id.':'.$this->session->id.':'.$this->operatorChatId;
    }

    public function via(): array
    {
        return [TelegramChannel::class];
    }

    public function resolvedChatId(): string
    {
        return $this->operatorChatId;
    }

    public function toTelegram(): TelegramMessage
    {
        $token = app(TelegramBotTokenResolver::class)->resolve(null);

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
