<?php

return [

    /**
     * Классы для прямой отправки писем.
     * Каждый ключ соответствует идентификатору письма, а значение — полное имя класса.
     *
     * @var array<string, class-string>
     */
    'mailables' => [
        'order_created' => \iEXPackages\SmartMailer\Dispatches\Orders\OrderCreatedMail::class,
        'order_restored' => \iEXPackages\SmartMailer\Dispatches\Orders\OrderRestoreMail::class,
        'order_payment_details_issued' => \iEXPackages\SmartMailer\Dispatches\Orders\OrderPaymentDetailsIssuedMail::class,

        'user_ip_changed' => \iEXPackages\SmartMailer\Dispatches\User\UserIpChangedMail::class,

        'verification_account_fail' => \iEXPackages\SmartMailer\Dispatches\Verifications\VerificationAccountFailMail::class,
        'verification_account_success' => \iEXPackages\SmartMailer\Dispatches\Verifications\VerificationAccountSuccessMail::class,

        'verification_card_fail' => \iEXPackages\SmartMailer\Dispatches\Verifications\VerificationCardFailMail::class,
        'verification_card_success' => \iEXPackages\SmartMailer\Dispatches\Verifications\VerificationCardSuccessMail::class,
    ],

    /**
     * Задачи для асинхронной отправки писем через очередь.
     * Каждый ключ — идентификатор задачи, значение — класс задачи.
     *
     * @var array<string, class-string>
     */
    'jobs' => [
        'order_created_job' => \iEXPackages\SmartMailer\DispatchJobs\Orders\OrderCreatedMailJob::class,
        'order_rejected_job' => \iEXPackages\SmartMailer\DispatchJobs\Orders\OrderRejectedMailJob::class,
        'order_defer_job' => \iEXPackages\SmartMailer\DispatchJobs\Orders\OrderDeferMailJob::class,
        'order_shot_blacklist_job' => \iEXPackages\SmartMailer\DispatchJobs\Orders\OrderShotBlackListJob::class,

        'order_completed_job' => \iEXPackages\SmartMailer\DispatchJobs\Orders\OrderCompletedMailJob::class,

        'user_verified_payouts_job' => \iEXPackages\SmartMailer\DispatchJobs\User\UserVerifiedPayoutsMailJob::class,
        'user_withdrawal_bonus_completed_job' => \iEXPackages\SmartMailer\DispatchJobs\User\UserWithdrawalBonusCompletedMailJob::class,

        'verification_card_fail_job' => \iEXPackages\SmartMailer\DispatchJobs\Verifications\VerificationCardFailJob::class,
        'verification_card_success_job' => \iEXPackages\SmartMailer\DispatchJobs\Verifications\VerificationCardSuccessJob::class,


        'admin_new_payouts_job' => \iEXPackages\SmartMailer\DispatchJobs\Admin\AdminNewPayoutsMailJob::class,
    ],

    /**
     * Классы условий, проверяемых перед отправкой письма.
     * Ключ — идентификатор условия, значение — класс условия.
     *
     * @var array<string, class-string>
     */
    'conditions' => [
        'order_created' => \iEXPackages\SmartMailer\Conditions\OrderCreatedCondition::class,
        'order_completed' => \iEXPackages\SmartMailer\Conditions\OrderCompletedCondition::class,
        'order_rejected' => \iEXPackages\SmartMailer\Conditions\OrderRejectedCondition::class,
        'order_defer' => \iEXPackages\SmartMailer\Conditions\OrderFrozenCondition::class,
    ],
];
