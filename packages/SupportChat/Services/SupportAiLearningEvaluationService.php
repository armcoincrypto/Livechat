<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiLearningCandidate;
use App\Models\SupportAiLearningEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Deterministic evaluation of pending learning candidates against stored events.
 * Never modifies production prompts or playbook code.
 */
final class SupportAiLearningEvaluationService
{
    /** @var array<string, string> */
    private const HARD_FAIL_PATTERNS = [
        'fake_eta' => '/\b(?:within|in)\s+\d+\s*(?:min(?:ute)?s?|hours?|h)\b|\b(?:скоро|через\s+\d+\s*(?:мин|час))\b/iu',
        'fake_confirmation' => '/\b(?:confirmed|completed|done|payment received|подтверждено|завершена|платёж получен|платеж получен)\b/iu',
        'guarantee_claim' => '/\b(?:guarantee|guaranteed|will definitely|гарантируем|гарантир|обязательно|100\s*%|точно)\b/iu',
        'funds_safe_claim' => '/\b(?:funds?\s+(?:are\s+)?safe|funds sent|средства отправлены|средства в безопасности|деньги в безопас)\b/iu',
        'recovery_guarantee' => '/\b(?:we recovered|восстановим|вернём средства|guaranteed recovery)\b/iu',
        'asks_known_data_again' => '/\b(?:please\s+(?:send|provide|share)|пришлите|отправьте|укажите)\b.*\b(?:order|заявк|tx|hash|сеть|network)/iu',
        'unsupported_financial_claim' => '/\b(?:already paid|already sent|payment completed|уже выплачен|уже отправлен)\b/iu',
        'secret_leak_risk' => '/\b(?:sk-[a-zA-Z0-9]{10,}|\d{8,10}:[A-Za-z0-9_-]{30,}|seed phrase|mnemonic|private key)\b/iu',
    ];

    /** @var array<string, list<string>> */
    private const INTENT_KEYWORDS = [
        'wrong_network' => ['network', 'сеть', 'chain', 'blockchain', 'перевод', 'transfer', 'вариант'],
        'wrong_amount' => ['amount', 'сумм', 'partial', 'top-up', 'доплат', 'пересч'],
        'eta_request' => ['status', 'статус', 'check', 'провер', 'update', 'уточн'],
        'funds_safety_question' => ['status', 'verify', 'провер', 'уточн', 'безопас', 'safe'],
        'large_otc_exchange' => ['otc', 'amount', 'сумм', 'direction', 'направлен', 'услов'],
        'complaint_or_angry_customer' => ['understand', 'понима', 'sorry', 'извин', 'help', 'помо'],
        'payment_proof_only' => ['proof', 'скрин', 'screenshot', 'received', 'получ'],
        'missing_order_id' => ['order', 'заявк', 'number', 'номер', 'id'],
        'repeated_follow_up' => ['status', 'update', 'уточн', 'follow', 'ожида'],
    ];

    public function __construct(
        private readonly SupportAiLearningService $learning,
    ) {}

    public function isAvailable(): bool
    {
        return Schema::hasTable('support_ai_learning_candidates')
            && Schema::hasColumn('support_ai_learning_candidates', 'evaluation_score');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     candidate_id: int,
     *     type: string,
     *     intent: string|null,
     *     safety_score: float,
     *     relevance_score: float,
     *     operator_fit_score: float,
     *     conciseness_score: float,
     *     language_score: float,
     *     overall_score: float,
     *     flags: list<string>,
     *     hard_fail: bool,
     *     result: string,
     *     summary: string
     * }
     */
    public function evaluateCandidate(SupportAiLearningCandidate $candidate, array $options = []): array
    {
        $text = $this->candidateEvaluationText($candidate);
        $intent = (string) ($candidate->intent ?? 'unknown_context');
        $language = $candidate->language;

        $flags = $this->detectEvaluationFlags($text);
        if ($language !== null && trim((string) $language) !== '' && $this->scoreLanguageFit($text, $language) <= 30.0) {
            $flags[] = 'wrong_language';
            $flags = array_values(array_unique($flags));
        }

        $hardFail = $this->hasHardFailFlag($flags);

        $safetyScore = $hardFail ? 0.0 : 100.0;
        $relevanceScore = $this->scoreRelevance($text, $intent);
        $operatorFitScore = $this->scoreOperatorSimilarity($text, $intent, $language, $options);
        $concisenessScore = $this->scoreConciseness($text);
        $languageScore = $this->scoreLanguageFit($text, $language);

        if (in_array('too_long', $flags, true)) {
            $hardFail = true;
            $safetyScore = 0.0;
        }

        if (in_array('wrong_language', $flags, true)) {
            $hardFail = true;
            $safetyScore = 0.0;
        }

        $overall = $this->computeOverallScore(
            $safetyScore,
            $relevanceScore,
            $operatorFitScore,
            $concisenessScore,
            $languageScore,
        );

        $result = $hardFail ? 'rejected' : 'pending_review';
        if (! $hardFail && $overall >= 90.0 && $operatorFitScore >= 80.0 && $safetyScore >= 100.0) {
            $result = 'approved';
        } elseif (! $hardFail && $overall >= 75.0 && $safetyScore >= 100.0) {
            $result = 'staged';
        } elseif ($hardFail) {
            $result = 'rejected';
        } elseif ($overall < 75.0) {
            $result = 'rejected';
        }

        return [
            'candidate_id' => (int) $candidate->id,
            'type' => (string) $candidate->candidate_type,
            'intent' => $candidate->intent,
            'safety_score' => round($safetyScore, 1),
            'relevance_score' => round($relevanceScore, 1),
            'operator_fit_score' => round($operatorFitScore, 1),
            'conciseness_score' => round($concisenessScore, 1),
            'language_score' => round($languageScore, 1),
            'overall_score' => round($overall, 1),
            'flags' => $flags,
            'hard_fail' => $hardFail,
            'result' => $result,
            'summary' => $this->buildEvaluationSummary($candidate, $result, $flags, $overall),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function evaluatePendingCandidates(int $limit = 50, array $options = []): array
    {
        $limit = max(1, min(500, $limit));

        $candidates = SupportAiLearningCandidate::query()
            ->where('status', SupportAiLearningCandidate::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $results = [];
        foreach ($candidates as $candidate) {
            $results[] = $this->evaluateCandidate($candidate, $options);
        }

        return $results;
    }

    public function scoreRelevance(string $text, string $intent): float
    {
        $keywords = self::INTENT_KEYWORDS[$intent] ?? ['check', 'verify', 'status', 'провер', 'уточн', 'ответ'];
        if ($keywords === []) {
            return 70.0;
        }

        $lower = mb_strtolower($text, 'UTF-8');
        $hits = 0;
        foreach ($keywords as $keyword) {
            if (mb_strpos($lower, mb_strtolower($keyword, 'UTF-8'), 0, 'UTF-8') !== false) {
                $hits++;
            }
        }

        $ratio = $hits / count($keywords);

        return round(min(100.0, 40.0 + ($ratio * 60.0)), 1);
    }

    public function scoreSafety(string $text): float
    {
        $flags = $this->detectEvaluationFlags($text);

        return $this->hasHardFailFlag($flags) ? 0.0 : 100.0;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function scoreOperatorSimilarity(
        string $text,
        string $intent,
        ?string $language,
        array $options = [],
    ): float {
        $days = max(7, min(365, (int) ($options['days'] ?? 30)));
        $since = Carbon::now()->subDays($days);

        $query = SupportAiLearningEvent::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('operator_reply')
            ->where('intent', $intent);

        if ($language !== null && $language !== '') {
            $query->where('language', $language);
        }

        $replies = $query->orderByDesc('id')->limit(25)->pluck('operator_reply');

        if ($replies->isEmpty()) {
            return 55.0;
        }

        $best = 0.0;
        foreach ($replies as $reply) {
            $ratio = $this->learning->computeEditDistanceRatio($text, (string) $reply);
            if ($ratio > $best) {
                $best = $ratio;
            }
        }

        return round(min(100.0, $best * 100.0), 1);
    }

    public function scoreConciseness(string $text): float
    {
        $len = mb_strlen(trim($text), 'UTF-8');
        if ($len === 0) {
            return 0.0;
        }

        if ($len >= 60 && $len <= 280) {
            return 100.0;
        }

        if ($len < 60) {
            return round(max(50.0, 50.0 + ($len / 60.0) * 50.0), 1);
        }

        if ($len <= 400) {
            return round(max(40.0, 100.0 - (($len - 280) / 120.0) * 60.0), 1);
        }

        return 20.0;
    }

    public function scoreLanguageFit(string $text, ?string $expectedLanguage): float
    {
        if ($expectedLanguage === null || trim($expectedLanguage) === '') {
            return 100.0;
        }

        $detected = $this->detectTextLanguage($text);
        if ($detected === 'unknown') {
            return 85.0;
        }

        $expected = mb_strtolower(trim($expectedLanguage), 'UTF-8');
        if ($expected === $detected || str_starts_with($expected, $detected) || str_starts_with($detected, $expected)) {
            return 100.0;
        }

        return 30.0;
    }

    /**
     * @return list<string>
     */
    public function detectEvaluationFlags(string $text): array
    {
        $flags = [];

        foreach (self::HARD_FAIL_PATTERNS as $flag => $pattern) {
            if ($this->matchesUnsafePattern($text, $pattern)) {
                $flags[] = $flag;
            }
        }

        $len = mb_strlen(trim($text), 'UTF-8');
        if ($len > 400) {
            $flags[] = 'too_long';
        }

        return array_values(array_unique($flags));
    }

    public function buildEvaluationSummary(
        SupportAiLearningCandidate $candidate,
        string $result,
        array $flags,
        float $overallScore,
    ): string {
        $intent = $candidate->intent ?? 'unknown';
        $flagText = $flags === [] ? 'none' : implode(', ', $flags);

        return sprintf(
            'Intent %s evaluated as %s (score %.1f). Flags: %s.',
            $intent,
            $result,
            $overallScore,
            $flagText,
        );
    }

    public function maskSensitiveForReport(string $text): string
    {
        $text = preg_replace('/\bsk-[a-zA-Z0-9]{10,}\b/', '[api-key]', $text) ?? $text;
        $text = preg_replace('/\b\d{8,10}:[A-Za-z0-9_-]{30,}\b/', '[bot-token]', $text) ?? $text;
        $text = preg_replace('/\b0x[0-9a-fA-F]{40,64}\b/', '[tx…]', $text) ?? $text;
        $text = preg_replace('/\b[0-9a-fA-F]{64}\b/', '[tx…]', $text) ?? $text;
        $text = preg_replace('/\b(T[1-9A-HJ-NP-Za-km-z]{20,})\b/', '[wallet…]', $text) ?? $text;
        $text = preg_replace('/\b(\d{10,})\b/u', 'order #…', $text) ?? $text;

        if (mb_strlen($text, 'UTF-8') > 120) {
            $text = mb_substr($text, 0, 117, 'UTF-8').'…';
        }

        return $text;
    }

    private function candidateEvaluationText(SupportAiLearningCandidate $candidate): string
    {
        $parts = array_filter([
            (string) ($candidate->proposed_example ?? ''),
            (string) ($candidate->after_example ?? ''),
            (string) ($candidate->proposed_rule ?? ''),
        ], static fn (string $p): bool => trim($p) !== '');

        return $this->learning->sanitizeLearningText(implode("\n", $parts));
    }

    /**
     * @param  list<string>  $flags
     */
    private function hasHardFailFlag(array $flags): bool
    {
        $hard = [
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

        foreach ($flags as $flag) {
            if (in_array($flag, $hard, true)) {
                return true;
            }
        }

        return false;
    }

    private function matchesUnsafePattern(string $text, string $pattern): bool
    {
        return preg_match($pattern, $text) === 1;
    }

    private function computeOverallScore(
        float $safety,
        float $relevance,
        float $operatorFit,
        float $conciseness,
        float $language,
    ): float {
        return round(
            ($safety * 0.35)
            + ($relevance * 0.25)
            + ($operatorFit * 0.20)
            + ($conciseness * 0.10)
            + ($language * 0.10),
            1,
        );
    }

    private function detectTextLanguage(string $text): string
    {
        $cyrillic = preg_match_all('/[\p{Cyrillic}]/u', $text, $m) ?: 0;
        $latin = preg_match_all('/[\p{Latin}]/u', $text, $m2) ?: 0;

        if ($cyrillic > $latin && $cyrillic >= 3) {
            return 'ru';
        }

        if ($latin >= 3) {
            return 'en';
        }

        return 'unknown';
    }
}
