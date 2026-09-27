<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\RateAuthorityDriftMonitor;
use Illuminate\Console\Command;

/**
 * Read-only owner-authority rate drift monitor + deduped alert events.
 * Never mutates course_value, ownership, profit, or positions.
 */
final class RatesAuthorityDriftMonitorCommand extends Command
{
    protected $signature = 'rates:authority-drift-monitor
        {--format=json : json|summary}
        {--no-persist : Do not write alert_state.json}
        {--no-jsonl : Do not append JSONL diagnostics}
        {--emit-alerts : Print operator-formatted alert bodies for new events}';

    protected $description = 'Owner-authority rate drift monitor (read-only; no pricing mutation).';

    public function handle(RateAuthorityDriftMonitor $monitor): int
    {
        $payload = $monitor->run(
            persistState: ! (bool) $this->option('no-persist'),
            writeJsonl: ! (bool) $this->option('no-jsonl'),
        );

        if ((bool) $this->option('emit-alerts')) {
            foreach ($payload['alert_events'] as $ev) {
                $this->line($monitor->formatOperatorAlert($ev));
                $this->line('---');
            }
        }

        if ((string) $this->option('format') === 'summary') {
            $this->line(json_encode($payload['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            // Cap directions list in stdout for operator usability; full set is in JSONL/state.
            $out = $payload;
            if (count($out['directions']) > 80) {
                $out['directions_truncated'] = true;
                $out['directions'] = array_slice($out['directions'], 0, 80);
            }
            $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $critical = (int) ($payload['summary']['CRITICAL_TOTAL'] ?? 0);
        $invariants = (int) ($payload['summary']['OWNERSHIP_INVARIANT_DEFECT_TOTAL'] ?? 0);

        return ($critical > 0 || $invariants > 0) ? self::FAILURE : self::SUCCESS;
    }
}
