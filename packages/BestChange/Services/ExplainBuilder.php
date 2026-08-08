<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use iEXPackages\BestChange\DTO\RateSelectionPolicy;

/**
 * ExplainBuilder
 *
 * Формирует explain_payload для bestchange_directions.
 *
 * Задача:
 * - сохранить краткое объяснение того, как был рассчитан курс:
 *   - стратегия выбора
 *   - статистика рынка (сколько строк пришло/прошло фильтр)
 *   - причины отбраковки
 *   - выбранная строка (changer + marks + extra)
 *   - качество выбранной строки (Anti-Fake score)
 *
 * Примечание:
 * - Это не "лог ошибок". Это структурированное объяснение результата расчёта.
 * - Формат intentionally JSON-friendly (array -> json_encode).
 */
final class ExplainBuilder
{
    /**
     * Внутреннее хранилище payload.
     *
     * @var array<string,mixed>
     */
    private array $data = [];

    /**
     * Получить новый builder (чистый).
     *
     * Используется в цикле по направлениям:
     * $explain = $this->explainBuilder->fresh();
     */
    public function fresh(): self
    {
        return new self();
    }

    /**
     * Базовый контекст: ключ пары и стратегия.
     */
    public function setBaseContext(string $pairKey, RateSelectionPolicy $policy): void
    {
        $this->data['pair_key'] = $pairKey;

        $this->data['strategy'] = [
            'mode'       => $policy->mode,
            'type_field' => $policy->typeField,
            'sort_order' => $policy->sortOrder,
            'top_n'      => $policy->topN,
        ];

        $this->data['timestamps'] = [
            'calculated_at' => now()->toISOString(),
        ];
    }

    /**
     * Статистика рынка.
     */
    public function setMarketStats(int $rowsTotal, int $rowsAfterFilter): void
    {
        $this->data['market'] = [
            'rows_total'        => max(0, $rowsTotal),
            'rows_after_filter' => max(0, $rowsAfterFilter),
        ];
    }

    /**
     * Причины отбраковки строк (для Explain/Audit).
     *
     * Пример структуры:
     * [
     *   "blacklist" => 2,
     *   "invalid_rate" => 5,
     *   "anti_fake_low_score" => 7,
     *   "anti_fake_reason:unstable" => 3,
     * ]
     *
     * @param array<string,int> $counters
     */
    public function setRejectedCounters(array $counters): void
    {
        // нормализация на случай кривых значений
        $normalized = [];
        foreach ($counters as $k => $v) {
            $key = trim((string)$k);
            if ($key === '') {
                continue;
            }
            $normalized[$key] = max(0, (int)$v);
        }

        $this->data['rejected'] = $normalized;
    }

    /**
     * Данные о выбранной строке.
     *
     * @param array<string,mixed> $selectedRow
     * @param string $sourceName
     * @param int|null $qualityScore 0..100 (если используется Anti-Fake Score)
     * @param array<string,int>|null $qualityReasons причины/штрафы Anti-Fake (если есть)
     */
    public function setSelection(
        array $selectedRow,
        string $sourceName,
        ?int $qualityScore = null,
        ?array $qualityReasons = null
    ): void {
        $changerId = 0;
        if (is_scalar($selectedRow['changer'] ?? null)) {
            $changerId = (int) $selectedRow['changer'];
        }

        $selection = [
            'selected_changer_id' => $changerId,
            'source_name'         => $sourceName,
            'marks'               => is_array($selectedRow['marks'] ?? null) ? $selectedRow['marks'] : [],
            'extra'               => is_array($selectedRow['extra'] ?? null) ? $selectedRow['extra'] : [],
        ];

        if ($qualityScore !== null) {
            $selection['quality_score'] = max(0, min(100, $qualityScore));
        }

        if (is_array($qualityReasons)) {
            // нормализуем причины
            $normalized = [];
            foreach ($qualityReasons as $k => $v) {
                $key = trim((string)$k);
                if ($key === '') continue;
                $normalized[$key] = max(0, (int)$v);
            }
            $selection['quality_reasons'] = $normalized;
        }

        $this->data['selection'] = $selection;
    }

    /**
     * Presences данные рынка (count/best).
     *
     * @param array{pair?:string,best?:string|float,count?:int}|null $presence
     */
    public function setPresence(?array $presence): void
    {
        if ($presence === null) {
            $this->data['presence'] = null;
            return;
        }

        $this->data['presence'] = [
            'pair' => isset($presence['pair']) ? (string)$presence['pair'] : null,
            'best' => isset($presence['best']) ? (string)$presence['best'] : null,
            'count' => isset($presence['count']) ? (int)$presence['count'] : null,
        ];
    }

    /**
     * Получить payload как массив (удобно для тестов/логов).
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Получить payload как JSON.
     */
    public function toJson(): string
    {
        return json_encode(
            $this->data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
