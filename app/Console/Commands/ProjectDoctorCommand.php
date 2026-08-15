<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Process\Process;

final class ProjectDoctorCommand extends Command
{
    protected $signature = 'project:doctor
        {--paths= : Comma-separated list of directories to scan (default: app,packages,bootstrap)}
        {--exclude= : Comma-separated list of directories to exclude (default: vendor,node_modules,storage)}
        {--php= : PHP binary to use (default: php)}
        {--no-tests : Skip PHPUnit}
        {--no-static : Skip static analysis (phpstan)}
        {--no-style : Skip code style (pint)}
        {--format=table : Output format: table|json|md}
        {--output= : Save report to file (json/md)}
        {--timeout=600 : Timeout seconds for each check}
        {--fail-on=error : Exit code policy: none|error|warning}
        {--progress : Show per-file progress during internal scans (smells)}
        {--stream : Stream external tool output live to console}';

    protected $description = 'Analyzes project health: static analysis, style, tests, composer validation, and quick smells.';

    public function handle(): int
    {
        $root = base_path();

        $paths = $this->parseListOption($this->option('paths'), ['app', 'packages', 'bootstrap']);
        $exclude = $this->parseListOption($this->option('exclude'), ['vendor', 'node_modules', 'storage']);

        $paths = $this->normalizeDirs($paths, $root);
        $exclude = $this->normalizeDirs($exclude, $root);

        if (empty($paths)) {
            $this->error('No valid paths found. Provide --paths=app,packages,bootstrap');
            return self::FAILURE;
        }

        $php = (string)($this->option('php') ?: 'php');
        $timeout = max(30, (int)$this->option('timeout'));
        $format = (string)($this->option('format') ?: 'table');
        $output = (string)($this->option('output') ?: '');
        $showProgress = (bool) $this->option('progress');
        $stream = (bool) $this->option('stream');

        $checks = [];

        // Composer validate
        $checks[] = $this->check(
            id: 'composer_validate',
            title: 'Composer validate',
            cmd: ['composer', 'validate', '--no-check-publish'],
            stream: $stream,
            timeout: $timeout
        );

        // PHPStan (recommended)
        if (!$this->option('no-static')) {
            $phpstan = file_exists($root . '/vendor/bin/phpstan') ? $root . '/vendor/bin/phpstan' : null;
            $checks[] = $this->check(
                id: 'phpstan',
                title: 'PHPStan',
                cmd: $phpstan
                    ? [$php, $phpstan, 'analyse', '--memory-limit=1G', ...$this->pathsForTool($paths, $exclude)]
                    : null,
                stream: $stream,
                timeout: $timeout,
                notInstalledHint: 'Install: composer require --dev phpstan/phpstan'
            );
        }

        // Pint
        if (!$this->option('no-style')) {
            $pint = file_exists($root . '/vendor/bin/pint') ? $root . '/vendor/bin/pint' : null;
            $checks[] = $this->check(
                id: 'pint',
                title: 'Laravel Pint (dry-run)',
                cmd: $pint
                    ? [$php, $pint, '--test', ...$this->pathsForTool($paths, $exclude)]
                    : null,
                stream: $stream,
                timeout: $timeout,
                notInstalledHint: 'Install: composer require --dev laravel/pint'
            );
        }

        // PHPUnit
        if (!$this->option('no-tests')) {
            $phpunit = file_exists($root . '/vendor/bin/phpunit') ? $root . '/vendor/bin/phpunit' : null;
            $checks[] = $this->check(
                id: 'phpunit',
                title: 'PHPUnit',
                cmd: $phpunit ? [$php, $phpunit, '--colors=never'] : null,
                stream: $stream,
                timeout: $timeout,
                notInstalledHint: 'Install: composer require --dev phpunit/phpunit'
            );
        }

        // Quick smells (fast grep-like checks)
        $checks[] = $this->smellsCheck($paths, $exclude, $showProgress);

        // Summarize
        $report = $this->buildReport($checks);

        // Render
        $this->renderReport($report, $format);
        if ($format !== 'json') {
            $this->renderProblems($report);
        }

        // Optional save
        if ($output !== '') {
            $this->saveReport($report, $output, $format);
            $this->line("Saved: {$output}");
        }

        // Exit policy
        $failOn = (string)($this->option('fail-on') ?: 'error');
        return $this->exitCodeByPolicy($report, $failOn);
    }

    private function parseListOption(?string $value, array $default): array
    {
        $value = trim((string)$value);
        if ($value === '') return $default;

        return array_values(array_filter(array_map(
            fn($x) => trim($x),
            explode(',', $value)
        ), fn($x) => $x !== ''));
    }

    private function normalizeDirs(array $dirs, string $root): array
    {
        $out = [];
        foreach ($dirs as $dir) {
            $dir = str_replace(['\\', '//'], ['/', '/'], $dir);
            $dir = ltrim($dir, '/');
            $abs = $root . '/' . $dir;
            if (is_dir($abs)) {
                $out[] = $dir;
            }
        }
        return array_values(array_unique($out));
    }

    private function pathsForTool(array $paths, array $exclude): array
    {
        // For tools that accept paths as args. Excludes handled by config usually,
        // but we also skip passing excluded roots as scan targets.
        return array_values(array_filter($paths, function (string $p) use ($exclude) {
            foreach ($exclude as $ex) {
                if ($p === $ex || str_starts_with($p . '/', $ex . '/')) {
                    return false;
                }
            }
            return true;
        }));
    }

    private function check(string $id, string $title, ?array $cmd, int $timeout, bool $stream = false, ?string $notInstalledHint = null): array
    {
        if ($cmd === null) {
            return [
                'id' => $id,
                'title' => $title,
                'status' => 'skip',
                'exit_code' => null,
                'duration_ms' => 0,
                'summary' => 'Skipped (tool not installed)',
                'hint' => $notInstalledHint,
                'output' => '',
            ];
        }

        $this->line("\n▶ {$title}");
        $start = hrtime(true);
        $proc = new Process($cmd, base_path(), null, null, $timeout);

        $collected = '';
        $proc->run(function (string $type, string $buffer) use ($stream, &$collected): void {
            // Stream live output for better visibility
            if ($stream) {
                $this->output->write($buffer);
            }

            // Always collect (limited later)
            $collected .= $buffer;
        });
        $duration = (int)((hrtime(true) - $start) / 1_000_000);

        $exit = $proc->getExitCode();
        $out = trim($collected);

        $status = ($exit === 0) ? 'ok' : 'error';

        $mark = $status === 'ok' ? '✅' : '❌';
        $this->line("{$mark} {$title} ({$duration} ms)");

        return [
            'id' => $id,
            'title' => $title,
            'status' => $status,
            'exit_code' => $exit,
            'duration_ms' => $duration,
            'summary' => $status === 'ok' ? 'OK' : 'Failed',
            'hint' => null,
            'output' => mb_substr($out, 0, 20000), // prevent huge logs
        ];
    }

    private function smellsCheck(array $paths, array $exclude, bool $showProgress = false): array
    {
        $start = hrtime(true);

        $this->line("\n▶ Quick smells (scan)");

        $patterns = [
            // risky
            ['name' => 'dd()/dump()/die()', 'regex' => '/\b(dd|dump|die)\s*\(/'],
            ['name' => 'var_dump/print_r', 'regex' => '/\b(var_dump|print_r)\s*\(/'],
            ['name' => 'TODO/FIXME', 'regex' => '/\b(TODO|FIXME)\b/'],
            ['name' => '@phpstan-ignore', 'regex' => '/@phpstan-ignore/'],
            ['name' => 'Suppress errors (@)', 'regex' => '/(^|[^a-zA-Z0-9_])@([a-zA-Z_]+)\s*\(/'],
        ];

        $hits = [];
        $scanned = 0;

        foreach ($this->iteratePhpFiles($paths, $exclude) as $file) {
            $scanned++;
            if ($showProgress && ($this->output->isVeryVerbose() || $scanned % 25 === 0)) {
                $this->line('  • ' . str_replace(base_path() . '/', '', $file));
            }

            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $lines = preg_split("/\R/", $content);
            if (!is_array($lines)) {
                continue;
            }
            $relativeFile = str_replace(base_path() . '/', '', $file);
            for ($i = 0; $i < count($lines); $i++) {
                $lineText = $lines[$i];
                foreach ($patterns as $p) {
                    if (preg_match($p['regex'], $lineText, $matches, PREG_OFFSET_CAPTURE)) {
                        $pos = isset($matches[0][1]) ? $matches[0][1] : null;
                        // Try to detect nearest function/class in the same file
                        $symbol = null;
                        if (strpos($lineText, 'function') !== false) {
                            if (preg_match('/function\s+([A-Za-z0-9_]+)\s*\(/', $lineText, $fnMatch)) {
                                $symbol = 'function ' . $fnMatch[1];
                            }
                        } elseif (strpos($lineText, 'class') !== false) {
                            if (preg_match('/class\s+([A-Za-z0-9_]+)/', $lineText, $classMatch)) {
                                $symbol = 'class ' . $classMatch[1];
                            }
                        }
                        $excerpt = mb_strimwidth(trim($lineText), 0, 180, '...');
                        $hit = [
                            'file' => $relativeFile,
                            'line' => $i + 1,
                            'pos' => $pos,
                            'symbol' => $symbol,
                            'rule' => $p['name'],
                            'excerpt' => $excerpt,
                        ];
                        $hits[] = $hit;
                        // Print each hit immediately
                        $msg = '    ⚠ ' . $hit['rule'] . ' -> ' . $hit['file'] . ':' . $hit['line'];
                        if ($symbol) {
                            $msg .= " ({$symbol})";
                        }
                        $this->warn($msg);
                    }
                }
            }
        }

        $duration = (int)((hrtime(true) - $start) / 1_000_000);

        $status = empty($hits) ? 'ok' : 'warning';

        $mark = $status === 'ok' ? '✅' : '⚠️';
        $this->line("{$mark} Quick smells (scan) ({$duration} ms), scanned={$scanned}, hits=" . count($hits));

        return [
            'id' => 'smells',
            'title' => 'Quick smells (grep)',
            'status' => $status,
            'exit_code' => $status === 'ok' ? 0 : 1,
            'duration_ms' => $duration,
            'summary' => empty($hits)
                ? ('No smells found (scanned: ' . $scanned . ')')
                : ('Found: ' . count($hits) . ' (scanned: ' . $scanned . ')'),
            'hint' => 'This is heuristic, review hits manually.',
            'output' => json_encode(array_slice($hits, 0, 2000), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Render a detailed problems section for each check with issues.
     */
    private function renderProblems(array $report): void
    {
        // Build issues per check
        $checks = $report['checks'] ?? [];
        $allIssues = [];
        foreach ($checks as $check) {
            $parsed = $this->parseIssuesForCheck($check);
            if (!empty($parsed)) {
                $allIssues[$check['title'] ?? $check['id']] = $parsed;
            }
        }
        if (empty($allIssues)) {
            $this->line("\nNo issues details found.");
            return;
        }
        $this->line("\n=== Problems (grouped) ===");
        foreach ($allIssues as $title => $issues) {
            $this->line("\n[{$title}]");
            $rows = [];
            $count = 0;
            foreach ($issues as $issue) {
                if ($count >= 300) break;
                $rows[] = [
                    'File' => $issue['file'] ?? '',
                    'Line' => $issue['line'] ?? '',
                    'Pos' => $issue['pos'] ?? '',
                    'Symbol' => $issue['symbol'] ?? '',
                    'Message' => $issue['message'] ?? '',
                ];
                $count++;
            }
            $this->table(['File', 'Line', 'Pos', 'Symbol', 'Message'], $rows);
        }
    }

    /**
     * Helper to parse issues for a check, normalizing to array of:
     * file, line, pos, symbol, message
     */
    private function parseIssuesForCheck(array $check): array
    {
        $id = $check['id'] ?? '';
        $output = $check['output'] ?? '';
        $issues = [];
        $base = base_path() . '/';
        if ($id === 'smells') {
            // Output is JSON array of hits with file, line, pos, symbol, rule, excerpt
            $arr = json_decode($output, true);
            if (is_array($arr)) {
                foreach ($arr as $hit) {
                    $issues[] = [
                        'file' => isset($hit['file']) ? (str_starts_with($hit['file'], $base) ? substr($hit['file'], strlen($base)) : $hit['file']) : '',
                        'line' => $hit['line'] ?? null,
                        'pos' => $hit['pos'] ?? null,
                        'symbol' => $hit['symbol'] ?? null,
                        'message' => $hit['rule'] ?? '',
                    ];
                }
            }
        } elseif ($id === 'phpstan') {
            // Parse lines: file:line: message
            $lines = preg_split('/\R/', $output);
            foreach ($lines as $line) {
                if (preg_match('/^(.+?):(\d+):\s*(.+)$/', $line, $m)) {
                    $file = $m[1];
                    $lineNum = (int)$m[2];
                    $msg = $m[3];
                    $symbol = null;
                    // Try to extract Class::method
                    if (preg_match('#\b([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)::([A-Za-z_][A-Za-z0-9_]*)\b#', $msg, $sm)) {
                        $symbol = $sm[1] . '::' . $sm[2];
                    }
                    $fileRel = str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
                    $issues[] = [
                        'file' => $fileRel,
                        'line' => $lineNum,
                        'pos' => null,
                        'symbol' => $symbol,
                        'message' => $msg,
                    ];
                }
            }
        } elseif ($id === 'pint') {
            // Parse lines that look like a path ending with .php
            $lines = preg_split('/\R/', $output);
            foreach ($lines as $line) {
                $trim = trim($line);
                if (preg_match('/^[\.\/\w-]+\.php$/', $trim)) {
                    $fileRel = str_starts_with($trim, $base) ? substr($trim, strlen($base)) : $trim;
                    $issues[] = [
                        'file' => $fileRel,
                        'line' => null,
                        'pos' => null,
                        'symbol' => null,
                        'message' => 'Formatting differs',
                    ];
                }
            }
        } elseif ($id === 'phpunit') {
            // Stack trace lines: at ... (file:line)
            // Also lines: #n ... (file:line)
            $lines = preg_split('/\R/', $output);
            $seen = [];
            $picked = 0;
            foreach ($lines as $line) {
                if (preg_match('/^\s*at\s+.*\((.+?):(\d+)\)$/', $line, $m)) {
                    $file = $m[1];
                    $lineNum = (int)$m[2];
                    $key = $file . ':' . $lineNum;
                    if (!isset($seen[$key]) && $picked < 50) {
                        $seen[$key] = true;
                        $fileRel = str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
                        $issues[] = [
                            'file' => $fileRel,
                            'line' => $lineNum,
                            'pos' => null,
                            'symbol' => null,
                            'message' => 'Test failure/exception (see output)',
                        ];
                        $picked++;
                    }
                } elseif (preg_match('/^\s*#\d+\s+(.+?)\((.+?):(\d+)\)/', $line, $m)) {
                    $file = $m[2];
                    $lineNum = (int)$m[3];
                    $key = $file . ':' . $lineNum;
                    if (!isset($seen[$key]) && $picked < 50) {
                        $seen[$key] = true;
                        $fileRel = str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
                        $issues[] = [
                            'file' => $fileRel,
                            'line' => $lineNum,
                            'pos' => null,
                            'symbol' => null,
                            'message' => 'Test failure/exception (see output)',
                        ];
                        $picked++;
                    }
                }
            }
        } else {
            // Generic: file:line: message
            $lines = preg_split('/\R/', $output);
            foreach ($lines as $line) {
                if (preg_match('/^(.+?):(\d+):\s*(.+)$/', $line, $m)) {
                    $file = $m[1];
                    $lineNum = (int)$m[2];
                    $msg = $m[3];
                    $fileRel = str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
                    $issues[] = [
                        'file' => $fileRel,
                        'line' => $lineNum,
                        'pos' => null,
                        'symbol' => null,
                        'message' => $msg,
                    ];
                }
            }
        }
        return $issues;
    }

    private function iteratePhpFiles(array $paths, array $exclude): \Generator
    {
        $root = base_path();

        $excludeAbs = array_map(fn($p) => $root . '/' . $p, $exclude);

        foreach ($paths as $path) {
            $abs = $root . '/' . $path;
            if (!is_dir($abs)) continue;

            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) continue;

                $real = $file->getRealPath();
                if (!$real) continue;

                // skip excluded
                foreach ($excludeAbs as $ex) {
                    if (str_starts_with($real, $ex . DIRECTORY_SEPARATOR) || $real === $ex) {
                        continue 2;
                    }
                }

                if (str_ends_with($real, '.php')) {
                    yield $real;
                }
            }
        }
    }

    private function buildReport(array $checks): array
    {
        $errors = 0;
        $warnings = 0;

        foreach ($checks as $c) {
            if (($c['status'] ?? '') === 'error') $errors++;
            if (($c['status'] ?? '') === 'warning') $warnings++;
        }

        return [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'project' => base_path(),
            ],
            'summary' => [
                'checks' => count($checks),
                'errors' => $errors,
                'warnings' => $warnings,
            ],
            'checks' => $checks,
        ];
    }

    private function renderReport(array $report, string $format): void
    {
        if ($format === 'json') {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        if ($format === 'md') {
            $this->line($this->reportToMarkdown($report));
            return;
        }

        // table
        $rows = [];
        foreach ($report['checks'] as $c) {
            $rows[] = [
                'Check' => $c['title'],
                'Status' => $c['status'],
                'Time(ms)' => (string)$c['duration_ms'],
                'Summary' => $c['summary'],
            ];
        }

        $this->info("Checks: {$report['summary']['checks']}, Errors: {$report['summary']['errors']}, Warnings: {$report['summary']['warnings']}");
        $this->table(['Check', 'Status', 'Time(ms)', 'Summary'], $rows);

        // show hints for skipped tools
        foreach ($report['checks'] as $c) {
            if (($c['status'] ?? '') === 'skip' && !empty($c['hint'])) {
                $this->line(" - {$c['title']}: {$c['hint']}");
            }
        }
    }

    private function saveReport(array $report, string $output, string $format): void
    {
        $ext = strtolower(pathinfo($output, PATHINFO_EXTENSION));

        if ($ext === 'json' || $format === 'json') {
            file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        // markdown default
        file_put_contents($output, $this->reportToMarkdown($report));
    }

    private function reportToMarkdown(array $report): string
    {
        $lines = [];
        $lines[] = '# Project Doctor Report';
        $lines[] = '';
        $lines[] = '- Generated: ' . ($report['meta']['generated_at'] ?? '');
        $lines[] = '- Project: ' . ($report['meta']['project'] ?? '');
        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '- Checks: ' . ($report['summary']['checks'] ?? 0);
        $lines[] = '- Errors: ' . ($report['summary']['errors'] ?? 0);
        $lines[] = '- Warnings: ' . ($report['summary']['warnings'] ?? 0);
        $lines[] = '';
        $lines[] = '## Checks';
        $lines[] = '';

        foreach ($report['checks'] as $c) {
            $lines[] = '### ' . ($c['title'] ?? $c['id']);
            $lines[] = '';
            $lines[] = '- Status: ' . ($c['status'] ?? '');
            $lines[] = '- Time(ms): ' . ($c['duration_ms'] ?? 0);
            $lines[] = '- Summary: ' . ($c['summary'] ?? '');
            if (!empty($c['hint'])) {
                $lines[] = '- Hint: ' . $c['hint'];
            }
            if (!empty($c['output'])) {
                $lines[] = '';
                $lines[] = '```';
                $lines[] = (string)$c['output'];
                $lines[] = '```';
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function exitCodeByPolicy(array $report, string $policy): int
    {
        $errors = (int)Arr::get($report, 'summary.errors', 0);
        $warnings = (int)Arr::get($report, 'summary.warnings', 0);

        return match ($policy) {
            'none' => self::SUCCESS,
            'warning' => ($errors > 0 || $warnings > 0) ? self::FAILURE : self::SUCCESS,
            default => ($errors > 0) ? self::FAILURE : self::SUCCESS, // error
        };
    }
}
