<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ReserveLedgerAction
 *
 * Типы операций по резерву.
 * Используются в reserve_ledgers.action
 */
enum ReserveLedgerAction: string
{
    /**
     * Списание резерва OUT при переходе заявки в работу (статусы 3/7)
     */
    case HOLD_OUT = 'hold_out';

    /**
     * Зачисление резерва IN при успешном завершении заявки (статус 4)
     */
    case COMMIT_IN = 'commit_in';

    /**
     * Возврат резерва OUT при отмене / пересчёте
     */
    case RELEASE_OUT = 'release_out';

    /**
     * Ручная корректировка администратором
     */
    case MANUAL_ADJUST = 'manual_adjust';

    /**
     * Системная синхронизация / исправление
     */
    case SYNC = 'sync';

    /**
     * Знак операции для delta.
     *
     * +1 → увеличение резерва
     * -1 → уменьшение резерва
     *  0 → нейтральная операция (manual / sync с абсолютными значениями)
     */
    public function sign(): int
    {
        return match ($this) {
            self::HOLD_OUT => -1,
            self::COMMIT_IN => +1,
            self::RELEASE_OUT => +1,
            self::MANUAL_ADJUST,
            self::SYNC => 0,
        };
    }
}
