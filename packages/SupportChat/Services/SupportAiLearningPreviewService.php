<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiLearningCandidate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Builds read-only preview packages from staged/approved learning candidates.
 * Never modifies production prompts, playbook code, or draft services.
 */
final class SupportAiLearningPreviewService
{
    public const PREVIEW_DIR = 'support-ai/previews';

    public const PREVIEW_JSON = 'latest-preview.json';

    public const PREVIEW_MD = 'latest-preview.md';

    /** @var list<string> */
    private const HARD_FAIL_FLAGS = [
        'fake_eta',
        'fake_confirmation',
        'guarantee_claim',
        'funds_safe_claim',
        'recovery_guarantee',
        'asks_known_data_again',
        'unsupported_financial_claim',
        'secret_leak_risk',
        'too_long',
        'wrong_language',
    ];

    public function __construct(
        private readonly SupportAiLearningEvaluationService $evaluation,
        private readonly SupportAiLearningAnalyzer $analyzer,
        private readonly SupportAiLearningService $learning,
    ) {}

    public function isAvailable(): bool
    {
        return Schema::hasTable('support_ai_learning_candidates');
    }

    public function previewDirectory(): string
    {
        return storage_path('app/'.self::PREVIEW_DIR);
    }

    public function previewJsonPath(): string
    {
        return $this->previewDirectory().'/'.self::PREVIEW_JSON;
    }

    public function previewMarkdownPath(): string
    {
        return $this->previewDirectory().'/'.self::PREVIEW_MD;
    }

    /**
     * @return Collection<int, SupportAiLearningCandidate>
     */
    public function loadApprovedCandidates(): Collection
    {
        return SupportAiLearningCandidate::query()
            ->where('status', SupportAiLearningCandidate::STATUS_APPROVED)
            ->orderBy('intent')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, SupportAiLearningCandidate>
     */
    public function loadStagedCandidates(): Collection
    {
        return SupportAiLearningCandidate::query()
            ->where('status', SupportAiLearningCandidate::STATUS_STAGED)
            ->orderBy('intent')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, SupportAiLearningCandidate>
     */
    public function loadPreviewableCandidates(): Collection
    {
        $candidates = SupportAiLearningCandidate::query()
            ->whereIn('status', [
                SupportAiLearningCandidate::STATUS_STAGED,
                SupportAiLearningCandidate::STATUS_APPROVED,
            ])
            ->orderBy('intent')
            ->orderBy('id')
            ->get();

        return $candidates->filter(function (SupportAiLearningCandidate $candidate): bool {
            return $this->validateCandidateForPreview($candidate) === null;
        })->values();
    }

    /**
     * @param  Collection<int, SupportAiLearningCandidate>  $candidates
     * @return array<string, list<array<string, mixed>>>
     */
    public function groupByIntent(Collection $candidates): array
    {
        $grouped = [];
        foreach ($candidates as $candidate) {
            $intent = trim((string) ($candidate->intent ?? ''));
            if ($intent === '') {
                continue;
            }
            if (! isset($grouped[$intent])) {
                $grouped[$intent] = [];
            }
            $grouped[$intent][] = $this->candidatePreviewRow($candidate);
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getCurrentPlaybookExamplesByIntent(): array
    {
        $out = [];
        foreach (SupportAiReplyPlaybook::builtInExamples() as $row) {
            $intent = (string) ($row['intent'] ?? 'unknown');
            $example = trim((string) ($row['example'] ?? ''));
            if ($example === '') {
                continue;
            }
            if (! isset($out[$intent])) {
                $out[$intent] = [];
            }
            $out[$intent][] = $example;
        }

        foreach ($out as $intent => $examples) {
            $out[$intent] = array_values(array_unique($examples));
        }

        ksort($out);

        return $out;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $groupedCandidates
     * @param  array<string, list<string>>  $currentExamples
     * @return list<array<string, mixed>>
     */
    public function buildPreviewPlaybook(array $groupedCandidates, array $currentExamples): array
    {
        $intents = array_unique(array_merge(array_keys($currentExamples), array_keys($groupedCandidates)));
        sort($intents);

        $preview = [];
        foreach ($intents as $intent) {
            $current = $currentExamples[$intent] ?? [];
            $rows = $groupedCandidates[$intent] ?? [];

            $proposedExamples = [];
            $proposedRules = [];
            foreach ($rows as $row) {
                if (($row['proposed_example'] ?? '') !== '') {
                    $proposedExamples[] = (string) $row['proposed_example'];
                }
                if (($row['proposed_rule'] ?? '') !== '') {
                    $proposedRules[] = (string) $row['proposed_rule'];
                }
            }

            $proposedExamples = array_values(array_unique($proposedExamples));
            $proposedRules = array_values(array_unique($proposedRules));

            $preview[] = [
                'intent' => $intent,
                'candidate_count' => count($rows),
                'current_examples' => $current,
                'current_example_count' => count($current),
                'proposed_examples' => $proposedExamples,
                'proposed_rules' => $proposedRules,
                'preview_example_count' => count($current) + count($proposedExamples),
            ];
        }

        return $preview;
    }

    /**
     * @param  list<array<string, mixed>>  $previewPlaybook
     * @return list<array{intent: string, block: string}>
     */
    public function buildPreviewPromptAdditions(array $previewPlaybook): array
    {
        $blocks = [];
        foreach ($previewPlaybook as $intentBlock) {
            $intent = (string) ($intentBlock['intent'] ?? '');
            $proposed = $intentBlock['proposed_examples'] ?? [];
            $rules = $intentBlock['proposed_rules'] ?? [];

            if ($proposed === [] && $rules === []) {
                continue;
            }

            $lines = ["[PREVIEW ONLY — intent: {$intent}]"];
            if ($rules !== []) {
                $lines[] = 'Proposed tone/safety rules:';
                foreach ($rules as $rule) {
                    $lines[] = '- '.$this->learning->sanitizeLearningText((string) $rule);
                }
            }
            if ($proposed !== []) {
                $lines[] = 'Proposed style examples:';
                foreach ($proposed as $example) {
                    $lines[] = '- '.$this->learning->sanitizeLearningText((string) $example);
                }
            }

            $blocks[] = [
                'intent' => $intent,
                'block' => implode("\n", $lines),
            ];
        }

        return $blocks;
    }

    /**
     * @param  Collection<int, SupportAiLearningCandidate>  $candidates
     * @return array<string, mixed>
     */
    public function buildImpactEstimate(Collection $candidates, int $analysisDays = 30): array
    {
        if ($candidates->isEmpty()) {
            return [
                'confidence' => 0.0,
                'expected_operator_adoption_pct' => null,
                'expected_rewrite_reduction_pct' => null,
                'safety_risk' => 'none',
                'high_risk_candidates' => 0,
                'average_overall_score' => null,
                'average_operator_fit_score' => null,
            ];
        }

        $overallScores = [];
        $operatorFitScores = [];
        $highRisk = 0;

        foreach ($candidates as $candidate) {
            if ($candidate->evaluation_score !== null) {
                $overallScores[] = (float) $candidate->evaluation_score;
            }
            $flags = is_array($candidate->evaluation_flags) ? $candidate->evaluation_flags : [];
            if (isset($flags['operator_fit_score'])) {
                $operatorFitScores[] = (float) $flags['operator_fit_score'];
            }
            if ((string) ($candidate->risk_level ?? '') === 'high') {
                $highRisk++;
            }
        }

        $avgOverall = $overallScores !== [] ? array_sum($overallScores) / count($overallScores) : null;
        $avgOperatorFit = $operatorFitScores !== [] ? array_sum($operatorFitScores) / count($operatorFitScores) : null;

        $analysis = $this->analyzer->analyzeRecentEvents($analysisDays);
        $usageRate = $analysis['usage_rate'];
        $rewriteRate = $analysis['rewrite_rate'];

        $expectedAdoption = $avgOperatorFit !== null
            ? round(min(95.0, max(5.0, $avgOperatorFit * 0.85)), 1)
            : ($usageRate !== null ? round(min(95.0, $usageRate + 8.0), 1) : null);

        $expectedRewriteReduction = $rewriteRate !== null && $avgOverall !== null
            ? round(min(40.0, max(0.0, ($avgOverall - 50.0) * 0.25)), 1)
            : null;

        $confidence = $avgOverall !== null ? round($avgOverall, 1) : 0.0;

        $safetyRisk = 'low';
        if ($highRisk > 0) {
            $safetyRisk = 'medium';
        }
        if ($highRisk >= max(1, (int) ceil($candidates->count() * 0.25))) {
            $safetyRisk = 'high';
        }

        return [
            'confidence' => $confidence,
            'expected_operator_adoption_pct' => $expectedAdoption,
            'expected_rewrite_reduction_pct' => $expectedRewriteReduction,
            'safety_risk' => $safetyRisk,
            'high_risk_candidates' => $highRisk,
            'average_overall_score' => $avgOverall !== null ? round($avgOverall, 2) : null,
            'average_operator_fit_score' => $avgOperatorFit !== null ? round($avgOperatorFit, 2) : null,
            'baseline_usage_rate_pct' => $usageRate,
            'baseline_rewrite_rate_pct' => $rewriteRate,
        ];
    }

    /**
     * @param  array<string, list<string>>  $currentExamples
     * @param  list<array<string, mixed>>  $previewPlaybook
     * @return array<string, mixed>
     */
    public function buildDiffSummary(array $currentExamples, array $previewPlaybook): array
    {
        $currentTotal = 0;
        foreach ($currentExamples as $examples) {
            $currentTotal += count($examples);
        }

        $addedExamples = 0;
        $addedRules = 0;
        $intentsAffected = [];

        foreach ($previewPlaybook as $block) {
            $added = count($block['proposed_examples'] ?? []);
            $rules = count($block['proposed_rules'] ?? []);
            if ($added > 0 || $rules > 0) {
                $intentsAffected[] = (string) $block['intent'];
            }
            $addedExamples += $added;
            $addedRules += $rules;
        }

        return [
            'current_example_count' => $currentTotal,
            'preview_example_count' => $currentTotal + $addedExamples,
            'added_examples' => $addedExamples,
            'added_rules' => $addedRules,
            'intents_affected' => array_values(array_unique($intentsAffected)),
            'intents_affected_count' => count(array_unique($intentsAffected)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generatePreview(int $analysisDays = 30): array
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('Learning candidates table is not available.');
        }

        $approved = $this->loadApprovedCandidates();
        $staged = $this->loadStagedCandidates();
        $previewable = $this->loadPreviewableCandidates();

        $skipped = [];
        $allCandidates = $approved->merge($staged)->unique('id');
        foreach ($allCandidates as $candidate) {
            $reason = $this->validateCandidateForPreview($candidate);
            if ($reason !== null) {
                $skipped[] = [
                    'id' => (int) $candidate->id,
                    'status' => $candidate->status,
                    'reason' => $reason,
                ];
            }
        }

        if ($previewable->isEmpty() && $skipped !== []) {
            throw new RuntimeException(
                'No previewable candidates. Skipped: '.json_encode(array_slice($skipped, 0, 5), JSON_UNESCAPED_UNICODE)
            );
        }

        $grouped = $this->groupByIntent($previewable);
        $current = $this->getCurrentPlaybookExamplesByIntent();
        $previewPlaybook = $this->buildPreviewPlaybook($grouped, $current);
        $promptAdditions = $this->buildPreviewPromptAdditions($previewPlaybook);
        $impact = $this->buildImpactEstimate($previewable, $analysisDays);
        $diff = $this->buildDiffSummary($current, $previewPlaybook);

        $intents = [];
        $previewPlaybookStored = [];
        foreach ($previewPlaybook as $block) {
            if (($block['proposed_examples'] ?? []) === [] && ($block['proposed_rules'] ?? []) === []) {
                continue;
            }
            $previewPlaybookStored[] = $block;
            $intents[] = [
                'intent' => $block['intent'],
                'candidate_count' => $block['candidate_count'],
                'proposed_examples' => $block['proposed_examples'],
                'proposed_rules' => $block['proposed_rules'],
            ];
        }

        return [
            'generated_at' => Carbon::now()->toIso8601String(),
            'candidate_count' => $previewable->count(),
            'approved' => $approved->filter(fn (SupportAiLearningCandidate $c): bool => $this->validateCandidateForPreview($c) === null)->count(),
            'staged' => $staged->filter(fn (SupportAiLearningCandidate $c): bool => $this->validateCandidateForPreview($c) === null)->count(),
            'skipped' => $skipped,
            'intents' => $intents,
            'preview_playbook' => $previewPlaybookStored,
            'prompt_additions' => $promptAdditions,
            'impact_estimate' => $impact,
            'diff_summary' => $diff,
            'future_autolearn_4_readiness' => [
                'ready_for_staged_simulation' => $previewable->count() > 0 && ($impact['safety_risk'] ?? 'high') !== 'high',
                'notes' => 'Preview only — AUTOLEARN-4 not implemented.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    public function writePreviewArtifacts(array $preview): void
    {
        $dir = $this->previewDirectory();
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put(
            $this->previewJsonPath(),
            json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"
        );

        File::put($this->previewMarkdownPath(), $this->buildMarkdownReport($preview));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readLatestPreview(): ?array
    {
        $path = $this->previewJsonPath();
        if (! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    public function buildMarkdownReport(array $preview): string
    {
        $lines = [];
        $lines[] = '# AI Support Preview Report';
        $lines[] = '';
        $lines[] = 'Generated: '.($preview['generated_at'] ?? 'unknown');
        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '- Approved candidates: '.($preview['approved'] ?? 0);
        $lines[] = '- Staged candidates: '.($preview['staged'] ?? 0);
        $lines[] = '- Previewable total: '.($preview['candidate_count'] ?? 0);

        $diff = is_array($preview['diff_summary'] ?? null) ? $preview['diff_summary'] : [];
        $lines[] = '- Current playbook examples: '.($diff['current_example_count'] ?? 0);
        $lines[] = '- Preview playbook examples: '.($diff['preview_example_count'] ?? 0);
        $lines[] = '- Added examples: +'.($diff['added_examples'] ?? 0);
        $lines[] = '';

        $impact = is_array($preview['impact_estimate'] ?? null) ? $preview['impact_estimate'] : [];
        $lines[] = '## Impact Estimate';
        $lines[] = '';
        $lines[] = '- Confidence: '.($impact['confidence'] ?? 'n/a');
        $lines[] = '- Expected operator adoption: '.($impact['expected_operator_adoption_pct'] ?? 'n/a').'%';
        $lines[] = '- Expected rewrite reduction: '.($impact['expected_rewrite_reduction_pct'] ?? 'n/a').'%';
        $lines[] = '- Safety risk: '.($impact['safety_risk'] ?? 'n/a');
        $lines[] = '';

        $playbook = is_array($preview['preview_playbook'] ?? null) ? $preview['preview_playbook'] : [];
        foreach ($playbook as $block) {
            $proposed = $block['proposed_examples'] ?? [];
            $rules = $block['proposed_rules'] ?? [];
            if ($proposed === [] && $rules === []) {
                continue;
            }

            $intent = (string) ($block['intent'] ?? 'unknown');
            $lines[] = '## Intent: '.$intent;
            $lines[] = '';
            $lines[] = '### Current behavior';
            $lines[] = '';
            $current = $block['current_examples'] ?? [];
            if ($current === []) {
                $lines[] = '- (no built-in examples for this intent key)';
            } else {
                foreach ($current as $example) {
                    $lines[] = '- '.$this->evaluation->maskSensitiveForReport((string) $example);
                }
            }
            $lines[] = '';
            $lines[] = '### Proposed additions';
            $lines[] = '';
            foreach ($proposed as $example) {
                $lines[] = '+ '.$this->evaluation->maskSensitiveForReport((string) $example);
            }
            foreach ($rules as $rule) {
                $lines[] = '+ [rule] '.$this->evaluation->maskSensitiveForReport((string) $rule);
            }
            $lines[] = '';
            $lines[] = '### Expected impact';
            $lines[] = '';
            $lines[] = '- Fewer ignored suggestions for '.$intent;
            $lines[] = '- Better operator similarity for '.$intent;
            $lines[] = '';
        }

        $readiness = is_array($preview['future_autolearn_4_readiness'] ?? null) ? $preview['future_autolearn_4_readiness'] : [];
        $lines[] = '## Future AUTOLEARN-4 Readiness';
        $lines[] = '';
        $ready = ! empty($readiness['ready_for_staged_simulation']);
        $lines[] = 'Ready for staged simulation: '.($ready ? 'YES' : 'NO');
        $lines[] = '';
        $lines[] = '_Preview only — no production prompt or playbook changes applied._';

        return implode("\n", $lines)."\n";
    }

    public function validateCandidateForPreview(SupportAiLearningCandidate $candidate): ?string
    {
        if (! in_array($candidate->status, [
            SupportAiLearningCandidate::STATUS_STAGED,
            SupportAiLearningCandidate::STATUS_APPROVED,
        ], true)) {
            return 'invalid_status:'.$candidate->status;
        }

        if ($candidate->status === SupportAiLearningCandidate::STATUS_REJECTED) {
            return 'rejected';
        }

        $intent = trim((string) ($candidate->intent ?? ''));
        if ($intent === '') {
            return 'missing_intent';
        }

        $text = trim((string) ($candidate->proposed_example ?? ''))
            ."\n".trim((string) ($candidate->proposed_rule ?? ''))
            ."\n".trim((string) ($candidate->after_example ?? ''));

        $evalFlags = is_array($candidate->evaluation_flags) ? $candidate->evaluation_flags : [];
        if (! empty($evalFlags['hard_fail'])) {
            return 'hard_fail_flagged';
        }

        $storedFlags = is_array($evalFlags['flags'] ?? null) ? $evalFlags['flags'] : [];
        foreach ($storedFlags as $flag) {
            if (in_array((string) $flag, self::HARD_FAIL_FLAGS, true)) {
                return 'hard_safety_flag:'.(string) $flag;
            }
        }

        $liveFlags = $this->evaluation->detectEvaluationFlags($this->learning->sanitizeLearningText($text));
        foreach ($liveFlags as $flag) {
            if (in_array($flag, self::HARD_FAIL_FLAGS, true)) {
                return 'hard_safety_flag:'.$flag;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function candidatePreviewRow(SupportAiLearningCandidate $candidate): array
    {
        $example = trim((string) ($candidate->proposed_example ?? ''));
        if ($example === '') {
            $example = trim((string) ($candidate->after_example ?? ''));
        }

        return [
            'id' => (int) $candidate->id,
            'status' => (string) $candidate->status,
            'candidate_type' => (string) $candidate->candidate_type,
            'language' => $candidate->language,
            'proposed_example' => $this->learning->sanitizeLearningText($example),
            'proposed_rule' => $this->learning->sanitizeLearningText((string) ($candidate->proposed_rule ?? '')),
            'evaluation_score' => $candidate->evaluation_score,
            'risk_level' => $candidate->risk_level,
        ];
    }
}
