<?php

namespace iEXPackages\SmartMailer\Dispatches\User;

use App\Models\WithdrawalRequest;
use iEXPackages\SmartMailer\SmartMailable;

class UserVerifiedPayoutsMail extends SmartMailable
{

    /**
     * @var WithdrawalRequest
     */
    public WithdrawalRequest $withdrawalRequest;

    /**
     * URL подтверждения выплаты
     *
     * @var string
     */
    public string $confirmUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(WithdrawalRequest $withdrawalRequest)
    {
        parent::__construct($withdrawalRequest->user->language ?? null);
        $this->withdrawalRequest = $withdrawalRequest;
        $this->confirmUrl = config('app.frontend_url') . '/confirms/partner-withdrawal/' . $withdrawalRequest->tx_id;
    }

    protected function compose(): void
    {
        $this->setSubject(__('smart-mailer::messages.payout_request_subject'))
            ->markdown('smart-mailer::admin.verified_payouts', [
                'item' => $this->withdrawalRequest,
                'confirmUrl' => $this->confirmUrl,
            ]);
    }
}
