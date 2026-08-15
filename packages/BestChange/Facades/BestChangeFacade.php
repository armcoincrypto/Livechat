<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Facades;

use iEXPackages\BestChange\BestChange;
use iEXPackages\BestChange\Blacklist\BestChangeBlacklistClient;
use iEXPackages\BestChange\RatesConnection;
use Illuminate\Support\Facades\Facade;

/**
 * BestChangeFacade
 *
 * Фасад Laravel для доступа к модулю BestChange.
 *
 * Назначение:
 * - предоставляет удобный статический интерфейс к модулю BestChange
 * - скрывает работу с контейнером и внедрение зависимостей
 *
 * Примеры использования:
 * <code>
 * BestChange::rates()->getCities();
 * BestChange::rates()->getRates([[42, 93]]);
 *
 * BestChange::blacklist()->search('1CchKukDiLua24qbcTri7Pvi1jGNJCxJuw');
 * </code>
 *
 * @method static RatesConnection rates() Работа с курсами и справочниками BestChange
 * @method static BestChangeBlacklistClient blacklist() Работа с чёрным списком BestChange
 *
 * @see BestChange
 */
final class BestChangeFacade extends Facade
{
    /**
     * Ключ сервиса в контейнере.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'bestchange';
    }
}
