<?php

namespace iEXPackages\SmartMailer\Dispatches\User;

use App\Models\User;
use iEXPackages\SmartMailer\SmartMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class UserIpChangedMail extends SmartMailable
{
    use Queueable, SerializesModels;

    public User $user;
    public ?array $data = null;

    public function __construct(User $user, ?array $data = null)
    {
        parent::__construct($user->language ?? null);
        $this->user = $user;
        $this->data = $data ?? [];
    }


    protected function compose(): void
    {
        $this->setSubject('🔐️ '. __('smart-mailer::messages.ip_change_detected'))
            ->markdown('smart-mailer::user.user_ip_changed', [
                'oldIp' => $this->data['oldIp'] ?? null,
                'newIp' => $this->data['newIp'] ?? null,
                'user' => $this->user,
                'subject' => $this->subject,
            ]);
    }
}
