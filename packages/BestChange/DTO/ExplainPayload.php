<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\DTO;

/**
 * ExplainPayload
 *
 * Структурированное объяснение расчёта курса BestChange.
 * Используется для аудита, аналитики и отображения в админке.
 */
final class ExplainPayload implements \JsonSerializable
{
    public function __construct(
        public readonly array $strategy,
        public readonly array $market,
        public readonly array $selection,
        public readonly array $rejected,
        public readonly array $fallback,
        public readonly array $timestamps,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'strategy'   => $this->strategy,
            'market'     => $this->market,
            'selection'  => $this->selection,
            'rejected'   => $this->rejected,
            'fallback'   => $this->fallback,
            'timestamps' => $this->timestamps,
        ];
    }
}
