<?php

namespace iEXPackages\SmartMailer\Dispatches\Admin;

use App\Models\WithdrawalRequest;
use iEXPackages\SmartMailer\SmartMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class AdminNewPayoutsMail extends SmartMailable
{
    /**
     * Модель WithdrawalRequest
     */
    protected WithdrawalRequest $withdrawalRequest;

    /**
     * Create a new message instance.
     */
    public function __construct(WithdrawalRequest $withdrawalRequest)
    {
        parent::__construct('ru');
        $this->withdrawalRequest = $withdrawalRequest;
    }

    protected function compose(): void
    {
        $this->setSubject(__('smart-mailer::messages.new_payout_request_subject', ['id' => $this->withdrawalRequest->big_id]))
            ->markdown('smart-mailer::admin.new_payouts_mail', [
                'subject' => $this->subject,
                'item' => $this->withdrawalRequest,
            ]);
    }
}
