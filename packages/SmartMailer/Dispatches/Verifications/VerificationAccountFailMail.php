<?php

namespace iEXPackages\SmartMailer\Dispatches\Verifications;

use App\Models\UserVerification;
use App\Models\VerificationCard;
use iEXPackages\SmartMailer\SmartMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class VerificationAccountFailMail extends SmartMailable
{
    /**
     * Информация по заявки на верификацию
     *
     * @var UserVerification
     */
    protected UserVerification $userVerification;

    /**
     * Create a new message instance.
     */
    public function __construct(UserVerification $userVerification)
    {
        parent::__construct($userVerification->user->language ?? null);
        $this->userVerification = $userVerification;
    }


    protected function compose(): void
    {
        $this->setSubject(__('smart-mailer::messages.verification_account_rejected'))
            ->markdown('smart-mailer::verifications.verification_account_fail', [
                'item' => $this->userVerification,
                'subject' => $this->subject,
            ]);
    }
}
