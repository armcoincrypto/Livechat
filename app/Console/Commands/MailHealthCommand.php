<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only mail configuration + failed-job observability.
 * Never prints secrets. Query failure → UNKNOWN (not healthy zero).
 */
final class MailHealthCommand extends Command
{
    protected $signature = 'mail:health {--format=json : json|table}';

    protected $description = 'Read-only mail config and recent failure health (no mutations).';

    public function handle(): int
    {
        try {
            $payload = $this->snapshot();
        } catch (\Throwable $e) {
            $unknown = [
                'generated_at' => now()->toIso8601String(),
                'MAIL_CONFIG_OK' => 'UNKNOWN',
                'MAIL_QUEUE_DEPTH' => 'UNKNOWN',
                'MAIL_FAILED_24H' => 'UNKNOWN',
                'MAIL_LAST_SUCCESS' => 'UNKNOWN',
                'MAIL_LAST_FAILURE' => 'UNKNOWN',
                'error' => 'health_query_failed',
            ];
            $this->line(json_encode($unknown, JSON_PRETTY_PRINT));

            return self::FAILURE;
        }

        if ($this->option('format') === 'table') {
            foreach ($payload as $k => $v) {
                if (! is_array($v)) {
                    $this->line("{$k}=".json_encode($v));
                }
            }

            return self::SUCCESS;
        }

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $from = (string) config('mail.from.address', '');
        $mailer = (string) config('mail.default', '');
        $host = (string) config('mail.mailers.smtp.host', '');
        $port = config('mail.mailers.smtp.port');
        $user = config('mail.mailers.smtp.username');

        $configOk = $from !== '' && $mailer !== '' && ($mailer !== 'smtp' || ($host !== '' && $port));

        $failed24 = 'UNKNOWN';
        $failedTotal = 'UNKNOWN';
        $lastFailure = 'UNKNOWN';
        $mailFailed24 = 'UNKNOWN';
        $topMailClasses = [];

        if (Schema::hasTable('failed_jobs')) {
            $failedTotal = (int) DB::table('failed_jobs')->count();
            $failed24 = (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
            $last = DB::table('failed_jobs')->orderByDesc('failed_at')->value('failed_at');
            $lastFailure = $last ? (string) $last : null;

            $recent = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->get(['payload']);
            $mail24 = 0;
            $counts = [];
            foreach ($recent as $row) {
                $p = json_decode((string) $row->payload, true);
                $cls = (string) ($p['displayName'] ?? ($p['data']['commandName'] ?? 'unknown'));
                if (preg_match('/Mail|Notification|VerifyEmail|ResetPassword|SetPassword|SmartMailer/i', $cls)) {
                    $mail24++;
                    $counts[$cls] = ($counts[$cls] ?? 0) + 1;
                }
            }
            $mailFailed24 = $mail24;
            arsort($counts);
            $topMailClasses = array_slice($counts, 0, 10, true);
        }

        // Success telemetry is not durably recorded in this release.
        $lastSuccess = null;

        $queueDepth = 'UNKNOWN';
        try {
            if (config('queue.default') === 'redis') {
                $queueDepth = 'redis_configured';
            } else {
                $queueDepth = (string) config('queue.default', 'UNKNOWN');
            }
        } catch (\Throwable) {
            $queueDepth = 'UNKNOWN';
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'MAIL_CONFIG_OK' => $configOk ? 'YES' : 'NO',
            'MAIL_MAILER' => $mailer !== '' ? $mailer : 'missing',
            'MAIL_FROM' => $from !== '' ? 'set' : 'missing',
            'MAIL_SMTP_HOST' => $host !== '' ? 'set' : 'missing',
            'MAIL_SMTP_PORT' => $port !== null && $port !== '' ? 'set' : 'missing',
            'MAIL_SMTP_USERNAME' => filled($user) ? 'set' : 'missing',
            'MAIL_QUEUE_DEPTH' => $queueDepth,
            'FAILED_JOBS_TOTAL' => $failedTotal,
            'FAILED_JOBS_24H' => $failed24,
            'MAIL_FAILED_24H' => $mailFailed24,
            'MAIL_LAST_SUCCESS' => $lastSuccess, // intentionally null → not fabricated
            'MAIL_LAST_FAILURE' => $lastFailure,
            'MAIL_FAILED_24H_TOP_CLASSES' => $topMailClasses,
            'ACTIVE_MAIL_DEFECT' => is_int($mailFailed24) && $mailFailed24 > 0,
        ];
    }
}
