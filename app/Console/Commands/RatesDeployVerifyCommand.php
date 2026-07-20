<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Verifies production rate-pipeline files match a certified git commit.
 */
final class RatesDeployVerifyCommand extends Command
{
    protected $signature = 'rates:deploy-verify
        {--commit=HEAD : Certified commit (default HEAD)}
        {--format=table : table|json}';

    protected $description = 'Fail if live rate-pipeline files drift from the certified git commit.';

    /** @var list<string> */
    private array $paths = [
        'xml-changer/main.py',
        'app/Services/Rates/RateSanityGuard.php',
        'app/Services/Rates/RateConfiguredExpectation.php',
        'app/Services/Rates/RateExportQuarantine.php',
        'app/Services/Rates/PeerRateSelector.php',
        'app/Services/Rates/BestChangeCurrencyCatalogGuard.php',
        'app/Services/Rates/BestChangeMappingRegistry.php',
        'app/Services/Rates/BestChangeMappingVerifier.php',
        'app/Services/Rates/RateDirectionEligibility.php',
        'resources/rates/bestchange-codes.overrides.json',
        'app/Services/Rates/AtomicPublicXmlPublisher.php',
        'app/Services/Rates/RateExportQuarantine.php',
        'app/Services/Rates/IndependentMarketBaseline.php',
        'packages/Courses/Export/ExportCourses.php',
        'packages/Courses/Export/Concerns/ExportFormatHelpers.php',
        'app/Console/Commands/RatesEconomicAuditCommand.php',

        'app/Services/Rates/IndependentMarketBaseline.php',
        'app/Console/Commands/RatesAuditCommand.php',
        'packages/BestChange/Services/RatesUpdateService.php',
        'packages/Courses/Export/Concerns/ExportFormatHelpers.php',
        'tests/Unit/Rates/RateSanityGuardTest.php',
        'tests/Unit/Rates/RatePostFixRemediationTest.php',
    ];

    public function handle(): int
    {
        $commit = (string) $this->option('commit');
        $rows = [];
        $drift = 0;
        $missing = 0;

        foreach ($this->paths as $path) {
            $abs = base_path($path);
            $blob = $this->git(['rev-parse', $commit . ':' . $path]);
            $live = is_file($abs) ? trim((string) shell_exec('git hash-object ' . escapeshellarg($abs))) : null;
            $ok = $blob !== null && $live !== null && hash_equals($blob, $live);
            if ($live === null) {
                $missing++;
            } elseif (!$ok) {
                $drift++;
            }
            $rows[] = [
                'path' => $path,
                'certified_blob' => $blob,
                'live_blob' => $live,
                'ok' => $ok,
            ];
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'commit' => $this->git(['rev-parse', $commit]),
            'head' => $this->git(['rev-parse', 'HEAD']),
            'drift' => $drift,
            'missing' => $missing,
            'files' => $rows,
        ];

        if ((string) $this->option('format') === 'json') {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['path', 'ok'], array_map(static fn ($r) => [$r['path'], $r['ok'] ? 'yes' : 'NO'], $rows));
            $this->info('commit=' . $payload['commit'] . ' drift=' . $drift . ' missing=' . $missing);
        }

        return ($drift === 0 && $missing === 0) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param list<string> $args
     */
    private function git(array $args): ?string
    {
        $cmd = 'git -C ' . escapeshellarg(base_path());
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }
        // Linked worktrees / root-owned .git: allow this repo path for the runtime user.
        $cmd = 'git -c safe.directory=' . escapeshellarg(base_path()) . ' ' . substr($cmd, 4);
        $out = [];
        $code = 1;
        exec($cmd . ' 2>/dev/null', $out, $code);
        if ($code !== 0) {
            return null;
        }

        return trim(implode("\n", $out));
    }
}
