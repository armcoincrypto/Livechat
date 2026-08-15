<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist\DTO;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Результат проверки реквизитов/контактов по базе BestChange Blacklist.
 *
 * Важно:
 * - предназначено для внутренней бизнес-логики (маркировка "нужна ручная проверка")
 * - не содержит чувствительных данных (не возвращаем пользователю найденные контакты/описание)
 *
 * @implements Arrayable<string,mixed>
 */
final readonly class BlacklistCheckResult implements Arrayable
{
    public function __construct(
        /** Было ли найдено совпадение хотя бы по одному полю */
        public bool $requiresManualReview,
        /** Какое поле сработало (email/sell/buy/...) */
        public ?string $field,
        /** Техническая причина (для админки/логов), не для пользователя */
        public ?string $reasonCode,
    ) {}

    public static function ok(): self
    {
        return new self(false, null, null);
    }

    public static function hit(string $field, string $reasonCode = 'bestchange_blacklist_hit'): self
    {
        return new self(true, $field, $reasonCode);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'requires_manual_review' => $this->requiresManualReview,
            'field' => $this->field,
            'reason_code' => $this->reasonCode,
        ];
    }
}
