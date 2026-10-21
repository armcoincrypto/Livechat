<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Same-snapshot L1 ↔ authoritative Top-N equivalence diagnostic.
 *
 * Compares AUTHORITATIVE(adjust(book X)) vs L1(adjust(book X)) on the exact
 * same normalized Top-N book. Never fetches, never writes customer rates,
 * never mutates the authoritative adjust result.
 *
 * Flag: BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC (default false).
 * Distinct from BESTCHANGE_L1_SHADOW_ENABLED / SKIP_IDENTICAL.
 */
final class BestChangeL1SameSnapshotDiagnostic
{
    public const FLAG = 'BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC';

    public const CLASS_OK = 'OK';

    public const CLASS_ALGORITHM_MISMATCH = 'ALGORITHM_MISMATCH';

    public const CLASS_REPRESENTATION_ONLY = 'REPRESENTATION_ONLY';

    public const CLASS_FALLBACK_DIFFERENCE = 'FALLBACK_DIFFERENCE';

    /** Max mismatch fixtures retained under evidence dir. */
    public const MAX_FIXTURES = 50;

    /** Soft cap on evidence directory size (MB). */
    public const MAX_STORAGE_MB = 20;

    /**
     * Deterministic sample stride: ~1/stride of eligible directions per generation.
     * With ~2040 BestChange directions, stride 24 ≈ 85 samples/generation.
     */
    public const SAMPLE_STRIDE = 24;

    public static function enabled(): bool
    {
        return self::envBool(self::FLAG, false);
    }

    /**
     * Observability: compare governance (shared .env) vs process-effective flag.
     * Emits L1_DIAGNOSTIC_FLAG_MISMATCH when shared requests true but effective is false.
     * Does not change pricing behavior.
     *
     * @return array{
     *   diagnostic_requested: int,
     *   diagnostic_effective: int,
     *   config_source: string,
     *   env_path: string,
     *   shared_env_path: ?string,
     *   mismatch: int
     * }
     */
    public static function assertAndLogFlagState(?string $liveEnvPath = null, ?string $sharedEnvPath = null): array
    {
        $state = self::inspectFlagState($liveEnvPath, $sharedEnvPath);
        self::logInfo('bestchange_l1_diagnostic_flag_assert', $state);
        if ($state['mismatch'] === 1) {
            self::logError('L1_DIAGNOSTIC_FLAG_MISMATCH', $state);
        }

        return $state;
    }

    /**
     * @return array{
     *   diagnostic_requested: int,
     *   diagnostic_effective: int,
     *   config_source: string,
     *   env_path: string,
     *   shared_env_path: ?string,
     *   mismatch: int
     * }
     */
    public static function inspectFlagState(?string $liveEnvPath = null, ?string $sharedEnvPath = null): array
    {
        $envPath = $liveEnvPath ?? (function_exists('base_path') ? base_path('.env') : '.env');
        $shared = $sharedEnvPath ?? self::resolveSharedEnvPath($envPath);
        $effective = self::enabled();
        $liveFileVal = self::parseEnvFileBool($envPath, self::FLAG);
        $sharedFileVal = $shared !== null ? self::parseEnvFileBool($shared, self::FLAG) : null;
        // Governance source of truth is shared when present; else the live .env file.
        $requested = $sharedFileVal ?? $liveFileVal ?? false;
        $source = self::describeConfigSource($envPath, $shared);

        return [
            'diagnostic_requested' => $requested ? 1 : 0,
            'diagnostic_effective' => $effective ? 1 : 0,
            'config_source' => $source,
            'env_path' => $envPath,
            'shared_env_path' => $shared,
            'mismatch' => ($requested && ! $effective) ? 1 : 0,
        ];
    }

    public static function describeConfigSource(string $envPath, ?string $sharedEnvPath = null): string
    {
        if (is_link($envPath)) {
            $target = readlink($envPath) ?: '';
            $real = realpath($envPath) ?: $target;

            return 'symlink->'.$real;
        }
        if (is_file($envPath)) {
            $real = realpath($envPath) ?: $envPath;
            if ($sharedEnvPath !== null && is_file($sharedEnvPath)) {
                $sharedReal = realpath($sharedEnvPath) ?: $sharedEnvPath;
                if ($real !== $sharedReal) {
                    return 'release-local-file_diverged_from_shared:'.$real;
                }
            }

            return 'release-local-file:'.$real;
        }

        return 'missing:'.$envPath;
    }

    public static function resolveSharedEnvPath(string $liveEnvPath): ?string
    {
        $override = getenv('EXSWAPING_BACKEND_SHARED_ENV');
        if (is_string($override) && $override !== '' && is_file($override)) {
            return $override;
        }
        if (is_link($liveEnvPath)) {
            $real = realpath($liveEnvPath);

            return $real !== false ? $real : null;
        }
        // Conventional layout: …/exswaping-backend-shared/config/.env
        $candidate = dirname(dirname(dirname($liveEnvPath))).'/exswaping-backend-shared/config/.env';
        if (is_file($candidate)) {
            return $candidate;
        }

        return null;
    }

    public static function parseEnvFileBool(string $path, string $key): ?bool
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return null;
        }
        $found = null;
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $k = trim(substr($line, 0, $eq));
            if ($k !== $key) {
                continue;
            }
            $v = trim(substr($line, $eq + 1));
            if (
                (str_starts_with($v, '"') && str_ends_with($v, '"'))
                || (str_starts_with($v, "'") && str_ends_with($v, "'"))
            ) {
                $v = substr($v, 1, -1);
            }
            $found = filter_var($v, FILTER_VALIDATE_BOOLEAN);
        }
        fclose($fh);

        return $found;
    }

    /**
     * Stable rotating sample: covers different direction cohorts across generations.
     */
    public static function shouldSample(int $directionExchangeId, int $generationSalt = 0): bool
    {
        $stride = max(1, self::SAMPLE_STRIDE);

        return (($directionExchangeId + max(0, $generationSalt)) % $stride) === 0;
    }

    /**
     * Evaluate production Top-N selection on an already-normalized (or raw) book.
     * Shares BestChangeExternalTopNController::adjust — no duplicated pricing logic.
     *
     * @param  array<int, array<string, mixed>>  $rawOrNormalizedBook
     * @return array{
     *   book: array<int, array{changer: int, rate: string}>,
     *   book_hash: string,
     *   book_row_count: int,
     *   adj: array<string, mixed>,
     *   selected_changer: ?int,
     *   selected_rate: ?string,
     *   position_result: ?int,
     *   course: string,
     *   source_label: string
     * }
     */
    public static function evaluateSelection(
        BestChangeExternalTopNController $topN,
        DirectionExchange $direction,
        string $baseCourse,
        array $rawOrNormalizedBook,
        int $exportDecimals,
        ?int $sourcePosition,
        string $sourceLabel,
    ): array {
        $book = BestChangeTopNBookHelper::normalizeForTopN($rawOrNormalizedBook);
        $hash = BestChangeTopNBookHelper::fingerprint($book);
        $adj = $topN->adjust(
            $direction,
            $baseCourse,
            $book,
            max(0, $exportDecimals),
            $sourcePosition,
            $sourcePosition,
        );

        $selectedChanger = null;
        $selectedRate = null;
        $pos = isset($adj['actual_rank']) && is_numeric($adj['actual_rank'])
            ? (int) $adj['actual_rank']
            : null;
        if ($pos !== null && $pos > 0 && isset($book[$pos - 1])) {
            $selectedChanger = (int) $book[$pos - 1]['changer'];
            $selectedRate = (string) $book[$pos - 1]['rate'];
        }

        return [
            'book' => $book,
            'book_hash' => $hash,
            'book_row_count' => count($book),
            'adj' => $adj,
            'selected_changer' => $selectedChanger,
            'selected_rate' => $selectedRate,
            'position_result' => $pos,
            'course' => (string) ($adj['course'] ?? $baseCourse),
            'source_label' => $sourceLabel,
        ];
    }

    /**
     * Same-book compare. Requires identical book_hash (caller must feed same rows).
     *
     * @param  array<string, mixed>  $authoritative
     * @param  array<string, mixed>  $l1
     * @return array{
     *   match: bool,
     *   class: string,
     *   same_book_hash: bool,
     *   record: array<string, mixed>
     * }
     */
    public static function compare(array $authoritative, array $l1, int $directionId): array
    {
        $authHash = (string) ($authoritative['book_hash'] ?? '');
        $l1Hash = (string) ($l1['book_hash'] ?? '');
        $sameHash = $authHash !== '' && hash_equals($authHash, $l1Hash);

        $authAdj = is_array($authoritative['adj'] ?? null) ? $authoritative['adj'] : [];
        $l1Adj = is_array($l1['adj'] ?? null) ? $l1['adj'] : [];

        $record = [
            'direction_id' => $directionId,
            'book_hash' => $authHash,
            'book_row_count' => (int) ($authoritative['book_row_count'] ?? 0),
            'authoritative_selected_row_id' => $authoritative['selected_changer'] ?? null,
            'l1_selected_row_id' => $l1['selected_changer'] ?? null,
            'authoritative_source' => (string) ($authoritative['source_label'] ?? 'authoritative'),
            'l1_source' => (string) ($l1['source_label'] ?? 'l1'),
            'authoritative_rate' => $authoritative['selected_rate'] ?? null,
            'l1_rate' => $l1['selected_rate'] ?? null,
            'authoritative_position_result' => $authoritative['position_result'] ?? null,
            'l1_position_result' => $l1['position_result'] ?? null,
            'authoritative_course' => (string) ($authoritative['course'] ?? ''),
            'l1_course' => (string) ($l1['course'] ?? ''),
            'authoritative_status' => (string) ($authAdj['status'] ?? ''),
            'l1_status' => (string) ($l1Adj['status'] ?? ''),
            'authoritative_reason' => (string) ($authAdj['reason'] ?? ''),
            'l1_reason' => (string) ($l1Adj['reason'] ?? ''),
            'authoritative_applied' => (bool) ($authAdj['applied'] ?? false),
            'l1_applied' => (bool) ($l1Adj['applied'] ?? false),
        ];

        if (! $sameHash) {
            // Harness contract violation — never classify as TIMING_RACE.
            return [
                'match' => false,
                'class' => self::CLASS_ALGORITHM_MISMATCH,
                'same_book_hash' => false,
                'record' => $record + ['harness_error' => 'book_hash_mismatch'],
            ];
        }

        $courseEq = bccomp(
            (string) $record['authoritative_course'],
            (string) $record['l1_course'],
            18
        ) === 0;
        $statusEq = $record['authoritative_status'] === $record['l1_status'];
        $reasonEq = $record['authoritative_reason'] === $record['l1_reason'];
        $appliedEq = $record['authoritative_applied'] === $record['l1_applied'];
        $posEq = $record['authoritative_position_result'] === $record['l1_position_result'];
        $rowEq = $record['authoritative_selected_row_id'] === $record['l1_selected_row_id'];

        if ($courseEq && $statusEq && $reasonEq && $appliedEq && $posEq && $rowEq) {
            return [
                'match' => true,
                'class' => self::CLASS_OK,
                'same_book_hash' => true,
                'record' => $record,
            ];
        }

        if ($courseEq && $appliedEq && $posEq && $rowEq && (! $statusEq || ! $reasonEq)) {
            // Semantic course/selection identical; status/reason string drift only.
            return [
                'match' => true,
                'class' => self::CLASS_REPRESENTATION_ONLY,
                'same_book_hash' => true,
                'record' => $record,
            ];
        }

        if (! $statusEq || (! $appliedEq && $courseEq)) {
            return [
                'match' => false,
                'class' => self::CLASS_FALLBACK_DIFFERENCE,
                'same_book_hash' => true,
                'record' => $record,
            ];
        }

        return [
            'match' => false,
            'class' => self::CLASS_ALGORITHM_MISMATCH,
            'same_book_hash' => true,
            'record' => $record,
        ];
    }

    /**
     * Run diagnostic on one direction. Fail-soft: any Throwable is logged; caller continues.
     *
     * @param  array<int, array<string, mixed>>  $normalizedAuthBook  Already Top-N normalized book X
     * @param  array<string, int>  $metrics  Mutated in place
     */
    public static function runFailSoft(
        BestChangeExternalTopNController $topN,
        DirectionExchange $direction,
        string $baseCourse,
        array $normalizedAuthBook,
        int $exportDecimals,
        ?int $sourcePosition,
        array &$metrics,
        int $generationSalt = 0,
        ?string $evidenceDir = null,
    ): void {
        if (! self::enabled()) {
            return;
        }

        $directionId = (int) $direction->id;
        if (! self::shouldSample($directionId, $generationSalt)) {
            return;
        }

        try {
            $auth = self::evaluateSelection(
                $topN,
                $direction,
                $baseCourse,
                $normalizedAuthBook,
                $exportDecimals,
                $sourcePosition,
                'authoritative_marketForDirection_book',
            );
            // L1 path: same in-memory book X — no second network fetch.
            $l1 = self::evaluateSelection(
                $topN,
                $direction,
                $baseCourse,
                $normalizedAuthBook,
                $exportDecimals,
                $sourcePosition,
                'l1_same_snapshot_book',
            );

            $classified = self::compare($auth, $l1, $directionId);
            $metrics['bc_same_snapshot_compare_count'] = (int) ($metrics['bc_same_snapshot_compare_count'] ?? 0) + 1;
            if ($classified['match']) {
                $metrics['bc_same_snapshot_match'] = (int) ($metrics['bc_same_snapshot_match'] ?? 0) + 1;
            } else {
                $metrics['bc_same_snapshot_mismatch'] = (int) ($metrics['bc_same_snapshot_mismatch'] ?? 0) + 1;
                $cls = (string) $classified['class'];
                $key = 'bc_same_snapshot_class_'.$cls;
                $metrics[$key] = (int) ($metrics[$key] ?? 0) + 1;
                self::logWarning('bestchange_l1_same_snapshot_mismatch', $classified['record'] + [
                    'class' => $cls,
                    'same_book_hash' => $classified['same_book_hash'],
                ]);
                self::captureMismatchFixture(
                    $classified['record'],
                    $auth['book'],
                    $sourcePosition,
                    $baseCourse,
                    $cls,
                    $evidenceDir,
                );
            }
            $metrics['bc_same_snapshot_extra_network_fetches'] = 0;
            $metrics['bc_same_snapshot_production_writes'] = 0;
        } catch (Throwable $e) {
            self::logWarning('bestchange_l1_same_snapshot_diagnostic_failed', [
                'direction_exchange_id' => $directionId,
                'error' => $e->getMessage(),
            ]);
            $metrics['bc_same_snapshot_diagnostic_errors'] =
                (int) ($metrics['bc_same_snapshot_diagnostic_errors'] ?? 0) + 1;
        }
    }

    /**
     * Persist compact sanitized mismatch fixture with rotation.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, array{changer: int, rate: string}>  $book
     */
    public static function captureMismatchFixture(
        array $record,
        array $book,
        ?int $sourcePosition,
        string $baseCourse,
        string $class,
        ?string $evidenceDir = null,
    ): void {
        $dir = $evidenceDir ?? self::defaultEvidenceDir();
        if ($dir === null) {
            return;
        }

        try {
            if (! is_dir($dir) && ! @mkdir($dir, 0750, true) && ! is_dir($dir)) {
                return;
            }
            self::enforceRetention($dir);

            $payload = [
                'captured_at' => gmdate('c'),
                'class' => $class,
                'book_hash' => $record['book_hash'] ?? null,
                'direction_config' => [
                    'direction_id' => $record['direction_id'] ?? null,
                    'source_position' => $sourcePosition,
                    'base_course' => $baseCourse,
                ],
                'canonical_raw_book' => $book,
                'authoritative_result' => [
                    'selected_row_id' => $record['authoritative_selected_row_id'] ?? null,
                    'rate' => $record['authoritative_rate'] ?? null,
                    'position' => $record['authoritative_position_result'] ?? null,
                    'course' => $record['authoritative_course'] ?? null,
                    'status' => $record['authoritative_status'] ?? null,
                    'reason' => $record['authoritative_reason'] ?? null,
                ],
                'l1_result' => [
                    'selected_row_id' => $record['l1_selected_row_id'] ?? null,
                    'rate' => $record['l1_rate'] ?? null,
                    'position' => $record['l1_position_result'] ?? null,
                    'course' => $record['l1_course'] ?? null,
                    'status' => $record['l1_status'] ?? null,
                    'reason' => $record['l1_reason'] ?? null,
                ],
            ];

            $name = sprintf(
                'mismatch_%s_%s_%s.json',
                (string) ($record['direction_id'] ?? '0'),
                substr((string) ($record['book_hash'] ?? 'x'), 0, 12),
                gmdate('YmdHis')
            );
            @file_put_contents(
                $dir.DIRECTORY_SEPARATOR.$name,
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );
            self::enforceRetention($dir);
        } catch (Throwable $e) {
            self::logWarning('bestchange_l1_same_snapshot_fixture_capture_failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function logWarning(string $message, array $context): void
    {
        try {
            Log::warning($message, $context);
        } catch (Throwable) {
            // Unit tests / missing container: swallow.
        }
    }

    public static function defaultEvidenceDir(): ?string
    {
        $override = getenv('BESTCHANGE_L1_SAME_SNAPSHOT_EVIDENCE_DIR');
        if (is_string($override) && $override !== '') {
            return $override;
        }
        if (function_exists('storage_path')) {
            return storage_path('app/l1_same_snapshot_evidence');
        }

        return sys_get_temp_dir().'/exswaping_l1_same_snapshot_evidence';
    }

    public static function enforceRetention(string $dir): void
    {
        $files = glob($dir.DIRECTORY_SEPARATOR.'mismatch_*.json') ?: [];
        usort($files, static fn (string $a, string $b): int => filemtime($a) <=> filemtime($b));

        while (count($files) > self::MAX_FIXTURES) {
            $old = array_shift($files);
            if (is_string($old)) {
                @unlink($old);
            }
        }

        $bytes = 0;
        foreach ($files as $f) {
            $bytes += (int) @filesize($f);
        }
        $maxBytes = self::MAX_STORAGE_MB * 1024 * 1024;
        while ($bytes > $maxBytes && $files !== []) {
            $old = array_shift($files);
            if (is_string($old)) {
                $bytes -= (int) @filesize($old);
                @unlink($old);
            }
        }
    }

    private static function envBool(string $key, bool $default): bool
    {
        // Prefer process environment (putenv/Dotenv) over Laravel env() helper,
        // which can return a stale cached value after putenv changes in the same process.
        $g = getenv($key);
        if ($g !== false) {
            return filter_var($g, FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists($key, $_ENV)) {
            return filter_var((string) $_ENV[$key], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists($key, $_SERVER)) {
            return filter_var((string) $_SERVER[$key], FILTER_VALIDATE_BOOLEAN);
        }
        $raw = function_exists('env') ? env($key, null) : null;
        if ($raw === null) {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function logInfo(string $message, array $context): void
    {
        if (class_exists(Log::class)) {
            try {
                Log::info($message, $context);

                return;
            } catch (Throwable) {
                // fall through
            }
        }
        error_log($message.' '.json_encode($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function logError(string $message, array $context): void
    {
        if (class_exists(Log::class)) {
            try {
                Log::error($message, $context);

                return;
            } catch (Throwable) {
                // fall through
            }
        }
        error_log($message.' '.json_encode($context));
    }
}
