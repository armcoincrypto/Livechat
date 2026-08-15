<?php

namespace iEXPackages\SmartMailer\DispatchJobs\Verifications;

use App\Models\VerificationCard;
use iEXPackages\SmartMailer\Dispatches\Verifications\VerificationCardSuccessMail;
use iEXPackages\SmartMailer\SmartQueueableJob;
use Illuminate\Support\Facades\Mail;

class VerificationCardSuccessJob extends SmartQueueableJob
{
    /**
     * Данные
     */
    protected VerificationCard $verificationCard;

    /**
     * Create a new job instance.
     */
    public function __construct(VerificationCard $verificationCard)
    {
        $this->verificationCard = $verificationCard;
    }

    /**
     * Execute the job.
     *
     * @throws \Exception
     */
    protected function run(): void
    {
        Mail::to($this->verificationCard->user->email)
            ->send(new VerificationCardSuccessMail($this->verificationCard));
    }
}
