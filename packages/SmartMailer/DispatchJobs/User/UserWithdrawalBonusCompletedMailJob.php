<?php

namespace iEXPackages\SmartMailer\DispatchJobs\User;


use App\Models\WithdrawalRequest;
use iEXPackages\SmartMailer\Dispatches\User\UserVerifiedPayoutsMail;
use iEXPackages\SmartMailer\Dispatches\User\UserWithdrawalBonusCompletedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class UserWithdrawalBonusCompletedMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Детали заявки
     *
     * @var WithdrawalRequest
     */
    protected WithdrawalRequest $withdrawalRequest;

    /**
     * Create a new job instance.
     */
    public function __construct(WithdrawalRequest $withdrawalRequest)
    {
        $this->withdrawalRequest = $withdrawalRequest;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Mail::to($this->withdrawalRequest->user->email)
            ->send(new UserWithdrawalBonusCompletedMail($this->withdrawalRequest));
    }
}
