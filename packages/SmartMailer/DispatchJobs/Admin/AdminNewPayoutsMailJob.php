<?php

namespace iEXPackages\SmartMailer\DispatchJobs\Admin;


use App\Models\WithdrawalRequest;
use iEXPackages\SmartMailer\Dispatches\Admin\AdminNewPayoutsMail;
use iEXPackages\SmartMailer\SmartQueueableJob;
use Illuminate\Support\Facades\Mail;

class AdminNewPayoutsMailJob extends SmartQueueableJob
{
    /**
     * Данные
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
     * @throws \Exception
     */
    protected function run(): void
    {
        foreach (get_admin_recipient_email() as $value) {
            Mail::to($value)
                ->send(new AdminNewPayoutsMail($this->withdrawalRequest));
        }
    }
}
