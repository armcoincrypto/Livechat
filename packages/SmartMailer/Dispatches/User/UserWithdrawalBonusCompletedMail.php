<?php

namespace iEXPackages\SmartMailer\Dispatches\User;

use App\Models\WithdrawalRequest;
use iEXPackages\SmartMailer\SmartMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class UserWithdrawalBonusCompletedMail extends SmartMailable
{
    use Queueable, SerializesModels;

    /**
     * Объявляем модель WithdrawalRequest
     */
    protected WithdrawalRequest $withdrawalRequest;

    public function __construct(WithdrawalRequest $withdrawalRequest)
    {
        parent::__construct($withdrawalRequest->user->language ?? null);
        $this->withdrawalRequest = $withdrawalRequest;
    }


    protected function compose(): void
    {
        $totalWithdrawal = floatval($this->withdrawalRequest->view_balance_referral);

        $payment = sprintf('%s %s', $this->withdrawalRequest->currency->payment->name, $this->withdrawalRequest->currency->code_currency->name);
        $balance = sprintf('%s %s', $totalWithdrawal, $this->withdrawalRequest->currency->code_currency->name);


        $this->setSubject(__('smart-mailer::messages.partner_payout_completed'))
            ->markdown('smart-mailer::user.withdrawal_bonus_completed', [
                'item' => $this->withdrawalRequest,
                'payment' => $payment,
                'balance' => $balance,
            ]);
    }
}
