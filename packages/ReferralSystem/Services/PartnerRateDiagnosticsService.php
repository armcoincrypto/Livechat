<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Services;

use App\Models\CodeCurrency;
use App\Settings\ReferralConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class PartnerRateDiagnosticsService
{
    public function __construct(
        private readonly ReferralConfig $referralConfig
    ) {}

    public function diagnose(string $giveCode, string $bonusCode, float $giveAmount): array
    {
        $giveCode  = $this->norm($giveCode);
        $bonusCode = $this->norm($bonusCode);

        $giveCC  = $this->findCodeCurrency($giveCode);
        $bonusCC = $this->findCodeCurrency($bonusCode);

        $report = [
            'input' => [
                'give_code'   => $giveCode,
                'give_amount' => $giveAmount,
                'bonus_code'  => $bonusCode,
            ],
            'code_currency' => [
                'give'  => $this->serializeCC($giveCC),
                'bonus' => $this->serializeCC($bonusCC),
            ],
            'probes' => [
                'targets' => [],
            ],
            'decision' => [
                'method'        => 'failed', // one_to_one | internal_rate | calculator | failed
                'selected_to'   => null,
                'fallback_used' => false,
                'rate'          => null,
                'converted'     => null,
                'reason'        => null,
                'source'        => null,
                'used_pair'     => null,
            ],
            'missing' => [
                'give_code_currency'  => $giveCC === null,
                'bonus_code_currency' => $bonusCC === null,
            ],
        ];

        if ($giveAmount <= 0.0 || !is_finite($giveAmount)) {
            $report['decision'] = $this->decisionFailed('Некорректная сумма для проверки');
            return $report;
        }

        // Альтернативные коды из настроек (вручную добавленные в админке)
        $fallbackCodes = $this->fallbackCodes();

        /**
         * 1:1 правило только для USD (как у тебя было),
         * но список "стабильных" берём уже из настроек.
         * По умолчанию сюда входит USD и всё, что указано в fallbackCodes (например USDT, USDC).
         */
        if ($bonusCode === 'USD' && in_array($giveCode, $this->stableGroupForUsd($fallbackCodes), true)) {
            $report['decision'] = $this->decisionOneToOne(
                selectedTo: 'USD',
                amount: $giveAmount,
                reason: 'Для USD расчётов выбранный код считается 1 к 1 (используется группа альтернативных кодов)',
                usedPair: ['from' => $giveCode, 'to' => 'USD']
            );
            return $report;
        }

        if ($bonusCode === 'RUB' && $giveCode === 'RUB') {
            $report['decision'] = $this->decisionOneToOne(
                selectedTo: 'RUB',
                amount: $giveAmount,
                reason: 'Для RUB расчётов рубль считается 1 к 1',
                usedPair: ['from' => 'RUB', 'to' => 'RUB']
            );
            return $report;
        }

        if ($giveCode === $bonusCode) {
            $report['decision'] = $this->decisionOneToOne(
                selectedTo: $bonusCode,
                amount: $giveAmount,
                reason: 'Валюта "Отдаю" совпадает с валютой расчётов — пересчёт не нужен',
                usedPair: ['from' => $giveCode, 'to' => $bonusCode]
            );
            return $report;
        }

        // Приоритет: internal_rate (если задан — используем только его)
        $internalRate = $this->normalizeInternalRate($giveCC?->internal_rate);
        if ($internalRate !== null) {
            $report['decision'] = [
                'method'        => 'internal_rate',
                'selected_to'   => $bonusCode,
                'fallback_used' => false,
                'rate'          => $internalRate,
                'converted'     => $giveAmount * $internalRate,
                'reason'        => 'Используется внутренний (ручной) курс из настроек кода валюты',
                'source'        => [
                    'key'       => 'internal_rate',
                    'title'     => 'Внутренний курс (ручной)',
                    'model'     => CodeCurrency::class,
                    'table'     => $giveCC?->getTable(),
                    'record_id' => $giveCC?->getKey(),
                    'updated_at'=> $giveCC?->updated_at?->toIso8601String(),
                    'raw'       => [
                        'currency' => $giveCode,
                        'internal_rate' => $internalRate,
                    ],
                ],
                'used_pair'     => ['from' => $giveCode, 'to' => $bonusCode],
            ];

            return $report;
        }

        // internal_rate нет — ищем курс в источниках + применяем альтернативы (fallbackCodes)
        $targets = $this->targetsByBonus($bonusCode, $fallbackCodes);

        $best = null;
        foreach ($targets as $i => $target) {
            $probe = $this->probeMetaRate($giveCode, $target, $fallbackCodes);

            $report['probes']['targets'][] = [
                'target'      => $target,
                'available'   => (bool) ($probe['available'] ?? false),
                'rate'        => $probe['rate'] ?? null,
                'converted_1' => $probe['converted_1'] ?? null,
                'reason'      => $probe['reason'] ?? null,
                'source'      => $probe['source'] ?? null,
                'used_pair'   => $probe['used_pair'] ?? null,
            ];

            if (!empty($probe['available']) && $best === null) {
                $best = [
                    'target' => (string) $target,
                    'index'  => (int) $i,
                    'rate'   => $probe['rate'] ?? null,
                ];
            }
        }

        if ($best !== null) {
            $meta = $this->converterMeta($giveCode, $best['target'], $giveAmount, $fallbackCodes);

            $converted = (float) ($meta['result'] ?? 0);
            $rate      = $meta['rate'] ?? $best['rate'];

            $report['decision'] = [
                'method'        => 'calculator',
                'selected_to'   => $best['target'],
                'fallback_used' => $best['index'] > 0,
                'rate'          => (is_numeric($rate) && is_finite((float) $rate) && (float) $rate > 0) ? (float) $rate : null,
                'converted'     => ($converted > 0.0 && is_finite($converted)) ? $converted : null,
                'reason'        => $best['index'] > 0
                    ? 'Основной вариант недоступен — применён запасной вариант'
                    : 'Курс найден и используется напрямую',
                'source'        => $meta['source'] ?? null,
                'used_pair'     => $meta['used_pair'] ?? null,
            ];

            return $report;
        }

        $report['decision'] = $this->decisionFailed('Курс не найден');
        return $report;
    }

    public function diagnoseAll(string $bonusCode, float $amount = 1.0): array
    {
        $bonusCode = $this->norm($bonusCode);
        if ($amount <= 0.0 || !is_finite($amount)) {
            $amount = 1.0;
        }

        /** @var Collection<int, CodeCurrency> $rows */
        $rows = CodeCurrency::query()
            ->select(['id', 'name', 'internal_rate'])
            ->orderBy('name')
            ->get();

        $items = [];
        $stats = [
            'one_to_one' => 0,
            'calculator' => 0,
            'internal_rate' => 0,
            'failed' => 0,
            'fallback_used' => 0,
            'missing_give_code_currency' => 0,
            'missing_bonus_code_currency' => 0,
        ];

        foreach ($rows as $cc) {
            $item = $this->diagnose((string) $cc->name, $bonusCode, $amount);
            $items[] = $item;

            $method = (string) ($item['decision']['method'] ?? 'failed');
            if (isset($stats[$method])) {
                $stats[$method]++;
            } else {
                $stats['failed']++;
            }

            if (!empty($item['decision']['fallback_used'])) {
                $stats['fallback_used']++;
            }

            if (!empty($item['missing']['give_code_currency'])) {
                $stats['missing_give_code_currency']++;
            }

            if (!empty($item['missing']['bonus_code_currency'])) {
                $stats['missing_bonus_code_currency']++;
            }
        }

        return [
            'bonus_code' => $bonusCode,
            'amount'     => $amount,
            'total'      => count($items),
            'stats'      => $stats,
            'items'      => $items,
        ];
    }

    // ---------------- Helpers ----------------

    private function norm(string $code): string
    {
        return Str::upper(trim($code));
    }

    private function normalizeInternalRate(mixed $rate): ?float
    {
        if (!is_numeric($rate)) {
            return null;
        }
        $rate = (float) $rate;

        return (is_finite($rate) && $rate > 0.0) ? $rate : null;
    }

    private function findCodeCurrency(string $name): ?CodeCurrency
    {
        if ($name === '') {
            return null;
        }

        return CodeCurrency::query()->where('name', $name)->first();
    }

    private function serializeCC(?CodeCurrency $cc): ?array
    {
        if ($cc === null) {
            return null;
        }

        return [
            'id'            => $cc->id,
            'name'          => (string) $cc->name,
            'internal_rate' => (float) ($cc->internal_rate ?? 0),
        ];
    }

    /**
     * Забираем альтернативные коды из настроек ReferralConfig.
     * Ожидаем array<string>. Если там мусор — чистим.
     */
    private function fallbackCodes(): array
    {
        $raw = $this->referralConfig->referralFallbackCodeCurrencies();

        if (!is_array($raw)) {
            return [];
        }

        $cleaned = [];
        foreach ($raw as $v) {
            $code = $this->norm((string) $v);
            if ($code !== '') {
                $cleaned[] = $code;
            }
        }

        return array_values(array_unique($cleaned));
    }

    private function stableGroupForUsd(array $fallbackCodes): array
    {
        // USD + альтернативы (например USDT, USDC, ...)
        $group = array_merge(['USD'], $fallbackCodes);
        $group = array_values(array_unique(array_map(fn($x) => $this->norm((string) $x), $group)));

        return array_values(array_filter($group, fn($x) => $x !== ''));
    }

    private function targetsByBonus(string $bonusCode, array $fallbackCodes): array
    {
        // Если расчёты в USD/USDT/USDC — пробуем основной и альтернативы.
        // Для остальных валют — только сама валюта.
        $bonusCode = $this->norm($bonusCode);

        if (in_array($bonusCode, $this->stableGroupForUsd($fallbackCodes), true)) {
            $targets = array_merge([$bonusCode], $fallbackCodes);
            $targets = array_values(array_unique(array_map(fn($x) => $this->norm((string) $x), $targets)));

            return array_values(array_filter($targets, fn($x) => $x !== ''));
        }

        return [$bonusCode];
    }

    private function decisionOneToOne(string $selectedTo, float $amount, string $reason, ?array $usedPair = null): array
    {
        return [
            'method'        => 'one_to_one',
            'selected_to'   => $selectedTo,
            'fallback_used' => false,
            'rate'          => 1.0,
            'converted'     => $amount,
            'reason'        => $reason,
            'source'        => ['key' => 'one_to_one', 'title' => 'Без пересчёта (1 к 1)'],
            'used_pair'     => $usedPair,
        ];
    }

    private function decisionFailed(string $reason): array
    {
        return [
            'method'        => 'failed',
            'selected_to'   => null,
            'fallback_used' => false,
            'rate'          => null,
            'converted'     => null,
            'reason'        => $reason,
            'source'        => null,
            'used_pair'     => null,
        ];
    }

    private function probeMetaRate(string $from, string $to, array $fallbackCodes): array
    {
        $from = $this->norm($from);
        $to   = $this->norm($to);

        if ($from === '' || $to === '') {
            return [
                'available' => false,
                'rate' => null,
                'converted_1' => null,
                'reason' => 'Пустой код валюты',
                'source' => null,
                'used_pair' => null,
            ];
        }

        if ($from === $to) {
            return [
                'available' => true,
                'rate' => 1.0,
                'converted_1' => 1.0,
                'reason' => 'Пересчёт не нужен',
                'source' => ['key' => 'identity', 'title' => '1 к 1 (одинаковые коды)'],
                'used_pair' => ['from' => $from, 'to' => $to],
            ];
        }

        $meta = $this->converterMeta($from, $to, 1.0, $fallbackCodes);

        if (!empty($meta['ok'])) {
            return [
                'available' => true,
                'rate' => (float) ($meta['rate'] ?? 0),
                'converted_1' => (float) ($meta['result'] ?? 0),
                'reason' => $meta['source']['title'] ?? 'Курс найден',
                'source' => $meta['source'] ?? null,
                'used_pair' => $meta['used_pair'] ?? ['from' => $from, 'to' => $to],
            ];
        }

        return [
            'available' => false,
            'rate' => null,
            'converted_1' => null,
            'reason' => $meta['reason'] ?? 'Курс не найден',
            'source' => $meta['source'] ?? null,
            'used_pair' => $meta['used_pair'] ?? null,
        ];
    }

    private function converterMeta(string $from, string $to, float $amount, array $fallbackCodes): array
    {
        $from = $this->norm($from);
        $to   = $this->norm($to);

        if (function_exists('calculator_converter_meta')) {
            $meta = calculator_converter_meta($from, $to, $amount, $fallbackCodes);

            if (!is_array($meta)) {
                return [
                    'ok' => false,
                    'from' => $from,
                    'to' => $to,
                    'amount' => $amount,
                    'result' => null,
                    'rate' => null,
                    'source' => null,
                    'used_pair' => null,
                    'reason' => 'Внутренняя ошибка: неверный формат ответа конвертера',
                ];
            }

            return $meta + [
                    'ok' => false,
                    'from' => $from,
                    'to' => $to,
                    'amount' => $amount,
                    'result' => null,
                    'rate' => null,
                    'source' => null,
                    'used_pair' => null,
                    'reason' => null,
                ];
        }

        // fallback без meta
        if (function_exists('calculator_converter')) {
            $result = (float) calculator_converter($from, $to, $amount);
            if ($result > 0.0 && is_finite($result)) {
                $rate = (float) calculator_converter($from, $to, 1.0);

                return [
                    'ok' => true,
                    'from' => $from,
                    'to' => $to,
                    'amount' => $amount,
                    'result' => $result,
                    'rate' => ($rate > 0.0 && is_finite($rate)) ? $rate : null,
                    'source' => [
                        'key' => 'basic_converter',
                        'title' => 'Курс найден (детали источника недоступны)',
                        'model' => '',
                        'table' => null,
                        'record_id' => null,
                        'updated_at' => null,
                        'raw' => null,
                    ],
                    'used_pair' => ['from' => $from, 'to' => $to],
                    'reason' => null,
                ];
            }
        }

        return [
            'ok' => false,
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
            'result' => null,
            'rate' => null,
            'source' => null,
            'used_pair' => null,
            'reason' => 'Курс не найден',
        ];
    }
}
