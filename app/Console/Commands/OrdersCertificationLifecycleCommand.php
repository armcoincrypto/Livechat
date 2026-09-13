<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Certification\CertificationContext;
use App\Services\Certification\CertificationGate;
use App\Services\Certification\CertificationLifecycleRunner;
use Illuminate\Console\Command;

final class OrdersCertificationLifecycleCommand extends Command
{
    protected $signature = 'orders:certification-lifecycle
        {--scenario=success : success|fail_closed}
        {--email=certification-lifecycle@qa.exswaping.com : QA-only mailbox}
        {--token= : Must match EXS_ORDER_CERTIFICATION_TOKEN}';

    protected $description = 'Operator-only default-off certification rail. Never calls Kobbopay.';

    public function handle(CertificationLifecycleRunner $runner): int
    {
        $token = (string) $this->option('token');
        $email = (string) $this->option('email');
        $scenario = (string) $this->option('scenario');
        if ($scenario !== CertificationContext::SCENARIO_FAIL_CLOSED) {
            $scenario = CertificationContext::SCENARIO_SUCCESS;
        }

        if (! (bool) config('exs_order_certification.enabled', false)) {
            $this->error(CertificationGate::REJECTED.' flag_disabled');

            return self::FAILURE;
        }

        if ($token === '' || strlen($token) < 16) {
            $this->error(CertificationGate::REJECTED.' token_not_configured');

            return self::FAILURE;
        }

        try {
            $result = $runner->run($scenario, $email, $token);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
