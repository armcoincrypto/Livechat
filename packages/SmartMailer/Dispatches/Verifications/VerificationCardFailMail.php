<?php

namespace iEXPackages\SmartMailer\Dispatches\Verifications;

use App\Models\VerificationCard;
use iEXPackages\SmartMailer\SmartMailable;

class VerificationCardFailMail extends SmartMailable
{
    /**
     * Информация по заявки на верификацию
     *
     * @var VerificationCard
     */
    protected VerificationCard $verificationCard;

    /**
     * Create a new message instance.
     */
    public function __construct(VerificationCard $verificationCard)
    {
        parent::__construct($userVerification->user->language ?? null);
        $this->verificationCard = $verificationCard;
    }


    protected function compose(): void
    {
        $this->setSubject(__('smart-mailer::messages.verification_card_rejected'))
            ->markdown('smart-mailer::verifications.verification_fail', [
                'verification' => $this->verificationCard,
                'subject' => $this->subject,
            ]);
    }
}
