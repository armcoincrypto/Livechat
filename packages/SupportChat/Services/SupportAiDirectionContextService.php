<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\Currency;
use App\Models\DirectionExchange;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use iEXPackages\WorkStatus\Services\WorkStatusService;
use Throwable;

/**
 * Read-only exchange direction availability for operator-assist AI drafts.
 * Reuses the same active-direction rules as ExchangerClient OperationsController.
 * Never mutates orders, directions, queues, or notifications.
 */
final class SupportAiDirectionContextService
{
    /** @var array<string, list<string>>|null */
    private static ?array $aliasToDesignations = null;

    /**
     * @return array{
     *     detected: bool,
     *     direction_lookup_attempted: bool,
     *     direction_lookup_found: bool,
     *     source_currency: string|null,
     *     target_currency: string|null,
     *     source_designation: string|null,
     *     target_designation: string|null,
     *     direction_normalized: string|null,
     *     availability_status: string|null,
     *     safe_summary: string|null,
     *     direction_context_error: string|null
     * }
     */
    public function lookupForDraft(?string $message, string $language = 'ru'): array
    {
        $context = $this->emptyContext();
        $message = trim((string) $message);
        if ($message === '') {
            return $context;
        }

        $pair = $this->extractPairFromMessage($message);
        if ($pair === null) {
            return $context;
        }

        $context['detected'] = true;
        $context['direction_lookup_attempted'] = true;

        if (! Schema::hasTable('direction_exchange') || ! Schema::hasTable('currencies')) {
            $context['direction_context_error'] = 'direction_tables_unavailable';

            return $context;
        }

        try {
            $fromDesignation = $this->resolveDesignation($pair['from']);
            $toDesignation = $this->resolveDesignation($pair['to']);

            if ($fromDesignation === null || $toDesignation === null) {
                $context['availability_status'] = 'unknown';
                $context['safe_summary'] = $this->unknownSummary($language);

                return $context;
            }

            $fromCurrency = $this->findCurrencyByDesignation($fromDesignation);
            $toCurrency = $this->findCurrencyByDesignation($toDesignation);

            if ($fromCurrency === null || $toCurrency === null) {
                $context['availability_status'] = 'unknown';
                $context['direction_normalized'] = $fromDesignation.' → '.$toDesignation;
                $context['safe_summary'] = $this->unknownAssetSummary($fromDesignation, $toDesignation, $language);

                return $context;
            }

            $context['source_designation'] = $fromDesignation;
            $context['target_designation'] = $toDesignation;
            $context['source_currency'] = $this->currencyLabel($fromCurrency);
            $context['target_currency'] = $this->currencyLabel($toCurrency);
            $context['direction_normalized'] = $fromDesignation.' → '.$toDesignation;

            $availability = $this->resolveAvailability($fromDesignation, $toDesignation, $fromCurrency, $toCurrency, $language);
            $context['direction_lookup_found'] = true;
            $context['availability_status'] = $availability['status'];
            $context['safe_summary'] = $availability['summary'];

            return $context;
        } catch (Throwable) {
            $context['direction_context_error'] = 'lookup_failed';

            return $context;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function buildPromptBlock(array $context): string
    {
        if (empty($context['direction_lookup_attempted'])) {
            return '';
        }

        $lines = ['--- verified direction availability (read-only admin DB) ---'];
        $lines[] = 'direction_detected: '.(! empty($context['detected']) ? 'true' : 'false');
        $lines[] = 'direction_lookup_found: '.(! empty($context['direction_lookup_found']) ? 'true' : 'false');

        foreach ([
            'direction_normalized',
            'source_currency',
            'target_currency',
            'source_designation',
            'target_designation',
            'availability_status',
            'safe_summary',
        ] as $key) {
            $value = $context[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $lines[] = $key.': '.$value;
            }
        }

        if (! empty($context['direction_context_error'])) {
            $lines[] = 'direction_context_error: '.$context['direction_context_error'];
        }

        $status = (string) ($context['availability_status'] ?? '');
        if ($status !== '' && $status !== 'unknown') {
            $lines[] = 'RULE: direction availability is verified — LEAD with availability status before wallet/requisites/order steps.';
            $lines[] = 'RULE: Do NOT open with "Уточните номер кошелька", "Проверим возможность обмена", or generic deferral when availability is known.';
            if (in_array($status, ['unsupported', 'paused', 'manual_review_required'], true)) {
                $lines[] = 'RULE: This direction is NOT open for automatic exchange — explain safely; do NOT ask for wallet/requisites to proceed.';
            } elseif ($status === 'supported') {
                $lines[] = 'RULE: Direction is supported — state that clearly; operator may help visitor continue exchange. Do NOT invent rates or reserves.';
            }
        } elseif ($status === 'unknown') {
            $lines[] = 'RULE: direction availability unknown — do not claim supported/unsupported; operator may verify manually.';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{from: string, to: string}|null
     */
    public function extractPairFromMessage(string $message): ?array
    {
        $normalized = mb_strtolower(trim($message), 'UTF-8');
        if ($normalized === '') {
            return null;
        }

        $hasExchangeIntent = preg_match(
            '/\b(?:поменять|обмен(?:ять|я|ять)?|exchange|swap|convert|купить|продать|change|обменять)\b/u',
            $normalized,
        ) === 1;

        $patterns = [
            '/\b(?:поменять|обмен(?:ять|я)?|exchange|swap|convert|обменять)\s+(.{1,40}?)\s+(?:на|to|->|→)\s+(.{1,40}?)(?:[,.!?]|$|\s+(?:смож|мож|please|и\b))/u',
            '/\b(.{1,30}?)\s+(?:на|to|->|→)\s+(.{1,30}?)(?:[,.!?]|$|\s+(?:смож|мож|please))/u',
        ];

        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $message, $matches) !== 1) {
                continue;
            }

            $from = $this->cleanPairToken((string) ($matches[1] ?? ''));
            $to = $this->cleanPairToken((string) ($matches[2] ?? ''));
            if ($from === '' || $to === '' || $this->isStopWord($from) || $this->isStopWord($to)) {
                continue;
            }

            if ($index === 1 && ! $hasExchangeIntent) {
                if ($this->resolveDesignation($from) === null || $this->resolveDesignation($to) === null) {
                    continue;
                }
            }

            return ['from' => $from, 'to' => $to];
        }

        return null;
    }

    public function resolveDesignation(string $token): ?string
    {
        $token = mb_strtolower(trim($token), 'UTF-8');
        if ($token === '') {
            return null;
        }

        $aliases = $this->aliasMap();
        if (isset($aliases[$token])) {
            return $aliases[$token][0];
        }

        $compact = preg_replace('/[\s._-]+/u', '', $token) ?? $token;
        if ($compact !== $token && isset($aliases[$compact])) {
            return $aliases[$compact][0];
        }

        if (preg_match('/^usdt\s*(trc20|trc-20|трц20?|трц)$/u', $token, $m) === 1) {
            return 'USDTTRC20';
        }
        if (preg_match('/^usdt\s*(erc20|erc-20|ерц20?|ерц)$/u', $token, $m) === 1) {
            return 'USDTERC20';
        }
        if (preg_match('/^tether\s*(trc20|erc20)?$/u', $token) === 1) {
            return str_contains($token, 'erc') ? 'USDTERC20' : 'USDTTRC20';
        }

        $upper = strtoupper(preg_replace('/\s+/u', '', $token) ?? $token);
        if ($upper !== '' && $this->findCurrencyByDesignation($upper) !== null) {
            return $upper;
        }

        foreach ($aliases as $alias => $designations) {
            if (str_contains($token, $alias) && count($designations) === 1) {
                return $designations[0];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyContext(): array
    {
        return [
            'detected' => false,
            'direction_lookup_attempted' => false,
            'direction_lookup_found' => false,
            'source_currency' => null,
            'target_currency' => null,
            'source_designation' => null,
            'target_designation' => null,
            'direction_normalized' => null,
            'availability_status' => null,
            'safe_summary' => null,
            'direction_context_error' => null,
        ];
    }

    /**
     * @return array{status: string, summary: string}
     */
    private function resolveAvailability(
        string $fromDesignation,
        string $toDesignation,
        Currency $fromCurrency,
        Currency $toCurrency,
        string $language,
    ): array {
        if ($this->isSiteOffline()) {
            return [
                'status' => 'paused',
                'summary' => $this->pausedSummary($fromCurrency, $toCurrency, $language, 'site_offline'),
            ];
        }

        $anyDirection = DirectionExchange::query()
            ->where('id_currency1', $fromCurrency->id)
            ->where('id_currency2', $toCurrency->id)
            ->first();

        if ($anyDirection === null) {
            return [
                'status' => 'unsupported',
                'summary' => $this->unsupportedSummary($fromCurrency, $toCurrency, $language),
            ];
        }

        if ((int) $anyDirection->status !== 1) {
            return [
                'status' => 'paused',
                'summary' => $this->pausedSummary($fromCurrency, $toCurrency, $language, 'direction_disabled'),
            ];
        }

        if ((int) $fromCurrency->status !== 0 || (int) $toCurrency->status !== 0) {
            return [
                'status' => 'paused',
                'summary' => $this->pausedSummary($fromCurrency, $toCurrency, $language, 'currency_disabled'),
            ];
        }

        if ($this->isScheduleBlocked($anyDirection)) {
            return [
                'status' => 'paused',
                'summary' => $this->pausedSummary($fromCurrency, $toCurrency, $language, 'schedule'),
            ];
        }

        if (trim((string) ($anyDirection->course_value ?? '')) === '' || (float) $anyDirection->course_value <= 0) {
            return [
                'status' => 'paused',
                'summary' => $this->pausedSummary($fromCurrency, $toCurrency, $language, 'no_rate'),
            ];
        }

        $active = $this->findActiveDirection($fromDesignation, $toDesignation);
        if ($active === null) {
            return [
                'status' => 'unsupported',
                'summary' => $this->unsupportedSummary($fromCurrency, $toCurrency, $language),
            ];
        }

        if ($this->requiresManualReview($active)) {
            return [
                'status' => 'manual_review_required',
                'summary' => $this->manualReviewSummary($fromCurrency, $toCurrency, $language),
            ];
        }

        return [
            'status' => 'supported',
            'summary' => $this->supportedSummary($fromCurrency, $toCurrency, $language),
        ];
    }

    private function findActiveDirection(string $fromCode, string $toCode): ?DirectionExchange
    {
        $from = function_exists('formatted_permitted_codes')
            ? formatted_permitted_codes($fromCode)
            : strtoupper($fromCode);
        $to = function_exists('formatted_permitted_codes')
            ? formatted_permitted_codes($toCode)
            : strtoupper($toCode);

        return DirectionExchange::query()
            ->where('status', '=', 1)
            ->whereHas('currency1', fn ($q) => $q->where('status', 0))
            ->whereHas('currency2', fn ($q) => $q->where('status', 0))
            ->whereHas('currency1', fn ($q) => $q->where('designation_xml', $from))
            ->whereHas('currency2', fn ($q) => $q->where('designation_xml', $to))
            ->with(['currency1:id,is_enabled_verification'])
            ->first();
    }

    private function findCurrencyByDesignation(string $designation): ?Currency
    {
        return Currency::query()
            ->select(['id', 'designation_xml', 'tech_name', 'status', 'visible_give', 'visible_receiving', 'is_enabled_verification'])
            ->with([
                'payment:id,name',
                'code_currency:id,name',
            ])
            ->where('designation_xml', $designation)
            ->first();
    }

    private function requiresManualReview(DirectionExchange $direction): bool
    {
        if ((int) ($direction->is_hidden_order_pay ?? 0) === 1) {
            return true;
        }

        if ((int) ($direction->direction_verification_type ?? 0) === 1) {
            return true;
        }

        if ((int) ($direction->identity_verification_type ?? 0) === 1) {
            $rules = is_array($direction->identity_verification_rules ?? null)
                ? $direction->identity_verification_rules
                : [];
            $mode = strtolower((string) ($rules['mode'] ?? 'disabled'));
            if ($mode !== '' && $mode !== 'disabled') {
                return true;
            }
        }

        $currency = $direction->currency1;
        if ($currency !== null && (int) ($currency->is_enabled_verification ?? 0) === 1) {
            return true;
        }

        return false;
    }

    private function isScheduleBlocked(DirectionExchange $direction): bool
    {
        if ((int) ($direction->is_enabled_exchange ?? 0) !== 1) {
            return false;
        }

        $from = trim((string) ($direction->from_on_time ?? ''));
        $to = trim((string) ($direction->to_on_time ?? ''));
        if ($from === '' || $to === '') {
            return true;
        }

        $now = Carbon::now()->format('H:i');

        if ($from <= $to) {
            return ! ($now >= $from && $now <= $to);
        }

        return ! ($now >= $from || $now <= $to);
    }

    private function isSiteOffline(): bool
    {
        try {
            return app(WorkStatusService::class)->isOffline();
        } catch (Throwable) {
            return false;
        }
    }

    private function currencyLabel(Currency $currency): string
    {
        $payment = trim((string) ($currency->payment?->name ?? ''));
        $code = trim((string) ($currency->code_currency?->name ?? ''));
        if ($payment !== '' && $code !== '' && mb_strtolower($payment, 'UTF-8') !== mb_strtolower($code, 'UTF-8')) {
            return $payment.' '.$code;
        }

        return $payment !== '' ? $payment : (string) ($currency->designation_xml ?? $currency->tech_name ?? 'unknown');
    }

    /**
     * @return array<string, list<string>>
     */
    private function aliasMap(): array
    {
        if (self::$aliasToDesignations !== null) {
            return self::$aliasToDesignations;
        }

        $map = [
            'ерц' => [],
            'erc20' => [],
            'erc' => [],
            'трц' => [],
            'trc20' => [],
            'trc' => [],
            'монеро' => [],
            'monero' => [],
            'биток' => [],
            'биткоин' => [],
            'bitcoin' => [],
            'btc' => [],
            'эфир' => [],
            'ethereum' => [],
            'eth' => [],
            'сбер' => [],
            'sber' => [],
            'sberbank' => [],
            'тинькофф' => [],
            'tinkoff' => [],
            'tcs' => [],
            'сбп' => [],
            'sbp' => [],
            'usdt' => [],
        ];

        try {
            $currencies = Currency::query()
                ->select(['id', 'designation_xml', 'tech_name'])
                ->where('status', 0)
                ->get();

            $designations = [];
            foreach ($currencies as $currency) {
                $code = strtoupper(trim((string) $currency->designation_xml));
                if ($code === '') {
                    continue;
                }
                $designations[$code] = true;
            }

            $assign = static function (array &$map, string $alias, string $designation) use ($designations): void {
                if (! isset($designations[$designation])) {
                    return;
                }
                if (! in_array($designation, $map[$alias], true)) {
                    $map[$alias][] = $designation;
                }
            };

            foreach (['USDTERC20', 'USDCERC20'] as $code) {
                $assign($map, 'ерц', $code);
                $assign($map, 'erc20', $code);
                $assign($map, 'erc', $code);
            }
            $assign($map, 'трц', 'USDTTRC20');
            $assign($map, 'trc20', 'USDTTRC20');
            $assign($map, 'trc', 'USDTTRC20');
            $assign($map, 'usdt', 'USDTTRC20');
            $assign($map, 'монеро', 'XMR');
            $assign($map, 'monero', 'XMR');
            $assign($map, 'биток', 'BTC');
            $assign($map, 'биткоин', 'BTC');
            $assign($map, 'bitcoin', 'BTC');
            $assign($map, 'btc', 'BTC');
            $assign($map, 'эфир', 'ETH');
            $assign($map, 'ethereum', 'ETH');
            $assign($map, 'eth', 'ETH');
            $assign($map, 'сбер', 'SBERRUB');
            $assign($map, 'sber', 'SBERRUB');
            $assign($map, 'sberbank', 'SBERRUB');
            $assign($map, 'тинькофф', 'TCSBRUB');
            $assign($map, 'tinkoff', 'TCSBRUB');
            $assign($map, 'tcs', 'TCSBRUB');
            $assign($map, 'сбп', 'SBPRUB');
            $assign($map, 'sbp', 'SBPRUB');
        } catch (Throwable) {
            // Static fallbacks only.
        }

        foreach ($map as $alias => $codes) {
            if ($codes === [] && $alias === 'ерц') {
                $map[$alias] = ['USDTERC20'];
            }
        }

        return self::$aliasToDesignations = $map;
    }

    private function cleanPairToken(string $token): string
    {
        $token = trim($token);
        $token = preg_replace('/^(?:хочу|хотел(?:а|и)?|please|want\s+to)\s+/iu', '', $token) ?? $token;
        $token = preg_replace('/\s+(?:сможете|можете|please).*$/iu', '', $token) ?? $token;

        return trim($token);
    }

    private function isStopWord(string $token): bool
    {
        return in_array(mb_strtolower($token, 'UTF-8'), ['день', 'здравствуйте', 'добрый', 'hello', 'hi'], true);
    }

    private function normalizeLocale(string $language): string
    {
        $language = strtolower(trim($language));

        return match ($language) {
            'en', 'uk', 'ka' => $language,
            default => 'ru',
        };
    }

    private function supportedSummary(Currency $from, Currency $to, string $language): string
    {
        $locale = $this->normalizeLocale($language);
        $dir = $this->currencyLabel($from).' → '.$this->currencyLabel($to);

        return $locale === 'en'
            ? "Direction {$dir} is supported. Operator can help the visitor continue the exchange."
            : "Направление {$dir} доступно. Оператор может помочь клиенту продолжить обмен.";
    }

    private function unsupportedSummary(Currency $from, Currency $to, string $language): string
    {
        $locale = $this->normalizeLocale($language);
        $dir = $this->currencyLabel($from).' → '.$this->currencyLabel($to);

        return $locale === 'en'
            ? "Direction {$dir} is not available on the exchange."
            : "Направление {$dir} сейчас недоступно на обменнике.";
    }

    private function pausedSummary(Currency $from, Currency $to, string $language, string $reason): string
    {
        $locale = $this->normalizeLocale($language);
        $dir = $this->currencyLabel($from).' → '.$this->currencyLabel($to);

        return $locale === 'en'
            ? "Direction {$dir} is temporarily unavailable ({$reason})."
            : "Направление {$dir} временно недоступно ({$reason}).";
    }

    private function manualReviewSummary(Currency $from, Currency $to, string $language): string
    {
        $locale = $this->normalizeLocale($language);
        $dir = $this->currencyLabel($from).' → '.$this->currencyLabel($to);

        return $locale === 'en'
            ? "Direction {$dir} requires operator review before proceeding."
            : "Направление {$dir} требует проверки оператором перед продолжением.";
    }

    private function unknownSummary(string $language): string
    {
        $locale = $this->normalizeLocale($language);

        return $locale === 'en'
            ? 'Exchange direction could not be determined from the message.'
            : 'Не удалось определить направление обмена из сообщения.';
    }

    private function unknownAssetSummary(string $from, string $to, string $language): string
    {
        $locale = $this->normalizeLocale($language);

        return $locale === 'en'
            ? "Could not map pair {$from} → {$to} to a known exchange asset."
            : "Не удалось сопоставить пару {$from} → {$to} с известным активом обменника.";
    }
}
