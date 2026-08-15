<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Enums;

/**
 * Тип эффекта — действие, которое применяем к заявке ПОСЛЕ успешной валидации.
 */
enum EffectType: string
{
    case FreezeScam = 'freeze_scam';
    case CardInfoSnapshot = 'card_info_snapshot';
    case AmlSnapshot = 'aml_snapshot';
}
