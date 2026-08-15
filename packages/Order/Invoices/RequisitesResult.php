<?php

namespace iEXPackages\Order\Invoices;

/**
 * Типобезопасный результат выдачи реквизитов.
 *
 * Обычным языком: это "коробочка" с двумя полями — откуда взяли реквизиты (mode)
 * и сами данные (data). Такой подход проще читать и тестировать, чем массивы с
 * произвольными ключами.
 */
final readonly class RequisitesResult
{
    public function __construct(
        public RequisitesMode $mode,
        public ?array         $data
    ) {}

    public static function merchant(?array $data): self
    {
        return new self(RequisitesMode::Merchant, $data);
    }

    public static function request(?array $data): self
    {
        return new self(RequisitesMode::Request, $data);
    }

    public static function manual(?array $data): self
    {
        return new self(RequisitesMode::Manual, $data);
    }
}
