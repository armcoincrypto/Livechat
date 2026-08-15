<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus\Enums;

/**
 * BaseMode
 *
 * Базовый режим работы:
 * - schedule: управление по расписанию (JobSchedule), допускает override
 * - manual:   управление вручную, расписание не участвует
 */
enum BaseMode: string
{
    case Schedule = 'schedule';
    case Manual   = 'manual';

    /**
     * Нормализует произвольный ввод (string/int/bool/enum/null) к валидному режиму.
     */
    public static function fromValue(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return match (strtolower(trim((string) $value))) {
            self::Manual->value => self::Manual,
            default => self::Schedule,
        };
    }
}
